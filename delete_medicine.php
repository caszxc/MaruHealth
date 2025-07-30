<?php
session_start();
require_once "config.php"; // Include database connection

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id > 0) {
        try {
            $stmt = $conn->prepare("DELETE FROM medicines WHERE id = ?");
            $stmt->execute([$id]);

            echo "Medicine deleted successfully.";
        } catch (PDOException $e) {
            echo "Error deleting medicine: " . $e->getMessage();
        }
    } else {
        echo "Invalid medicine ID.";
    }
} else {
    echo "Invalid request.";
}
?>
