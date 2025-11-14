<?php
// approvedAcc_requests.php
session_start();
require_once "config.php";
require_once "email_function.php";
include 'settings.php';

// Check if user is logged in as super admin or admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// === HANDLE DELETE ACTION ===
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $deleteUserId = intval($_GET['id']);
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

    try {
        $conn->beginTransaction();

        // Fetch user data
        $userStmt = $conn->prepare("
            SELECT u.*, CONCAT(pu.first_name, ' ', pu.last_name) AS primary_name
            FROM users u
            LEFT JOIN users pu ON u.primary_user_id = pu.id
            WHERE u.id = :id
        ");
        $userStmt->execute([':id' => $deleteUserId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) throw new Exception("User not found.");

        $userId = $user['id'];
        $userName = $user['first_name'] . ' ' . $user['last_name'];
        $userEmail = $user['email'];
        $isPrimary = !$user['primary_user_id'];
        $uploadDirs = [
            'profile_picture' => 'images/uploads/profile_pictures/',
            'valid_id_front'  => 'images/uploads/IDs/'
        ];

        $deletedUserIds = [$userId];
        $dependentCount = 0;

        // === 1. Delete Files (current user) ===
        foreach ($uploadDirs as $field => $dir) {
            if (!empty($user[$field])) {
                $filePath = $dir . basename($user[$field]);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }

        // === 2. Handle Dependents (only if Primary) ===
        if ($isPrimary) {
            $dependentsStmt = $conn->prepare("
                SELECT id, first_name, last_name, email, valid_id_front, profile_picture
                FROM users WHERE primary_user_id = :primary_id
            ");
            $dependentsStmt->execute([':primary_id' => $userId]);
            $dependents = $dependentsStmt->fetchAll(PDO::FETCH_ASSOC);
            $dependentCount = count($dependents);

            foreach ($dependents as $dep) {
                $depId = $dep['id'];
                $deletedUserIds[] = $depId;

                // Delete files
                foreach ($uploadDirs as $field => $dir) {
                    if (!empty($dep[$field])) {
                        $filePath = $dir . basename($dep[$field]);
                        if (file_exists($filePath)) unlink($filePath);
                    }
                }

                // Delete medicine requests
                $reqIdsStmt = $conn->prepare("SELECT id FROM medicine_requests WHERE user_id = :uid");
                $reqIdsStmt->execute([':uid' => $depId]);
                foreach ($reqIdsStmt->fetchAll(PDO::FETCH_COLUMN) as $reqId) {
                    $conn->prepare("DELETE FROM medicine_distributions WHERE request_id = ?")->execute([$reqId]);
                    $conn->prepare("DELETE FROM requested_medicines WHERE request_id = ?")->execute([$reqId]);
                }
                $conn->prepare("DELETE FROM medicine_requests WHERE user_id = ?")->execute([$depId]);

                // Delete tokens
                $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?")->execute([$depId]);

                // Delete user (cascade deletes relationship)
                $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$depId]);
            }
        }

        // === 3. Delete Main User Data ===
        $reqIdsStmt = $conn->prepare("SELECT id FROM medicine_requests WHERE user_id = :uid");
        $reqIdsStmt->execute([':uid' => $userId]);
        foreach ($reqIdsStmt->fetchAll(PDO::FETCH_COLUMN) as $reqId) {
            $conn->prepare("DELETE FROM medicine_distributions WHERE request_id = ?")->execute([$reqId]);
            $conn->prepare("DELETE FROM requested_medicines WHERE request_id = ?")->execute([$reqId]);
        }
        $conn->prepare("DELETE FROM medicine_requests WHERE user_id = ?")->execute([$userId]);
        $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?")->execute([$userId]);
        $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

        // === 4. DETERMINE DELETION TYPE & SEND CUSTOM EMAIL ===
        $subject = "MaruHealth Account Permanently Deleted";
        $logDetails = "";
        $emailMessage = "";

        if ($isPrimary && $dependentCount > 0) {
            // PRIMARY + DEPENDENTS
            $logDetails = "Deleted primary account: $userName and $dependentCount dependent(s)";
            $emailMessage = "
                <h2>Your Maru-Health Account Has Been Deleted</h2>
                <p>Dear $userName,</p>
                <p>Your account and <strong>all $dependentCount dependent account(s)</strong> have been <strong>permanently deleted</strong> by the administrator.</p>
                <p><strong>All associated data has been erased, including:</strong></p>
                <ul>
                    <li>Profile picture</li>
                    <li>Valid ID</li>
                    <li>All medicine requests and distribution records</li>
                    <li>Account access and login credentials</li>
                </ul>
                <p>This action is irreversible. If you believe this was a mistake, please contact the health center immediately.</p>
                <p>Best regards,<br><strong>MaruHealth Team</strong></p>
            ";
        } elseif ($isPrimary) {
            // PRIMARY ONLY (no dependents)
            $logDetails = "Deleted primary account: $userName";
            $emailMessage = "
                <h2>Your Maru-Health Account Has Been Deleted</h2>
                <p>Dear $userName,</p>
                <p>Your Account has been <strong>permanently deleted</strong> by the administrator.</p>
                <p><strong>All your data has been erased, including:</strong></p>
                <ul>
                    <li>Profile picture</li>
                    <li>Valid ID</li>
                    <li>All medicine requests and records</li>
                    <li>Login credentials</li>
                </ul>
                <p>This action is irreversible. Contact the health center if this was in error.</p>
                <p>Best regards,<br><strong>MaruHealth Team</strong></p>
            ";
        } else {
            // DEPENDENT ONLY
            $primaryName = $user['primary_name'] ?? 'Unknown Primary';
            $logDetails = "Deleted dependent account: $userName under primary account: $primaryName";
            $emailMessage = "
                <h2>Your Dependent Account Has Been Deleted</h2>
                <p>Dear $userName,</p>
                <p>Your <strong>dependent account</strong> under <strong>$primaryName</strong> has been <strong>permanently deleted</strong> by the administrator.</p>
                <p><strong>All your data has been erased, including:</strong></p>
                <ul>
                    <li>Profile picture</li>
                    <li>Valid ID</li>
                    <li>All medicine requests</li>
                    <li>Access to the platform</li>
                </ul>
                <p>The primary account remains active. Contact the health center if needed.</p>
                <p>Best regards,<br><strong>Maru-Health Team</strong></p>
            ";
        }

        // Send Email
        sendEmail($userEmail, $userName, $subject, $emailMessage);

        // === 5. Log Activity ===
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
            VALUES (:admin_id, 'delete_user', :details, :target_id)
        ");
        $logStmt->execute([
            ':admin_id' => $_SESSION['admin_id'],
            ':details' => $logDetails,
            ':target_id' => $userId
        ]);

        $conn->commit();
        $_SESSION['approval_message'] = "Account(s) deleted successfully.";
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['approval_message'] = "Error: " . $e->getMessage();
    }

    header("Location: approvedAcc_requests.php" . (!empty($searchTerm) ? "?search=" . urlencode($searchTerm) : ""));
    exit();
}

// === FETCH USERS (same as before) ===
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "
    SELECT 
        u.id, 
        CONCAT(u.first_name, ' ', u.last_name) AS full_name, 
        u.first_name, u.last_name, u.middle_name, u.gender, u.birthday, u.address, 
        u.email, u.phone_number, u.valid_id_front, u.role, u.family_number, u.primary_user_id,
        dr.relationship,
        CONCAT(pu.first_name, ' ', pu.last_name) AS primary_user_name
    FROM users u
    LEFT JOIN dependent_relationships dr ON u.id = dr.dependent_user_id
    LEFT JOIN users pu ON u.primary_user_id = pu.id
    WHERE u.role != 'admin'
";
if (!empty($searchTerm)) {
    $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.phone_number LIKE :search OR u.family_number LIKE :search)";
}
$query .= " ORDER BY u.date_registered DESC";

