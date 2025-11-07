<?php
session_start();
require_once "config.php";

// Check if user is logged in as super admin or health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'health_staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Initialize filters
$period = $_GET['period'] ?? 'all';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Fetch admin/staff info
$adminId = $_SESSION['admin_id'];
$adminStmt = $conn->prepare("SELECT * FROM admin_staff WHERE id = :id");
$adminStmt->execute([':id' => $adminId]);
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
$adminName = $admin['full_name'] ?? $_SESSION['admin_name'];
$adminRole = $admin['role'] ?? $_SESSION['admin_role'];
$displayRole = ucwords(str_replace('_', ' ', $adminRole));

// Handle date range
switch ($period) {
    case 'today':      $startDate = $endDate = date('Y-m-d'); break;
    case 'this_week':  $startDate = date('Y-m-d', strtotime('monday this week'));
                       $endDate   = date('Y-m-d', strtotime('sunday this week')); break;
    case 'this_month': $startDate = date('Y-m-01');
                       $endDate   = date('Y-m-t'); break;
    case 'this_year':  $startDate = date('Y-01-01');
                       $endDate   = date('Y-12-31'); break;
    case 'custom':     break;
    case 'all':
    default:           $startDate = $endDate = ''; break;
}

// ---------------------------------------------------------------------
//  HELPER FUNCTIONS
// ---------------------------------------------------------------------

function countTotalMedicines($conn) {
    return (int)$conn->query("SELECT COUNT(*) FROM medicines_catalog")->fetchColumn();
}

function countTotalBatches($conn) {
    return (int)$conn->query("SELECT COUNT(*) FROM medicine_batches")->fetchColumn();
}

function countTotalUnits($conn) {
    return (int)$conn->query("SELECT COALESCE(SUM(stocks), 0) FROM medicine_batches")->fetchColumn();
}

function countLowStock($conn) {
    return (int)$conn->query("SELECT COUNT(*) FROM medicines_catalog WHERE stock_status = 'Low Stock'")->fetchColumn();
}

function countOutOfStock($conn) {
    return (int)$conn->query("SELECT COUNT(*) FROM medicines_catalog WHERE stock_status = 'Out of Stock'")->fetchColumn();
}

