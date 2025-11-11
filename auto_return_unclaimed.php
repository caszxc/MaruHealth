<?php
// auto_return_unclaimed.php
// Run this script via CRON every hour (or every 15 min) after 6:00 PM
require_once "config.php";
require_once "email_function.php";

date_default_timezone_set('Asia/Manila');

try {
    $conn->beginTransaction();

    // 1. Find all requests that are still "to be claimed" but past 6:00 PM of claim_until_date
    $now = new DateTime();
    $sql = "
        SELECT mr.id, mr.request_id, mr.full_name, mr.claim_until_date, u.email
        FROM medicine_requests mr
        JOIN users u ON mr.user_id = u.id
        WHERE mr.request_status = 'to be claimed'
          AND mr.claim_until_date < :cutoff
    ";
    $cutoff = $now->format('Y-m-d 18:00:00'); // 6:00 PM today
    $stmt = $conn->prepare($sql);
    $stmt->execute([':cutoff' => $cutoff]);
    $overdueRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($overdueRequests)) {
        $conn->commit();
        //exit("No overdue requests found.\n");
    }

    // Prepare reusable statements
    $updateReq = $conn->prepare("UPDATE medicine_requests SET request_status = 'unclaimed' WHERE id = :id");
    $updateDist = $conn->prepare("UPDATE medicine_distributions SET status = 'returned' WHERE request_id = :req_id AND status = 'reserved'");
    $updateStock = $conn->prepare("
        UPDATE medicine_batches 
        SET stocks = stocks + :qty,
            stock_status = CASE 
                WHEN stocks + :qty <= 0 THEN 'Out of Stock'
                WHEN stocks + :qty <= (SELECT min_stock FROM medicines_catalog WHERE id = catalog_id) THEN 'Low Stock'
                ELSE 'In Stock'
            END
        WHERE id = :batch_id
    ");
    $insertHistory = $conn->prepare("
        INSERT INTO medicine_history (catalog_id, batch_id, action_type, details, performed_by)
        VALUES (:cat_id, :batch_id, 'return', :details, :admin_id)
    ");
    $insertLog = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'return_unclaimed_request', :details, :target_id)
    ");

    // Use a dummy admin (system) – create a system user or use super_admin
    $systemAdminId = 1; // Change to your super_admin ID or create a "System" user

    foreach ($overdueRequests as $req) {
        $requestId = $req['id'];
        $requestCode = $req['request_id'];
        $fullName = $req['full_name'];
        $email = $req['email'];
        $until = $req['claim_until_date'];

        // 2. Update request status
        $updateReq->execute([':id' => $requestId]);

        // 3. Get all reserved distributions
        $distStmt = $conn->prepare("
            SELECT md.requested_medicine_id, md.batch_id, md.quantity,
                   mb.catalog_id, mb.batch_lot_number,
                   rm.medicine_name, rm.dosage
            FROM medicine_distributions md
            JOIN medicine_batches mb ON md.batch_id = mb.id
            JOIN requested_medicines rm ON md.requested_medicine_id = rm.id
            WHERE md.request_id = :req_id AND md.status = 'reserved'
        ");
        $distStmt->execute([':req_id' => $requestId]);
        $dists = $distStmt->fetchAll(PDO::FETCH_ASSOC);

        $returnList = [];
        foreach ($dists as $d) {
            $qty = $d['quantity'];
            $batchId = $d['batch_id'];
            $catId = $d['catalog_id'];
            $lot = $d['batch_lot_number'];
            $med = $d['medicine_name'];
            $dosage = $d['dosage'];

            // Restore stock
            $updateStock->execute([':qty' => $qty, ':batch_id' => $batchId]);

            // Log in medicine_history
            $histDetail = "Auto-returned {$qty} of {$med} ({$dosage}) - Batch #{$lot} (unclaimed request #{$requestCode})";
            $insertHistory->execute([
                ':cat_id' => $catId,
                ':batch_id' => $batchId,
                ':details' => $histDetail,
                ':admin_id' => $systemAdminId
            ]);

            $returnList[] = "{$med} ({$dosage}) × {$qty} → Batch #{$lot}";
        }

        // Update distributions
        $updateDist->execute([':req_id' => $requestId]);

        // 4. Log in activity_logs
        $logDetails = "Auto-returned unclaimed request #{$requestCode} (due: " . date('m/d/Y', strtotime($until)) . ")\n";
        $logDetails .= "Returned medicines:\n" . implode("\n", $returnList);
        $insertLog->execute([
            ':admin_id' => $systemAdminId,
            ':details' => $logDetails,
            ':target_id' => $requestId
        ]);

        // 5. Send Email
        $subject = "Medicine Request Cancelled (Unclaimed) - #$requestCode";
        $message = "
            <h2>Medicine Request Cancelled</h2>
            <p>Dear {$fullName},</p>
            <p>Your medicine request <strong>#{$requestCode}</strong> was not claimed by <strong>6:00 PM on " . date('F j, Y', strtotime($until)) . "</strong>.</p>
            <p>The reserved medicines have been returned to inventory. Please submit a new request if you still need them.</p>
            <p><strong>Contact Us:</strong><br>
            Email: _mainaccount@maruhealth.site<br>
            Address: Barangay Marulas 3S Health Station</p>
            <p>Thank you for your understanding.</p>
            <p>Best regards,<br>MaruHealth Team</p>
        ";
        $emailResult = sendEmail($email, $fullName, $subject, $message);
        if (!$emailResult['success']) {
            error_log("Auto-return email failed for {$email}: " . $emailResult['message']);
        }
    }

    $conn->commit();
    //echo "Processed " . count($overdueRequests) . " overdue request(s).\n";

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log("Auto-return error: " . $e->getMessage());
    //echo "Error: " . $e->getMessage() . "\n";
}
?>