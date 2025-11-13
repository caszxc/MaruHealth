<?php
// get_requests_profile.php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

if (!isset($_GET['id'])) {
    header("HTTP/1.1 400 Bad Request");
    exit();
}

$request_id = $_GET['id'];
$primary_user_id = $_SESSION['user_id'];
$active_user_id = isset($_SESSION['active_user_id']) ? $_SESSION['active_user_id'] : $primary_user_id;

// Validate user
$stmt = $conn->prepare("SELECT id, primary_user_id FROM users WHERE id = :id");
$stmt->bindParam(':id', $active_user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || ($active_user_id != $primary_user_id && $user['primary_user_id'] != $primary_user_id)) {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

try {
    // Fetch main request
    $sql = "SELECT mr.request_id, mr.full_name, mr.gender AS sex, mr.birthdate, mr.address, mr.phone, mr.reason, 
                   mr.request_status, mr.prescription, mr.claim_date, mr.claim_until_date, mr.claimed_date, mr.note
            FROM medicine_requests mr
            WHERE mr.id = :request_id AND mr.user_id = :active_user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
    $stmt->bindParam(':active_user_id', $active_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        header("HTTP/1.1 404 Not Found");
        exit();
    }

    // Fetch requested medicines with approved quantity
    $sql = "SELECT 
                rm.id,
                rm.medicine_name,
                rm.dosage,
                rm.quantity AS requested_quantity,
                rm.status,
                md.quantity AS approved_quantity
            FROM requested_medicines rm
            LEFT JOIN medicine_distributions md ON rm.id = md.requested_medicine_id AND md.status IN ('reserved', 'claimed')
            WHERE rm.request_id = :request_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
    $stmt->execute();
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format medicines
    $formatted_medicines = array_map(function($med) {
        return [
            'medicine_name' => $med['medicine_name'],
            'dosage' => $med['dosage'] ?: 'N/A',
            'requested_quantity' => (int)$med['requested_quantity'],
            'approved_quantity' => $med['approved_quantity'] ? (int)$med['approved_quantity'] : null,
            'status' => $med['status'],
            'quantity_diff' => $med['approved_quantity'] !== null && $med['approved_quantity'] != $med['requested_quantity']
        ];
    }, $medicines);

    $response = [
        'request_id' => $request['request_id'],
        'full_name' => $request['full_name'],
        'sex' => $request['sex'],
        'birthdate' => date("F j, Y", strtotime($request['birthdate'])),
        'address' => $request['address'] ?? 'N/A',
        'phone' => $request['phone'],
        'reason' => $request['reason'],
        'request_status' => $request['request_status'],
        'prescription' => $request['prescription'] ? $request['prescription'] : 'images/uploads/prescriptions/no-prescription.png',
        'claim_date' => $request['claim_date'] ? date("F j, Y, g:i A", strtotime($request['claim_date'])) : null,
        'claim_until_date' => $request['claim_until_date'] ? date("F j, Y, g:i A", strtotime($request['claim_until_date'])) : null,
        'claimed_date' => $request['claimed_date'] ? date("F j, Y, g:i A", strtotime($request['claimed_date'])) : null,
        'note' => $request['note'],
        'medicines' => $formatted_medicines
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
} catch (PDOException $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode(['error' => 'Database error']);
}
?>