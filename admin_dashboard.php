<?php
// admin_dashboard.php
session_start();
require_once "config.php";

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
    LIMIT 8;
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
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="images/3s logo.png">
            <div>
                <h1>
                    <span class="maruhealth">MaruHealth</span>
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
            <h2>Dashboard Overview</h2>
        </div>

        <div class="dashboard-sections">
            <div class="section-wrapper">
                <!-- Alert Section Cards-->
                 
                <section class="summary-section">
                    <h3>Summary</h3>
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

                <section class="quick-actions-section">
                    <h3>Quick Actions</h3>
                    <div class="actions-container">
                        <!-- Create Announcement -->
                        <a href="announcements.php" class="action-btn announce-btn">
                            <img src="images/icons/announcement_icon.png" alt="Announcement">
                            <span>Create Announcement</span>
                        </a>

                        <!-- Add Event -->
                        <a href="edit_calendar.php" class="action-btn event-btn">
                            <img src="images/icons/calendar_icon.png" alt="Add Event">
                            <span>Add Event</span>
                        </a>

                        <!-- Add New Service -->
                        <a href="service_management.php" class="action-btn service-btn">
                            <img src="images/icons/service_icon.png" alt="Add Service">
                            <span>Add New Service</span>
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
                                        'service_delete'      => ['icon' => 'dashboard/service_icon.png', 'color' => '#3498db'],
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
            </div>
        </div>
    </div>
</body>
</html>