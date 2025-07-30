<?php
// medicine_management.php
session_start();
require_once "config.php"; // Include database connection

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch Admin's Name
$adminId = $_SESSION['admin_id'];
$adminStmt = $conn->prepare("SELECT * FROM admin_staff WHERE id = :id");
$adminStmt->bindParam(':id', $adminId);
$adminStmt->execute();
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

// Default to session information if query fails
$adminName = $admin ? $admin['full_name'] : $_SESSION['admin_name'];
$adminRole = $admin ? $admin['role'] : $_SESSION['admin_role'];

// Format role for display (convert super_admin to Super Admin)
$displayRole = ucwords(str_replace('_', ' ', $adminRole));

// Pagination settings
$itemsPerPage = 10; // Number of medicines per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Current page, default is 1
$offset = ($page - 1) * $itemsPerPage; // Offset for SQL query

// Search and filter functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$therapeutic_category = isset($_GET['therapeutic_category']) ? trim($_GET['therapeutic_category']) : '';
$dosage_form = isset($_GET['dosage_form']) ? trim($_GET['dosage_form']) : '';
$stock_status = isset($_GET['stock_status']) ? trim($_GET['stock_status']) : '';
$expiry_status = isset($_GET['expiry_status']) ? trim($_GET['expiry_status']) : '';

$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(generic_name LIKE :search OR brand_name LIKE :search OR therapeutic_category LIKE :search OR batch_lot_number LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($therapeutic_category)) {
    $conditions[] = "therapeutic_category = :therapeutic_category";
    $params[':therapeutic_category'] = $therapeutic_category;
}

if (!empty($dosage_form)) {
    $conditions[] = "dosage_form = :dosage_form";
    $params[':dosage_form'] = $dosage_form;
}

if (!empty($stock_status)) {
    $conditions[] = "stock_status = :stock_status";
    $params[':stock_status'] = $stock_status;
}

if (!empty($expiry_status)) {
    $conditions[] = "expiry_status = :expiry_status";
    $params[':expiry_status'] = $expiry_status;
} else {
    // Hide expired medicines by default
    $conditions[] = "expiry_status != 'Expired'";
}

$searchCondition = '';
if (!empty($conditions)) {
    $searchCondition = "WHERE " . implode(" AND ", $conditions);
}

// Count total medicines (for pagination)
$countQuery = "SELECT COUNT(*) FROM medicines $searchCondition";
$countStmt = $conn->prepare($countQuery);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

// Ensure the page is within valid range
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

// Fetch medicines with pagination
$query = "SELECT * FROM medicines $searchCondition ORDER BY id DESC LIMIT :offset, :limit";
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->execute();
$medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Update expiry_status and stock_status for all medicines
$currentDate = new DateTime(); // Fetches the current date and time dynamically

