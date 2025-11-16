<?php
// view_batches.php
session_start();
require_once "config.php";
include 'settings.php';

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
    WHERE catalog_id = :catalog_id
    AND (stocks > 0 OR is_disposed = 0)
";

// Apply expiry filter
if ($filter !== 'all') {
    if ($filter === 'expired') {
        $batchQuery .= " AND expiry_status = 'Expired'";
    } elseif ($filter === 'week') {
        $batchQuery .= " AND expiry_status = 'Expiring within a week'";
    } elseif ($filter === 'month') {
        $batchQuery .= " AND expiry_status = 'Expiring within a month'";
    }
}

// Search filter
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
    <title>Medicine Management</title>
    <link rel="stylesheet" href="css/medicine_management.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" href="<?= $logo_url ?>" type="image/x-icon">
    <style>
        .message {
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            transition: opacity .5s ease-in-out;
            margin: 10px 0;
            font-weight: 500;
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
                <div class="search-filter">
                    <div class="search-con">
                        <form method="GET" action="view_batches.php">
                            <input type="hidden" name="catalog_id" value="<?= htmlspecialchars($catalog_id) ?>">
                            <input type="text" name="search" placeholder="Search by Batch Lot, PONO, or Source" value="<?= htmlspecialchars($searchTerm) ?>">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            <button type="submit">Search</button>
                        </form>
                    </div>
                    <div class="filter-buttons" data-initial-filter="<?= $filter ?>">
                        <button class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>" data-filter="all" title="All Batches">
                            <i class="fas fa-boxes"></i>
                        </button>
                        <button class="filter-btn <?= $filter === 'expired' ? 'active' : '' ?>" data-filter="expired" title="Expired">
                            <i class="fas fa-skull-crossbones"></i>
                        </button>
                        <button class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>" data-filter="week" title="Expiring in 7 Days">
                            <i class="fas fa-exclamation-triangle"></i>
                        </button>
                        <button class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>" data-filter="month" title="Expiring in 30 Days">
                            <i class="fas fa-clock"></i>
                        </button>
                    </div>
                </div>
                
                <div class="button-con">
                    <button class="add-batch-btn" onclick="openBatchModal()">ADD BATCH</button>
                    <button class="view-disposed-btn" onclick="openDisposedModal()">View Disposed Batches</button>
                </div>
            </div>

            <!-- ----- SUCCESS / ERROR MESSAGE ----- -->
            <?php if (isset($_SESSION['batch_message'])): ?>
                <div class="message <?= (strpos($_SESSION['batch_message'], 'Error') !== false && strpos($_SESSION['batch_message'], 'successfully') === false) ? 'error' : 'success' ?>">
                    <?= nl2br(htmlspecialchars($_SESSION['batch_message'])) ?>
                </div>
                <?php unset($_SESSION['batch_message']); ?>
            <?php endif; ?>

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

    <!-- Dispose Batch Modal -->
    <div id="disposeModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Dispose Batch</h2>
            <div class="form-scroll">
                <form id="disposeForm">
                    <input type="hidden" name="batch_id" id="disposeBatchId">
                    <input type="hidden" name="admin_id" value="<?= $adminId ?>">

                    <div class="form-group">
                        <label>Current Stocks: <strong id="currentDisposeStocks">0</strong></label>
                    </div>

                    <!-- ==== REASON FIRST ==== -->
                    <div class="form-group">
                        <label>Reason for Disposal <span class="required">*</span></label>
                        <select name="reason" id="disposeReason" required>
                            <option value="" disabled selected>Select reason</option>
                            <option value="expired">Expired</option>
                            <option value="damaged">Damaged</option>
                            <option value="near-expiry donated">Near-expiry donated</option>
                            <option value="recalled">Recalled</option>
                            <option value="wrongly dispensed">Wrongly dispensed</option>
                            <option value="others">Others</option>
                        </select>
                    </div>

                    <div class="form-group" id="othersReasonGroup" style="display:none;">
                        <label>Specify Reason</label>
                        <textarea name="others_reason" rows="2" placeholder="Please specify..."></textarea>
                    </div>

                    <!-- ==== QUANTITY SECOND ==== -->
                    <div class="form-group">
                        <label>Quantity to Dispose <span class="required">*</span></label>
                        <input type="number"
                            name="quantity"
                            id="disposeQuantity"
                            min="1"
                            required
                            placeholder="How many to dispose?">
                        <small style="color:#666;">Cannot exceed current stock.</small>
                    </div>

                    <div class="form-group">
                        <label>Witness Name <span class="required">*</span></label>
                        <input type="text" name="witness_name" required placeholder="Full name of witness">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeDisposeModal()">Cancel</button>
                <button type="button" class="save-btn" onclick="submitDispose()">Dispose</button>
            </div>
        </div>
    </div>

    <!-- Add Batch Modal -->
    <div id="batchModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Add Batch</h2>
            <div class="form-scroll">
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
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeBatchModal()">Cancel</button>
                <button type="submit" form="addBatchForm" class="save-btn">Save</button>
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

    <!-- Disposed Batches Modal -->
    <div id="disposedBatchesModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h2>Disposed Batches — <?= htmlspecialchars($medicine['generic_name'] . ($medicine['brand_name'] ? ' ('.$medicine['brand_name'].')' : '')) ?></h2>
            <div class="disposed-container">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Batch Lot</th>
                                <th>Disposed Qty</th>
                                <th>Remaining</th>
                                <th>Reason</th>
                                <th>Disposed By</th>
                                <th>Witness</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="disposedBatchesBody">
                            <tr><td colspan="7" style="text-align:center;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeDisposedModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- jQuery and Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        let disposeBatchId = null;
        let disposeMaxStocks = 0;

        function openDisposeModal(batchId, currentStocks, expiryStatus) {
            disposeBatchId = batchId;
            disposeMaxStocks = currentStocks;

            document.getElementById('disposeBatchId').value = batchId;
            document.getElementById('currentDisposeStocks').textContent = currentStocks;

            const qtyInput = document.getElementById('disposeQuantity');
            qtyInput.max = currentStocks;
            qtyInput.value = currentStocks;               // default = full stock
            qtyInput.readOnly = false;

            // Reset form
            document.getElementById('disposeForm').reset();
            document.getElementById('disposeReason').value = '';   // clear first
            document.getElementById('othersReasonGroup').style.display = 'none';

            // === AUTO-SELECT "Expired" IF BATCH IS EXPIRED ===
            if (expiryStatus === 'Expired') {
                const reasonSelect = document.getElementById('disposeReason');
                reasonSelect.value = 'expired';  // pre-select
                // Trigger change to lock quantity automatically
                reasonSelect.dispatchEvent(new Event('change'));
            }

            // Reason options based on expiry (still apply restrictions)
            updateReasonOptions(expiryStatus);

            document.getElementById('disposeModal').style.display = 'flex';
        }

        function closeDisposeModal() {
            document.getElementById('disposeModal').style.display = 'none';
        }

        document.getElementById('disposeReason').addEventListener('change', function () {
            const reason = this.value;
            const qtyInput = document.getElementById('disposeQuantity');
            const max = parseInt(qtyInput.max);

            // Show/hide "others" textarea
            document.getElementById('othersReasonGroup').style.display =
                reason === 'others' ? 'block' : 'none';

            // Auto-fill & lock quantity for Expired / Recalled
            if (reason === 'expired' || reason === 'recalled') {
                qtyInput.value = max;          // full stock
                qtyInput.readOnly = true;      // cannot edit
                qtyInput.title = 'All remaining stock must be disposed for this reason';
            } else {
                qtyInput.readOnly = false;
                qtyInput.title = '';
                if (qtyInput.value === '' || qtyInput.value == max) {
                    qtyInput.value = max;      // default to full stock (user can change)
                }
            }
        });

        function updateReasonOptions(expiryStatus) {
            const reasonSelect = document.querySelector('#disposeForm select[name="reason"]');
            const expiredOption = reasonSelect.querySelector('option[value="expired"]');
            const nearExpiryOption = reasonSelect.querySelector('option[value="near-expiry donated"]');

            // Reset all options first
            expiredOption.disabled = true;
            expiredOption.title = "Not allowed: Batch is not expired";
            expiredOption.style.color = "#ccc";

            nearExpiryOption.disabled = true;
            nearExpiryOption.title = "Not allowed: Batch is not near expiry";
            nearExpiryOption.style.color = "#ccc";

            // Enable based on expiry status
            if (expiryStatus === 'Expired') {
                expiredOption.disabled = false;
                expiredOption.title = "";
                expiredOption.style.color = "";
            } 
            else if (expiryStatus === 'Expiring within a week' || expiryStatus === 'Expiring within a month') {
                nearExpiryOption.disabled = false;
                nearExpiryOption.title = "";
                nearExpiryOption.style.color = "";
            }
            else if (expiryStatus === 'Valid') {
                // Both remain disabled (already set above)
            }

            // Reset selection if current value is now disabled
            const currentValue = reasonSelect.value;
            if ((currentValue === 'expired' && expiryStatus !== 'Expired') ||
                (currentValue === 'near-expiry donated' && 
                !['Expiring within a week', 'Expiring within a month'].includes(expiryStatus))) {
                reasonSelect.value = '';
            }
        }

        function submitDispose() {
            const quantity = parseInt(document.getElementById('disposeQuantity').value);
            const reason = document.querySelector('[name="reason"]').value;

            if (quantity > disposeMaxStocks) {
                alert('Cannot dispose more than available stock.');
                return;
            }
            if (quantity <= 0) {
                alert('Please enter a valid quantity.');
                return;
            }
            if (!reason) {
                alert('Please select a reason.');
                return;
            }

            if (!confirm(`Dispose ${quantity} item(s)? This action cannot be undone.`)) {
                return;
            }

            const formData = new FormData(document.getElementById('disposeForm'));
            if (reason !== 'others') {
                formData.delete('others_reason');
            }

            fetch('dispose_batch.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Failed to dispose batch.');
            });
        }

        function openDisposedModal() {
            document.getElementById('disposedBatchesModal').style.display = 'flex';
            loadDisposedBatches(); // no parameter needed
        }

        function closeDisposedModal() {
            document.getElementById('disposedBatchesModal').style.display = 'none';
        }

        function loadDisposedBatches() {
            const tbody = document.getElementById('disposedBatchesBody');
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Loading...</td></tr>';

            fetch('get_disposed_batches.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'catalog_id=<?= $catalog_id ?>'
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success || data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No disposal records found.</td></tr>';
                    return;
                }

                let html = '';
                data.data.forEach(d => {
                    const was = d.remaining_at_that_time;
                    const now = d.current_stocks_now;

                    const remainingDisplay = now > 0
                        ? `<strong style="color:#28a745;">${now} left now</strong><br>
                        <small style="color:#666;">(was ${was} when disposed)</small>`
                        : `<strong style="color:#dc3545;">Fully Disposed</strong><br>
                        <small style="color:#666;">(was ${was} when this batch was disposed)</small>`;

                    html += `<tr>
                        <td><strong>${d.batch_lot_number}</strong></td>
                        <td>${d.disposed_qty}</td>
                        <td>${remainingDisplay}</td>
                        <td><em>${d.reason}</em></td>
                        <td>${d.performed_by}</td>
                        <td>${d.witness_name}</td>
                        <td>${d.disposal_date}</td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            })
            .catch(() => {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:red;">Failed to load data.</td></tr>';
            });
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const msg = document.querySelector('.message');
            if (msg) {
                setTimeout(() => {
                    msg.style.opacity = '0';
                    setTimeout(() => msg.remove(), 500);
                }, 3000);
            }
        });
        // BATCH FILTERS – CLIENT-SIDE URL UPDATE
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

                    // Keep catalog_id
                    url.searchParams.set('catalog_id', '<?= $catalog_id ?>' );

                    window.location = url.toString();
                });
            });
        });

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
                            <button class="dispose-btn" onclick="openDisposeModal(${batch.id}, ${batch.stocks}, '${batch.expiry_status.replace(/'/g, "\\'")}')">Dispose Batch</button>
                            <button class="edit-btn" id="editBtn" onclick="enableEditing()">Edit</button>
                            <button class="delete-btn" onclick="deleteBatch()">Delete</button>
                        </div>
                        <div class="form-scroll">
                            <form id="batchForm">
                                <input type="hidden" name="batch_id" value="${batch.id}">
                                <div class="details-fields">
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
                            </form>
                        </div>
                        <div class="action-buttons" style="display:none;">
                            <button type="button" class="save-btn" onclick="saveBatchChanges()">Save</button>
                            <button type="button" class="cancel-btn" onclick="cancelEditing()">Cancel</button>
                        </div>
                    </div>

                    <div id="addStockTab" class="tab-page" style="display:none;">
                        <div class="current-stock">
                            <p><strong>Current Stocks:</strong> <span id="currentStocks">0</span></p>
                            <p><strong>Stock Status:</strong> <span id="currentStockStatus">Out of Stock</span></p>
                        </div>

                        <div class="form-scroll">
                            <form id="addStockForm">
                                <input type="hidden" name="batch_id" id="addStockBatchId" value="">

                                <div class="form-group">
                                    <label>Add Quantity <span class="required">*</span></label>
                                    <input type="number" name="quantity" min="1" required placeholder="Enter quantity to add">
                                </div>

                                <div class="form-group">
                                    <label>Reason / Remarks (optional)</label>
                                    <textarea name="remarks" rows="3" placeholder="e.g. Received from DOH delivery"></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="stock-button">
                            <button type="button" class="add-stock-btn" onclick="submitAddStock()">Add Stock</button>
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

            // === CHECK DELETABLE & EDITABLE ===
            fetch('check_batch_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'batch_id=' + batch.id
            })
            .then(r => r.json())
            .then(data => {
                const deleteBtn = document.querySelector('.delete-btn');
                const editBtn = document.getElementById('editBtn');

                // Delete Button
                if (data.deletable) {
                    deleteBtn.disabled = false;
                    deleteBtn.title = "Delete unused batch";
                } else {
                    deleteBtn.disabled = true;
                    deleteBtn.title = "Cannot delete: Batch has been used";
                    deleteBtn.style.opacity = '0.5';
                    deleteBtn.style.cursor = 'not-allowed';
                }

                // Edit Button
                if (data.editable) {
                    editBtn.disabled = false;
                    editBtn.title = "Edit batch details";
                    editBtn.onclick = enableEditing;
                } else {
                    editBtn.disabled = true;
                    editBtn.title = "Cannot edit: Batch has been distributed or adjusted";
                    editBtn.style.opacity = '0.5';
                    editBtn.style.cursor = 'not-allowed';
                    editBtn.onclick = () => alert("Cannot edit: This batch has been used in distributions or adjustments.");
                }
            })
            .catch(() => {
                console.error("Failed to check batch status");
            });
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
                document.getElementById('detailsTab').style.display = 'flex';
                tabs[0].classList.add('active');
            } else if (tabName === 'addStock') {
                document.getElementById('addStockTab').style.display = 'flex';
                tabs[1].classList.add('active');
                
                // Update current stock info
                const currentStocks = document.getElementById('currentStocks');
                const currentStockStatus = document.getElementById('currentStockStatus');
                const batch = <?= json_encode($batches[0] ?? []) ?>; // fallback

                // Find selected batch
                const selected = <?= json_encode($batches) ?>.find(b => b.id == selectedBatchId);
                if (selected) {
                    currentStocks.textContent = selected.stocks;
                    currentStockStatus.textContent = selected.stock_status;
                }

                // Set batch ID in form
                document.getElementById('addStockBatchId').value = selectedBatchId;
            } else if (tabName === 'history') {
                document.getElementById('historyTab').style.display = 'flex';
                tabs[2].classList.add('active');
                fetchBatchHistory(selectedBatchId);
            }
        }

        function submitAddStock() {
            if (!confirm('Are you sure you want to add stocks to this batch?')) {
                return;
            }
            
            if (!selectedBatchId) {
                alert('Please select a batch first.');
                return;
            }

            const form = document.getElementById('addStockForm');
            const data = new FormData(form);
            data.append('batch_id', selectedBatchId);
            data.append('admin_id', '<?= $adminId ?>');

            fetch('add_stock.php', {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
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
        }

        function fetchBatchHistory(batchId, limit = 5) {
            const historyTab = document.getElementById('historyTab');
            historyTab.innerHTML = '<p>Loading history...</p>';

            const formData = new URLSearchParams();
            formData.append('batch_id', batchId);
            if (limit > 0) formData.append('limit', limit);

            fetch('get_batch_history.php', {
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
                    historyTab.innerHTML = '<p>No history available for this batch.</p>';
                    return;
                }

                let html = '';

                // Show "View Detailed" button only when limit was applied
                if (limit > 0) {
                    html += `
                        <div class="details-buttons">
                            <button class="view-detailed-btn" onclick="openBatchHistoryModal(${batchId})">
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

        let fullBatchHistoryId = null;

        function openBatchHistoryModal(batchId) {
            fullBatchHistoryId = batchId;
            const modal = document.getElementById('historyModal');
            const content = document.getElementById('fullHistoryContent');
            modal.style.display = 'flex';
            content.innerHTML = '<p>Loading full history...</p>';

            const formData = new URLSearchParams();
            formData.append('batch_id', batchId);
            // No limit → full history

            fetch('get_batch_history.php', {
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

                document.querySelector('#historyModal .modal-content h2')
                .textContent = `Batch History: ${data.batch_lot}`;

                // UPDATE SUBTITLE (Medicine Name + Dosage)
                const subtitle = document.querySelector('#historyModal .medicine-name h4');
                subtitle.textContent = data.medicine_full;

                const rows = data.data;
                if (rows.length === 0) {
                    content.innerHTML = '<p>No history found.</p>';
                    return;
                }

                let table = `
                    <table class="history-table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Details</th>
                                <th>Performed By</th>
                                <th>Date & Time</th>
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