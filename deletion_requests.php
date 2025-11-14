<?php
// deletion_requests.php
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
// 2. HANDLE APPROVE / REJECT (GET)
// ---------------------------------------------------------------------
if (isset($_GET['action']) && isset($_GET['id'])) {
    $reqId     = (int)$_GET['id'];
    $searchTerm = $_GET['search'] ?? '';

    // ----- FETCH REQUEST -----
    $reqStmt = $conn->prepare("
        SELECT dr.*, u.email, u.first_name, u.last_name,
               pu.first_name AS pu_fn, pu.last_name AS pu_ln
        FROM account_deletion_requests dr
        JOIN users u ON dr.user_id = u.id
        LEFT JOIN users pu ON dr.primary_user_id = pu.id
        WHERE dr.id = :id AND dr.status = 'pending'
    ");
    $reqStmt->execute([':id' => $reqId]);
    $request = $reqStmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $_SESSION['request_message'] = "Request not found or already processed.";
        header("Location: deletion_requests.php" . (!empty($searchTerm) ? "?search=" . urlencode($searchTerm) : ""));
        exit();
    }

    $targetUserId = $request['user_id'];
    $primaryId    = $request['primary_user_id'] ?? $targetUserId;
    $isPrimary    = ($targetUserId == $primaryId);

    try {
        $conn->beginTransaction();

        // -------------------------------------------------------------
        // APPROVE → DELETE (same logic as approvedAcc_requests.php)
        // -------------------------------------------------------------
        if ($_GET['action'] === 'approve') {
            // 1. FETCH USER + FILES
            $userStmt = $conn->prepare("
                SELECT u.*, CONCAT(pu.first_name,' ',pu.last_name) AS primary_name
                FROM users u
                LEFT JOIN users pu ON u.primary_user_id = pu.id
                WHERE u.id = :id
            ");
            $userStmt->execute([':id' => $targetUserId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) throw new Exception("User not found.");

            $uploadDirs = [
                'profile_picture' => 'images/uploads/profile_pictures/',
                'valid_id_front'  => 'images/uploads/IDs/'
            ];
            $deletedUserIds = [$targetUserId];
            $dependentCount = 0;

            // ---- delete files (current user) ----
            foreach ($uploadDirs as $field => $dir) {
                if (!empty($user[$field])) {
                    $path = $dir . basename($user[$field]);
                    if (file_exists($path)) unlink($path);
                }
            }

            // ---- dependents (only primary) ----
            if ($isPrimary) {
                $depStmt = $conn->prepare("
                    SELECT id, first_name, last_name, email, valid_id_front, profile_picture
                    FROM users WHERE primary_user_id = :pid
                ");
                $depStmt->execute([':pid' => $targetUserId]);
                $dependents = $depStmt->fetchAll(PDO::FETCH_ASSOC);
                $dependentCount = count($dependents);

                foreach ($dependents as $dep) {
                    $depId = $dep['id'];
                    $deletedUserIds[] = $depId;

                    foreach ($uploadDirs as $field => $dir) {
                        if (!empty($dep[$field])) {
                            $path = $dir . basename($dep[$field]);
                            if (file_exists($path)) unlink($path);
                        }
                    }

                    // delete medicine requests
                    $reqIds = $conn->prepare("SELECT id FROM medicine_requests WHERE user_id = ?");
                    $reqIds->execute([$depId]);
                    foreach ($reqIds->fetchAll(PDO::FETCH_COLUMN) as $rId) {
                        $conn->prepare("DELETE FROM medicine_distributions WHERE request_id = ?")->execute([$rId]);
                        $conn->prepare("DELETE FROM requested_medicines WHERE request_id = ?")->execute([$rId]);
                    }
                    $conn->prepare("DELETE FROM medicine_requests WHERE user_id = ?")->execute([$depId]);

                    $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?")->execute([$depId]);
                    $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$depId]);
                }
            }

            // ---- main user ----
            $reqIds = $conn->prepare("SELECT id FROM medicine_requests WHERE user_id = ?");
            $reqIds->execute([$targetUserId]);
            foreach ($reqIds->fetchAll(PDO::FETCH_COLUMN) as $rId) {
                $conn->prepare("DELETE FROM medicine_distributions WHERE request_id = ?")->execute([$rId]);
                $conn->prepare("DELETE FROM requested_medicines WHERE request_id = ?")->execute([$rId]);
            }
            $conn->prepare("DELETE FROM medicine_requests WHERE user_id = ?")->execute([$targetUserId]);
            $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?")->execute([$targetUserId]);
            $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$targetUserId]);

            // ---- EMAIL ----
            $subject = "MaruHealth Account Permanently Deleted";
            if ($isPrimary && $dependentCount) {
                $msg = "<h2>Your Maru-Health Account Has Been Deleted</h2>
                        <p>Dear {$user['first_name']} {$user['last_name']},</p>
                        <p>Your account **and all $dependentCount dependent account(s)** have been permanently deleted.</p>
                        <p>All associated data (profile picture, ID, medicine requests…) has been erased.</p>
                        <p>This action is irreversible.</p>
                        <p>Best regards,<br><strong>MaruHealth Team</strong></p>";
            } elseif ($isPrimary) {
                $msg = "<h2>Your Maru-Health Account Has Been Deleted</h2>
                        <p>Dear {$user['first_name']} {$user['last_name']},</p>
                        <p>Your account has been permanently deleted.</p>
                        <p>All data has been erased.</p>
                        <p>Best regards,<br><strong>MaruHealth Team</strong></p>";
            } else {
                $primaryName = $request['pu_fn'] . ' ' . $request['pu_ln'];
                $msg = "<h2>Your Dependent Account Has Been Deleted</h2>
                        <p>Dear {$user['first_name']} {$user['last_name']},</p>
                        <p>Your dependent account under **$primaryName** has been permanently deleted.</p>
                        <p>All your data has been erased.</p>
                        <p>The primary account remains active.</p>
                        <p>Best regards,<br><strong>MaruHealth Team</strong></p>";
            }
            sendEmail($request['email'], $user['first_name'] . ' ' . $user['last_name'], $subject, $msg);

            // ---- LOG ----
            $logDetails = $isPrimary
                ? ($dependentCount ? "Deleted primary {$user['first_name']} {$user['last_name']} and $dependentCount dependent(s)" : "Deleted primary {$user['first_name']} {$user['last_name']}")
                : "Deleted dependent {$user['first_name']} {$user['last_name']} under primary {$request['pu_fn']} {$request['pu_ln']}";
            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
                VALUES (:aid, 'delete_user', :det, :tid)
            ");
            $logStmt->execute([
                ':aid' => $adminId,
                ':det' => $logDetails,
                ':tid' => $targetUserId
            ]);

            // ---- UPDATE REQUEST STATUS ----
            $conn->prepare("UPDATE account_deletion_requests SET status = 'deleted' WHERE id = ?")
                 ->execute([$reqId]);

            $_SESSION['request_message'] = "Account(s) deleted successfully.";
        }

        // -------------------------------------------------------------
        // REJECT → CANCEL
        // -------------------------------------------------------------
        if ($_GET['action'] === 'reject') {
            $conn->prepare("UPDATE account_deletion_requests SET status = 'cancelled' WHERE id = ?")
                 ->execute([$reqId]);

            $subject = "Account Deletion Request Rejected";
            $msg = "<h2>Deletion Request Rejected</h2>
                    <p>Dear {$request['first_name']} {$request['last_name']},</p>
                    <p>Your request to delete the account has been **rejected**.</p>
                    <p>Reason (admin): <em>None provided</em></p>
                    <p>Your account remains active.</p>
                    <p>Best regards,<br><strong>MaruHealth Team</strong></p>";
            sendEmail($request['email'], $request['first_name'] . ' ' . $request['last_name'], $subject, $msg);

            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
                VALUES (:aid, 'user_rejection', :det, :tid)
            ");
            $logStmt->execute([
                ':aid' => $adminId,
                ':det' => "Rejected deletion request for user ID $targetUserId",
                ':tid' => $targetUserId
            ]);

            $_SESSION['request_message'] = "Deletion request rejected.";
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['request_message'] = "Error: " . $e->getMessage();
    }

    header("Location: deletion_requests.php" . (!empty($searchTerm) ? "?search=" . urlencode($searchTerm) : ""));
    exit();
}

