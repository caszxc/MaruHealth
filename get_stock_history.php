<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$medicine_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($medicine_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid medicine ID']);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT sh.quantity_change, sh.reason, sh.changed_at, a.full_name AS changed_by_name
        FROM stock_history sh
        LEFT JOIN admin_staff a ON sh.changed_by = a.id
        WHERE sh.medicine_id = :medicine_id
        ORDER BY sh.changed_at DESC
    ");
    $stmt->bindParam(':medicine_id', $medicine_id, PDO::PARAM_INT);
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($history);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>