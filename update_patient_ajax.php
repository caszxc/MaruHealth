<?php
// update_patient_ajax.php
session_start();
include 'config.php';

// ---------------------------------------------------------------------
// 1. AUTHORIZATION
// ---------------------------------------------------------------------
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

// ---------------------------------------------------------------------
// 2. INPUT
// ---------------------------------------------------------------------
$patient_id      = $_POST['patient_id'] ?? null;
$first_name      = $_POST['first_name'] ?? null;
$middle_name     = $_POST['middle_name'] ?? null;
$last_name       = $_POST['last_name'] ?? null;
$contact_number  = $_POST['contact_number'] ?? null;
$address         = $_POST['address'] ?? null;
$weight          = $_POST['weight'] ? floatval($_POST['weight']) : null;
$height          = $_POST['height'] ? floatval($_POST['height']) : null;
$bmi             = $_POST['bmi'] ? floatval($_POST['bmi']) : null;
$bmi_status      = $_POST['bmi_status'] ?? null;
$new_family_num  = trim($_POST['family_number'] ?? '');

// ---------------------------------------------------------------------
// 3. BASIC VALIDATIONS
// ---------------------------------------------------------------------
if (!$patient_id) {
    echo json_encode(['status' => 'error', 'message' => 'Patient ID is required']);
    exit();
}

if ($contact_number !== null && !preg_match('/^\d{10,11}$/', $contact_number)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid contact number (10-11 digits)']);
    exit();
}
if ($weight !== null && $weight <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Weight must be positive']);
    exit();
}
if ($height !== null && $height <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Height must be positive']);
    exit();
}

// ---------------------------------------------------------------------
// 4. START TRANSACTION
// ---------------------------------------------------------------------
try {
    $conn->beginTransaction();

    // -----------------------------------------------------------------
    // 4a. FETCH CURRENT PATIENT (including current family_number)
    // -----------------------------------------------------------------
    $stmt = $conn->prepare("SELECT family_number FROM patients WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        throw new Exception('Patient not found');
    }
    $old_family_num = $patient['family_number'] ?? null;

    // -----------------------------------------------------------------
    // 4b. PREPARE UPDATE QUERY
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
    // 6. LOG THE ACTION (like add_patient_ajax.php)
    // -----------------------------------------------------------------
    $fullName = trim("{$first_name} {$middle_name} {$last_name}");
    $fullName = trim(str_replace('  ', ' ', $fullName)); // clean double spaces

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
    // 7. COMMIT
    // -----------------------------------------------------------------
    $conn->commit();

    echo json_encode(['status' => 'success', 'message' => 'Patient information updated successfully']);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>