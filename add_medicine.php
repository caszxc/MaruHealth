<?php
session_start();
require_once "config.php"; // Database connection

// Ensure only logged-in super admin or staff can access
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Check if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and collect input data
    $therapeutic_category = trim($_POST["therapeutic_category"]);
    $batch_lot_number = trim($_POST["batch_lot_number"]);
    $pono = isset($_POST["pono"]) ? trim($_POST["pono"]) : null;
    $generic_name = trim($_POST["generic_name"]);
    $brand_name = isset($_POST["brand_name"]) ? trim($_POST["brand_name"]) : null;
    $dosage_form = trim($_POST["dosage_form"]);
    $dosage = trim($_POST["dosage"]);
    $unit = trim($_POST["unit"]);
    $manufacturing_date = !empty($_POST["manufacturing_date"]) ? $_POST["manufacturing_date"] : null;
    $expiration_date = $_POST["expiration_date"];
    $source = isset($_POST["source"]) ? trim($_POST["source"]) : null;
    $stocks = isset($_POST["stocks"]) ? intval($_POST["stocks"]) : 0;
    $min_stock = isset($_POST["min_stock"]) ? intval($_POST["min_stock"]) : 0;
    $admin_id = $_SESSION['admin_id'];

    // Validate inputs
    if (empty($therapeutic_category) || empty($batch_lot_number) || empty($generic_name) || 
        empty($dosage_form) || empty($dosage) || empty($unit) || empty($expiration_date)) {
        $_SESSION['error'] = "All required fields must be filled.";
        header("Location: medicine_management.php");
        exit();
    }

    if ($stocks < 0 || $min_stock < 1) {
        $_SESSION['error'] = "Initial stock cannot be negative, and minimum stock must be at least 1.";
        header("Location: medicine_management.php");
        exit();
    }

    // Determine stock status based on stock levels
    if ($stocks <= 0) {
        $stock_status = 'Out of Stock';
    } elseif ($stocks <= $min_stock) {
        $stock_status = 'Low Stock';
    } else {
        $stock_status = 'In Stock';
    }

    // Determine expiry status based on expiration date
    $current_date = date('Y-m-d');
    $expiry_date = new DateTime($expiration_date);
    $today = new DateTime($current_date);
    $days_until_expiry = $today->diff($expiry_date)->days;

    if ($expiry_date < $today) {
        $expiry_status = 'Expired';
    } elseif ($days_until_expiry <= 7) {
        $expiry_status = 'Expiring within a week';
    } elseif ($days_until_expiry <= 30) {
        $expiry_status = 'Expiring within a month';
    } else {
        $expiry_status = 'Valid';
    }

    try {
        // Begin transaction to ensure atomicity
        $conn->beginTransaction();

        // Insert medicine into medicines table
        $stmt = $conn->prepare("
            INSERT INTO medicines (
                therapeutic_category, batch_lot_number, pono,
                generic_name, brand_name, dosage_form, dosage,
                unit, manufacturing_date, expiration_date,
                source, stocks, min_stock, stock_status, expiry_status
            ) VALUES (
                :therapeutic_category, :batch_lot_number, :pono,
                :generic_name, :brand_name, :dosage_form, :dosage,
                :unit, :manufacturing_date, :expiration_date,
                :source, :stocks, :min_stock, :stock_status, :expiry_status
            )
        ");

        $stmt->execute([
            ':therapeutic_category' => $therapeutic_category,
            ':batch_lot_number' => $batch_lot_number,
            ':pono' => $pono,
            ':generic_name' => $generic_name,
            ':brand_name' => $brand_name,
            ':dosage_form' => $dosage_form,
            ':dosage' => $dosage,
            ':unit' => $unit,
            ':manufacturing_date' => $manufacturing_date,
            ':expiration_date' => $expiration_date,
            ':source' => $source,
            ':stocks' => $stocks,
            ':min_stock' => $min_stock,
            ':stock_status' => $stock_status,
            ':expiry_status' => $expiry_status
        ]);

        // Get the ID of the newly inserted medicine
        $medicine_id = $conn->lastInsertId();

        // Log initial stock in stock_history if stocks > 0
        if ($stocks > 0) {
            $historyStmt = $conn->prepare("
                INSERT INTO stock_history (medicine_id, quantity_change, reason, changed_by)
                VALUES (:medicine_id, :quantity_change, :reason, :changed_by)
            ");
            $historyStmt->execute([
                ':medicine_id' => $medicine_id,
                ':quantity_change' => $stocks,
                ':reason' => 'Initial Stock',
                ':changed_by' => $admin_id
            ]);
        }

        // Commit transaction
        $conn->commit();

        $_SESSION['success'] = "Medicine added successfully.";
        header("Location: medicine_management.php");
        exit();

    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header("Location: medicine_management.php");
        exit();
    }
} else {
    header("Location: medicine_management.php");
    exit();
}
?>