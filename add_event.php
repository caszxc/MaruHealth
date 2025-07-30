<?php
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $date = $_POST['date'];
    $start = $_POST['start'];
    $end = $_POST['end'];
    $venue = $_POST['venue'];
    $image = null;

    // Handle image upload
    if (!empty($_FILES["image"]["name"])) {
        $targetDir = "images/uploads/event_images/";
        $imageName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $imageName;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
            $image = $imageName; // Save the image filename in DB
        }
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO events (title, event_date, start, end, venue, image) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $date, $start, $end, $venue, $image]);

    echo json_encode(["success" => true, "message" => "Event added successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Event not added"]);
}
?>
