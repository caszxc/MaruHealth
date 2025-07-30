<?php
session_start();
require_once "config.php";

// Check if user is logged in and is admin
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

// Get pending account approvals
$pendingAccountsStmt = $conn->prepare("SELECT id, first_name, last_name, email, date_registered 
                                      FROM pending_users 
                                      WHERE role = 'user' 
                                      ORDER BY date_registered ASC 
                                      LIMIT 5");
$pendingAccountsStmt->execute();
$pendingAccounts = $pendingAccountsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent announcements
$announcementsStmt = $conn->prepare("SELECT id, title, created_at 
                                    FROM announcements 
                                    WHERE status = 'active' 
                                    ORDER BY created_at DESC 
                                    LIMIT 5");
$announcementsStmt->execute();
$announcements = $announcementsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming events
$eventsStmt = $conn->prepare("SELECT id, title, event_date, start, venue 
                             FROM events 
                             WHERE event_date >= CURDATE() 
                             ORDER BY event_date ASC 
                             LIMIT 5");
$eventsStmt->execute();
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

// Count total pending accounts
$totalPendingStmt = $conn->query("SELECT COUNT(*) FROM pending_users WHERE role = 'user'");
$totalPending = $totalPendingStmt->fetchColumn();

// Count active announcements
$totalAnnouncementsStmt = $conn->query("SELECT COUNT(*) FROM announcements WHERE status = 'active'");
$totalAnnouncements = $totalAnnouncementsStmt->fetchColumn();

// Count upcoming events
$upcomingEventsStmt = $conn->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()");
$upcomingEvents = $upcomingEventsStmt->fetchColumn();

// Count total users
$totalUsersStmt = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$totalUsers = $totalUsersStmt->fetchColumn();
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
    <style>
        .stats-cards {
            display: flex;
            justify-content: flex-start;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        
        
        .stat-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            width: 22%;
            margin-bottom: 15px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            margin-top: 0;
            color: #555;
            font-size: 16px;
        }
        
        .stat-card .count {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }
        
        .alert-section {
            margin-bottom: 30px;
        }
        
        .alert-section h2 {
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        
        .alert-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .alert-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 15px;
            flex: 1;
            min-width: 300px;
        }
        
        .alert-card h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .alert-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        
        .alert-list li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .alert-list li:last-child {
            border-bottom: none;
        }
        
        .view-all {
            display: block;
            text-align: right;
            margin-top: 10px;
            color: #337ab7;
            text-decoration: none;
            font-size: 14px;
        }
        
        .view-all:hover {
            text-decoration: underline;
        }
        
        .event-date {
            color: #d9534f;
            font-weight: bold;
        }
        
        .tiles-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 30px;
        }
        
        .tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            width: 200px;
            height: 150px;
            transition: transform 0.3s, background-color 0.3s;
        }
        
        .tile:hover {
            transform: translateY(-5px);
            background-color: #e9ecef;
        }
        
        .tile img {
            width: 40px;
            height: 40px;
            margin-bottom: 10px;
        }
        
        .tile h3 {
            margin: 0;
            color: #333;
            text-align: center;
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
    </style>
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="images/3s logo.png">
            <p>Maru-Health <br> Barangay Marulas 3S <br> Health Station</p>
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
                $dashboard_url = 'admin_dashboard.php';
            ?>
            <p class="menu-header">ANALYTICS</p>
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/dashboard_icon_active.png" alt="">
                <a href="<?= htmlspecialchars($dashboard_url) ?>" class="<?= $current_page == $dashboard_url ? 'active' : '' ?>">Dashboard</a>
            </div>
            <p class="menu-header">BASE</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/account_approval_icon.png" alt="">
                <a href="account_approval.php" class="<?= $current_page == 'account_approval.php' ? 'active' : '' ?>">Account Approval</a>
            </div>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/announcement_icon.png" alt="">
                <a href="announcements.php" class="<?= $current_page == 'announcements.php' ? 'active' : '' ?>">Announcement</a>
            </div>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/calendar_icon.png" alt="">
                <a href="edit_calendar.php" class="<?= $current_page == 'edit_calendar.php' ? 'active' : '' ?>">Calendar</a>
            </div>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/calendar_icon.png" alt="">
                <a href="content_management.php" class="<?= $current_page == 'content_management.php' ? 'active' : '' ?>">Content Management</a>
            </div>
            <p class="menu-header">OTHERS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/logout_icon.png" alt="">
                <a href="logout.php" class="logout-button">Log Out</a>
            </div>
        </div>
    </div>

    <div class="dashboard-content">
        <h1>Admin Dashboard</h1>
        
        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="count"><?= $totalUsers ?></div>
                <a href="users_stats.php" class="view-all">View Details</a>
            </div>
            <div class="stat-card">
                <h3>Pending Accounts</h3>
                <div class="count"><?= $totalPending ?></div>
                <a href="account_requests.php" class="view-all">View Details</a>
            </div>
            <div class="stat-card">
                <h3>Active Announcements</h3>
                <div class="count"><?= $totalAnnouncements ?></div>
                <a href="announcements.php" class="view-all">View Details</a>
            </div>
            <div class="stat-card">
                <h3>Upcoming Events</h3>
                <div class="count"><?= $upcomingEvents ?></div>
                <a href="edit_calendar.php" class="view-all">View Details</a>
            </div>
        </div>
        
        <!-- Alert Sections -->
        <div class="alert-section">
            <h2>Recent Activity</h2>
            
            <div class="alert-cards">
                <!-- Pending Account Approvals -->
                <div class="alert-card">
                    <h3>Pending Account Approvals</h3>
                    <?php if (count($pendingAccounts) > 0): ?>
                        <ul class="alert-list">
                            <?php foreach ($pendingAccounts as $account): ?>
                                <li>
                                    <strong><?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) ?></strong>
                                    <br>
                                    <span class="request-date">Email: <?= htmlspecialchars($account['email']) ?></span>
                                    <br>
                                    <span class="request-date">Registered: <?= date('M d, Y', strtotime($account['date_registered'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="account_approval.php" class="view-all">View All Pending Accounts</a>
                    <?php else: ?>
                        <p>No pending account approvals.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Announcements -->
                <div class="alert-card">
                    <h3>Recent Announcements</h3>
                    <?php if (count($announcements) > 0): ?>
                        <ul class="alert-list">
                            <?php foreach ($announcements as $announcement): ?>
                                <li>
                                    <strong><?= htmlspecialchars($announcement['title']) ?></strong>
                                    <br>
                                    <span class="request-date">Posted: <?= date('M d, Y', strtotime($announcement['created_at'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="announcements.php" class="view-all">View All Announcements</a>
                    <?php else: ?>
                        <p>No recent announcements.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Upcoming Events -->
                <div class="alert-card">
                    <h3>Upcoming Events</h3>
                    <?php if (count($events) > 0): ?>
                        <ul class="alert-list">
                            <?php foreach ($events as $event): ?>
                                <li>
                                    <strong><?= htmlspecialchars($event['title']) ?></strong>
                                    <br>
                                    <span class="event-date">Date: <?= date('M d, Y', strtotime($event['event_date'])) ?></span>
                                    <br>
                                    <span class="request-date">Time: <?= date('g:i A', strtotime($event['start'])) ?></span>
                                    <br>
                                    <span class="request-date">Venue: <?= htmlspecialchars($event['venue'] ?? 'Not specified') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="edit_calendar.php" class="view-all">View All Events</a>
                    <?php else: ?>
                        <p>No upcoming events.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</body>
</html>