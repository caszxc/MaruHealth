<?php
//get_medicine_history.php
session_start();
require_once "config.php";

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $catalog_id = isset($_POST['catalog_id']) ? (int)$_POST['catalog_id'] : 0;

    if ($catalog_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid catalog ID.']);
        exit();
    }

    try {
        // Fetch history for catalog-related actions
        $stmt = $conn->prepare("
            SELECT mh.id, mh.action_type, mh.details, mh.created_at, a.full_name
            FROM medicine_history mh
            LEFT JOIN admin_staff a ON mh.performed_by = a.id
            WHERE mh.catalog_id = :catalog_id
            AND mh.action_type IN ('add_catalog', 'update_catalog', 'delete_catalog')
            ORDER BY mh.created_at DESC
        ");
        $stmt->execute([':catalog_id' => $catalog_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the history data
        $formatted_history = array_map(function($entry) {
            return [
                'id' => $entry['id'],
                'action_type' => ucwords(str_replace('_', ' ', $entry['action_type'])),
                'details' => htmlspecialchars($entry['details']),
                'performed_by' => htmlspecialchars($entry['full_name']),
                'created_at' => date('M d, Y h:i A', strtotime($entry['created_at']))
            ];
        }, $history);

        echo json_encode(['success' => true, 'data' => $formatted_history]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching history: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>