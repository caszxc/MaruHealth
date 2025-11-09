<?php
// request_deletion.php
session_start();
require_once "config.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$primary_id = $_SESSION['user_id'];
$active_id  = $_SESSION['active_user_id'] ?? $primary_id;
$is_dep     = ($active_id != $primary_id);
$type       = $_POST['type'] ?? ''; // 'primary' or 'dependent'

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$reason   = trim($_POST['reason'] ?? '');
$password = $_POST['password'] ?? '';

if ($reason === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'All fields required']);
    exit;
}

// Determine which user is being deleted
if ($type === 'dependent') {
    $target_user_id = (int)($_POST['dependent_id'] ?? 0);
    if ($target_user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid dependent ID']);
        exit;
    }

    // Verify dependent belongs to this primary
    $stmt = $conn->prepare("SELECT 1 FROM dependent_relationships WHERE dependent_user_id = :dep_id AND primary_user_id = :pid");
    $stmt->execute([':dep_id' => $target_user_id, ':pid' => $primary_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Dependent not found or not yours']);
        exit;
    }
} else {
    $target_user_id = $primary_id; // primary deletion
}

// Verify password (always primary's password)
$stmt = $conn->prepare("SELECT password FROM users WHERE id = :id");
$stmt->execute([':id' => $primary_id]);
$hash = $stmt->fetchColumn();

if (!password_verify($password, $hash)) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password']);
    exit;
}

try {
    $conn->beginTransaction();

    $sql = "INSERT INTO account_deletion_requests 
            (user_id, primary_user_id, reason) 
            VALUES (:uid, :pid, :reason)";
    $ins = $conn->prepare($sql);
    $ins->execute([
        ':uid'    => $target_user_id,
        ':pid'    => $primary_id,
        ':reason' => $reason
    ]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Deletion request submitted']);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}