<?php
// expiring_low_stock.php
session_start();
require_once "config.php";

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'health_staff') {
    header("Location: login.php");
    exit();
}

// Fetch Admin's Name
$adminId = $_SESSION['admin_id'];
$adminStmt = $conn->prepare("SELECT * FROM admin_staff WHERE id = :id");
$adminStmt->bindParam(':id', $adminId);
$adminStmt->execute();
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
$adminName = $admin ? $admin['full_name'] : $_SESSION['admin_name'];
$adminRole = $admin ? $admin['role'] : $_SESSION['admin_role'];
$displayRole = ucwords(str_replace('_', ' ', $adminRole));

// Fetch all expiring and low stock medicines
$expiryDate = date('Y-m-d', strtotime('+60 days'));
$stockQuery = $conn->prepare("
    SELECT 
        mc.id AS catalog_id, 
        mc.generic_name, 
        mc.brand_name, 
        mc.dosage, 
        mc.dosage_form, 
        mc.unit, 
        mc.min_stock, 
        mb.id AS batch_id, 
        mb.batch_lot_number, 
        mb.expiration_date, 
        mb.stocks, 
        mb.stock_status, 
        mb.expiry_status
    FROM medicines_catalog mc
    JOIN medicine_batches mb ON mc.id = mb.catalog_id
    WHERE (mb.expiration_date <= :expiryDate 
           OR mb.expiry_status IN ('Expiring within a month', 'Expiring within a week', 'Expired')
           OR mb.stocks <= mc.min_stock)
    AND mb.stocks >= 0
    ORDER BY mb.expiration_date ASC, mb.stocks ASC
");
$stockQuery->bindParam(':expiryDate', $expiryDate);
$stockQuery->execute();
$medicines = $stockQuery->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expiring and Low Stock Medicines</title>
    <link rel="stylesheet" href="css/medicine_management.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <style>
        .med-container {
            padding: 20px;
        }
        .table-wrapper {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f4f4f4;
        }
        .critical {
            color: #d9534f;
            font-weight: bold;
        }
        .warning {
            color: #f0ad4e;
            font-weight: bold;
        }
        .low-stock {
            color: #f0ad4e;
        }
        .critical-stock {
            color: #d9534f;
        }
        
    </style>
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
            <img src="images/profile-placeholder.png" alt="Staff">
            <div class="profile-details">
                <p class="admin_name"><strong><?= htmlspecialchars($adminName) ?></strong></p>
                <p class="role"><?= htmlspecialchars($displayRole) ?></p>
            </div>
        </div>
        <div class="menu">
            <?php 
                $current_page = basename($_SERVER['PHP_SELF']); 
                $dashboard_url = 'healthstaff_dashboard.php';
            ?>
            <p class="menu-header">ANALYTICS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/dashboard_icon.png" alt="">
                <a href="<?= htmlspecialchars($dashboard_url) ?>" class="<?= $current_page == $dashboard_url ? 'active' : '' ?>">Dashboard</a>
            </div>
            <p class="menu-header">BASE</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/patient_icon.png" alt="">
                <a href="patient_management.php" class="<?= $current_page == 'patient_management.php' ? 'active' : '' ?>">Patient Management</a>
            </div>
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/med_icon_active.png" alt="">
                <a href="medicine_management.php" class="<?= $current_page == 'medicine_management.php' ? 'active' : '' ?>">Medicine Management</a>
            </div>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/reqmd_icon.png" alt="">
                <a href="medicine_requests.php" class="<?= $current_page == 'medicine_requests.php' ? 'active' : '' ?>">Medicine Requests</a>
            </div>
            <p class="menu-header">OTHERS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/logout_icon.png" alt="">
                <a href="logout.php" class="logout-button">Log Out</a>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="med-container">
            <div class="sort-controls">
                <div style="display: flex; gap: 15px; align-items: center;">
                    <a href="#" class="back-button" onclick="history.back(); return false;">← Back</a>
                    <h2>Expiring and Low Stock Medicines</h2>
                </div>
                <div class="search-con">
                    
                </div>
            </div>

            <div class="table-details">
                <div class="table-con">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Generic Name</th>
                                    <th>Brand Name</th>
                                    <th>Dosage</th>
                                    <th>Dosage Form</th>
                                    <th>Unit</th>
                                    <th>Batch Lot Number</th>
                                    <th>Expiration Date</th>
                                    <th>Stocks</th>
                                    <th>Min Stock</th>
                                    <th>Stock Status</th>
                                    <th>Expiry Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($medicines)): ?>
                                    <tr>
                                        <td colspan="12" style="text-align: center;">No expiring or low stock medicines found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($medicines as $medicine): ?>
                                        <?php 
                                            $daysUntilExpiry = $medicine['expiration_date'] ? (strtotime($medicine['expiration_date']) - time()) / (60 * 60 * 24) : null;
                                            $expiryClass = '';
                                            if ($medicine['expiry_status'] == 'Expired') {
                                                $expiryClass = 'critical';
                                            } elseif ($medicine['expiry_status'] == 'Expiring within a week' || $daysUntilExpiry <= 7) {
                                                $expiryClass = 'critical';
                                            } elseif ($medicine['expiry_status'] == 'Expiring within a month' || $daysUntilExpiry <= 30) {
                                                $expiryClass = 'warning';
                                            }
                                            $stockClass = $medicine['stocks'] <= $medicine['min_stock'] && $medicine['stocks'] > 0 ? ($medicine['stocks'] / $medicine['min_stock'] <= 0.5 ? 'critical-stock' : 'low-stock') : '';
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($medicine['generic_name']) ?></td>
                                            <td><?= htmlspecialchars($medicine['brand_name'] ?: 'N/A') ?></td>
                                            <td><?= htmlspecialchars($medicine['dosage'] ?: 'N/A') ?></td>
                                            <td><?= htmlspecialchars($medicine['dosage_form'] ?: 'N/A') ?></td>
                                            <td><?= htmlspecialchars($medicine['unit'] ?: 'N/A') ?></td>
                                            <td><?= htmlspecialchars($medicine['batch_lot_number']) ?></td>
                                            <td class="<?= $expiryClass ?>">
                                                <?= htmlspecialchars($medicine['expiration_date'] ?: 'N/A') ?>
                                            </td>
                                            <td class="<?= $stockClass ?>">
                                                <?= htmlspecialchars($medicine['stocks']) ?>
                                            </td>
                                            <td><?= htmlspecialchars($medicine['min_stock']) ?></td>
                                            <td class="<?= $stockClass ?>">
                                                <?= htmlspecialchars($medicine['stock_status']) ?>
                                            </td>
                                            <td class="<?= $expiryClass ?>">
                                                <?= htmlspecialchars($medicine['expiry_status']) ?>
                                            </td>
                                            <td>
                                                <a href="view_batches.php?catalog_id=<?= htmlspecialchars($medicine['catalog_id']) ?>" class="viewBatch-btn">View Batch</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>