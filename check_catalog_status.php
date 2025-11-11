<?php
// check_catalog_status.php
session_start();
require_once "config.php";

if (!isset($_POST['catalog_id'])) {
    echo json_encode(['editable' => false, 'deletable' => false]);
    exit();
}

$catalog_id = $_POST['catalog_id'];

// Check if any batches exist
$stmt = $conn->prepare("
    SELECT COUNT(*) as batch_count 
    FROM medicine_batches 
    WHERE catalog_id = :catalog_id
");
$stmt->execute([':catalog_id' => $catalog_id]);
$count = $stmt->fetch(PDO::FETCH_ASSOC);

$hasBatches = $count['batch_count'] > 0;

echo json_encode([
    'editable'  => !$hasBatches,
    'deletable' => !$hasBatches
]);
?>