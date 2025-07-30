<?php
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_POST['id'];

    // 1. Fetch the image filename first
    $stmt = $conn->prepare("SELECT image FROM events WHERE id = ?");
    $stmt->execute([$id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($event) {
        $imageFile = $event['image'];

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

        echo json_encode(["success" => true, "message" => "Event deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Event not found"]);
    }
}
?>
