<?php
session_start();
require_once "config.php";

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Initialize filters
$period = isset($_GET['period']) ? $_GET['period'] : 'all';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Fetch admin/staff info
$adminId = $_SESSION['admin_id'];
$adminStmt = $conn->prepare("SELECT * FROM admin_staff WHERE id = :id");
$adminStmt->bindParam(':id', $adminId);
$adminStmt->execute();
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
$adminName = $admin ? $admin['full_name'] : $_SESSION['admin_name'];
$adminRole = $admin ? $admin['role'] : $_SESSION['admin_role'];
$displayRole = ucwords(str_replace('_', ' ', $adminRole));

// Handle date range based on period selection
switch ($period) {
    case 'today':
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
        break;
    case 'this_week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'this_month':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        break;
    case 'this_year':
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');
        break;
    case 'custom':
        // startDate and endDate already set from GET parameters
        break;
    case 'all':
    default:
        $startDate = '';
        $endDate = '';
        break;
}

// Function to count patients
function countPatients($conn, $startDate = '', $endDate = '') {
    try {
        $sql = "SELECT COUNT(*) as count FROM patients";
        $params = [];
        
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " WHERE created_at BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

// Function to count consultations
function countConsultations($conn, $startDate = '', $endDate = '') {
    try {
        $sql = "SELECT COUNT(*) as count FROM consultations";
        $params = [];
        
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " WHERE consultation_date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate;
            $params[':end_date'] = $endDate;
        }
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

// Function to get gender distribution
function getGenderDistribution($conn, $startDate = '', $endDate = '') {
    try {
        $sql = "SELECT sex as gender, COUNT(*) as count FROM patients";
        $params = [];
        
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " WHERE created_at BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        
        $sql .= " GROUP BY sex";
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Function to get BMI status distribution
function getBmiDistribution($conn, $startDate = '', $endDate = '') {
    try {
        $sql = "SELECT bmi_status, COUNT(*) as count FROM patients WHERE bmi_status IS NOT NULL";
        $params = [];
        
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " AND created_at BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        
        $sql .= " GROUP BY bmi_status";
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Function to get age group distribution
function getAgeDistribution($conn, $startDate = '', $endDate = '') {
    try {
        $sql = "SELECT 
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 18 THEN 'Under 18'
                        WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 18 AND 24 THEN '18-24'
                        WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 25 AND 34 THEN '25-34'
                        WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 35 AND 44 THEN '35-44'
                        WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 45 AND 54 THEN '45-54'
                        WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) BETWEEN 55 AND 64 THEN '55-64'
                        ELSE '65+' 
                    END AS age_group,
                    COUNT(*) as count
                FROM patients";
        
        $params = [];
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " WHERE created_at BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        
        $sql .= " GROUP BY age_group ORDER BY 
                    CASE age_group
                        WHEN 'Under 18' THEN 1
                        WHEN '18-24' THEN 2
                        WHEN '25-34' THEN 3
                        WHEN '35-44' THEN 4
                        WHEN '45-54' THEN 5
                        WHEN '55-64' THEN 6
                        WHEN '65+' THEN 7
                    END";
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Function to get monthly consultation data
function getMonthlyConsultations($conn, $year = null) {
    if ($year === null) {
        $year = date('Y');
    }
    
    try {
        $sql = "SELECT 
                    MONTH(consultation_date) as month, 
                    COUNT(*) as count 
                FROM consultations 
                WHERE YEAR(consultation_date) = :year 
                GROUP BY MONTH(consultation_date)
                ORDER BY month";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':year', $year);
        $stmt->execute();
        
        // Initialize all months with zero counts
        $monthlyData = array_fill(1, 12, 0);
        
        // Fill in actual data
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $monthlyData[$row['month']] = (int)$row['count'];
        }
        
        return $monthlyData;
    } catch (PDOException $e) {
        return array_fill(1, 12, 0);
    }
}

// Function to get patient list for table
function getPatientList($conn, $startDate = '', $endDate = '', $limit = 10, $offset = 0) {
    try {
        $sql = "SELECT id, family_number, first_name, last_name, sex, birthdate, bmi_status, created_at 
                FROM patients";
        $params = [];
        
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " WHERE created_at BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Get statistics
$totalPatients = countPatients($conn, $startDate, $endDate);
$totalConsultations = countConsultations($conn, $startDate, $endDate);
$genderDistribution = getGenderDistribution($conn, $startDate, $endDate);
$bmiDistribution = getBmiDistribution($conn, $startDate, $endDate);
$ageDistribution = getAgeDistribution($conn, $startDate, $endDate);
$monthlyConsultations = getMonthlyConsultations($conn);

// Process gender distribution for chart
$genderLabels = [];
$genderData = [];
foreach ($genderDistribution as $item) {
    $genderLabels[] = $item['gender'];
    $genderData[] = (int)$item['count'];
}

// Process BMI distribution for chart
$bmiLabels = [];
$bmiData = [];
foreach ($bmiDistribution as $item) {
    $bmiLabels[] = $item['bmi_status'];
    $bmiData[] = (int)$item['count'];
}

// Process age distribution for chart
$ageLabels = [];
$ageData = [];
foreach ($ageDistribution as $item) {
    $ageLabels[] = $item['age_group'];
    $ageData[] = (int)$item['count'];
}


// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="patient_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    $allPatients = getPatientList($conn, $startDate, $endDate, 100000, 0);
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Patient Report</title>
    </head>
    <body>
        <table border="1">
            <thead>
                <tr>
                    <th colspan="7">Maru-Health Patient Report - ' . ($period != 'custom' ? ucfirst(str_replace('_', ' ', $period)) : date('M d, Y', strtotime($startDate)) . ' to ' . date('M d, Y', strtotime($endDate))) . '</th>
                </tr>
                <tr>
                    <th>ID</th>
                    <th>Family Number</th>
                    <th>Name</th>
                    <th>Sex</th>
                    <th>Birthdate</th>
                    <th>BMI Status</th>
                    <th>Registration Date</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($allPatients as $patient) {
        echo '<tr>
                <td>' . $patient['id'] . '</td>
                <td>' . htmlspecialchars($patient['family_number']) . '</td>
                <td>' . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . '</td>
                <td>' . htmlspecialchars($patient['sex']) . '</td>
                <td>' . date('M d, Y', strtotime($patient['birthdate'])) . '</td>
                <td>' . htmlspecialchars($patient['bmi_status'] ?: 'N/A') . '</td>
                <td>' . date('M d, Y', strtotime($patient['created_at'])) . '</td>
            </tr>';
    }
    
    echo '</tbody>
        </table>
    </body>
    </html>';
    
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Statistics - Admin Dashboard</title>
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/dashboard_icon_active.png" alt="">
                <a href="<?= htmlspecialchars($dashboard_url) ?>" class="<?= $current_page == 'patient_stats.php' ? 'active' : '' ?>">Dashboard</a>
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
            <div style="display: flex; gap: 15px; align-items: center;">
                <a href="#" class="back-button" onclick="history.back(); return false;">← Back</a>
                <h2>Patient Statistics</h2>
            </div>
            <div class="stats-actions">
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="export-btn">
                    Export to Excel
                </a>
            </div>
        </div>
        
        <div class="stats-header">
            <div class="filter-form">
                <form id="periodForm" method="GET" action="">
                    <label for="period">Time Period:</label>
                    <select name="period" id="period" onchange="toggleCustomDate()">
                        <option value="all" <?= $period == 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="today" <?= $period == 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="this_week" <?= $period == 'this_week' ? 'selected' : '' ?>>This Week</option>
                        <option value="this_month" <?= $period == 'this_month' ? 'selected' : '' ?>>This Month</option>
                        <option value="this_year" <?= $period == 'this_year' ? 'selected' : '' ?>>This Year</option>
                        <option value="custom" <?= $period == 'custom' ? 'selected' : '' ?>>Custom Date Range</option>
                    </select>
                    
                    <div id="custom-date-container" style="<?= $period == 'custom' ? 'display: flex;' : '' ?> display: none;">
                        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                        <span>-</span>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                    
                    <button type="submit" class="generate-btn">Apply Filter</button>
                </form>
            </div>
        </div>
        
        <div class="summary-tiles">
            <div class="summary-tile">
                <h3>Total Patients</h3>
                <div class="number"><?= number_format($totalPatients) ?></div>
            </div>
            <div class="summary-tile">
                <h3>Total Consultations</h3>
                <div class="number"><?= number_format($totalConsultations) ?></div>
            </div>
        </div>
        
        <div class="chart-row">
            <div class="chart-container">
                <h3>Gender Distribution</h3>
                <canvas id="genderChart"></canvas>
            </div>
            <div class="chart-container">
                <h3>Age Distribution</h3>
                <canvas id="ageChart"></canvas>
            </div>
        </div>
        
        <div class="chart-row">
            <div class="chart-container">
                <h3>BMI Status Distribution</h3>
                <canvas id="bmiChart"></canvas>
            </div>
            <div class="chart-container">
                <h3>Monthly Consultations (<?= date('Y') ?>)</h3>
                <canvas id="consultationChart"></canvas>
            </div>
        </div>
        
        
    </div>

    <script>
        function toggleCustomDate() {
            const periodSelect = document.getElementById('period');
            const customDateContainer = document.getElementById('custom-date-container');
            
            if (periodSelect.value === 'custom') {
                customDateContainer.style.display = 'flex';
            } else {
                customDateContainer.style.display = 'none';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Gender distribution chart
            const genderCtx = document.getElementById('genderChart');
            if (genderCtx) {
                const genderChart = new Chart(genderCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode($genderLabels) ?>,
                        datasets: [{
                            data: <?= json_encode($genderData) ?>,
                            backgroundColor: [
                                '#8B0000',
                                '#E57373',
                                '#FF9800'
                            ],
                            borderColor: '#ffffff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    font: {
                                        size: 14
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.formattedValue;
                                        const dataset = context.dataset;
                                        const total = dataset.data.reduce((acc, data) => acc + data, 0);
                                        const percentage = Math.round((context.raw / total) * 100);
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Age distribution chart
            const ageCtx = document.getElementById('ageChart');
            if (ageCtx) {
                const ageChart = new Chart(ageCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($ageLabels) ?>,
                        datasets: [{
                            label: 'Number of Patients',
                            data: <?= json_encode($ageData) ?>,
                            backgroundColor: [
                                'rgba(139, 0, 0, 0.7)',
                                'rgba(165, 42, 42, 0.7)',
                                'rgba(178, 34, 34, 0.7)',
                                'rgba(220, 20, 60, 0.7)',
                                'rgba(255, 0, 0, 0.7)',
                                'rgba(255, 99, 71, 0.7)',
                                'rgba(255, 127, 80, 0.7)'
                            ],
                            borderColor: '#8B0000',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
            
            // BMI distribution chart
            const bmiCtx = document.getElementById('bmiChart');
            if (bmiCtx) {
                const bmiChart = new Chart(bmiCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode($bmiLabels) ?>,
                        datasets: [{
                            data: <?= json_encode($bmiData) ?>,
                            backgroundColor: [
                                '#4CAF50',
                                '#2196F3',
                                '#FFC107',
                                '#F44336'
                            ],
                            borderColor: '#ffffff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    font: {
                                        size: 14
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.formattedValue;
                                        const dataset = context.dataset;
                                        const total = dataset.data.reduce((acc, data) => acc + data, 0);
                                        const percentage = Math.round((context.raw / total) * 100);
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Monthly consultations chart
            const consultationCtx = document.getElementById('consultationChart');
            if (consultationCtx) {
                const consultationChart = new Chart(consultationCtx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                        datasets: [{
                            label: 'Consultations',
                            data: <?= json_encode(array_values($monthlyConsultations)) ?>,
                            backgroundColor: 'rgba(165, 42, 42, 0.2)',
                            borderColor: '#8B0000',
                            borderWidth: 3,
                            tension: 0.3,
                            fill: true,
                            pointBackgroundColor: '#8B0000',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleFont: {
                                    size: 14
                                },
                                bodyFont: {
                                    size: 14
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>