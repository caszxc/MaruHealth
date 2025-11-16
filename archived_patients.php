<?php
//archived_patients.php
session_start();
require_once "config.php";
include 'settings.php';

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Get search term from GET request and sanitize it
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch archived patients with optional search filter
$query = "SELECT * FROM patients WHERE status = 'archived'";
if (!empty($searchTerm)) {
    $query .= " AND (family_number LIKE :search OR last_name LIKE :search OR first_name LIKE :search OR middle_name LIKE :search)";
}
$query .= " ORDER BY id DESC";

$stmt = $conn->prepare($query);
if (!empty($searchTerm)) {
    $searchParam = "%$searchTerm%";
    $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
}
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Patient Management</title>
    <link rel="stylesheet" href="css/patient_management.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= $logo_url ?>" type="image/x-icon">
    <style>
        .message {
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            transition: opacity .5s ease-in-out;
        }
        .message.success { background:#dff0d8; color:#3c763d; }
        .message.error   { background:#f2dede; color:#a94442; }
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

                // Determine dashboard URL based on role
                $dashboard_url = ''; // Default
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

            <?php if ($adminRole == 'super_admin' || $adminRole == 'health_staff'): ?>
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/patient_icon_active.png" alt="">
                <a href="patient_management.php" class="<?= ($current_page == 'patient_management.php' || $current_page == 'archived_patients.php') ? 'active' : '' ?>">Patient Management</a>
            </div>
            
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/med_icon.png" alt="">
                <a href="medicine_management.php" class="<?= $current_page == 'medicine_management.php' ? 'active' : '' ?>">Medicine Management</a>
            </div>
            
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/reqmd_icon.png" alt="">
                <a href="medicine_requests.php" class="<?= ($current_page == 'medicine_requests.php' || $current_page == 'requests.php') ? 'active' : '' ?>">Medicine Requests</a>
            </div>
            <?php endif; ?>

            <p class="menu-header">OTHERS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/logout_icon.png" alt="">
                <a href="logout.php" class="logout-button">Log Out</a>
            </div>
        </div>
    </div>

    <div class="patient-content">
        <div class="title-con">
            <div class="title">
                <a href="patient_management.php" class="back-button">← Back</a>
                <h2>Archived Patients</h2>
            </div>
        </div>
        <div class="patient-container">
            <div class="sort-controls">
                <div class="search-con">
                    <form method="GET" action="archived_patients.php">
                        <input type="text" name="search" placeholder="Search by Family No. or Name" value="<?= htmlspecialchars($searchTerm) ?>">
                        <button type="submit">Search</button>
                    </form>
                </div>
            </div>
            <!-- ----- SUCCESS / ERROR MESSAGE ----- -->
            <?php if (isset($_SESSION['patient_message'])): ?>
                <div class="message <?= (strpos($_SESSION['patient_message'], 'successfully') !== false || strpos($_SESSION['patient_message'], 'restored') !== false) ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($_SESSION['patient_message']) ?>
                </div>
                <?php unset($_SESSION['patient_message']); ?>
            <?php endif; ?>
            <div class="patient-table">
                <div class="table-container">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Family No.</th>
                                    <th>Last Name</th>
                                    <th>First Name</th>
                                    <th>Middle Name</th>
                                    <th>Sex</th>
                                    <th>Birthdate</th>
                                    <th>Contact Number</th>
                                    <th>Date Registered</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($patients)): ?>
                                    <tr>
                                        <td colspan="10" class="no-patients" style="text-align: center;">No archived patients found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($patients as $patient): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($patient['family_number']) ?></td>
                                        <td><?= htmlspecialchars($patient['last_name']) ?></td>
                                        <td><?= htmlspecialchars($patient['first_name']) ?></td>
                                        <td><?= htmlspecialchars($patient['middle_name']) ?></td>
                                        <td><?= htmlspecialchars($patient['sex']) ?></td>
                                        <td><?= htmlspecialchars($patient['birthdate']) ?></td>
                                        <td><?= htmlspecialchars($patient['contact_number']) ?></td>
                                        <td><?= htmlspecialchars($patient['created_at']) ?></td>
                                        <td class="action-buttons">
                                            <a href="view_patient.php?id=<?= $patient['id'] ?>" class="view-btn">VIEW</a>
                                            <a href="restore_patient.php?id=<?= $patient['id'] ?>" class="restore-btn" onclick="return confirm('Are you sure you want to restore this patient?')">RESTORE</a>
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
</body>
</html>