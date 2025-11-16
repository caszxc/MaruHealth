<?php
//user_stats.php
session_start();
require_once "config.php";
include 'settings.php';

// ---------------------------------------------------------------------
// 1. AUTHENTICATION
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
// 2. USER STATISTICS QUERIES
// ---------------------------------------------------------------------
try {
    // Total Registered Users (approved)
    $total_registered = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // Primary Accounts (no primary_user_id)
    $primary_accounts = $conn->query("SELECT COUNT(*) FROM users WHERE primary_user_id IS NULL")->fetchColumn();

    // Dependent Accounts
    $dependent_accounts = $conn->query("SELECT COUNT(*) FROM users WHERE primary_user_id IS NOT NULL")->fetchColumn();

    // Pending Registrations
    $pending_registrations = $conn->query("SELECT COUNT(*) FROM pending_users")->fetchColumn();

    // New This Month
    $this_month = date('Y-m-01 00:00:00');
    $new_this_month = $conn->prepare("SELECT COUNT(*) FROM users WHERE date_registered >= ?");
    $new_this_month->execute([$this_month]);
    $new_this_month = $new_this_month->fetchColumn();

    // Gender Distribution
    $gender_data = $conn->query("
        SELECT gender, COUNT(*) as count 
        FROM users 
        GROUP BY gender
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Age Group Distribution
    $age_groups = $conn->query("
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) < 18 THEN 'Below 18'
                WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 18 AND 35 THEN '18-35'
                WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 36 AND 55 THEN '36-55'
                ELSE '56+' 
            END as age_group,
            COUNT(*) as count
        FROM users
        GROUP BY age_group
        ORDER BY 
            FIELD(age_group, 'Below 18', '18-35', '36-55', '56+')
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Medicine Requests Trend (Last 6 Months)
    $request_trend = $conn->query("
        SELECT DATE_FORMAT(request_date, '%Y-%m') as month, COUNT(*) as requests
        FROM medicine_requests
        WHERE request_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month
    ")->fetchAll(PDO::FETCH_ASSOC);

    // User Registration Trend (Last 6 Months)
    $reg_trend = $conn->query("
        SELECT DATE_FORMAT(date_registered, '%Y-%m') as month, COUNT(*) as registrations
        FROM users
        WHERE date_registered >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    error_log("User Stats Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Statistics</title>
    <link rel="stylesheet" href="css/user_stats.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" href="<?= $logo_url ?>" type="image/x-icon">
</head>
<body>

    <!-- ====================== NAVBAR & SIDEBAR (Same as medicine_stats.php) ====================== -->
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
                <a href="<?=htmlspecialchars($dashboard_url)?>" class="<?= $current_page == 'user_stats.php' ? 'active' : '' ?>">Dashboard</a>
            </div>

            <p class="menu-header">BASE</p>
            <?php if ($adminRole == 'super_admin'): ?>
            <div class="menu-link"><img class="menu-icon" src="images/icons/account_approval_icon.png" alt=""><a href="manage_staff.php" class="<?= $current_page == 'manage_staff.php' ? 'active' : '' ?>">Admin Account Management</a></div>
            <div class="menu-link"><img class="menu-icon" src="images/icons/account_approval_icon.png" alt=""><a href="account_approval.php" class="<?= $current_page == 'account_approval.php' ? 'active' : '' ?>">User Account Management</a></div>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/settings_icon.png" alt="">
                <a href="system_settings.php" class="<?= $current_page == 'system_settings.php' ? 'active' : '' ?>">
                    System Settings
                </a>
            </div>
            <?php endif; ?>
            <?php if ($adminRole == 'admin'): ?>
            <div class="menu-link"><img class="menu-icon" src="images/icons/announcement_icon.png" alt=""><a href="announcements.php" class="<?= $current_page == 'announcements.php' ? 'active' : '' ?>">Announcement</a></div>
            <div class="menu-link"><img class="menu-icon" src="images/icons/calendar_icon.png" alt=""><a href="edit_calendar.php" class="<?= $current_page == 'edit_calendar.php' ? 'active' : '' ?>">Calendar</a></div>
            <div class="menu-link"><img class="menu-icon" src="images/icons/calendar_icon.png" alt=""><a href="service_management.php" class="<?= $current_page == 'service_management.php' ? 'active' : '' ?>">Service Management</a></div>
            <?php endif; ?>
            <?php if ($adminRole == 'health_staff'): ?>
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
                <a href="<?= $dashboard_url ?>" class="back-button">← Back</a>
                <h2>User Statistics</h2>
            </div>
            <small class="stat-desc">Last updated: <?= date('M d, Y h:i A') ?></small>
        </div>

        <div class="stats-container">
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card card-total">
                    <div>
                        <div class="stat-title">Total Registered Users</div>
                        <div class="stat-value"><?= number_format($total_registered) ?></div>
                        <small class="stat-desc">Primary and Dependents</small>
                    </div>
                    <i class="fas fa-users stat-icon" style="color: #0d6efd;"></i>
                </div>

                <div class="stat-card card-primary">
                    <div>
                        <div class="stat-title">Primary Accounts</div>
                        <div class="stat-value"><?= number_format($primary_accounts) ?></div>
                        <small class="stat-desc">Personal</small>
                    </div>
                    <i class="fas fa-user-check stat-icon" style="color: #6f42c1;"></i>
                </div>

                <div class="stat-card card-dependent">
                    <div>
                        <div class="stat-title">Dependent Accounts</div>
                        <div class="stat-value"><?= number_format($dependent_accounts) ?></div>
                        <small class="stat-desc">Dependent</small>
                    </div>
                    <i class="fas fa-user-friends stat-icon" style="color: #d63384;"></i>
                </div>

                <div class="stat-card card-pending">
                    <div>
                        <div class="stat-title">Pending Registrations</div>
                        <div class="stat-value"><?= number_format($pending_registrations) ?></div>
                        <small class="stat-desc">Awaiting approval</small>
                    </div>
                    <i class="fas fa-clock stat-icon" style="color: #fd7e14;"></i>
                </div>

                <div class="stat-card card-new">
                    <div>
                        <div class="stat-title">New This Month</div>
                        <div class="stat-value"><?= number_format($new_this_month) ?></div>
                        <small class="stat-desc"><?= date('F Y') ?></small>
                    </div>
                    <i class="fas fa-user-plus stat-icon" style="color: #198754;"></i>
                </div>
            </div>

            <!-- Charts Grid -->
            <div class="charts-grid">
                <div class="chart-container">
                    <h5><i class="fas fa-venus-mars"></i> Gender Distribution</h5>
                    <canvas id="genderChart"></canvas>
                </div>

                <div class="chart-container">
                    <h5><i class="fas fa-birthday-cake"></i> Age Group Distribution</h5>
                    <canvas id="ageGroupChart"></canvas>
                </div>

                <div class="chart-container full-width">
                    <h5><i class="fas fa-chart-line"></i> User Registration Trend (Last 6 Months)</h5>
                    <canvas id="registrationTrendChart"></canvas>
                </div>

                <div class="chart-container full-width">
                    <h5><i class="fas fa-prescription-bottle"></i> Medicine Requests Trend (Last 6 Months)</h5>
                    <canvas id="requestTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================== CHART.JS SCRIPTS ====================== -->
    <script>
        // Gender Distribution
        new Chart(document.getElementById('genderChart'), {
            type: 'doughnut',
            data: {
                labels: [<?php foreach($gender_data as $g) echo "'".$g['gender']."',"; ?>],
                datasets: [{
                    data: [<?php foreach($gender_data as $g) echo $g['count'].","; ?>],
                    backgroundColor: ['#e91e63', '#2196f3'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Age Group Distribution
        new Chart(document.getElementById('ageGroupChart'), {
            type: 'bar',
            data: {
                labels: [<?php foreach($age_groups as $a) echo "'".$a['age_group']."',"; ?>],
                datasets: [{
                    label: 'Number of Users',
                    data: [<?php foreach($age_groups as $a) echo $a['count'].","; ?>],
                    backgroundColor: ['#ff9800', '#4caf50', '#2196f3', '#9c27b0'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Registration Trend
        const regLabels = <?php
            $labels = [];
            for($i = 5; $i >= 0; $i--) {
                $labels[] = date('M Y', strtotime("-$i month"));
            }
            echo json_encode($labels);
        ?>;
        const regData = <?php
            $data = array_fill(0, 6, 0);
            foreach($reg_trend as $row) {
                $month = date('Y-m', strtotime($row['month']));
                $diff = date_diff(date_create($month), date_create(date('Y-m')))->m;
                if ($diff <= 5) $data[5 - $diff] = (int)$row['registrations'];
            }
            echo json_encode($data);
        ?>;

        new Chart(document.getElementById('registrationTrendChart'), {
            type: 'line',
            data: {
                labels: regLabels,
                datasets: [{
                    label: 'New Registrations',
                    data: regData,
                    borderColor: '#2196f3',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#2196f3',
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Medicine Requests Trend
        const reqData = <?php
            $data = array_fill(0, 6, 0);
            foreach($request_trend as $row) {
                $month = date('Y-m', strtotime($row['month']));
                $diff = date_diff(date_create($month), date_create(date('Y-m')))->m;
                if ($diff <= 5) $data[5 - $diff] = (int)$row['requests'];
            }
            echo json_encode($data);
        ?>;

        new Chart(document.getElementById('requestTrendChart'), {
            type: 'line',
            data: {
                labels: regLabels,
                datasets: [{
                    label: 'Medicine Requests',
                    data: reqData,
                    borderColor: '#e91e63',
                    backgroundColor: 'rgba(233, 30, 99, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#e91e63',
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>

</body>
</html>