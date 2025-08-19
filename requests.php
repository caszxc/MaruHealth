<?php
//requests.php
session_start();
require_once "config.php";

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch medicine requests with 'pending' status
$requestsQuery = "SELECT mr.id, mr.request_id, mr.full_name, DATE_FORMAT(mr.request_date, '%m/%d/%Y %h:%i%p') as formatted_date 
                 FROM medicine_requests mr 
                 WHERE mr.request_status = 'pending'
                 ORDER BY mr.request_date DESC";
$requestsStmt = $conn->prepare($requestsQuery);
$requestsStmt->execute();
$requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all available medicines for the dropdown
$medicinesQuery = "SELECT id, generic_name, brand_name, dosage, dosage_form, stocks 
                FROM medicines 
                WHERE stocks > 0 
                AND stock_status IN ('In Stock', 'Low Stock') 
                AND expiry_status != 'Expired'
                ORDER BY generic_name ASC";
$medicinesStmt = $conn->prepare($medicinesQuery);
$medicinesStmt->execute();
$availableMedicines = $medicinesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch the admin's name
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
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Requests</title>
    <link rel="stylesheet" href="css/medicine_requests.css">
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
            
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/med_icon.png" alt="">
                <a href="medicine_management.php" class="<?= $current_page == 'medicine_management.php' ? 'active' : '' ?>">Medicine Management</a>
            </div>
            
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/reqmd_icon_active.png" alt="">
                <a href="medicine_requests.php" class="<?= ($current_page == 'medicine_requests.php' || $current_page == 'requests.php') ? 'active' : '' ?>">Medicine Requests</a>
            </div>
            <?php endif; ?>

            <p class="menu-header">OTHERS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/logout_icon.png" alt="">
                <a href="logout.php" class="logout-button">Log Out</a>
            </div>
            
        </div>
    </div>

    <div class="med-req-content">
        <div class="title-con">
            <a href="#" class="back-button" onclick="history.back(); return false;">← Back</a>
            <h2>Pending Medicine Requests</h2>
        </div>
        <div class="table-con">
            <table>
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Name</th>
                        <th>Date/Time Requested</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="4" class="no-requests" style="text-align: center;">No medicine requests found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['request_id']) ?></td>
                                <td><?= htmlspecialchars($request['full_name']) ?></td>
                                <td><?= htmlspecialchars($request['formatted_date']) ?></td>
                                <td>
                                    <button class="view-btn" onclick="openModal(<?= $request['id'] ?>)">View</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Request Details Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
    

    <!-- Hidden template for medicine options -->
    <select id="medicine_options_template" style="display: none;">
        <?php foreach ($availableMedicines as $medicine): ?>
            <?php 
                $medicineName = !empty($medicine['brand_name']) 
                    ? $medicine['brand_name'] . ' - ' . $medicine['generic_name'] 
                    : $medicine['generic_name'];
                $medicineInfo = $medicineName . ' - ' . $medicine['dosage'] . ' ' . $medicine['dosage_form'] . ' - ' . $medicine['stocks'];
            ?>
            <option value="<?= $medicine['id'] ?>"><?= htmlspecialchars($medicineInfo) ?></option>
        <?php endforeach; ?>
    </select>

    <script>
        function openModal(requestId) {
            fetch('get_request_details.php?id=' + requestId)
                .then(response => response.json())
                .then(data => {
                    populateModal(data);
                    document.getElementById("viewModal").classList.add("show");
                    document.getElementById('request_id').value = requestId;
                    updateApproveButton();
                })
                .catch(error => console.error('Error:', error));
        }

        function closeModal() {
            document.getElementById("viewModal").classList.remove("show");
        }

        function populateModal(data) {
            const modal = document.querySelector('.modal-content');
            let modalHTML = `
                <div class="modal-header">
                    <span class="close" onclick="closeModal()">×</span>
                    <h2 class="title">Medicine Request Details</h2>
                </div>
                <form id="medicineApprovalForm" method="post" action="process_request.php">
                    <input type="hidden" id="request_id" name="request_id" value="${data.request.id}">
                    <div class="patient-details">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Request ID</label>
                                <div class="detail-box">${data.request.request_id}</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Patient's Full Name <span class="sub-label">(Buong Pangalan ng Pasyente)</span></label>
                                <div class="detail-box">${data.request.full_name}</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group half">
                                <label>Gender <span class="sub-label">(Kasarian)</span></label>
                                <div class="detail-box">${data.request.gender}</div>
                            </div>
                            <div class="form-group half">
                                <label>Birthdate <span class="sub-label">(Araw ng Kapanganakan)</span></label>
                                <div class="detail-box">${data.request.birthdate}</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group half">
                                <label>Complete Address <span class="sub-label">(Kompletong Address)</span></label>
                                <div class="detail-box">${data.request.address}</div>
                            </div>
                            <div class="form-group half">
                                <label>Contact Number <span class="sub-label">(Numero ng Telepono)</span></label>
                                <div class="detail-box">${data.request.phone}</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Reason for Request <span class="sub-label">(Rason ng Paghingi)</span></label>
                                <div class="detail-box">${data.request.reason || 'No reason provided'}</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Prescription <span class="sub-label">(Reseta)</span></label>
                                <div class="prescription-image">
                                    <img src="${data.request.prescription}" alt="Prescription">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="requested-medicines">
                        <h3>Requested Medicines</h3>`;
            
            window.matchedMedicinesData = {};
            data.medicines.forEach((medicine, index) => {
                let availabilityInfo = '';
                let checkboxHtml = '';
                if (medicine.available_stock) {
                    window.matchedMedicinesData[medicine.id] = medicine.matched_medicines;
                    availabilityInfo = `<div class="availability available">
                        Available Medicines:<br>
                        ${medicine.medicine_details}
                    </div>`;
                    checkboxHtml = `<div class="approve-checkbox">
                        <input type="checkbox" id="approve_medicine_${medicine.id}" name="approve_medicines[]" value="${medicine.id}" onchange="updateApproveButton()">
                        <label for="approve_medicine_${medicine.id}"></label>
                    </div>`;
                } else {
                    availabilityInfo = `<div class="availability unavailable">
                        Available Medicines:<br>
                        ${medicine.medicine_details || 'No stock available'}
                    </div>`;
                }
                modalHTML += `
                    <div class="medicine-item">
                        <div class="medicine-details">
                            <div class="form-row">
                                <div class="form-group third">
                                    <label>Medicine Name <span class="sub-label">(Pangalan ng Gamot)</span></label>
                                    <div class="detail-box">${medicine.medicine_name}</div>
                                </div>
                                <div class="form-group third">
                                    <label>Dosage <span class="sub-label">(Dosis)</span></label>
                                    <div class="detail-box">${medicine.dosage}</div>
                                </div>
                                <div class="form-group third">
                                    <label>Quantity <span class="sub-label">(Bilang)</span></label>
                                    <div class="detail-box">${medicine.quantity}</div>
                                </div>
                            </div>
                            ${availabilityInfo}
                        </div>
                        ${checkboxHtml}
                    </div>`;
            });
            
            modalHTML += `
                    </div>
                    <div id="claim-section" style="display: none;">
                        <h3>Claim Details</h3>
                        <div id="medicine-allocation"></div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="claim_by">Claim by</label>
                                <input type="date" id="claim_by" name="claim_by" required>
                            </div>
                            <div class="form-group">
                                <label for="claim_until">Until</label>
                                <input type="date" id="claim_until" name="claim_until" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="admin_note">Message/Note for Resident</label>
                            <textarea id="admin_note" name="admin_note"></textarea>
                        </div>
                    </div>
                    <div class="button-group">
                        <button type="button" class="btn-secondary" onclick="closeModal()">Close</button>
                        <button type="submit" id="approveButton" class="btn-primary" disabled>Approve</button>
                        <button type="button" id="declineButton" class="btn-decline" onclick="processDecline()">Decline</button>
                    </div>
                </form>`;
            
            modal.innerHTML = modalHTML;
            
            const today = new Date();
            const threeDaysLater = new Date();
            threeDaysLater.setDate(today.getDate() + 3);
            document.getElementById('claim_by').value = formatDate(today);
            document.getElementById('claim_until').value = formatDate(threeDaysLater);
        }

        function updateApproveButton() {
            const checkboxes = document.querySelectorAll('input[name="approve_medicines[]"]');
            const approveButton = document.getElementById('approveButton');
            const claimSection = document.getElementById('claim-section');
            
            const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
            
            if (anyChecked) {
                approveButton.disabled = false;
                approveButton.classList.remove('disabled');
                claimSection.style.display = 'flex';
                generateMedicineAllocation();
            } else {
                approveButton.disabled = true;
                approveButton.classList.add('disabled');
                claimSection.style.display = 'none';
                document.getElementById('medicine-allocation').innerHTML = '';
            }
        }

        function generateMedicineAllocation() {
            const medicineAllocationDiv = document.getElementById('medicine-allocation');
            medicineAllocationDiv.innerHTML = '';
            
            const checkedCheckboxes = document.querySelectorAll('input[name="approve_medicines[]"]:checked');
            
            checkedCheckboxes.forEach(checkbox => {
                const medicineId = checkbox.value;
                const medicineNameElement = document.querySelector(`#approve_medicine_${medicineId}`).closest('.medicine-item').querySelector('.medicine-details .form-group:first-child .detail-box');
                const medicineName = medicineNameElement ? medicineNameElement.textContent : `Medicine #${medicineId}`;
                
                const quantityElement = document.querySelector(`#approve_medicine_${medicineId}`).closest('.medicine-item').querySelector('.form-group:nth-child(3) .detail-box');
                const requestedQuantity = quantityElement ? parseInt(quantityElement.textContent) : 1;
                
                const allocationContainer = document.createElement('div');
                allocationContainer.className = 'medicine-allocation-box';
                
                const requestedInfo = document.createElement('p');
                requestedInfo.textContent = `Requested Quantity: ${requestedQuantity}`;
                allocationContainer.appendChild(requestedInfo);
                
                const fieldsContainer = document.createElement('div');
                fieldsContainer.className = 'allocation-fields';
                
                const selectFormGroup = document.createElement('div');
                selectFormGroup.className = 'form-group';
                const selectLabel = document.createElement('label');
                selectLabel.htmlFor = `distribute_medicine_${medicineId}`;
                selectLabel.textContent = `Select Inventory Medicine for: ${medicineName}`;
                selectFormGroup.appendChild(selectLabel);
                const select = document.createElement('select');
                select.id = `distribute_medicine_${medicineId}`;
                select.name = `distribute_medicines[${medicineId}]`;
                select.required = true;
                
                if (window.matchedMedicinesData && window.matchedMedicinesData[medicineId]) {
                    const matchedMedicines = window.matchedMedicinesData[medicineId];
                    matchedMedicines.forEach(medicine => {
                        const option = document.createElement('option');
                        option.value = medicine.id;
                        option.dataset.stock = medicine.stocks;
                        const medicineName = !empty(medicine.brand_name) ? `${medicine.brand_name} - ${medicine.generic_name}` : medicine.generic_name;
                        const medicineInfo = `${medicineName} - ${medicine.dosage} ${medicine.dosage_form} - ${medicine.stocks} in stock`;
                        option.textContent = medicineInfo;
                        select.appendChild(option);
                    });
                }
                selectFormGroup.appendChild(select);
                fieldsContainer.appendChild(selectFormGroup);
                
                const quantityFormGroup = document.createElement('div');
                quantityFormGroup.className = 'form-group';
                const approvedLabel = document.createElement('label');
                approvedLabel.htmlFor = `approved_quantity_${medicineId}`;
                approvedLabel.textContent = `Approved Quantity (max ${requestedQuantity}):`;
                quantityFormGroup.appendChild(approvedLabel);
                const quantityInput = document.createElement('input');
                quantityInput.type = 'number';
                quantityInput.id = `approved_quantity_${medicineId}`;
                quantityInput.name = `approved_quantities[${medicineId}]`;
                quantityInput.min = '1';
                quantityInput.max = requestedQuantity;
                quantityInput.required = true;
                quantityInput.value = Math.min(requestedQuantity, getMaxStock(select));
                quantityFormGroup.appendChild(quantityInput);
                fieldsContainer.appendChild(quantityFormGroup);
                
                allocationContainer.appendChild(fieldsContainer);
                
                select.addEventListener('change', function() {
                    const maxStock = getMaxStock(this);
                    const maxApprovable = Math.min(requestedQuantity, maxStock);
                    quantityInput.max = maxApprovable;
                    if (quantityInput.value > maxApprovable) {
                        quantityInput.value = maxApprovable;
                    }
                });
                
                function getMaxStock(select) {
                    const selectedOption = select.options[select.selectedIndex];
                    return selectedOption ? parseInt(selectedOption.dataset.stock) : 0;
                }
                
                medicineAllocationDiv.appendChild(allocationContainer);
            });
        }

        function empty(value) {
            return value === undefined || value === null || value === '';
        }

        function processDecline() {
            if (confirm('Are you sure you want to decline this medicine request?')) {
                const requestId = document.getElementById('request_id').value;
                const formData = new FormData();
                formData.append('request_id', requestId);
                formData.append('action', 'decline');
                fetch('process_request.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    closeModal();
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing your request.');
                });
            }
        }

        function formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
    </script>

</body>
</html>