<?php
//get_request_details.php
session_start();
require_once "config.php";

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
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
    $availabilityQuery = "
        SELECT 
            mb.id AS batch_id, 
            mc.generic_name, 
            mc.brand_name, 
            mc.dosage, 
            mc.dosage_form, 
            mb.stocks, 
            mb.batch_lot_number,
            DATE_FORMAT(mb.expiration_date, '%d/%m/%Y') AS exp_date   /* <-- formatted date */
        FROM medicines_catalog mc 
        JOIN medicine_batches mb ON mc.id = mb.catalog_id
        WHERE (mc.generic_name LIKE :name OR mc.brand_name LIKE :name) 
          AND mc.dosage LIKE :dosage
          AND mb.stocks > 0 
          AND mb.stock_status IN ('In Stock', 'Low Stock') 
          AND mb.expiry_status != 'Expired'
        ORDER BY mb.expiration_date ASC
    ";
    $availabilityStmt = $conn->prepare($availabilityQuery);
    $searchName   = "%" . $medicine['medicine_name'] . "%";
    $searchDosage = "%" . $medicine['dosage'] . "%";
    $availabilityStmt->bindParam(':name', $searchName);
    $availabilityStmt->bindParam(':dosage', $searchDosage);
    $availabilityStmt->execute();
    $availableBatches = $availabilityStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($availableBatches) > 0) {
        $medicine['available_stock'] = true;

        // ---- DISPLAY IN THE MODAL (Available Medicines list) ----
        $medicineDetails = '';
        foreach ($availableBatches as $batch) {
            $name = !empty($batch['brand_name'])
                ? $batch['brand_name'] . ' (' . $batch['generic_name'] . ')'
                : $batch['generic_name'];

            $medicineDetails .=
                htmlspecialchars($name) . " - " .
                htmlspecialchars($batch['dosage']) . " " .
                htmlspecialchars($batch['dosage_form']) . " - " .
                "Batch/Lot no: " . htmlspecialchars($batch['batch_lot_number']) . " - " .
                $batch['stocks'] . " in stock - " .
                "Expiry: <strong>" . $batch['exp_date'] . "</strong><br>";
        }
        $medicine['medicine_details'] = $medicineDetails;

        // ---- DATA FOR THE SELECT DROPDOWN (allocation) ----
        $medicine['matched_batches'] = $availableBatches;
    } else {
        $medicine['available_stock'] = false;
        $medicine['medicine_details'] = 'No stock available';
        $medicine['matched_batches'] = [];
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