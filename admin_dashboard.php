<?php
// admin_dashboard.php
session_start();
require_once "config.php";
include 'settings.php';

// Check if user is logged in and is super admin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
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

$totalActiveAnnouncementsStmt = $conn->query("SELECT COUNT(*) FROM announcements WHERE status = 'active'");
$totalActiveAnnouncements    = $totalActiveAnnouncementsStmt->fetchColumn();

$today = date('Y-m-d');
$upcomingEventsStmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM events 
    WHERE event_date >= :today
");
$upcomingEventsStmt->execute([':today' => $today]);
$upcomingEvents = $upcomingEventsStmt->fetchColumn();


$activityStmt = $conn->prepare("
    SELECT al.*, a.full_name AS admin_name
    FROM activity_logs al
    LEFT JOIN admin_staff a ON al.admin_id = a.id
    WHERE a.role = 'admin'
    ORDER BY al.created_at DESC
    LIMIT 10;
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/admin_dashboard.css">
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
            <h2>Admin Dashboard</h2>
        </div>

        <div class="dashboard-sections">
            <div class="section-wrapper">
                <section class="summary-section">
                    <div class="section-header">
                        <h3>Summary</h3>
                    </div>
                    <div class="stat-bars">
                        <!-- 3. Active Announcements -->
                        <div class="stat-item">
                            <div class="stat-label">
                                <img src="images/icons/dashboard/active_announcement_icon.png" alt="">
                                <span>Active Announcements</span>
                            </div>
                            <div class="stat-value"><?= number_format($totalActiveAnnouncements) ?></div>
                        </div>

                        <!-- 4. Upcoming Events -->
                        <div class="stat-item">
                            <div class="stat-label">
                                <img src="images/icons/dashboard/upcoming_events_icon.png" alt="">
                                <span>Upcoming Events</span>
                            </div>
                            <div class="stat-value"><?= number_format($upcomingEvents) ?></div>
                        </div>
                    </div>
                </section>
    
                <section class="recent-activities-section">
                    <div class="section-header">
                        <h3>Recent Activities</h3>
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
            </div>
        </div>
    </div>
</body>
</html>