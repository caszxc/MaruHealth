<?php
//add_event.php
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

$title = trim($_POST['title'] ?? '');
$date  = $_POST['date'] ?? '';
$start = $_POST['start'] ?? '';
$end   = $_POST['end'] ?? '';
$venue = trim($_POST['venue'] ?? '');
$image = null;

if (!DateTime::createFromFormat('Y-m-d', $date)) {
    echo json_encode(["success" => false, "message" => "Invalid date"]);
    exit();
}

if ($start >= $end) {
    echo json_encode(["success" => false, "message" => "End time must be after start time"]);
    exit();
}

if (isset($_FILES["image"]) && $_FILES["image"]["error"] === UPLOAD_ERR_OK) {
    $dir = "images/uploads/event_images/";
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $image = time() . "_" . basename($_FILES["image"]["name"]);
    move_uploaded_file($_FILES["image"]["tmp_name"], $dir . $image);
}

try {
    $stmt = $conn->prepare("
        INSERT INTO events (title, event_date, start, end, venue, image, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$title, $date, $start, $end, $venue, $image]);

    $eventId = $conn->lastInsertId();

    $log = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'event_create', :details, :target_id)
    ");
    $log->execute([
        ':admin_id'   => $_SESSION['admin_id'],
        ':details'    => "Created event titled '{$title}'",
        ':target_id'  => $eventId
    ]);

    $_SESSION['calendar_message'] = "Event added successfully.";
    echo json_encode(["success" => true, "message" => "Event added successfully"]);
} catch (Exception $e) {
    $_SESSION['calendar_message'] = "Error: " . $e->getMessage();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>