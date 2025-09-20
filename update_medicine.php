<?php
// update_medicine.php
session_start();
require_once "config.php";

// Check if user is logged in as staff or super_admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['staff', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// Initialize variables for form data and errors
$medicine_id = $therapeutic_category = $generic_name = $brand_name = $dosage_form = $dosage = $unit = $min_stock = "";
$errors = [];

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input
    $medicine_id = trim($_POST['medicine_id'] ?? '');
    $therapeutic_category = trim($_POST['therapeutic_category'] ?? '');
    $generic_name = trim($_POST['generic_name'] ?? '');
    $brand_name = trim($_POST['brand_name'] ?? '');
    $dosage_form = trim($_POST['dosage_form'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $min_stock = trim($_POST['min_stock'] ?? '');

    // Validation checks
    if (empty($medicine_id)) {
        $errors[] = "Medicine ID is required.";
    }
    if (empty($therapeutic_category)) {
        $errors[] = "Therapeutic Category is required.";
    }
    if (empty($generic_name)) {
        $errors[] = "Generic Name is required.";
    }
    if (empty($dosage_form)) {
        $errors[] = "Dosage Form is required.";
    }
    if (empty($dosage)) {
        $errors[] = "Dosage is required.";
    }
    if (empty($unit)) {
        $errors[] = "Unit is required.";
    }
    if (empty($min_stock) || $min_stock < 0) {
        $errors[] = "Minimum Stock is required and must be a non-negative number.";
    }

    // Check for duplicate medicine (excluding the current medicine)
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM medicines_catalog WHERE generic_name = :generic_name AND brand_name = :brand_name AND dosage = :dosage AND dosage_form = :dosage_form AND id != :medicine_id");
    $checkStmt->execute([
        ':generic_name' => $generic_name,
        ':brand_name' => $brand_name ?: null,
        ':dosage' => $dosage,
        ':dosage_form' => $dosage_form,
        ':medicine_id' => $medicine_id
    ]);
    $exists = $checkStmt->fetchColumn();

    if ($exists > 0) {
        $errors[] = "This medicine already exists in the catalog.";
    }

    // If no errors, update the medicine in the catalog
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE medicines_catalog SET therapeutic_category = :therapeutic_category, generic_name = :generic_name, brand_name = :brand_name, dosage = :dosage, dosage_form = :dosage_form, unit = :unit, min_stock = :min_stock WHERE id = :medicine_id");
            $stmt->execute([
                ':therapeutic_category' => $therapeutic_category,
                ':generic_name' => $generic_name,
                ':brand_name' => $brand_name ?: null,
                ':dosage' => $dosage,
                ':dosage_form' => $dosage_form,
                ':unit' => $unit,
                ':min_stock' => $min_stock,
                ':medicine_id' => $medicine_id
            ]);

            echo json_encode(['success' => true, 'message' => 'Medicine updated successfully!']);
            exit();
        } catch (PDOException $e) {
            $errors[] = "Error updating medicine: " . $e->getMessage();
        }
    }

    // Return errors as JSON
    echo json_encode(['success' => false, 'message' => implode('\n', $errors)]);
    exit();
}
?>