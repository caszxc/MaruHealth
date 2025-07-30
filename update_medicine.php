<?php
//update_medicine.php
session_start();
require_once "config.php";

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    echo "Unauthorized access.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Invalid request method.";
    exit();
}

$medicine_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$admin_id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;

if ($medicine_id <= 0 || $admin_id <= 0) {
    echo "Invalid medicine or admin ID.";
    exit();
}

try {
    // Prepare input data
    $therapeutic_category = $_POST['therapeutic_category'] ?? '';
    $batch_lot_number = $_POST['batch_lot_number'] ?? '';
    $pono = $_POST['pono'] ?? null;
    $generic_name = $_POST['generic_name'] ?? '';
    $brand_name = $_POST['brand_name'] ?? null;
    $dosage_form = $_POST['dosage_form'] ?? null;
    $dosage = $_POST['dosage'] ?? null;
    $unit = $_POST['unit'] ?? null;
    $manufacturing_date = $_POST['manufacturing_date'] ?? null;
    $expiration_date = $_POST['expiration_date'] ?? '';
    $source = $_POST['source'] ?? null;
    $min_stock = isset($_POST['min_stock']) ? (int)$_POST['min_stock'] : 0;

    // Determine expiry status
    $current_date = date('Y-m-d');
    $expiry_date = new DateTime($expiration_date);
    $today = new DateTime($current_date);
    $days_until_expiry = $today->diff($expiry_date)->days;

    if ($expiry_date <= $today) {
        $expiry_status = 'Expired';
    } elseif ($days_until_expiry <= 7) {
        $expiry_status = 'Expiring within a week';
    } elseif ($days_until_expiry <= 30) {
        $expiry_status = 'Expiring within a month';
    } else {
        $expiry_status = 'Valid';
    }

    // Update medicine
    $stmt = $conn->prepare("UPDATE medicines SET 
        therapeutic_category = ?, 
        batch_lot_number = ?, 
        pono = ?, 
        generic_name = ?, 
        brand_name = ?, 
        dosage_form = ?, 
        dosage = ?, 
        unit = ?, 
        manufacturing_date = ?, 
        expiration_date = ?, 
        source = ?, 
        min_stock = ?,
        expiry_status = ?
        WHERE id = ?");

    $stmt->execute([
        $therapeutic_category, $batch_lot_number, $pono, $generic_name, $brand_name,
        $dosage_form, $dosage, $unit, $manufacturing_date, $expiration_date,
        $source, $min_stock, $expiry_status, $medicine_id
    ]);

    echo "Medicine updated successfully!";
} catch (PDOException $e) {
    echo "Error updating medicine: " . $e->getMessage();
}
?>