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
$stockStatus = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';
$expiryStatus = isset($_GET['expiry_status']) ? $_GET['expiry_status'] : '';

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

// Function to count medicines
function countMedicines($conn, $startDate = '', $endDate = '', $stockStatus = '', $expiryStatus = '') {
    try {
        $sql = "SELECT COUNT(*) as count FROM medicines";
        $params = [];
        $conditions = [];
        
        if (!empty($startDate) && !empty($endDate)) {
            $conditions[] = "created_at BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        
        if (!empty($stockStatus)) {
            $conditions[] = "stock_status = :stock_status";
            $params[':stock_status'] = $stockStatus;
        }
        
        if (!empty($expiryStatus)) {
            $conditions[] = "expiry_status = :expiry_status";
            $params[':expiry_status'] = $expiryStatus;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
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

// Function to count total stock
function countTotalStock($conn, $startDate = '', $endDate = '', $stockStatus = '', $expiryStatus = '') {
    try {
        $sql = "SELECT SUM(stocks) as total FROM medicines";
        $params = [];
        $conditions = [];
        
        if (!empty($startDate) && !empty($endDate)) {
            $conditions[] = "created_at BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        
        if (!empty($stockStatus)) {
            $conditions[] = "stock_status = :stock_status";
            $params[':stock_status'] = $stockStatus;
        }
        
        if (!empty($expiryStatus)) {
            $conditions[] = "expiry_status = :expiry_status";
            $params[':expiry_status'] = $expiryStatus;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

// Function to count pending medicine requests
function countPendingRequests($conn, $startDate = '', $endDate = '') {
    try {
        $sql = "SELECT COUNT(*) as count FROM medicine_requests WHERE request_status IN ('requested', 'pending')";
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

// Function to get stock status distribution
function getStockStatusDistribution($conn, $startDate = '', $endDate = '', $stockStatus = '', $expiryStatus = '') {
    try {
        $sql = "SELECT stock_status, COUNT(*) as count FROM medicines";
        $params = [];
        $conditions = [];
        
        if (!empty($startDate) && !empty($endDate)) {
            $conditions[] = "created_at BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        
        if (!empty($stockStatus)) {
            $conditions[] = "stock_status = :stock_status";
            $params[':stock_status'] = $stockStatus;
        }
        
        if (!empty($expiryStatus)) {
            $conditions[] = "expiry_status = :expiry_status";
            $params[':expiry_status'] = $expiryStatus;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " GROUP BY stock_status";
        
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

// Function to get expiry status distribution
function getExpiryStatusDistribution($conn, $startDate = '', $endDate = '', $stockStatus = '', $expiryStatus = '') {
    try {
        $sql = "SELECT expiry_status, COUNT(*) as count FROM medicines";
        $params = [];
        $conditions = [];
        
        if (!empty($startDate) && !empty($endDate)) {
            $conditions[] = "created_at BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        
        if (!empty($stockStatus)) {
            $conditions[] = "stock_status = :stock_status";
            $params[':stock_status'] = $stockStatus;
        }
        
        if (!empty($expiryStatus)) {
            $conditions[] = "expiry_status = :expiry_status";
            $params[':expiry_status'] = $expiryStatus;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " GROUP BY expiry_status";
        
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

// Function to get medicine list for table
function getMedicineList($conn, $startDate = '', $endDate = '', $stockStatus = '', $expiryStatus = '', $limit = 10, $offset = 0) {
    try {
        $sql = "SELECT id, generic_name, brand_name, stocks, min_stock, stock_status, expiry_status, expiration_date, created_at 
                FROM medicines";
        $params = [];
        $conditions = [];
        
        if (!empty($startDate) && !empty($endDate)) {
            $conditions[] = "created_at BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        
        if (!empty($stockStatus)) {
            $conditions[] = "stock_status = :stock_status";
            $params[':stock_status'] = $stockStatus;
        }
        
        if (!empty($expiryStatus)) {
            $conditions[] = "expiry_status = :expiry_status";
            $params[':expiry_status'] = $expiryStatus;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
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
$totalMedicines = countMedicines($conn, $startDate, $endDate, $stockStatus, $expiryStatus);
$totalStock = countTotalStock($conn, $startDate, $endDate, $stockStatus, $expiryStatus);
$pendingRequests = countPendingRequests($conn, $startDate, $endDate); // No status filter for requests
$stockStatusDistribution = getStockStatusDistribution($conn, $startDate, $endDate, $stockStatus, $expiryStatus);
$expiryStatusDistribution = getExpiryStatusDistribution($conn, $startDate, $endDate, $stockStatus, $expiryStatus);
$monthlyRequests = getMonthlyRequests($conn);

// Process stock status distribution for chart
$stockStatusLabels = [];
$stockStatusData = [];
foreach ($stockStatusDistribution as $item) {
    $stockStatusLabels[] = $item['stock_status'];
    $stockStatusData[] = (int)$item['count'];
}

// Process expiry status distribution for chart
$expiryStatusLabels = [];
$expiryStatusData = [];
foreach ($expiryStatusDistribution as $item) {
    $expiryStatusLabels[] = $item['expiry_status'];
    $expiryStatusData[] = (int)$item['count'];
}

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="medicine_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    $allMedicines = getMedicineList($conn, $startDate, $endDate, $stockStatus, $expiryStatus, 100000, 0);
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Medicine Report</title>
    </head>
    <body>
        <table border="1">
            <thead>
                <tr>
                    <th colspan="8">Maru-Health Medicine Report - ' . ($period != 'custom' ? ucfirst(str_replace('_', ' ', $period)) : date('M d, Y', strtotime($startDate)) . ' to ' . date('M d, Y', strtotime($endDate))) . '</th>
                </tr>
                <tr>
                    <th>ID</th>
                    <th>Generic Name</th>
                    <th>Brand Name</th>
                    <th>Stocks</th>
                    <th>Min Stock</th>
                    <th>Stock Status</th>
                    <th>Expiry Status</th>
                    <th>Expiration Date</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($allMedicines as $medicine) {
        echo '<tr>
                <td>' . $medicine['id'] . '</td>
                <td>' . htmlspecialchars($medicine['generic_name']) . '</td>
                <td>' . htmlspecialchars($medicine['brand_name'] ?: 'N/A') . '</td>
                <td>' . $medicine['stocks'] . '</td>
                <td>' . $medicine['min_stock'] . '</td>
                <td>' . htmlspecialchars($medicine['stock_status']) . '</td>
                <td>' . htmlspecialchars($medicine['expiry_status']) . '</td>
                <td>' . ($medicine['expiration_date'] ? date('M d, Y', strtotime($medicine['expiration_date'])) : 'N/A') . '</td>
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
    <title>Medicine Statistics - Admin Dashboard</title>
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
            <div>
                <h1>Maru-Health</h1>
                <p>Barangay Marulas 3S Health Station</p>
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
                } elseif ($adminRole === 'staff') {
                    $dashboard_url = 'staff_dashboard.php';
                }
            ?>
            <p class="menu-header">ANALYTICS</p>

            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/dashboard_icon_active.png" alt="">
                <a href="<?= htmlspecialchars($dashboard_url) ?>" class="<?= $current_page == 'medicine_stats.php' ? 'active' : '' ?>">Dashboard</a>
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
                <h2>Medicine Statistics</h2>
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
                    
                    <!-- Add Stock Status Filter -->
                    <label for="stock_status">Stock Status:</label>
                    <select name="stock_status" id="stock_status">
                        <option value="" <?= !isset($_GET['stock_status']) || $_GET['stock_status'] == '' ? 'selected' : '' ?>>All</option>
                        <option value="In Stock" <?= isset($_GET['stock_status']) && $_GET['stock_status'] == 'In Stock' ? 'selected' : '' ?>>In Stock</option>
                        <option value="Low Stock" <?= isset($_GET['stock_status']) && $_GET['stock_status'] == 'Low Stock' ? 'selected' : '' ?>>Low Stock</option>
                        <option value="Out of Stock" <?= isset($_GET['stock_status']) && $_GET['stock_status'] == 'Out of Stock' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                    
                    <!-- Add Expiry Status Filter -->
                    <label for="expiry_status">Expiry Status:</label>
                    <select name="expiry_status" id="expiry_status">
                        <option value="" <?= !isset($_GET['expiry_status']) || $_GET['expiry_status'] == '' ? 'selected' : '' ?>>All</option>
                        <option value="Valid" <?= isset($_GET['expiry_status']) && $_GET['expiry_status'] == 'Valid' ? 'selected' : '' ?>>Valid</option>
                        <option value="Expiring within a month" <?= isset($_GET['expiry_status']) && $_GET['expiry_status'] == 'Expiring within a month' ? 'selected' : '' ?>>Expiring within a month</option>
                        <option value="Expiring within a week" <?= isset($_GET['expiry_status']) && $_GET['expiry_status'] == 'Expiring within a week' ? 'selected' : '' ?>>Expiring within a week</option>
                        <option value="Expired" <?= isset($_GET['expiry_status']) && $_GET['expiry_status'] == 'Expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                    
                    <button type="submit" class="generate-btn">Apply Filter</button>
                </form>
            </div>
        </div>
        
        <div class="summary-tiles">
            <div class="summary-tile">
                <h3>Total Medicines</h3>
                <div class="number"><?= number_format($totalMedicines) ?></div>
            </div>
            <div class="summary-tile">
                <h3>Total Stock</h3>
                <div class="number"><?= number_format($totalStock) ?></div>
            </div>
            <div class="summary-tile">
                <h3>Pending Requests</h3>
                <div class="number"><?= number_format($pendingRequests) ?></div>
            </div>
        </div>
        
        <div class="chart-row">
            <div class="chart-container">
                <h3>Stock Status Distribution</h3>
                <canvas id="stockStatusChart"></canvas>
            </div>
            <div class="chart-container">
                <h3>Expiry Status Distribution</h3>
                <canvas id="expiryStatusChart"></canvas>
            </div>
        </div>
        
        <div class="chart-container">
            <h3>Monthly Medicine Requests (<?= date('Y') ?>)</h3>
            <canvas id="requestChart"></canvas>
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
            // Stock status distribution chart
            const stockStatusCtx = document.getElementById('stockStatusChart');
            if (stockStatusCtx) {
                const stockStatusChart = new Chart(stockStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode($stockStatusLabels) ?>,
                        datasets: [{
                            data: <?= json_encode($stockStatusData) ?>,
                            backgroundColor: [
                                '#4CAF50', // In Stock
                                '#FFC107', // Low Stock
                                '#F44336'  // Out of Stock
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
            
            // Expiry status distribution chart
            const expiryStatusCtx = document.getElementById('expiryStatusChart');
            if (expiryStatusCtx) {
                const expiryStatusChart = new Chart(expiryStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode($expiryStatusLabels) ?>,
                        datasets: [{
                            data: <?= json_encode($expiryStatusData) ?>,
                            backgroundColor: [
                                '#2196F3', // Valid
                                '#FF9800', // Expiring within a month
                                '#F44336', // Expiring within a week
                                '#9E9E9E'  // Expired
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