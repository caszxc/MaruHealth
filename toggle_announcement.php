<?php
require 'config.php';

if (isset($_GET['id']) && isset($_GET['action'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    $newStatus = ($action === 'unarchive') ? 'active' : 'archived';

    $stmt = $conn->prepare("UPDATE announcements SET status = ? WHERE id = ?");
    if ($stmt->execute([$newStatus, $id])) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false]);
    }
} else {
    echo json_encode(["success" => false]);
}
?>
