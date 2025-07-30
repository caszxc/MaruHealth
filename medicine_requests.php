<?php
session_start();
require_once "config.php";

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}
// Count medicine requests
$requestsQuery = "SELECT COUNT(*) AS requests FROM medicine_requests WHERE request_status = 'pending'";
$requestsStmt = $conn->prepare($requestsQuery);
$requestsStmt->execute();
$requestsCount = $requestsStmt->fetch(PDO::FETCH_ASSOC)['requests'];

// Count pending requests
$pendingQuery = "SELECT COUNT(*) AS pending FROM medicine_requests WHERE request_status = 'to be claimed'";
$pendingStmt = $conn->prepare($pendingQuery);
$pendingStmt->execute();
$pendingCount = $pendingStmt->fetch(PDO::FETCH_ASSOC)['pending'];

// Count archives (claimed + declined)
$archivesQuery = "SELECT COUNT(*) AS archives FROM medicine_requests WHERE request_status IN ('claimed', 'declined')";
$archivesStmt = $conn->prepare($archivesQuery);
$archivesStmt->execute();
$archivesCount = $archivesStmt->fetch(PDO::FETCH_ASSOC)['archives'];

// Fetch the admin's name
$adminId = $_SESSION['admin_id'];
$adminStmt = $conn->prepare("SELECT * FROM admin_staff WHERE id = :id");
$adminStmt->bindParam(':id', $adminId);
$adminStmt->execute();
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

// Default to session information if query fails
$adminName = $admin ? $admin['full_name'] : $_SESSION['admin_name'];
$adminRole = $admin ? $admin['role'] : $_SESSION['admin_role'];

// Format role for display (convert super_admin to Super Admin)
$displayRole = ucwords(str_replace('_', ' ', $adminRole));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Requests</title>
    <link rel="stylesheet" href="css/medicine_requests.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">

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

                // Determine dashboard URL based on role
                $dashboard_url = ''; // Default
                if ($adminRole === 'super_admin') {
                    $dashboard_url = 'superadmin_dashboard.php';
                } elseif ($adminRole === 'admin') {
                    $dashboard_url = 'admin_dashboard.php';
                } elseif ($adminRole === 'staff') {
                    $dashboard_url = 'staff_dashboard.php';
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
                <img class="menu-icon" src="images/icons/account_approval_icon.png" alt="">
                <a href="manage_staff.php" class="<?= $current_page == 'manage_staff.php' ? 'active' : '' ?>">Manage Staff</a>
            </div>
            <?php endif; ?>
            
            <?php if ($adminRole == 'super_admin' || $adminRole == 'admin'): ?>
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
            <?php endif; ?>

            <?php if ($adminRole == 'super_admin' || $adminRole == 'staff'): ?>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/patient_icon.png" alt="">
                <a href="patient_management.php" class="<?= $current_page == 'patient_management.php' ? 'active' : '' ?>">Patient Management</a>
            </div>
            
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/med_icon.png" alt="">
                <a href="medicine_management.php" class="<?= $current_page == 'medicine_management.php' ? 'active' : '' ?>">Medicine Management</a>
            </div>
            
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/reqmd_icon_active.png" alt="">
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

    <div class="med-req-content">
        <div class="tiles-container">
            <a href="requests.php">
                <div class="tile">
                    <img src="images/icons/reqmd_icon_active.png" alt="">
                    <h3>Pending Requests</h3>
                    <p><?= $requestsCount ?></p>
                </div>
            </a>
            <a href="pending_requests.php">
                <div class="tile">
                    <img src="images/icons/pendings_icon_active.png" alt="">
                    <h3>To be Claimed Requests</h3>
                    <p><?= $pendingCount ?></p>
                </div>
            </a>
            <a href="archived_requests.php">
                <div class="tile">
                    <img src="images/icons/archives_icon_active.png" alt="">
                    <h3>Archives</h3>
                    <p><?= $archivesCount ?></p>
                </div>
            </a>
            
        </div>
    </div>

</body>
</html>
