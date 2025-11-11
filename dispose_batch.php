<?php
// dispose_batch.php
session_start();
require_once "config.php";

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff', 'super_admin', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$admin_id = $_SESSION['admin_id'];
$batch_id = $_POST['batch_id'] ?? null;
$quantity = intval($_POST['quantity'] ?? 0);
$reason = $_POST['reason'] ?? '';
$others_reason = trim($_POST['others_reason'] ?? '');
$witness_name = trim($_POST['witness_name'] ?? '');

if (!$batch_id || $quantity <= 0 || empty($reason) || empty($witness_name)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    $conn->beginTransaction();

    // Get current batch
    $stmt = $conn->prepare("SELECT stocks, batch_lot_number, catalog_id, expiration_date FROM medicine_batches WHERE id = ?");
    $stmt->execute([$batch_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch || $batch['stocks'] < $quantity) {
        throw new Exception('Insufficient stock');
    }

    $final_reason = ($reason === 'others') ? $others_reason : $reason;
    $current_stocks_before = $batch['stocks'];
    $new_stocks = $batch['stocks'] - $quantity;
    $stock_status = $new_stocks > 0 ? 'In Stock' : 'Out of Stock';

    // DECIDE: Should we mark as disposed?
    $mark_as_disposed = in_array($reason, ['expired', 'recalled']) ? 1 : 0;

    // If expired → must dispose ALL remaining stock
    if ($reason === 'expired' && $quantity != $batch['stocks']) {
        throw new Exception('Expired batches must dispose ALL remaining stock');
    }

    if ($reason === 'recalled' && $quantity != $batch['stocks']) {
        throw new Exception('Recalled batches must dispose ALL remaining stock');
    }

    // Insert disposal record
    $stmt = $conn->prepare("INSERT INTO medicine_disposals 
        (batch_id, quantity, reason, performed_by, witness_name, disposal_date, stock_before_disposal) 
        VALUES (?, ?, ?, ?, ?, NOW(), ?)");
    $stmt->execute([$batch_id, $quantity, $final_reason, $admin_id, $witness_name, $current_stocks_before]);

    // Update batch
    $sql = "UPDATE medicine_batches 
            SET stocks = ?, 
                stock_status = ?,
                is_disposed = ?,
                expiry_status = CASE 
                    WHEN ? = 'expired' THEN 'Disposed'
                    ELSE expiry_status 
                END
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$new_stocks, $stock_status, $mark_as_disposed, $reason, $batch_id]);

    // Log history
    $details = "Disposed $quantity pcs - Reason: " . ucwords(str_replace('_', ' ', $final_reason));
    $stmt = $conn->prepare("INSERT INTO medicine_history 
        (catalog_id, batch_id, action_type, details, performed_by) 
        VALUES (?, ?, 'dispose', ?, ?)");
    $stmt->execute([$batch['catalog_id'], $batch_id, $details, $admin_id]);

    $conn->commit();
    $_SESSION['batch_message'] = "Successfully disposed $quantity item(s).";
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>