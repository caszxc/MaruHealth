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

// Function to count medicine requests
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
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

// Function to count total requested medicines
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
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

// Function to count pending medicine requests
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
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

// Function to count approved medicine requests
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
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

// Function to count declined medicine requests
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
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

// Function to get request status distribution
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
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Function to get requested medicines status distribution
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
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Function to get top requested medicines
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
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Function to get monthly medicine request data
function getMonthlyRequests($conn, $year = null) {
    if ($year === null) {
        $year = date('Y');
    }
    
    try {
        $sql = "SELECT 
                    MONTH(request_date) as month, 
                    COUNT(*) as count 
                FROM medicine_requests 
                WHERE YEAR(request_date) = :year 
                GROUP BY MONTH(request_date)
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

// Function to get request list with requested medicines
function getRequestList($conn, $startDate = '', $endDate = '', $limit = 10, $offset = 0) {
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
        
        $sql .= " GROUP BY mr.id ORDER BY mr.request_date DESC LIMIT :limit OFFSET :offset";
        
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

// Count total requests for pagination
function countTotalRequests($conn, $startDate = '', $endDate = '') {
    try {
        $sql = "SELECT COUNT(*) FROM medicine_requests";
        $params = [];
        
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " WHERE request_date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// Get statistics
$totalRequests = countMedicineRequests($conn, $startDate, $endDate);
$totalRequestedMedicines = countRequestedMedicines($conn, $startDate, $endDate);
$pendingRequests = countPendingRequests($conn, $startDate, $endDate);
$approvedRequests = countApprovedRequests($conn, $startDate, $endDate);
$declinedRequests = countDeclinedRequests($conn, $startDate, $endDate);
$requestStatusDistribution = getRequestStatusDistribution($conn, $startDate, $endDate);
$requestedMedicinesStatusDistribution = getRequestedMedicinesStatusDistribution($conn, $startDate, $endDate);
$topRequestedMedicines = getTopRequestedMedicines($conn, $startDate, $endDate);
$monthlyRequests = getMonthlyRequests($conn);

// Process request status distribution for chart
$requestStatusLabels = [];
$requestStatusData = [];
foreach ($requestStatusDistribution as $item) {
    $requestStatusLabels[] = ucfirst($item['request_status']);
    $requestStatusData[] = (int)$item['count'];
}

// Process requested medicines status distribution for chart
$requestedMedicinesStatusLabels = [];
$requestedMedicinesStatusData = [];
foreach ($requestedMedicinesStatusDistribution as $item) {
    $requestedMedicinesStatusLabels[] = ucfirst($item['status']);
    $requestedMedicinesStatusData[] = (int)$item['count'];
}

// Process top requested medicines for chart
$topMedicinesLabels = [];
$topMedicinesData = [];
foreach ($topRequestedMedicines as $item) {
    $topMedicinesLabels[] = $item['medicine_name'];
    $topMedicinesData[] = (int)$item['total_quantity'];
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;
$totalRecords = countTotalRequests($conn, $startDate, $endDate);
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get request list for current page
$requestList = getRequestList($conn, $startDate, $endDate, $recordsPerPage, $offset);

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="medicine_request_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    $allRequests = getRequestList($conn, $startDate, $endDate, 100000, 0);
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Medicine Request Report</title>
    </head>
    <body>
        <table border="1">
            <thead>
                <tr>
                    <th colspan="5">Maru-Health Medicine Request Report - ' . ($period != 'custom' ? ucfirst(str_replace('_', ' ', $period)) : date('M d, Y', strtotime($startDate)) . ' to ' . date('M d, Y', strtotime($endDate))) . '</th>
                </tr>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Gender</th>
                    <th>Request Status</th>
                    <th>Requested Medicines</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($allRequests as $request) {
        echo '<tr>
                <td>' . $request['id'] . '</td>
                <td>' . htmlspecialchars($request['full_name']) . '</td>
                <td>' . htmlspecialchars($request['gender']) . '</td>
                <td>' . htmlspecialchars($request['request_status']) . '</td>
                <td>' . htmlspecialchars($request['requested_medicines'] ?: 'None') . '</td>
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
    <title>Medicine Request Statistics - Admin Dashboard</title>
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .stats-actions {
            display: flex;
            gap: 10px;
        }
        
        .export-btn {
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 15px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .summary-tiles {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .summary-tile {
            flex: 1;
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            min-width: 150px;
        }
        
        .summary-tile h3 {
            margin-top: 0;
            color: #7D0000;
            font-size: 16px;
        }
        
        .summary-tile .number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }
        
        .chart-row {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .chart-container {
            flex: 1;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            min-height: 400px;
            position: relative;
            min-width: 300px;
        }
        
        .chart-container canvas {
            width: 100% !important;
            height: 350px !important;
        }
        
        .requests-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .requests-table th, .requests-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .requests-table th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: bold;
        }
        
        .requests-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
        }
        
        .pagination a:hover {
            background-color: #f5f5f5;
        }
        
        .pagination .active {
            background-color: #7D0000;
            color: white;
            border-color: #7D0000;
        }
        
        #custom-date-container {
            display: none;
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
                <a href="<?= htmlspecialchars($dashboard_url) ?>" class="<?= $current_page == 'medicine_request_stats.php' ? 'active' : '' ?>">Dashboard</a>
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
            <a href="<?= htmlspecialchars($dashboard_url) ?>" class="back-button">< Back to Dashboard</a>
            <h1>Medicine Request Statistics</h1>
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
                    
                    <div id="custom-date-container" style="<?= $period == 'custom' ? 'display: flex;' : '' ?>">
                        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                        <span>-</span>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                    
                    <button type="submit" class="generate-btn">Apply Filter</button>
                </form>
            </div>
            
            <div class="stats-actions">
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="export-btn">
                    <img src="images/icons/excel_icon.png" alt="Excel" style="width: 20px; height: 20px;">
                    Export to Excel
                </a>
            </div>
        </div>
        
        <div class="summary-tiles">
            <div class="summary-tile">
                <h3>Total Requests</h3>
                <div class="number"><?= number_format($totalRequests) ?></div>
            </div>
            <div class="summary-tile">
                <h3>Total Requested Medicines</h3>
                <div class="number"><?= number_format($totalRequestedMedicines) ?></div>
            </div>
            <div class="summary-tile">
                <h3>Pending Requests</h3>
                <div class="number"><?= number_format($pendingRequests) ?></div>
            </div>
            <div class="summary-tile">
                <h3>Approved Requests</h3>
                <div class="number"><?= number_format($approvedRequests) ?></div>
            </div>
            <div class="summary-tile">
                <h3>Declined Requests</h3>
                <div class="number"><?= number_format($declinedRequests) ?></div>
            </div>
        </div>
        
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
        
        <div class="table-container">
            <h3>Request List</h3>
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Gender</th>
                        <th>Request Status</th>
                        <th>Requested Medicines</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($requestList) > 0): ?>
                        <?php foreach ($requestList as $request): ?>
                            <tr>
                                <td><?= $request['id'] ?></td>
                                <td><?= htmlspecialchars($request['full_name']) ?></td>
                                <td><?= htmlspecialchars($request['gender']) ?></td>
                                <td><?= htmlspecialchars($request['request_status']) ?></td>
                                <td><?= htmlspecialchars($request['requested_medicines'] ?: 'None') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No requests found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1) {
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a>';
                        if ($startPage > 2) {
                            echo '<span>...</span>';
                        }
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        if ($i == $page) {
                            echo '<span class="active">' . $i . '</span>';
                        } else {
                            echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '">' . $i . '</a>';
                        }
                    }
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<span>...</span>';
                        }
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $totalPages])) . '">' . $totalPages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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
            // Request status distribution chart
            const requestStatusCtx = document.getElementById('requestStatusChart');
            if (requestStatusCtx) {
                const requestStatusChart = new Chart(requestStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode($requestStatusLabels) ?>,
                        datasets: [{
                            data: <?= json_encode($requestStatusData) ?>,
                            backgroundColor: [
                                '#2196F3', // Requested
                                '#FF9800', // Pending
                                '#4CAF50', // Claimed
                                '#F44336'  // Declined
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
            
            // Top requested medicines chart
            const topMedicinesCtx = document.getElementById('topMedicinesChart');
            if (topMedicinesCtx) {
                const topMedicinesChart = new Chart(topMedicinesCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($topMedicinesLabels) ?>,
                        datasets: [{
                            label: 'Total Quantity Requested',
                            data: <?= json_encode($topMedicinesData) ?>,
                            backgroundColor: 'rgba(139, 0, 0, 0.6)',
                            borderColor: '#8B0000',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                },
                                title: {
                                    display: true,
                                    text: 'Quantity Requested'
                                }
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: 'Medicine Name'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `${context.label}: ${context.formattedValue} units`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Monthly requests chart
            const requestCtx = document.getElementById('requestChart');
            if (requestCtx) {
                const requestChart = new Chart(requestCtx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                        datasets: [{
                            label: 'Medicine Requests',
                            data: <?= json_encode(array_values($monthlyRequests)) ?>,
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