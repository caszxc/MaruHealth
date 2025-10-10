<?php
// submit_request.php
session_start();
require 'config.php'; // DB connection

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo "Invalid request method.";
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    echo "<script>alert('You must be logged in to make a request.'); window.location.href = 'login.php';</script>";
    exit;
}

$primary_user_id = $_SESSION['user_id'];
$active_user_id = isset($_SESSION['active_user_id']) ? $_SESSION['active_user_id'] : $primary_user_id;

// Validate active_user_id
$stmt = $conn->prepare("SELECT id, primary_user_id, address FROM users WHERE id = :active_user_id");
$stmt->bindParam(':active_user_id', $active_user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || ($active_user_id != $primary_user_id && $user['primary_user_id'] != $primary_user_id)) {
    echo "<script>alert('Invalid user account.'); window.location.href = 'login.php';</script>";
    exit;
}

// Get form data
$full_name = trim($_POST['full_name'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$birthdate = trim($_POST['birthdate'] ?? '');
$address = trim($_POST['address'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$reason = trim($_POST['reason'] ?? '');
$medicine_names = $_POST['medicine_name'] ?? [];
$dosages = $_POST['dosage'] ?? [];
$quantities = $_POST['quantity'] ?? [];

// Validate form data
if (empty($full_name) || empty($gender) || empty($birthdate) || empty($address) || empty($phone)) {
    echo "<script>alert('All required fields must be filled.'); window.history.back();</script>";
    exit;
}

if (!is_array($medicine_names) || count($medicine_names) === 0) {
    echo "<script>alert('Please add at least one medicine.'); window.history.back();</script>";
    exit;
}

// Handle prescription upload
$prescriptionPath = null;
if (isset($_FILES['prescription']) && $_FILES['prescription']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['prescription']['tmp_name'];
    $fileName = $_FILES['prescription']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];

    if (!in_array($fileExtension, $allowedExtensions)) {
        echo "<script>alert('Invalid file type. Only JPG, PNG, and PDF are allowed.'); window.history.back();</script>";
        exit;
    }

    $uploadDir = 'images/uploads/prescriptions/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $newFileName = uniqid('rx_', true) . '.' . $fileExtension;
    $destPath = $uploadDir . $newFileName;

    if (!move_uploaded_file($fileTmpPath, $destPath)) {
        echo "<script>alert('Error uploading prescription.'); window.history.back();</script>";
        exit;
    }
    $prescriptionPath = $destPath;
}

// Generate a random request ID
$request_id = 'REQ-' . strtoupper(substr(uniqid(), -6)) . '-' . date('Ymd');

// Ensure the request ID is unique
$stmt = $conn->prepare("SELECT COUNT(*) FROM medicine_requests WHERE request_id = ?");
$stmt->execute([$request_id]);
$count = $stmt->fetchColumn();
while ($count > 0) {
    $request_id = 'REQ-' . strtoupper(substr(uniqid(), -6)) . '-' . date('Ymd');
    $stmt->execute([$request_id]);
    $count = $stmt->fetchColumn();
}

try {
    $conn->beginTransaction();

    // Insert into medicine_requests with request_id and active_user_id
    $stmt = $conn->prepare("INSERT INTO medicine_requests 
        (request_id, user_id, full_name, gender, birthdate, address, phone, reason, prescription) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $request_id,
        $active_user_id,
        $full_name,
        $gender,
        $birthdate,
        $address,
        $phone,
        $reason,
        $prescriptionPath
    ]);

    $request_auto_id = $conn->lastInsertId(); // Get the auto-incremented ID

    // Insert each medicine into requested_medicines
    $stmtMed = $conn->prepare("INSERT INTO requested_medicines (request_id, medicine_name, dosage, quantity) 
                               VALUES (?, ?, ?, ?)");

    for ($i = 0; $i < count($medicine_names); $i++) {
        $name = trim($medicine_names[$i]);
        $dosage = trim($dosages[$i] ?? '');
        $quantity = intval($quantities[$i] ?? 0);

        if ($name === '' || $quantity <= 0) continue;

        $stmtMed->execute([
            $request_auto_id,
            $name,
            $dosage,
            $quantity
        ]);
    }

    $conn->commit();

    // Log for debugging
    error_log("Medicine request submitted: request_id=$request_id, user_id=$active_user_id, address=$address");

    // Redirect with request_id as query parameter
    header("Location: request_medicine.php?request_id=" . urlencode($request_id));
    exit;
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Error in submit_request.php: " . $e->getMessage());
    echo "<script>alert('Error submitting request: " . htmlspecialchars($e->getMessage()) . "'); window.history.back();</script>";
}
?>