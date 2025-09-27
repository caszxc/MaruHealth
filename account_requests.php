<?php
//account_requests.php
session_start();
require_once "config.php"; // include your database connection
require_once "email_function.php"; // include email functionality

// Check if user is logged in as super admin or admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Handle Approve/Reject actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $pendingUserId = intval($_GET['id']);
    
    // Fetch user data first to get contact information
    $fetchUserStmt = $conn->prepare("SELECT * FROM pending_users WHERE id = :id");
    $fetchUserStmt->execute([':id' => $pendingUserId]);
    $userData = $fetchUserStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($userData) {
        $userPhone = $userData['phone_number'];
        $userEmail = $userData['email'];
        $userName = $userData['first_name'] . ' ' . $userData['last_name'];
        
        if ($action === 'approve') {
            // Begin transaction to ensure data consistency
            $conn->beginTransaction();
            try {
                // Transfer user from pending_users to users
                $approveStmt = $conn->prepare("
                    INSERT INTO users (
                        registration_type, age_category, family_number, first_name, last_name, middle_name, 
                        gender, birthday, address, email, phone_number, valid_id_front, 
                        password, role, date_registered
                    )
                    SELECT 
                        registration_type, age_category, family_number, first_name, last_name, middle_name, 
                        gender, birthday, address, email, phone_number, valid_id_front, 
                        password, role, date_registered
                    FROM pending_users
                    WHERE id = :id
                ");
                $approveStmt->execute([':id' => $pendingUserId]);
                
                // Get the newly inserted user ID
                $newUserId = $conn->lastInsertId();

                // Check if a patient record already exists
                $checkPatientStmt = $conn->prepare("
                    SELECT id FROM patients 
                    WHERE first_name = :first_name 
                    AND last_name = :last_name 
                    AND birthdate = :birthdate
                    AND status = 'active'
                ");
                $checkPatientStmt->execute([
                    ':first_name' => $userData['first_name'],
                    ':last_name' => $userData['last_name'],
                    ':birthdate' => $userData['birthday']
                ]);
                $existingPatient = $checkPatientStmt->fetch(PDO::FETCH_ASSOC);

                // Only insert into patients table if no existing record is found
                if (!$existingPatient) {
                    // Use family_number from pending_users or GET parameter
                    $familyNumber = $userData['family_number'] ?: '';
                    if (isset($_GET['family_number']) && !empty(trim($_GET['family_number']))) {
                        $familyNumber = trim($_GET['family_number']);
                        // Insert into families table if it doesn't exist
                        $familyStmt = $conn->prepare("
                            INSERT INTO families (family_number, member_count)
                            SELECT :family_number, 1
                            WHERE NOT EXISTS (
                                SELECT 1 FROM families WHERE family_number = :family_number
                            )
                        ");
                        $familyStmt->execute([':family_number' => $familyNumber]);
                    }

                    // Insert into patients table
                    $patientStmt = $conn->prepare("
                        INSERT INTO patients (
                            family_number, first_name, middle_name, last_name, birthdate, 
                            sex, contact_number, address, weight, height, bmi, bmi_status, status
                        )
                        VALUES (
                            :family_number, :first_name, :middle_name, :last_name, :birthdate, 
                            :sex, :contact_number, :address, NULL, NULL, NULL, NULL, 'active'
                        )
                    ");
                    $patientStmt->execute([
                        ':family_number' => $familyNumber,
                        ':first_name' => $userData['first_name'],
                        ':middle_name' => $userData['middle_name'] ?: null,
                        ':last_name' => $userData['last_name'],
                        ':birthdate' => $userData['birthday'],
                        ':sex' => $userData['gender'],
                        ':contact_number' => $userData['phone_number'] ?: null,
                        ':address' => $userData['address'] ?: null
                    ]);
                }

                // Compose approval email
                $subject = "Maru-Health Account Approval";
                $message = "
                    <h2>Account Approved</h2>
                    <p>Dear $userName,</p>
                    <p>Your Maru-Health account registration has been approved. You may now log in to our Website.</p>
                    <p>Best regards,<br>Maru-Health Team</p>
                ";
                
                // Send email notification
                $emailResponse = sendEmail($userEmail, $userName, $subject, $message);
                
                // Remove from pending_users
                $deleteStmt = $conn->prepare("DELETE FROM pending_users WHERE id = :id");
                $deleteStmt->execute([':id' => $pendingUserId]);
                
                // Commit transaction
                $conn->commit();
                
                header("Location: account_approval.php");
                exit();
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['approval_message'] = "Error approving account: " . $e->getMessage();
                header("Location: account_approval.php");
                exit();
            }
        
        } elseif ($action === 'reject') {
            // Fetch the uploaded file paths
            $fetchFilesStmt = $conn->prepare("SELECT valid_id_front FROM pending_users WHERE id = :id");
            $fetchFilesStmt->execute([':id' => $pendingUserId]);
            $files = $fetchFilesStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($files) {
                // Define upload directory
                $uploadDir = "images/uploads/IDs/";
                
                // Delete front ID if exists
                if (!empty($files['valid_id_front'])) {
                    $filePath = $uploadDir . basename($files['valid_id_front']);
                    if (file_exists($filePath)) {
                        unlink($filePath); // Delete the file
                    }
                }

                // Delete guardian ID file if exists
                if (!empty($files['valid_id_path'])) {
                    $guardianFilePath = $uploadDir . basename($files['valid_id_path']);
                    if (file_exists($guardianFilePath)) {
                        unlink($guardianFilePath); // Delete the file
                    }
                }
            }
            
            // Compose rejection email
            $subject = "Maru-Health Account Registration Update";
            $message = "
                <h2>Account Registration Update</h2>
                <p>Dear $userName,</p>
                <p>We regret to inform you that your Maru-Health account registration could not be approved at this time.</p>
                <p>Please contact our office for further assistance or to clarify any requirements.</p>
                <p>Best regards,<br>Maru-Health Team</p>
            ";
            
            // Send email notification
            $emailResponse = sendEmail($userEmail, $userName, $subject, $message);
            
            // Delete user from pending_users
            $rejectStmt = $conn->prepare("DELETE FROM pending_users WHERE id = :id");
            $rejectStmt->execute([':id' => $pendingUserId]);
            
            header("Location: account_approval.php");
            exit();
        }
    } else {
        $_SESSION['approval_message'] = "Error: User not found.";
        header("Location: account_approval.php");
        exit();
    }
}

// Fetch all pending users and their guardian information
$pendingUsersStmt = $conn->prepare("
    SELECT 
        pu.id, 
        CONCAT(pu.first_name, ' ', pu.last_name) AS full_name, 
        pu.first_name,
        pu.last_name,
        pu.middle_name, 
        pu.gender, 
        pu.birthday, 
        pu.address, 
        pu.email, 
        pu.phone_number, 
        pu.valid_id_front, 
        pu.role,
        pu.registration_type,
        g.full_name AS guardian_name,
        g.relationship AS guardian_relationship,
        g.phone_number AS guardian_phone,
        g.email AS guardian_email,
        g.valid_id_path AS guardian_id_path
    FROM pending_users pu
    LEFT JOIN guardians g ON pu.id = g.pending_user_id
    ORDER BY pu.date_registered DESC
");

$pendingUsersStmt->execute();
$pendingUsers = $pendingUsersStmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Account Approval</title>
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
            
            <?php if ($adminRole == 'super_admin' || $adminRole === 'admin'): ?>
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/account_approval_icon_active.png" alt="">
                <a href="account_approval.php" class="<?= ($current_page == 'account_approval.php' || $current_page == 'account_requests.php')? 'active' : '' ?>">Account Approval</a>
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
    <div class="approval-container">
        <div class="title-con">
            <a href="#" class="back-button" onclick="history.back(); return false;">← Back</a>
            <h2>Requests</h2>
        </div>
        
        <div class="table-con">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone Number</th>
                        <th>Registration Type</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($pendingUsers)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">No pending requests found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pendingUsers as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone_number']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($user['registration_type'])); ?></td>
                            <td>
                                <a href="#" 
                                    class="view-btn" 
                                    data-id="<?php echo $user['id']; ?>"
                                    data-firstname="<?php echo htmlspecialchars($user['first_name']); ?>"
                                    data-lastname="<?php echo htmlspecialchars($user['last_name']); ?>"
                                    data-middlename="<?php echo htmlspecialchars($user['middle_name']); ?>"
                                    data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                    data-phone="<?php echo htmlspecialchars($user['phone_number']); ?>"
                                    data-address="<?php echo htmlspecialchars($user['address']); ?>"
                                    data-gender="<?php echo htmlspecialchars($user['gender']); ?>"
                                    data-birthday="<?php echo htmlspecialchars($user['birthday']); ?>"
                                    data-idfront="<?php echo htmlspecialchars($user['valid_id_front']); ?>"
                                    data-registrationtype="<?php echo htmlspecialchars($user['registration_type']); ?>"
                                    data-guardianname="<?php echo htmlspecialchars($user['guardian_name'] ?: ''); ?>"
                                    data-guardianrelationship="<?php echo htmlspecialchars($user['guardian_relationship'] ?: ''); ?>"
                                    data-guardianphone="<?php echo htmlspecialchars($user['guardian_phone'] ?: ''); ?>"
                                    data-guardianemail="<?php echo htmlspecialchars($user['guardian_email'] ?: ''); ?>"
                                    data-guardianid="<?php echo htmlspecialchars($user['guardian_id_path'] ?: ''); ?>"
                                    >View</a>
                                <a href="account_requests.php?action=approve&id=<?php echo $user['id']; ?>" class="approve-btn" onclick="return confirm('Are you sure you want to approve this account? An email notification will be sent to the user.')">Approve</a>
                                <a href="account_requests.php?action=reject&id=<?php echo $user['id']; ?>" class="reject-btn" onclick="return confirm('Are you sure you want to reject this account? An email notification will be sent to the user.')">Reject</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal Structure -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <h2 class="title">Account Details</h2>

            <div class="user-info">
                <h4>Personal Information</h4>
                <div class="info-group">
                    <label>Registration Type</label>
                    <span id="registrationType"></span>
                </div>
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

                <div id="guardianInfo" style="display: none;">
                    <h4>Guardian Information</h4>
                    <div class="info-group">
                        <label>Guardian Name</label>
                        <span id="guardianName"></span>
                    </div>
                    <div class="info-group">
                        <label>Relationship</label>
                        <span id="guardianRelationship"></span>
                    </div>
                    <div class="info-group">
                        <label>Guardian Phone</label>
                        <span id="guardianPhone"></span>
                    </div>
                    <div class="info-group">
                        <label>Guardian Email</label>
                        <span id="guardianEmail"></span>
                    </div>
                </div>
            </div>

            <div class="id-preview">
                <h4>Uploaded Documents</h4>
                <div id="userIdPreview">
                    <label id="idLabel">Valid ID</label>
                    <img id="idFront" src="" alt="Valid ID Front">
                </div>
                <div id="guardianIdPreview" style="display: none;">
                    <label>Guardian's Valid ID</label>
                    <img id="guardianId" src="" alt="Guardian Valid ID">
                </div>
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

                    // Populate user information
                    document.getElementById("registrationType").textContent = this.dataset.registrationtype.charAt(0).toUpperCase() + this.dataset.registrationtype.slice(1);
                    document.getElementById("lastName").textContent = this.dataset.lastname;
                    document.getElementById("firstName").textContent = this.dataset.firstname;
                    document.getElementById("middleName").textContent = this.dataset.middlename;
                    document.getElementById("gender").textContent = this.dataset.gender;
                    document.getElementById("birthday").textContent = this.dataset.birthday;
                    document.getElementById("address").textContent = this.dataset.address;
                    document.getElementById("email").textContent = this.dataset.email;
                    document.getElementById("phone").textContent = this.dataset.phone;

                    // Handle guardian information
                    const guardianInfo = document.getElementById("guardianInfo");
                    const guardianIdPreview = document.getElementById("guardianIdPreview");
                    const idLabel = document.getElementById("idLabel");

                    if (this.dataset.registrationtype === 'child' || this.dataset.registrationtype === 'senior') {
                        guardianInfo.style.display = 'block';
                        document.getElementById("guardianName").textContent = this.dataset.guardianname || 'N/A';
                        document.getElementById("guardianRelationship").textContent = this.dataset.guardianrelationship || 'N/A';
                        document.getElementById("guardianPhone").textContent = this.dataset.guardianphone || 'N/A';
                        document.getElementById("guardianEmail").textContent = this.dataset.guardianemail || 'N/A';
                        
                        if (this.dataset.guardianid) {
                            guardianIdPreview.style.display = 'block';
                            document.getElementById("guardianId").src = this.dataset.guardianid;
                        } else {
                            guardianIdPreview.style.display = 'none';
                        }

                        idLabel.textContent = this.dataset.registrationtype === 'child' ? "Child's ID/Birth Certificate" : "Senior's Valid ID";
                    } else {
                        guardianInfo.style.display = 'none';
                        guardianIdPreview.style.display = 'none';
                        idLabel.textContent = "Valid ID";
                    }

                    // Set user ID image
                    document.getElementById("idFront").src = this.dataset.idfront;

                    // Show modal
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