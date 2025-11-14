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

// Fetch Medicine Statistics
try {
    // Total Medicines in Catalog
    $total_medicines = $conn->query("SELECT COUNT(*) FROM medicines_catalog")->fetchColumn();

    // Total Batches
    $total_batches = $conn->query("SELECT COUNT(*) FROM medicine_batches WHERE is_disposed = 0")->fetchColumn();

    // Total Stock Quantity
    $stock_result = $conn->query("SELECT SUM(stocks) FROM medicine_batches WHERE is_disposed = 0")->fetchColumn();
    $total_stock = $stock_result ? $stock_result : 0;

    // Low Stock Medicines (< min_stock)
    $low_stock = $conn->query("
        SELECT COUNT(*) FROM medicines_catalog mc
        JOIN medicine_batches mb ON mc.id = mb.catalog_id
        WHERE mb.is_disposed = 0 AND mb.stocks < mc.min_stock
        GROUP BY mc.id
    ")->rowCount();

    // Out of Stock
    $out_of_stock = $conn->query("SELECT COUNT(*) FROM medicines_catalog WHERE stock_status = 'Out of Stock'")->fetchColumn();

    // Expiring Soon (within 30 days)
    $expiring_soon = $conn->query("
        SELECT COUNT(*) FROM medicine_batches 
        WHERE expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        AND expiration_date >= CURDATE() AND is_disposed = 0
    ")->fetchColumn();

    // Expired Medicines
    $expired = $conn->query("
        SELECT COUNT(*) FROM medicine_batches 
        WHERE expiration_date < CURDATE() AND is_disposed = 0
    ")->fetchColumn();

    // Top 5 Most Stocked Medicines
    $top_meds = $conn->query("
        SELECT mc.generic_name, mc.brand_name, mc.dosage, SUM(mb.stocks) as total_stock
        FROM medicines_catalog mc
        JOIN medicine_batches mb ON mc.id = mb.catalog_id
        WHERE mb.is_disposed = 0
        GROUP BY mc.id
        ORDER BY total_stock DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Stock Status Distribution
    $status_data = $conn->query("
        SELECT mc.stock_status, COUNT(*) as count
        FROM medicines_catalog mc
        GROUP BY mc.stock_status
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Monthly Distribution Trend (Last 6 Months)
    $monthly_data = $conn->query("
        SELECT DATE_FORMAT(md.created_at, '%Y-%m') as month, 
               COUNT(*) as distributions
        FROM medicine_distributions md
        WHERE md.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(md.created_at, '%Y-%m')
        ORDER BY month
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    error_log($e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Statistics</title>
    <link rel="stylesheet" href="css/medicine_stats.css">
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
            <div class="stats-grid">
                <div class="stat-card card-total">
                    <i class="fas fa-capsules stat-icon"></i>
                    <div class="stat-title">Total Medicines</div>
                    <div class="stat-value"><?= number_format($total_medicines) ?></div>
                    <div class="stat-desc">In catalog</div>
                </div>

                <div class="stat-card card-stock">
                    <i class="fas fa-boxes-stacked stat-icon"></i>
                    <div class="stat-title">Total Stock</div>
                    <div class="stat-value"><?= number_format($total_stock) ?></div>
                    <div class="stat-desc">Units available</div>
                </div>

                <div class="stat-card card-low">
                    <i class="fas fa-exclamation-triangle stat-icon"></i>
                    <div class="stat-title">Low Stock</div>
                    <div class="stat-value"><?= $low_stock ?></div>
                    <div class="stat-desc">Need restock</div>
                </div>

                <div class="stat-card card-out">
                    <i class="fas fa-ban stat-icon"></i>
                    <div class="stat-title">Out of Stock</div>
                    <div class="stat-value"><?= $out_of_stock ?></div>
                    <div class="stat-desc">Unavailable</div>
                </div>

                <div class="stat-card card-expiring">
                    <i class="fas fa-hourglass-half stat-icon"></i>
                    <div class="stat-title">Expiring Soon</div>
                    <div class="stat-value"><?= $expiring_soon ?></div>
                    <div class="stat-desc">Within 30 days</div>
                </div>

                <div class="stat-card card-batches">
                    <i class="fas fa-prescription-bottle-alt stat-icon"></i>
                    <div class="stat-title">Active Batches</div>
                    <div class="stat-value"><?= $total_batches ?></div>
                    <div class="stat-desc">Not disposed</div>
                </div>

                <div class="stat-card card-expired">
                    <i class="fas fa-skull-crossbones stat-icon"></i>
                    <div class="stat-title">Expired</div>
                    <div class="stat-value"><?= $expired ?></div>
                    <div class="stat-desc">Requires disposal</div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <div class="chart-container">
                    <div class="chart-title">Stock Status Distribution</div>
                    <canvas id="stockStatusChart"></canvas>
                </div>

                <div class="chart-container">
                    <div class="chart-title">Top 5 Most Stocked Medicines</div>
                    <canvas id="topMedicinesChart"></canvas>
                </div>

                <div class="chart-container full-width">
                    <div class="chart-title">Medicine Distribution Trend (Last 6 Months)</div>
                    <canvas id="monthlyTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>



    <!-- ====================== SCRIPTS ====================== -->
    <script>
        // Stock Status Doughnut
        new Chart(document.getElementById('stockStatusChart'), {
            type: 'doughnut',
            data: {
                labels: [<?php foreach($status_data as $s) echo "'".$s['stock_status']."',"; ?>],
                datasets: [{
                    data: [<?php foreach($status_data as $s) echo $s['count'].","; ?>],
                    backgroundColor: ['#198754', '#ffc107', '#dc3545'],
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 20, font: { size: 14 } } }
                }
            }
        });

        // Top 5 Medicines
        new Chart(document.getElementById('topMedicinesChart'), {
            type: 'bar',
            data: {
                labels: [<?php foreach($top_meds as $med){
                    $name = $med['generic_name'] . ($med['brand_name'] ? ' ('.$med['brand_name'].')' : '');
                    echo "'".substr($name, 0, 25).(strlen($name)>25?'...':'')."',";
                } ?>],
                datasets: [{
                    label: 'Stock',
                    data: [<?php foreach($top_meds as $med) echo $med['total_stock'].","; ?>],
                    backgroundColor: 'rgba(13, 110, 253, 0.85)',
                    borderColor: '#0d6efd',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Monthly Trend
        const months = <?php
            $labels = [];
            for($i = 5; $i >= 0; $i--) {
                $labels[] = date('M Y', strtotime("-$i month"));
            }
            echo json_encode($labels);
        ?>;
        const trendData = <?php
            $data = array_fill(0, 6, 0);
            foreach($monthly_data as $row) {
                $diff = date_diff(date_create($row['month']), date_create(date('Y-m')))->m;
                if ($diff <= 5) $data[5 - $diff] = (int)$row['distributions'];
            }
            echo json_encode($data);
        ?>;

        new Chart(document.getElementById('monthlyTrendChart'), {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Distributions',
                    data: trendData,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#0d6efd',
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>