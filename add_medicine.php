<?php
// add_medicine.php
session_start();
require_once "config.php"; // Include database connection

// Check if user is logged in as staff or super_admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['staff', 'super_admin'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Initialize variables for form data and errors
$therapeutic_category = $generic_name = $brand_name = $dosage_form = $dosage = $unit = $min_stock = "";
$errors = [];

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input
    $therapeutic_category = trim($_POST['therapeutic_category']);
    $generic_name = trim($_POST['generic_name']);
    $brand_name = trim($_POST['brand_name']);
    $dosage_form = trim($_POST['dosage_form']);
    $dosage = trim($_POST['dosage']);
    $unit = trim($_POST['unit']);
    $min_stock = trim($_POST['min_stock']);

    // Validation checks
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

    // Check for duplicate medicine
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM medicines_catalog WHERE generic_name = :generic_name AND brand_name = :brand_name AND dosage = :dosage AND dosage_form = :dosage_form");
    $checkStmt->execute([
        ':generic_name' => $generic_name,
        ':brand_name' => $brand_name ?: null, // Handle empty brand_name
        ':dosage' => $dosage,
        ':dosage_form' => $dosage_form
    ]);
    $exists = $checkStmt->fetchColumn();

    if ($exists > 0) {
        $errors[] = "This medicine already exists in the catalog.";
    }

    // If no errors, insert the medicine into the catalog
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO medicines_catalog (therapeutic_category, generic_name, brand_name, dosage, dosage_form, unit, min_stock) VALUES (:therapeutic_category, :generic_name, :brand_name, :dosage, :dosage_form, :unit, :min_stock)");
            $stmt->execute([
                ':therapeutic_category' => $therapeutic_category,
                ':generic_name' => $generic_name,
                ':brand_name' => $brand_name ?: null, // Handle empty brand_name
                ':dosage' => $dosage,
                ':dosage_form' => $dosage_form,
                ':unit' => $unit,
                ':min_stock' => $min_stock
            ]);

            // Redirect back to medicine management with success message
            $_SESSION['success'] = "Medicine added successfully!";
            header("Location: medicine_management.php");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Error adding medicine: " . $e->getMessage();
        }
    }

    // If there are errors, store them in session and redirect back
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        header("Location: medicine_management.php");
        exit();
    }
}
?>