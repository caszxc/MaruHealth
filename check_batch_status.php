<?php
// check_batch_status.php
session_start();
require_once "config.php";

if (!isset($_POST['batch_id'])) {
    echo json_encode(['deletable' => false, 'editable' => false]);
    exit();
}

$batch_id = $_POST['batch_id'];

$stmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM medicine_distributions WHERE batch_id = :id) AS dist,
        (SELECT COUNT(*) FROM medicine_disposals WHERE batch_id = :id) AS disp,
        (SELECT COUNT(*) FROM medicine_history 
         WHERE batch_id = :id 
           AND action_type NOT IN ('add_batch', 'update_batch', 'delete_batch')) AS other_actions
");
$stmt->execute([':id' => $batch_id]);
$counts = $stmt->fetch(PDO::FETCH_ASSOC);

$hasUsage = ($counts['dist'] > 0 || $counts['disp'] > 0 || $counts['other_actions'] > 0);
$deletable = !$hasUsage;
$editable = !$hasUsage; // Same rule

echo json_encode([
    'deletable' => $deletable,
    'editable' => $editable
]);
?>