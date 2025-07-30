<?php
require_once "config.php";

// Ensure the request includes an ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'Invalid staff ID']);
    exit();
}

$staffId = $_GET['id'];

// Fetch staff details
try {
    $stmt = $conn->prepare("SELECT full_name, role, email, username, created_at FROM admin_staff WHERE id = :id");
    $stmt->bindParam(':id', $staffId);
    $stmt->execute();
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($staff) {
        // Format the created_at date
        $staff['created_at'] = date('m/d/Y g:iA', strtotime($staff['created_at']));
        echo json_encode($staff);
    } else {
        echo json_encode(['error' => 'Staff member not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>