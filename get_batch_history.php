<?php
// get_batch_history.php
session_start();
require_once "config.php";

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_id = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;

    if ($batch_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid batch ID.']);
        exit();
    }

    try {
        // Fetch history for batch-related actions, including distribute and return
        $stmt = $conn->prepare("
            SELECT mh.id, mh.action_type, mh.details, mh.created_at, a.full_name
            FROM medicine_history mh
            LEFT JOIN admin_staff a ON mh.performed_by = a.id
            WHERE mh.batch_id = :batch_id
            AND mh.action_type IN ('add_batch', 'update_batch', 'delete_batch', 'distribute', 'return')
            ORDER BY mh.created_at DESC
        ");
        $stmt->execute([':batch_id' => $batch_id]);
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