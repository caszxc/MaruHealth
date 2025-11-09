<?php
// cancel_deletion.php
session_start();
require_once "config.php";

header('Content-Type: application/json');

/* ------------------------------------------------------------------
   1. BASIC AUTH – must be a logged-in primary user (role = 'user')
   ------------------------------------------------------------------ */
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'user'
) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$primary_user_id = (int)$_SESSION['user_id'];               // the real logged-in account
$active_user_id  = $_SESSION['active_user_id'] ?? $primary_user_id;

/* ------------------------------------------------------------------
   2. INPUT – we accept two ways of identifying the request
       • request_id          – used from the overlay / own profile
       • dependent_user_id   – used from the Dependents tab
   ------------------------------------------------------------------ */
$request_id        = (int)($_POST['request_id'] ?? 0);
$dependent_user_id = (int)($_POST['dependent_user_id'] ?? 0);

if ($request_id <= 0 && $dependent_user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing request identifier']);
    exit;
}

/* ------------------------------------------------------------------
   3. Resolve the request_id if we only received a dependent_user_id
   ------------------------------------------------------------------ */
if ($request_id === 0 && $dependent_user_id > 0) {
    // Find a pending request for this dependent
    $stmt = $conn->prepare("
        SELECT id
        FROM account_deletion_requests
        WHERE user_id = :dep_id
          AND status = 'pending'
        LIMIT 1
    ");
    $stmt->execute([':dep_id' => $dependent_user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'No pending deletion request for this dependent']);
        exit;
    }
    $request_id = (int)$row['id'];
}

/* ------------------------------------------------------------------
   4. SECURITY CHECK – the request must belong to:
       • the currently active user   OR
       • a dependent of the primary user
   ------------------------------------------------------------------ */
try {
    $conn->beginTransaction();

    // Fetch the request + its user_id in one go
    $chk = $conn->prepare("
        SELECT adr.user_id, adr.primary_user_id, u.primary_user_id AS user_primary
        FROM account_deletion_requests adr
        LEFT JOIN users u ON adr.user_id = u.id
        WHERE adr.id = :rid
          AND adr.status = 'pending'
        LIMIT 1
    ");
    $chk->execute([':rid' => $request_id]);
    $req = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        throw new Exception('Deletion request not found or already processed.');
    }

    $req_user_id        = (int)$req['user_id'];          // who requested deletion
    $req_primary_id     = $req['primary_user_id'] ? (int)$req['primary_user_id'] : null;
    $user_primary_id    = $req['user_primary'] ? (int)$req['user_primary'] : null;

    // ---- CASE A: active user cancelling his own request -----------------
    if ($req_user_id === $active_user_id) {
        // allowed – nothing more to check
    }
    // ---- CASE B: primary user cancelling a dependent's request ----------
    else {
        // The primary user must be the one logged in
        if ($primary_user_id !== $primary_user_id) {
            throw new Exception('You are not allowed to cancel this request.');
        }

        // The request must belong to one of the primary's dependents
        $depCheck = $conn->prepare("
            SELECT 1
            FROM dependent_relationships
            WHERE primary_user_id = :primary
              AND dependent_user_id = :dep
            LIMIT 1
        ");
        $depCheck->execute([
            ':primary' => $primary_user_id,
            ':dep'     => $req_user_id
        ]);
        if (!$depCheck->fetch()) {
            throw new Exception('The request does not belong to one of your dependents.');
        }
    }

    // -----------------------------------------------------------------
    // 5. CANCEL THE REQUEST
    // -----------------------------------------------------------------
    $upd = $conn->prepare("
        UPDATE account_deletion_requests
        SET status = 'cancelled'
        WHERE id = :rid
    ");
    $upd->execute([':rid' => $request_id]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Deletion request cancelled successfully.'
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>