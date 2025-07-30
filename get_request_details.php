<?php
// get_request_details.php
session_start();
require_once "config.php";

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid request ID']);
    exit();
}

$requestId = intval($_GET['id']);

// Get request details
$requestQuery = "SELECT id, request_id, full_name, gender, birthdate, address, phone, reason, request_status, prescription, claim_date, claim_until_date, claimed_date, note 
                 FROM medicine_requests 
                 WHERE id = :id";
$requestStmt = $conn->prepare($requestQuery);
$requestStmt->bindParam(':id', $requestId);
$requestStmt->execute();
$request = $requestStmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Request not found']);
    exit();
}

// Format the birthdate
$request['birthdate'] = date('m/d/Y', strtotime($request['birthdate']));

// Get requested medicines
$medicinesQuery = "SELECT * FROM requested_medicines WHERE request_id = :request_id";
$medicinesStmt = $conn->prepare($medicinesQuery);
$medicinesStmt->bindParam(':request_id', $requestId);
$medicinesStmt->execute();
$medicines = $medicinesStmt->fetchAll(PDO::FETCH_ASSOC);

// For each medicine, check if it's available in stock
foreach ($medicines as &$medicine) {
    // Check if this medicine is available in our inventory
    $availabilityQuery = "SELECT id, generic_name, brand_name, dosage, dosage_form, stocks 
                     FROM medicines 
                     WHERE (generic_name LIKE :name OR brand_name LIKE :name) 
                     AND stocks > 0 
                     AND stock_status IN ('In Stock', 'Low Stock') 
                     AND expiry_status != 'Expired'";
                         
    $availabilityStmt = $conn->prepare($availabilityQuery);
    $searchName = "%" . $medicine['medicine_name'] . "%";
    $availabilityStmt->bindParam(':name', $searchName);
    $availabilityStmt->execute();
    $availableMeds = $availabilityStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($availableMeds) > 0) {
        $medicine['available_stock'] = true;
        
        // Format the available medicines information
        $medicineDetails = '';
        foreach ($availableMeds as $med) {
            $name = !empty($med['brand_name']) ? $med['brand_name'] . ' (' . $med['generic_name'] . ')' : $med['generic_name'];
            $medicineDetails .= $name . ' - ' . $med['dosage'] . ' ' . $med['dosage_form'] . ' - ' . $med['stocks'] . " in stock<br>";
        }
        $medicine['medicine_details'] = $medicineDetails;
        
        // Store the matched medicines for this requested medicine
        $medicine['matched_medicines'] = $availableMeds;
    } else {
        $medicine['available_stock'] = false;
        $medicine['medicine_details'] = 'No stock available';
        $medicine['matched_medicines'] = [];
    }
}

// Return the data as JSON
header('Content-Type: application/json');
echo json_encode([
    'request' => [
        'id' => $request['id'],
        'request_id' => $request['request_id'],
        'full_name' => $request['full_name'],
        'gender' => $request['gender'],
        'birthdate' => date('m/d/Y', strtotime($request['birthdate'])),
        'address' => $request['address'],
        'phone' => $request['phone'],
        'reason' => $request['reason'],
        'request_status' => $request['request_status'],
        'prescription' => $request['prescription'],
        'claim_date' => $request['claim_date'],
        'claim_until_date' => $request['claim_until_date'],
        'claimed_date' => $request['claimed_date'],
        'note' => $request['note']
    ],
    'medicines' => $medicines
]);
?>