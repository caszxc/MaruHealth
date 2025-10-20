<?php
// approvedAcc_requests.php
session_start();
require_once "config.php"; // include your database connection

// Check if user is logged in as super admin or admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Capture and sanitize search term
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch approved users with optional search filter
$query = "
    SELECT id, 
        CONCAT(first_name, ' ', last_name) AS full_name, 
        first_name, 
        last_name, 
        middle_name, 
        gender, 
        birthday, 
        address, 
        email, 
        phone_number, 
        valid_id_front, 
        role 
    FROM users 
    WHERE role != 'admin'
";
if (!empty($searchTerm)) {
    $query .= " AND (first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR phone_number LIKE :search)";
}
$query .= " ORDER BY id DESC";

$approvedUsersStmt = $conn->prepare($query);
if (!empty($searchTerm)) {
    $searchParam = "%$searchTerm%";
    $approvedUsersStmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
}
$approvedUsersStmt->execute();
$approvedUsers = $approvedUsersStmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Approved Accounts</title>
    <link rel="stylesheet" href="css/account_approval.css">
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
                <img class="menu-icon" src="images/icons/admin_icon.png" alt="">
                <a href="manage_staff.php" class="<?= $current_page == 'manage_staff.php' ? 'active' : '' ?>">Admin Account Management</a>
            </div>
            <?php endif; ?>
            <?php if ($adminRole == 'super_admin' || $adminRole == 'admin'): ?>
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/account_approval_icon_active.png" alt="">
                <a href="account_approval.php" class="<?= ($current_page == 'account_approval.php' || $current_page == 'approvedAcc_requests.php') ? 'active' : '' ?>">User Account Management</a>
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
                <img class="menu-icon" src="images/icons/service_icon.png" alt="">
                <a href="service_management.php" class="<?= $current_page == 'service_management.php' ? 'active' : '' ?>">Service Management</a>
            </div>
            <?php endif; ?>
            <?php if ($adminRole == 'health_staff'): ?>
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
    <div class="approval-content">
        <div class="title-con">
            <a href="account_approval.php" class="back-button">← Back</a>
            <h2>Approved Accounts</h2>
        </div>
        <div class="approval-container">
            <div class="sort-control">
                <div class="search-con">
                    <form method="GET" action="approvedAcc_requests.php">
                        <input type="text" name="search" placeholder="Search by Name, Email, or Phone Number" value="<?= htmlspecialchars($searchTerm) ?>">
                        <button type="submit">Search</button>
                    </form>
                </div> 
            </div>
            <div class="table-details">
                <div class="table-con">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone Number</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($approvedUsers)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center;">No approved users found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($approvedUsers as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= htmlspecialchars($user['phone_number']) ?></td>
                                            <td>
                                                <a href="#" 
                                                   class="view-btn" 
                                                   data-id="<?= $user['id'] ?>"
                                                   data-firstname="<?= htmlspecialchars($user['first_name']) ?>"
                                                   data-lastname="<?= htmlspecialchars($user['last_name']) ?>"
                                                   data-middlename="<?= htmlspecialchars($user['middle_name']) ?>"
                                                   data-email="<?= htmlspecialchars($user['email']) ?>"
                                                   data-phone="<?= htmlspecialchars($user['phone_number']) ?>"
                                                   data-address="<?= htmlspecialchars($user['address']) ?>"
                                                   data-gender="<?= htmlspecialchars($user['gender']) ?>"
                                                   data-birthday="<?= htmlspecialchars($user['birthday']) ?>"
                                                   data-idfront="<?= htmlspecialchars($user['valid_id_front']) ?>"
                                                >View</a>
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
    
    <!-- Modal Structure -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <h2 class="title">Account Details</h2>
            <div class="user-info">
                <div class="info-group">
                    <label>Last Name</label>
                    <span id="lastName"></span>
                </div>
                <div class="info-group">
                    <label>First Name</label>
                    <span id="firstName"></span>
                </div>
                <div class="info-group">
                    <label>Middle Name</label>
                    <span id="middleName"></span>
                </div>
                <div class="info-row">
                    <div class="info-group">
                        <label>Gender</label>
                        <span id="gender"></span>
                    </div>
                    <div class="info-group">
                        <label>Date of Birth</label>
                        <span id="birthday"></span>
                    </div>
                </div>
                <div class="info-group">
                    <label>Address</label>
                    <span id="address"></span>
                </div>
                <div class="info-group">
                    <label>E-mail Address</label>
                    <span id="email"></span>
                </div>
                <div class="info-group">
                    <label>Phone Number</label>
                    <span id="phone"></span>
                </div>
            </div>
            <div class="id-preview">
                <label>Uploaded Valid ID</label>
                <img id="idFront" src="" alt="Valid ID Front">
            </div>
            <button type="button" class="close-btn" onclick="closeModal()">Close</button>
        </div>
    </div>
    
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const modal = document.getElementById("viewModal");
            const viewButtons = document.querySelectorAll(".view-btn");

            // Function to open modal and populate with data
            viewButtons.forEach(button => {
                button.addEventListener("click", function (event) {
                    event.preventDefault();
                    document.getElementById("lastName").textContent = this.dataset.lastname;
                    document.getElementById("firstName").textContent = this.dataset.firstname;
                    document.getElementById("middleName").textContent = this.dataset.middlename;
                    document.getElementById("gender").textContent = this.dataset.gender;
                    document.getElementById("birthday").textContent = this.dataset.birthday;
                    document.getElementById("address").textContent = this.dataset.address;
                    document.getElementById("email").textContent = this.dataset.email;
                    document.getElementById("phone").textContent = this.dataset.phone;
                    document.getElementById("idFront").src = this.dataset.idfront;
                    modal.style.display = "flex";
                });
            });
        });

        function closeModal() {
            const modal = document.getElementById("viewModal");
            modal.style.display = "none";
        }

        document.addEventListener("DOMContentLoaded", function () {
            const closeModalButtons = document.querySelectorAll(".close-btn");
            closeModalButtons.forEach(button => {
                button.addEventListener("click", closeModal);
            });

            window.addEventListener("click", function (event) {
                const modal = document.getElementById("viewModal");
                if (event.target === modal) {
                    closeModal();
                }
            });
        });
    </script>


</body>
</html>