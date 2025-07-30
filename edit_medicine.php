<?php
session_start();
require_once "config.php"; // Database connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
        exit();
    }

    // Collect form data
    $id = $_POST["edit_id"];
    $name = $_POST["medicine_name"];
    $generic_name = $_POST["generic_name"];
    $brand = $_POST["brand"];
    $dosage = $_POST["dosage"]; // Ensure dosage is retrieved
    $type = $_POST["type"];
    $storage_location = $_POST["storage_location"];
    $min_stock = $_POST["min_stock"]; // Ensure min_stock is retrieved
    $quantity = $_POST["quantity"];
    $manufactured_date = $_POST["manufactured_date"];
    $expiration_date = $_POST["expiration_date"];
    $status = $_POST["status"];

    try {
        $stmt = $conn->prepare("UPDATE medicines SET 
            name = ?, 
            generic_name = ?, 
            brand = ?, 
            dosage = ?, 
            type = ?, 
            storage_location = ?, 
            min_stock = ?, 
            quantity = ?, 
            manufactured_stored = ?, 
            expiry_date = ?, 
            status = ? 
            WHERE id = ?");
            
        $stmt->execute([
            $name, $generic_name, $brand, $dosage, $type, 
            $storage_location, $min_stock, $quantity, 
            $manufactured_date, $expiration_date, $status, $id
        ]);

        echo json_encode(["status" => "success", "message" => "Medicine updated successfully"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
}
?>
