<?php
//view_patient.php
session_start();
include 'config.php';
include 'settings.php';

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("No patient ID provided.");
}

$patient_id = $_GET['id'];

// Fetch patient details
$patientQuery = "SELECT * FROM patients WHERE id = :id";
$stmt = $conn->prepare($patientQuery);
$stmt->bindParam(":id", $patient_id, PDO::PARAM_INT);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die("Patient not found.");
}

// Fetch patient consultation history
$consultationQuery = "SELECT * FROM consultations WHERE patient_id = :id";
$stmt = $conn->prepare($consultationQuery);
$stmt->bindParam(":id", $patient_id, PDO::PARAM_INT);
$stmt->execute();
$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Patient Management</title>
    <link rel="stylesheet" href="css/view_patient.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <style>
        .message {
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            transition: opacity .5s ease-in-out;
            margin: 15px 0;
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

                // Determine dashboard URL based on role
                $dashboard_url = ''; // Default
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

            <?php if ($adminRole == 'health_staff'): ?>
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/patient_icon_active.png" alt="">
                <a href="patient_management.php" class="<?= ($current_page == 'patient_management.php' || $current_page == 'view_patient.php')  ? 'active' : '' ?>">Patient Management</a>
            </div>
            
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/med_icon.png" alt="">
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

    <div class="container">
        <div class="title-con">
            <a href="patient_management.php" class="back-button">← Back</a>
            <h2>Patient Details</h2>
        </div>
        <!-- ----- SUCCESS / ERROR MESSAGE ----- -->
        <?php if (isset($_SESSION['patient_message'])): ?>
            <div class="message <?= strpos($_SESSION['patient_message'], 'Error') !== false && strpos($_SESSION['patient_message'], 'successfully') === false ? 'error' : 'success' ?>">
                <?= htmlspecialchars($_SESSION['patient_message']) ?>
            </div>
            <?php unset($_SESSION['patient_message']); ?>
        <?php endif; ?>
        <div class="patient-container">
            <div class="patient-box">
                <div class="patient-info">
                    <h3>Basic Information
                        <button class="edit-btn">Edit</button>
                        <button class="logs-btn" onclick="openLogsModal()">Update Logs</button>
                    </h3>
                    <div class="info-grid">
                        <div class="row">
                            <p class="label">Family Number</p>
                            <p class="value">
                                <?php if (!empty($patient['family_number'])): ?>
                                    <?= htmlspecialchars($patient['family_number']) ?>
                                    <a href="family_number.php?family_number=<?= urlencode($patient['family_number']) ?>" class="view-fam-btn">View</a>
                                <?php else: ?>
                                    Not Provided
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="row">
                            <p class="label">Last Name</p>
                            <p class="value"><?= $patient['last_name'] ?></p>
                        </div>
                        <div class="row">
                            <p class="label">First Name</p>
                            <p class="value"><?= $patient['first_name'] ?></p>
                        </div>
                        <div class="row">
                            <p class="label">Middle Name</p>
                            <p class="value"><?= $patient['middle_name'] ?></p>
                        </div>
                        <div class="row">
                            <p class="label">Sex</p>
                            <p class="value"><?= $patient['sex'] ?></p>
                        </div>
                        <div class="row">
                            <p class="label">Birthdate</p>
                            <p class="value"><?= $patient['birthdate'] ?></p>
                        </div>
                        <div class="row">
                            <p class="label">Contact Number</p>
                            <p class="value"><?= $patient['contact_number'] ?></p>
                        </div>
                        <div class="row">
                            <p class="label">Address</p>
                            <p class="value"><?= $patient['address'] ?></p>
                        </div>
                        <div class="row">
                            <p class="label">Date Registered</p>
                            <p class="value"><?= $patient['created_at'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="measurement-info">
                    <h3>Anthropometric Measurement</h3>
                    <div class="info-grid">
                        <div class="row">
                            <p class="label">Height (cm)</p>
                            <p class="value"><?= $patient['height'] ?></p>
                        </div>
                        <div class="row">
                            <p class="label">Weight (kg)</p>
                            <p class="value"><?= $patient['weight'] ?></p>
                        </div>
                        <div class="row">
                            <p class="label">BMI</p>
                            <p class="value"><?= $patient['bmi'] ?></p>
                        </div>
                        <div class="row">
                            <p class="label">Status</p>
                            <p class="value"><?= $patient['bmi_status'] ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="consultation-history">
                <h3>Consultation History</h3>
                <div class="sort-control">
                    <button class="add-btn" onclick="openModal()">Add Consultation</button>
                </div>
                
                <div class="consultation-content">
                    <div class="consultation-table">
                        <div class="table-container">
                            <div class="table-wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Type of Consultation</th>
                                            <th>Date of Consultation</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($consultations)): ?>
                                            <tr>
                                                <td colspan="3" class="no-consultations" style="text-align: center;">No consultations found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($consultations as $consultation): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($consultation['consultation_type']) ?></td>
                                                <td><?= htmlspecialchars($consultation['consultation_date']) ?></td>
                                                <td class="action-buttons">
                                                    <button class="view-btn" onclick='viewConsultation(<?= json_encode($consultation) ?>)'>VIEW</button>
                                                    <a href="delete_consultation.php?id=<?= $consultation['id'] ?>" class="delete-btn" onclick="return confirm('Are you sure?')">DELETE</a>
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
        </div>
    </div>

    <!-- Update Logs Modal -->
    <div id="updateLogsModal" class="modal">
        <div class="modal-content">
            <h2>Patient Update History</h2>
            <div class="table-container">
                    <div class="table-wrapper">
                        <table class="logs-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Updated By</th>
                                    <th>Field</th>
                                    <th>Old Value</th>
                                    <th>New Value</th>
                                </tr>
                            </thead>
                            <tbody id="logsTableBody">
                                <!-- Filled by JS -->
                            </tbody>
                        </table>
                    </div>
                <div id="noLogs" style="text-align: center; padding: 20px; color: #666;">
                    No update logs found.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeLogsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Add Consultation Modal -->
    <div id="addConsultationModal" class="modal">
        <div class="modal-content">
            <h2>Add Consultation Form</h2>
            <div class="form-scroll">
                <form id="addConsultationForm">
                    <div class="form-grid">
                        <input type="hidden" id="patient_id" name="patient_id" value="<?= $patient_id ?>">
                        <div class="form-group">
                            <div class="form-row">
                                <label>Type of Consultation</label>
                                <select name="consultation_type" id="consultation_type" required>
                                    <option value="" disabled selected>Select</option>
                                    <option value="General Check Up">General Check Up</option>
                                    <option value="Vaccination">Vaccination</option>
                                    <option value="Prenatal">Prenatal</option>
                                    <option value="Dentistry">Dentistry</option>
                                    <option value="Family Planning">Family Planning</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-row">
                                <label>Date of Consultation</label>
                                <input type="date" id="consultation_date" name="consultation_date" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-row">
                                <label>Reason for Consultation</label>
                                <input type="text" id="reason_for_consultation" name="reason_for_consultation" required autocomplete="off">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-row-vitals">
                                <label>Blood Pressure</label>
                                <input type="text" id="blood_pressure" name="blood_pressure" placeholder="e.g., 120/80" autocomplete="off">
                            </div>
                            <div class="form-row">
                                <label>Temperature (°C)</label>
                                <input type="number" id="temperature" name="temperature" step="0.1" required autocomplete="off">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-row">
                                <label>Diagnosis</label>
                                <input type="text" id="diagnosis" name="diagnosis" required autocomplete="off">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-row">
                                <label>Prescribed Medicine (if any)</label>
                                <input type="text" id="prescribed_medicine" name="prescribed_medicine" autocomplete="off">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-row">
                                <label>Treatment Given (if any)</label>
                                <input type="text" id="treatment_given" name="treatment_given" autocomplete="off">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-row">
                                <label>Consulting Physician/Nurse</label>
                                <input type="text" id="consulting_physician_nurse" name="consulting_physician_nurse" required autocomplete="off">
                            </div>
                        </div>
                    </div>  
                </form>
            </div>
            <!-- Submit and Cancel Buttons -->
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                <button type="submit" form="addConsultationForm" class="submit-btn">Add</button>
            </div> 
        </div>
    </div>

    <!-- View Consultation Modal -->
    <div id="viewConsultationModal" class="modal">
        <div class="modal-content">
            <h2>Consultation Details</h2>
            <div class="form-scroll">
                <div class="form-grid">
                    <div class="form-group">
                        <div class="form-row">
                            <label>Type of Consultation</label>
                            <input type="text" id="view_consultation_type" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-row">
                            <label>Date of Consultation</label>
                            <input type="text" id="view_consultation_date" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-row">
                            <label>Reason for Consultation</label>
                            <input type="text" id="view_reason_for_consultation" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-row-vitals">
                            <label>Blood Pressure</label>
                            <input type="text" id="view_blood_pressure" readonly>
                        </div>
                        <div class="form-row">
                            <label>Temperature</label>
                            <input type="text" id="view_temperature" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-row">
                            <label>Diagnosis</label>
                            <input type="text" id="view_diagnosis" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-row">
                            <label>Prescribed Medicine</label>
                            <input type="text" id="view_prescribed_medicine" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-row">
                            <label>Treatment Given</label>
                            <input type="text" id="view_treatment_given" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-row">
                            <label>Consulting Physician/Nurse</label>
                            <input type="text" id="view_consulting_physician_nurse" readonly>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>
    <?php
    // Edit Patient Modal (to be placed before the closing </body> tag in view_patient.php)
    echo '
    <div id="editPatientModal" class="modal">
        <div class="modal-content">
            <h2 class="title">Edit Patient</h2>
            <div class="form-scroll">
                <form id="editPatientForm">
                    <div class="form-grid">
                        <input type="hidden" id="edit_patient_id" name="patient_id" value="' . htmlspecialchars($patient_id) . '">
                        <div class="form-group">
                            <div class="form-row-address">
                                <label>Family Number</label>
                                <input type="text" id="edit_family_number" name="family_number" value="' . htmlspecialchars($patient['family_number']) . '">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-row">
                                <label>First Name</label>
                                <input type="text" id="edit_first_name" name="first_name" value="' . htmlspecialchars($patient['first_name']) . '" autocomplete="off">
                            </div>
                            <div class="form-row">
                                <label>Middle Name</label>
                                <input type="text" id="edit_middle_name" name="middle_name" value="' . htmlspecialchars($patient['middle_name']) . '" autocomplete="off">
                            </div>
                            <div class="form-row">
                                <label>Last Name</label>
                                <input type="text" id="edit_last_name" name="last_name" value="' . htmlspecialchars($patient['last_name']) . '" autocomplete="off">
                            </div>
                        </div>
                        <p>Demographic-Socio Economic Profile</p>
                        <div class="form-group">
                            <div class="form-row">
                                <label>Birthdate</label>
                                <input type="date" id="edit_birthdate" name="birthdate" value="' . htmlspecialchars($patient['birthdate']) . '" readonly>
                            </div>
                            <div class="form-row">
                                <label>Sex</label>
                                <select name="sex" id="edit_sex">
                                    <option value="Male" ' . ($patient['sex'] == 'Male' ? 'selected' : '') . '>Male</option>
                                    <option value="Female" ' . ($patient['sex'] == 'Female' ? 'selected' : '') . '>Female</option>
                                </select>
                            </div>
                            <div class="form-row">
                                <label>Contact Number</label>
                                <input type="tel" id="edit_contact_number" name="contact_number" value="' . htmlspecialchars($patient['contact_number']) . '" autocomplete="off">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-row-address">
                                <label>Address</label>
                                <input type="text" id="edit_address" name="address" value="' . htmlspecialchars($patient['address']) . '" autocomplete="off">
                            </div>
                        </div>
                        <p>Anthropometric Measurement</p>
                        <p>Insert your height and weight to compute for your BMI and status</p>
                        <div class="form-group">
                            <div class="form-row">
                                <label>Weight (kg)</label>
                                <input type="number" id="edit_weight" name="weight" value="' . htmlspecialchars($patient['weight']) . '" step="0.01">
                            </div>
                            <div class="form-row">
                                <label>Height (cm)</label>
                                <input type="number" id="edit_height" name="height" value="' . htmlspecialchars($patient['height']) . '" step="0.01">
                            </div>
                            <div class="form-row">
                                <label>BMI</label>
                                <input type="number" id="edit_bmi" name="bmi" value="' . htmlspecialchars($patient['bmi']) . '" readonly>
                            </div>
                            <div class="form-row">
                                <label>Status</label>
                                <input type="text" id="edit_bmi_status" name="bmi_status" value="' . htmlspecialchars($patient['bmi_status']) . '" readonly>
                            </div>
                            <div class="form-row">
                                <div class="legend-box">
                                    <div class="legend-item">
                                        <span class="circle blue"></span> Underweight (< 18.5)
                                    </div>
                                    <div class="legend-item">
                                        <span class="circle green"></span> Normal (18.5 - 24.9)
                                    </div>
                                    <div class="legend-item">
                                        <span class="circle orange"></span> Overweight (25 - 29.9)
                                    </div>
                                    <div class="legend-item">
                                        <span class="circle red"></span> Obese (≥ 30)
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
                <button type="submit" form="editPatientForm" class="submit-btn">Update</button>
            </div>
        </div>
    </div>';
    ?>

    <script>
        function openLogsModal() {
            document.getElementById('updateLogsModal').classList.add('show');
            fetchUpdateLogs();
        }

        function closeLogsModal() {
            document.getElementById('updateLogsModal').classList.remove('show');
        }

        function fetchUpdateLogs() {
            const patientId = <?= $patient_id ?>;
            fetch(`get_patient_update_logs.php?patient_id=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('logsTableBody');
                    const noLogs = document.getElementById('noLogs');
                    tbody.innerHTML = '';

                    if (data.length === 0) {
                        noLogs.style.display = 'block';
                        return;
                    }

                    noLogs.style.display = 'none';

                    data.forEach(log => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${formatDate(log.updated_at)}</td>
                            <td>${escapeHtml(log.updated_by_name)} (${log.updated_by_type})</td>
                            <td><strong>${escapeHtml(log.field_changed)}</strong></td>
                            <td>${escapeHtml(log.old_value)}</td>
                            <td>${escapeHtml(log.new_value)}</td>
                        `;
                        tbody.appendChild(row);
                    });
                });
        }

        function formatDate(dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-PH') + ' ' + d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.toString().replace(/[&<>"']/g, match => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            })[match]);
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const msg = document.querySelector('.message');
            if (msg) {
                setTimeout(() => {
                    msg.style.opacity = '0';
                    setTimeout(() => msg.remove(), 600);
                }, 3000);
            }
        });
        
        function openModal() {
            let modal = document.getElementById("addConsultationModal");
            modal.classList.add("show");
        }

        function closeModal() {
            let modal = document.getElementById("addConsultationModal");
            modal.classList.remove("show");
            document.getElementById("addConsultationForm").reset(); // Clear form
        }

        function viewConsultation(consultation) {
            // Populate modal fields
            document.getElementById('view_consultation_type').value = consultation.consultation_type || '';
            document.getElementById('view_consultation_date').value = consultation.consultation_date || '';
            document.getElementById('view_reason_for_consultation').value = consultation.reason_for_consultation || '';
            document.getElementById('view_blood_pressure').value = consultation.blood_pressure || '';
            document.getElementById('view_temperature').value = consultation.temperature || '';
            document.getElementById('view_diagnosis').value = consultation.diagnosis || '';
            document.getElementById('view_prescribed_medicine').value = consultation.prescribed_medicine || '';
            document.getElementById('view_treatment_given').value = consultation.treatment_given || '';
            document.getElementById('view_consulting_physician_nurse').value = consultation.consulting_physician_nurse || '';

            // Open modal
            let modal = document.getElementById('viewConsultationModal');
            modal.classList.add('show');
        }

        function closeViewModal() {
            let modal = document.getElementById('viewConsultationModal');
            modal.classList.remove('show');
        }

        // AJAX Form Submission
        document.getElementById("addConsultationForm").addEventListener("submit", function(event) {
            event.preventDefault();

            let formData = new FormData(this);

            fetch("add_consultation_ajax.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json()) // Parse JSON response
            .then(data => {
                if (data.status === "success") {
                    closeModal();
                    location.reload(); // Refresh page after submission
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("An error occurred while adding the consultation. Please try again.");
            });
        });
    </script>

    <script>
        // Add this to the JavaScript section
        function openEditModal() {
            let modal = document.getElementById("editPatientModal");
            modal.classList.add("show");
        }

        function closeEditModal() {
            let modal = document.getElementById("editPatientModal");
            modal.classList.remove("show");
        }

        // Update the Edit button click handler in the patient-info section
        document.querySelector(".edit-btn").addEventListener("click", function() {
            openEditModal();
        });

        // BMI calculation for edit form
        function calculateEditBMI() {
            let weight = parseFloat(document.getElementById("edit_weight").value);
            let height = parseFloat(document.getElementById("edit_height").value) / 100; // Convert cm to meters
            if (weight > 0 && height > 0) {
                let bmi = (weight / (height * height)).toFixed(2);
                document.getElementById("edit_bmi").value = bmi;
                let status = "";
                if (bmi < 18.5) {
                    status = "Underweight";
                } else if (bmi < 24.9) {
                    status = "Normal";
                } else if (bmi < 29.9) {
                    status = "Overweight";
                } else {
                    status = "Obese";
                }
                document.getElementById("edit_bmi_status").value = status;
            } else {
                document.getElementById("edit_bmi").value = "";
                document.getElementById("edit_bmi_status").value = "";
            }
        }

        // Attach event listeners for BMI calculation
        document.getElementById("edit_weight").addEventListener("input", calculateEditBMI);
        document.getElementById("edit_height").addEventListener("input", calculateEditBMI);

        // AJAX Form Submission for the edit form with confirmation
        document.getElementById("editPatientForm").addEventListener("submit", function(event) {
            event.preventDefault();
            if (confirm("Are you sure you want to update this patient's information?")) {
                let formData = new FormData(this);
                fetch("update_patient_ajax.php", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.json()) // Parse JSON response
                .then(data => {
                    if (data.status === "success") {
                        closeEditModal();
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("An error occurred while updating the patient. Please try again.");
                });
            }
        });
    </script>

</body>
</html>
