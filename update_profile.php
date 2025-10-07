<?php
// update_profile.php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$active_user_id = isset($_SESSION['active_user_id']) ? $_SESSION['active_user_id'] : $user_id;
$is_dependent = ($active_user_id != $user_id);
$response = ['success' => false, 'message' => ''];

// Fetch current user details to use in patient update
$stmt = $conn->prepare("SELECT first_name, last_name, middle_name, birthday, primary_user_id, phone_number, email FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $active_user_id, PDO::PARAM_INT);
$stmt->execute();
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $middle_name = trim($_POST['middle_name']);
    $gender = trim($_POST['gender']);
    $birthday = trim($_POST['birthday']);
    $address = trim($_POST['address']);
    $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : ($is_dependent ? $current_user['phone_number'] : '');
    $email = isset($_POST['email']) ? trim($_POST['email']) : ($is_dependent ? $current_user['email'] : '');

    // Validate inputs (phone_number and email are optional for dependents)
    if (empty($first_name) || empty($last_name) || empty($middle_name) || empty($gender) || empty($birthday) || empty($address)) {
        $response['message'] = 'All required fields (First Name, Last Name, Middle Name, Gender, Date of Birth, Address) must be filled.';
        echo json_encode($response);
        exit();
    }

    if (!$is_dependent) {
        // Validate email and phone for primary users
        if (empty($phone_number) || empty($email)) {
            $response['message'] = 'Phone number and email are required for primary users.';
            echo json_encode($response);
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Invalid email format.';
            echo json_encode($response);
            exit();
        }

        if (!preg_match('/^\+?\d{10,15}$/', $phone_number)) {
            $response['message'] = 'Invalid phone number format.';
            echo json_encode($response);
            exit();
        }

        // Check email uniqueness for primary users
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id AND primary_user_id IS NULL");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $active_user_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $response['message'] = 'Email is already in use by another primary account.';
            echo json_encode($response);
            exit();
        }

        // Check phone number uniqueness for primary users
        $stmt = $conn->prepare("SELECT id FROM users WHERE phone_number = :phone_number AND id != :user_id AND primary_user_id IS NULL");
        $stmt->bindParam(':phone_number', $phone_number, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $active_user_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $response['message'] = 'Phone number is already in use by another primary account.';
            echo json_encode($response);
            exit();
        }
    }

    try {
        // Begin transaction to ensure atomic updates
        $conn->beginTransaction();

        // Update user details (exclude phone_number and email for dependents)
        if ($is_dependent) {
            $update_stmt = $conn->prepare("
                UPDATE users 
                SET first_name = :first_name, 
                    last_name = :last_name, 
                    middle_name = :middle_name, 
                    gender = :gender, 
                    birthday = :birthday, 
                    address = :address 
                WHERE id = :user_id
            ");
            $update_stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
            $update_stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
            $update_stmt->bindParam(':middle_name', $middle_name, PDO::PARAM_STR);
            $update_stmt->bindParam(':gender', $gender, PDO::PARAM_STR);
            $update_stmt->bindParam(':birthday', $birthday, PDO::PARAM_STR);
            $update_stmt->bindParam(':address', $address, PDO::PARAM_STR);
            $update_stmt->bindParam(':user_id', $active_user_id, PDO::PARAM_INT);
        } else {
            $update_stmt = $conn->prepare("
                UPDATE users 
                SET first_name = :first_name, 
                    last_name = :last_name, 
                    middle_name = :middle_name, 
                    gender = :gender, 
                    birthday = :birthday, 
                    address = :address, 
                    phone_number = :phone_number, 
                    email = :email 
                WHERE id = :user_id
            ");
            $update_stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
            $update_stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
            $update_stmt->bindParam(':middle_name', $middle_name, PDO::PARAM_STR);
            $update_stmt->bindParam(':gender', $gender, PDO::PARAM_STR);
            $update_stmt->bindParam(':birthday', $birthday, PDO::PARAM_STR);
            $update_stmt->bindParam(':address', $address, PDO::PARAM_STR);
            $update_stmt->bindParam(':phone_number', $phone_number, PDO::PARAM_STR);
            $update_stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $update_stmt->bindParam(':user_id', $active_user_id, PDO::PARAM_INT);
        }
        $update_stmt->execute();

        // If the user is a primary user, sync email and phone to dependents
        if (!$is_dependent) {
            $sync_stmt = $conn->prepare("
                UPDATE users 
                SET email = :email, 
                    phone_number = :phone_number 
                WHERE primary_user_id = :primary_user_id
            ");
            $sync_stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $sync_stmt->bindParam(':phone_number', $phone_number, PDO::PARAM_STR);
            $sync_stmt->bindParam(':primary_user_id', $active_user_id, PDO::PARAM_INT);
            $sync_stmt->execute();
        }

        // Update patient record if linked
        $patient_stmt = $conn->prepare("
            UPDATE patients 
            SET first_name = :first_name, 
                last_name = :last_name, 
                middle_name = :middle_name, 
                birthdate = :birthday, 
                sex = :gender, 
                contact_number = :phone_number, 
                address = :address 
            WHERE first_name = :old_first_name 
            AND last_name = :old_last_name 
            AND birthdate = :old_birthday
        ");
        $patient_stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
        $patient_stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
        $patient_stmt->bindParam(':middle_name', $middle_name, PDO::PARAM_STR);
        $patient_stmt->bindParam(':birthday', $birthday, PDO::PARAM_STR);
        $patient_stmt->bindParam(':gender', $gender, PDO::PARAM_STR);
        $patient_stmt->bindParam(':phone_number', $phone_number, PDO::PARAM_STR);
        $patient_stmt->bindParam(':address', $address, PDO::PARAM_STR);
        $patient_stmt->bindParam(':old_first_name', $current_user['first_name'], PDO::PARAM_STR);
        $patient_stmt->bindParam(':old_last_name', $current_user['last_name'], PDO::PARAM_STR);
        $patient_stmt->bindParam(':old_birthday', $current_user['birthday'], PDO::PARAM_STR);
        $patient_stmt->execute();

        // Commit transaction
        $conn->commit();

        $response['success'] = true;
        $response['message'] = 'Profile updated successfully.';
    } catch (PDOException $e) {
        $conn->rollBack();
        $response['message'] = 'An error occurred: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
exit();
?>