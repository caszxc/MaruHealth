<?php
// medicine_management.php
session_start();
require_once "config.php"; // Include database connection
require_once "update_inventory.php"; // Include database connection
include 'settings.php';

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
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
// Handle search query
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchQuery = "%" . $searchTerm . "%";

// Handle filter parameter
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';
$validFilters = ['all', 'low', 'out'];
if (!in_array($filter, $validFilters)) {
    $filter = 'all';
}

// Build query with stock_status
$catalogQuery = "
    SELECT 
        c.id, 
        c.therapeutic_category, 
        c.generic_name, 
        c.brand_name, 
        c.dosage, 
        c.dosage_form, 
        c.unit, 
        c.min_stock,
        c.stock_status
    FROM medicines_catalog c
";

$whereConditions = [];
$params = [];

// Search filter
if (!empty($searchTerm)) {
    $whereConditions[] = "(c.generic_name LIKE :search 
                       OR c.brand_name LIKE :search 
                       OR c.therapeutic_category LIKE :search)";
    $params[':search'] = $searchQuery;
}

// Stock status filter
if ($filter === 'low') {
    $whereConditions[] = "c.stock_status = 'Low Stock'";
} elseif ($filter === 'out') {
    $whereConditions[] = "c.stock_status = 'Out of Stock'";
}

if (!empty($whereConditions)) {
    $catalogQuery .= " WHERE " . implode(" AND ", $whereConditions);
}

$catalogQuery .= " ORDER BY c.generic_name ASC";

