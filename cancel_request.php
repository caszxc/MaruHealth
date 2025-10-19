<?php
//cancel_request.php
session_start();
require_once "config.php";

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $response['message'] = 'Invalid request ID.';
    echo json_encode($response);
    exit();
}

$request_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    $conn->beginTransaction();

    // Verify the request belongs to the user and is cancellable
    $stmt = $conn->prepare("SELECT request_status, request_id FROM medicine_requests WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $request_id, ':user_id' => $user_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $conn->rollBack();
        $response['message'] = 'Request not found or you do not have permission to cancel it.';
        echo json_encode($response);
        exit();
    }

    if (!in_array($request['request_status'], ['pending', 'to be claimed'])) {
        $conn->rollBack();
        $response['message'] = 'This request cannot be cancelled.';
        echo json_encode($response);
        exit();
    }

    // Update the request status to 'cancelled'
    $updateStmt = $conn->prepare("UPDATE medicine_requests SET request_status = 'cancelled' WHERE id = :id");
    $updateStmt->execute([':id' => $request_id]);

    // If the request was 'to be claimed', release reserved stock and log to medicine_history
    if ($request['request_status'] === 'to be claimed') {
        $distStmt = $conn->prepare("SELECT md.batch_id, md.quantity, md.requested_medicine_id, mb.catalog_id 
                                    FROM medicine_distributions md 
                                    JOIN medicine_batches mb ON md.batch_id = mb.id 
                                    WHERE md.request_id = :request_id AND md.status = 'reserved'");
        $distStmt->execute([':request_id' => $request_id]);
        $distributions = $distStmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare history logging statement
        $historyStmt = $conn->prepare("INSERT INTO medicine_history (catalog_id, batch_id, action_type, details, performed_by, created_at)
                                       VALUES (:catalog_id, :batch_id, 'return', :details, :performed_by, CURRENT_TIMESTAMP)");

        foreach ($distributions as $dist) {
            // Update stock in medicine_batches
            $stockStmt = $conn->prepare("UPDATE medicine_batches SET stocks = stocks + :quantity WHERE id = :batch_id");
            $stockStmt->execute([
                ':quantity' => $dist['quantity'],
                ':batch_id' => $dist['batch_id']
            ]);

            // Update stock_status by referencing min_stock from medicines_catalog
            $batchStmt = $conn->prepare("UPDATE medicine_batches mb
                                         JOIN medicines_catalog mc ON mb.catalog_id = mc.id
                                         SET mb.stock_status = CASE 
                                             WHEN mb.stocks > mc.min_stock THEN 'In Stock'
                                             WHEN mb.stocks > 0 THEN 'Low Stock'
                                             ELSE 'Out of Stock'
                                             END
                                         WHERE mb.id = :batch_id");
            $batchStmt->execute([':batch_id' => $dist['batch_id']]);

            // Log cancellation to medicine_history
            $historyDetails = json_encode([
                'request_id' => $request['request_id'],
                'requested_medicine_id' => $dist['requested_medicine_id'],
                'batch_id' => $dist['batch_id'],
                'quantity' => $dist['quantity'],
                'action' => 'cancelled'
            ]);
            $historyStmt->execute([
                ':catalog_id' => $dist['catalog_id'],
                ':batch_id' => $dist['batch_id'],
                ':details' => $historyDetails,
                ':performed_by' => $user_id
            ]);
        }

        // Update medicine_distributions status to 'returned'
        $distUpdateStmt = $conn->prepare("UPDATE medicine_distributions SET status = 'returned' WHERE request_id = :request_id AND status = 'reserved'");
        $distUpdateStmt->execute([':request_id' => $request_id]);
    }

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Request cancelled successfully.';
    echo json_encode($response);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['message'] = 'Database error: ' . $e->getMessage();
    echo json_encode($response);
}
?>