$approvedUsersStmt = $conn->prepare($query);
if (!empty($searchTerm)) {
    $searchParam = "%$searchTerm%";
    $approvedUsersStmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
}
$approvedUsersStmt->execute();
$approvedUsers = $approvedUsersStmt->fetchAll(PDO::FETCH_ASSOC);

// Admin info
$adminId = $_SESSION['admin_id'];
$adminStmt = $conn->prepare("SELECT * FROM admin_staff WHERE id = :id");
$adminStmt->bindParam(':id', $adminId);
$adminStmt->execute();
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
$adminName = $admin ? $admin['full_name'] : $_SESSION['admin_name'];
$adminRole = $admin ? $admin['role'] : $_SESSION['admin_role'];
$displayRole = ucwords(str_replace('_', ' ', $adminRole));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Accounts</title>
    <link rel="stylesheet" href="css/account_approval.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <style>
        .dependent-info {
            font-style: italic;
            color: #555;
            font-size: 0.9em;
        }
        .message {
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            transition: opacity 0.5s ease-in-out;
        }
        .message.success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .message.error {
            background-color: #f2dede;
            color: #a94442;
        }
        .delete-btn {
            background: #dc3545; color: white; padding: 6px 10px; border-radius: 4px; font-size: 0.9em;
            text-decoration: none; margin-left: 8px;
        }
        .delete-btn:hover { background: #c82333; }
    </style>
</head>
<body>
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
                <p class="admin_name"><strong><?= htmlspecialchars($adminName) ?></strong></p>
                <p class="role"><?= htmlspecialchars($displayRole) ?></p>
            </div>
        </div>
        <div class="menu">
            <?php 
                $current_page = basename($_SERVER['PHP_SELF']); 
                $dashboard_url = $adminRole === 'super_admin' ? 'superadmin_dashboard.php' : 'admin_dashboard.php';
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
                <a href="account_approval.php" class="<?= in_array($current_page, ['account_approval.php', 'approvedAcc_requests.php', 'account_requests.php']) ? 'active' : '' ?>">User Account Management</a>
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
            <p class="menu-header">OTHERS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/logout_icon.png" alt="">
                <a href="logout.php" class="logout-button">Log Out</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="approval-content">
        <div class="title-con">
            <a href="account_approval.php" class="back-button">← Back</a>
            <h2>Approved Accounts</h2>
        </div>
        <?php if (isset($_SESSION['approval_message'])): ?>
            <div class="message <?= strpos($_SESSION['approval_message'], 'Error') === false ? 'success' : 'error' ?>">
                <?= htmlspecialchars($_SESSION['approval_message']) ?>
            </div>
            <?php unset($_SESSION['approval_message']); ?>
        <?php endif; ?>
        <div class="approval-container">
            <div class="sort-control">
                <div class="search-con">
                    <form method="GET" action="approvedAcc_requests.php">
                        <input type="text" name="search" placeholder="Search by Name, Email, Phone, or Family Number" value="<?= htmlspecialchars($searchTerm) ?>">
                        <button type="submit">Search</button>
                    </form>
                </div> 
                <a href="deletion_requests.php" class="add-dependent-btn" style="margin-left:12px;">
                    Deletion Requests
                </a>
            </div>
            <div class="account-table">
                <div class="table-container">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone Number</th>
                                    <th>Family Number</th>
                                    <th>Account Type</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($approvedUsers)): ?>
                                    <tr><td colspan="6" style="text-align:center;">No approved users found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($approvedUsers as $user): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($user['full_name']) ?>
                                                <?php if ($user['primary_user_id']): ?>
                                                    <div class="dependent-info">
                                                        Dependent of: <?= htmlspecialchars($user['primary_user_name'] ?? 'Unknown') ?><br>
                                                        Relationship: <?= htmlspecialchars($user['relationship'] ?? 'Not specified') ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= htmlspecialchars($user['phone_number']) ?></td>
                                            <td><?= htmlspecialchars($user['family_number'] ?? 'Not provided') ?></td>
                                            <td><?= $user['primary_user_id'] ? 'Dependent' : 'Primary' ?></td>
                                            <td>
                                                <a href="#" class="view-btn"
                                                   data-id="<?= $user['id'] ?>"
                                                   data-firstname="<?= htmlspecialchars($user['first_name']) ?>"
                                                   data-lastname="<?= htmlspecialchars($user['last_name']) ?>"
                                                   data-middlename="<?= htmlspecialchars($user['middle_name']) ?>"
                                                   data-email="<?= htmlspecialchars($user['email']) ?>"
                                                   data-phone="<?= htmlspecialchars($user['phone_number']) ?>"
                                                   data-address="<?= htmlspecialchars($user['address']) ?>"
                                                   data-gender="<?= htmlspecialchars($user['gender']) ?>"
                                                   data-birthday="<?= htmlspecialchars($user['birthday']) ?>"
                                                   data-idfront="<?= htmlspecialchars($user['valid_id_front']) ?>"
                                                   data-familynumber="<?= htmlspecialchars($user['family_number'] ?? 'Not provided') ?>"
                                                   data-primaryname="<?= htmlspecialchars($user['primary_user_name'] ?? 'N/A') ?>"
                                                   data-relationship="<?= htmlspecialchars($user['relationship'] ?? 'N/A') ?>"
                                                >View</a>

                                                <a href="approvedAcc_requests.php?action=delete&id=<?= $user['id'] . (!empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '') ?>"
                                                   class="delete-btn"
                                                   onclick="return confirm('DELETE USER?\n\nThis will permanently delete:\n• This account\n• All files (ID, profile pic)\n• Medicine requests\n<?php if (!$user['primary_user_id']): ?>• ALL DEPENDENTS<?php endif; ?>\n\nThis cannot be undone.')">
                                                   Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Structure -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <h2>Account Details</h2>
            <div class="modal-scroll">
                <div class="content-container">
                    <div class="user-info">
                        <div class="info-group">
                            <label>Last Name</label>
                            <span id="lastName"></span>
                        </div>
                        <div class="info-group">
                            <label>First Name</label>
                            <span id="firstName"></span>
                        </div>
                        <div class="info-group">
                            <label>Middle Name</label>
                            <span id="middleName"></span>
                        </div>
                        <div class="info-row">
                            <div class="info-group">
                                <label>Gender</label>
                                <span id="gender"></span>
                            </div>
                            <div class="info-group">
                                <label>Date of Birth</label>
                                <span id="birthday"></span>
                            </div>
                        </div>
                        <div class="info-group">
                            <label>Address</label>
                            <span id="address"></span>
                        </div>
                        <div class="info-group">
                            <label>Family Number</label>
                            <span id="familyNumber"></span>
                        </div>
                        <div class="info-group">
                            <label>E-mail Address</label>
                            <span id="email"></span>
                        </div>
                        <div class="info-group">
                            <label>Phone Number</label>
                            <span id="phone"></span>
                        </div>
                        <div class="info-group">
                            <label>Account Type</label>
                            <span id="accountType"></span>
                        </div>
                        <div class="info-group" id="dependentInfo" style="display: none;">
                            <label>Primary User</label>
                            <span id="primaryName"></span>
                        </div>
                        <div class="info-group" id="relationshipInfo" style="display: none;">
                            <label>Relationship</label>
                            <span id="relationship"></span>
                        </div>
                    </div>
                    <div class="id-preview">
                        <label>Uploaded Valid ID</label>
                        <img id="idFront" src="" alt="Valid ID Front">
                    </div>                              
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="close-btn" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const modal = document.getElementById("viewModal");
            const viewButtons = document.querySelectorAll(".view-btn");

            viewButtons.forEach(button => {
                button.addEventListener("click", function (event) {
                    event.preventDefault();
                    // Populate fields
                    document.getElementById("lastName").textContent = this.dataset.lastname;
                    document.getElementById("firstName").textContent = this.dataset.firstname;
                    document.getElementById("middleName").textContent = this.dataset.middlename;
                    document.getElementById("gender").textContent = this.dataset.gender;
                    document.getElementById("birthday").textContent = this.dataset.birthday;
                    document.getElementById("address").textContent = this.dataset.address;
                    document.getElementById("email").textContent = this.dataset.email;
                    document.getElementById("phone").textContent = this.dataset.phone;
                    document.getElementById("familyNumber").textContent = this.dataset.familynumber;
                    document.getElementById("idFront").src = this.dataset.idfront;

                    // Account Type
                    const isDependent = this.dataset.primaryname !== 'N/A';
                    document.getElementById("accountType").textContent = isDependent ? 'Dependent' : 'Primary';

                    // Show/hide dependent info
                    document.getElementById("dependentInfo").style.display = isDependent ? 'flex' : 'none';
                    document.getElementById("relationshipInfo").style.display = isDependent ? 'flex' : 'none';
                    document.getElementById("primaryName").textContent = this.dataset.primaryname;
                    document.getElementById("relationship").textContent = this.dataset.relationship;

                    modal.style.display = "flex";
                });
            });
        });

        function closeModal() {
            document.getElementById("viewModal").style.display = "none";
        }

        // Close on outside click
        window.addEventListener("click", function (event) {
            const modal = document.getElementById("viewModal");
            if (event.target === modal) closeModal();
        });

        // Auto-disappear message after 3 seconds
        document.addEventListener("DOMContentLoaded", function () {
            const message = document.querySelector(".message");
            if (message) {
                setTimeout(() => {
                    message.style.opacity = "0";
                    setTimeout(() => {
                        message.style.display = "none";
                    }, 500); // Wait for fade-out transition to complete
                }, 3000); // 3 seconds
            }
        });
    </script>
</body>
</html>