foreach ($medicines as &$medicine) {
    // Calculate expiry status
    $expirationDate = new DateTime($medicine['expiration_date']);
    $interval = $currentDate->diff($expirationDate);
    $daysUntilExpiry = $interval->days;

    if ($expirationDate < $currentDate) {
        $medicine['expiry_status'] = 'Expired';
    } elseif ($daysUntilExpiry <= 7) {
        $medicine['expiry_status'] = 'Expiring within a week';
    } elseif ($daysUntilExpiry <= 30) {
        $medicine['expiry_status'] = 'Expiring within a month';
    } else {
        $medicine['expiry_status'] = 'Valid';
    }

    // Update expiry_status in the database
    $updateStmt = $conn->prepare("UPDATE medicines SET expiry_status = :expiry_status WHERE id = :id");
    $updateStmt->execute([
        ':expiry_status' => $medicine['expiry_status'],
        ':id' => $medicine['id']
    ]);

    // Calculate stock status
    $stocks = (int)$medicine['stocks'];
    $minStock = (int)$medicine['min_stock'];

    if ($stocks == 0) {
        $medicine['stock_status'] = 'Out of Stock';
    } elseif ($stocks <= $minStock) {
        $medicine['stock_status'] = 'Low Stock';
    } else {
        $medicine['stock_status'] = 'In Stock';
    }

    // Update stock_status in the database
    $updateStmt = $conn->prepare("UPDATE medicines SET stock_status = :stock_status WHERE id = :id");
    $updateStmt->execute([
        ':stock_status' => $medicine['stock_status'],
        ':id' => $medicine['id']
    ]);
}
unset($medicine);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Management</title>
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

    <div class="content">
        <div class="med-container">
            <div class="sort-controls">
                <div class="search-con">
                    <form method="GET" action="" id="filterForm">
                        <div class="filter-row">
                            <input type="text" name="search" class="search" placeholder="Search Medicine" value="<?= htmlspecialchars($search) ?>">
                            <select name="therapeutic_category">
                                <option value="">All Categories</option>
                                <?php
                                // Fetch unique therapeutic categories
                                $categoryStmt = $conn->query("SELECT DISTINCT therapeutic_category FROM medicines ORDER BY therapeutic_category");
                                while ($category = $categoryStmt->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($category['therapeutic_category'] === $therapeutic_category) ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($category['therapeutic_category']) . "' $selected>" . htmlspecialchars($category['therapeutic_category']) . "</option>";
                                }
                                ?>
                            </select>
                            <select name="dosage_form">
                                <option value="">All Dosage Forms</option>
                                <?php
                                // Fetch unique dosage forms
                                $dosageStmt = $conn->query("SELECT DISTINCT dosage_form FROM medicines WHERE dosage_form IS NOT NULL ORDER BY dosage_form");
                                while ($dosage = $dosageStmt->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($dosage['dosage_form'] === $dosage_form) ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($dosage['dosage_form']) . "' $selected>" . htmlspecialchars($dosage['dosage_form']) . "</option>";
                                }
                                ?>
                            </select>
                            <select name="stock_status">
                                <option value="">All Stock Status</option>
                                <option value="In Stock" <?= $stock_status === 'In Stock' ? 'selected' : '' ?>>In Stock</option>
                                <option value="Low Stock" <?= $stock_status === 'Low Stock' ? 'selected' : '' ?>>Low Stock</option>
                                <option value="Out of Stock" <?= $stock_status === 'Out of Stock' ? 'selected' : '' ?>>Out of Stock</option>
                            </select>
                            <select name="expiry_status">
                                <option value="">All Expiry Status</option>
                                <option value="Valid" <?= $expiry_status === 'Valid' ? 'selected' : '' ?>>Valid</option>
                                <option value="Expiring within a month" <?= $expiry_status === 'Expiring within a month' ? 'selected' : '' ?>>Expiring within a month</option>
                                <option value="Expiring within a week" <?= $expiry_status === 'Expiring within a week' ? 'selected' : '' ?>>Expiring within a week</option>
                                <option value="Expired" <?= $expiry_status === 'Expired' ? 'selected' : '' ?>>Expired</option>
                            </select>
                            <a href="medicine_management.php" class="clear-btn">Clear Filters</a>
                        </div>
                    </form>
                    <button class="add-med-btn" onclick="openModal()">ADD MEDICINE</button>
                </div>
            </div>

            

            <!-- Medicine Table -->
            <div class="table-details">
                <div class="table-con">
                    <div class="legend">
                        <span class="legend-item valid">Valid</span>
                        <span class="legend-item expiring-month">Expiring within a month</span>
                        <span class="legend-item expiring-week">Expiring within a week</span>
                        <span class="legend-item expired">Expired</span>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Therapeutic Category</th>
                                    <th>Batch/Lot No.</th>
                                    <th>Generic Name</th>
                                    <th>Brand Name</th>
                                    <th>Dosage</th>
                                    <th>Dosage Form</th>
                                    <th>Unit</th>
                                    <th>Expiration Date</th>
                                    <th>Stocks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($medicines)): ?>
                                    <tr>
                                        <td colspan="10" class="no-medicines" style="text-align: center;">No medicines found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($medicines as $medicine): ?>
                                        <?php 
                                            $rowClass = '';
                                            switch($medicine['expiry_status']) {
                                                case 'Expiring within a month':
                                                    $rowClass = 'expiring-month';
                                                    break;
                                                case 'Expiring within a week':
                                                    $rowClass = 'expiring-week';
                                                    break;
                                                case 'Expired':
                                                    $rowClass = 'expired';
                                                    break;
                                                default:
                                                    $rowClass = 'valid';
                                            }
                                        ?>
                                        <tr class="status-<?= $rowClass ?>" onclick="selectRow(this); showDetails(<?= htmlspecialchars(json_encode($medicine)) ?>)">
                                            <td><?= htmlspecialchars($medicine['therapeutic_category']) ?></td>
                                            <td><?= htmlspecialchars($medicine['batch_lot_number']) ?></td>
                                            <td><?= htmlspecialchars($medicine['generic_name']) ?></td>
                                            <td><?= htmlspecialchars($medicine['brand_name']) ?></td>
                                            <td><?= htmlspecialchars($medicine['dosage']) ?></td>
                                            <td><?= htmlspecialchars($medicine['dosage_form']) ?></td>
                                            <td><?= htmlspecialchars($medicine['unit']) ?></td>
                                            <td><?= htmlspecialchars($medicine['expiration_date']) ?></td>
                                            <td><?= htmlspecialchars($medicine['stocks']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Controls -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-container">
                            <div class="pagination-info">
                                Showing <?= ($offset + 1) ?>-<?= min($offset + $itemsPerPage, $totalItems) ?> of <?= $totalItems ?> entries
                            </div>
                            <div class="pagination-controls">
                                <?php
                                // Build query string with all filter parameters
                                $queryParams = [];
                                if (!empty($search)) $queryParams['search'] = urlencode($search);
                                if (!empty($therapeutic_category)) $queryParams['therapeutic_category'] = urlencode($therapeutic_category);
                                if (!empty($dosage_form)) $queryParams['dosage_form'] = urlencode($dosage_form);
                                if (!empty($stock_status)) $queryParams['stock_status'] = urlencode($stock_status);
                                if (!empty($expiry_status)) $queryParams['expiry_status'] = urlencode($expiry_status);
                                $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                                ?>
                                <a href="?page=<?= max(1, $page - 1) ?><?= $queryString ?>" 
                                class="pagination-button <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <
                                </a>
                                <a href="#" class="pagination-button active"><?= $page ?></a>
                                <a href="?page=<?= min($totalPages, $page + 1) ?><?= $queryString ?>" 
                                class="pagination-button <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    >
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
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

    <!-- Add Medicine Modal -->
    <div id="medicineModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Add Medicine</h2>

            <form method="POST" action="add_medicine.php" id="addMedicineForm">
                <div class="form-group">
                    <label>Therapeutic Category</label>
                    <input type="text" name="therapeutic_category" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Batch/Lot Number</label>
                        <input type="text" name="batch_lot_number" required>
                    </div>

                    <div class="form-group">
                        <label>P.O. Number</label>
                        <input type="text" name="pono">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Generic Name</label>
                    <input type="text" name="generic_name" required>
                </div>

                <div class="form-group">
                    <label>Brand Name</label>
                    <input type="text" name="brand_name">
                </div>

                <div class="form-group">
                    <label>Dosage Form</label>
                    <select name="dosage_form" required>
                        <option value="" disabled selected>Select Dosage Form</option>
                        <option value="Tablet">Tablet</option>
                        <option value="Capsule">Capsule</option>
                        <option value="Syrup">Syrup</option>
                        <option value="Suspension">Suspension</option>
                        <option value="Cream">Cream</option>
                        <option value="Drops">Drops</option>
                        <option value="Ointment">Ointment</option>
                        <option value="Cream">Cream</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Dosage</label>
                        <input type="text" name="dosage" required>
                    </div>
                    <div class="form-group">
                        <label>Unit</label>
                        <select name="unit" required>
                            <option value="" disabled selected>Select Unit</option>
                            <option value="PCS">PCS</option>
                            <option value="TABS">TABS</option>
                            <option value="CAPS">CAPS</option>
                            <option value="BOTTLE">BOTTLE</option>
                            <option value="BOX">BOX</option>
                            <option value="SACHET">SACHET</option>
                            <option value="AMPULE">AMPULE</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Manufacturing Date</label>
                        <input type="date" name="manufacturing_date">
                    </div>
                    <div class="form-group">
                        <label>Expiration Date</label>
                        <input type="date" name="expiration_date" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Source</label>
                    <input type="text" name="source">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Initial Stock</label>
                        <input type="number" name="stocks" min="0" value="0" placeholder="Enter initial stock">
                    </div>
                    <div class="form-group">
                        <label>Minimum Stock</label>
                        <input type="number" name="min_stock" min="1" required placeholder="Enter minimum stock">
                    </div>
                </div>

                <div class="button-group">
                    <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="save-btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function selectRow(row) {
        // Remove 'selected' class from all rows
        document.querySelectorAll('tbody tr').forEach(tr => tr.classList.remove('selected'));
        // Add 'selected' class to the clicked row
        row.classList.add('selected');
    }

    let selectedMedicineId = null;

    function showDetails(medicine) {
        selectedMedicineId = medicine.id;

        // Fetch stock history for the selected medicine
        fetch('get_stock_history.php?id=' + encodeURIComponent(medicine.id))
            .then(response => response.json())
            .then(history => {
                let historyRows = '';
                if (history.length > 0) {
                    history.forEach(entry => {
                        historyRows += `
                            <tr>
                                <td>${entry.changed_at}</td>
                                <td>${entry.quantity_change > 0 ? '+' : ''}${entry.quantity_change}</td>
                                <td>${entry.reason}</td>
                                <td>${entry.changed_by_name || 'Unknown'}</td>
                            </tr>
                        `;
                    });
                } else {
                    historyRows = '<tr><td colspan="4" style="text-align: center;">No stock history available.</td></tr>';
                }

                const detailsPanel = document.getElementById('detailsPanel');
                const detailsContent = `
                    <div class="tabs-buttons">
                        <div class="details-tabs">  
                            <button class="tab active" onclick="switchTab('details')">Details</button>
                            <button class="tab" onclick="switchTab('addStock')">Add Stock</button>
                            <button class="tab" onclick="switchTab('history')">Stock History</button>
                        </div>                            
                    </div>

                    <div id="tab-content">
                        <div id="detailsTab" class="tab-page">
                            <div class="details-buttons">
                                <button class="edit-btn" id="editBtn" onclick="enableEditing()">Edit</button>
                                <button class="delete-btn" onclick="deleteMedicine()">Delete</button>
                            </div>
                            <form id="medicineForm">
                                <div class="details-fields">
                                    <div class="field">
                                        <label>Therapeutic Category</label>
                                        <input type="text" name="therapeutic_category" value="${medicine.therapeutic_category}" readonly>
                                    </div>
                                
                                    <div class="row">
                                        <div class="field">
                                            <label>Batch/Lot Number</label>
                                            <input type="text" name="batch_lot_number" value="${medicine.batch_lot_number}" readonly>
                                        </div>
                                        <div class="field">
                                            <label>P.O.NO.</label>
                                            <input type="text" name="pono" value="${medicine.pono || ''}" readonly>
                                        </div>
                                    </div>
    
                                    <div class="field">
                                        <label>Generic Name</label>
                                        <input type="text" name="generic_name" value="${medicine.generic_name}" readonly>
                                    </div>
                                    <div class="field">
                                        <label>Brand Name</label>
                                        <input type="text" name="brand_name" value="${medicine.brand_name || ''}" readonly>
                                    </div>
                                    <div class="field">
                                        <label>Dosage Form</label>
                                        <input type="text" name="dosage_form" value="${medicine.dosage_form || ''}" readonly>
                                    </div>

                                    <div class="row">
                                        <div class="field">
                                            <label>Dosage</label>
                                            <input type="text" name="dosage" value="${medicine.dosage || ''}" readonly>
                                        </div>
                                        <div class="field">
                                            <label>Unit</label>
                                            <input type="text" name="unit" value="${medicine.unit || ''}" readonly>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="field">
                                            <label>Manufacturing Date</label>
                                            <input type="date" name="manufacturing_date" value="${medicine.manufacturing_date}" readonly>
                                        </div>
                                        <div class="field">
                                            <label>Expiration Date</label>
                                            <input type="date" name="expiration_date" value="${medicine.expiration_date}" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="field">
                                        <label>Source</label>
                                        <input type="text" name="source" value="${medicine.source || ''}" readonly>
                                    </div>
                                    <div class="field">
                                        <label>Minimum Stocks</label>
                                        <input type="number" name="min_stock" value="${medicine.min_stock}" readonly>
                                    </div>
                                </div>

                                <div class="action-buttons" style="display:none; text-align:center; margin-top:20px;">
                                    <button type="button" class="save-btn" onclick="saveChanges()">Save</button>
                                    <button type="button" class="cancel-btn" onclick="cancelEditing()">Cancel</button>
                                </div>
                            </form>
                        </div>

                        <div id="addStockTab" class="tab-page" style="display:none;">
                            <form id="addStockForm">
                                <div class="details-fields">
                                    <div class="field">
                                        <label>Current Stocks</label>
                                        <input type="number" name="current_stocks" value="${medicine.stocks}" readonly>
                                    </div>
                                    <div class="field">
                                        <label>Add Stock</label>
                                        <input type="number" name="add_stock" min="1" required placeholder="Enter quantity to add">
                                    </div>
                                </div>
                                <button type="button" class="save-btn" onclick="addStock()" style="text-align:center; margin-top:20px;">Add Stock</button>
                            </form>
                        </div>

                        <div id="historyTab" class="tab-page" style="display:none;">
                            <table class="stock-history-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Quantity Change</th>
                                        <th>Reason</th>
                                        <th>Changed By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${historyRows}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
                document.getElementById('detailsContent').innerHTML = detailsContent;
            })
            .catch(error => {
                console.error('Error fetching stock history:', error);
                document.getElementById('detailsContent').innerHTML = '<p>Error loading stock history.</p>';
            });
    }

    function enableEditing() {
        const inputs = document.querySelectorAll('#medicineForm input');
        inputs.forEach(input => {
            if (input.name !== 'stocks') { // Prevent editing of stocks field
                input.removeAttribute('readonly');
            }
        });

        document.querySelectorAll('.action-buttons').forEach(actionBtn => {
            actionBtn.style.display = 'flex';
        });

        const editBtn = document.getElementById('editBtn');
        if (editBtn) {
            editBtn.disabled = true;
            editBtn.style.opacity = '0.6';
            editBtn.style.cursor = 'not-allowed';
        }
    }

    function saveChanges() {
        if (!confirm('Are you sure you want to save changes to this medicine?')) {
            return;
        }

        const medicineForm = document.getElementById('medicineForm');
        const formData = new FormData(medicineForm);

        // Add the medicine ID and admin ID
        formData.append('id', selectedMedicineId);
        formData.append('admin_id', '<?= $adminId ?>');

        fetch('update_medicine.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating.');
        });

        document.getElementById('editBtn').disabled = false;
    }

    function addStock() {
        const addStockInput = document.querySelector('#addStockForm input[name="add_stock"]');
        const quantity = addStockInput.value;
        if (!confirm(`Are you sure you want to add ${quantity} to the stock?`)) {
            return;
        }

        const addStockForm = document.getElementById('addStockForm');
        const formData = new FormData(addStockForm);
        formData.append('medicine_id', selectedMedicineId);
        formData.append('admin_id', '<?= $adminId ?>');

        fetch('add_stock.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while adding stock.');
        });
    }

    function cancelEditing() {
        const medicineInputs = document.querySelectorAll('#medicineForm input');
        medicineInputs.forEach(input => {
            input.setAttribute('readonly', true);
        });

        document.querySelectorAll('.action-buttons').forEach(actionBtn => {
            actionBtn.style.display = 'none';
        });

        const editBtn = document.getElementById('editBtn');
        if (editBtn) {
            editBtn.disabled = false;
            editBtn.style.opacity = '1';
            editBtn.style.cursor = 'pointer';
        }
    }

    function deleteMedicine() {
        if (confirm('Are you sure you want to delete this medicine?')) {
            fetch('delete_medicine.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + encodeURIComponent(selectedMedicineId)
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting.');
            });
        }
    }

    function switchTab(tabName) {
        cancelEditing();
        const tabs = document.querySelectorAll('.tab');
        const tabPages = document.querySelectorAll('.tab-page');

        tabs.forEach(tab => tab.classList.remove('active'));
        tabPages.forEach(page => page.style.display = 'none');

        if (tabName === 'details') {
            document.getElementById('detailsTab').style.display = 'block';
            tabs[0].classList.add('active');
        } else if (tabName === 'addStock') {
            document.getElementById('addStockTab').style.display = 'block';
            tabs[1].classList.add('active');
        } else if (tabName === 'history') {
            document.getElementById('historyTab').style.display = 'block';
            tabs[2].classList.add('active');
        }
    }

    function openModal() {
        document.getElementById("medicineModal").style.display = "flex";
    }

    function closeModal() {
        document.getElementById("medicineModal").style.display = "none";
    }

    window.onclick = function(event) {
        let modal = document.getElementById("medicineModal");
        if (event.target === modal) {
            modal.style.display = "none";
        }
    };

    document.querySelectorAll('#filterForm select').forEach(select => {
        select.addEventListener('change', () => {
            document.getElementById('filterForm').submit();
        });
    });
</script>
</body>
</html>