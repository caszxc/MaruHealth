<?php
//delete_event.php
session_start();
require_once "config.php";

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit();
}

$id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
if (!$id) {
    echo json_encode(["success" => false, "message" => "Invalid ID"]);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT title, event_date, image FROM events WHERE id = ?");
    $stmt->execute([$id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        echo json_encode(["success" => false, "message" => "Event not found"]);
        exit();
    }

    $delete = $conn->prepare("DELETE FROM events WHERE id = ?");
    $delete->execute([$id]);

    if ($event['image'] && $event['image'] !== 'default_event.png') {
        $path = __DIR__ . "/images/uploads/event_images/" . $event['image'];
        if (file_exists($path)) unlink($path);
    }

    $log = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'event_delete', :details, :target_id)
    ");
    $log->execute([
        ':admin_id'   => $_SESSION['admin_id'],
        ':details'    => "Deleted event '{$event['title']}' on {$event['event_date']}",
        ':target_id'  => $id
    ]);

    $_SESSION['calendar_message'] = "Event deleted successfully.";
    echo json_encode(["success" => true, "message" => "Event deleted successfully"]);
} catch (Exception $e) {
    $_SESSION['calendar_message'] = "Error: " . $e->getMessage();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>