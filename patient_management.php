<?php
// patient_management.php
session_start();
require 'config.php';
include 'settings.php';

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Search functionality
$rawSearch = isset($_GET['search']) ? trim($_GET['search']) : ''; // Store raw search term
$searchCondition = '';
$searchParams = [];

// Build search condition if search term is provided
if (!empty($rawSearch)) {
    $search = "%{$rawSearch}%"; // Add wildcards for SQL query
    $searchCondition = "WHERE status = 'active' AND (family_number LIKE :search OR last_name LIKE :search OR first_name LIKE :search OR middle_name LIKE :search)";
    $searchParams[':search'] = $search;
} else {
    $searchCondition = "WHERE status = 'active'";
}

// Fetch patients with search
$query = "SELECT * FROM patients $searchCondition ORDER BY id DESC";
$stmt = $conn->prepare($query);
if (!empty($rawSearch)) {
    $stmt->bindParam(':search', $search);
}
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <link rel="stylesheet" href="css/patient_management.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= $logo_url ?>" type="image/x-icon">
    <style>
        .message {
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            transition: opacity .5s ease-in-out;
        }
        .message.success { background:#dff0d8; color:#3c763d; }
        .message.error   { background:#f2dede; color:#a94442; }
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
                <a href="patient_management.php" class="<?= $current_page == 'patient_management.php' ? 'active' : '' ?>">Patient Management</a>
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

    <div class="patient-content">
        <div class="patient-container">
            <div class="sort-controls">
                <div class="search-con">
                    <form method="GET" action="patient_management.php">
                        <input type="text" name="search" placeholder="Search Patient..." value="<?= htmlspecialchars($rawSearch) ?>">
                        <button type="submit">Search</button>
                    </form>
                </div>
                <!-- Add Patient and View Archived Patients Buttons -->
                <div class="button-group">
                    <button class="add-button" onclick="openModal()">Add Patient</button>
                    <a href="archived_patients.php" class="archive-button">View Archived Patients</a>
                </div>
            </div>
            <!-- ----- SUCCESS / ERROR MESSAGE ----- -->
            <?php if (isset($_SESSION['patient_message'])): ?>
                <div class="message <?= strpos($_SESSION['patient_message'], 'Error') !== false && strpos($_SESSION['patient_message'], 'successfully') === false ? 'error' : 'success' ?>">
                    <?= htmlspecialchars($_SESSION['patient_message']) ?>
                </div>
                <?php unset($_SESSION['patient_message']); ?>
            <?php endif; ?>
            <!-- Patient List Table -->
            <div class="patient-table">
                <div class="table-container">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Family No.</th>
                                    <th>Last Name</th>
                                    <th>First Name</th>
                                    <th>Middle Name</th>
                                    <th>Sex</th>
                                    <th>Birthdate</th>
                                    <th>Contact Number</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($patients)): ?>
                                    <tr>
                                        <td colspan="10" class="no-patients" style="text-align: center;">No patients found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($patients as $patient): ?>
                                    <tr>
                                        <td><?= !empty($patient['family_number']) ? htmlspecialchars($patient['family_number']) : 'Not Provided' ?></td>
                                        <td><?= htmlspecialchars($patient['last_name']) ?></td>
                                        <td><?= htmlspecialchars($patient['first_name']) ?></td>
                                        <td><?= htmlspecialchars($patient['middle_name']) ?></td>
                                        <td><?= htmlspecialchars($patient['sex']) ?></td>
                                        <td><?= htmlspecialchars($patient['birthdate']) ?></td>
                                        <td><?= htmlspecialchars($patient['contact_number']) ?></td>
                                        <td class="action-buttons">
                                            <a href="view_patient.php?id=<?= $patient['id'] ?>" class="view-btn">VIEW</a>
                                            <a href="archive_patient.php?id=<?= $patient['id'] ?>" class="archive-btn" onclick="return confirm('Are you sure you want to archive this patient?')">ARCHIVE</a>
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

    <!-- Add Patient Modal -->
    <div id="addPatientModal" class="modal">
        <div class="modal-content">
            <h2>Add Patient</h2>
            <div class="form-scroll">
                <form id="addPatientForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <div class="form-row-address">
                                <label>Family Number</label>
                                <input type="text" id="family_number" name="family_number" autocomplete="off" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-row">
                                <label>First Name</label>
                                <input type="text" id="first_name" name="first_name" autocomplete="off" required>
                            </div>
                            <div class="form-row">
                                <label>Middle Name</label>
                                <input type="text" id="middle_name" name="middle_name" autocomplete="off">
                            </div>
                            <div class="form-row">
                                <label>Last Name</label>
                                <input type="text" id="last_name" name="last_name" autocomplete="off" required>
                            </div>
                        </div>
                        <p>Demographic-Socio Economic Profile</p>
                        <div class="form-group">
                            <div class="form-row">
                                <label>Birthdate</label>
                                <input type="date" id="birthdate" name="birthdate" required>
                            </div>
                            <div class="form-row">
                                <label>Sex</label>
                                <select name="sex" id="sex" required>
                                    <option value="" disabled selected>Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="form-row">
                                <label>Contact Number</label>
                                <input type="tel" id="contact_number" name="contact_number" autocomplete="off">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="form-row-address">
                                <label>Address</label>
                                <input type="text" id="address" name="address" autocomplete="off">
                            </div>
                        </div>
                        <p>Anthropometric Measurement</p>
                        <p>Insert your height and weight to compute for your BMI and status</p>
                        <div class="form-group">
                            <div class="form-row">
                                <label>Weight (kg)</label>
                                <input type="number" id="weight" name="weight">
                            </div>
                            <div class="form-row">
                                <label>Height (cm)</label>
                                <input type="number" id="height" name="height">
                            </div>
                            <div class="form-row">
                                <label>BMI</label>
                                <input type="number" id="bmi" name="bmi" readonly>
                            </div>
                            <div class="form-row">
                                <label>Status</label>
                                <input type="text" id="bmi_status" name="bmi_status" readonly>
                            </div>
                            <div class="form-row">
                                <div class="legend-box">
                                    <div class="legend-item">
                                        <span class="circle blue"></span> Underweight (< 18.5)
                                    </div>
                                    <div class="legend-item">
                                        <span class="circle green"></span> Normal (18.5 - 22.9)
                                    </div>
                                    <div class="legend-item">
                                        <span class="circle orange"></span> Overweight (> 23 - 24.9)
                                    </div>
                                    <div class="legend-item">
                                        <span class="circle red"></span> Obese (> 25)
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <!-- Submit and Cancel Buttons -->
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                <button type="submit" form="addPatientForm" class="submit-btn">Add</button>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            let modal = document.getElementById("addPatientModal");
            modal.classList.add("show");
        }

        function closeModal() {
            let modal = document.getElementById("addPatientModal");
            modal.classList.remove("show");
        }

        // AJAX Form Submission
        document.getElementById('addPatientForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('add_patient_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                closeModal();
                location.reload();   // reload → session message will be displayed
            })
            .catch(() => {
                alert('Network error. Please try again.');
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            const msg = document.querySelector('.message');
            if (msg) {
                setTimeout(() => {
                    msg.style.opacity = '0';
                    setTimeout(() => msg.style.display = 'none', 500);
                }, 3000);
            }
        });
    </script>

    <!-- Calculation -->  
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            function calculateBMI() {
                let weight = parseFloat(document.getElementById("weight").value);
                let height = parseFloat(document.getElementById("height").value) / 100; // Convert cm to meters
                
                if (weight > 0 && height > 0) {
                    let bmi = (weight / (height * height)).toFixed(2);
                    document.getElementById("bmi").value = bmi;
                    
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
                    document.getElementById("bmi_status").value = status;
                }
            }

            // Attach event listeners
            document.getElementById("weight").addEventListener("input", calculateBMI);
            document.getElementById("height").addEventListener("input", calculateBMI);
        });
    </script>
</body>
</html>