<?php
//process_claim.php
session_start();
require_once "config.php";
require_once "email_function.php"; // Include email function

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
        $_SESSION['error'] = "Invalid request ID";
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['error' => 'Invalid request ID']);
            exit();
        }
        header("Location: pending_requests.php");
        exit();
    }
    
    if ($action !== 'claim') {
        $_SESSION['error'] = "Invalid action";
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['error' => 'Invalid action']);
            exit();
        }
        header("Location: pending_requests.php");
        exit();
    }
    
    try {
        $conn->beginTransaction();
        
        // Check if the request exists and is in "to be claimed" status
        $checkQuery = "SELECT mr.id, mr.request_id, mr.full_name, u.email 
                       FROM medicine_requests mr 
                       JOIN users u ON mr.user_id = u.id 
                       WHERE mr.id = :id AND mr.request_status = 'to be claimed'";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bindParam(':id', $requestId);
        $checkStmt->execute();
        $request = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            throw new Exception("Request not found or already processed");
        }
        
        // Update request status to "claimed" and set claimed_date
        $claimedDate = date('Y-m-d H:i:s');
        $updateQuery = "UPDATE medicine_requests 
                       SET request_status = 'claimed', 
                           claimed_date = :claimed_date 
                       WHERE id = :id";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bindParam(':claimed_date', $claimedDate);
        $updateStmt->bindParam(':id', $requestId);
        $updateStmt->execute();
        
        // Update medicine distributions to claimed status and get distribution details for history
        $distributionsQuery = "SELECT md.requested_medicine_id, md.batch_id, md.quantity, mb.catalog_id 
                              FROM medicine_distributions md 
                              JOIN medicine_batches mb ON md.batch_id = mb.id 
                              WHERE md.request_id = :request_id AND md.status = 'reserved'";
        $distributionsStmt = $conn->prepare($distributionsQuery);
        $distributionsStmt->bindParam(':request_id', $requestId);
        $distributionsStmt->execute();
        $distributions = $distributionsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updateDistributionsQuery = "UPDATE medicine_distributions 
                                   SET status = 'claimed' 
                                   WHERE request_id = :request_id 
                                   AND status = 'reserved'";
        $updateDistributionsStmt = $conn->prepare($updateDistributionsQuery);
        $updateDistributionsStmt->bindParam(':request_id', $requestId);
        $updateDistributionsStmt->execute();
        
        // Log claim action in medicine history for each distribution
        $historyQuery = "INSERT INTO medicine_history (catalog_id, batch_id, action_type, details, performed_by, created_at)
                        VALUES (:catalog_id, :batch_id, 'claim', :details, :admin_id, CURRENT_TIMESTAMP)";
        $historyStmt = $conn->prepare($historyQuery);
        
        foreach ($distributions as $distribution) {
            $historyDetails = json_encode([
                'request_id' => $request['request_id'],
                'requested_medicine_id' => $distribution['requested_medicine_id'],
                'batch_id' => $distribution['batch_id'],
                'quantity' => $distribution['quantity'],
                'action' => 'claimed'
            ]);
            $historyStmt->bindParam(':catalog_id', $distribution['catalog_id']);
            $historyStmt->bindParam(':batch_id', $distribution['batch_id']);
            $historyStmt->bindParam(':details', $historyDetails);
            $historyStmt->bindParam(':admin_id', $adminId);
            $historyStmt->execute();
        }
        
        $conn->commit();
        
        // Send confirmation email
        $recipientName = $request['full_name'];
        $recipientEmail = $request['email'];
        $requestIdValue = $request['request_id'];
        
        $subject = "Medicine Request Claimed - Request ID #$requestIdValue";
        $message = "
            <h2>Medicine Request Claimed</h2>
            <p>Dear $recipientName,</p>
            <p>We are pleased to confirm that your medicine request (ID #$requestIdValue) has been successfully claimed at Maru-Health Barangay Marulas 3S Health Station on " . date('m/d/Y H:i A', strtotime($claimedDate)) . ".</p>
            <p><strong>Contact Us:</strong><br>
            Email: _mainaccount@maruhealth.site<br>
            Address: Barangay Marulas 3S Health Station</p>
            <p>Thank you for using our services.</p>
            <p>Best regards,<br>Maru-Health Team</p>
        ";
        $emailResult = sendEmail($recipientEmail, $recipientName, $subject, $message);
        
        if (!$emailResult['success']) {
            error_log("Failed to send claim confirmation email for request #$requestIdValue: " . $emailResult['message']);
        }
        
        $_SESSION['success'] = "Request has been marked as claimed successfully";
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => 'Request claimed successfully']);
            exit();
        }
        header("Location: pending_requests.php");
        exit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = "Error processing claim: " . $e->getMessage();
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['error' => 'Error processing claim: ' . $e->getMessage()]);
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