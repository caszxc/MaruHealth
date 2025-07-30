<?php
// process_request.php
session_start();
require_once "config.php";
require_once "email_function.php"; // Include email function

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $requestId = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $adminId = $_SESSION['admin_id']; // Get admin ID for stock history logging
    $adminNote = isset($_POST['admin_note']) ? trim($_POST['admin_note']) : null;
    
    if ($requestId <= 0) {
        $_SESSION['error'] = "Invalid request ID";
        header("Location: medicine_requests.php");
        exit();
    }
    
    // Fetch request and user details for email, including request_id
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
    $requestIdValue = $user['request_id']; // Store request_id for email
    
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
            $updateQuery = "UPDATE medicine_requests SET request_status = 'declined' WHERE id = :id";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bindParam(':id', $requestId);
            $updateStmt->execute();
            
            // Update all medicines to declined
            $updateMedicinesQuery = "UPDATE requested_medicines SET status = 'declined' WHERE request_id = :request_id";
            $updateMedicinesStmt = $conn->prepare($updateMedicinesQuery);
            $updateMedicinesStmt->bindParam(':request_id', $requestId);
            $updateMedicinesStmt->execute();
            
            $conn->commit();
            
            // Send decline email
            $subject = "Medicine Request Declined - Request ID #$requestIdValue";
            $message = "
                <h2>Medicine Request Declined</h2>
                <p>Dear $recipientName,</p>
                <p>We regret to inform you that your medicine request (ID #$requestIdValue) has been declined by Maru-Health Barangay Marulas 3S Health Station.</p>
                <p><strong>Reason:</strong> Your request could not be fulfilled at this time. Please contact us for more details or to submit a new request.</p>
                <p><strong>Contact Us:</strong><br>
                Email: _mainaccount@maruhealth.site<br>
                Address: Barangay Marulas 3S Health Station</p>
                <p>Thank you for your understanding.</p>
                <p>Best regards,<br>Maru-Health Team</p>
            ";
            $emailResult = sendEmail($recipientEmail, $recipientName, $subject, $message);
            
            if (!$emailResult['success']) {
                // Log email failure but don't interrupt the process
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
            $_SESSION['error'] = "Error: " . $e->getMessage();
            
            // For AJAX response
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
        $distributeMedicines = isset($_POST['distribute_medicines']) ? $_POST['distribute_medicines'] : [];
        
        if ($requestId <= 0 || empty($claimBy) || empty($claimUntil)) {
            $_SESSION['error'] = "Please fill in all required fields";
            header("Location: medicine_requests.php");
            exit();
        }
        
        try {
            // Check if we have a valid connection before starting a transaction
            if (!$conn || !($conn instanceof PDO)) {
                throw new Exception("Database connection is not valid");
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            // Update the medicine request with claim dates, note, and set status to to be claimed
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
            
            if (isset($_POST['approve_medicines']) && is_array($_POST['approve_medicines']) && !empty($_POST['approve_medicines'])) {
                $approvedMedicines = $_POST['approve_medicines'];

                if (is_array($approvedMedicines) && count($approvedMedicines) > 0) {
                    // Fetch all requested medicines for email content
                    $medicinesQuery = "SELECT id, medicine_name, dosage, quantity, status 
                                      FROM requested_medicines 
                                      WHERE request_id = :request_id";
                    $medicinesStmt = $conn->prepare($medicinesQuery);
                    $medicinesStmt->bindParam(':request_id', $requestId);
                    $medicinesStmt->execute();
                    $requestedMedicines = $medicinesStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Update each requested medicine status
                    $updateMedicineQuery = "UPDATE requested_medicines SET status = :status WHERE id = :id";
                    $updateMedicineStmt = $conn->prepare($updateMedicineQuery);

                    foreach ($requestedMedicines as $medicine) {
                        $status = in_array($medicine['id'], $approvedMedicines) ? 'approved' : 'declined';
                        $updateMedicineStmt->bindParam(':status', $status);
                        $updateMedicineStmt->bindParam(':id', $medicine['id']);
                        $updateMedicineStmt->execute();
                        
                        // Build email content lists
                        $medicineDetails = htmlspecialchars($medicine['medicine_name'] . 
                            ($medicine['dosage'] ? " - " . $medicine['dosage'] : "") . 
                            " - Quantity: " . $medicine['quantity']);
                        if ($status === 'approved') {
                            $approvedMedicinesList[] = $medicineDetails;
                        } else {
                            $declinedMedicinesList[] = $medicineDetails;
                        }
                    }

                    // Store the association between requested medicine and inventory medicine
                    if (isset($_POST['distribute_medicines']) && is_array($_POST['distribute_medicines']) && isset($_POST['approved_quantities']) && is_array($_POST['approved_quantities'])) {
                        // Insert the distribution data
                        $distributionQuery = "INSERT INTO medicine_distributions 
                                             (request_id, requested_medicine_id, inventory_medicine_id, quantity) 
                                             VALUES (:request_id, :requested_id, :inventory_id, :quantity)";
                        $distributionStmt = $conn->prepare($distributionQuery);

                        // Update medicine stock
                        $updateStockQuery = "UPDATE medicines SET stocks = stocks - :quantity WHERE id = :id";
                        $updateStockStmt = $conn->prepare($updateStockQuery);

                        // Log stock history
                        $historyQuery = "INSERT INTO stock_history 
                                        (medicine_id, quantity_change, reason, changed_by) 
                                        VALUES (:medicine_id, :quantity_change, :reason, :changed_by)";
                        $historyStmt = $conn->prepare($historyQuery);

                        foreach ($_POST['distribute_medicines'] as $requestedId => $inventoryId) {
                            if (in_array($requestedId, $approvedMedicines)) {
                                // Get the approved quantity
                                $approvedQty = isset($_POST['approved_quantities'][$requestedId]) ? intval($_POST['approved_quantities'][$requestedId]) : 0;
                                if ($approvedQty <= 0) {
                                    continue; // Skip if no quantity approved
                                }

                                // Check stock for safety
                                $stockCheckQuery = "SELECT stocks FROM medicines WHERE id = :id";
                                $stockCheckStmt = $conn->prepare($stockCheckQuery);
                                $stockCheckStmt->bindParam(':id', $inventoryId);
                                $stockCheckStmt->execute();
                                $currentStock = $stockCheckStmt->fetchColumn();

                                if ($currentStock < $approvedQty) {
                                    throw new Exception("Not enough stock for medicine ID $inventoryId");
                                }

                                // Add to distribution table
                                $distributionStmt->bindParam(':request_id', $requestId);
                                $distributionStmt->bindParam(':requested_id', $requestedId);
                                $distributionStmt->bindParam(':inventory_id', $inventoryId);
                                $distributionStmt->bindParam(':quantity', $approvedQty);
                                $distributionStmt->execute();

                                // Reduce inventory stock
                                $updateStockStmt->bindParam(':quantity', $approvedQty);
                                $updateStockStmt->bindParam(':id', $inventoryId);
                                $updateStockStmt->execute();

                                // Log stock history
                                $quantityChange = -$approvedQty; // Negative for distribution
                                $reason = 'Distribution';
                                $historyStmt->bindParam(':medicine_id', $inventoryId);
                                $historyStmt->bindParam(':quantity_change', $quantityChange);
                                $historyStmt->bindParam(':reason', $reason);
                                $historyStmt->bindParam(':changed_by', $adminId);
                                $historyStmt->execute();

                                // Update medicine stock status
                                $updateStockStatusQuery = "UPDATE medicines SET 
                                                         stock_status = CASE 
                                                            WHEN stocks <= 0 THEN 'Out of Stock'
                                                            WHEN stocks <= min_stock THEN 'Low Stock'
                                                            ELSE 'In Stock'
                                                         END
                                                         WHERE id = :id";
                                $updateStockStatusStmt = $conn->prepare($updateStockStatusQuery);
                                $updateStockStatusStmt->bindParam(':id', $inventoryId);
                                $updateStockStatusStmt->execute();
                            }
                        }
                    }
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
                <p><strong>Claim By:</strong> " . htmlspecialchars($claimBy) . "</p>
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
                // Log email failure but don't interrupt the process
                error_log("Failed to send approval email for request #$requestIdValue: " . $emailResult['message']);
            }
            
            $_SESSION['success'] = "Medicine request has been approved and claim dates have been set";
        } catch (Exception $e) {
            // Check if a transaction is active before rolling back
            if ($conn && $conn instanceof PDO && $conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['error'] = "Error: " . $e->getMessage();
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