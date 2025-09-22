<?php
session_start();
require_once "config.php";

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid request ID']);
    exit();
}

$requestId = intval($_GET['id']);

// Get request details including request_id
$requestQuery = "SELECT mr.*, 
                mr.request_id, 
                DATE_FORMAT(mr.birthdate, '%m/%d/%Y') as birthdate,
                DATE_FORMAT(mr.claim_date, '%m/%d/%Y') as formatted_claim_date,
                DATE_FORMAT(mr.claim_until_date, '%m/%d/%Y') as formatted_until_date,
                mr.note
                FROM medicine_requests mr 
                WHERE mr.id = :id";
$requestStmt = $conn->prepare($requestQuery);
$requestStmt->bindParam(':id', $requestId);
$requestStmt->execute();
$request = $requestStmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Request not found']);
    exit();
}

// Check if the request is past due
$today = new DateTime();
$claimUntilDate = new DateTime($request['claim_until_date']);
$request['is_past_due'] = $claimUntilDate < $today && $request['request_status'] === 'to be claimed';

// Get requested medicines with their status
$medicinesQuery = "SELECT * FROM requested_medicines WHERE request_id = :request_id";
$medicinesStmt = $conn->prepare($medicinesQuery);
$medicinesStmt->bindParam(':request_id', $requestId);
$medicinesStmt->execute();
$medicines = $medicinesStmt->fetchAll(PDO::FETCH_ASSOC);

// For each approved medicine, get the distribution information
foreach ($medicines as &$medicine) {
    if ($medicine['status'] === 'approved') {
        $distributionQuery = "SELECT md.quantity, md.batch_id, 
                            mc.id as catalog_id, 
                            CONCAT(
                                CASE WHEN mc.brand_name IS NOT NULL AND mc.brand_name != '' 
                                    THEN CONCAT(mc.brand_name, ' - ')
                                    ELSE ''
                                END,
                                mc.generic_name, ' ', mc.dosage, ' ', mc.dosage_form
                            ) as medicine_name
                            FROM medicine_distributions md
                            JOIN medicine_batches mb ON md.batch_id = mb.id
                            JOIN medicines_catalog mc ON mb.catalog_id = mc.id
                            WHERE md.requested_medicine_id = :requested_medicine_id
                            AND md.status = 'reserved'";
        $distributionStmt = $conn->prepare($distributionQuery);
        $distributionStmt->bindParam(':requested_medicine_id', $medicine['id']);
        $distributionStmt->execute();
        $distribution = $distributionStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($distribution) {
            $medicine['distribution'] = $distribution;
        }
    }
}

// Return the data as JSON
header('Content-Type: application/json');
echo json_encode([
    'request' => $request,
    'medicines' => $medicines
]);
?>