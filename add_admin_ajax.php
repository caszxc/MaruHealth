<?php
// add_admin_ajax.php
session_start();
require_once "config.php";

// Check if the user is logged in and is a super admin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'super_admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Process only if it's a POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = trim($_POST['full_name']);
    $role = $_POST['role'];
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirmPassword = trim($_POST['confirm_password']);
    
    // Validation
    $errors = [];
    
    if (empty($fullName)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($role) || !in_array($role, ['admin', 'health_staff'])) {
        $errors[] = "Valid role is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    // Password validation
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/", $password)) {
        $errors[] = "Password must include at least one uppercase letter, one lowercase letter, and one number";
    }
    
    // Check if email or username already exists
    $checkStmt = $conn->prepare("SELECT * FROM admin_staff WHERE email = :email OR username = :username");
    $checkStmt->bindParam(':email', $email);
    $checkStmt->bindParam(':username', $username);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($existingUser['email'] == $email) {
            $errors[] = "Email already in use";
        }
        if ($existingUser['username'] == $username) {
            $errors[] = "Username already in use";
        }
    }
    
    // Prepare JSON response
    header('Content-Type: application/json');
    
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $insertStmt = $conn->prepare("INSERT INTO admin_staff (full_name, role, email, username, password) VALUES (:fullName, :role, :email, :username, :password)");
        $insertStmt->bindParam(':fullName', $fullName);
        $insertStmt->bindParam(':role', $role);
        $insertStmt->bindParam(':email', $email);
        $insertStmt->bindParam(':username', $username);
        $insertStmt->bindParam(':password', $hashedPassword);
        
        if ($insertStmt->execute()) {
            $_SESSION['staff_message'] = "Admin Account created successfully";
            echo json_encode(['success' => 'Admin added successfully']);
        } else {
            echo json_encode(['error' => 'Failed to add admin. Please try again.']);
        }
    } else {
        echo json_encode(['error' => $errors[0]]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request method']);
}
?>