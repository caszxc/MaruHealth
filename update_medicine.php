<?php
// update_medicine.php
session_start();
require_once "config.php";

// Check if user is logged in as health staff 
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
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

    $batchCheck = $conn->prepare("SELECT COUNT(*) FROM medicine_batches WHERE catalog_id = :medicine_id");
    $batchCheck->execute([':medicine_id' => $medicine_id]);
    if ($batchCheck->fetchColumn() > 0) {
        $errors[] = "Cannot edit: Medicine has associated batches.";
    }

    // If no errors, update the medicine in the catalog
    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Fetch current medicine details for comparison
            $currentStmt = $conn->prepare("SELECT * FROM medicines_catalog WHERE id = :medicine_id");
            $currentStmt->execute([':medicine_id' => $medicine_id]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

            // Update medicines_catalog
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

            // Log changes in medicine_history
            $changes = [];
            if ($current['therapeutic_category'] !== $therapeutic_category) $changes[] = "Therapeutic Category: {$current['therapeutic_category']} to $therapeutic_category";
            if ($current['generic_name'] !== $generic_name) $changes[] = "Generic Name: {$current['generic_name']} to $generic_name";
            if ($current['brand_name'] !== ($brand_name ?: null)) $changes[] = "Brand Name: {$current['brand_name']} to " . ($brand_name ?: 'None');
            if ($current['dosage'] !== $dosage) $changes[] = "Dosage: {$current['dosage']} to $dosage";
            if ($current['dosage_form'] !== $dosage_form) $changes[] = "Dosage Form: {$current['dosage_form']} to $dosage_form";
            if ($current['unit'] !== $unit) $changes[] = "Unit: {$current['unit']} to $unit";
            if ($current['min_stock'] !== $min_stock) $changes[] = "Min Stock: {$current['min_stock']} to $min_stock";

            if (!empty($changes)) {
                $details = "Updated medicine: " . implode(', ', $changes);
                $historyStmt = $conn->prepare("INSERT INTO medicine_history (catalog_id, action_type, details, performed_by) VALUES (:catalog_id, 'update_catalog', :details, :performed_by)");
                $historyStmt->execute([
                    ':catalog_id' => $medicine_id,
                    ':details' => $details,
                    ':performed_by' => $_SESSION['admin_id']
                ]);

                // Log in activity_logs
                $logStmt = $conn->prepare("
                    INSERT INTO activity_logs (admin_id, action_type, action_details, target_id) 
                    VALUES (:admin_id, 'update_medicine', :details, :medicine_id)
                ");
                $logDetails = "Updated medicine: " . implode('; ', $changes);
                $logStmt->execute([
                    ':admin_id' => $_SESSION['admin_id'],
                    ':details' => $logDetails,
                    ':medicine_id' => $medicine_id
                ]);
            }

            $conn->commit();
            $_SESSION['medicine_message'] = "Medicine updated successfully!";
            echo json_encode(['success' => true, 'message' => 'Medicine updated successfully!']);
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = "Error updating medicine: " . $e->getMessage();
        }
    }

    // Return errors as JSON
    echo json_encode(['success' => false, 'message' => implode('\n', $errors)]);
    exit();
}
?>