<?php
//family_number.php
session_start();
require_once "config.php"; // include your database connection

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

$familyNumber = $_GET['family_number'];

// Fetch all patients with the same family number
$stmt = $conn->prepare("SELECT * FROM patients WHERE family_number = :family_number");
$stmt->bindParam(":family_number", $familyNumber);
$stmt->execute();
$familyPatients = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            ?>
            <p class="menu-header">ANALYTICS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/dashboard_icon.png" alt="">
                <a href="admin_dashboard.php" class="<?= $current_page == 'admin_dashboard.php' ? 'active' : '' ?>">Dashboard</a>
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
                <a href="patient_management.php" class="<?= ($current_page == 'patient_management.php' || $current_page == 'family_number.php')  ? 'active' : '' ?>">Patient Management</a>
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

    <!-- Main Content -->
    <div class="family-container">
        <div class="title-con">
            <div style="display: flex; gap: 15px; align-items: center;">
                <a href="#" class="back-button" onclick="history.back(); return false;">← Back</a>
                <h2>Patients with Family Number: <?= htmlspecialchars($familyNumber) ?></h2>
            </div>
            <button class="add-btn" onclick="openFamilyMemberModal()">Add Family Member</button>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Last Name</th>
                    <th>First Name</th>
                    <th>Birthdate</th>
                    <th>Sex</th>
                    <th>Contact</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($familyPatients)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">No patients found with this family number.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($familyPatients as $patient): ?>
                    <tr>
                        <td><?= htmlspecialchars($patient['last_name']) ?></td>
                        <td><?= htmlspecialchars($patient['first_name']) ?></td>
                        <td><?= htmlspecialchars($patient['birthdate']) ?></td>
                        <td><?= htmlspecialchars($patient['sex']) ?></td>
                        <td><?= htmlspecialchars($patient['contact_number']) ?></td>
                        <td><a href="view_patient.php?id=<?= $patient['id'] ?>" class="view-btn">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Family Member Modal -->
    <div id="addFamilyMemberModal" class="modal">
        <div class="modal-content">
            <h2 class="title">Add Family Member</h2>
            <form id="addFamilyMemberForm">
                <div class="form-grid">
                    <div class="form-group">
                        <div class="form-row-address">
                            <label>Family Number</label>
                            <input type="text" id="family_member_number" name="family_number" value="<?= htmlspecialchars($familyNumber) ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-row">
                            <label>First Name</label>
                            <input type="text" id="family_first_name" name="first_name" required>
                        </div>
                        <div class="form-row">
                            <label>Middle Name</label>
                            <input type="text" id="family_middle_name" name="middle_name">
                        </div>
                        <div class="form-row">
                            <label>Last Name</label>
                            <input type="text" id="family_last_name" name="last_name" required>
                        </div>
                    </div>
                    <p>Demographic-Socio Economic Profile</p>
                    <div class="form-group">
                        <div class="form-row">
                            <label>Birthdate</label>
                            <input type="date" id="family_birthdate" name="birthdate" required>
                        </div>
                        <div class="form-row">
                            <label>Sex</label>
                            <select name="sex" id="family_sex" required>
                                <option value="" disabled selected>Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label>Civil Status</label>
                            <select name="civil_status" id="family_civil_status" required>
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
                            <input type="tel" id="family_contact_number" name="contact_number">
                        </div>
                        <div class="form-row">
                            <label>Occupation</label>
                            <input type="text" id="family_occupation" name="occupation">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-row-address">
                            <label>Address</label>
                            <input type="text" id="family_address" name="address">
                        </div>
                    </div>
                    <p>Anthropometric Measurement</p>
                    <p>Insert your height and weight to compute for your BMI and status</p>
                    <div class="form-group">
                        <div class="form-row">
                            <label>Weight (kg)</label>
                            <input type="number" id="family_weight" name="weight" step="0.01">
                        </div>
                        <div class="form-row">
                            <label>Height (cm)</label>
                            <input type="number" id="family_height" name="height" step="0.01">
                        </div>
                        <div class="form-row">
                            <label>BMI</label>
                            <input type="number" id="family_bmi" name="bmi" readonly>
                        </div>
                        <div class="form-row">
                            <label>Status</label>
                            <input type="text" id="family_bmi_status" name="bmi_status" readonly>
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
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeFamilyMemberModal()">Cancel</button>
                    <button type="submit" class="submit-btn">Add</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openFamilyMemberModal() {
            let modal = document.getElementById("addFamilyMemberModal");
            modal.classList.add("show");
        }

        function closeFamilyMemberModal() {
            let modal = document.getElementById("addFamilyMemberModal");
            modal.classList.remove("show");
            document.getElementById("addFamilyMemberForm").reset();
            // Reset family number to original value
            document.getElementById("family_member_number").value = "<?= htmlspecialchars($familyNumber) ?>";
        }

        // BMI calculation for family member form
        function calculateFamilyBMI() {
            let weight = parseFloat(document.getElementById("family_weight").value);
            let height = parseFloat(document.getElementById("family_height").value) / 100; // Convert cm to meters
            if (weight > 0 && height > 0) {
                let bmi = (weight / (height * height)).toFixed(2);
                document.getElementById("family_bmi").value = bmi;
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
                document.getElementById("family_bmi_status").value = status;
            } else {
                document.getElementById("family_bmi").value = "";
                document.getElementById("family_bmi_status").value = "";
            }
        }

        // Attach event listeners for BMI calculation
        document.getElementById("family_weight").addEventListener("input", calculateFamilyBMI);
        document.getElementById("family_height").addEventListener("input", calculateFamilyBMI);

        // AJAX Form Submission for family member
        document.getElementById("addFamilyMemberForm").addEventListener("submit", function(event) {
            event.preventDefault();
            if (confirm("Are you sure you want to add this family member?")) {
                let formData = new FormData(this);
                fetch("add_family_member_ajax.php", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.status === "success") {
                        closeFamilyMemberModal();
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("An error occurred while adding the family member. Please try again.");
                });
            }
        });
    </script>
</body>
</html>