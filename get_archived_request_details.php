<?php
// get_archived_request_details.php
session_start();
require_once "config.php";

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
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

// Get request details
$requestQuery = "SELECT mr.*, 
                DATE_FORMAT(mr.birthdate, '%m/%d/%Y') as birthdate,
                DATE_FORMAT(mr.claim_date, '%m/%d/%Y') as formatted_claim_date,
                DATE_FORMAT(mr.claim_until_date, '%m/%d/%Y') as formatted_until_date,
                DATE_FORMAT(mr.claimed_date, '%m/%d/%Y %h:%i%p') as formatted_claimed_date
                FROM medicine_requests mr 
                WHERE mr.id = :id AND mr.request_status IN ('claimed', 'declined')";
$requestStmt = $conn->prepare($requestQuery);
$requestStmt->bindParam(':id', $requestId);
$requestStmt->execute();
$request = $requestStmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Request not found']);
    exit();
}

// Get requested medicines with their status
$medicinesQuery = "SELECT * FROM requested_medicines WHERE request_id = :request_id";
$medicinesStmt = $conn->prepare($medicinesQuery);
$medicinesStmt->bindParam(':request_id', $requestId);
$medicinesStmt->execute();
$medicines = $medicinesStmt->fetchAll(PDO::FETCH_ASSOC);

// For each approved medicine, get the distribution information (which inventory medicine was assigned)
foreach ($medicines as &$medicine) {
    if ($medicine['status'] === 'approved') {
        // Modified query to handle both 'claimed' and 'reserved' status since we're looking at archived requests
        $distributionQuery = "SELECT md.quantity, 
                            m.id as medicine_id, 
                            CONCAT(
                                CASE WHEN m.brand_name IS NOT NULL AND m.brand_name != '' 
                                    THEN CONCAT(m.brand_name, ' - ')
                                    ELSE ''
                                END,
                                m.generic_name, ' ', m.dosage, ' ', m.dosage_form
                            ) as medicine_name
                            FROM medicine_distributions md
                            JOIN medicines m ON md.inventory_medicine_id = m.id
                            WHERE md.requested_medicine_id = :requested_medicine_id
                            AND md.status IN ('claimed', 'reserved')";
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