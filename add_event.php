<?php
//add_event.php
session_start();
require_once "config.php";

// Check if user is logged in as super admin or admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = htmlspecialchars($_POST['title'] ?? '');
    $date = $_POST['date'] ?? '';
    $start = $_POST['start'] ?? '';
    $end = $_POST['end'] ?? '';
    $venue = htmlspecialchars($_POST['venue'] ?? '');
    $image = null;

    // Validate date format
    if (!DateTime::createFromFormat('Y-m-d', $date)) {
        echo json_encode(["success" => false, "message" => "Invalid date format"]);
        exit();
    }

    // Handle image upload
    if (!empty($_FILES["image"]["name"])) {
        $targetDir = "images/uploads/event_images/";
        $imageName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $imageName;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
            $image = $imageName; // Save the image filename in DB
        }
    }

    try {
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO events (title, event_date, start, end, venue, created_at, image) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->execute([$title, $date, $start, $end, $venue, $image]);

        // Get the event ID
        $eventId = $conn->lastInsertId();

        // Log the event creation
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
            VALUES (:admin_id, 'event_create', :details, :target_id)
        ");
        $details = "Created event titled '{$title}' scheduled for {$date}";
        $logStmt->execute([
            ':admin_id' => $_SESSION['admin_id'],
            ':details' => $details,
            ':target_id' => $eventId
        ]);

        echo json_encode(["success" => true, "message" => "Event added successfully"]);
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
}
?>