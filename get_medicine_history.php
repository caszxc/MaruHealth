<?php
// get_medicine_history.php
session_start();
require_once "config.php";

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$catalog_id = isset($_POST['catalog_id']) ? (int)$_POST['catalog_id'] : 0;
$limit      = isset($_POST['limit']) ? (int)$_POST['limit'] : 0;

if ($catalog_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid catalog ID.']);
    exit();
}

try {
    // Get medicine name
    $medStmt = $conn->prepare("
        SELECT 
            CONCAT(
                generic_name,
                IF(brand_name IS NOT NULL AND brand_name!='', CONCAT(' (', brand_name, ')'), ''),
                IF(dosage IS NOT NULL AND dosage!='', CONCAT(' ', dosage), '')
            ) AS medicine_full
        FROM medicines_catalog 
        WHERE id = :catalog_id
    ");
    $medStmt->execute([':catalog_id' => $catalog_id]);
    $medicine_name = $medStmt->fetchColumn() ?: 'Unknown Medicine';

    // FIXED QUERY: Use catalog_id for ALL actions
    $sql = "
        SELECT 
            mh.id, 
            mh.action_type, 
            mh.details, 
            mh.created_at, 
            a.full_name
        FROM medicine_history mh
        LEFT JOIN admin_staff a ON mh.performed_by = a.id
        WHERE mh.catalog_id = :catalog_id
           OR (
               mh.batch_id IN (
                   SELECT id FROM medicine_batches 
                   WHERE catalog_id = :catalog_id
               )
               AND mh.action_type IN ('add_batch', 'update_batch', 'distribute', 'return')
           )
           OR (
               mh.catalog_id = :catalog_id 
               AND mh.action_type IN ('add_batch', 'update_batch', 'delete_batch', 'distribute', 'return')
           )
        ORDER BY mh.created_at DESC
    ";

    if ($limit > 0) {
        $sql .= " LIMIT :limit";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':catalog_id', $catalog_id, PDO::PARAM_INT);
    if ($limit > 0) {
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    }
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = array_map(function($e){
        return [
            'id'          => $e['id'],
            'action_type' => ucwords(str_replace('_', ' ', $e['action_type'])),
            'details'     => htmlspecialchars($e['details']),
            'performed_by'=> htmlspecialchars($e['full_name'] ?? 'Unknown'),
            'created_at'  => date('M d, Y h:i A', strtotime($e['created_at']))
        ];
    }, $history);

    echo json_encode([
        'success' => true, 
        'data' => $formatted, 
        'medicine_name' => $medicine_name
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: '.$e->getMessage()]);
}
?>