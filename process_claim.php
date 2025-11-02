<?php
// process_claim.php
ob_start();
session_start();
require_once "config.php";

// Ensure only logged-in health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    ob_end_clean();
    header("Location: admin_dashboard.php");
    exit();
}

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    ob_end_clean();
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

$requestId = intval($_POST['request_id'] ?? 0);
$action = $_POST['action'] ?? '';
$adminId = $_SESSION['admin_id'];

if ($requestId <= 0 || $action !== 'claim') {
    ob_end_clean();
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

try {
    $conn->beginTransaction();

    // Validate request exists and is in 'to be claimed'
    $checkStmt = $conn->prepare("
        SELECT mr.id, mr.request_id, mr.full_name 
        FROM medicine_requests mr 
        WHERE mr.id = :id AND mr.request_status = 'to be claimed'
    ");
    $checkStmt->execute([':id' => $requestId]);
    $request = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new Exception("Request not found or already claimed");
    }

    $claimedDate = date('Y-m-d H:i:s');

    // Update request status
    $updateReq = $conn->prepare("
        UPDATE medicine_requests 
        SET request_status = 'claimed', claimed_date = :claimed_date 
        WHERE id = :id
    ");
    $updateReq->execute([
        ':claimed_date' => $claimedDate,
        ':id' => $requestId
    ]);

    // Update distributions
    $updateDist = $conn->prepare("
        UPDATE medicine_distributions 
        SET status = 'claimed' 
        WHERE request_id = :request_id AND status = 'reserved'
    ");
    $updateDist->execute([':request_id' => $requestId]);

    // === LOG mark_request_claimed IN activity_logs ===
    $logDetails = "Marked medicine request #{$request['request_id']} as claimed by {$request['full_name']} on " . date('m/d/Y h:i A');

    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'mark_request_claimed', :details, :target_id)
    ");
    $logStmt->execute([
        ':admin_id' => $adminId,
        ':details' => $logDetails,
        ':target_id' => $requestId
    ]);

    $conn->commit();

    ob_end_clean();
    echo json_encode(['success' => 'Request marked as claimed']);
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log("Claim error: " . $e->getMessage());
    ob_end_clean();
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    exit();
}
?>