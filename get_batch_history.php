<?php
// get_batch_history.php
session_start();
require_once "config.php";

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$batch_id = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;
$limit    = isset($_POST['limit']) ? (int)$_POST['limit'] : 0;   // Optional limit

if ($batch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid batch ID.']);
    exit();
}

try {
    $infoStmt = $conn->prepare("
        SELECT 
            mc.generic_name,
            mc.brand_name,
            mc.dosage,
            mb.batch_lot_number
        FROM medicine_batches mb
        JOIN medicines_catalog mc ON mb.catalog_id = mc.id
        WHERE mb.id = :batch_id
    ");
    $infoStmt->bindValue(':batch_id',$batch_id,PDO::PARAM_INT);
    $infoStmt->execute();
    $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

    $medicine_full = $info ? 
        $info['generic_name'] .
        ($info['brand_name'] ? ' ('.$info['brand_name'].' ' : '') .
        ($info['dosage'] ? $info['dosage'].')' : '')
        : 'Unknown Medicine';

    $batch_lot = $info['batch_lot_number'] ?? 'N/A';

    $sql = "
        SELECT mh.id, mh.action_type, mh.details, mh.created_at, a.full_name
        FROM medicine_history mh
        LEFT JOIN admin_staff a ON mh.performed_by = a.id
        WHERE mh.batch_id = :batch_id
          AND mh.action_type IN ('add_batch', 'update_batch', 'delete_batch', 'distribute', 'return', 'add_stock', 'dispose')
        ORDER BY mh.created_at DESC
    ";

    // Apply limit only if requested (e.g., for preview)
    if ($limit > 0) {
        $sql .= " LIMIT :limit";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':batch_id', $batch_id, PDO::PARAM_INT);
    if ($limit > 0) {
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    }
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = array_map(function($e) {
        return [
            'id'           => $e['id'],
            'action_type'  => ucwords(str_replace('_', ' ', $e['action_type'])),
            'details'      => htmlspecialchars($e['details'] ?? ''),
            'performed_by' => htmlspecialchars($e['full_name'] ?? 'Unknown'),
            'created_at'   => date('M d, Y h:i A', strtotime($e['created_at']))
        ];
    }, $history);

    echo json_encode(['success' => true, 'data' => $formatted, 'medicine_full'  => $medicine_full, 'batch_lot' => $batch_lot]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>