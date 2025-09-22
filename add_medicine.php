<?php
// add_medicine.php
session_start();
require_once "config.php"; // Include database connection

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
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
            $conn->beginTransaction();

            // Insert into medicines_catalog
            $stmt = $conn->prepare("INSERT INTO medicines_catalog (therapeutic_category, generic_name, brand_name, dosage, dosage_form, unit, min_stock) VALUES (:therapeutic_category, :generic_name, :brand_name, :dosage, :dosage_form, :unit, :min_stock)");
            $stmt->execute([
                ':therapeutic_category' => $therapeutic_category,
                ':generic_name' => $generic_name,
                ':brand_name' => $brand_name ?: null,
                ':dosage' => $dosage,
                ':dosage_form' => $dosage_form,
                ':unit' => $unit,
                ':min_stock' => $min_stock
            ]);

            // Get the inserted catalog ID
            $catalog_id = $conn->lastInsertId();

            // Log the action in medicine_history
            $details = "Added medicine: $generic_name" . ($brand_name ? " ($brand_name)" : "") . ", Dosage: $dosage $dosage_form, Unit: $unit, Min Stock: $min_stock";
            $historyStmt = $conn->prepare("INSERT INTO medicine_history (catalog_id, action_type, details, performed_by) VALUES (:catalog_id, 'add_catalog', :details, :performed_by)");
            $historyStmt->execute([
                ':catalog_id' => $catalog_id,
                ':details' => $details,
                ':performed_by' => $_SESSION['admin_id']
            ]);

            $conn->commit();

            $_SESSION['success'] = "Medicine added successfully!";
            header("Location: medicine_management.php");
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
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