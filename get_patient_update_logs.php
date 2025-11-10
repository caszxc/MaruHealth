<?php
// get_patient_update_logs.php
session_start();
include 'config.php';

if (!isset($_GET['patient_id'])) {
    echo json_encode([]);
    exit();
}

$patient_id = $_GET['patient_id'];

$stmt = $conn->prepare("
    SELECT 
        pul.*,
        COALESCE(
            CONCAT(u.first_name, ' ', u.last_name),
            a.full_name,
            pul.updated_by_name,
            'Unknown'
        ) AS updated_by_name
    FROM patient_updates_log pul
    LEFT JOIN users u ON pul.updated_by_id = u.id AND pul.updated_by_type = 'user'
    LEFT JOIN admin_staff a ON pul.updated_by_id = a.id AND pul.updated_by_type = 'health_staff'
    WHERE pul.patient_id = :id
    ORDER BY pul.updated_at DESC
");
$stmt->execute([':id' => $patient_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($logs as &$log) {
    if ($log['updated_by_type'] === 'health_staff') {
        $log['updated_by_type'] = 'Health Staff';
    } elseif ($log['updated_by_type'] === 'user') {
        $log['updated_by_type'] = 'User';
    }
}
unset($log); // good practice

header('Content-Type: application/json');
echo json_encode($logs);
?>