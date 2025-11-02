<?php
// view_expiring.php
session_start();
require_once "config.php";

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'health_staff') {
    header("Location: login.php");
    exit();
}

// Get search term from GET request and sanitize it
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle filter parameter
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';
$validFilters = ['all', 'expired', 'week', 'month'];
if (!in_array($filter, $validFilters)) {
    $filter = 'all';
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

// Define expiry threshold: medicines expiring within 60 days
$expiryThreshold = date('Y-m-d', strtotime('+60 days'));

// Fetch ONLY expiring medicines (within 60 days OR expired/expiring soon)
$expiryQuery = "
    SELECT 
        mc.id AS catalog_id, 
        mc.generic_name, 
        mc.brand_name, 
        mc.dosage, 
        mc.dosage_form, 
        mc.unit,
        mb.id AS batch_id, 
        mb.batch_lot_number, 
        mb.expiration_date, 
        mb.stocks, 
        mb.expiry_status
    FROM medicines_catalog mc
    JOIN medicine_batches mb ON mc.id = mb.catalog_id
    WHERE (mb.expiration_date <= :expiryThreshold
       OR mb.expiry_status IN ('Expiring within a month', 'Expiring within a week', 'Expired'))
      AND mb.stocks >= 0
";

// Apply status filter
if ($filter !== 'all') {
    if ($filter === 'expired') {
        $expiryQuery .= " AND mb.expiry_status = 'Expired'";
    } elseif ($filter === 'week') {
        $expiryQuery .= " AND mb.expiry_status = 'Expiring within a week'";
    } elseif ($filter === 'month') {
        $expiryQuery .= " AND mb.expiry_status = 'Expiring within a month'";
    }
}

// Search filter
if (!empty($searchTerm)) {
    $expiryQuery .= " AND (mc.generic_name LIKE :search 
                        OR mc.brand_name LIKE :search 
                        OR mb.batch_lot_number LIKE :search 
                        OR mc.dosage LIKE :search)";
}

$expiryQuery .= " ORDER BY mb.expiration_date ASC";

$expiryStmt = $conn->prepare($expiryQuery);
$expiryStmt->bindParam(':expiryThreshold', $expiryThreshold);

if (!empty($searchTerm)) {
    $searchParam = "%$searchTerm%";
    $expiryStmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
}

$expiryStmt->execute();
$expiringMedicines = $expiryStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expiring Medicines</title>
    <link rel="stylesheet" href="css/medicine_management.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
                $dashboard_url = 'healthstaff_dashboard.php'; // health_staff only
            ?>
            <p class="menu-header">ANALYTICS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/dashboard_icon.png" alt="">
                <a href="<?= $dashboard_url ?>" class="<?= $current_page == $dashboard_url ? 'active' : '' ?>">Dashboard</a>
            </div>
            
            <p class="menu-header">BASE</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/patient_icon.png" alt="">
                <a href="patient_management.php" class="<?= $current_page == 'patient_management.php' ? 'active' : '' ?>">Patient Management</a>
            </div>
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/med_icon_active.png" alt="">
                <a href="medicine_management.php" class="<?= in_array($current_page, ['medicine_management.php', 'view_expiring.php']) ? 'active' : '' ?>">Medicine Management</a>
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
        <div class="title-con">
            <div class="title">
                <a href="medicine_management.php" class="back-button">Back</a>
                <h2>Expiring Medicines (Within 60 Days)</h2>
            </div>
        </div>

        <div class="med-container">
            <div class="sort-controls">
                <div class="search-filter">
                    <div class="search-con">
                        <form method="GET" action="view_expiring.php">
                            <input type="text" name="search" placeholder="Search by Generic, Brand, Batch Lot, or Dosage" value="<?= htmlspecialchars($searchTerm) ?>">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            <button type="submit">Search</button>
                        </form>
                    </div>
                    <div class="filter-buttons" data-initial-filter="<?= $filter ?>">
                        <button class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>" data-filter="all" title="All (60 Days)">
                            <i class="fas fa-calendar-alt"></i>
                        </button>
                        <button class="filter-btn <?= $filter === 'expired' ? 'active' : '' ?>" data-filter="expired" title="Expired">
                            <i class="fas fa-skull-crossbones" ></i>
                        </button>
                        <button class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>" data-filter="week" title="Expiring in 7 Days">
                            <i class="fas fa-exclamation-triangle" ></i>
                        </button>
                        <button class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>" data-filter="month" title="Expiring in 30 Days">
                            <i class="fas fa-clock"></i>
                        </button>
                    </div>
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
                                    <th>Batch Lot</th>
                                    <th>Expiration Date</th>
                                    <th>Stocks</th>
                                    <th>Expiry Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($expiringMedicines)): ?>
                                    <tr>
                                        <td colspan="10" style="text-align: center; color: #666;">
                                            No medicines expiring within 60 days.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($expiringMedicines as $med): ?>
                                        <?php 
                                            $daysLeft = $med['expiration_date'] 
                                                ? floor((strtotime($med['expiration_date']) - time()) / (60*60*24)) 
                                                : null;
                                            $expiryClass = '';
                                            if ($med['expiry_status'] === 'Expired' || ($daysLeft !== null && $daysLeft < 0)) {
                                                $expiryClass = 'critical';
                                            } elseif ($med['expiry_status'] === 'Expiring within a week' || ($daysLeft !== null && $daysLeft <= 7)) {
                                                $expiryClass = 'critical';
                                            } elseif ($med['expiry_status'] === 'Expiring within a month' || ($daysLeft !== null && $daysLeft <= 30)) {
                                                $expiryClass = 'warning';
                                            }
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($med['generic_name']) ?></td>
                                            <td><?= htmlspecialchars($med['brand_name'] ?: 'N/A') ?></td>
                                            <td><?= htmlspecialchars($med['dosage'] ?: 'N/A') ?></td>
                                            <td><?= htmlspecialchars($med['dosage_form'] ?: 'N/A') ?></td>
                                            <td><?= htmlspecialchars($med['unit'] ?: 'N/A') ?></td>
                                            <td><?= htmlspecialchars($med['batch_lot_number']) ?></td>
                                            <td class="<?= $expiryClass ?>">
                                                <?= htmlspecialchars($med['expiration_date']) ?>
                                                <?php if ($daysLeft !== null && $daysLeft >= 0): ?>
                                                    <small>(<?= $daysLeft ?> days left)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($med['stocks']) ?></td>
                                            <td class="<?= $expiryClass ?>">
                                                <?= htmlspecialchars($med['expiry_status']) ?>
                                            </td>
                                            <td>
                                                <a href="view_batches.php?catalog_id=<?= $med['catalog_id'] ?>" class="viewBatch-btn">
                                                    View Batch
                                                </a>
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
    <script>
        // EXPIRING FILTERS – CLIENT-SIDE URL UPDATE
        document.addEventListener('DOMContentLoaded', function () {
            const filterButtons = document.querySelectorAll('.filter-btn');

            filterButtons.forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();

                    const filter = this.getAttribute('data-filter');
                    const url = new URL(window.location);
                    url.searchParams.set('filter', filter);

                    // Keep search term if exists
                    const searchInput = document.querySelector('input[name="search"]');
                    if (searchInput && searchInput.value.trim()) {
                        url.searchParams.set('search', searchInput.value.trim());
                    } else {
                        url.searchParams.delete('search');
                    }

                    window.location = url.toString();
                });
            });
        });
        </script>
</body>
</html>