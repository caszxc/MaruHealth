<?php
require_once "config.php";

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $stmt = $conn->prepare("SELECT * FROM medicines WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $medicine = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($medicine) {
        echo json_encode(['status' => 'success', 'medicine' => $medicine]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Medicine not found.']);
    }
}
?>
