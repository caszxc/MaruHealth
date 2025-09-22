<?php
// medicine_management.php
session_start();
require_once "config.php"; // Include database connection

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

$catalogStmt = $conn->prepare("
    SELECT id, therapeutic_category, generic_name, brand_name, 
           dosage, dosage_form, unit, min_stock
    FROM medicines_catalog
");
$catalogStmt->execute();
$catalogs = $catalogStmt->fetchAll(PDO::FETCH_ASSOC);
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
    <!-- Select2 CSS -->
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
                <div></div>
                <div class="search-con">
                    <button class="add-med-btn" onclick="openModal()">ADD MEDICINE</button>
                </div>
            </div>
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
                                        <td colspan="9" style="text-align: center;">No medicines found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($catalogs as $catalog): ?>
                                        <tr onclick="selectRow(this); showDetails(<?= htmlspecialchars(json_encode($catalog)) ?>)">
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

                <div class="button-group">
                    <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="save-btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- jQuery and Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
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
                        <form id="medicineForm">
                            <div class="details-fields">
                                <div class="field">
                                    <label>Therapeutic Category</label>
                                    <input type="text" name="therapeutic_category" value="${medicine.therapeutic_category}" readonly>
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
            } else if (tabName === 'batchSummary') {
                document.getElementById('batchSummaryTab').style.display = 'block';
                tabs[1].classList.add('active');
                fetchBatchSummary(selectedMedicineId);
            } else if (tabName === 'history') {
                document.getElementById('historyTab').style.display = 'block';
                tabs[2].classList.add('active');
            }
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
            formData.append('medicine_id', selectedMedicineId); // Change 'id' to 'medicine_id'
            formData.append('admin_id', '<?= $adminId ?>');

            fetch('update_medicine.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json()) // Parse JSON response
            .then(data => {
                if (data.success) {
                    alert('Medicine updated successfully!');
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
                    alert('Medicine deleted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
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