$catalogStmt = $conn->prepare($catalogQuery);
foreach ($params as $key => $value) {
    $catalogStmt->bindValue($key, $value);
}
$catalogStmt->execute();
$catalogs = $catalogStmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $conn->query("SELECT DISTINCT therapeutic_category FROM medicines_catalog ORDER BY therapeutic_category")->fetchAll(PDO::FETCH_COLUMN);
$generics = $conn->query("SELECT DISTINCT generic_name FROM medicines_catalog ORDER BY generic_name")->fetchAll(PDO::FETCH_COLUMN);
$brands = $conn->query("SELECT DISTINCT brand_name FROM medicines_catalog WHERE brand_name IS NOT NULL ORDER BY brand_name")->fetchAll(PDO::FETCH_COLUMN);
?>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .message {
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            transition: opacity .5s ease-in-out;
            margin: 10px 0;
        }
        .message.success { background:#dff0d8; color:#3c763d; border:1px solid #d6e9c6; }
        .message.error   { background:#f2dede; color:#a94442; border:1px solid #ebccd1; }
    </style>
</head>
<body>
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
                $dashboard_url = '';
                if ($adminRole === 'super_admin') {
                    $dashboard_url = 'superadmin_dashboard.php';
                } elseif ($adminRole === 'admin') {
                    $dashboard_url = 'admin_dashboard.php';
                } elseif ($adminRole === 'health_staff') {
                    $dashboard_url = 'healthstaff_dashboard.php';
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
            <?php if ($adminRole == 'super_admin' || $adminRole == 'health_staff'): ?>
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
                <div class="search-filter">
                    <div class="search-con">
                        <form method="GET" action="medicine_management.php">
                            <input type="text" name="search" placeholder="Search medicines..." value="<?= htmlspecialchars($searchTerm) ?>">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            <button type="submit">Search</button>
                        </form>
                    </div>

                    <div class="filter-buttons" data-initial-filter="<?= $filter ?>">
                        <button class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>" data-filter="all" title="Show All">
                            <i class="fas fa-boxes"></i>
                        </button>
                        <button class="filter-btn <?= $filter === 'low' ? 'active' : '' ?>" data-filter="low" title="Low Stock">
                            <i class="fas fa-exclamation-triangle"></i>
                        </button>
                        <button class="filter-btn <?= $filter === 'out' ? 'active' : '' ?>" data-filter="out" title="Out of Stock">
                            <i class="fas fa-ban"></i>
                        </button>
                    </div>
                </div>

                <div class="button-con">
                    <button class="add-med-btn" onclick="openModal()">ADD MEDICINE</button>
                    <a href="view_expiring.php" class="expiring-btn">View Expiring Medicines</a>
                </div>
            </div>
            <!-- ----- SUCCESS / ERROR MESSAGE ----- -->
            <?php if (isset($_SESSION['medicine_message'])): ?>
                <?php
                $isSuccess = strpos($_SESSION['medicine_message'], 'successfully') !== false || 
                            strpos($_SESSION['medicine_message'], 'added') !== false ||
                            strpos($_SESSION['medicine_message'], 'updated') !== false ||
                            strpos($_SESSION['medicine_message'], 'deleted') !== false;
                ?>
                <div class="message <?= $isSuccess ? 'success' : 'error' ?>">
                    <?= nl2br(htmlspecialchars($_SESSION['medicine_message'])) ?>
                </div>
                <?php unset($_SESSION['medicine_message']); ?>
            <?php endif; ?>
            <div class="table-details">
                <div class="table-con">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Therapeutic Category</th>
                                    <th>Generic Name</th>
                                    <th>Brand Name</th>
                                    <th>Dosage</th>
                                    <th>Dosage Form</th>
                                    <th>Unit</th>
                                    <th>Minimum Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($catalogs)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center;">No medicines found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($catalogs as $catalog): 
                                        $statusClass = '';
                                        if ($catalog['stock_status'] === 'Out of Stock') {
                                            $statusClass = 'out-of-stock';
                                        } elseif ($catalog['stock_status'] === 'Low Stock') {
                                            $statusClass = 'low-stock';
                                        }
                                    ?>
                                    <tr 
                                        onclick="selectRow(this); showDetails(<?= htmlspecialchars(json_encode($catalog)) ?>)"
                                        class="<?= $statusClass ?>"
                                        data-status="<?= strtolower(str_replace(' ', '-', $catalog['stock_status'])) ?>">
                                        <td><?= htmlspecialchars($catalog['therapeutic_category']) ?></td>
                                        <td><?= htmlspecialchars($catalog['generic_name']) ?></td>
                                        <td><?= htmlspecialchars($catalog['brand_name']) ?></td>
                                        <td><?= htmlspecialchars($catalog['dosage']) ?></td>
                                        <td><?= htmlspecialchars($catalog['dosage_form']) ?></td>
                                        <td><?= htmlspecialchars($catalog['unit']) ?></td>
                                        <td><?= htmlspecialchars($catalog['min_stock']) ?></td>
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

    <!-- Add Medicine Catalog Modal -->
    <div id="medicineModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Add Medicine</h2>
            <div class="form-scroll">
                <form method="POST" action="add_medicine.php" id="addMedicineForm">
                    <div class="form-group">
                        <label>Therapeutic Category</label>
                        <select name="therapeutic_category" class="select2" required>
                            <option value="" disabled selected>Select or type to add new</option>
                            <?php
                            $categoryStmt = $conn->query("SELECT DISTINCT therapeutic_category FROM medicines_catalog ORDER BY therapeutic_category");
                            while ($category = $categoryStmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='" . htmlspecialchars($category['therapeutic_category']) . "'>" . htmlspecialchars($category['therapeutic_category']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Generic Name</label>
                        <select name="generic_name" class="select2" required>
                            <option value="" disabled selected>Select or type to add new</option>
                            <?php
                            $genericStmt = $conn->query("SELECT DISTINCT generic_name FROM medicines_catalog ORDER BY generic_name");
                            while ($generic = $genericStmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='" . htmlspecialchars($generic['generic_name']) . "'>" . htmlspecialchars($generic['generic_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Brand Name</label>
                        <select name="brand_name" class="select2">
                            <option value="" disabled selected>Select or type to add new</option>
                            <?php
                            $brandStmt = $conn->query("SELECT DISTINCT brand_name FROM medicines_catalog WHERE brand_name IS NOT NULL ORDER BY brand_name");
                            while ($brand = $brandStmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='" . htmlspecialchars($brand['brand_name']) . "'>" . htmlspecialchars($brand['brand_name']) . "</option>";
                            }
                            ?>
                        </select>
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
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Dosage</label>
                            <input type="text" name="dosage" required autocomplete="off">
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
                            <label>Minimum Stock</label>
                            <input type="number" name="min_stock" min="1" required placeholder="Enter minimum stock">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                <button type="submit" form="addMedicineForm" class="save-btn">Save</button>
            </div>
        </div>
    </div>

    <!-- ==== FULL HISTORY MODAL ==== -->
    <div id="historyModal" class="modal" style="display:none;">
        <div class="modal-content" style="width:90%;">
            <h2>Medicine History</h2>
            <div class="history-container">
                <div class="medicine-name">
                    <h4>Medicine Name</h4>
                </div>
                <div id="fullHistoryContent">
                    <p>Loading full history...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeHistoryModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- jQuery and Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const preloadData = {
            categories: <?= json_encode($categories) ?>,
            generics: <?= json_encode($generics) ?>,
            brands: <?= json_encode($brands) ?>
        };

        document.addEventListener('DOMContentLoaded', function () {
            const msg = document.querySelector('.message');
            if (msg) {
                setTimeout(() => {
                    msg.style.opacity = '0';
                    setTimeout(() => msg.remove(), 500);
                }, 3000);
            }
        });
        // FILTER FUNCTIONALITY + AUTO-APPLY FROM URL
        document.addEventListener('DOMContentLoaded', function () {
            const filterButtons = document.querySelectorAll('.filter-btn');

            filterButtons.forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();

                    const filter = this.getAttribute('data-filter');
                    const url = new URL(window.location);
                    url.searchParams.set('filter', filter);

                    // Keep search term
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

        let selectedMedicineId = null;
        //CATALOG INVENTORY TABLE
        function selectRow(row) {
            // Remove 'selected' class from all rows
            document.querySelectorAll('tbody tr').forEach(tr => tr.classList.remove('selected'));
            // Add 'selected' class to the clicked row
            row.classList.add('selected');
        }

        function showDetails(medicine) {
            selectedMedicineId = medicine.id;

            const detailsContent = `
                <div class="tabs-buttons">
                    <div class="details-tabs">  
                        <button class="tab active" onclick="switchTab('details')">Details</button>
                        <button class="tab" onclick="switchTab('batchSummary')">Batch Summary</button>
                        <button class="tab" onclick="switchTab('history')">History</button>
                    </div>                            
                </div>

                <div id="tab-content">
                    <div id="detailsTab" class="tab-page">
                        <div class="details-buttons">
                            <button class="viewBatch-btn" id="viewBatchBtn" onclick="window.location.href='view_batches.php?catalog_id=${medicine.id}'">View Batches</button>
                            <button class="edit-btn" id="editBtn" onclick="enableEditing()">Edit</button>
                            <button class="delete-btn" onclick="deleteMedicine()">Delete</button>
                        </div>
                        <div class="form-scroll">
                            <form id="medicineForm">
                                <div class="details-fields">
                                    <div class="field">
                                        <label>Therapeutic Category</label>
                                        <select name="therapeutic_category" class="select2-edit" disabled>
                                            <option value="${medicine.therapeutic_category}" selected>${medicine.therapeutic_category}</option>
                                        </select>
                                    </div>

                                    <div class="field">
                                        <label>Generic Name</label>
                                        <select name="generic_name" class="select2-edit" disabled>
                                            <option value="${medicine.generic_name}" selected>${medicine.generic_name}</option>
                                        </select>                                    
                                    </div>
                                    <div class="field">
                                        <label>Brand Name</label>
                                        <select name="brand_name" class="select2-edit" disabled>
                                            <option value="${medicine.brand_name || ''}" selected>${medicine.brand_name || ''}</option>
                                        </select>
                                    </div>

                                    <div class="field">
                                        <label>Dosage Form</label>
                                        <select name="dosage_form" required disabled>
                                            <option value="">Select Dosage Form</option>
                                            <option value="Tablet" ${medicine.dosage_form === 'Tablet' ? 'selected' : ''}>Tablet</option>
                                            <option value="Capsule" ${medicine.dosage_form === 'Capsule' ? 'selected' : ''}>Capsule</option>
                                            <option value="Syrup" ${medicine.dosage_form === 'Syrup' ? 'selected' : ''}>Syrup</option>
                                            <option value="Suspension" ${medicine.dosage_form === 'Suspension' ? 'selected' : ''}>Suspension</option>
                                            <option value="Cream" ${medicine.dosage_form === 'Cream' ? 'selected' : ''}>Cream</option>
                                            <option value="Drops" ${medicine.dosage_form === 'Drops' ? 'selected' : ''}>Drops</option>
                                            <option value="Ointment" ${medicine.dosage_form === 'Ointment' ? 'selected' : ''}>Ointment</option>
                                        </select>
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

                                    <div class="field">
                                        <label>Minimum Stocks</label>
                                        <input type="number" name="min_stock" value="${medicine.min_stock}" readonly>
                                    </div>
                                </div>
                            </form>
                        </div>   
                        <div class="action-buttons" style="display:none;">
                            <button type="button" class="save-btn" onclick="saveChanges()">Save</button>
                            <button type="button" class="cancel-btn" onclick="cancelEditing()">Cancel</button>
                        </div> 
                    </div>

                    <div id="batchSummaryTab" class="tab-page" style="display:none;">
                        <div class="details-fields">
                            <p>Loading batch summary...</p>
                        </div>
                    </div>

                    <div id="historyTab" class="tab-page" style="display:none;">
                        
                    </div>
                </div>
            `;

            document.getElementById('detailsContent').innerHTML = detailsContent;

            // Initialize Select2 on the three fields (disabled initially)
            $('.select2-edit').select2({
                tags: true,
                placeholder: "Type to search or add new",
                allowClear: true,
                data: [] // Will populate below
            });

            // Populate options from preloaded data
            $('#medicineForm select[name="therapeutic_category"]').select2('destroy').select2({
                tags: true,
                data: preloadData.categories.map(c => ({ id: c, text: c }))
            }).val(medicine.therapeutic_category).trigger('change').prop('disabled', true);

            $('#medicineForm select[name="generic_name"]').select2('destroy').select2({
                tags: true,
                data: preloadData.generics.map(g => ({ id: g, text: g }))
            }).val(medicine.generic_name).trigger('change').prop('disabled', true);

            $('#medicineForm select[name="brand_name"]').select2('destroy').select2({
                tags: true,
                data: preloadData.brands.map(b => ({ id: b, text: b }))
            }).val(medicine.brand_name || '').trigger('change').prop('disabled', true);

            // === CHECK EDITABLE & DELETABLE ===
            fetch('check_catalog_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'catalog_id=' + medicine.id
            })
            .then(r => r.json())
            .then(data => {
                const editBtn = document.getElementById('editBtn');
                const deleteBtn = document.querySelector('.delete-btn');

                // Edit Button
                if (data.editable) {
                    editBtn.disabled = false;
                    editBtn.title = "Edit medicine details";
                    editBtn.onclick = enableEditing;
                } else {
                    editBtn.disabled = true;
                    editBtn.title = "Cannot edit: Batches exist";
                    editBtn.onclick = () => alert("Cannot edit: This medicine has associated batches.");
                    editBtn.style.opacity = '0.5';
                    editBtn.style.cursor = 'not-allowed';
                }

                // Delete Button
                if (data.deletable) {
                    deleteBtn.disabled = false;
                    deleteBtn.title = "Delete unused medicine";
                } else {
                    deleteBtn.disabled = true;
                    deleteBtn.title = "Cannot delete: Batches exist";
                    deleteBtn.style.opacity = '0.5';
                    deleteBtn.style.cursor = 'not-allowed';
                    deleteBtn.onclick = () => alert("Cannot delete: This medicine has associated batches.");
                }
            })
            .catch(err => {
                console.error("Failed to check catalog status", err);
            });
        }

        function switchTab(tabName) {
            cancelEditing();
            const tabs = document.querySelectorAll('.tab');
            const tabPages = document.querySelectorAll('.tab-page');

            tabs.forEach(tab => tab.classList.remove('active'));
            tabPages.forEach(page => page.style.display = 'none');

            if (tabName === 'details') {
                document.getElementById('detailsTab').style.display = 'flex';
                tabs[0].classList.add('active');
            } else if (tabName === 'batchSummary') {
                document.getElementById('batchSummaryTab').style.display = 'flex';
                tabs[1].classList.add('active');
                fetchBatchSummary(selectedMedicineId);
            } else if (tabName === 'history') {
                document.getElementById('historyTab').style.display = 'flex';
                tabs[2].classList.add('active');
                fetchHistory(selectedMedicineId);
            }
        }

        function fetchHistory(catalogId, limit = 5) {
            const historyTab = document.getElementById('historyTab');
            historyTab.innerHTML = '<p>Loading...</p>';

            const formData = new URLSearchParams();
            formData.append('catalog_id', catalogId);
            if (limit > 0) formData.append('limit', limit);

            fetch('get_medicine_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    historyTab.innerHTML = `<p style="color:red;">${data.message}</p>`;
                    return;
                }

                const rows = data.data;
                if (rows.length === 0) {
                    historyTab.innerHTML = '<p>No history available.</p>';
                    return;
                }

                let html = '';

                // Only show button when we applied a limit (i.e., not full view)
                if (limit > 0) {
                    html += `
                        <div class="details-buttons">
                            <button class="view-detailed-btn" onclick="openHistoryModal(${catalogId})">
                                View Detailed History
                            </button>
                        </div>
                    `;
                }

                html += `
                    <div class="history-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>By</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                rows.forEach(r => {
                    html += `<tr>
                                <td>${r.action_type}</td>
                                <td>${r.performed_by}</td>
                                <td>${r.created_at}</td>
                            </tr>`;
                });

                html += `</tbody></table></div>`;

                historyTab.innerHTML = html;
            })
            .catch(err => {
                console.error(err);
                historyTab.innerHTML = '<p style="color:red;">Failed to load history.</p>';
            });
        }

        let fullHistoryCatalogId = null;

        function openHistoryModal(catalogId) {
            fullHistoryCatalogId = catalogId;
            const modal = document.getElementById('historyModal');
            const content = document.getElementById('fullHistoryContent');
            modal.style.display = 'flex';
            content.innerHTML = '<p>Loading full history...</p>';

            // Fetch **without** limit
            const formData = new URLSearchParams();
            formData.append('catalog_id', catalogId);

            fetch('get_medicine_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    content.innerHTML = `<p style="color:red;">${data.message}</p>`;
                    return;
                }

                document.querySelector('#historyModal .modal-content .history-container h4').textContent = `${data.medicine_name}`;

                const rows = data.data;
                if (rows.length === 0) {
                    content.innerHTML = '<p>No history found.</p>';
                    return;
                }

                let table = `
                    <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th >Performed By</th>
                                    <th>Date &amp; Time</th>
                                </tr>
                            </thead>
                        <tbody>
                `;
                rows.forEach(r => {
                    table += `<tr>
                        <td>${r.action_type}</td>
                        <td>${r.details}</td>
                        <td>${r.performed_by}</td>
                        <td>${r.created_at}</td>
                    </tr>`;
                });
                table += `</tbody></table>`;
                content.innerHTML = table;
            })
            .catch(() => {
                content.innerHTML = '<p style="color:red;">Failed to load full history.</p>';
            });
        }

        function closeHistoryModal() {
            document.getElementById('historyModal').style.display = 'none';
        }

        function fetchBatchSummary(catalogId) {
            fetch('get_batch_summary.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `catalog_id=${encodeURIComponent(catalogId)}`
            })
            .then(response => response.json())
            .then(data => {
                const batchSummaryTab = document.getElementById('batchSummaryTab');
                if (data.success) {
                    batchSummaryTab.innerHTML = `
                        <div class="form-scroll">
                            <div class="details-fields">
                                <div class="field">
                                    <label>Total Stock</label>
                                    <p>${data.data.total_stock}</p>
                                </div>
                                <div class="field">
                                    <label>Number of Batches</label>
                                    <p>${data.data.batch_count}</p>
                                </div>
                                <div class="field">
                                    <label>Expiry Status Summary</label>
                                    <p>${data.data.expiry_summary}</p>
                                </div>
                                <div class="field">
                                    <label>Earliest Expiration Date</label>
                                    <p>${data.data.earliest_expiry}</p>
                                </div>
                                <div class="field">
                                    <label>Stock Status</label>
                                    <p>${data.data.stock_status}</p>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    batchSummaryTab.innerHTML = `
                        <div class="details-fields">
                            <p style="color: red;">Error: ${data.message}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('batchSummaryTab').innerHTML = `
                    <div class="details-fields">
                        <p style="color: red;">Failed to load batch summary.</p>
                    </div>
                `;
            });
        }

        function enableEditing() {
            const inputs = document.querySelectorAll('#medicineForm input');
            inputs.forEach(input => {
                if (input.name !== 'stocks') {
                    input.removeAttribute('readonly');
                }
            });

            // Enable Select2 fields
            $('#medicineForm select[name="therapeutic_category"]').prop('disabled', false);
            $('#medicineForm select[name="generic_name"]').prop('disabled', false);
            $('#medicineForm select[name="brand_name"]').prop('disabled', false);

            $('#medicineForm select[name="dosage_form"]').prop('disabled', false);

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
            formData.append('medicine_id', selectedMedicineId); // Change 'id' to 'medicine_id'
            formData.append('admin_id', '<?= $adminId ?>');

            fetch('update_medicine.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json()) // Parse JSON response
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating.');
            });

            document.getElementById('editBtn').disabled = false;
        }

        function cancelEditing() {
            const inputs = document.querySelectorAll('#medicineForm input');
            inputs.forEach(input => {
                input.setAttribute('readonly', true);
            });

            // Disable Select2
            $('#medicineForm select[name="therapeutic_category"]').prop('disabled', true);
            $('#medicineForm select[name="generic_name"]').prop('disabled', true);
            $('#medicineForm select[name="brand_name"]').prop('disabled', true);

            $('#medicineForm select[name="dosage_form"]').prop('disabled', true);

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
            if (!selectedMedicineId) {
                alert('Please select a medicine to delete.');
                return;
            }

            if (!confirm('Are you sure you want to delete this medicine? This action cannot be undone.')) {
                return;
            }

            fetch('delete_medicine.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `medicine_id=${encodeURIComponent(selectedMedicineId)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the medicine.');
            });
        }

    </script>
    <script>
        //ADD MEDICINE MODAL
        // Initialize Select2 for searchable dropdowns
        $(document).ready(function() {
            $('.select2').select2({
                tags: true, // Allow adding new options
                placeholder: "Select or type to add new",
                allowClear: true,
                width: '100%'
            });
        });
        
        function openModal() {
            document.getElementById("medicineModal").style.display = "flex";
        }

        function closeModal() {
            document.getElementById("medicineModal").style.display = "none";
        }
    </script>



</body>
</html>