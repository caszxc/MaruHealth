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
    LIMIT 5
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" href="images/3s logo.png" type="image/x-icon">

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
            <h2>Super Admin Dashboard</h2>
        </div>

        <div class="dashboard-sections">
            <div class="section-wrapper">
                <!-- Alert Section Cards-->
                <section class="alert-section">
                    <div class="section-header">
                        <h3>Alerts</h3>
                    </div>
                    
                    <!-- Pending Account Approval Card-->
                    <div class="alert-cards">
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
                    </div>
                    
                </section>

                <section class="summary-section">
                    <div class="section-header">
                        <h3>Summary</h3>
                    </div>
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
    
                <section class="recent-activities-section">
                    <div class="section-header">
                        <h3>Recent Activities</h3>
                        <a href="activity_logs.php" class="view-all-link">
                            <i class="fas fa-history"></i> View Full Activity Logs →
                        </a>
                    </div>
                    <div class="activity-container">
                        <div class="activity-list">
                            <?php if (empty($activities)): ?>
                                <p class="no-activity">No recent activity.</p>
                            <?php else: ?>
                                <?php foreach ($activities as $act): ?>
                                    <?php
                                        // Map action type to icon & color
                                        $iconMap = [
                                            'user_approval'           => ['label' => 'Approved User Account',           'icon' => 'fas fa-user-check',      'color' => '#27ae60'],
                                            'user_rejection'          => ['label' => 'Rejected User Account',          'icon' => 'fas fa-user-slash',      'color' => '#e74c3c'],
                                            'announcement_create'     => ['label' => 'Created Announcement',           'icon' => 'fas fa-bullhorn',        'color' => '#9b59b6'],
                                            'announcement_update'     => ['label' => 'Updated Announcement',           'icon' => 'fas fa-edit',            'color' => '#8e44ad'],
                                            'announcement_toggle'     => ['label' => 'Toggled Announcement Status',    'icon' => 'fas fa-toggle-on',       'color' => '#71368a'],
                                            'event_create'            => ['label' => 'Created Event',                  'icon' => 'fas fa-calendar-plus',   'color' => '#e67e22'],
                                            'event_delete'            => ['label' => 'Deleted Event',                  'icon' => 'fas fa-calendar-minus',  'color' => '#d35400'],
                                            'service_create'          => ['label' => 'Created Service',                'icon' => 'fas fa-plus-circle',     'color' => '#2980b9'],
                                            'service_update'          => ['label' => 'Updated Service',                'icon' => 'fas fa-cogs',            'color' => '#3498db'],
                                            'service_delete'          => ['label' => 'Deleted Service',                'icon' => 'fas fa-trash-alt',       'color' => '#c0392b'],
                                            'add_patient_record'      => ['label' => 'Added Patient Record',           'icon' => 'fas fa-user-plus',       'color' => '#3498db'],
                                            'update_patient_record'   => ['label' => 'Updated Patient Record',         'icon' => 'fas fa-edit',            'color' => '#f39c12'],
                                            'archive_patient'         => ['label' => 'Archived Patient',               'icon' => 'fas fa-archive',         'color' => '#95a5a6'],
                                            'restore_patient'         => ['label' => 'Restored Patient',               'icon' => 'fas fa-undo',            'color' => '#1abc9c'],
                                            'approve_request'         => ['label' => 'Approved Medicine Request',      'icon' => 'fas fa-check-circle',    'color' => '#27ae60'],
                                            'decline_request'         => ['label' => 'Declined Medicine Request',      'icon' => 'fas fa-times-circle',    'color' => '#e74c3c'],
                                            'mark_request_claimed'    => ['label' => 'Marked Request as Claimed',      'icon' => 'fas fa-prescription-bottle', 'color' => '#2ecc71'],
                                            'return_unclaimed_request'=> ['label' => 'Returned Unclaimed Medicines',   'icon' => 'fas fa-undo',            'color' => '#e67e22'],
                                            'add_medicine'            => ['label' => 'Added Medicine to Catalog',      'icon' => 'fas fa-pills',           'color' => '#27ae60'],
                                            'update_medicine'         => ['label' => 'Updated Medicine Catalog',       'icon' => 'fas fa-edit',            'color' => '#f39c12'],
                                            'delete_medicine'         => ['label' => 'Deleted Medicine from Catalog',  'icon' => 'fas fa-trash',           'color' => '#e74c3c'],
                                            'add_batch'               => ['label' => 'Added Medicine Batch',           'icon' => 'fas fa-box',             'color' => '#27ae60'],
                                            'update_batch'            => ['label' => 'Updated Medicine Batch',         'icon' => 'fas fa-edit',            'color' => '#f39c12'],
                                            'delete_batch'            => ['label' => 'Deleted Medicine Batch',         'icon' => 'fas fa-trash',           'color' => '#e74c3c'],
                                            'add_family_member'       => ['label' => 'Added Family Member',            'icon' => 'fas fa-users',           'color' => '#9b59b6'],
                                            'add_consultation'        => ['label' => 'Added Consultation Record',      'icon' => 'fas fa-notes-medical',   'color' => '#3498db'],
                                            'delete_consultation'     => ['label' => 'Deleted Consultation Record',    'icon' => 'fas fa-trash',           'color' => '#e74c3c'],
                                            'delete_user'             => ['label' => 'Deleted User Account',           'icon' => 'fas fa-user-times',      'color' => '#c0392b'],
                                        ];
                                        $type = $act['action_type'];
                                        $info = $iconMap[$type] ?? [
                                            'label' => ucwords(str_replace('_', ' ', $type)),
                                            'icon'  => 'fas fa-cube',
                                            'color' => '#7f8c8d'
                                        ];

                                        // Use the clean label from iconMap, fallback to formatted type
                                        $actionText = $info['label'];
                                    ?>
                                    <details class="activity-item" style="border-left: 4px solid <?= $info['color'] ?>;">
                                        <summary>
                                            <i class="<?= $info['icon'] ?>" style="color: <?= $info['color'] ?>; font-size: 1.2rem; font-size: 2rem;"></i>
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
                    </div>
                </section>

                <section class="stats-reports-section">
                    <div class="section-header">
                        <h3>Statistics and Reports</h3>
                    </div>
                    <div class="stats-card-container">

                        <!-- 1. User Statistics -->
                        <a href="user_stats.php" class="stats-card user-stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stats-content">
                                <h4>User Statistics</h4>
                                <div class="stats-numbers">
                                    <div class="stat-line">
                                        <span class="label">Total Registered Users</span>
                                        <span class="value"><?= number_format($stats['total']) ?></span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="label">Pending Approval</span>
                                        <span class="value pending"><?= number_format($stats['pending']) ?></span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="label">Approved / Active</span>
                                        <span class="value success"><?= number_format($stats['approved']) ?></span>
                                    </div>
                                </div>
                                <p class="view-more">View Detailed Report →</p>
                            </div>
                        </a>

                        <!-- 2. Patient Statistics -->
                        <a href="patient_stats.php" class="stats-card patient-stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-user-injured"></i>
                            </div>
                            <div class="stats-content">
                                <h4>Patient Statistics</h4>
                                <div class="stats-numbers">
                                    <?php
                                    $totalPatients   = $conn->query("SELECT COUNT(*) FROM patients WHERE status = 'active'")->fetchColumn();
                                    $archivedPatients = $conn->query("SELECT COUNT(*) FROM patients WHERE status = 'archived'")->fetchColumn();
                                    $totalFamilies   = $conn->query("SELECT COUNT(*) FROM families")->fetchColumn();
                                    ?>
                                    <div class="stat-line">
                                        <span class="label">Active Patients</span>
                                        <span class="value"><?= number_format($totalPatients) ?></span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="label">Archived Patients</span>
                                        <span class="value"><?= number_format($archivedPatients) ?></span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="label">Total Families</span>
                                        <span class="value"><?= number_format($totalFamilies) ?></span>
                                    </div>
                                </div>
                                <p class="view-more">View Detailed Report →</p>
                            </div>
                        </a>

                        <!-- 3. Consultation Statistics -->
                        <a href="consultation_stats.php" class="stats-card consultation-stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-stethoscope"></i>
                            </div>
                            <div class="stats-content">
                                <h4>Consultation Statistics</h4>
                                <div class="stats-numbers">
                                    <?php
                                    $totalConsultations = $conn->query("SELECT COUNT(*) FROM consultations")->fetchColumn();
                                    $thisMonthConsult   = $conn->query("SELECT COUNT(*) FROM consultations WHERE MONTH(consultation_date) = MONTH(CURDATE()) AND YEAR(consultation_date) = YEAR(CURDATE())")->fetchColumn();
                                    $todayConsult       = $conn->query("SELECT COUNT(*) FROM consultations WHERE DATE(consultation_date) = CURDATE()")->fetchColumn();
                                    ?>
                                    <div class="stat-line">
                                        <span class="label">Total Consultations</span>
                                        <span class="value"><?= number_format($totalConsultations) ?></span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="label">This Month</span>
                                        <span class="value"><?= number_format($thisMonthConsult) ?></span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="label">Today</span>
                                        <span class="value highlight"><?= number_format($todayConsult) ?></span>
                                    </div>
                                </div>
                                <p class="view-more">View Detailed Report →</p>
                            </div>
                        </a>

                        <!-- 4. Medicine Statistics -->
                        <a href="medicine_stats.php" class="stats-card medicine-stats-card">
                            <div class="stats-icon">
                                <i class="fas fa-pills"></i>
                            </div>
                            <div class="stats-content">
                                <h4>Medicine Statistics</h4>
                                <div class="stats-numbers">
                                    <?php
                                    $totalMedicines   = $conn->query("SELECT COUNT(*) FROM medicines_catalog")->fetchColumn();
                                    $inStock          = $conn->query("SELECT COUNT(*) FROM medicines_catalog WHERE stock_status = 'In Stock'")->fetchColumn();
                                    $lowStock         = $conn->query("SELECT COUNT(*) FROM medicines_catalog WHERE stock_status = 'Low Stock'")->fetchColumn();
                                    $outOfStock       = $conn->query("SELECT COUNT(*) FROM medicines_catalog WHERE stock_status = 'Out of Stock'")->fetchColumn();
                                    $expiringSoon     = $conn->query("SELECT COUNT(DISTINCT catalog_id) FROM medicine_batches WHERE expiry_status IN ('Expiring within a month', 'Expiring within a week') AND is_disposed = 0")->fetchColumn();
                                    ?>
                                    <div class="stat-line">
                                        <span class="label">Total Medicines (Catalog)</span>
                                        <span class="value"><?= number_format($totalMedicines) ?></span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="label">In Stock</span>
                                        <span class="value success"><?= number_format($inStock) ?></span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="label">Low Stock / Expiring Soon</span>
                                        <span class="value warning"><?= number_format($lowStock + $expiringSoon) ?></span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="label">Out of Stock</span>
                                        <span class="value danger"><?= number_format($outOfStock) ?></span>
                                    </div>
                                </div>
                                <p class="view-more">View Detailed Report →</p>
                            </div>
                        </a>
                    </div>
                </section>

                <section class="system-logs-section">
                    <div class="section-header">
                        <h3>SMS and Email Logs</h3>
                    </div>
                    <div class="system-logs">
                        <div class="log-tabs">
                            <button class="tab-btn active" data-tab="sms">SMS</button>
                            <button class="tab-btn" data-tab="email">Email</button>
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
                                                <?php 
                                                    $cleanMessage = strip_tags($log['message']);
                                                    $shortMessage = strlen($cleanMessage) > 200 
                                                        ? substr($cleanMessage, 0, 200) . "..." 
                                                        : $cleanMessage;
                                                ?>
                                                <p><strong>Message:</strong> 
                                                    <?= nl2br(htmlspecialchars($shortMessage)) ?>
                                                    <?php if (strlen($cleanMessage) > 200): ?>
                                                        <br><small style="color:#666;">(truncated)</small>
                                                    <?php endif; ?>
                                                </p>                                             
                                                <?php if ($log['status'] === 'failed'): ?>
                                                    <p class="error-msg"><strong>Error:</strong> <?= htmlspecialchars($log['error_message']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
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