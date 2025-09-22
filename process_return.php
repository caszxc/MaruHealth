<?php
session_start();
require_once "config.php";
require_once "email_function.php";

// Set timezone to Philippine Standard Time
date_default_timezone_set('Asia/Manila');

// Ensure only logged-in health staff can access
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $requestId = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $adminId = $_SESSION['admin_id'];
    
    if ($requestId <= 0) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['error' => 'Invalid request ID']);
            exit();
        }
        $_SESSION['error'] = "Invalid request ID";
        header("Location: pending_requests.php");
        exit();
    }
    
    if ($action !== 'return') {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['error' => 'Invalid action']);
            exit();
        }
        $_SESSION['error'] = "Invalid action";
        header("Location: pending_requests.php");
        exit();
    }
    
    try {
        $conn->beginTransaction();
        
        // Check if the request exists, is 'to be claimed', and is past due
        $checkQuery = "SELECT mr.id, mr.request_id, mr.full_name, mr.claim_until_date, u.email 
                       FROM medicine_requests mr 
                       JOIN users u ON mr.user_id = u.id 
                       WHERE mr.id = :id AND mr.request_status = 'to be claimed'";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bindParam(':id', $requestId);
        $checkStmt->execute();
        $request = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            throw new Exception("Request not found or not in 'to be claimed' status");
        }
        
        // Verify the request is past due
        $today = new DateTime();
        $claimUntilDate = new DateTime($request['claim_until_date']);
        if ($claimUntilDate >= $today) {
            throw new Exception("Request has not yet reached its claim until date");
        }
        
        // Update request status to 'cancelled'
        $updateQuery = "UPDATE medicine_requests 
                       SET request_status = 'cancelled' 
                       WHERE id = :id";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bindParam(':id', $requestId);
        $updateStmt->execute();
        
        // Get distributions to return medicines to inventory
        $distributionsQuery = "SELECT md.requested_medicine_id, md.batch_id, md.quantity, mb.catalog_id 
                              FROM medicine_distributions md 
                              JOIN medicine_batches mb ON md.batch_id = mb.id 
                              WHERE md.request_id = :request_id AND md.status = 'reserved'";
        $distributionsStmt = $conn->prepare($distributionsQuery);
        $distributionsStmt->bindParam(':request_id', $requestId);
        $distributionsStmt->execute();
        $distributions = $distributionsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update distributions to 'returned' and restore stock
        $updateDistributionsQuery = "UPDATE medicine_distributions 
                                   SET status = 'returned' 
                                   WHERE request_id = :request_id 
                                   AND status = 'reserved'";
        $updateDistributionsStmt = $conn->prepare($updateDistributionsQuery);
        $updateDistributionsStmt->bindParam(':request_id', $requestId);
        $updateDistributionsStmt->execute();
        
        $updateStockQuery = "UPDATE medicine_batches 
                            SET stocks = stocks + :quantity,
                                stock_status = CASE 
                                    WHEN stocks + :quantity <= 0 THEN 'Out of Stock'
                                    WHEN stocks + :quantity <= (SELECT min_stock FROM medicines_catalog WHERE id = medicine_batches.catalog_id) THEN 'Low Stock'
                                    ELSE 'In Stock'
                                END
                            WHERE id = :batch_id";
        $updateStockStmt = $conn->prepare($updateStockQuery);
        
        $historyQuery = "INSERT INTO medicine_history (catalog_id, batch_id, action_type, details, performed_by, created_at)
                        VALUES (:catalog_id, :batch_id, 'return', :details, :admin_id, CURRENT_TIMESTAMP)";
        $historyStmt = $conn->prepare($historyQuery);
        
        foreach ($distributions as $distribution) {
            // Restore stock
            $updateStockStmt->bindParam(':quantity', $distribution['quantity']);
            $updateStockStmt->bindParam(':batch_id', $distribution['batch_id']);
            $updateStockStmt->execute();
            
            // Log return action
            $historyDetails = json_encode([
                'request_id' => $request['request_id'],
                'requested_medicine_id' => $distribution['requested_medicine_id'],
                'batch_id' => $distribution['batch_id'],
                'quantity' => $distribution['quantity'],
                'action' => 'returned',
                'reason' => 'unclaimed'
            ]);
            $historyStmt->bindParam(':catalog_id', $distribution['catalog_id']);
            $historyStmt->bindParam(':batch_id', $distribution['batch_id']);
            $historyStmt->bindParam(':details', $historyDetails);
            $historyStmt->bindParam(':admin_id', $adminId);
            $historyStmt->execute();
        }
        
        $conn->commit();
        
        // Send cancellation email
        $recipientName = $request['full_name'];
        $recipientEmail = $request['email'];
        $requestIdValue = $request['request_id'];
        
        $subject = "Medicine Request Cancelled - Request ID #$requestIdValue";
        $message = "
            <h2>Medicine Request Cancelled</h2>
            <p>Dear $recipientName,</p>
            <p>We regret to inform you that your medicine request (ID #$requestIdValue) has been cancelled as it was not claimed by " . htmlspecialchars($request['claim_until_date']) . ".</p>
            <p>The reserved medicines have been returned to our inventory. Please submit a new request if you still need these medicines.</p>
            <p><strong>Contact Us:</strong><br>
            Email: _mainaccount@maruhealth.site<br>
            Address: Barangay Marulas 3S Health Station</p>
            <p>Thank you for your understanding.</p>
            <p>Best regards,<br>Maru-Health Team</p>
        ";
        $emailResult = sendEmail($recipientEmail, $recipientName, $subject, $message);
        
        if (!$emailResult['success']) {
            error_log("Failed to send cancellation email for request #$requestIdValue: " . $emailResult['message']);
        }
        
        $_SESSION['success'] = "Unclaimed medicines have been returned to inventory successfully";
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => 'Unclaimed medicines returned successfully']);
            exit();
        }
        header("Location: pending_requests.php");
        exit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = "Error processing return: " . $e->getMessage();
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['error' => 'Error processing return: ' . $e->getMessage()]);
            exit();
        }
        header("Location: pending_requests.php");
        exit();
    }
}

// Redirect if accessed directly
header("Location: pending_requests.php");
exit();
?>