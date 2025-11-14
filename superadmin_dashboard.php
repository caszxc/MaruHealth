<?php
// superadmin_dashboard.php
session_start();
require_once "config.php";
include 'settings.php';

// Check if user is logged in and is super admin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'super_admin') {
    header("Location: login.php");
    exit();
}

// Fetch admin info
$adminId = $_SESSION['admin_id'];
$adminStmt = $conn->prepare("SELECT * FROM admin_staff WHERE id = :id");
$adminStmt->bindParam(':id', $adminId);
$adminStmt->execute();
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
$adminName = $admin ? $admin['full_name'] : $_SESSION['admin_name'];
$adminRole = $admin ? $admin['role'] : $_SESSION['admin_role'];
$displayRole = ucwords(str_replace('_', ' ', $adminRole));

// Fetch pending accounts count
$pendingStmt = $conn->prepare("SELECT COUNT(*) FROM pending_users");
$pendingStmt->execute();
$pendingCount = $pendingStmt->fetchColumn();

$totalActiveUsersStmt = $conn->query("SELECT COUNT(*) FROM users");
$totalActiveUsers    = $totalActiveUsersStmt->fetchColumn();

$totalAdminsStmt = $conn->query("SELECT COUNT(*) FROM admin_staff");
$totalAdmins    = $totalAdminsStmt->fetchColumn();

