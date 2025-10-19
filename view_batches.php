<?php
// view_batches.php
session_start();
require_once "config.php";

// Check if user is logged in as health staff 
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Check if catalog_id is provided
if (!isset($_GET['catalog_id']) || empty($_GET['catalog_id'])) {
    header("Location: medicine_management.php");
    exit();
}

$catalog_id = trim($_GET['catalog_id']);

// Get search term from GET request and sanitize it
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

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
    SELECT therapeutic_category, generic_name, brand_name, dosage, dosage_form, unit
    FROM medicines_catalog
    WHERE id = :catalog_id
");
$catalogStmt->execute([':catalog_id' => $catalog_id]);
$medicine = $catalogStmt->fetch(PDO::FETCH_ASSOC);

if (!$medicine) {
    header("Location: medicine_management.php");
    exit();
}

// Fetch batches for the given catalog_id with optional search filter
$batchQuery = "
    SELECT id, batch_lot_number, pono, manufacturing_date, expiration_date, stocks, 
           stock_status, expiry_status, source
    FROM medicine_batches
    WHERE catalog_id = :catalog_id";
if (!empty($searchTerm)) {
    $batchQuery .= " AND (batch_lot_number LIKE :search OR pono LIKE :search OR source LIKE :search)";
}
$batchQuery .= " ORDER BY expiration_date ASC";

