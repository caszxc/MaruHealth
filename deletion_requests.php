<?php
session_start();
require_once "config.php";
require_once "email_function.php";
include 'settings.php';

// ---------------------------------------------------------------------
// 1. AUTH & ADMIN INFO
// ---------------------------------------------------------------------
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin'])) {
    header("Location: admin_dashboard.php");
    exit();
}

$adminId   = $_SESSION['admin_id'];
$adminStmt = $conn->prepare("SELECT * FROM admin_staff WHERE id = :id");
$adminStmt->execute([':id' => $adminId]);
$admin     = $adminStmt->fetch(PDO::FETCH_ASSOC);
$adminName = $admin['full_name'] ?? $_SESSION['admin_name'];
$adminRole = $admin['role'] ?? $_SESSION['admin_role'];
$displayRole = ucwords(str_replace('_', ' ', $adminRole));

// ---------------------------------------------------------------------
// 2. HANDLE APPROVE / REJECT ACTIONS
// ---------------------------------------------------------------------
$searchTerm = $_GET['search'] ?? '';

if (isset($_GET['action']) && isset($_GET['id'])) {
    $requestId = (int)$_GET['id'];

    try {
        $conn->beginTransaction();

        // Fetch deletion request + user info
        $reqStmt = $conn->prepare("
            SELECT adr.*, 
                   u.id AS user_id, u.first_name, u.last_name, u.email,
                   u.primary_user_id, u.profile_picture, u.valid_id_front,
                   pu.first_name AS pu_fn, pu.last_name AS pu_ln
            FROM account_deletion_requests adr
            JOIN users u ON adr.user_id = u.id
            LEFT JOIN users pu ON u.primary_user_id = pu.id
            WHERE adr.id = :rid AND adr.status = 'pending'
        ");
        $reqStmt->execute([':rid' => $requestId]);
        $request = $reqStmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            throw new Exception("Deletion request not found or already processed.");
        }

        $userId       = $request['user_id'];
        $userName     = $request['first_name'] . ' ' . $request['last_name'];
        $userEmail    = $request['email'];
        $isPrimary    = is_null($request['primary_user_id']);
        $primaryName  = $request['pu_fn'] && $request['pu_ln'] ? $request['pu_fn'] . ' ' . $request['pu_ln'] : null;

        $uploadDirs = [
            'profile_picture' => 'images/uploads/profile_pictures/',
            'valid_id_front'  => 'images/uploads/IDs/'
        ];

        $deletedUserIds = [$userId];
        $dependentCount = 0;

        // Helper: handle medicine requests (same as in approvedAcc_requests.php)
        function handleUserMedicineRequests($conn, $uid, $adminId) {
            $reqStmt = $conn->prepare("SELECT id, request_status FROM medicine_requests WHERE user_id = ?");
            $reqStmt->execute([$uid]);
            $requests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($requests as $mr) {
                $reqId = $mr['id'];
                $status = $mr['request_status'];

                if ($status === 'pending') {
                    $conn->prepare("UPDATE medicine_requests SET request_status = 'declined', declined_date = NOW() WHERE id = ?")->execute([$reqId]);
                    $conn->prepare("UPDATE requested_medicines SET status = 'declined' WHERE request_id = ?")->execute([$reqId]);

                } elseif ($status === 'to be claimed') {
                    $conn->prepare("UPDATE medicine_requests SET request_status = 'cancelled', cancelled_date = NOW() WHERE id = ?")->execute([$reqId]);
                    $conn->prepare("UPDATE requested_medicines SET status = 'cancelled' WHERE request_id = ?")->execute([$reqId]);

                    // Return reserved medicines
                    $distStmt = $conn->prepare("
                        SELECT batch_id, quantity FROM medicine_distributions 
                        WHERE request_id = ? AND status = 'reserved'
                    ");
                    $distStmt->execute([$reqId]);
                    $distributions = $distStmt->fetchAll();

                    $returnStmt = $conn->prepare("
                        UPDATE medicine_batches 
                        SET stocks = stocks + ?, 
                            stock_status = CASE WHEN stocks + ? > 0 THEN 'In Stock' ELSE 'Out of Stock' END
                        WHERE id = ?
                    ");
                    $historyStmt = $conn->prepare("
                        INSERT INTO medicine_history (catalog_id, batch_id, action_type, details, performed_by)
                        SELECT catalog_id, id, 'return', ?, ?
                        FROM medicine_batches WHERE id = ?
                    ");

                    foreach ($distributions as $dist) {
                        $returnStmt->execute([$dist['quantity'], $dist['quantity'], $dist['batch_id']]);
                        $details = "Returned due to approved account deletion (request #$reqId)";
                        $historyStmt->execute([$details, $adminId, $dist['batch_id']]);
                    }

                    $conn->prepare("UPDATE medicine_distributions SET status = 'returned' WHERE request_id = ? AND status = 'reserved'")->execute([$reqId]);
                }
            }
        }

        if ($_GET['action'] === 'approve') {
            // === DELETE DEPENDENTS FIRST (if primary) ===
            if ($isPrimary) {
                $depStmt = $conn->prepare("
                    SELECT id, first_name, last_name, email, profile_picture, valid_id_front
                    FROM users WHERE primary_user_id = ?
                ");
                $depStmt->execute([$userId]);
                $dependents = $depStmt->fetchAll(PDO::FETCH_ASSOC);
                $dependentCount = count($dependents);

                foreach ($dependents as $dep) {
                    $depId = $dep['id'];
                    $deletedUserIds[] = $depId;

                    // Delete files
                    foreach ($uploadDirs as $field => $dir) {
                        if (!empty($dep[$field])) {
                            $path = $dir . basename($dep[$field]);
                            if (file_exists($path)) unlink($path);
                        }
                    }

                    handleUserMedicineRequests($conn, $depId, $adminId);
                    $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?")->execute([$depId]);
                    $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$depId]);
                }
            }

            // === DELETE MAIN USER FILES & REQUESTS ===
            foreach ($uploadDirs as $field => $dir) {
                if (!empty($request[$field])) {
                    $path = $dir . basename($request[$field]);
                    if (file_exists($path)) unlink($path);
                }
            }

            handleUserMedicineRequests($conn, $userId, $adminId);
            $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?")->execute([$userId]);
            $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

            // === UPDATE DELETION REQUEST STATUS ===
            $conn->prepare("UPDATE account_deletion_requests SET status = 'deleted' WHERE id = ?")->execute([$requestId]);

            // === SEND EMAIL ===
            $subject = "MaruHealth Account Deletion Approved";
            if ($isPrimary && $dependentCount > 0) {
                $msg = "<h2>Your Account Has Been Deleted</h2>
                        <p>Dear $userName,</p>
                        <p>Your deletion request has been <strong>approved</strong>. Your primary account and all $dependentCount dependent account(s) have been permanently deleted.</p>
                        <p>All pending medicine requests were declined and reserved items returned to inventory.</p>
                        <p>Thank you for using MaruHealth.</p>
                        <p>Best regards,<br><strong>MaruHealth Team</strong></p>";
            } elseif ($isPrimary) {
                $msg = "<h2>Your Account Has Been Deleted</h2>
                        <p>Dear $userName,</p>
                        <p>Your deletion request has been approved and your account is now permanently deleted.</p>
                        <p>Best regards,<br><strong>MaruHealth Team</strong></p>";
            } else {
                $msg = "<h2>Your Dependent Account Has Been Deleted</h2>
                        <p>Dear $userName,</p>
                        <p>Your deletion request has been approved. Your dependent account under <strong>$primaryName</strong> has been permanently removed.</p>
                        <p>The primary account remains active.</p>
                        <p>Best regards,<br><strong>MaruHealth Team</strong></p>";
            }
            sendEmail($userEmail, $userName, $subject, $msg);

            // === LOG ACTIVITY ===
            $logDetails = $isPrimary
                ? ($dependentCount ? "Approved deletion: $userName + $dependentCount dependents" : "Approved deletion: $userName")
                : "Approved deletion: dependent $userName (under $primaryName)";

            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
                VALUES (?, 'delete_user', ?, ?)
            ");
            $logStmt->execute([$adminId, $logDetails, $userId]);

            $_SESSION['request_message'] = "Account deletion approved and completed successfully.";
        }

        elseif ($_GET['action'] === 'reject') {
            $conn->prepare("UPDATE account_deletion_requests SET status = 'cancelled' WHERE id = ?")->execute([$requestId]);

            $subject = "Account Deletion Request Rejected";
            $msg = "<h2>Deletion Request Rejected</h2>
                    <p>Dear $userName,</p>
                    <p>Your request to delete your MaruHealth account has been <strong>rejected</strong> by the administrator.</p>
                    <p>Your account remains active.</p>
                    <p>If you have concerns, please contact the health center.</p>
                    <p>Best regards,<br><strong>MaruHealth Team</strong></p>";
            sendEmail($userEmail, $userName, $subject, $msg);

            $logDetails = "Rejected deletion request for $userName";
            $conn->prepare("INSERT INTO activity_logs (admin_id, action_type, action_details, target_id) VALUES (?, 'delete_user', ?, ?)")->execute([$adminId, $logDetails, $userId]);

            $_SESSION['request_message'] = "Deletion request rejected.";
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['request_message'] = "Error: " . $e->getMessage();
    }

    header("Location: deletion_requests.php" . ($searchTerm ? "?search=" . urlencode($searchTerm) : ""));
    exit();
}

// ---------------------------------------------------------------------
// 3. FETCH PENDING DELETION REQUESTS
// ---------------------------------------------------------------------
$searchTerm = $_GET['search'] ?? '';
$query = "
    SELECT adr.id, adr.user_id, adr.reason, adr.requested_at,
           u.first_name, u.last_name, u.primary_user_id,
           pu.first_name AS pu_fn, pu.last_name AS pu_ln,
           (u.primary_user_id IS NULL) AS is_primary
    FROM account_deletion_requests adr
    JOIN users u ON adr.user_id = u.id
    LEFT JOIN users pu ON u.primary_user_id = pu.id
    WHERE adr.status = 'pending'
";

if ($searchTerm !== '') {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search)";
}

$query .= " ORDER BY adr.requested_at DESC";

$stmt = $conn->prepare($query);
if ($searchTerm !== '') {
    $param = "%$searchTerm%";
    $stmt->bindParam(':search', $param);
}
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Account Management</title>
    <link rel="stylesheet" href="css/account_approval.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= $logo_url ?>" type="image/x-icon">
    <style>
        .message{padding:10px;border-radius:5px;text-align:center;transition:opacity .5s;}
        .message.success{background:#dff0d8;color:#3c763d;}
        .message.error{background:#f2dede;color:#a94442;}
        .approve-btn{background:#28a745;color:#fff;padding:6px 10px;border-radius:4px;text-decoration:none;margin-right:5px;}
        .approve-btn:hover{background:#218838;}
        .reject-btn{background:#dc3545;color:#fff;padding:6px 10px;border-radius:4px;text-decoration:none;}
        .reject-btn:hover{background:#c82333;}
        .dependent-info{font-style:italic;color:#555;font-size:.9em;}
    </style>
</head>
<body>
    <!-- ====================== NAV & SIDEBAR (same as approvedAcc_requests.php) ====================== -->
    <nav>
        <div class="logo-container">
            <img src="<?= $logo_url ?>" alt="Logo">
            <div>
                <h1>
                    <span class="maruhealth"><?= htmlspecialchars($site_name) ?></span>
                    <span class="barangay-title">Barangay Marulas 3S Health Center</span>
                </h1>
            </div>
        </div>
    </nav>

    <div class="sidebar">
        <div class="profile">
            <img src="images/profile-placeholder.png" alt="Admin">
            <div class="profile-details">
                <p class="admin_name"><strong><?=htmlspecialchars($adminName)?></strong></p>
                <p class="role"><?=htmlspecialchars($displayRole)?></p>
            </div>
        </div>
        <div class="menu">
            <?php 
                $current_page = basename($_SERVER['PHP_SELF']); 
                $dashboard_url = '';
                if ($adminRole === 'super_admin') {
                    $dashboard_url = 'superadmin_dashboard.php';
                } elseif ($adminRole === 'admin') {
                    $dashboard_url = 'admin_dashboard.php';
                } elseif ($adminRole === 'health_staff') {
                    $dashboard_url = 'healthstaff_dashboard.php';
                }
            ?>
            <p class="menu-header">ANALYTICS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/dashboard_icon.png" alt="">
                <a href="<?= htmlspecialchars($dashboard_url) ?>" class="<?= $current_page == $dashboard_url ? 'active' : '' ?>">Dashboard</a>
            </div>
            
            <p class="menu-header">BASE</p>
            <?php if ($adminRole == 'super_admin'): ?>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/admin_icon.png" alt="">
                <a href="manage_staff.php" class="<?= $current_page == 'manage_staff.php' ? 'active' : '' ?>">Admin Account Management</a>
            </div>
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/account_approval_icon_active.png" alt="">
                <a href="account_requests.php" class="<?=in_array($current_page,['account_approval.php','approvedAcc_requests.php','deletion_requests.php'])?'active':''?>">User Account Management</a>
            </div>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/settings_icon.png" alt="">
                <a href="system_settings.php" class="<?= $current_page == 'system_settings.php' ? 'active' : '' ?>">
                    System Settings
                </a>
            </div>
            <?php endif; ?>
            <?php if ($adminRole == 'admin'): ?>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/announcement_icon.png" alt="">
                <a href="announcements.php" class="<?= $current_page == 'announcements.php' ? 'active' : '' ?>">Announcement</a>
            </div>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/calendar_icon.png" alt="">
                <a href="edit_calendar.php" class="<?= $current_page == 'edit_calendar.php' ? 'active' : '' ?>">Calendar</a>
            </div>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/service_icon.png" alt="">
                <a href="service_management.php" class="<?= $current_page == 'service_management.php' ? 'active' : '' ?>">Service Management</a>
            </div>
            <?php endif; ?>
            <?php if ($adminRole == 'health_staff'): ?>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/patient_icon.png" alt="">
                <a href="patient_management.php" class="<?= $current_page == 'patient_management.php' ? 'active' : '' ?>">Patient Management</a>
            </div>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/med_icon.png" alt="">
                <a href="medicine_management.php" class="<?= $current_page == 'medicine_management.php' ? 'active' : '' ?>">Medicine Management</a>
            </div>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/reqmd_icon.png" alt="">
                <a href="medicine_requests.php" class="<?= $current_page == 'medicine_requests.php' ? 'active' : '' ?>">Medicine Requests</a>
            </div>
            <?php endif; ?>
            <p class="menu-header">OTHERS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/logout_icon.png" alt="">
                <a href="logout.php" class="logout-button">Log Out</a>
            </div>
        </div>
    </div>

    <!-- ====================== MAIN CONTENT ====================== -->
    <div class="approval-content">
        <div class="title-con">
            <a href="approvedAcc_requests.php" class="back-button">Back</a>
            <h2>Account Deletion Requests</h2>
        </div>

        <?php if(isset($_SESSION['request_message'])):?>
            <div class="message <?=strpos($_SESSION['request_message'],'Error')===false?'success':'error'?>">
                <?=htmlspecialchars($_SESSION['request_message'])?>
            </div>
            <?php unset($_SESSION['request_message']);?>
        <?php endif;?>

        <div class="approval-container">
            <div class="sort-control">
                <div class="search-con">
                    <form method="GET">
                        <input type="text" name="search" placeholder="Search by Name, Email, Phone…" value="<?=htmlspecialchars($searchTerm)?>">
                        <button type="submit">Search</button>
                    </form>
                </div>
            </div>

            <div class="account-table">
                <div class="table-container">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Requester</th>
                                    <th>Reason</th>
                                    <th>Requested At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($requests)):?>
                                    <tr><td colspan="4" style="text-align:center;">No pending deletion requests.</td></tr>
                                <?php else:?>
                                    <?php foreach($requests as $r):?>
                                        <tr>
                                            <td>
                                                <?=htmlspecialchars($r['first_name'].' '.$r['last_name'])?>
                                                <?php if(!$r['is_primary']):?>
                                                    <div class="dependent-info">
                                                        Dependent of: <?=htmlspecialchars($r['pu_fn'].' '.$r['pu_ln'])?>
                                                    </div>
                                                <?php endif;?>
                                            </td>
                                            <td><?=htmlspecialchars($r['reason'])?></td>
                                            <td><?=date('M j, Y g:i A', strtotime($r['requested_at']))?></td>
                                            <td>
                                                <a href="deletion_requests.php?action=approve&id=<?= $r['id'] ?>&search=<?= urlencode($searchTerm) ?>"
                                                    class="approve-btn"
                                                    onclick="return confirm('APPROVE DELETION?\n\nThis will permanently delete the account<?= $r['is_primary'] ? ' and ALL dependents' : '' ?>.\n\nAll files and data will be removed.\nThis action is IRREVERSIBLE.');">
                                                        Approve
                                                </a>
                                                <a href="deletion_requests.php?action=reject&id=<?= $r['id'] ?>&search=<?= urlencode($searchTerm) ?>"
                                                    class="reject-btn"
                                                    onclick="return confirm('Reject this deletion request?');">
                                                        Reject
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach;?>
                                <?php endif;?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================== AUTO-FADE MESSAGE ====================== -->
    <script>
        document.addEventListener("DOMContentLoaded",function(){
            const msg=document.querySelector(".message");
            if(msg){
                setTimeout(()=>{msg.style.opacity="0";setTimeout(()=>{msg.style.display="none";},500);},3000);
            }
        });
    </script>
</body>
</html>