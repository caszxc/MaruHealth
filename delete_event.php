<?php
//delete_event.php
session_start(); // Add session_start to access admin_id
require_once "config.php";

// Check if user is logged in as super admin or admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(["success" => false, "message" => "Invalid event ID"]);
        exit();
    }

    try {
        // 1. Fetch the event details for logging and image deletion
        $stmt = $conn->prepare("SELECT title, event_date, image FROM events WHERE id = ?");
        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($event) {
            $imageFile = $event['image'];
            $eventTitle = $event['title'];
            $eventDate = $event['event_date'];

            // 2. Delete the event from database
            $deleteStmt = $conn->prepare("DELETE FROM events WHERE id = ?");
            $deleteStmt->execute([$id]);

            // 3. Delete the image file from the folder (if not default and exists)
            if (!empty($imageFile) && $imageFile !== "default_event.png") {
                $imagePath = __DIR__ . "/images/uploads/event_images/" . $imageFile;
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            // 4. Log the event deletion
            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
                VALUES (:admin_id, 'event_delete', :details, :target_id)
            ");
            $details = "Deleted event titled '{$eventTitle}' scheduled for {$eventDate} (ID: {$id})";
            $logStmt->execute([
                ':admin_id' => $_SESSION['admin_id'],
                ':details' => $details,
                ':target_id' => $id
            ]);

            echo json_encode(["success" => true, "message" => "Event deleted successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "Event not found"]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
}
?>