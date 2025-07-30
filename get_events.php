<?php
include 'config.php';

$date = $_GET['date'];
$stmt = $conn->prepare("SELECT id, title, start, end, venue, image FROM events WHERE event_date = ?");
$stmt->execute([$date]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
