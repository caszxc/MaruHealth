<?php
// update_patient_ajax.php
session_start();
include 'config.php';

// ---------------------------------------------------------------------
// 1. AUTHORIZATION
// ---------------------------------------------------------------------
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    $_SESSION['patient_message'] = "Unauthorized access.";
    header("Location: view_patient.php?id=" . ($_POST['patient_id'] ?? ''));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['patient_message'] = "Invalid request method.";
    header("Location: view_patient.php?id=" . ($_POST['patient_id'] ?? ''));
    exit();
}

// ---------------------------------------------------------------------
// 2. INPUT
// ---------------------------------------------------------------------
$patient_id      = $_POST['patient_id'] ?? null;
$first_name      = trim($_POST['first_name'] ?? '');
$middle_name     = trim($_POST['middle_name'] ?? '');
$last_name       = trim($_POST['last_name'] ?? '');
$contact_number  = trim($_POST['contact_number'] ?? '');
$address         = trim($_POST['address'] ?? '');
$weight          = $_POST['weight'] ? floatval($_POST['weight']) : null;
$height          = $_POST['height'] ? floatval($_POST['height']) : null;
$bmi             = $_POST['bmi'] ? floatval($_POST['bmi']) : null;
$bmi_status      = $_POST['bmi_status'] ?? null;
$new_family_num  = trim($_POST['family_number'] ?? '');

// ---------------------------------------------------------------------
// 3. BASIC VALIDATIONS
// ---------------------------------------------------------------------
if (!$patient_id || !is_numeric($patient_id)) {
    $_SESSION['patient_message'] = "Invalid patient ID.";
    header("Location: view_patient.php?id=$patient_id");
    exit();
}

if ($contact_number !== '' && !preg_match('/^\d{10,11}$/', $contact_number)) {
    $_SESSION['patient_message'] = "Invalid contact number (10-11 digits).";
    header("Location: view_patient.php?id=$patient_id");
    exit();
}

if ($weight !== null && $weight <= 0) {
    $_SESSION['patient_message'] = "Weight must be positive.";
    header("Location: view_patient.php?id=$patient_id");
    exit();
}

if ($height !== null && $height <= 0) {
    $_SESSION['patient_message'] = "Height must be positive.";
    header("Location: view_patient.php?id=$patient_id");
    exit();
}

// ---------------------------------------------------------------------
// 4. START TRANSACTION
// ---------------------------------------------------------------------
try {
    $conn->beginTransaction();

    // -----------------------------------------------------------------
    // 4a. FETCH CURRENT PATIENT
    // -----------------------------------------------------------------
    $stmt = $conn->prepare("SELECT family_number FROM patients WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        throw new Exception('Patient not found');
    }
    $old_family_num = $patient['family_number'] ?? null;

    // -----------------------------------------------------------------
    // 4b. UPDATE PATIENT
    // -----------------------------------------------------------------
    $sql = "UPDATE patients SET
                first_name      = :first_name,
                middle_name     = :middle_name,
                last_name       = :last_name,
                contact_number  = :contact_number,
                address         = :address,
                weight          = :weight,
                height          = :height,
                bmi             = :bmi,
                bmi_status      = :bmi_status,
                family_number   = :family_number
            WHERE id = :patient_id";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':middle_name', $middle_name);
    $stmt->bindParam(':last_name', $last_name);
    $stmt->bindParam(':contact_number', $contact_number);
    $stmt->bindParam(':address', $address);
    $stmt->bindParam(':weight', $weight);
    $stmt->bindParam(':height', $height);
    $stmt->bindParam(':bmi', $bmi);
    $stmt->bindParam(':bmi_status', $bmi_status);

    if ($new_family_num === '') {
        $stmt->bindValue(':family_number', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':family_number', $new_family_num);
    }

    $stmt->execute();

    // -----------------------------------------------------------------
    // 5. FAMILY-NUMBER LOGIC
    // -----------------------------------------------------------------
    if ($old_family_num !== $new_family_num && !($old_family_num === null && $new_family_num === '')) {

        // Decrement old family
        if ($old_family_num !== null) {
            $stmt = $conn->prepare("SELECT member_count FROM families WHERE family_number = :fn FOR UPDATE");
            $stmt->execute([':fn' => $old_family_num]);
            $oldFam = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($oldFam) {
                $newCount = $oldFam['member_count'] - 1;
                if ($newCount > 0) {
                    $upd = $conn->prepare("UPDATE families SET member_count = :cnt WHERE family_number = :fn");
                    $upd->execute([':cnt' => $newCount, ':fn' => $old_family_num]);
                } else {
                    $del = $conn->prepare("DELETE FROM families WHERE family_number = :fn");
                    $del->execute([':fn' => $old_family_num]);
                }
            }
        }

        // Increment new family
        if ($new_family_num !== '') {
            $stmt = $conn->prepare("SELECT member_count FROM families WHERE family_number = :fn FOR UPDATE");
            $stmt->execute([':fn' => $new_family_num]);
            $newFam = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($newFam) {
                $upd = $conn->prepare("UPDATE families SET member_count = member_count + 1 WHERE family_number = :fn");
                $upd->execute([':fn' => $new_family_num]);
            } else {
                $ins = $conn->prepare("INSERT INTO families (family_number, member_count) VALUES (:fn, 1)");
                $ins->execute([':fn' => $new_family_num]);
            }
        }
    }

    // -----------------------------------------------------------------
    // 6. LOG THE ACTION
    // -----------------------------------------------------------------
    $fullName = trim("{$first_name} " . ($middle_name ? $middle_name . ' ' : '') . $last_name);
    $fullName = preg_replace('/\s+/', ' ', $fullName);

    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'update_patient_record', :details, :target_id)
    ");
    $logStmt->execute([
        ':admin_id'   => $_SESSION['admin_id'],
        ':details'    => "Updated patient: {$fullName}",
        ':target_id'  => $patient_id
    ]);

    // -----------------------------------------------------------------
    // 7. SUCCESS
    // -----------------------------------------------------------------
    $conn->commit();
    $_SESSION['patient_message'] = "Patient **{$fullName}** updated successfully.";

} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['patient_message'] = "Failed to update patient: " . $e->getMessage();
}

// ---------------------------------------------------------------------
// 8. REDIRECT BACK TO view_patient.php
// ---------------------------------------------------------------------
header("Location: view_patient.php?id=$patient_id");
exit();
?>