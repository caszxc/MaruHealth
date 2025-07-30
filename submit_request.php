<?php
session_start();
require 'config.php'; // DB connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_SESSION['user_id'])) {
        echo "You must be logged in to make a request.";
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $full_name = $_POST['full_name'];
    $gender = $_POST['gender'];
    $birthdate = $_POST['birthdate'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $reason = $_POST['reason'] ?? '';

    $medicine_names = $_POST['medicine_name'];
    $dosages = $_POST['dosage'];
    $quantities = $_POST['quantity'];

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
        if (in_array($fileExtension, $allowedExtensions)) {
            $uploadDir = 'images/uploads/prescriptions/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newFileName = uniqid('rx_', true) . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $prescriptionPath = $destPath;
            } else {
                echo "<script>alert('Error uploading prescription.'); window.history.back();</script>";
                exit;
            }
        } else {
            echo "<script>alert('Invalid file type. Only JPG, PNG, and PDF are allowed.'); window.history.back();</script>";
            exit;
        }
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

        // Insert into medicine_requests with request_id
        $stmt = $conn->prepare("INSERT INTO medicine_requests 
            (request_id, user_id, full_name, gender, birthdate, address, phone, reason, prescription) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $request_id,
            $user_id,
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
            $dosage = trim($dosages[$i]);
            $quantity = intval($quantities[$i]);

            if ($name === '' || $quantity <= 0) continue;

            $stmtMed->execute([
                $request_auto_id,
                $name,
                $dosage,
                $quantity
            ]);
        }

        $conn->commit();

        echo "<script>
        alert('Medicine request submitted successfully! Your Request ID is: $request_id');
        window.location.href = 'request_medicine.php';
        </script>";
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Invalid request method.";
}
?>