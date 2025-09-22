<?php
session_start();
require_once "config.php";
require_once "email_function.php"; // Include email function

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $requestId = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $adminId = $_SESSION['admin_id']; // Get admin ID
    $adminNote = isset($_POST['admin_note']) ? trim($_POST['admin_note']) : null;
    
    if ($requestId <= 0) {
        $_SESSION['error'] = "Invalid request ID";
        header("Location: medicine_requests.php");
        exit();
    }
    
    // Fetch request and user details for email
    $userQuery = "SELECT mr.full_name, mr.request_id, u.email 
                  FROM medicine_requests mr 
                  JOIN users u ON mr.user_id = u.id 
                  WHERE mr.id = :id";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bindParam(':id', $requestId);
    $userStmt->execute();
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = "User or request not found";
        header("Location: medicine_requests.php");
        exit();
    }
    
    $recipientName = $user['full_name'];
    $recipientEmail = $user['email'];
    $requestIdValue = $user['request_id'];
    
    // Check if the request exists and is in "pending" status
    $checkQuery = "SELECT id FROM medicine_requests WHERE id = :id AND request_status = 'pending'";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bindParam(':id', $requestId);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() == 0) {
        $_SESSION['error'] = "Request not found or already processed";
        header("Location: medicine_requests.php");
        exit();
    }
    
    // Check which action we're performing
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // DECLINE PROCESS
    if ($action === 'decline') {
        try {
            $conn->beginTransaction();
            
            // Update request status to "declined"
            $updateQuery = "UPDATE medicine_requests SET request_status = 'declined', note = :note WHERE id = :id";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bindParam(':id', $requestId);
            $updateStmt->bindParam(':note', $adminNote);
            $updateStmt->execute();
            
            // Update all medicines to declined
            $updateMedicinesQuery = "UPDATE requested_medicines SET status = 'declined' WHERE request_id = :request_id";
            $updateMedicinesStmt = $conn->prepare($updateMedicinesQuery);
            $updateMedicinesStmt->bindParam(':request_id', $requestId);
            $updateMedicinesStmt->execute();
            
            // Log decline action in medicine history
            $historyQuery = "INSERT INTO medicine_history (action_type, details, performed_by, created_at)
                           VALUES ('distribute', :details, :admin_id, CURRENT_TIMESTAMP)";
            $historyStmt = $conn->prepare($historyQuery);
            $historyDetails = json_encode([
                'request_id' => $requestIdValue,
                'status' => 'declined',
                'note' => $adminNote
            ]);
            $historyStmt->bindParam(':details', $historyDetails);
            $historyStmt->bindParam(':admin_id', $adminId);
            $historyStmt->execute();
            
            $conn->commit();
            
            // Send decline email
            $subject = "Medicine Request Declined - Request ID #$requestIdValue";
            $message = "
                <h2>Medicine Request Declined</h2>
                <p>Dear $recipientName,</p>
                <p>We regret to inform you that your medicine request (ID #$requestIdValue) has been declined by Maru-Health Barangay Marulas 3S Health Station.</p>
                <p><strong>Reason:</strong> " . ($adminNote ? htmlspecialchars($adminNote) : 'Your request could not be fulfilled at this time.') . "</p>
                <p><strong>Contact Us:</strong><br>
                Email: _mainaccount@maruhealth.site<br>
                Address: Barangay Marulas 3S Health Station</p>
                <p>Thank you for your understanding.</p>
                <p>Best regards,<br>Maru-Health Team</p>
            ";
            $emailResult = sendEmail($recipientEmail, $recipientName, $subject, $message);
            
            if (!$emailResult['success']) {
                error_log("Failed to send decline email for request #$requestIdValue: " . $emailResult['message']);
            }
            
            $_SESSION['success'] = "Request has been declined successfully";
            
            // For AJAX response
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo "Request declined successfully";
                exit();
            }
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['error'] = "Error declining request: " . $e->getMessage();
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo "Error: " . $e->getMessage();
                exit();
            }
        }
    }
    // APPROVAL PROCESS
    else {
        $claimBy = isset($_POST['claim_by']) ? $_POST['claim_by'] : null;
        $claimUntil = isset($_POST['claim_until']) ? $_POST['claim_until'] : null;
        
        if ($requestId <= 0 || empty($claimBy) || empty($claimUntil) || empty($_POST['approve_medicines'])) {
            $_SESSION['error'] = "Please fill in all required fields and select at least one medicine";
            header("Location: medicine_requests.php");
            exit();
        }
        
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Update the medicine request
            $updateQuery = "UPDATE medicine_requests SET 
                            request_status = 'to be claimed', 
                            claim_date = :claim_by, 
                            claim_until_date = :claim_until,
                            note = :note
                            WHERE id = :id";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bindParam(':claim_by', $claimBy);
            $updateStmt->bindParam(':claim_until', $claimUntil);
            $updateStmt->bindParam(':note', $adminNote);
            $updateStmt->bindParam(':id', $requestId);
            $updateStmt->execute();
            
            // Process approved medicines
            $approvedMedicinesList = [];
            $declinedMedicinesList = [];
            
            // Fetch all requested medicines for email content
            $medicinesQuery = "SELECT id, medicine_name, dosage, quantity, status 
                              FROM requested_medicines 
                              WHERE request_id = :request_id";
            $medicinesStmt = $conn->prepare($medicinesQuery);
            $medicinesStmt->bindParam(':request_id', $requestId);
            $medicinesStmt->execute();
            $requestedMedicines = $medicinesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Prepare queries for distribution and history
            $distributionQuery = "INSERT INTO medicine_distributions (request_id, requested_medicine_id, batch_id, quantity, status, created_at)
                                VALUES (:request_id, :requested_medicine_id, :batch_id, :quantity, 'reserved', CURRENT_TIMESTAMP)";
            $distributionStmt = $conn->prepare($distributionQuery);
            
            $historyQuery = "INSERT INTO medicine_history (catalog_id, batch_id, action_type, details, performed_by, created_at)
                           VALUES (:catalog_id, :batch_id, 'distribute', :details, :admin_id, CURRENT_TIMESTAMP)";
            $historyStmt = $conn->prepare($historyQuery);
            
            $updateStockQuery = "UPDATE medicine_batches SET 
                                stocks = stocks - :quantity,
                                stock_status = CASE 
                                    WHEN stocks <= 0 THEN 'Out of Stock'
                                    WHEN stocks <= (SELECT min_stock FROM medicines_catalog WHERE id = medicine_batches.catalog_id) THEN 'Low Stock'
                                    ELSE 'In Stock'
                                END
                                WHERE id = :batch_id";
            $updateStockStmt = $conn->prepare($updateStockQuery);
            
            // Process each requested medicine
            foreach ($requestedMedicines as $medicine) {
                $status = in_array($medicine['id'], $_POST['approve_medicines']) ? 'approved' : 'declined';
                
                // Update medicine status
                $updateMedicineQuery = "UPDATE requested_medicines SET status = :status WHERE id = :id";
                $updateMedicineStmt = $conn->prepare($updateMedicineQuery);
                $updateMedicineStmt->bindParam(':status', $status);
                $updateMedicineStmt->bindParam(':id', $medicine['id']);
                $updateMedicineStmt->execute();
                
                // Build email content
                $medicineDetails = htmlspecialchars($medicine['medicine_name'] . 
                    ($medicine['dosage'] ? " - " . $medicine['dosage'] : "") . 
                    " - Quantity: " . $medicine['quantity']);
                if ($status === 'approved') {
                    $approvedMedicinesList[] = $medicineDetails;
                } else {
                    $declinedMedicinesList[] = $medicineDetails;
                }
                
                // If approved, create distribution record and update stock
                if ($status === 'approved' && isset($_POST['distribute_medicines'][$medicine['id']]) && isset($_POST['approved_quantities'][$medicine['id']])) {
                    $batchId = intval($_POST['distribute_medicines'][$medicine['id']]);
                    $approvedQty = intval($_POST['approved_quantities'][$medicine['id']]);
                    
                    if ($approvedQty <= 0) {
                        throw new Exception("Invalid quantity for medicine ID {$medicine['id']}");
                    }
                    
                    // Verify stock availability
                    $stockCheckQuery = "SELECT mb.stocks, mb.catalog_id 
                                      FROM medicine_batches mb 
                                      WHERE mb.id = :batch_id";
                    $stockCheckStmt = $conn->prepare($stockCheckQuery);
                    $stockCheckStmt->bindParam(':batch_id', $batchId);
                    $stockCheckStmt->execute();
                    $stockInfo = $stockCheckStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$stockInfo || $stockInfo['stocks'] < $approvedQty) {
                        throw new Exception("Insufficient stock for medicine batch ID $batchId");
                    }
                    
                    // Create distribution record
                    $distributionStmt->bindParam(':request_id', $requestId);
                    $distributionStmt->bindParam(':requested_medicine_id', $medicine['id']);
                    $distributionStmt->bindParam(':batch_id', $batchId);
                    $distributionStmt->bindParam(':quantity', $approvedQty);
                    $distributionStmt->execute();
                    
                    // Update stock
                    $updateStockStmt->bindParam(':quantity', $approvedQty);
                    $updateStockStmt->bindParam(':batch_id', $batchId);
                    $updateStockStmt->execute();
                    
                    // Log distribution in history
                    $historyDetails = json_encode([
                        'request_id' => $requestIdValue,
                        'medicine_id' => $medicine['id'],
                        'medicine_name' => $medicine['medicine_name'],
                        'quantity' => $approvedQty,
                        'batch_id' => $batchId
                    ]);
                    $historyStmt->bindParam(':catalog_id', $stockInfo['catalog_id']);
                    $historyStmt->bindParam(':batch_id', $batchId);
                    $historyStmt->bindParam(':details', $historyDetails);
                    $historyStmt->bindParam(':admin_id', $adminId);
                    $historyStmt->execute();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Send approval email
            $subject = "Medicine Request Approved - Request ID #$requestIdValue";
            $approvedList = !empty($approvedMedicinesList) ? "<ul><li>" . implode("</li><li>", $approvedMedicinesList) . "</li></ul>" : "None";
            $declinedList = !empty($declinedMedicinesList) ? "<ul><li>" . implode("</li><li>", $declinedMedicinesList) . "</li></ul>" : "None";
            $noteSection = $adminNote ? "<p><strong>Note:</strong> " . htmlspecialchars($adminNote) . "</p>" : "";

            $message = "
                <h2>Medicine Request Approved</h2>
                <p>Dear $recipientName,</p>
                <p>We are pleased to inform you that your medicine request (ID #$requestIdValue) has been approved by Maru-Health Barangay Marulas 3S Health Station.</p>
                <h3>Request Details</h3>
                <p><strong>Approved Medicines:</strong><br>$approvedList</p>
                <p><strong>Declined Medicines:</strong><br>$declinedList</p>
                <p><strong>Claim Until:</strong> " . htmlspecialchars($claimUntil) . "</p>
                $noteSection
                <p>Please visit the health station during the specified period to claim your medicines. Bring a valid ID for verification.</p>
                <p><strong>Contact Us:</strong><br>
                Email: _mainaccount@maruhealth.site<br>
                Address: Barangay Marulas 3S Health Station</p>
                <p>Best regards,<br>Maru-Health Team</p>
            ";
            $emailResult = sendEmail($recipientEmail, $recipientName, $subject, $message);
            
            if (!$emailResult['success']) {
                error_log("Failed to send approval email for request #$requestIdValue: " . $emailResult['message']);
            }
            
            $_SESSION['success'] = "Medicine request has been approved and distribution recorded";
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['error'] = "Error processing request: " . $e->getMessage();
        }
    }
    
    // Redirect back to medicine requests page
    header("Location: medicine_requests.php");
    exit();
}

// Redirect if accessed directly
header("Location: medicine_requests.php");
exit();
?>