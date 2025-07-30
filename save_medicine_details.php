<?php
session_start();
require_once "config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit();
    }

    $id = $_POST['id'];
    $action = $_POST['action'];
    $name = $_POST['medicine_name'];
    $type = $_POST['type'];
    $form = $_POST['med_form'];
    $storage_location = $_POST['storage_location'];
    $unit = $_POST['unit'];
    $quantity = $_POST['quantity'];
    $status = $_POST['status'];
    $min_stock = $_POST['min_stock'];
    $expiry_date = $_POST['expiry_date'];
    $manufactured_date = $_POST['manufactured_date'];

    try {
        if ($action === 'edit') {
            // Update existing medicine record
            $stmt = $conn->prepare("UPDATE medicines SET name = ?, type = ?, form = ?, storage_location = ?, unit = ?, quantity = ?, status = ?, min_stock = ?, expiry_date = ?, manufactured_date = ? WHERE id = ?");
            $stmt->execute([$name, $type, $form, $storage_location, $unit, $quantity, $status, $min_stock, $expiry_date, $manufactured_date, $id]);

            echo json_encode(['status' => 'success', 'message' => 'Medicine details updated successfully']);
        } elseif ($action === 'add') {
            // Add new medicine details
            $stmt = $conn->prepare("UPDATE medicines SET form = ?, storage_location = ?, unit = ?, quantity = ?, status = ?, min_stock = ?, expiry_date = ?, manufactured_date = ? WHERE id = ?");
            $stmt->execute([$form, $storage_location, $unit, $quantity, $status, $min_stock, $expiry_date, $manufactured_date, $id]);

            echo json_encode(['status' => 'success', 'message' => 'Medicine details added successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
