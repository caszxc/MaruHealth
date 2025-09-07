<?php
// view_batches.php
session_start();
require_once "config.php";

// Check if user is logged in as staff or super_admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['staff', 'super_admin'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Check if catalog_id is provided
if (!isset($_GET['catalog_id']) || empty($_GET['catalog_id'])) {
    header("Location: medicine_management.php");
    exit();
}

$catalog_id = trim($_GET['catalog_id']);

// Fetch Admin's Name
$adminId = $_SESSION['admin_id'];
$adminStmt = $conn->prepare("SELECT * FROM admin_staff WHERE id = :id");
$adminStmt->bindParam(':id', $adminId);
$adminStmt->execute();
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

// Default to session information if query fails
$adminName = $admin ? $admin['full_name'] : $_SESSION['admin_name'];
$adminRole = $admin ? $admin['role'] : $_SESSION['admin_role'];

// Format role for display
$displayRole = ucwords(str_replace('_', ' ', $adminRole));

// Fetch medicine details for display
$catalogStmt = $conn->prepare("
    SELECT therapeutic_category, generic_name, brand_name, dosage, dosage_form
    FROM medicines_catalog
    WHERE id = :catalog_id
");
$catalogStmt->execute([':catalog_id' => $catalog_id]);
$medicine = $catalogStmt->fetch(PDO::FETCH_ASSOC);

if (!$medicine) {
    header("Location: medicine_management.php");
    exit();
}

// Fetch batches for the given catalog_id
$batchStmt = $conn->prepare("
    SELECT batch_lot_number, manufacturing_date, expiration_date, stocks, 
           stock_status, expiry_status, source
    FROM medicine_batches
    WHERE catalog_id = :catalog_id
    ORDER BY expiration_date ASC
");
$batchStmt->execute([':catalog_id' => $catalog_id]);
$batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Batches - <?php echo htmlspecialchars($medicine['generic_name'] . ($medicine['brand_name'] ? ' (' . $medicine['brand_name'] . ')' : '')); ?></title>
    <link rel="stylesheet" href="css/medicine_management.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
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
                $dashboard_url = '';
                if ($adminRole === 'super_admin') {
                    $dashboard_url = 'superadmin_dashboard.php';
                } elseif ($adminRole === 'admin') {
                    $dashboard_url = 'admin_dashboard.php';
                } elseif ($adminRole === 'staff') {
                    $dashboard_url = 'staff_dashboard.php';
                }
            ?>
            <p class="menu-header">ANALYTICS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/dashboard_icon.png" alt="">
                <a href="<?= htmlspecialchars($dashboard_url) ?>" class="<?= $current_page == $dashboard_url ? 'active' : '' ?>">Dashboard</a>
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
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/med_icon_active.png" alt="">
                <a href="medicine_management.php" class="<?= ($current_page == 'medicine_management.php' || $current_page == 'view_batches.php') ? 'active' : '' ?>">Medicine Management</a>
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

    <div class="content">
        <div class="med-container">
            <div class="sort-controls">
                <div style="display: flex; gap: 15px; align-items: center;">
                    <a href="#" class="back-button" onclick="history.back(); return false;">← Back</a>
                    <h2>Batches for <?php echo htmlspecialchars($medicine['generic_name'] . ($medicine['brand_name'] ? ' (' . $medicine['brand_name'] . ' ' . $medicine['dosage'] .')' : '')); ?></h2></h2>
                </div>
                <div class="search-con">
                    <button class="add-batch-btn" onclick="openModal()">ADD BATCH</button>
                </div>
            </div>

            <div class="table-details">
                <div class="table-con">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Batch Lot Number</th>
                                    <th>Manufacturing Date</th>
                                    <th>Expiration Date</th>
                                    <th>Stocks</th>
                                    <th>Stock Status</th>
                                    <th>Expiry Status</th>
                                    <th>Source</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($batches)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center;">No batches found for this medicine.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($batches as $batch): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($batch['batch_lot_number']) ?></td>
                                            <td><?= htmlspecialchars($batch['manufacturing_date'] ?: 'N/A') ?></td>
                                            <td><?= htmlspecialchars($batch['expiration_date'] ?: 'N/A') ?></td>
                                            <td><?= htmlspecialchars($batch['stocks']) ?></td>
                                            <td><?= htmlspecialchars($batch['stock_status']) ?></td>
                                            <td><?= htmlspecialchars($batch['expiry_status']) ?></td>
                                            <td><?= htmlspecialchars($batch['source'] ?: 'N/A') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="details-panel" id="detailsPanel">
                    <div id="detailsContent">
                        <div style="display: flex; justify-content: center; align-items: center; height: 100%;">
                            <p style="margin: 30px;">Select a medicine to view details.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>