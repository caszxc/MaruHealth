<?php
//manage_staff.php
session_start();
require_once "config.php"; 

// Security: Redirect if not super admin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'super_admin') {
    header("Location: admin_dashboard.php");
    exit();
}

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

// Handle staff search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchWhere = '';
if (!empty($search)) {
    $searchWhere = " WHERE full_name LIKE :search OR id LIKE :search OR username LIKE :search OR email LIKE :search";
}

// Fetch all admin staff except super_admin
$whereClause = " WHERE role != 'super_admin'";
if (!empty($search)) {
    $whereClause .= " AND (full_name LIKE :search OR id LIKE :search OR username LIKE :search OR email LIKE :search)";
}

$staffQuery = "SELECT * FROM admin_staff" . $whereClause . " ORDER BY id ASC";
$staffStmt = $conn->prepare($staffQuery);

if (!empty($search)) {
    $searchParam = "%$search%";
    $staffStmt->bindParam(':search', $searchParam);
}

$staffStmt->execute();
$staffMembers = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle staff deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = $_GET['delete'];
    
    // Don't allow self-deletion
    if ($deleteId != $_SESSION['admin_id']) {
        $deleteStmt = $conn->prepare("DELETE FROM admin_staff WHERE id = :id");
        $deleteStmt->bindParam(':id', $deleteId);
        $deleteStmt->execute();
        
        // Redirect to avoid resubmission
        header("Location: manage_staff.php");
        exit();
    }
}

// We've removed the form processing code for adding a new admin
// as it's now handled by add_admin_ajax.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff</title>
    <link rel="stylesheet" href="css/manage_staff.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="images/3s logo.png" alt="Logo">
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
                $dashboard_url = 'admin_dashboard.php'; // Default
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
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/account_approval_icon_active.png" alt="">
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
    <div class="container">
        <!-- Search and Add Admin -->
        <div class="search-container">
            <form method="GET" action="">
                <input type="text" name="search" placeholder="Search for Name/ID No." value="<?= htmlspecialchars($search) ?>">
            </form>
            <button class="add-button" onclick="openModal()">Add Admin</button>
        </div>

        <!-- Admin/Staff Table -->
        <table class="accounts-table">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Email Address</th>
                    <th>Username</th>
                    <th>Date/Time Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staffMembers)): ?>
                    <tr>
                        <td colspan="10" class="no-accounts" style="text-align: center;">No Accounts found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($staffMembers as $staff): ?>
                        <tr>
                            <td><?= htmlspecialchars($staff['full_name']) ?></td>
                            <td><?= ucwords(str_replace('_', ' ', $staff['role'])) ?></td>
                            <td><?= htmlspecialchars($staff['email']) ?></td>
                            <td><?= htmlspecialchars($staff['username']) ?></td>
                            <td><?= date('m/d/Y g:iA', strtotime($staff['created_at'])) ?></td>
                            <td>
                                <a href="#" class="view-btn" onclick="openViewModal(<?= $staff['id'] ?>)">View</a>
                                <?php if ($staff['id'] != $_SESSION['admin_id']): ?>
                                    <a href="manage_staff.php?delete=<?= $staff['id'] ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this staff member?')">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Admin Modal -->
    <div id="addAdminModal" class="modal">
        <div class="modal-content">
            <h2 class="title">Add New Admin</h2>
            <form id="addAdminForm">
                <div class="form-container">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="submit-btn">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Admin Modal -->
    <div id="viewAdminModal" class="modal">
        <div class="modal-content">
            <h2 class="title">View Staff Details</h2>
            <div class="form-container">
                <div class="form-group">
                    <label>Full Name</label>
                    <p id="view_full_name"></p>
                </div>
                
                <div class="form-group">
                    <label>Role</label>
                    <p id="view_role"></p>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <p id="view_email"></p>
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <p id="view_username"></p>
                </div>
                
                <div class="form-group">
                    <label>Date/Time Created</label>
                    <p id="view_created_at"></p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Add Admin Modal Functions
        function openModal() {
            let modal = document.getElementById("addAdminModal");
            modal.classList.add("show");
        }

        function closeModal() {
            let modal = document.getElementById("addAdminModal");
            modal.classList.remove("show");
            document.getElementById("addAdminForm").reset();
        }

        // AJAX Form Submission for Add Admin
        document.getElementById("addAdminForm").addEventListener("submit", function(event) {
            event.preventDefault();

            let formData = new FormData(this);

            fetch("add_admin_ajax.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                if (data.includes("successfully")) {
                    closeModal();
                    location.reload();
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("An error occurred. Please try again.");
            });
        });

        // View Admin Modal Functions
        function openViewModal(staffId) {
            let modal = document.getElementById("viewAdminModal");
            modal.classList.add("show");

            // Fetch staff details via AJAX
            fetch("view_staff_ajax.php?id=" + staffId, {
                method: "GET"
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    closeViewModal();
                } else {
                    document.getElementById("view_full_name").textContent = data.full_name;
                    document.getElementById("view_role").textContent = data.role.charAt(0).toUpperCase() + data.role.slice(1).replace('_', ' ');
                    document.getElementById("view_email").textContent = data.email;
                    document.getElementById("view_username").textContent = data.username;
                    document.getElementById("view_created_at").textContent = data.created_at;
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("An error occurred while fetching staff details.");
                closeViewModal();
            });
        }

        function closeViewModal() {
            let modal = document.getElementById("viewAdminModal");
            modal.classList.remove("show");
        }
    </script>
</body>
</html>