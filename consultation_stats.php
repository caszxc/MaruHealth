<?php
// patient_stats.php
session_start();
require_once "config.php";
include 'settings.php';

// ---------------------------------------------------------------------
// 1. AUTHENTICATION
// ---------------------------------------------------------------------
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin', 'health_staff'])) {
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
// 2. FETCH PATIENT STATISTICS
// ---------------------------------------------------------------------
try {
    $today = date('Y-m-d');
    $this_month = date('Y-m');
    $last_month = date('Y-m', strtotime('-1 month'));

    // Key Metrics
    $total_consultations     = $conn->query("SELECT COUNT(*) FROM consultations")->fetchColumn();
    $today_consultations     = $conn->query("SELECT COUNT(*) FROM consultations WHERE DATE(consultation_date) = '$today'")->fetchColumn();
    $this_month_count        = $conn->query("SELECT COUNT(*) FROM consultations WHERE DATE_FORMAT(consultation_date, '%Y-%m') = '$this_month'")->fetchColumn();
    $last_month_count        = $conn->query("SELECT COUNT(*) FROM consultations WHERE DATE_FORMAT(consultation_date, '%Y-%m') = '$last_month'")->fetchColumn();
    $growth_rate = $last_month_count > 0 ? round((($this_month_count - $last_month_count) / $last_month_count) * 100, 1) : 0;
    $growth_class = $growth_rate >= 0 ? 'positive' : 'negative';

    // Daily average this month
    $days_in_month = date('j');
    $daily_avg = $days_in_month > 0 ? round($this_month_count / $days_in_month, 1) : 0;

    // Consultation Type Breakdown
    $type_breakdown = $conn->query("
        SELECT consultation_type, COUNT(*) as count 
        FROM consultations 
        GROUP BY consultation_type 
        ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Top 10 Diagnoses
    $top_diagnoses = $conn->query("
        SELECT diagnosis, COUNT(*) as freq 
        FROM consultations 
        GROUP BY diagnosis 
        ORDER BY freq DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Top 10 Prescribed Medicines (from text field)
    $top_meds = $conn->query("
        SELECT TRIM(SUBSTRING_INDEX(prescribed_medicine, ',', 1)) as med,
               COUNT(*) as freq
        FROM consultations 
        WHERE prescribed_medicine IS NOT NULL AND prescribed_medicine != ''
        GROUP BY med
        ORDER BY freq DESC LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Monthly Trend (Last 12 months)
    $trend_query = $conn->query("
        SELECT DATE_FORMAT(consultation_date, '%Y-%m') as month,
               COUNT(*) as total
        FROM consultations
        WHERE consultation_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Prepare trend data
    $trend_labels = [];
    $trend_data   = [];
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i month"));
        $trend_labels[] = date('M Y', strtotime("-$i month"));
        $found = false;
        foreach ($trend_query as $row) {
            if ($row['month'] == $month) {
                $trend_data[] = (int)$row['total'];
                $found = true;
                break;
            }
        }
        if (!$found) $trend_data[] = 0;
    }

    // Busiest Day of Week
    $day_of_week = $conn->query("
        SELECT DAYNAME(consultation_date) as day, COUNT(*) as count
        FROM consultations
        GROUP BY DAYOFWEEK(consultation_date), day
        ORDER BY count DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    // Staff Performance (who recorded the most consultations)
    $staff_performance = $conn->query("
        SELECT consulting_physician_nurse as staff, COUNT(*) as consultations
        FROM consultations
        WHERE consulting_physician_nurse IS NOT NULL AND consulting_physician_nurse != ''
        GROUP BY consulting_physician_nurse
        ORDER BY consultations DESC LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    error_log("Consultation Stats Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Statistics | MaruHealth</title>
    <link rel="stylesheet" href="css/consultation_stats.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body>

    <!-- ====================== NAVBAR & SIDEBAR ====================== -->
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
                <p class="admin_name"><strong><?= htmlspecialchars($adminName) ?></strong></p>
                <p class="role"><?= htmlspecialchars($displayRole) ?></p>
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
                <a href="<?= htmlspecialchars($dashboard_url) ?>" class="<?= $current_page == 'consultation_stats.php' ? 'active' : '' ?>">Dashboard</a>
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
                <a href="<?= $dashboard_url ?>" class="back-button">Back</a>
                <h2>Consultation Statistics</h2>
            </div>
            <small class="stat-desc">Last updated: <?= date('M d, Y h:i A') ?></small>
        </div>

        <div class="stats-container">
            <div class="stats-grid">
                <div class="stat-card card-total">
                    <div>
                        <div class="stat-title">Total Consultations</div>
                        <div class="stat-value"><?= number_format($total_consultations) ?></div>
                        <small class="stat-desc">All time recorded</small>
                    </div>
                    <i class="fas fa-stethoscope stat-icon" style="color: #198754;"></i>
                </div>

                <div class="stat-card card-today">
                    <div>
                        <div class="stat-title">Today's Consultations</div>
                        <div class="stat-value"><?= number_format($today_consultations) ?></div>
                        <small class="stat-desc"><?= date('l, M j') ?></small>
                    </div>
                    <i class="fas fa-calendar-day stat-icon" style="color: #0d6efd;"></i>
                </div>

                <div class="stat-card card-month">
                    <div>
                        <div class="stat-title">This Month</div>
                        <div class="stat-value"><?= number_format($this_month_count) ?></div>
                        <small class="stat-desc <?= $growth_class ?>">
                            <?= $growth_rate >= 0 ? '+' : '' ?><?= $growth_rate ?>% vs last month
                        </small>
                    </div>
                    <i class="fas fa-chart-line stat-icon" style="color: #fd7e14;"></i>
                </div>

                <div class="stat-card card-avg">
                    <div>
                        <div class="stat-title">Daily Average</div>
                        <div class="stat-value"><?= $daily_avg ?></div>
                        <small class="stat-desc">consultations per day (<?= date('F') ?>)</small>
                    </div>
                    <i class="fas fa-tachometer-alt stat-icon" style="color: #6f42c1;"></i>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <div class="chart-container full-width">
                    <h5>Consultation Trend (Last 12 Months)</h5>
                    <canvas id="trendChart"></canvas>
                </div>

                <div class="chart-container">
                    <h5>Consultation Types</h5>
                    <canvas id="typeChart"></canvas>
                </div>

                <div class="chart-container">
                    <h5>Top 10 Diagnoses</h5>
                    <canvas id="diagnosisChart"></canvas>
                </div>

                <div class="chart-container">
                    <h5>Top Prescribed Medicines</h5>
                    <canvas id="medicineChart"></canvas>
                </div>

                <div class="chart-container">
                    <h5>Staff Performance</h5>
                    <canvas id="staffChart"></canvas>
                </div>

                <div class="chart-container highlight">
                    <h5>Busiest Day This Year</h5>
                    <div style="text-align:center; padding:20px 0; font-size:1.8rem; color:#0d6efd;">
                        <i class="fas fa-calendar-week fa-2x"></i><br><br>
                        <strong><?= $day_of_week['day'] ?? 'N/A' ?></strong>
                        <p style="font-size:1rem; margin-top:10px; color:#666;">
                            with <?= $day_of_week['count'] ?? 0 ?> consultations recorded
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- ====================== CHARTS SCRIPT ====================== -->
    <script>
        // 1. Trend Line Chart
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($trend_labels) ?>,
                datasets: [{
                    label: 'Consultations',
                    data: <?= json_encode($trend_data) ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // 2. Consultation Types (Doughnut)
        new Chart(document.getElementById('typeChart'), {
            type: 'doughnut',
            data: {
                labels: [<?= "'" . implode("','", array_column($type_breakdown, 'consultation_type')) . "'" ?>],
                datasets: [{
                    data: [<?= implode(',', array_column($type_breakdown, 'count')) ?>],
                    backgroundColor: ['#ff6b6b','#4ecdc4','#a29bfe','#ffe66d','#ff9ff3','#51cf66','#74c0fc','#ff8787']
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });

        // 3. Top Diagnoses (Horizontal Bar)
        new Chart(document.getElementById('diagnosisChart'), {
            type: 'bar',
            data: {
                labels: [<?= "'" . implode("','", array_map(fn($d) => substr($d['diagnosis'],0,30).(strlen($d['diagnosis'])>30?'...':''), $top_diagnoses)) . "'" ?>],
                datasets: [{
                    label: 'Frequency',
                    data: [<?= implode(',', array_column($top_diagnoses, 'freq')) ?>],
                    backgroundColor: '#e74c3c'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { display: false } }
            }
        });

        // 4. Top Medicines
        new Chart(document.getElementById('medicineChart'), {
            type: 'bar',
            data: {
                labels: [<?= "'" . implode("','", array_column($top_meds, 'med')) . "'" ?>],
                datasets: [{
                    label: 'Times Prescribed',
                    data: [<?= implode(',', array_column($top_meds, 'freq')) ?>],
                    backgroundColor: '#27ae60'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // 5. Staff Performance
        new Chart(document.getElementById('staffChart'), {
            type: 'bar',
            data: {
                labels: [<?= "'" . implode("','", array_column($staff_performance, 'staff')) . "'" ?>],
                datasets: [{
                    label: 'Consultations Handled',
                    data: [<?= implode(',', array_column($staff_performance, 'consultations')) ?>],
                    backgroundColor: '#9b59b6'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>