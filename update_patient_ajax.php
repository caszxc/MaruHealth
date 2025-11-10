<?php
// update_patient_ajax.php
session_start();
include 'config.php';

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$patient_id      = $_POST['patient_id'] ?? null;
$first_name      = trim($_POST['first_name'] ?? '');
$middle_name     = trim($_POST['middle_name'] ?? '');
$last_name       = trim($_POST['last_name'] ?? '');
$sex             = $_POST['sex'] ?? null;
$contact_number  = $_POST['contact_number'] ?? null;
$address         = trim($_POST['address'] ?? '');
$weight          = $_POST['weight'] ? floatval($_POST['weight']) : null;
$height          = $_POST['height'] ? floatval($_POST['height']) : null;
$bmi             = $_POST['bmi'] ? floatval($_POST['bmi']) : null;
$bmi_status      = $_POST['bmi_status'] ?? null;
$new_family_num  = trim($_POST['family_number'] ?? '');

// Validation
if (!$patient_id || empty($first_name) || empty($last_name) || !in_array($sex, ['Male', 'Female'])) {
    echo json_encode(['status' => 'error', 'message' => 'Required fields missing or invalid']);
    exit();
}

try {
    $conn->beginTransaction();

    // Fetch current patient data BEFORE update (for logging changes)
    $stmt = $conn->prepare("SELECT * FROM patients WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $patient_id]);
    $old_patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$old_patient) {
        throw new Exception('Patient not found');
    }

    // Update patient
    $sql = "UPDATE patients SET
                first_name = :first_name,
                middle_name = :middle_name,
                last_name = :last_name,
                sex = :sex,
                contact_number = :contact_number,
                address = :address,
                weight = :weight,
                height = :height,
                bmi = :bmi,
                bmi_status = :bmi_status,
                family_number = :family_number
            WHERE id = :patient_id";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':middle_name', $middle_name);
    $stmt->bindParam(':last_name', $last_name);
    $stmt->bindParam(':sex', $sex);
    $stmt->bindParam(':contact_number', $contact_number);
    $stmt->bindParam(':address', $address);
    $stmt->bindParam(':weight', $weight);
    $stmt->bindParam(':height', $height);
    $stmt->bindParam(':bmi', $bmi);
    $stmt->bindParam(':bmi_status', $bmi_status);
    $stmt->bindValue(':family_number', $new_family_num === '' ? null : $new_family_num, $new_family_num === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->execute();

    // Handle family number member count
    $old_family_num = $old_patient['family_number'];
    if ($old_family_num !== $new_family_num && !($old_family_num === null && $new_family_num === '')) {
        if ($old_family_num !== null) {
            $stmt = $conn->prepare("UPDATE families SET member_count = member_count - 1 WHERE family_number = :fn AND member_count > 0");
            $stmt->execute([':fn' => $old_family_num]);
            $conn->prepare("DELETE FROM families WHERE family_number = :fn AND member_count = 0")->execute([':fn' => $old_family_num]);
        }
        if ($new_family_num !== '') {
            $stmt = $conn->prepare("INSERT INTO families (family_number, member_count) VALUES (:fn, 1) ON DUPLICATE KEY UPDATE member_count = member_count + 1");
            $stmt->execute([':fn' => $new_family_num]);
        }
    }

    // === LOG CHANGES TO patient_updates_log ===
    $admin_name = $_SESSION['admin_name'] ?? 'Health Staff';
    $admin_id = $_SESSION['admin_id'];

    $fields_to_log = [
        'first_name' => 'First Name',
        'middle_name' => 'Middle Name',
        'last_name' => 'Last Name',
        'sex' => 'Sex',
        'contact_number' => 'Contact Number',
        'address' => 'Address',
        'weight' => 'Weight (kg)',
        'height' => 'Height (cm)',
        'bmi' => 'BMI',
        'bmi_status' => 'BMI Status',
        'family_number' => 'Family Number'
    ];

    // Map field names to their actual POST values
    $new_values = [
        'first_name'     => $first_name,
        'middle_name'    => $middle_name,
        'last_name'      => $last_name,
        'sex'            => $sex,
        'contact_number' => $contact_number,
        'address'        => $address,
        'weight'         => $weight,
        'height'         => $height,
        'bmi'            => $bmi,
        'bmi_status'     => $bmi_status,
        'family_number'  => $new_family_num === '' ? null : $new_family_num,
    ];

    foreach ($fields_to_log as $field => $label) {
        $old_val = $old_patient[$field] ?? null;
        $new_val = $new_values[$field] ?? null;

        // Convert both to float if the field is numeric
        if (in_array($field, ['weight', 'height', 'bmi'])) {
            $old_val = $old_val !== null ? floatval($old_val) : null;
            $new_val = $new_val !== null ? floatval($new_val) : null;
        } else {
            // For strings: trim and nullify empty
            $old_val = $old_val !== null ? trim($old_val) : null;
            $new_val = $new_val !== null ? trim($new_val) : null;
            $old_val = $old_val === '' ? null : $old_val;
            $new_val = $new_val === '' ? null : $new_val;
        }

        // Now strictly compare
        if ($old_val === $new_val) {
            continue; // No change
        }

        // Format display values
        $display_old = $old_val === null ? 'None' : $old_val;
        $display_new = $new_val === null ? 'None' : $new_val;

        // Special formatting for decimals
        if (in_array($field, ['weight', 'height'])) {
            $display_old = $old_val !== null ? number_format($old_val, 2) : 'None';
            $display_new = $new_val !== null ? number_format($new_val, 2) : 'None';
        } elseif ($field === 'bmi') {
            $display_old = $old_val !== null ? number_format($old_val, 1) : 'None';
            $display_new = $new_val !== null ? number_format($new_val, 1) : 'None';
        }

        $log_stmt = $conn->prepare("
            INSERT INTO patient_updates_log 
            (patient_id, updated_by_type, updated_by_id, updated_by_name, field_changed, old_value, new_value)
            VALUES 
            (:patient_id, 'health_staff', :updated_by_id, :updated_by_name, :field, :old_value, :new_value)
        ");
        $log_stmt->execute([
            ':patient_id'      => $patient_id,
            ':updated_by_id'   => $admin_id,
            ':updated_by_name' => $admin_name,
            ':field'           => $label,
            ':old_value'       => (string)$display_old,
            ':new_value'       => (string)$display_new
        ]);
    }

    // Log activity
    $fullName = trim("$first_name $middle_name $last_name");
    $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (?, 'update_patient_record', ?, ?)
    ")->execute([
        $admin_id,
        "Updated patient: $fullName",
        $patient_id
    ]);

    $conn->commit();

    $_SESSION['patient_message'] = "Patient updated successfully!";
    echo json_encode(['status' => 'success', 'message' => 'Patient updated successfully']);

} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['patient_message'] = "Error: " . $e->getMessage();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>