$batchStmt = $conn->prepare($batchQuery);
$batchStmt->bindParam(':catalog_id', $catalog_id, PDO::PARAM_INT);
if (!empty($searchTerm)) {
    $searchParam = "%$searchTerm%";
    $batchStmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
}
$batchStmt->execute();
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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        <div class="title-con">
            <div class="title">
                <a href="medicine_management.php" class="back-button">← Back</a>
                <h2>Batches for <?php echo htmlspecialchars($medicine['generic_name'] . ($medicine['brand_name'] ? ' (' . $medicine['brand_name'] . ' ' . $medicine['dosage'] .')' : '')); ?></h2>
            </div>
        </div>
        <div class="med-container">
            <div class="sort-controls">
                <div class="search-con">
                    <form method="GET" action="view_batches.php">
                        <input type="hidden" name="catalog_id" value="<?= htmlspecialchars($catalog_id) ?>">
                        <input type="text" name="search" placeholder="Search by Batch Lot, PONO, or Source" value="<?= htmlspecialchars($searchTerm) ?>">
                        <button type="submit">Search</button>
                    </form>
                </div>
                <div class="button-con">
                    <button class="add-batch-btn" onclick="openBatchModal()">ADD BATCH</button>
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
                                        <tr onclick="selectRow(this); showBatchDetails(<?= htmlspecialchars(json_encode($batch)) ?>, <?= htmlspecialchars(json_encode($medicine)) ?>)">
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
                            <p style="margin: 30px;">Select a batch to view details.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Batch Modal -->
    <div id="batchModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Add Batch</h2>
            <form method="POST" action="add_batch.php" id="addBatchForm">
                <input type="hidden" name="catalog_id" value="<?= htmlspecialchars($catalog_id) ?>">
                <div class="form-group">
                    <label>Batch Lot Number</label>
                    <input type="text" name="batch_lot_number" required placeholder="Enter batch lot number" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>PONO</label>
                    <input type="text" name="pono" placeholder="Enter PONO" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Manufacturing Date</label>
                    <input type="date" name="manufacturing_date">
                </div>
                <div class="form-group">
                    <label>Expiration Date</label>
                    <input type="date" name="expiration_date" required>
                </div>
                <div class="form-group">
                    <label>Stocks</label>
                    <input type="number" name="stocks" min="1" required placeholder="Enter stock quantity">
                </div>
                <div class="form-group">
                    <label>Source</label>
                    <select name="source" class="select2" required>
                        <option value="" disabled selected>Select or type to add new</option>
                        <?php
                        $categoryStmt = $conn->query("SELECT DISTINCT source FROM medicine_batches ORDER BY source");
                        while ($category = $categoryStmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<option value='" . htmlspecialchars($category['source']) . "'>" . htmlspecialchars($category['source']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="button-group">
                    <button type="button" class="cancel-btn" onclick="closeBatchModal()">Cancel</button>
                    <button type="submit" class="save-btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- jQuery and Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        let selectedBatchId = null;
        let medicineDetails = null;

        function selectRow(row) {
            // Remove 'selected' class from all rows
            document.querySelectorAll('tbody tr').forEach(tr => tr.classList.remove('selected'));
            // Add 'selected' class to the clicked row
            row.classList.add('selected');
        }
        function showBatchDetails(batch, medicine) {
            selectedBatchId = batch.id;
            medicineDetails = medicine;

            const detailsContent = `
                <div class="tabs-buttons">
                    <div class="details-tabs">  
                        <button class="tab active" onclick="switchTab('details')">Details</button>
                        <button class="tab" onclick="switchTab('addStock')">Add Stock</button>
                        <button class="tab" onclick="switchTab('history')">History</button>
                    </div>                            
                </div>

                <div id="tab-content">
                    <div id="detailsTab" class="tab-page">
                        <div class="details-buttons">
                            <button class="edit-btn" id="editBtn" onclick="enableEditing()">Edit</button>
                            <button class="delete-btn" onclick="deleteBatch()">Delete</button>
                        </div>
                        <form id="batchForm">
                            <input type="hidden" name="batch_id" value="${batch.id}">
                            <div class="details-fields">
                                <div class="field">
                                    <label>Medicine</label>
                                    <p>${medicine.generic_name} ${medicine.brand_name ? '(' + medicine.brand_name + ')' : ''} - ${medicine.dosage} ${medicine.dosage_form} (${medicine.unit})</p>
                                </div>
                                <div class="field">
                                    <label>Batch Lot Number</label>
                                    <input type="text" name="batch_lot_number" id="batch_lot_number" value="${batch.batch_lot_number || ''}" readonly>
                                </div>
                                <div class="field">
                                    <label>PONO</label>
                                    <input type="text" name="pono" id="pono" value="${batch.pono || ''}" readonly>
                                </div>
                                <div class="field">
                                    <label>Manufacturing Date</label>
                                    <input type="date" name="manufacturing_date" id="manufacturing_date" value="${batch.manufacturing_date || ''}" readonly>
                                </div>
                                <div class="field">
                                    <label>Expiration Date</label>
                                    <input type="date" name="expiration_date" id="expiration_date" value="${batch.expiration_date || ''}" readonly>
                                </div>
                                <div class="row">
                                    <div class="field">
                                        <label>Stocks</label>
                                        <input type="number" name="stocks" id="stocks" value="${batch.stocks || 0}" readonly>
                                    </div>
                                    <div class="field">
                                        <label>Stock Status</label>
                                        <input type="text" name="stock_status" id="stock_status" value="${batch.stock_status || ''}" readonly>
                                    </div>
                                </div>
                                <div class="field">
                                    <label>Expiry Status</label>
                                    <input type="text" name="expiry_status" id="expiry_status" value="${batch.expiry_status || ''}" readonly>
                                </div>
                                <div class="field">
                                    <label>Source</label>
                                    <input type="text" name="source" id="source" value="${batch.source || ''}" readonly>
                                </div>
                            </div>
                            <div class="action-buttons" style="display:none; text-align:center; margin-top:20px;">
                                <button type="button" class="save-btn" onclick="saveBatchChanges()">Save</button>
                                <button type="button" class="cancel-btn" onclick="cancelEditing()">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <div id="addStockTab" class="tab-page" style="display:none;">
                        <div class="details-fields">
                            <p>Loading batch summary...</p>
                        </div>
                    </div>

                    <div id="historyTab" class="tab-page" style="display:none;">
                        <div class="details-fields">
                            <p>Loading history...</p>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('detailsContent').innerHTML = detailsContent;
        }

        function enableEditing() {
            const inputs = document.querySelectorAll('#batchForm input');
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

        function cancelEditing() {
            const medicineInputs = document.querySelectorAll('#batchForm input');
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

        function saveBatchChanges() {
            if (!confirm('Are you sure you want to save changes to this batch?')) {
                return;
            }

            const batchForm = document.getElementById('batchForm');
            const formData = new FormData(batchForm);
            formData.append('admin_id', '<?= $adminId ?>');

            fetch('update_batch.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Batch updated successfully!');
                    window.location.href = 'view_batches.php?catalog_id=<?= htmlspecialchars($catalog_id) ?>';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating.');
            });
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
                // fetchBatchSummary(selectedMedicineId); // Uncomment if needed
            } else if (tabName === 'history') {
                document.getElementById('historyTab').style.display = 'block';
                tabs[2].classList.add('active');
                fetchBatchHistory(selectedBatchId);
            }
        }

        function fetchBatchHistory(batchId) {
            const historyTab = document.getElementById('historyTab');
            historyTab.innerHTML = '<p>Loading history...</p>';

            fetch('get_batch_history.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `batch_id=${encodeURIComponent(batchId)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.data.length === 0) {
                        historyTab.innerHTML = '<p>No history available for this batch.</p>';
                    } else {
                        let tableHTML = `
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>Performed by</th>
                                        <th>When</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;
                        data.data.forEach(entry => {
                            tableHTML += `
                                <tr>
                                    <td>${entry.action_type}</td>
                                    <td>${entry.details}</td>
                                    <td>${entry.performed_by}</td>
                                    <td>${entry.created_at}</td>
                                </tr>
                            `;
                        });
                        tableHTML += '</tbody></table>';
                        historyTab.innerHTML = tableHTML;
                    }
                } else {
                    historyTab.innerHTML = `<p style="color: red;">Error: ${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                historyTab.innerHTML = '<p style="color: red;">Failed to load history.</p>';
            });
        }

        function deleteBatch() {
            if (!selectedBatchId) {
                alert('No batch selected.');
                return;
            }

            if (!confirm('Are you sure you want to delete this batch? This action cannot be undone.')) {
                return;
            }

            const formData = new FormData();
            formData.append('batch_id', selectedBatchId);
            formData.append('admin_id', '<?= $adminId ?>');

            fetch('delete_batch.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Batch deleted successfully!');
                    window.location.reload(); // Refresh the page to update the batch list
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the batch.');
            });
        }
        
    </script>
    <script>
        // Initialize Select2 for searchable dropdowns
        $(document).ready(function() {
            $('.select2').select2({
                tags: true, // Allow adding new options
                placeholder: "Select or type to add new",
                allowClear: true,
                width: '100%'
            });
        });
        function openBatchModal() {
            document.getElementById("batchModal").style.display = "flex";
        }

        function closeBatchModal() {
            document.getElementById("batchModal").style.display = "none";
        }
    </script>
</body>
</html>