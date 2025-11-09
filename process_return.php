<?php
// process_return.php
ob_start();
session_start();
require_once "config.php";
require_once "email_function.php";

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        ob_end_clean();
        echo json_encode(['error' => 'Unauthorized access']);
        exit();
    }
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    ob_end_clean();
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

$requestId = intval($_POST['request_id'] ?? 0);
$action = $_POST['action'] ?? '';
$adminId = $_SESSION['admin_id'];

if ($requestId <= 0 || $action !== 'return') {
    ob_end_clean();
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

try {
    $conn->beginTransaction();

    // Validate request
    $checkStmt = $conn->prepare("
        SELECT mr.id, mr.request_id, mr.full_name, mr.claim_until_date, u.email 
        FROM medicine_requests mr 
        JOIN users u ON mr.user_id = u.id 
        WHERE mr.id = :id AND mr.request_status = 'to be claimed'
    ");
    $checkStmt->execute([':id' => $requestId]);
    $request = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new Exception("Request not found or not in 'to be claimed' status");
    }

    $today = new DateTime();
    $claimUntil = new DateTime($request['claim_until_date']);
    if ($claimUntil >= $today) {
        throw new Exception("Claim until date has not passed");
    }

    // Update request status
    $updateReq = $conn->prepare("UPDATE medicine_requests SET request_status = 'unclaimed' WHERE id = :id");
    $updateReq->execute([':id' => $requestId]);

    // Get distributions + batch lot number
    $distStmt = $conn->prepare("
        SELECT md.requested_medicine_id, md.batch_id, md.quantity, 
               mb.catalog_id, mb.batch_lot_number,
               rm.medicine_name, rm.dosage
        FROM medicine_distributions md 
        JOIN medicine_batches mb ON md.batch_id = mb.id
        JOIN requested_medicines rm ON md.requested_medicine_id = rm.id
        WHERE md.request_id = :request_id AND md.status = 'reserved'
    ");
    $distStmt->execute([':request_id' => $requestId]);
    $distributions = $distStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($distributions)) {
        throw new Exception("No reserved medicines to return");
    }

    // Update distributions
    $updateDist = $conn->prepare("
        UPDATE medicine_distributions 
        SET status = 'returned' 
        WHERE request_id = :request_id AND status = 'reserved'
    ");
    $updateDist->execute([':request_id' => $requestId]);

    // Restore stock + log return in medicine_history
    $updateStock = $conn->prepare("
        UPDATE medicine_batches 
        SET stocks = stocks + :quantity,
            stock_status = CASE 
                WHEN stocks + :quantity <= 0 THEN 'Out of Stock'
                WHEN stocks + :quantity <= (SELECT min_stock FROM medicines_catalog WHERE id = catalog_id) THEN 'Low Stock'
                ELSE 'In Stock'
            END
        WHERE id = :batch_id
    ");

    $historyStmt = $conn->prepare("
        INSERT INTO medicine_history (catalog_id, batch_id, action_type, details, performed_by)
        VALUES (:cat_id, :batch_id, 'return', :details, :admin_id)
    ");

    $returnDetailsList = [];
    foreach ($distributions as $d) {
        $qty = $d['quantity'];
        $batchId = $d['batch_id'];
        $catId = $d['catalog_id'];
        $lot = $d['batch_lot_number'];
        $medName = $d['medicine_name'];
        $dosage = $d['dosage'];

        // Restore stock
        $updateStock->execute([':quantity' => $qty, ':batch_id' => $batchId]);

        // Log return in medicine_history
        $histDetail = "Returned {$qty} of {$medName} ({$dosage}) - Batch #{$lot} (unclaimed request #{$request['request_id']})";
        $historyStmt->execute([
            ':cat_id' => $catId,
            ':batch_id' => $batchId,
            ':details' => $histDetail,
            ':admin_id' => $adminId
        ]);

        $returnDetailsList[] = "{$medName} ({$dosage}) × {$qty} → Batch #{$lot}";
    }

    // === LOG return_unclaimed_request IN activity_logs ===
    $logDetails = "Returned unclaimed request #{$request['request_id']} (due: " . $claimUntil->format('m/d/Y') . ")\n";
    $logDetails .= "Returned medicines:\n" . implode("\n", $returnDetailsList);

    $activityLog = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'return_unclaimed_request', :details, :target_id)
    ");
    $activityLog->execute([
        ':admin_id' => $adminId,
        ':details' => $logDetails,
        ':target_id' => $requestId
    ]);

    $conn->commit();

    // === SEND EMAIL (unchanged) ===
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
        <p>Best regards,<br>MaruHealth Team</p>
    ";
    $emailResult = sendEmail($recipientEmail, $recipientName, $subject, $message);

    if (!$emailResult['success']) {
        error_log("Failed to send cancellation email: " . $emailResult['message']);
    }

    $_SESSION['claim_message'] = "Unclaimed medicines for request #{$request['request_id']} returned to inventory.";
    $_SESSION['claim_status']  = 'success';
    ob_end_clean();
    echo json_encode(['success' => 'Unclaimed medicines returned to inventory']);
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $_SESSION['claim_message'] = "Error returning medicines: " . $e->getMessage();
    $_SESSION['claim_status']  = 'error';
    
    ob_end_clean();
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    exit();
}
?>