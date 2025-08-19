<?php
//patient_management.php
session_start();
require 'config.php';

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(family_number LIKE :search OR first_name LIKE :search OR last_name LIKE :search OR middle_name LIKE :search)";
    $params[':search'] = "%$search%";
}

$searchCondition = '';
if (!empty($conditions)) {
    $searchCondition = "WHERE " . implode(" AND ", $conditions);
}

// Fetch patients
$query = "SELECT * FROM patients $searchCondition ORDER BY id DESC";
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
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
        <!-- Search and Add Patient -->
        <div class="search-container">
            <form method="GET" action="" id="searchForm">
                <div class="search-row">
                    <input type="text" name="search" id="searchInput" placeholder="Search for ID No./Name" value="<?= htmlspecialchars($search) ?>">
                    <a href="patient_management.php" class="clear-btn">Clear</a>
                </div>
            </form>
            <button class="add-button" onclick="openModal()">Add Patient</button>
        </div>

        <!-- Patient List Table -->
        <table class="patient-table">
            <thead>
                <tr>
                    <th>Family No.</th>
                    <th>Last Name</th>
                    <th>First Name</th>
                    <th>Middle Name</th>
                    <th>Sex</th>
                    <th>Birthdate</th>
                    <th>Civil Status</th>
                    <th>Contact Number</th>
                    <th>Date Registered</th>
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
                        <td><?= htmlspecialchars($patient['family_number']) ?></td>
                        <td><?= htmlspecialchars($patient['last_name']) ?></td>
                        <td><?= htmlspecialchars($patient['first_name']) ?></td>
                        <td><?= htmlspecialchars($patient['middle_name']) ?></td>
                        <td><?= htmlspecialchars($patient['sex']) ?></td>
                        <td><?= htmlspecialchars($patient['birthdate']) ?></td>
                        <td><?= htmlspecialchars($patient['civil_status']) ?></td>
                        <td><?= htmlspecialchars($patient['contact_number']) ?></td>
                        <td><?= htmlspecialchars($patient['created_at']) ?></td>
                        <td class="action-buttons">
                            <a href="view_patient.php?id=<?= $patient['id'] ?>" class="view-btn">VIEW</a>
                            <a href="delete_patient.php?id=<?= $patient['id'] ?>" class="delete-btn" onclick="return confirm('Are you sure?')">DELETE</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Patient Modal -->
    <div id="addPatientModal" class="modal">
        <div class="modal-content">
            <h2 class="title">Add Patient</h2>
            <form id="addPatientForm">
                <div class="form-grid">
                    <div class="form-group">
                        <div class="form-row-address">
                            <label>Family Number</label>
                            <input type="text" id="family_number" name="family_number" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-row">
                            <label>First Name</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        <div class="form-row">
                            <label>Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name">
                        </div>
                        <div class="form-row">
                            <label>Last Name</label>
                            <input type="text" id="last_name" name="last_name" required>
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
                            <label>Civil Status</label>
                            <select name="civil_status" id="civil_status" required>
                                <option value="" disabled selected>Select</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-row">
                            <label>Contact Number</label>
                            <input type="tel" id="contact_number" name="contact_number">
                        </div>
                        <div class="form-row">
                             <label>Occupation</label>
                            <input type="text" id="occupation" name="occupation">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-row-address">
                            <label>Address</label>
                            <input type="text" id="address" name="address">
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
                <!-- Submit and Cancel Buttons -->
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="submit-btn">Add</button>
                </div>
            </form>
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
        document.getElementById("addPatientForm").addEventListener("submit", function(event) {
            event.preventDefault();

            let formData = new FormData(this);

            fetch("add_patient_ajax.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                closeModal();
                location.reload(); // Refresh page after submission
            })
            .catch(error => console.error("Error:", error));
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
            document.getElementById("sex").addEventListener("change", calculateWHRatio);
        });
    </script>

</body>
</html>