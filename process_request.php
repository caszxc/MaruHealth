<?php
// process_request.php
session_start();
require_once "config.php";
require_once "email_function.php";
require_once "semaphore_sms.php";

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: medicine_requests.php");
    exit();
}

$requestId = intval($_POST['request_id'] ?? 0);
$adminId = $_SESSION['admin_id'];
$adminNote = trim($_POST['admin_note'] ?? '');

if ($requestId <= 0) {
    $_SESSION['error'] = "Invalid request ID";
    header("Location: medicine_requests.php");
    exit();
}

try {
    $conn->beginTransaction();

    // === FETCH USER & REQUEST INFO ===
    $userStmt = $conn->prepare("
        SELECT mr.full_name, mr.request_id, u.email 
        FROM medicine_requests mr 
        JOIN users u ON mr.user_id = u.id 
        WHERE mr.id = :id
    ");
    $userStmt->execute([':id' => $requestId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("Request or user not found");
    }

    $recipientName = $user['full_name'];
    $recipientEmail = $user['email'];
    $requestIdValue = $user['request_id'];

    // === VALIDATE PENDING STATUS ===
    $checkStmt = $conn->prepare("SELECT id FROM medicine_requests WHERE id = :id AND request_status = 'pending'");
    $checkStmt->execute([':id' => $requestId]);
    if ($checkStmt->rowCount() == 0) {
        throw new Exception("Request already processed");
    }

    $action = $_POST['action'] ?? '';

    // ===================================================================
    // DECLINE REQUEST (FULL DECLINE)
    // ===================================================================
    if ($action === 'decline') {
        $updateReq = $conn->prepare("UPDATE medicine_requests SET request_status = 'declined', note = :note, declined_date = NOW() WHERE id = :id");
        $updateReq->execute([':note' => $adminNote, ':id' => $requestId]);

        $updateMeds = $conn->prepare("UPDATE requested_medicines SET status = 'declined' WHERE request_id = :id");
        $updateMeds->execute([':id' => $requestId]);

        // === LOG DECLINE IN activity_logs ===
        $declinedList = [];
        $medsStmt = $conn->prepare("SELECT medicine_name, dosage, quantity FROM requested_medicines WHERE request_id = :id");
        $medsStmt->execute([':id' => $requestId]);
        foreach ($medsStmt->fetchAll() as $m) {
            $declinedList[] = "{$m['medicine_name']} ({$m['dosage']}) × {$m['quantity']}";
        }
        $details = "Declined request #$requestIdValue: " . implode('; ', $declinedList);

        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
            VALUES (:admin_id, 'decline_request', :details, :target_id)
        ");
        $logStmt->execute([
            ':admin_id' => $adminId,
            ':details' => $details,
            ':target_id' => $requestId
        ]);

        $conn->commit();

        // Send email
        $subject = "Medicine Request Declined - #$requestIdValue";
        $message = "<h2>Request Declined</h2><p>Dear $recipientName,</p><p>Your request has been declined.</p><p><strong>Reason:</strong> " . ($adminNote ? htmlspecialchars($adminNote) : 'Your request could not be fulfilled at this time.') . "</p>
                <p><strong>Contact Us:</strong><br>
                Email: _mainaccount@maruhealth.site<br>
                Address: Barangay Marulas 3S Health Station</p>
                <p>Thank you for your understanding.</p>
                <p>Best regards,<br>MaruHealth Team</p>";
        sendEmail($recipientEmail, $recipientName, $subject, $message);

        $phoneStmt = $conn->prepare("SELECT phone_number FROM users WHERE id = (SELECT user_id FROM medicine_requests WHERE id = :id)");
        $phoneStmt->execute([':id' => $requestId]);
        $userPhone = $phoneStmt->fetchColumn();

        if ($userPhone) {
            $smsDecline = "Hi $recipientName, your medicine request #$requestIdValue was DECLINED. " .
                          ($adminNote ? substr(strip_tags($adminNote), 0, 100) : "Your request could not be fulfilled at this time.") .
                          " Please contact MaruHealth for clarification.";
            sendSMS($userPhone, $smsDecline);
        }

        $_SESSION['request_message'] = "Medicine request declined successfully!";
        echo "declined";
        exit();
    }

    // ===================================================================
    // APPROVE REQUEST (PARTIAL OR FULL)
    // ===================================================================
    $claimByDate = $_POST['claim_by'] ?? null;
    $claimUntilDate = $_POST['claim_until'] ?? null;

    if (!$claimByDate || !$claimUntilDate) {
        throw new Exception("Claim dates are required");
    }

    // Set claim_by to 8:00 AM of the selected date
    $claimBy = $claimByDate . ' 08:00:00';

    // Set claim_until to 6:00 PM of the selected date
    $claimUntil = $claimUntilDate . ' 18:00:00';
    
    $approveMedicines = $_POST['approve_medicines'] ?? [];

    if (empty($claimBy) || empty($claimUntil) || empty($approveMedicines)) {
        throw new Exception("Missing required fields");
    }

    // Update main request
    $updateReq = $conn->prepare("
        UPDATE medicine_requests 
        SET request_status = 'to be claimed', claim_date = :claim_by, claim_until_date = :claim_until, note = :note
        WHERE id = :id
    ");
    $updateReq->execute([
        ':claim_by' => $claimBy,
        ':claim_until' => $claimUntil,
        ':note' => $adminNote,
        ':id' => $requestId
    ]);

    // Prepare for logs
    $approvedList = [];
    $declinedList = [];
    $distributeHistory = [];

    $distStmt = $conn->prepare("
        INSERT INTO medicine_distributions 
        (request_id, requested_medicine_id, batch_id, quantity, status) 
        VALUES (:req_id, :med_id, :batch_id, :qty, 'reserved')
    ");

    $updateStock = $conn->prepare("
        UPDATE medicine_batches 
        SET stocks = stocks - :qty,
            stock_status = CASE 
                WHEN stocks - :qty <= 0 THEN 'Out of Stock'
                ELSE 'In Stock'
            END
        WHERE id = :batch_id
    ");

    $updateMedStatus = $conn->prepare("UPDATE requested_medicines SET status = :status WHERE id = :id");

    // Process each requested medicine
    $allMeds = $conn->prepare("SELECT id, medicine_name, dosage, quantity FROM requested_medicines WHERE request_id = :id");
    $allMeds->execute([':id' => $requestId]);
    foreach ($allMeds->fetchAll() as $med) {
        $medId = $med['id'];
        $isApproved = in_array($medId, $approveMedicines);

        $status = $isApproved ? 'approved' : 'declined';
        $updateMedStatus->execute([':status' => $status, ':id' => $medId]);

        $medLabel = "{$med['medicine_name']} ({$med['dosage']}) × {$med['quantity']}";
        if ($isApproved) {
            $approvedList[] = $medLabel;
        } else {
            $declinedList[] = $medLabel;
        }

        if ($isApproved) {
            $batchId = intval($_POST['distribute_medicines'][$medId] ?? 0);
            $qty = intval($_POST['approved_quantities'][$medId] ?? 0);

            if ($batchId <= 0 || $qty <= 0) {
                throw new Exception("Invalid distribution data");
            }

            // === FETCH BATCH LOT NUMBER ===
            $batchInfoStmt = $conn->prepare("
                SELECT mb.batch_lot_number, mb.stocks, mb.catalog_id 
                FROM medicine_batches mb 
                WHERE mb.id = :batch_id
            ");
            $batchInfoStmt->execute([':batch_id' => $batchId]);
            $batch = $batchInfoStmt->fetch(PDO::FETCH_ASSOC);

            if (!$batch || $batch['stocks'] < $qty) {
                throw new Exception("Insufficient stock for batch {$batch['batch_lot_number']}");
            }

            // Insert distribution
            $distStmt->execute([
                ':req_id' => $requestId,
                ':med_id' => $medId,
                ':batch_id' => $batchId,
                ':qty' => $qty
            ]);

            // Update stock
            $updateStock->execute([':qty' => $qty, ':batch_id' => $batchId]);

            // === LOG DISTRIBUTE IN medicine_history (with batch_lot_number) ===
            $distDetails = "Distributed {$qty} of {$med['medicine_name']} (Batch #{$batch['batch_lot_number']}) for request #$requestIdValue";
            $distHistoryStmt = $conn->prepare("
                INSERT INTO medicine_history (catalog_id, batch_id, action_type, details, performed_by)
                VALUES (:cat_id, :batch_id, 'distribute', :details, :admin_id)
            ");
            $distHistoryStmt->execute([
                ':cat_id' => $batch['catalog_id'],
                ':batch_id' => $batchId,
                ':details' => $distDetails,
                ':admin_id' => $adminId
            ]);

            // === UPDATE APPROVED LIST TO SHOW BATCH LOT NUMBER ===
            $approvedList[count($approvedList) - 1] .= " - Batch Lot Number #{$batch['batch_lot_number']}, Quantity: $qty";
        }
    }

    // === LOG approve_request IN activity_logs ===
    $logDetails = "Approved request #$requestIdValue\n";
    $logDetails .= "Approved: " . (count($approvedList) ? implode('; ', $approvedList) : 'None') . "\n";
    $logDetails .= "Declined: " . (count($declinedList) ? implode('; ', $declinedList) : 'None');

    $activityLog = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'approve_request', :details, :target_id)
    ");
    $activityLog->execute([
        ':admin_id' => $adminId,
        ':details' => $logDetails,
        ':target_id' => $requestId
    ]);

    // === LOG decline_request IF ANY DECLINED ===
    if (!empty($declinedList)) {
        $declineLog = $conn->prepare("
            INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
            VALUES (:admin_id, 'decline_request', :details, :target_id)
        ");
        $declineLog->execute([
            ':admin_id' => $adminId,
            ':details' => "Partially declined request #$requestIdValue: " . implode('; ', $declinedList),
            ':target_id' => $requestId
        ]);
    }

    $conn->commit();

    // === SEND EMAIL ===
    $subject = "Medicine Request Processed - #$requestIdValue";
    $approvedHTML = !empty($approvedList) ? "<ul><li>" . implode("</li><li>", $approvedList) . "</li></ul>" : "<p>None</p>";
    $declinedHTML = !empty($declinedList) ? "<ul><li>" . implode("</li><li>", $declinedList) . "</li></ul>" : "<p>None</p>";
    $noteHTML = $adminNote ? "<p><strong>Note:</strong> " . htmlspecialchars($adminNote) . "</p>" : "";

    $message = "
        <h2>Medicine Request Update</h2>
        <p>Dear $recipientName,</p>
        <p>We are pleased to inform you that your medicine request (ID #$requestIdValue) has been approved by MaruHealth Barangay Marulas 3S Health Station.</p>
        <h3>Request Details</h3>
        <h3>Approved Medicines:</h3>$approvedHTML
        <h3>Declined Medicines:</h3>$declinedHTML
        <p><strong>Claim Until:</strong> $claimUntil</p>
        $noteHTML
        <p>Please visit the health station during the specified period to claim your medicines. Bring a valid ID for verification.</p>
                <p><strong>Contact Us:</strong><br>
                Email: _mainaccount@maruhealth.site<br>
                Address: Barangay Marulas 3S Health Station</p>
                <p>Best regards,<br>Maru-Health Team</p>
    ";
    sendEmail($recipientEmail, $recipientName, $subject, $message);

    $phoneStmt = $conn->prepare("SELECT phone_number FROM users WHERE id = (SELECT user_id FROM medicine_requests WHERE id = :id)");
    $phoneStmt->execute([':id' => $requestId]);
    $userPhone = $phoneStmt->fetchColumn();

    if ($userPhone) {
        $claimDate = date('M j, Y', strtotime($claimUntil));
        $itemText  = $approvedCount == $totalMeds ? "all items" : "$approvedCount of $totalMeds items";
        
        $smsApprove = "Hi $recipientName! Your medicine request #$requestIdValue is APPROVED ($itemText). " .
                      "Claim until $claimDate 6PM at Marulas 3S Health Center, Marulas. Bring valid ID. Thank you!";
        
        sendSMS($userPhone, $smsApprove);
    }

    $_SESSION['request_message'] = "Medicine request processed successfully!";

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $_SESSION['request_message'] = "Error processing request: " . $e->getMessage();    error_log("Medicine request error: " . $e->getMessage());
    echo "error: " . $e->getMessage();
}

// Redirect if accessed directly
header("Location: requests.php");
exit();
?>