function countExpired($conn, $start = '', $end = '') {
    $sql = "SELECT COUNT(*) FROM medicine_batches WHERE expiry_status = 'Expired'";
    $params = [];
    if ($start && $end) {
        $sql .= " AND expiration_date BETWEEN :s AND :e";
        $params = [':s' => $start, ':e' => $end];
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function countExpiringSoon($conn, $days = 30) {
    $sql = "SELECT COUNT(*) FROM medicine_batches 
            WHERE expiry_status IN ('Expiring within a month', 'Expiring within a week')
               OR (expiration_date IS NOT NULL AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY))";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':days' => $days]);
    return (int)$stmt->fetchColumn();
}

function getStockStatusDistribution($conn) {
    $stmt = $conn->query("SELECT stock_status, COUNT(*) AS count FROM medicines_catalog GROUP BY stock_status");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getExpiryStatusDistribution($conn, $start = '', $end = '') {
    $sql = "SELECT expiry_status, COUNT(*) AS count FROM medicine_batches WHERE expiry_status != 'Valid'";
    $params = [];
    if ($start && $end) {
        $sql .= " AND expiration_date BETWEEN :s AND :e";
        $params = [':s' => $start, ':e' => $end];
    }
    $sql .= " GROUP BY expiry_status";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMonthlyDistribution($conn, $year = null) {
    $year = $year ?? date('Y');
    $sql = "SELECT MONTH(md.created_at) AS month, SUM(md.quantity) AS total
            FROM medicine_distributions md
            JOIN medicine_requests mr ON md.request_id = mr.id
            WHERE YEAR(mr.claimed_date) = :year OR mr.claimed_date IS NULL
            GROUP BY MONTH(md.created_at)
            ORDER BY month";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':year' => $year]);
    $data = array_fill(1, 12, 0);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data[$row['month']] = (int)$row['total'];
    }
    return $data;
}

function getTopDistributedMedicines($conn, $limit = 10, $start = '', $end = '') {
    $sql = "SELECT mc.generic_name, mc.brand_name, COALESCE(SUM(md.quantity), 0) AS total_distributed
            FROM medicine_distributions md
            JOIN requested_medicines rm ON md.requested_medicine_id = rm.id
            JOIN medicines_catalog mc ON rm.medicine_name = mc.generic_name
            WHERE md.status = 'claimed'";
    $params = [];
    if ($start && $end) {
        $sql .= " AND md.created_at BETWEEN :s AND :e";
        $params = [':s' => "$start 00:00:00", ':e' => "$end 23:59:59"];
    }
    $sql .= " GROUP BY mc.id ORDER BY total_distributed DESC LIMIT :limit";
    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMedicineList($conn, $start = '', $end = '', $limit = 100000, $offset = 0) {
    $sql = "SELECT mc.generic_name, mc.brand_name, mc.dosage, mc.dosage_form, 
                   COALESCE(SUM(mb.stocks), 0) AS total_stock,
                   mc.stock_status, mc.min_stock
            FROM medicines_catalog mc
            LEFT JOIN medicine_batches mb ON mc.id = mb.catalog_id
            GROUP BY mc.id";
    $params = [];
    if ($start && $end) {
        $sql .= " HAVING mb.created_at BETWEEN :s AND :e OR mb.created_at IS NULL";
        $params = [':s' => "$start 00:00:00", ':e' => "$end 23:59:59"];
    }
    $sql .= " ORDER BY mc.generic_name LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------------------------------------------------------------------
//  FETCH DATA
// ---------------------------------------------------------------------
$totalMedicines = countTotalMedicines($conn);
$totalBatches = countTotalBatches($conn);
$totalUnits = countTotalUnits($conn);
$lowStock = countLowStock($conn);
$outOfStock = countOutOfStock($conn);
$expired = countExpired($conn, $startDate, $endDate);
$expiringSoon = countExpiringSoon($conn, 30);

$stockStatusData = getStockStatusDistribution($conn);
$expiryStatusData = getExpiryStatusDistribution($conn, $startDate, $endDate);
$monthlyDistribution = getMonthlyDistribution($conn);
$topDistributed = getTopDistributedMedicines($conn, 10, $startDate, $endDate);

// Process for charts
$stockLabels = $stockData = [];
foreach ($stockStatusData as $item) {
    $stockLabels[] = $item['stock_status'];
    $stockData[] = (int)$item['count'];
}

$expiryLabels = $expiryData = [];
foreach ($expiryStatusData as $item) {
    $expiryLabels[] = $item['expiry_status'];
    $expiryData[] = (int)$item['count'];
}

$topMedLabels = $topMedData = [];
foreach ($topDistributed as $item) {
    $name = $item['generic_name'];
    if ($item['brand_name']) $name .= " ({$item['brand_name']})";
    $topMedLabels[] = $name;
    $topMedData[] = (int)$item['total_distributed'];
}

// ---------------------------------------------------------------------
//  EXPORT TO EXCEL
// ---------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="medicine_report_' . date('Y-m-d') . '.xls"');
    $allMeds = getMedicineList($conn, $startDate, $endDate, 100000, 0);

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Medicine Report</title></head><body>';
    echo '<table border="1"><thead><tr>
        <th colspan="6">Medicine Inventory Report - ' . ($period !== 'custom' ? ucfirst(str_replace('_', ' ', $period)) : "$startDate to $endDate") . '</th>
    </tr><tr>
        <th>Generic Name</th><th>Brand</th><th>Dosage</th><th>Form</th><th>Total Stock</th><th>Status</th>
    </tr></thead><tbody>';

    foreach ($allMeds as $med) {
        echo "<tr>
            <td>" . htmlspecialchars($med['generic_name']) . "</td>
            <td>" . htmlspecialchars($med['brand_name'] ?: 'N/A') . "</td>
            <td>" . htmlspecialchars($med['dosage'] ?: 'N/A') . "</td>
            <td>" . htmlspecialchars($med['dosage_form']) . "</td>
            <td>" . $med['total_stock'] . "</td>
            <td>" . htmlspecialchars($med['stock_status']) . "</td>
        </tr>";
    }
    echo '</tbody></table></body></html>';
    exit;
}
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
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="images/3s logo.png">
            <div><h1>Maru-Health</h1><p>Barangay Marulas 3S Health Station</p></div>
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
                <a href="<?= $dashboard_url ?>" class="<?= $current_page == 'medicine_stats.php' ? 'active' : '' ?>">Dashboard</a>
            </div>
            <p class="menu-header">BASE</p>
            <?php if ($adminRole == 'super_admin' || $adminRole == 'health_staff'): ?>
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
                <a href="<?= $dashboard_url ?>" class="back-button">Back</a>
                <h2>Medicine Statistics</h2>
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

            <div class="stats-con">
                <div class="stats-wrapper">
                    <!-- SUMMARY TILES -->
                    <div class="summary-tiles">
                        <div class="summary-tile">
                            <h3>Total Medicines</h3>
                            <div class="number"><?= number_format($totalMedicines) ?></div>
                        </div>
                        <div class="summary-tile">
                            <h3>Total Batches</h3>
                            <div class="number"><?= number_format($totalBatches) ?></div>
                        </div>
                        <div class="summary-tile">
                            <h3>Total Units</h3>
                            <div class="number"><?= number_format($totalUnits) ?></div>
                        </div>
                        <div class="summary-tile">
                            <h3>Low Stock</h3>
                            <div class="number warning"><?= number_format($lowStock) ?></div>
                        </div>
                        <div class="summary-tile">
                            <h3>Out of Stock</h3>
                            <div class="number danger"><?= number_format($outOfStock) ?></div>
                        </div>
                        <div class="summary-tile">
                            <h3>Expired</h3>
                            <div class="number danger"><?= number_format($expired) ?></div>
                        </div>
                        <div class="summary-tile">
                            <h3>Expiring Soon</h3>
                            <div class="number warning"><?= number_format($expiringSoon) ?></div>
                        </div>
                    </div>

                    <!-- CHARTS -->
                    <div class="chart-row">
                        <div class="chart-container">
                            <h3>Stock Status</h3>
                            <canvas id="stockChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <h3>Expiry Alerts</h3>
                            <canvas id="expiryChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-row">
                        <div class="chart-container">
                            <h3>Monthly Distribution (<?= date('Y') ?>)</h3>
                            <canvas id="distributionChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <h3>Top 10 Distributed Medicines</h3>
                            <canvas id="topMedsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleCustomDate() {
            const sel = document.getElementById('period');
            document.getElementById('custom-date-container').style.display = 
                sel.value === 'custom' ? 'flex' : 'none';
        }

        document.addEventListener('DOMContentLoaded', () => {
            const colors = {
                success: '#27ae60', warning: '#f39c12', danger: '#e74c3c',
                info: '#3498db', muted: '#95a5a6'
            };

            // Stock Status Doughnut
            new Chart(document.getElementById('stockChart'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($stockLabels) ?>,
                    datasets: [{
                        data: <?= json_encode($stockData) ?>,
                        backgroundColor: ['#27ae60', '#f39c12', '#e74c3c'],
                        borderWidth: 2, borderColor: '#fff'
                    }]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });

            // Expiry Bar
            new Chart(document.getElementById('expiryChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($expiryLabels) ?>,
                    datasets: [{
                        label: 'Count',
                        data: <?= json_encode($expiryData) ?>,
                        backgroundColor: ['#e67e22', '#e74c3c'],
                        borderColor: '#8B0000', borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                    plugins: { legend: { display: false } }
                }
            });

            // Monthly Line
            new Chart(document.getElementById('distributionChart'), {
                type: 'line',
                data: {
                    labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
                    datasets: [{
                        label: 'Units Distributed',
                        data: <?= json_encode(array_values($monthlyDistribution)) ?>,
                        borderColor: '#8B0000', backgroundColor: 'rgba(139,0,0,0.1)',
                        tension: 0.3, fill: true, pointRadius: 4
                    }]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true } } }
            });

            // Top 10 Horizontal Bar
            new Chart(document.getElementById('topMedsChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($topMedLabels) ?>,
                    datasets: [{
                        label: 'Units',
                        data: <?= json_encode($topMedData) ?>,
                        backgroundColor: 'rgba(139, 0, 0, 0.7)',
                        borderColor: '#8B0000', borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    scales: { x: { beginAtZero: true } },
                    plugins: { legend: { display: false } }
                }
            });
        });
    </script>
</body>
</html>