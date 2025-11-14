    <?php
    session_start();
    require_once "config.php";
    include 'settings.php';

    // ---------------------------------------------------------------------
    // 1. AUTHENTICATION
    // ---------------------------------------------------------------------
    if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'health_staff'])) {
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


    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Medicine Statistics</title>
        <link rel="stylesheet" href="css/stats.css">
        <link rel="stylesheet" href="css/nav_footer.css">
        <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    </head>
    <body>
        <!-- ====================== NAVBAR & SIDEBAR (unchanged) ====================== -->
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
                <img src="images/profile-placeholder.png" alt="Staff">
                <div class="profile-details">
                    <p class="admin_name"><strong><?=htmlspecialchars($adminName)?></strong></p>
                    <p class="role"><?=htmlspecialchars($displayRole)?></p>
                </div>
            </div>
            <div class="menu">
                <?php 
                $current_page = basename($_SERVER['PHP_SELF']);
                $dashboard_url = $adminRole === 'super_admin' ? 'superadmin_dashboard.php' : 'healthstaff_dashboard.php';
                ?>
                <p class="menu-header">ANALYTICS</p>
                <div class="menu-link-active">
                    <img class="menu-icon" src="images/icons/dashboard_icon_active.png" alt="">
                    <a href="<?=htmlspecialchars($dashboard_url)?>" class="<?= $current_page == 'medicine_stats.php' ? 'active' : '' ?>">Dashboard</a>
                </div>

                <p class="menu-header">BASE</p>
                <?php if ($adminRole == 'super_admin'): ?>
                <div class="menu-link"><img class="menu-icon" src="images/icons/account_approval_icon.png" alt=""><a href="manage_staff.php" class="<?= $current_page == 'manage_staff.php' ? 'active' : '' ?>">Manage Staff</a></div>
                <?php endif; ?>
                <?php if ($adminRole == 'super_admin' || $adminRole == 'admin'): ?>
                <div class="menu-link"><img class="menu-icon" src="images/icons/account_approval_icon.png" alt=""><a href="account_approval.php" class="<?= $current_page == 'account_approval.php' ? 'active' : '' ?>">Account Approval</a></div>
                <div class="menu-link"><img class="menu-icon" src="images/icons/announcement_icon.png" alt=""><a href="announcements.php" class="<?= $current_page == 'announcements.php' ? 'active' : '' ?>">Announcement</a></div>
                <div class="menu-link"><img class="menu-icon" src="images/icons/calendar_icon.png" alt=""><a href="edit_calendar.php" class="<?= $current_page == 'edit_calendar.php' ? 'active' : '' ?>">Calendar</a></div>
                <div class="menu-link"><img class="menu-icon" src="images/icons/calendar_icon.png" alt=""><a href="service_management.php" class="<?= $current_page == 'service_management.php' ? 'active' : '' ?>">Service Management</a></div>
                <?php endif; ?>
                <?php if ($adminRole == 'super_admin' || $adminRole == 'health_staff'): ?>
                <div class="menu-link"><img class="menu-icon" src="images/icons/patient_icon.png" alt=""><a href="patient_management.php" class="<?= $current_page == 'patient_management.php' ? 'active' : '' ?>">Patient Management</a></div>
                <div class="menu-link"><img class="menu-icon" src="images/icons/med_icon.png" alt=""><a href="medicine_management.php" class="<?= $current_page == 'medicine_management.php' ? 'active' : '' ?>">Medicine Management</a></div>
                <div class="menu-link"><img class="menu-icon" src="images/icons/reqmd_icon.png" alt=""><a href="medicine_requests.php" class="<?= $current_page == 'medicine_requests.php' ? 'active' : '' ?>">Medicine Requests</a></div>
                <?php endif; ?>

                <p class="menu-header">OTHERS</p>
                <div class="menu-link"><img class="menu-icon" src="images/icons/logout_icon.png" alt=""><a href="logout.php" class="logout-button">Log Out</a></div>
            </div>
        </div>

        <!-- ====================== MAIN CONTENT ====================== -->
        <div class="dashboard-content">
            <div class="title-con">
                <div style="display:flex;gap:15px;align-items:center;">
                    <a href="<?= $dashboard_url ?>" class="back-button">Back</a>
                    <h2>Medicine Statistics</h2>
                </div>
            </div>

            <div class="stats-container">
                <div class="stats-header">
                    <div class="filter">
                        <!-- ====================== filters for summary tiles and charts ====================== -->
                    </div>

                </div>

                <!-- ====================== SUMMARY TILES ====================== -->
                <div class="summary-tiles">
                    <!-- total medicines -->
                    <!-- total units -->
                    <!-- expired units -->
                    <!-- disposed units -->
                    <!-- low stock medicine -->
                    <!-- out of stock medicine -->
                </div>

                <!-- ====================== CHARTS ====================== -->
                
            </div>
        </div>



        <!-- ====================== SCRIPTS ====================== -->
        <script>

        </script>
    </body>
    </html>