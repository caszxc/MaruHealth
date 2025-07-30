<?php
//get_requests_profile.php
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
$user_id = $_SESSION['user_id'];

try {
    $sql = "SELECT mr.request_id, mr.full_name, mr.gender AS sex, mr.birthdate, mr.address, mr.phone, mr.reason, 
                   mr.request_status, mr.prescription, mr.claim_date, mr.claim_until_date, mr.claimed_date, mr.note
            FROM medicine_requests mr
            WHERE mr.id = :request_id AND mr.user_id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        header("HTTP/1.1 404 Not Found");
        exit();
    }

    $sql = "SELECT medicine_name, dosage, quantity, status
            FROM requested_medicines
            WHERE request_id = :request_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
    $stmt->execute();
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'request_id' => $request['request_id'],
        'full_name' => $request['full_name'],
        'sex' => $request['sex'],
        'birthdate' => date("F j, Y", strtotime($request['birthdate'])),
        'address' => $request['address'],
        'phone' => $request['phone'],
        'reason' => $request['reason'],
        'request_status' => $request['request_status'],
        'prescription' => $request['prescription'] ? $request['prescription'] : 'images/uploads/prescriptions/no-prescription.png',
        'claim_date' => $request['claim_date'] ? date("F j, Y, g:i A", strtotime($request['claim_date'])) : null,
        'claim_until_date' => $request['claim_until_date'] ? date("F j, Y, g:i A", strtotime($request['claim_until_date'])) : null,
        'claimed_date' => $request['claimed_date'] ? date("F j, Y, g:i A", strtotime($request['claimed_date'])) : null,
        'note' => $request['note'],
        'medicines' => $medicines
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
} catch (PDOException $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>