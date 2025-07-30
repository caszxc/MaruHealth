<?php
// process_claim.php
session_start();
require_once "config.php";

// Set timezone to Philippine Standard Time
date_default_timezone_set('Asia/Manila');

// Ensure only logged-in super admin or staff can access
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $requestId = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($requestId <= 0) {
        $_SESSION['error'] = "Invalid request ID";
        header("Location: pending_requests.php");
        exit();
    }
    
    // Check if the request exists and is in "to be claimed" status
    $checkQuery = "SELECT id FROM medicine_requests WHERE id = :id AND request_status = 'to be claimed'";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bindParam(':id', $requestId);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() == 0) {
        $_SESSION['error'] = "Request not found or already processed";
        header("Location: pending_requests.php");
        exit();
    }
    
    if ($action === 'claim') {
        try {
            $conn->beginTransaction();
            
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
            
            // Update medicine distributions to claimed status
            $updateDistributionsQuery = "UPDATE medicine_distributions 
                                       SET status = 'claimed' 
                                       WHERE request_id = :request_id 
                                       AND status = 'reserved'";
            $updateDistributionsStmt = $conn->prepare($updateDistributionsQuery);
            $updateDistributionsStmt->bindParam(':request_id', $requestId);
            $updateDistributionsStmt->execute();
            
            $conn->commit();
            
            $_SESSION['success'] = "Request has been marked as claimed successfully";
            echo "Request claimed successfully";
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error'] = "Error processing claim: " . $e->getMessage();
            echo "Error: " . $e->getMessage();
        }
    } else {
        echo "Invalid action";
    }
    
    exit();
}

// Redirect if accessed directly
header("Location: pending_requests.php");
exit();
?>