<?php
//add_dependent.php
session_start();
require_once "config.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $primary_user_id = $_SESSION['user_id'];
    
    // Get primary user's credentials
    $stmt = $conn->prepare("SELECT email, phone_number, password FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $primary_user_id]);
    $primary_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$primary_user) {
        echo json_encode(['success' => false, 'message' => 'Primary user not found']);
        exit();
    }

    // Sanitize and validate input
    $firstName = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
    $lastName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    $middleName = filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_STRING);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $birthday = filter_input(INPUT_POST, 'birthday', FILTER_SANITIZE_STRING);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    $relationship = filter_input(INPUT_POST, 'relationship', FILTER_SANITIZE_STRING);
    $hasFamilyNumber = isset($_POST['hasFamilyNumber']) && $_POST['hasFamilyNumber'] === 'on';
    $familyNumber = $hasFamilyNumber ? filter_input(INPUT_POST, 'familyNumber', FILTER_SANITIZE_STRING) : null;

    // Validate required fields
    $errors = [];
    if (empty($firstName)) $errors[] = "First name is required";
    if (empty($lastName)) $errors[] = "Last name is required";
    if (empty($gender)) $errors[] = "Gender is required";
    if (empty($birthday)) $errors[] = "Date of birth is required";
    if (empty($address)) $errors[] = "Address is required";
    if (empty($relationship)) $errors[] = "Relationship is required";

    // Validate family number if provided
    if ($hasFamilyNumber && empty($familyNumber)) {
        $errors[] = "Family number is required if you indicate you know it";
    } elseif ($hasFamilyNumber && !preg_match('/^[A-Za-z0-9-]{1,50}$/', $familyNumber)) {
        $errors[] = "Family number can only contain letters, numbers, and hyphens, up to 50 characters";
    }

    // Validate file upload
    if (!isset($_FILES["validID_front"]) || $_FILES["validID_front"]["error"] !== UPLOAD_ERR_OK) {
        $errors[] = "Valid ID is required";
    } else {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $fileType = $_FILES["validID_front"]["type"];
        $fileSize = $_FILES["validID_front"]["size"];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Invalid file type. Please upload JPEG or PNG images only";
        }
        
        if ($fileSize > $maxSize) {
            $errors[] = "File size exceeds the 5MB limit";
        }
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(", ", $errors)]);
        exit();
    }

    // Begin transaction
    $conn->beginTransaction();

    // Handle file upload
    $uploadDir = "images/uploads/IDs/";
    $fileExtension = pathinfo($_FILES["validID_front"]["name"], PATHINFO_EXTENSION);
    $newFileName = uniqid('id_') . '.' . $fileExtension;
    $validIdFrontPath = $uploadDir . $newFileName;

    if (!move_uploaded_file($_FILES["validID_front"]["tmp_name"], $validIdFrontPath)) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error uploading file']);
        exit();
    }

    // Insert into pending_users
    $stmt = $conn->prepare("
        INSERT INTO pending_users 
        (first_name, last_name, middle_name, gender, birthday, address, email, phone_number, valid_id_front, password, family_number, primary_user_id, date_registered) 
        VALUES 
        (:firstName, :lastName, :middleName, :gender, :birthday, :address, :email, :phone, :validIDFront, :password, :familyNumber, :primaryUserId, NOW())
    ");
    $stmt->execute([
        ':firstName' => $firstName,
        ':lastName' => $lastName,
        ':middleName' => $middleName,
        ':gender' => $gender,
        ':birthday' => $birthday,
        ':address' => $address,
        ':email' => $primary_user['email'],
        ':phone' => $primary_user['phone_number'],
        ':validIDFront' => $validIdFrontPath,
        ':password' => $primary_user['password'],
        ':familyNumber' => $familyNumber,
        ':primaryUserId' => $primary_user_id
    ]);

    // Get the newly inserted pending user ID
    $pendingUserId = $conn->lastInsertId();

    // Insert into pending_dependent_relationships
    $relationshipStmt = $conn->prepare("
        INSERT INTO pending_dependent_relationships (primary_user_id, dependent_user_id, relationship)
        VALUES (:primary_user_id, :dependent_user_id, :relationship)
    ");
    $relationshipStmt->execute([
        ':primary_user_id' => $primary_user_id,
        ':dependent_user_id' => $pendingUserId,
        ':relationship' => $relationship ?: 'Other'
    ]);

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true]);
    exit();
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
    exit();
}
?>