// ---------------------------------------------------------------------
// 3. FETCH PENDING REQUESTS (with optional search)
// ---------------------------------------------------------------------
$searchTerm = $_GET['search'] ?? '';
$where = "WHERE dr.status = 'pending'";
$params = [];

if ($searchTerm !== '') {
    $where .= " AND (u.first_name LIKE :s OR u.last_name LIKE :s OR u.email LIKE :s OR u.phone_number LIKE :s)";
    $params[':s'] = "%$searchTerm%";
}

$sql = "
    SELECT dr.id, dr.user_id, dr.reason, dr.requested_at,
           u.first_name, u.last_name, u.email,
           pu.first_name AS pu_fn, pu.last_name AS pu_ln,
           (dr.user_id = dr.primary_user_id) AS is_primary
    FROM account_deletion_requests dr
    JOIN users u ON dr.user_id = u.id
    LEFT JOIN users pu ON dr.primary_user_id = pu.id
    $where
    ORDER BY dr.requested_at DESC
";
$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deletion Requests</title>
    <link rel="stylesheet" href="css/account_approval.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
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
                                                <a href="deletion_requests.php?action=approve&id=<?=$r['id']?>&search=<?=urlencode($searchTerm)?>"
                                                   class="approve-btn"
                                                   onclick="return confirm('APPROVE DELETION?\n\nThis will PERMANENTLY delete the account<?=$r['is_primary']?' and ALL dependents':''?>.');">
                                                   Approve
                                                </a>
                                                <a href="deletion_requests.php?action=reject&id=<?=$r['id']?>&search=<?=urlencode($searchTerm)?>"
                                                   class="reject-btn"
                                                   onclick="return confirm('REJECT deletion request?');">
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