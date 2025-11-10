<?php
// update_profile.php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

$user_id        = $_SESSION['user_id'];
$active_user_id = $_SESSION['active_user_id'] ?? $user_id;
$is_dependent   = ($active_user_id != $user_id);

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit();
}

/* ------------------------------------------------------------------
   1. Get CURRENT values (for comparison & patient-record lookup)
   ------------------------------------------------------------------ */
$stmt = $conn->prepare("
    SELECT first_name, last_name, middle_name, gender, birthday,
           address, phone_number, email, family_number
    FROM users WHERE id = :id
");
$stmt->execute([':id' => $active_user_id]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current) {
    $response['message'] = 'User not found.';
    echo json_encode($response);
    exit();
}

/* ------------------------------------------------------------------
   2. Gather NEW values
   ------------------------------------------------------------------ */
$first_name   = trim($_POST['first_name'] ?? '');
$last_name    = trim($_POST['last_name'] ?? '');
$middle_name  = trim($_POST['middle_name'] ?? '');
$gender       = $_POST['gender'] ?? '';
$birthday     = $_POST['birthday'] ?? '';
$address      = trim($_POST['address'] ?? '');

$phone_number = $is_dependent ? $current['phone_number']
                             : trim($_POST['phone_number'] ?? '');
$email        = $is_dependent ? $current['email']
                             : trim($_POST['email'] ?? '');

/* ------------------------------------------------------------------
   3. Basic validation
   ------------------------------------------------------------------ */
$required = [$first_name,$last_name,$middle_name,$gender,$birthday,$address];
if (in_array('', $required, true)) {
    $response['message'] = 'All required fields must be filled.';
    echo json_encode($response);
    exit();
}

if (!$is_dependent) {
    if (empty($phone_number) || empty($email)) {
        $response['message'] = 'Phone and email are required for primary accounts.';
        echo json_encode($response);
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format.';
        echo json_encode($response);
        exit();
    }
    if (!preg_match('/^\+?\d{10,15}$/', $phone_number)) {
        $response['message'] = 'Invalid phone number.';
        echo json_encode($response);
        exit();
    }

    // uniqueness checks for primary accounts
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND primary_user_id IS NULL");
    $check->execute([$email, $active_user_id]);
    if ($check->rowCount()) {
        $response['message'] = 'Email already used by another primary account.';
        echo json_encode($response);
        exit();
    }

    $check = $conn->prepare("SELECT id FROM users WHERE phone_number = ? AND id != ? AND primary_user_id IS NULL");
    $check->execute([$phone_number, $active_user_id]);
    if ($check->rowCount()) {
        $response['message'] = 'Phone number already used by another primary account.';
        echo json_encode($response);
        exit();
    }
}

/* ------------------------------------------------------------------
   4. Start transaction
   ------------------------------------------------------------------ */
$conn->beginTransaction();

try {
    /* --------------------------------------------------------------
       4a. Update users table
       -------------------------------------------------------------- */
    if ($is_dependent) {
        $sql = "UPDATE users SET
                    first_name   = :fn,
                    last_name    = :ln,
                    middle_name  = :mn,
                    gender       = :g,
                    birthday     = :b,
                    address      = :a
                WHERE id = :id";
        $upd = $conn->prepare($sql);
        $upd->execute([
            ':fn' => $first_name,
            ':ln' => $last_name,
            ':mn' => $middle_name,
            ':g'  => $gender,
            ':b'  => $birthday,
            ':a'  => $address,
            ':id' => $active_user_id
        ]);
    } else {
        $sql = "UPDATE users SET
                    first_name   = :fn,
                    last_name    = :ln,
                    middle_name  = :mn,
                    gender       = :g,
                    birthday     = :b,
                    address      = :a,
                    phone_number = :ph,
                    email        = :em
                WHERE id = :id";
        $upd = $conn->prepare($sql);
        $upd->execute([
            ':fn' => $first_name,
            ':ln' => $last_name,
            ':mn' => $middle_name,
            ':g'  => $gender,
            ':b'  => $birthday,
            ':a'  => $address,
            ':ph' => $phone_number,
            ':em' => $email,
            ':id' => $active_user_id
        ]);

        // sync phone & email to all dependents
        $sync = $conn->prepare("UPDATE users SET phone_number = ?, email = ? WHERE primary_user_id = ?");
        $sync->execute([$phone_number, $email, $active_user_id]);
    }

    /* --------------------------------------------------------------
       4b. Find the linked patient record (if any)
       -------------------------------------------------------------- */
    $patStmt = $conn->prepare("
        SELECT id FROM patients
        WHERE first_name = ? AND last_name = ? AND birthdate = ?
        LIMIT 1
    ");
    $patStmt->execute([$current['first_name'], $current['last_name'], $current['birthday']]);
    $patientRow = $patStmt->fetch(PDO::FETCH_ASSOC);
    $patient_id = $patientRow['id'] ?? null;

    /* --------------------------------------------------------------
       4c. Update patient record (if exists)
       -------------------------------------------------------------- */
    if ($patient_id) {
        $patUpd = $conn->prepare("
            UPDATE patients SET
                first_name     = :fn,
                middle_name    = :mn,
                last_name      = :ln,
                birthdate      = :b,
                sex            = :g,
                contact_number = :ph,
                address        = :a
            WHERE id = :pid
        ");
        $patUpd->execute([
            ':fn' => $first_name,
            ':mn' => $middle_name,
            ':ln' => $last_name,
            ':b'  => $birthday,
            ':g'  => $gender,
            ':ph' => $phone_number,
            ':a'  => $address,
            ':pid'=> $patient_id
        ]);
    }

    /* --------------------------------------------------------------
       4d. LOG every changed field into patient_updates_log
       -------------------------------------------------------------- */
    if ($patient_id) {
        $logFields = [
            'first_name'   => ['First Name',   $current['first_name']],
            'last_name'    => ['Last Name',    $current['last_name']],
            'middle_name'  => ['Middle Name',  $current['middle_name']],
            'gender'       => ['Gender',       $current['gender']],
            'birthday'     => ['Birthdate',    $current['birthday']],
            'address'      => ['Address',      $current['address']],
        ];

        if (!$is_dependent) {
            $logFields['phone_number'] = ['Phone Number', $current['phone_number']];
            $logFields['email']        = ['Email',        $current['email']];
        }

        $logStmt = $conn->prepare("
            INSERT INTO patient_updates_log
                (patient_id, updated_by_type, updated_by_id, updated_by_name,
                 field_changed, old_value, new_value)
            VALUES
                (:pid, 'user', :uid, :name, :field, :old, :new)
        ");

        $userName = trim("{$current['first_name']} {$current['middle_name']} {$current['last_name']}");

        foreach ($logFields as $postKey => $info) {
            $old = $info[1] ?? '';
            $new = ${$postKey}; // variable variable → $first_name, $phone_number …

            if ($old !== $new) {
                $logStmt->execute([
                    ':pid'   => $patient_id,
                    ':uid'   => $active_user_id,
                    ':name'  => $userName,
                    ':field' => $info[0],
                    ':old'   => $old ?: null,
                    ':new'   => $new ?: null
                ]);
            }
        }
    }

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Profile updated successfully.';

} catch (Exception $e) {
    $conn->rollBack();
    $response['message'] = 'Database error: '.$e->getMessage();
}

echo json_encode($response);
exit();
?>