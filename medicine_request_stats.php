<?php
// medicine_request_stats.php
session_start();
require_once "config.php";
include 'settings.php';

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header("Location: healthstaff_dashboard.php");
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
        break;
    case 'all':
    default:
        $startDate = '';
        $endDate = '';
        break;
}

// === FUNCTIONS (unchanged except getRequestList removes LIMIT) ===

function countMedicineRequests($conn, $startDate = '', $endDate = '') {
    try {
        $sql = "SELECT COUNT(*) as count FROM medicine_requests";
        $params = [];
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " WHERE request_date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

function countRequestedMedicines($conn, $startDate = '', $endDate = '') {
    try {
        $sql = "SELECT COUNT(*) as count FROM requested_medicines rm 
                JOIN medicine_requests mr ON rm.request_id = mr.id";
        $params = [];
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " WHERE mr.request_date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

function countPendingRequests($conn, $startDate = '', $endDate = '') {
    try {
        $sql = "SELECT COUNT(*) as count FROM medicine_requests 
                WHERE request_status IN ('requested', 'pending')";
        $params = [];
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " AND request_date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

function countApprovedRequests($conn, $startDate = '', $endDate = '') {
    try {
        $sql = "SELECT COUNT(*) as count FROM medicine_requests 
                WHERE request_status = 'claimed'";
        $params = [];
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " AND request_date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

function countDeclinedRequests($conn, $startDate = '', $endDate = '') {
    try {
        $sql = "SELECT COUNT(*) as count FROM medicine_requests 
                WHERE request_status = 'declined'";
        $params = [];
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " AND request_date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

function getRequestStatusDistribution($conn, $startDate = '', $endDate = '') {
    try {
        $sql = "SELECT request_status, COUNT(*) as count FROM medicine_requests";
        $params = [];
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " WHERE request_date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        $sql .= " GROUP BY request_status";
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getRequestedMedicinesStatusDistribution($conn, $startDate = '', $endDate = '') {
    try {
        $sql = "SELECT rm.status, COUNT(*) as count 
                FROM requested_medicines rm
                JOIN medicine_requests mr ON rm.request_id = mr.id";
        $params = [];
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " WHERE mr.request_date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        $sql .= " GROUP BY rm.status";
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getTopRequestedMedicines($conn, $startDate = '', $endDate = '', $limit = 5) {
    try {
        $sql = "SELECT rm.medicine_name, SUM(rm.quantity) as total_quantity
                FROM requested_medicines rm
                JOIN medicine_requests mr ON rm.request_id = mr.id";
        $params = [];
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " WHERE mr.request_date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        $sql .= " GROUP BY rm.medicine_name
                  ORDER BY total_quantity DESC
                  LIMIT :limit";
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getMonthlyRequests($conn, $year = null) {
    if ($year === null) $year = date('Y');
    try {
        $sql = "SELECT MONTH(request_date) as month, COUNT(*) as count 
                FROM medicine_requests 
                WHERE YEAR(request_date) = :year 
                GROUP BY MONTH(request_date)
                ORDER BY month";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':year', $year);
        $stmt->execute();
        $monthlyData = array_fill(1, 12, 0);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $monthlyData[$row['month']] = (int)$row['count'];
        }
        return $monthlyData;
    } catch (PDOException $e) {
        return array_fill(1, 12, 0);
    }
}

// UPDATED: getRequestList() — NO LIMIT, NO OFFSET
function getRequestList($conn, $startDate = '', $endDate = '') {
    try {
        $sql = "SELECT mr.id, mr.full_name, mr.gender, mr.request_status, mr.request_date,
                       GROUP_CONCAT(CONCAT(rm.medicine_name, ' (Qty: ', rm.quantity, ', Status: ', rm.status, ')') SEPARATOR ', ') as requested_medicines
                FROM medicine_requests mr
                LEFT JOIN requested_medicines rm ON mr.id = rm.request_id";
        $params = [];
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " WHERE mr.request_date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        $sql .= " GROUP BY mr.id ORDER BY mr.request_date DESC";
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// === Fetch Data ===
$totalRequests = countMedicineRequests($conn, $startDate, $endDate);
$totalRequestedMedicines = countRequestedMedicines($conn, $startDate, $endDate);
$pendingRequests = countPendingRequests($conn, $startDate, $endDate);
$approvedRequests = countApprovedRequests($conn, $startDate, $endDate);
$declinedRequests = countDeclinedRequests($conn, $startDate, $endDate);
$requestStatusDistribution = getRequestStatusDistribution($conn, $startDate, $endDate);
$requestedMedicinesStatusDistribution = getRequestedMedicinesStatusDistribution($conn, $startDate, $endDate);
$topRequestedMedicines = getTopRequestedMedicines($conn, $startDate, $endDate);
$monthlyRequests = getMonthlyRequests($conn);

// Process chart data
$requestStatusLabels = array_map('ucfirst', array_column($requestStatusDistribution, 'request_status'));
$requestStatusData = array_column($requestStatusDistribution, 'count');

$requestedMedicinesStatusLabels = array_map('ucfirst', array_column($requestedMedicinesStatusDistribution, 'status'));
$requestedMedicinesStatusData = array_column($requestedMedicinesStatusDistribution, 'count');

$topMedicinesLabels = array_column($topRequestedMedicines, 'medicine_name');
$topMedicinesData = array_column($topRequestedMedicines, 'total_quantity');


// Fetch ALL requests (no pagination)
$requestList = getRequestList($conn, $startDate, $endDate);

// Handle Excel export (uses all data)
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="medicine_request_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    echo '<!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Report</title></head><body>
    <table border="1">
        <tr><th colspan="5">Medicine Request Report - ' . ($period != 'custom' ? ucfirst(str_replace('_', ' ', $period)) : date('M d, Y', strtotime($startDate)) . ' to ' . date('M d, Y', strtotime($endDate))) . '</th></tr>
        <tr><th>ID</th><th>Full Name</th><th>Gender</th><th>Status</th><th>Medicines</th></tr>';

    foreach ($requestList as $request) {
        echo '<tr>
            <td>' . $request['id'] . '</td>
            <td>' . htmlspecialchars($request['full_name']) . '</td>
            <td>' . htmlspecialchars($request['gender']) . '</td>
            <td>' . htmlspecialchars($request['request_status']) . '</td>
            <td>' . htmlspecialchars($request['requested_medicines'] ?: 'None') . '</td>
        </tr>';
    }

    echo '</table></body></html>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Request Statistics</title>
    <link rel="stylesheet" href="css/stats.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- NAV & SIDEBAR (unchanged) -->
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

            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/dashboard_icon_active.png" alt="">
                <a href="<?= htmlspecialchars($dashboard_url) ?>" class="<?= $current_page == 'medicine_request_stats.php' ? 'active' : '' ?>">Dashboard</a>
            </div>
            
            <p class="menu-header">BASE</p>

            <?php if ($adminRole == 'super_admin'): ?>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/admin_icon.png" alt="">
                <a href="manage_staff.php" class="<?= $current_page == 'manage_staff.php' ? 'active' : '' ?>">Admin Account Management</a>
            </div>
            <?php endif; ?>
            
            <?php if ($adminRole == 'super_admin' || $adminRole == 'admin'): ?>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/account_approval_icon.png" alt="">
                <a href="account_approval.php" class="<?= $current_page == 'account_approval.php' ? 'active' : '' ?>">User Account Management</a>
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
            <div style="display: flex; gap: 15px; align-items: center;">
                <a href="healthstaff_dashboard.php" class="back-button">← Back</a>
                <h2>Medicine Request Statistics</h2>
            </div>
        </div>
        
        <div class="stats-container">
            <div class="stats-header">
                <div class="filter-form">
                    <form id="periodForm" method="GET">
                        <label for="period">Time Period:</label>
                        <select name="period" id="period" onchange="toggleCustomDate()">
                            <option value="all" <?= $period == 'all' ? 'selected' : '' ?>>All Time</option>
                            <option value="today" <?= $period == 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="this_week" <?= $period == 'this_week' ? 'selected' : '' ?>>This Week</option>
                            <option value="this_month" <?= $period == 'this_month' ? 'selected' : '' ?>>This Month</option>
                            <option value="this_year" <?= $period == 'this_year' ? 'selected' : '' ?>>This Year</option>
                            <option value="custom" <?= $period == 'custom' ? 'selected' : '' ?>>Custom Range</option>
                        </select>
                        <div id="custom-date-container" style="<?= $period == 'custom' ? 'display: flex;' : 'display: none;' ?>">
                            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                            <span>-</span>
                            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                        </div>
                        <button type="submit" class="generate-btn">Apply</button>
                    </form>
                </div>
                <div class="stats-actions">
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="export-btn">
                        Export to Excel
                    </a>
                </div>
            </div>

            <!-- Summary Tiles -->
            <div class="stats-con">
                <div class="stats-wrapper">
                    <div class="summary-tiles">
                        <div class="summary-tile"><h3>Total Requests</h3><div class="number"><?= number_format($totalRequests) ?></div></div>
                        <div class="summary-tile"><h3>Total Requested Medicines</h3><div class="number"><?= number_format($totalRequestedMedicines) ?></div></div>
                        <div class="summary-tile"><h3>Pending Requests</h3><div class="number"><?= number_format($pendingRequests) ?></div></div>
                        <div class="summary-tile"><h3>Approved Requests</h3><div class="number"><?= number_format($approvedRequests) ?></div></div>
                        <div class="summary-tile"><h3>Declined Requests</h3><div class="number"><?= number_format($declinedRequests) ?></div></div>
                    </div>

                    <!-- Charts -->
                    <div class="chart-row">
                        <div class="chart-container">
                            <h3>Request Status Distribution</h3>
                            <canvas id="requestStatusChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <h3>Top Requested Medicines</h3>
                            <canvas id="topMedicinesChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-container">
                        <h3>Monthly Medicine Requests (<?= date('Y') ?>)</h3>
                        <canvas id="requestChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleCustomDate() {
            const container = document.getElementById('custom-date-container');
            container.style.display = document.getElementById('period').value === 'custom' ? 'flex' : 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Charts (unchanged)
            new Chart(document.getElementById('requestStatusChart'), {
                type: 'doughnut',
                data: { labels: <?= json_encode($requestStatusLabels) ?>, datasets: [{ data: <?= json_encode($requestStatusData) ?>, backgroundColor: ['#2196F3','#FF9800','#4CAF50','#F44336'], borderWidth: 2 }] },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });

            new Chart(document.getElementById('topMedicinesChart'), {
                type: 'bar',
                data: { labels: <?= json_encode($topMedicinesLabels) ?>, datasets: [{ label: 'Qty', data: <?= json_encode($topMedicinesData) ?>, backgroundColor: 'rgba(139,0,0,0.6)' }] },
                options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } } }
            });

            new Chart(document.getElementById('requestChart'), {
                type: 'line',
                data: { labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], datasets: [{ label: 'Requests', data: <?= json_encode(array_values($monthlyRequests)) ?>, borderColor: '#8B0000', fill: true, tension: 0.3 }] },
                options: { responsive: true }
            });
        });
    </script>
</body>
</html>