$activityStmt = $conn->prepare("
    SELECT al.*, a.full_name AS admin_name
    FROM activity_logs al
    LEFT JOIN admin_staff a ON al.admin_id = a.id
    ORDER BY al.created_at DESC
    LIMIT 8
");
$activityStmt->execute();
$activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper: Human-readable time ago
function timeAgo($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $interval = $now->diff($past);

    if ($interval->d == 0 && $interval->m == 0 && $interval->y == 0) {
        if ($interval->h > 0) return $interval->h . " hour" . ($interval->h > 1 ? "s" : "") . " ago";
        if ($interval->i > 0) return $interval->i . " minute" . ($interval->i > 1 ? "s" : "") . " ago";
        return "Just now";
    }
    if ($interval->d < 30) return $interval->d . " day" . ($interval->d > 1 ? "s" : "") . " ago";
    if ($interval->m < 12) return $interval->m . " month" . ($interval->m > 1 ? "s" : "") . " ago";
    return $interval->y . " year" . ($interval->y > 1 ? "s" : "") . " ago";
}

$stats = [
    'total'     => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'pending'   => $conn->query("SELECT COUNT(*) FROM pending_users")->fetchColumn(),
    'approved'  => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(), // same as total
];

$smsLogs = $conn->query("
    SELECT * FROM sms_logs 
    ORDER BY sent_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$emailLogs = $conn->query("
    SELECT * FROM email_logs 
    ORDER BY sent_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
    <link rel="stylesheet" href="css/superadmin_dashboard.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
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
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/dashboard_icon_active.png" alt="">
                <a href="<?= htmlspecialchars($dashboard_url) ?>" class="<?= $current_page == $dashboard_url ? 'active' : '' ?>">Dashboard</a>
            </div>
            
            <p class="menu-header">BASE</p>
            <?php if ($adminRole == 'super_admin'): ?>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/admin_icon.png" alt="">
                <a href="manage_staff.php" class="<?= $current_page == 'manage_staff.php' ? 'active' : '' ?>">Admin Account Management</a>
            </div>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/account_approval_icon.png" alt="">
                <a href="account_approval.php" class="<?= $current_page == 'account_approval.php' ? 'active' : '' ?>">User Account Management</a>
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

    <div class="dashboard-content">
        <div class="title-con">
            <h2>Dashboard Overview</h2>
        </div>

        <div class="dashboard-sections">
            <div class="section-wrapper">
                <!-- Alert Section Cards-->
                <section class="alert-section">
                    <h3>Alerts</h3>
                    <!-- Pending Account Approval Card-->
                    <div class="alert-card <?= $pendingCount > 0 ? 'has-pending' : '' ?>">
                        <div class="alert-header">
                            <img src="images/icons/dashboard/pending_account_icon.png" alt="Pending Accounts" class="alert-icon">
                            <h4>Pending Account Approvals</h4>
                        </div>
                        <p class="pending-info">
                            <span class="pending-number"><?= $pendingCount ?></span> accounts awaiting approval
                        </p>
                        <a href="account_requests.php" class="manage-link">Manage Approvals</a>
                    </div>
                </section>

                <section class="summary-section">
                    <h3>Summary</h3>
                    <div class="stat-bars">
                        <!-- 1. Active Users -->
                        <div class="stat-item">
                            <div class="stat-label">
                                <img src="images/icons/dashboard/total_users_icon.png" alt="">
                                <span>Total Active Users</span>
                            </div>
                            <div class="stat-value"><?= number_format($totalActiveUsers) ?></div>
                        </div>

                        <!-- 2. Admin Accounts -->
                        <div class="stat-item">
                            <div class="stat-label">
                                <img src="images/icons/dashboard/total_admins_icon.png" alt="">
                                <span>Total Admin Accounts</span>
                            </div>
                            <div class="stat-value"><?= number_format($totalAdmins) ?></div>
                        </div>
                    </div>
                </section>

                <section class="quick-actions-section">
                    <h3>Quick Actions</h3>
                    <div class="actions-container">
                        <!-- Create Admin Account -->
                        <a href="manage_staff.php" class="action-btn admin-btn">
                            <img src="images/icons/admin_icon.png" alt="Create Admin">
                            <span>Create Admin Account</span>
                        </a>
                    </div>
                </section>
    
                <section class="recent-activities-section">
                    <h3>Recent Activities</h3>
                    <div class="activity-list">
                        <?php if (empty($activities)): ?>
                            <p class="no-activity">No recent activity.</p>
                        <?php else: ?>
                            <?php foreach ($activities as $act): ?>
                                <?php
                                    // Map action type to icon & color
                                    $iconMap = [
                                        'announcement_create' => ['icon' => 'dashboard/active_announcement_icon.png', 'color' => '#9b59b6'],
                                        'announcement_update' => ['icon' => 'dashboard/active_announcement_icon.png', 'color' => '#8e44ad'],
                                        'announcement_toggle' => ['icon' => 'dashboard/active_announcement_icon.png', 'color' => '#71368a'],
                                        'event_create'        => ['icon' => 'dashboard/upcoming_events_icon.png', 'color' => '#e67e22'],
                                        'event_delete'        => ['icon' => 'dashboard/upcoming_events_icon.png', 'color' => '#d35400'],
                                        'user_approval'       => ['icon' => 'dashboard/approved_user_icon.png', 'color' => '#27ae60'],
                                        'user_rejection'      => ['icon' => 'dashboard/reject_user_icon.png', 'color' => '#c0392b'],
                                        'service_create'      => ['icon' => 'dashboard/service_icon.png', 'color' => '#2980b9'],
                                        'service_update'      => ['icon' => 'dashboard/service_icon.png', 'color' => '#3498db'],

                                        // MEDICINE CATALOG
                                        'add_medicine'        => ['icon' => 'dashboard/add_medicine_icon.png',           'color' => '#27ae60'], // Green - Add
                                        'update_medicine'     => ['icon' => 'dashboard/update_medicine_icon.png',          'color' => '#f39c12'], // Orange - Edit
                                        'delete_medicine'     => ['icon' => 'dashboard/delete_medicine_icon.png',        'color' => '#e74c3c'], // Red - Delete

                                        // MEDICINE BATCHES
                                        'add_batch'           => ['icon' => 'dashboard/add_batch_icon.png',     'color' => '#27ae60'], // Green
                                        'update_batch'        => ['icon' => 'dashboard/update_batch_icon.png',    'color' => '#f39c12'], // Orange
                                        'delete_batch'        => ['icon' => 'dashboard/delete_batch_icon.png',  'color' => '#e74c3c'], // Red

                                        // PATIENT RECORDS
                                        'add_patient_record'  => ['icon' => 'dashboard/patient_add_icon.png',   'color' => '#3498db'], // Blue - Add Patient
                                        'update_patient_record' => ['icon' => 'dashboard/edit_patient_icon.png',       'color' => '#f39c12'], // Orange - Edit
                                        'add_family_member'   => ['icon' => 'dashboard/family_add_icon.png',    'color' => '#9b59b6'], // Purple - Family
                                        'archive_patient'     => ['icon' => 'dashboard/archive_patient_icon.png',       'color' => '#95a5a6'], // Gray - Archive
                                        'restore_patient'     => ['icon' => 'dashboard/restore_patient_icon.png',       'color' => '#1abc9c'], // Teal - Restore

                                        // MEDICINE REQUESTS
                                        'approve_request'     => ['icon' => 'dashboard/approve_request_icon.png',       'color' => '#27ae60'], // Green - Approve
                                        'decline_request'     => ['icon' => 'dashboard/decline_request_icon.png',       'color' => '#e74c3c'], // Red - Decline
                                        'mark_request_claimed'=> ['icon' => 'dashboard/claimed_request_icon.png',       'color' => '#2ecc71'], // Bright Green
                                        'return_unclaimed_request' => ['icon' => 'dashboard/return_request_icon.png','color' => '#e67e22'], // Carrot Orange

                                        // CONSULTATIONS
                                        'add_consultation'    => ['icon' => 'dashboard/add_consultation_icon.png',    'color' => '#3498db'], // Blue - Add
                                        'delete_consultation' => ['icon' => 'dashboard/delete_consultation_icon.png', 'color' => '#e74c3c'], // Red - Delete
                                    ];
                                    $type = $act['action_type'];
                                    $info = $iconMap[$type] ?? ['icon' => 'dashboard_icon_active.png', 'color' => '#7f8c8d'];
                                    
                                    // Humanize action
                                    $actionText = ucwords(str_replace('_', ' ', $type));
                                    $actionText = str_replace('User Approval', 'Approved User', $actionText);
                                    $actionText = str_replace('User Rejection', 'Rejected User', $actionText);
                                ?>
                                <details class="activity-item" style="border-left: 4px solid <?= $info['color'] ?>;">
                                    <summary>
                                        <img src="images/icons/<?= $info['icon'] ?>" alt="" class="activity-icon">
                                        <div class="activity-content">
                                            <p class="activity-desc">
                                                <strong><?= htmlspecialchars($act['admin_name'] ?? 'System') ?></strong>
                                                <?= htmlspecialchars($actionText) ?>
                                            </p>
                                            <p class="activity-time"><?= timeAgo($act['created_at']) ?></p>
                                        </div>
                                    </summary>
                                    <div class="activity-details">
                                        <p><?= nl2br(htmlspecialchars($act['action_details'] ?? 'No details recorded.')) ?></p>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="stats-reports-section">
                    <h3>Statistics and Reports</h3>
                    <div class="stats-card-container">
                        <a href="users_stats.php" class="stats-card user-stats-card">
                            <div class="stats-icon">
                                <img src="images/icons/dashboard/user_stats_icon.png" alt="User Stats">
                            </div>
                            <div class="stats-content">
                                <h4>User Statistics</h4>
                                <div class="stats-numbers">
                                    <div class="stat-line">
                                        <span class="label">Total Users</span>
                                        <span class="value"><?= number_format($stats['total']) ?></span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="label">Pending Approval</span>
                                        <span class="value pending"><?= number_format($stats['pending']) ?></span>
                                    </div>
                                </div>
                                <p class="view-more">View Detailed Report →</p>
                            </div>
                        </a>
                    </div>
                </section>

                <section class="system-logs-section">
                    <h3>System Logs</h3>
                    <div class="log-tabs">
                        <button class="tab-btn active" data-tab="sms">SMS Logs</button>
                        <button class="tab-btn" data-tab="email">Email Logs</button>
                    </div>

                    <!-- SMS Logs -->
                    <div class="log-content active" id="sms">
                        <?php if (empty($smsLogs)): ?>
                            <p class="no-logs">No SMS logs found.</p>
                        <?php else: ?>
                            <div class="log-list">
                                <?php foreach ($smsLogs as $log): ?>
                                    <details class="log-item">
                                        <summary>
                                            <span class="log-status <?= $log['status'] === 'success' ? 'success' : 'failed' ?>">
                                                <?= $log['status'] === 'success' ? 'Success' : 'Failed' ?>
                                            </span>
                                            <span class="log-recipient"><?= htmlspecialchars($log['recipient_name']) ?></span>
                                            <span class="log-time"><?= timeAgo($log['sent_at']) ?></span>
                                        </summary>
                                        <div class="log-details">
                                            <p><strong>Phone:</strong> <?= htmlspecialchars($log['recipient_phone']) ?></p>
                                            <p><strong>Message:</strong> <?= nl2br(htmlspecialchars($log['message'])) ?></p>
                                            <?php if ($log['status'] === 'failed'): ?>
                                                <p class="error-msg"><strong>Error:</strong> <?= htmlspecialchars($log['error_message']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Email Logs -->
                    <div class="log-content" id="email">
                        <?php if (empty($emailLogs)): ?>
                            <p class="no-logs">No email logs found.</p>
                        <?php else: ?>
                            <div class="log-list">
                                <?php foreach ($emailLogs as $log): ?>
                                    <details class="log-item">
                                        <summary>
                                            <span class="log-status <?= $log['status'] === 'success' ? 'success' : 'failed' ?>">
                                                <?= $log['status'] === 'success' ? 'Success' : 'Failed' ?>
                                            </span>
                                            <span class="log-recipient"><?= htmlspecialchars($log['recipient_name']) ?></span>
                                            <span class="log-time"><?= timeAgo($log['sent_at']) ?></span>
                                        </summary>
                                        <div class="log-details">
                                            <p><strong>Email:</strong> <?= htmlspecialchars($log['recipient_email']) ?></p>
                                            <p><strong>Subject:</strong> <?= htmlspecialchars($log['subject']) ?></p>
                                            <p><strong>Message:</strong> <?= nl2br(htmlspecialchars($log['message'])) ?></p>
                                            <?php if ($log['status'] === 'failed'): ?>
                                                <p class="error-msg"><strong>Error:</strong> <?= htmlspecialchars($log['error_message']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = document.querySelectorAll('.tab-btn');
            const contents = document.querySelectorAll('.log-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const target = tab.getAttribute('data-tab');

                    // Update active tab
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');

                    // Show content
                    contents.forEach(c => c.classList.remove('active'));
                    document.getElementById(target).classList.add('active');
                });
            });
        });
        </script>
</body>
</html>