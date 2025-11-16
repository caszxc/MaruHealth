<?php
// patient_stats.php
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

// ---------------------------------------------------------------------
// 2. FETCH PATIENT STATISTICS
// ---------------------------------------------------------------------
try {
    // Total Patients
    $total_patients = $conn->query("SELECT COUNT(*) FROM patients WHERE status = 'active'")->fetchColumn();

    // Total Archived Patients
    $archived_patients = $conn->query("SELECT COUNT(*) FROM patients WHERE status = 'archived'")->fetchColumn();

    // Gender Distribution
    $gender_data = $conn->query("
        SELECT sex, COUNT(*) as count 
        FROM patients 
        WHERE status = 'active' 
        GROUP BY sex
    ")->fetchAll(PDO::FETCH_ASSOC);

    // BMI Status Distribution
    $bmi_data = $conn->query("
        SELECT bmi_status, COUNT(*) as count 
        FROM patients 
        WHERE status = 'active' AND bmi_status IS NOT NULL
        GROUP BY bmi_status
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Age Group Distribution
    $age_groups = $conn->query("
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 1 THEN 'Infant (0-1)'
                WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 1 AND 12 THEN 'Child (1-12)'
                WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 13 AND 17 THEN 'Teen (13-17)'
                WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 18 AND 59 THEN 'Adult (18-59)'
                ELSE 'Senior (60+)'
            END as age_group,
            COUNT(*) as count
        FROM patients 
        WHERE status = 'active'
        GROUP BY age_group
        ORDER BY 
            FIELD(age_group, 'Infant (0-1)', 'Child (1-12)', 'Teen (13-17)', 'Adult (18-59)', 'Senior (60+)')
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Consultation Trends (Last 6 Months)
    $consultation_trend = $conn->query("
        SELECT DATE_FORMAT(consultation_date, '%Y-%m') as month, COUNT(*) as consultations
        FROM consultations
        WHERE consultation_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Top 5 Most Frequent Consultation Reasons
    $top_reasons = $conn->query("
        SELECT reason_for_consultation, COUNT(*) as frequency
        FROM consultations
        GROUP BY reason_for_consultation
        ORDER BY frequency DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Patients by Family (Top 5 Largest Families)
    $top_families = $conn->query("
        SELECT family_number, COUNT(*) as members
        FROM patients
        WHERE status = 'active'
        GROUP BY family_number
        ORDER BY members DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    error_log("Patient Stats Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Statistics</title>
    <link rel="stylesheet" href="css/patient_stats.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="<?= $logo_url ?>" type="image/x-icon">
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
                <a href="<?= htmlspecialchars($dashboard_url) ?>" class="<?= $current_page == 'patient_stats.php' ? 'active' : '' ?>">Dashboard</a>
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
                <a href="<?= $dashboard_url ?>" class="back-button">← Back</a>
                <h2>Patient Statistics</h2>
            </div>
            <small class="stat-desc">Last updated: <?= date('M d, Y h:i A') ?></small>
        </div>

        <div class="stats-container">
            <div class="stats-grid">
                <div class="stat-card card-total">
                    <div>
                        <div class="stat-title">Total Active Patients</div>
                        <div class="stat-value"><?= number_format($total_patients) ?></div>
                        <small class="stat-desc">Currently registered</small>
                    </div>
                    <i class="fas fa-users stat-icon" style="color: #0d6efd;"></i>
                </div>

                <div class="stat-card card-archived">
                    <div>
                        <div class="stat-title">Archived Patients</div>
                        <div class="stat-value"><?= number_format($archived_patients) ?></div>
                        <small class="stat-desc">Inactive records</small>
                    </div>
                    <i class="fas fa-archive stat-icon" style="color: #6c757d;"></i>
                </div>

                <div class="stat-card card-consultations">
                    <div>
                        <div class="stat-title">Total Consultations</div>
                        <div class="stat-value"><?= number_format($conn->query("SELECT COUNT(*) FROM consultations")->fetchColumn()) ?></div>
                        <small class="stat-desc">All time</small>
                    </div>
                    <i class="fas fa-stethoscope stat-icon" style="color: #198754;"></i>
                </div>

                <div class="stat-card card-families">
                    <div>
                        <div class="stat-title">Registered Families</div>
                        <div class="stat-value"><?= number_format($conn->query("SELECT COUNT(DISTINCT family_number) FROM patients WHERE family_number != '' AND family_number IS NOT NULL")->fetchColumn()) ?></div>
                        <small class="stat-desc">Unique family numbers</small>
                    </div>
                    <i class="fas fa-home stat-icon" style="color: #fd7e14;"></i>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <div class="chart-container">
                    <h5><i class="fas fa-venus-mars"></i> Gender Distribution</h5>
                    <canvas id="genderChart"></canvas>
                </div>

                <div class="chart-container">
                    <h5><i class="fas fa-weight"></i> BMI Status Distribution</h5>
                    <canvas id="bmiChart"></canvas>
                </div>

                <div class="chart-container">
                    <h5><i class="fas fa-child"></i> Age Group Distribution</h5>
                    <canvas id="ageGroupChart"></canvas>
                </div>

                <div class="chart-container full-width">
                    <h5><i class="fas fa-chart-line"></i> Consultation Trend (Last 6 Months)</h5>
                    <canvas id="consultationTrendChart"></canvas>
                </div>

                <div class="chart-container">
                    <h5><i class="fas fa-clipboard-list"></i> Top 5 Consultation Reasons</h5>
                    <canvas id="topReasonsChart"></canvas>
                </div>

                <div class="chart-container">
                    <h5><i class="fas fa-users"></i> Top 5 Largest Families</h5>
                    <canvas id="topFamiliesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================== CHARTS SCRIPT ====================== -->
    <script>
        // Gender Pie Chart
        new Chart(document.getElementById('genderChart'), {
            type: 'doughnut',
            data: {
                labels: [<?php foreach($gender_data as $g) echo "'".$g['sex']."',"; ?>],
                datasets: [{
                    data: [<?php foreach($gender_data as $g) echo $g['count'].","; ?>],
                    backgroundColor: ['#ff6b6b', '#4ecdc4', '#a29bfe'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });

        // BMI Status
        new Chart(document.getElementById('bmiChart'), {
            type: 'pie',
            data: {
                labels: [<?php foreach($bmi_data as $b) echo "'".$b['bmi_status']."',"; ?>],
                datasets: [{
                    data: [<?php foreach($bmi_data as $b) echo $b['count'].","; ?>],
                    backgroundColor: ['#51cf66', '#339af0', '#ff922b', '#ff6b6b'],
                    borderWidth: 2
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });

        // Age Groups
        new Chart(document.getElementById('ageGroupChart'), {
            type: 'bar',
            data: {
                labels: [<?php foreach($age_groups as $ag) echo "'".$ag['age_group']."',"; ?>],
                datasets: [{
                    label: 'Number of Patients',
                    data: [<?php foreach($age_groups as $ag) echo $ag['count'].","; ?>],
                    backgroundColor: '#0d6efd',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Consultation Trend
        const months = <?php
            $labels = [];
            for($i = 5; $i >= 0; $i--) {
                $labels[] = date('M Y', strtotime("-$i month"));
            }
            echo json_encode($labels);
        ?>;
        const trendData = <?php
            $data = array_fill(0, 6, 0);
            foreach($consultation_trend as $row) {
                $diff = date_diff(date_create($row['month'].'-01'), date_create(date('Y-m-01')))->m;
                if ($diff <= 5) $data[5 - $diff] = (int)$row['consultations'];
            }
            echo json_encode($data);
        ?>;
        new Chart(document.getElementById('consultationTrendChart'), {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Consultations',
                    data: trendData,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Top Reasons
        new Chart(document.getElementById('topReasonsChart'), {
            type: 'bar',
            data: {
                labels: [<?php foreach($top_reasons as $r) echo "'".addslashes(substr($r['reason_for_consultation'], 0, 30)).(strlen($r['reason_for_consultation']) > 30 ? '...' : '')."',"; ?>],
                datasets: [{
                    label: 'Frequency',
                    data: [<?php foreach($top_reasons as $r) echo $r['frequency'].","; ?>],
                    backgroundColor: '#ff6b6b'
                }]
            },
            options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } } }
        });

        // Top Families
        new Chart(document.getElementById('topFamiliesChart'), {
            type: 'bar',
            data: {
                labels: [<?php foreach($top_families as $f) echo "'Family ".$f['family_number']."',"; ?>],
                datasets: [{
                    label: 'Members',
                    data: [<?php foreach($top_families as $f) echo $f['members'].","; ?>],
                    backgroundColor: '#51cf66'
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });
    </script>

</body>
</html>