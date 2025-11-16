<?php
// profile.php
session_start();
require_once "config.php";
include 'settings.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

require_once "deletion_notice.php";


$primary_user_id = $_SESSION['user_id'];
$active_user_id = isset($_SESSION['active_user_id']) ? $_SESSION['active_user_id'] : $primary_user_id;
$is_dependent = ($active_user_id != $primary_user_id);
$requests = []; // default to empty
$patient = null; // linked patient record (if any)
$consultations = []; // patient's consultation history
$dependents = []; // dependents list
$pending_dependents = [];

// Fetch user details for the active user (primary or dependent)
$sql = "SELECT first_name, last_name, middle_name, gender, birthday, address, phone_number, email, profile_picture, family_number, date_registered, primary_user_id 
        FROM users WHERE id = :active_user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':active_user_id', $active_user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Redirect if user not found
if (!$user) {
    header("Location: login.php");
    exit();
}

$profilePic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'images/uploads/profile_pictures/profile-placeholder.png'; 

// Fetch dependents (only for primary user)
if (!$is_dependent) {
    $sql = "SELECT u.id, u.first_name, u.last_name, u.middle_name, u.birthday, dr.relationship 
            FROM users u 
            JOIN dependent_relationships dr ON u.id = dr.dependent_user_id 
            WHERE dr.primary_user_id = :primary_user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':primary_user_id', $primary_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $dependents = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (!$is_dependent) {
    $sql = "SELECT pu.id, pu.first_name, pu.last_name, pu.middle_name, pu.birthday, pdr.relationship 
            FROM pending_users pu 
            JOIN pending_dependent_relationships pdr ON pu.id = pdr.dependent_user_id 
            WHERE pdr.primary_user_id = :primary_user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':primary_user_id', $primary_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $pending_dependents = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch medicine requests for the active user
$sql = "SELECT id, request_id, request_date, request_status FROM medicine_requests WHERE user_id = :active_user_id ORDER BY request_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':active_user_id', $active_user_id, PDO::PARAM_INT);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Try to link the active user to a patient record (if existing)
try {
    $patientLookup = $conn->prepare("SELECT * FROM patients WHERE first_name = :fn AND last_name = :ln AND birthdate = :bd AND status = 'active' ORDER BY id DESC LIMIT 1");
    $patientLookup->execute([
        ':fn' => $user['first_name'],
        ':ln' => $user['last_name'],
        ':bd' => $user['birthday']
    ]);
    $patient = $patientLookup->fetch(PDO::FETCH_ASSOC);

    if ($patient) {
        // Load consultations for this patient
        $consultStmt = $conn->prepare("SELECT id, consultation_type, consultation_date, created_at FROM consultations WHERE patient_id = :pid ORDER BY consultation_date DESC");
        $consultStmt->execute([':pid' => $patient['id']]);
        $consultations = $consultStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Fail silently; the Patient Record tab will show a friendly message
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="stylesheet" href="css/profile.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" href="<?= $logo_url ?>" type="image/x-icon">
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

        <!-- Hamburger Icon for Small Screens -->
        <div class="menu-toggle" id="menu-toggle">
            <i class="fa fa-bars"></i>
        </div>

        <div class="nav-links">
            <ul>
                <li><a href="index.php" class="links">HOME</a></li>
                <li><a href="calendar.php" class="links">CALENDAR</a></li>
                <li><a href="request_medicine.php" class="links">MEDICINE REQUEST</a></li>
                <li><a href="about_us.php" class="links">ABOUT US</a></li>

                <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'user'): ?>
                    <li class="profile-nav">
                        <a href="profile.php" class="profile">
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile Picture" class="nav-profile-pic">
                            <span class="nav-profile-name"><?= htmlspecialchars($_SESSION['name'] ?? '') ?></span>
                        </a>                    
                    </li>
                <?php elseif (!isset($_SESSION['admin_id'])): ?>
                    <li><a href="login.php" class="login-button">LOG IN</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="profile-container">
        <div class="profile-box">
            <div class="profile-img">
                <div class="name-img-container">
                    <div class="profile-pic-wrapper" onclick="openProfilePicModal()">
                        <img src="<?= htmlspecialchars($profilePic) ?>" alt="profile">
                        <button class="icon-button" onclick="openProfilePicModal(event)">
                            <i class="fas fa-pen"></i>
                        </button>
                    </div>
                    <p class="full-name"><?php echo htmlspecialchars($user['first_name'] . " " . $user['middle_name'] . " " . $user['last_name']); ?></p>
                </div>
                <div class="buttons-profile">
                    <button class="edit-profile-btn" onclick="openEditProfileModal()">Edit Profile</button>
                    <?php if ($is_dependent): ?>
                        <a href="switch_account.php" class="switch-acc-btn">Switch Account</a>
                    <?php endif; ?>
                    <?php if (!$is_dependent): ?>
                        <button class="change-password-btn" onclick="openChangePasswordModal()">Change Password</button>
                    <?php endif; ?>
                    <button class="logout-button" onclick="openLogoutConfirmModal()">Log Out</button>
                </div>
            </div>

            <div class="profile-info">
                <!-- Tab buttons -->
                <div class="tab-buttons">
                    <button class="tab-button active" onclick="openTab(event, 'details')">Details</button>
                    <button class="tab-button" onclick="openTab(event, 'request-history')">Request History</button>
                    <button class="tab-button" onclick="openTab(event, 'patient-record')">Patient Record</button>
                    <?php if (!$is_dependent): ?>
                        <button class="tab-button" onclick="openTab(event, 'dependents')">Dependents</button>
                    <?php endif; ?>
                </div>
                
                <div class="content-wrapper">
                    <!-- Details Tab -->
                    <div id="details" class="tab-content" style="display: flex;">
                        <div class="content-con">
                            <h3 class="title">
                                General Information
                                <?php
                                $hasPending = $conn->prepare("
                                    SELECT 1 FROM account_deletion_requests
                                    WHERE user_id = :uid AND status='pending' LIMIT 1
                                ");
                                $hasPending->execute([':uid'=>$active_user_id]);
                                $pending = $hasPending->fetchColumn();
                                ?>
                                <?php if (!$is_dependent && !$pending): ?>
                                    <button class="delete-account-btn" onclick="openPrimaryDeletionModal()">
                                        Request Account Deletion
                                    </button>
                                <?php elseif (!$is_dependent && $pending): ?>
                                    <span style="float:right; color:#856404; font-weight:600;">
                                        Deletion request pending
                                    </span>
                                <?php endif; ?>
                            </h3>
                            <div class="group-row">
                                <div class="row">
                                    <p class="label">Last Name</p>
                                    <p class="value"><?php echo htmlspecialchars($user['last_name']); ?></p>
                                </div>
                                <div class="row">
                                    <p class="label">First Name</p>
                                    <p class="value"><?php echo htmlspecialchars($user['first_name']); ?></p>
                                </div>
                                <div class="row">
                                    <p class="label">Middle Name</p>
                                    <p class="value"><?php echo htmlspecialchars($user['middle_name']); ?></p>
                                </div>
                                <div class="row">
                                    <p class="label">Gender</p>
                                    <p class="value"><?php echo htmlspecialchars($user['gender']); ?></p>
                                </div>
                                <div class="row">
                                    <p class="label">Date of Birth</p>
                                    <p class="value"> <?php 
                                        $birthdate = date("F j, Y", strtotime($user['birthday'])); 
                                        echo htmlspecialchars($birthdate);
                                    ?></p>
                                </div>
                                <div class="row">
                                    <p class="label">Address</p>
                                    <p class="value"><?php echo htmlspecialchars($user['address']); ?></p>
                                </div>
                                <?php if ($is_dependent): ?>
                                <div class="row">
                                    <p class="label">Relationship</p>
                                    <p class="value"><?php 
                                        $stmt = $conn->prepare("SELECT relationship FROM dependent_relationships WHERE dependent_user_id = :active_user_id AND primary_user_id = :primary_user_id");
                                        $stmt->execute([':active_user_id' => $active_user_id, ':primary_user_id' => $primary_user_id]);
                                        $relationship = $stmt->fetchColumn();
                                        echo htmlspecialchars($relationship ?: 'N/A');
                                    ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="content-con">
                            <h3 class="title">Contact Information</h3>
                            <div class="group-row">
                                <div class="row">
                                    <p class="label">Phone Number</p>
                                    <p class="value"><?php echo htmlspecialchars($user['phone_number']); ?></p>
                                </div>
                                <div class="row">
                                    <p class="label">Email Address</p>
                                    <p class="value"><?php echo htmlspecialchars($user['email']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Request History Tab -->
                    <div id="request-history" class="tab-content" style="display: none;">
                        <div class="table-container">
                            <div class="table-wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Date & Time Requested</th>
                                            <th >Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($requests)): ?>
                                            <tr>
                                                <td colspan="4" style="text-align: center;">No requests found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php
                                                $statusColors = [
                                                    'claimed' => 'style="background-color: #28a745; color: white;"',
                                                    'pending' => 'style="background-color: #ffc107; color: white;"',
                                                    'declined' => 'style="background-color: #dc3545; color: white;"',
                                                    'to be claimed' => 'style="background-color: #17a2b8; color: white;"',
                                                    'unclaimed' => 'style="background-color: #6f42c1; color: white;"',
                                                    'cancelled' => 'style="background-color: #6c757d; color: white;"'
                                                ];
                                            ?>
                                            <?php foreach ($requests as $request): ?>
                                                <?php 
                                                    $status = strtolower($request['request_status']);
                                                    $formattedDate = date("n/j/Y g:iA", strtotime($request['request_date']));
                                                    $colorStyle = $statusColors[$status] ?? 'style="background-color: #ccc; color: black;"';
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($request['request_id']) ?></td>
                                                    <td><?= htmlspecialchars($formattedDate) ?></td>
                                                    <td><span class="status-badge" <?= $colorStyle ?>><?= ucfirst($status) ?></span></td>
                                                    <td>
                                                        <div class="button-container">
                                                            <button class="view-btn" data-id="<?= $request['id'] ?>" onclick="viewRequest(this)">View</button>
                                                            <?php if (in_array($status, ['pending', 'to be claimed'])): ?>
                                                            <button class="cancel-request-btn" data-id="<?= $request['id'] ?>" onclick="openCancelRequestModal(this)">Cancel</button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                        </div>
                    </div>

                    <!-- Patient Record Tab -->
                    <div id="patient-record" class="tab-content" style="display: none;">
                        <div class="patient-con">
                            <div class="anthro-con">
                                <h3 class="title">Record Information</h3>
                                <?php if ($patient): ?>
                                <div class="metrics-grid">
                                    <div class="metric-card">
                                        <div class="metric-label">Date Registered: </div>
                                        <div class="metric-value"><?php echo !empty($user['date_registered']) ? htmlspecialchars(date("F j, Y", strtotime($user['date_registered']))) : 'N/A'; ?></div>
                                    </div>
                                    <div class="metric-card">
                                        <div class="metric-label">Family Number: </div>
                                        <div class="metric-value"><?= htmlspecialchars($user['family_number'] ?? 'Not Provided') ?></div>
                                    </div>
                                    <div class="metric-card">
                                        <div class="metric-label">Height:</div>
                                        <div class="metric-value"><?= htmlspecialchars($patient['height'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="metric-card">
                                        <div class="metric-label">Weight:</div>
                                        <div class="metric-value"><?= htmlspecialchars($patient['weight'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="metric-card">
                                        <div class="metric-label">BMI:</div>
                                        <div class="metric-value"><?= htmlspecialchars($patient['bmi'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="metric-card">
                                        <div class="metric-label">Status:</div>
                                        <div class="metric-value"><?= htmlspecialchars($patient['bmi_status'] ?? 'N/A') ?></div>
                                    </div>
                                </div>
                                <?php else: ?>
                                    <p>No patient record found yet.</p>
                                <?php endif; ?>
                            </div>

                            <div class="consultation-con">
                                <h3 class="title">Consultation History</h3>
                                <div class="table-container">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Type</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($consultations)): ?>
                                                <tr>
                                                    <td colspan="4" style="text-align: center;">No Consultations yet</td>
                                                </tr>
                                            <?php else: ?>
                                            <?php foreach ($consultations as $c): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars(date('n/j/Y g:ia', strtotime($c['consultation_date']))) ?></td>
                                                    <td><?= htmlspecialchars($c['consultation_type']) ?></td>
                                                    <td><button class="view-btn" data-cid="<?= $c['id'] ?>" onclick="viewConsultation(this)">View</button></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dependents Tab -->
                    <?php if (!$is_dependent): ?>
                    <div id="dependents" class="tab-content" style="display: none;">
                        <div class="dependents-con">
                            <div class="dependents-header">
                                <div>
                                    <p class="dependents-desc">Add dependent accounts for family members who are incapable of managing their own, such as children, seniors, or persons with disabilities (PWD). These dependent accounts will use your registered contact number, email and password for login, notifications, and other communications.</p>
                                </div>
                                <button class="add-dependent-btn" onclick="openAddDependentModal()">Add Dependent</button>
                            </div>
                            <div class="table-container">
                                <div class="table-wrapper">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Relationship</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($dependents) && empty($pending_dependents)): ?>
                                                <tr>
                                                    <td colspan="5" style="text-align: center;">No dependents found</td>
                                                </tr>
                                            <?php else: ?>
                                                <!-- Approved Dependents -->
                                                <?php foreach ($dependents as $dependent): ?>
                                                    <?php
                                                        // ---- NEW: check if this dependent has a pending deletion request ----
                                                        $hasPendingDel = $conn->prepare("
                                                            SELECT 1 FROM account_deletion_requests
                                                            WHERE user_id = :uid AND status = 'pending' LIMIT 1
                                                        ");
                                                        $hasPendingDel->execute([':uid' => $dependent['id']]);
                                                        $pendingDel = $hasPendingDel->fetchColumn();   // 1 or false
                                                    ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($dependent['first_name'] . ' ' . $dependent['middle_name'] . ' ' . $dependent['last_name']) ?></td>
                                                        <td><?= htmlspecialchars($dependent['relationship']) ?></td>
                                                        <?php
                                                        // Check for pending deletion request
                                                        $delStatusStmt = $conn->prepare("
                                                            SELECT status FROM account_deletion_requests 
                                                            WHERE user_id = :uid AND status = 'pending' 
                                                            LIMIT 1
                                                        ");
                                                        $delStatusStmt->execute([':uid' => $dependent['id']]);
                                                        $delStatus = $delStatusStmt->fetchColumn(); // 'pending' or false

                                                        $statusText = $delStatus ? 'Deletion Pending' : 'Approved';
                                                        $statusColor = $delStatus ? '#fd7e14' : '#28a745'; // yellow if pending, green if approved
                                                        ?>
                                                        <td>
                                                            <span class="status-badge" style="background-color: <?= $statusColor ?>; color: white;">
                                                                <?= htmlspecialchars($statusText) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="button-container">
                                                                <?php if (!$delStatus): ?>
                                                                    <button class="switch-account-btn"
                                                                            onclick="openSwitchAccountConfirmModal(<?= $dependent['id'] ?>)">
                                                                        Switch
                                                                    </button>
                                                                    <!-- Normal:  DELETE -->
                                                                    <button class="delete-dependent-btn"
                                                                            onclick="openDependentDeletionModal(<?= $dependent['id'] ?>)"
                                                                            title="Request deletion of this dependent only">
                                                                        Delete
                                                                    </button>
                                                                <?php else: ?>
                                                                    <!-- Pending deletion: Only CANCEL -->
                                                                    <button class="cancel-dependent-deletion-btn"
                                                                            data-dep-id="<?= $dependent['id'] ?>"
                                                                            onclick="openCancelDepDelModal(this)">
                                                                        Cancel
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <!-- Pending Dependents -->
                                                <?php foreach ($pending_dependents as $pending_dependent): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($pending_dependent['first_name'] . ' ' . $pending_dependent['middle_name'] . ' ' . $pending_dependent['last_name']) ?></td>
                                                        <td><?= htmlspecialchars($pending_dependent['relationship']) ?></td>
                                                        <td><span class="status-badge" style="background-color: #ffc107; color: white;">Pending Approval</span></td>
                                                        <td>
                                                            <div class="button-container">
                                                                <button class="cancel-dependent-btn" data-id="<?= $pending_dependent['id'] ?>" onclick="openCancelDependentModal(this)">Cancel</button>
                                                            </div>
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Dependent-Deletion Confirmation Modal -->
    <div id="cancelDepDelModal" class="modal confirmation">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-undo-alt"></i></div>
            <h2>Cancel Deletion</h2>
            <p>Are you sure you want to cancel the deletion request for this dependent?</p>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeCancelDepDelModal()">Cancel</button>
                <button class="btn-confirm" id="confirmCancelDepDelBtn">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Cancel Dependent Deletion SUCCESS Modal -->
    <div id="cancelDepDelSuccessModal" class="modal success">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-check-circle"></i></div>
            <h2>Cancellation Successful</h2>
            <p>The deletion request for this dependent has been cancelled.</p>
            <div class="modal-footer">
                <button class="btn-success" id="cancelDepDelSuccessOkBtn">OK</button>
            </div>
        </div>
    </div>

    <!-- Primary Account Deletion Modal -->
    <div id="primaryDeletionModal" class="modal form">
        <div class="modal-content">
            <div class="modal-title-bar">
                <h2>Request Account Deletion</h2>
            </div>
            <div class="form-scroll">
                <p>This will delete <u>your primary account and ALL dependent accounts</u> permanently once approved.</p>
                <form id="primaryDeletionForm">
                    <input type="hidden" name="user_id" value="<?= $primary_user_id ?>">
                    <div class="group-col">
                        <label>Reason for deletion <span class="required">*</span></label>
                        <textarea name="reason" rows="4" required placeholder="Why do you want to delete your account?"></textarea>
                    </div>
                    <div class="group-col">
                        <label>Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="primary_password" required>
                            <i class="toggle-password fas fa-eye-slash" onclick="togglePass(this)"></i>
                        </div>
                        <span class="field-error" id="primaryPasswordError" style="color:#d32f2f; font-size:12px; display:none; margin-top:4px;"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closePrimaryDeletionModal()">Cancel</button>
                <button type="submit" form="primaryDeletionForm" class="confirm-btn">Submit Request</button>
            </div>
        </div>
    </div>

    <!-- Primary Deletion SUCCESS Modal -->
    <div id="primaryDeletionSuccessModal" class="modal success">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-check-circle"></i></div>
            <h2>Request Submitted</h2>
            <p>Your account-deletion request has been sent.<br>
            <strong>You will be logged out in <span id="countdown">5</span> seconds...</strong>
            </p>
            <div class="modal-footer">
                <button class="btn-success" id="primarySuccessOkBtn">OK</button>
            </div>
        </div>
    </div>

    <!-- Dependent Deletion Modal -->
    <div id="dependentDeletionModal" class="modal form">
        <div class="modal-content">
            <div class="modal-title-bar">
                <h2>Request Dependent Deletion</h2>
            </div>
            <div class="form-scroll">
                <p>This will delete the dependent accounts permanently once approved.</p>
                <form id="dependentDeletionForm">
                    <input type="hidden" name="dependent_id" id="depDelId">
                    <input type="hidden" name="primary_id" value="<?= $primary_user_id ?>">
                    <div class="group-col">
                        <label>Reason for deletion <span class="required">*</span></label>
                        <textarea name="reason" rows="4" required></textarea>
                    </div>
                    <div class="group-col">
                        <label>Your Password (Primary) <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="dep_password" required>
                            <i class="toggle-password fas fa-eye-slash" onclick="togglePass(this)"></i>
                        </div>
                        <span class="field-error" id="depPasswordError" style="color:#d32f2f; font-size:12px; display:none;"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeDependentDeletionModal()">Cancel</button>
                <button type="submit" form="dependentDeletionForm" class="confirm-btn">Submit Request</button>
            </div>
        </div>
    </div>

    <!-- Dependent Deletion SUCCESS Modal -->
    <div id="dependentDeletionSuccessModal" class="modal success">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-check-circle"></i></div>
            <h2>Request Submitted</h2>
            <p>The deletion request for this dependent has been sent.</p>
            <div class="modal-footer">
                <button class="btn-success" id="dependentSuccessOkBtn">OK</button>
            </div>
        </div>
    </div>

    <!-- Profile Picture Modal -->
    <div id="changeProfileModal" class="modal form">
        <div class="modal-content">
            <div class="modal-title-bar">
                <h2>Change Profile Picture</h2>
            </div>
            <div class="form-scroll">
                <form id="profilePicForm" action="upload_profilePic.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="active_user_id" value="<?= htmlspecialchars($active_user_id) ?>">
                    <div class="image-preview">
                        <img id="preview-img" src="<?= htmlspecialchars($profilePic) ?>" alt="Preview">
                    </div>
                    <div class="file-upload">
                        <label for="profile-photo">Choose File</label>
                        <input type="file" name="profile_photo" id="profile-photo" accept="image/*" required style="display: none;">
                        <span class="file-name" id="profile_file_name">No file chosen</span>
                    </div>
                    <small class="field-hint">Max file size: 5MB. Accepted formats: JPEG, PNG, GIF</small>
                    <div class="error-message" id="profilePicError"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeProfilePicModal()">Cancel</button>
                <button type="submit" form="profilePicForm" class="save-btn">Save</button>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal form">
        <div class="modal-content">
            <div class="modal-title-bar">
                <h2>Edit Profile</h2>
            </div>
            <div class="error-message" id="editProfileError"></div>
            <div class="form-scroll">
                <form id="editProfileForm" action="update_profile.php" method="POST">
                    <input type="hidden" name="active_user_id" value="<?= htmlspecialchars($active_user_id) ?>">
                    <div class="group-row">
                        <div class="group-col">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                        </div>
                        <div class="group-col">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                        </div>
                    </div>
                    <div class="group-row">
                        <div class="group-col">
                            <label for="middle_name">Middle Name <span class="required">*</span></label>
                            <input type="text" id="middle_name" name="middle_name" value="<?= htmlspecialchars($user['middle_name']) ?>" required>
                        </div>
                        <div class="group-col">
                            <label for="gender">Gender <span class="required">*</span></label>
                            <select id="gender" name="gender" required>
                                <option value="Male" <?= $user['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $user['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="group-col">
                        <label for="birthday">Date of Birth <span class="required">*</span></label>
                        <input type="date" id="birthday" name="birthday" value="<?= htmlspecialchars($user['birthday']) ?>" required>
                    </div>
                    <div class="group-col">
                        <label for="address">Address <span class="required">*</span></label>
                        <input type="text" id="address" name="address" value="<?= htmlspecialchars($user['address']) ?>" required>
                    </div>
                    <?php if (!$is_dependent): ?>
                    <div class="group-row">
                        <div class="group-col">
                            <label for="phone_number">Phone Number <span class="required">*</span></label>
                            <input type="text" id="phone_number" name="phone_number" value="<?= htmlspecialchars($user['phone_number']) ?>" required>
                        </div>
                        <div class="group-col">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" id="editCancelBtn" onclick="closeEditProfileModal()">Cancel</button>
                <button type="submit" form="editProfileForm" class="save-btn" id="editSaveBtn">
                    <span class="btn-text">Save</span>
                    <i class="fas fa-spinner fa-spin" style="display:none;"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Change Password Modal (Only for Primary User) -->
    <?php if (!$is_dependent): ?>
    <div id="changePasswordModal" class="modal form">
        <div class="modal-content">
            <div class="modal-title-bar">
                <h2>Change Password</h2>
            </div>
            <div class="form-scroll">
                <form id="changePasswordForm" action="change_password.php" method="POST" novalidate>
                    <!-- Current Password -->
                    <div class="group-col">
                        <label for="current_password">Current Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" id="current_password" name="current_password" required>
                            <i class="toggle-password fas fa-eye-slash" onclick="togglePass(this)"></i>
                        </div>
                        <small class="field-hint">Enter your existing password</small>
                    </div>

                    <!-- New Password -->
                    <div class="group-col">
                        <label for="new_password">New Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" id="new_password" name="new_password" required>
                            <i class="toggle-password fas fa-eye-slash" onclick="togglePass(this)"></i>
                        </div>
                        <small class="field-hint">At least 8 characters with uppercase, lowercase, and numbers</small>
                    </div>

                    <!-- Confirm New Password -->
                    <div class="group-col">
                        <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <i class="toggle-password fas fa-eye-slash" onclick="togglePass(this)"></i>
                        </div>
                        <small class="field-hint">Must match the new password</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeChangePasswordModal()">Cancel</button>
                <button type="submit" form="changePasswordForm" class="save-btn" id="changePasswordSubmitBtn" disabled>
                    <span class="btn-text">Save</span>
                    <i class="fas fa-spinner fa-spin" style="display:none;"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Dependent Modal (Only for Primary User) -->
    <?php if (!$is_dependent): ?>
    <div id="addDependentModal" class="modal form">
        <div class="modal-content">
            <div class="modal-title-bar">
                <h2>Add Dependent</h2>
            </div>
            <div class="error-message" id="addDependentError"></div>
            <div class="form-scroll">
                <form id="addDependentForm" action="add_dependent.php" method="POST" enctype="multipart/form-data">
                    <div class="group-row">
                        <div class="group-col">
                            <label for="dep_first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="dep_first_name" name="first_name" required>
                        </div>
                        <div class="group-col">
                            <label for="dep_last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="dep_last_name" name="last_name" required>
                        </div>
                    </div>
                    <div class="group-row">
                        <div class="group-col">
                            <label for="dep_middle_name">Middle Name</label>
                            <input type="text" id="dep_middle_name" name="middle_name">
                        </div>
                        <div class="group-col">
                            <label for="dep_gender">Gender <span class="required">*</span></label>
                            <select id="dep_gender" name="gender" required>
                                <option value="" disabled selected>Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="group-col">
                        <label for="dep_birthday">Date of Birth <span class="required">*</span></label>
                        <input type="date" id="dep_birthday" name="birthday" required>
                    </div>
                    <div class="group-col">
                        <label for="dep_address">Address <span class="required">*</span></label>
                        <input type="text" id="dep_address" name="address" required value="<?= htmlspecialchars($user['address']) ?>">
                    </div>
                    <div class="group-col">
                        <label for="dep_relationship">Relationship to You <span class="required">*</span></label>
                        <select id="dep_relationship" name="relationship" required>
                            <option value="" disabled selected>Select Relationship</option>
                            <option value="Child">Child</option>
                            <option value="Parent">Parent</option>
                            <option value="Grandparent">Grandparent</option>
                            <option value="Sibling">Sibling</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="group-col">
                        <label class="checkbox-label">
                            <input type="checkbox" id="dep_hasFamilyNumber" name="hasFamilyNumber">
                            I know their family number
                        </label>
                    </div>
                    <div class="group-col" id="dep_familyNumberContainer" style="display: none;">
                        <label for="dep_familyNumber">Family Number</label>
                        <input type="text" id="dep_familyNumber" name="familyNumber">
                        <small class="field-hint">Enter their family number (letters, numbers, and hyphens only)</small>
                    </div>
                    <div class="group-col">
                        <label>Upload Valid ID <span class="required">*</span></label>
                        <div class="file-upload">
                            <label for="dep_validID_front">Add File</label>
                            <input type="file" id="dep_validID_front" name="validID_front" accept="image/jpeg,image/png" required>
                            <span class="file-name" id="dep_file_name">No file chosen</span>
                        </div>
                        <small class="field-hint">Max file size: 5MB. Accepted formats: JPEG, PNG</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" id="addDepCancelBtn" onclick="closeAddDependentModal()">Cancel</button>
                <button type="submit" form="addDependentForm" class="save-btn" id="addDepSaveBtn">
                    <span class="btn-text">Add Dependent</span>
                    <i class="fas fa-spinner fa-spin" style="display:none; margin-left:8px;"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- View Consultation Modal -->
    <div id="viewConsultationModal" class="modal info">
        <div class="modal-content">
            <div class="modal-title-bar">
                <h2 class="title">Consultation Details</h2>
            </div>
            <div class="form-scroll">
                <div class="consultation-container">
                    <div class="group-row">
                        <div class="group-col">
                            <label>Type of Consultation</label>
                            <input type="text" id="view_consultation_type" readonly>
                        </div>
                    </div>
                    <div class="group-row">
                        <div class="group-col">
                            <label>Date of Consultation</label>
                            <input type="text" id="view_consultation_date" readonly>
                        </div>
                    </div>
                    <div class="group-row">
                        <div class="group-col">
                            <label>Reason for Consultation</label>
                            <input type="text" id="view_reason_for_consultation" readonly>
                        </div>
                    </div>
                    <div class="group-row">
                        <div class="group-col">
                            <label>Blood Pressure</label>
                            <input type="text" id="view_blood_pressure" readonly>
                        </div>
                        <div class="group-col">
                            <label>Temperature</label>
                            <input type="text" id="view_temperature" readonly>
                        </div>
                    </div>
                    <div class="group-row">
                        <div class="group-col">
                            <label>Diagnosis</label>
                            <input type="text" id="view_diagnosis" readonly>
                        </div>
                    </div>
                    <div class="group-row">
                        <div class="group-col">
                            <label>Prescribed Medicine</label>
                            <input type="text" id="view_prescribed_medicine" readonly>
                        </div>
                    </div>
                    <div class="group-row">
                        <div class="group-col">
                            <label>Treatment Given</label>
                            <input type="text" id="view_treatment_given" readonly>
                        </div>
                    </div>
                    <div class="group-row">
                        <div class="group-col">
                            <label>Consulting Physician/Nurse</label>
                            <input type="text" id="view_consulting_physician_nurse" readonly>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="document.getElementById('viewConsultationModal').classList.remove('show')">Close</button>
            </div>
        </div>
    </div>

    <!-- View Request Modal -->
    <div id="viewRequestModal" class="modal info"> 
        <div class="modal-content">
            <div class="modal-title-bar">
                <h2>Request Medicine Details</h2>
            </div>
            <div class="request-header-summary">
                <div class="summary-item">
                    <label>Request ID</label>
                    <span id="requestId" class="highlight-id"></span>
                </div>
                <div class="summary-item">
                    <label>Request Status</label>
                    <span id="requestStatus" class="status-badge-large"></span>
                </div>
            </div>
            <div class="form-scroll">
                <div class="modal-container">
                    
                    <div class="medicine-section" id="medicine-group">
                        <label>Requested Medicines</label>
                    </div>

                    <div class="claim-section" id="claim-info" style="display: none;">
                        <div class="claim-container">
                            <div>
                                <label>Claim Information</label>
                                <div class="claim-details">
                                    <p><strong>Claim Date:</strong> <span id="claimDate"></span></p>
                                    <p><strong>Claim Until:</strong> <span id="claimUntil"></span></p>
                                    <p><strong>Claimed Date:</strong> <span id="claimedDate"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="note-section" id="note-info" style="display: none;">
                        <div class="group-row">
                            <div class="group-col">
                                <label>Note</label>
                                <span id="req_note"></span>
                            </div>
                        </div>
                    </div>

                    <div class="req-details-section">
                        <div class="group-row">
                            <div class="group-col">
                                <label>Patient's Full Name</label>
                                <span id="req_fullName"></span>
                            </div>
                        </div>
                        <div class="group-row">
                            <div class="group-col">
                                <label>Sex</label>
                                <span id="req_sex"></span>
                            </div>
                            <div class="group-col">
                                <label>Birthdate</label>
                                <span id="req_birthdate"></span>
                            </div>
                            <div class="group-col">
                                <label>Contact Number</label>
                                <span id="req_phone"></span>
                            </div>
                        </div>
                        <div class="group-row">
                            <div class="group-col">
                                <label>Address</label>
                                <span id="req_address"></span>
                            </div>
                        </div>
                        <div class="group-row">
                            <div class="group-col">
                                <label>Reason for Request</label>
                                <span id="req_reason"></span>
                            </div>
                        </div>
                        <div class="prescription-preview">
                            <div class="group-col">
                                <label>Prescription</label>
                                <img id="prescriptionImg" src="" alt="Prescription Image">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Cancel Request Confirmation Modal -->
    <div id="cancelRequestModal" class="modal confirmation">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h2>Cancel Request</h2>
            <p>Are you sure you want to cancel this medicine request?</p>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeCancelRequestModal()">Cancel</button>
                <button class="btn-confirm" id="confirmCancelBtn">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Cancel Request SUCCESS Modal -->
    <div id="cancelRequestSuccessModal" class="modal success">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-check-circle"></i></div>
            <h2>Cancellation Successful</h2>
            <p>Your medicine request has been cancelled.</p>
            <div class="modal-footer">
                <button class="btn-success" id="cancelRequestSuccessOkBtn">OK</button>
            </div>
        </div>
    </div>

    <!-- Cancel Dependent Confirmation Modal -->
    <div id="cancelDependentModal" class="modal confirmation">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h2>Cancel Pending Dependent</h2>
            <p>Are you sure you want to cancel this pending dependent account?</p>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeCancelDependentModal()">Cancel</button>
                <button class="btn-confirm" id="confirmCancelDependentBtn">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Cancel Pending Dependent SUCCESS Modal -->
    <div id="cancelPendingDepSuccessModal" class="modal success">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-check-circle"></i></div>
            <h2>Cancellation Successful</h2>
            <p>The pending dependent account has been cancelled.</p>
            <div class="modal-footer">
                <button class="btn-success" id="cancelPendingDepSuccessOkBtn">OK</button>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logoutConfirmModal" class="modal confirmation">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-sign-out-alt"></i></div>
            <h2>Confirm Logout</h2>
            <p>Are you sure you want to log out?</p>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeLogoutConfirmModal()">Cancel</button>
                <button class="btn-confirm" onclick="confirmLogout()">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Switch Account Confirmation Modal -->
    <div id="switchAccountConfirmModal" class="modal confirmation">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-exchange-alt"></i></div>
            <h2>Switch Account</h2>
            <p>Are you sure you want to switch to this account?</p>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeSwitchAccountConfirmModal()">Cancel</button>
                <button class="btn-confirm" id="confirmSwitchAccountBtn">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        function showModal(id) { 
            document.getElementById(id).classList.add('show'); 

        }
        function hideModal(id) { 
            document.getElementById(id).classList.remove('show'); 

        }

        function delayedConfirm(btn, delayMs, ajaxFn) {
            // 1. UI → disabled + spinner
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `
                <span class="btn-text">Processing…</span>
                <i class="fas fa-spinner fa-spin" style="margin-left:6px;"></i>
            `;

            // 2. artificial delay
            setTimeout(() => {
                // 3. run the real AJAX
                ajaxFn().finally(() => {
                    // 4. always restore UI (even on error)
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                });
            }, delayMs);
        }

        function performLogout() {
            fetch("logout.php", { method: "POST" })
                .finally(() => {
                    window.location.href = "index.php";
                });
        }

        function openTab(evt, tabName) {
            const tabContents = document.getElementsByClassName("tab-content");
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].style.display = "none";
            }
            const tabButtons = document.getElementsByClassName("tab-button");
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove("active");
            }
            document.getElementById(tabName).style.display = tabName === 'request-history' || tabName === 'dependents' ? 'block' : 'flex';
            evt.currentTarget.classList.add("active");
        }
    </script>

    <script>
        // Primary Deletion Modal Functions
        function openPrimaryDeletionModal() {
            document.getElementById("primaryDeletionModal").classList.add("show");
        }
        function closePrimaryDeletionModal() {
            document.getElementById("primaryDeletionModal").classList.remove("show");
            document.getElementById("primaryDeletionForm").reset();
        }

        document.getElementById("primaryDeletionForm").addEventListener("submit", function (e) {
            e.preventDefault();

            // Clear any previous error
            const errorEl = document.getElementById("primaryPasswordError");
            errorEl.style.display = "none";
            errorEl.textContent = "";

            const submitBtn = document.querySelector("#primaryDeletionModal .confirm-btn");
            const cancelBtn = document.querySelector("#primaryDeletionModal .cancel-btn");
            const originalText = submitBtn.textContent;

            // Show spinner + disable both buttons
            submitBtn.disabled = true;
            cancelBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

            const fd = new FormData(this);
            fd.append('type', 'primary');

            fetch("request_deletion.php", {
                method: "POST",
                body: fd
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    // SUCCESS → show success modal with countdown
                    closePrimaryDeletionModal();
                    const successModal = document.getElementById("primaryDeletionSuccessModal");
                    const countdownEl = document.getElementById("countdown");
                    successModal.classList.add("show");

                    let seconds = 5;
                    countdownEl.textContent = seconds;

                    const timer = setInterval(() => {
                        seconds--;
                        countdownEl.textContent = seconds;
                        if (seconds <= 0) {
                            clearInterval(timer);
                            performLogout();
                        }
                    }, 1000);

                    document.getElementById("primarySuccessOkBtn").onclick = () => {
                        clearInterval(timer);
                        performLogout();
                    };

                    // Prevent back button from keeping session
                    history.pushState(null, null, location.href);
                    window.onpopstate = () => history.go(1);

                } else {
                    // ERROR → re-enable buttons
                    submitBtn.disabled = false;
                    cancelBtn.disabled = false;
                    submitBtn.innerHTML = originalText;

                    // Show password error under the field
                    if (d.message && d.message.includes("password")) {
                        errorEl.textContent = "Incorrect password. Please try again.";
                        errorEl.style.display = "block";
                    } else {
                        alert(d.message || "Failed to submit request.");
                    }
                }
            })
            .catch(err => {
                console.error(err);
                submitBtn.disabled = false;
                cancelBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                alert("Network error. Please try again.");
            });
        });
    </script>

    <script>
        // Dependent Deletion Modal Functions
        function openDependentDeletionModal(dependentId) {
            document.getElementById("depDelId").value = dependentId;
            document.getElementById("dependentDeletionModal").classList.add("show");
        }
        function closeDependentDeletionModal() {
            document.getElementById("dependentDeletionModal").classList.remove("show");
            document.getElementById("dependentDeletionForm").reset();
        }

        document.getElementById("dependentDeletionForm").addEventListener("submit", function (e) {
            e.preventDefault();

            // Clear previous error
            const errorEl = document.getElementById("depPasswordError");
            errorEl.style.display = "none";
            errorEl.textContent = "";

            const submitBtn = document.querySelector("#dependentDeletionModal .confirm-btn");
            const cancelBtn = document.querySelector("#dependentDeletionModal .cancel-btn");
            const originalText = submitBtn.textContent;

            // Show spinner + disable buttons
            submitBtn.disabled = true;
            cancelBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

            const fd = new FormData(this);
            fd.append('type', 'dependent');

            fetch("request_deletion.php", {
                method: "POST",
                body: fd
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    closeDependentDeletionModal();
                    const successModal = document.getElementById("dependentDeletionSuccessModal");
                    successModal.classList.add("show");
                    document.getElementById("dependentSuccessOkBtn").onclick = () => {
                        successModal.classList.remove("show");
                        location.reload();
                    };
                } else {
                    // Re-enable buttons
                    submitBtn.disabled = false;
                    cancelBtn.disabled = false;
                    submitBtn.innerHTML = originalText;

                    // Show specific password error under field
                    if (d.message && d.message.includes("password")) {
                        errorEl.textContent = "Incorrect password. Please try again.";
                        errorEl.style.display = "block";
                    } else {
                        alert(d.message || "Failed to submit request.");
                    }
                }
            })
            .catch(err => {
                console.error(err);
                submitBtn.disabled = false;
                cancelBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                alert("Network error. Please try again.");
            });
        });

    </script>


    <script>
        function openCancelDepDelModal(btn) {
            const id = btn.dataset.depId;
            document.getElementById('confirmCancelDepDelBtn').dataset.depId = id;
            showModal('cancelDepDelModal');
        }
        
        function closeCancelDepDelModal() {
            hideModal('cancelDepDelModal');
        }

        document.getElementById('confirmCancelDepDelBtn')?.addEventListener('click', function () {
            const depId = this.dataset.depId;
            if (!depId) return;

            delayedConfirm(this, 2000, () => {
                return fetch('cancel_deletion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'dependent_user_id=' + depId
                })
                .then(r => r.json())
                .then(d => {
                    closeCancelDepDelModal();
                    if (d.success) {
                        const successModal = document.getElementById('cancelDepDelSuccessModal');
                        successModal.classList.add('show');
                        document.getElementById('cancelDepDelSuccessOkBtn').onclick = () => {
                            successModal.classList.remove('show');
                            location.reload();
                        };
                    } else {
                        alert(d.message || 'Failed to cancel deletion request');
                    }
                })
                .catch(() => {
                    closeCancelDepDelModal();
                    alert('Network error – please try again.');
                });
            });
        });
    </script>

    <script>
        function openProfilePicModal(e) {
            if (e) e.stopPropagation();
            const modal = document.getElementById("changeProfileModal");
            modal.classList.add("show");
            document.getElementById("profilePicError").textContent = "";
            document.getElementById("profilePicForm").reset();
            document.getElementById("profile_file_name").textContent = "No file chosen";
            document.getElementById("preview-img").src = "<?= htmlspecialchars($profilePic) ?>";
        }

        function closeProfilePicModal() {
            document.getElementById("changeProfileModal").classList.remove("show");
            document.getElementById("profilePicError").textContent = "";
            document.getElementById("profilePicForm").reset();
            document.getElementById("profile_file_name").textContent = "No file chosen";
        }

        document.getElementById("profile-photo").addEventListener("change", function(event) {
            const file = event.target.files[0];
            const preview = document.getElementById("preview-img");
            const fileNameSpan = document.getElementById("profile_file_name");
            if (file && file.type.startsWith("image/")) {
                fileNameSpan.textContent = file.name;
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                fileNameSpan.textContent = "No file chosen";
            }
        });

        document.getElementById("profilePicForm").addEventListener("submit", function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch("upload_profilePic.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const errorDiv = document.getElementById("profilePicError");
                errorDiv.textContent = data.message;
                
                if (data.success) {
                    errorDiv.style.color = "#28a745"; // Green for success
                    setTimeout(() => {
                        location.reload(); // Reload to reflect updated profile picture
                    }, 1700);
                } else {
                    errorDiv.style.color = "#FF0000"; // Red for error
                }
            })
            .catch(error => {
                document.getElementById("profilePicError").textContent = "An error occurred. Please try again.";
                console.error(error);
            });
        });
    </script>


    <script>
        function closeViewModal() {
            document.getElementById("viewRequestModal").classList.remove("show");
        }

        function viewRequest(button) {
            const requestId = button.getAttribute('data-id');
            fetch(`get_requests_profile.php?id=${requestId}`)
                .then(response => response.json())
                .then(data => {
                    // Fill modal fields
                    document.getElementById('requestId').textContent = data.request_id || 'N/A';
                    document.getElementById('requestStatus').textContent = data.request_status ? data.request_status.charAt(0).toUpperCase() + data.request_status.slice(1) : 'N/A';
                    document.getElementById('req_fullName').textContent = data.full_name;
                    document.getElementById('req_sex').textContent = data.sex;
                    document.getElementById('req_birthdate').textContent = data.birthdate;
                    document.getElementById('req_address').textContent = data.address;
                    document.getElementById('req_phone').textContent = data.phone;
                    document.getElementById('req_reason').textContent = data.reason || 'No reason provided';
                    document.getElementById('prescriptionImg').src = data.prescription;

                    // Medicine entries with status
                    const medicineGroup = document.getElementById('medicine-group');
                    medicineGroup.innerHTML = '<label>Requested Medicines</label>';

                    // create the container div for all medicine rows
                    const container = document.createElement('div');
                    container.classList.add('medicine-container');

                    // loop through medicines and append rows into the container
                    data.medicines.forEach(med => {
                        const approvedQty = med.approved_quantity !== null ? med.approved_quantity : '—';
                        const requestedQty = med.requested_quantity;
                        const isDifferent = med.quantity_diff;

                        let quantityDisplay = `<span>${requestedQty}</span>`;
                        if (isDifferent) {
                            quantityDisplay = `
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <span style="text-decoration: line-through; color: #888; font-size: 0.9em;">
                                        Requested: ${requestedQty}
                                    </span>
                                    <span style="color: #d32f2f; font-weight: 600;">
                                        Approved: ${approvedQty}
                                    </span>
                                </div>`;
                        } else if (med.approved_quantity !== null) {
                            quantityDisplay = `<span style="color: #2e7d32;">${approvedQty}</span>`;
                        }

                        const row = document.createElement('div');
                        row.classList.add('row', 'medicine-entry');
                        row.innerHTML = `
                            <div><label>Medicine Name</label><span>${med.medicine_name}</span></div>
                            <div><label>Dosage</label><span>${med.dosage}</span></div>
                            <div><label>Quantity</label>${quantityDisplay}</div>
                            <div><label>Status</label>
                                <span class="status-badge status-${med.status}">
                                    ${med.status.charAt(0).toUpperCase() + med.status.slice(1)}
                                </span>
                            </div>
                        `;
                        container.appendChild(row);
                    });

                    const statusSpan = document.getElementById('requestStatus');
                    const statusText = data.request_status ? data.request_status.toLowerCase() : 'pending';

                    statusSpan.textContent = statusText.charAt(0).toUpperCase() + statusText.slice(1);

                    // Apply correct background color
                    const statusColors = {
                        'claimed': '#28a745',
                        'pending': '#ffc107',
                        'declined': '#dc3545',
                        'to be claimed': '#17a2b8',
                        'unclaimed': '#6f42c1',
                        'cancelled': '#6c757d'
                    };
                    statusSpan.style.backgroundColor = statusColors[statusText] || '#ccc';

                    medicineGroup.appendChild(container);


                    // Claim information
                    const claimInfo = document.getElementById('claim-info');
                    const claimDate = document.getElementById('claimDate');
                    const claimUntil = document.getElementById('claimUntil');
                    const claimedDate = document.getElementById('claimedDate');
                    if (data.request_status === 'to be claimed' || data.request_status === 'claimed') {
                        claimDate.textContent = data.claim_date || 'N/A';
                        claimUntil.textContent = data.claim_until_date || 'N/A';
                        claimedDate.textContent = data.claimed_date || 'N/A';
                        claimInfo.style.display = 'block';
                    } else {
                        claimInfo.style.display = 'none';
                    }

                    // Note
                    const noteInfo = document.getElementById('note-info');
                    const note = document.getElementById('req_note');
                    if ((data.request_status === 'to be claimed' || data.request_status === 'claimed') && data.note && data.note.trim() !== '') {
                        note.textContent = data.note;
                        noteInfo.style.display = 'block';
                    } else {
                        noteInfo.style.display = 'none';
                    }

                    document.getElementById("viewRequestModal").classList.add("show");
                })
                .catch(error => {
                    alert("Failed to load request data.");
                    console.error(error);
                });
        }

        document.querySelector("#viewRequestModal .close")?.addEventListener("click", function () {
            closeViewModal();
        });
    </script>


    <script>
        function openChangePasswordModal() {
            document.getElementById("changePasswordModal").classList.add("show");
        }

        function closeChangePasswordModal() {
            document.getElementById("changePasswordModal").classList.remove("show");
            document.getElementById("changePasswordForm").reset();

            // Reset button state
            const btn = document.getElementById("changePasswordSubmitBtn");
            const btnText = btn.querySelector(".btn-text");
            const spinner = btn.querySelector(".fa-spinner");
            const cancelBtn = document.querySelector("#changePasswordModal .cancel-btn");

            btn.disabled = true; // stays disabled until valid input
            if (btnText) btnText.textContent = "Save";
            if (spinner) spinner.style.display = "none";
            if (cancelBtn) cancelBtn.disabled = false;
        }

        function togglePass(icon) {
            const input = icon.previousElementSibling; // the password input
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            }
        }

        document.addEventListener("DOMContentLoaded", function () {
            const currentPass = document.getElementById('current_password');
            const newPass = document.getElementById('new_password');
            const confirmPass = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('changePasswordSubmitBtn');

            // Validation rules
            const validators = {
                current_password: {
                    element: currentPass,
                    errorMsg: "Current password is required",
                    validator: v => v.trim().length > 0
                },
                new_password: {
                    element: newPass,
                    errorMsg: "Password must be at least 8 characters and include uppercase, lowercase, and numbers",
                    validator: v => {
                        const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
                        return regex.test(v);
                    }
                },
                confirm_password: {
                    element: confirmPass,
                    errorMsg: "Passwords do not match",
                    validator: v => v && v === newPass.value
                }
            };

            // Show error under input (outside .password-wrapper)
            function showFieldError(input, msg) {
                removeFieldError(input);
                const err = document.createElement('span');
                err.className = 'field-error';
                err.textContent = msg;
                err.style.display = 'block';
                err.style.color = '#FF0000';
                err.style.fontSize = '12px';
                input.style.borderColor = '#FF0000';
                input.closest('.group-col').appendChild(err);
            }

            function removeFieldError(input) {
                const group = input.closest('.group-col');
                const old = group.querySelector('.field-error');
                if (old) old.remove();
                input.style.borderColor = '';
            }

            // Validate single field
            function validateField(name) {
                const field = validators[name];
                const value = field.element.value;
                if (field.validator(value)) {
                    removeFieldError(field.element);
                    return true;
                } else {
                    showFieldError(field.element, field.errorMsg);
                    return false;
                }
            }

            // Update submit button
            function updateSubmitButton() {
                const allValid = 
                    validators.current_password.validator(currentPass.value.trim()) &&
                    validators.new_password.validator(newPass.value) &&
                    validators.confirm_password.validator(confirmPass.value);
                submitBtn.disabled = !allValid;
            }

            // Real-time validation
            [currentPass, newPass, confirmPass].forEach(input => {
                input.addEventListener('input', () => {
                    validateField(input.id);
                    updateSubmitButton();
                });
                input.addEventListener('blur', () => {
                    validateField(input.id);
                    updateSubmitButton();
                });
            });

            // Initial state
            updateSubmitButton();
        });

        document.getElementById("changePasswordForm")?.addEventListener("submit", function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById("changePasswordSubmitBtn");
            const btnText = submitBtn.querySelector(".btn-text");
            const spinner = submitBtn.querySelector(".fa-spinner");
            const cancelBtn = document.querySelector("#changePasswordModal .cancel-btn");

            // Disable buttons + show spinner
            submitBtn.disabled = true;
            cancelBtn.disabled = true;
            btnText.textContent = "Saving…";
            spinner.style.display = "inline-block";

            const formData = new FormData(this);

            fetch("change_password.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                } else {
                    // Re-enable UI on error
                    submitBtn.disabled = false;
                    cancelBtn.disabled = false;
                    btnText.textContent = "Save";
                    spinner.style.display = "none";
                }
            })
            .catch(error => {
                console.error(error);
                // Re-enable on network error
                submitBtn.disabled = false;
                cancelBtn.disabled = false;
                btnText.textContent = "Save";
                spinner.style.display = "none";
            });
        });

    </script>


    <script>
        function openAddDependentModal() {
            document.getElementById("addDependentModal").classList.add("show");
            document.getElementById("addDependentError").textContent = "";
            document.getElementById("addDependentForm").reset();
            document.getElementById("dep_file_name").textContent = "No file chosen";
            document.getElementById("dep_familyNumberContainer").style.display = "none";
            // Clear all field errors
            document.querySelectorAll(".field-error").forEach(el => el.remove());
            document.querySelectorAll("input, select").forEach(el => el.style.borderColor = "");
        }

        function closeAddDependentModal() {
            document.getElementById("addDependentModal").classList.remove("show");
            document.getElementById("addDependentForm").reset();
            document.getElementById("addDependentError").textContent = "";
            document.getElementById("dep_file_name").textContent = "No file chosen";
            document.getElementById("dep_familyNumberContainer").style.display = "none";
            document.querySelectorAll(".field-error").forEach(el => el.remove());
            document.querySelectorAll("input, select").forEach(el => el.style.borderColor = "");
        }

        document.addEventListener("DOMContentLoaded", function () {
            const form = document.getElementById("addDependentForm");
            const saveBtn = document.getElementById("addDepSaveBtn");
            const cancelBtn = document.getElementById("addDepCancelBtn");
            const btnText = saveBtn?.querySelector(".btn-text");
            const spinner = saveBtn?.querySelector(".fa-spinner");

            // Elements for validation
            const depFirstName       = document.getElementById("dep_first_name");
            const depLastName        = document.getElementById("dep_last_name");
            const depMiddleName      = document.getElementById("dep_middle_name");
            const depGender          = document.getElementById("dep_gender");
            const depBirthday        = document.getElementById("dep_birthday");
            const depAddress         = document.getElementById("dep_address");
            const depRelationship    = document.getElementById("dep_relationship");
            const depHasFamily       = document.getElementById("dep_hasFamilyNumber");
            const depFamilyContainer = document.getElementById("dep_familyNumberContainer");
            const depFamilyNumber    = document.getElementById("dep_familyNumber");
            const depValidID         = document.getElementById("dep_validID_front");
            const depFileNameSpan    = document.getElementById("dep_file_name");

            // Validation rules
            const depFields = {
                first_name: { element: depFirstName, errorMsg: "First name is required", validator: v => v.trim().length > 0 },
                last_name:  { element: depLastName,  errorMsg: "Last name is required",  validator: v => v.trim().length > 0 },
                middle_name:{ element: depMiddleName, validator: () => true },
                gender:     { element: depGender,     errorMsg: "Please select a gender", validator: v => v === "Male" || v === "Female" },
                birthday:   { 
                    element: depBirthday, 
                    errorMsg: "Date of birth is required", 
                    validator: v => v && new Date(v) <= new Date(),
                    customErrorMsg: () => "Invalid date of birth"
                },
                address:    { element: depAddress,    errorMsg: "Address is required",    validator: v => v.trim().length > 0 },
                relationship:{ element: depRelationship, errorMsg: "Please select relationship", validator: v => v && v !== "" },
                familyNumber:{ 
                    element: depFamilyNumber,
                    validator: v => !depHasFamily.checked || (v.trim() && /^[A-Za-z0-9-]{1,50}$/.test(v.trim())),
                    customErrorMsg: () => depHasFamily.checked ? "Family number is required and must be letters, numbers, or hyphens only" : ""
                },
                validID_front:{ 
                    element: depValidID,
                    errorMsg: "Please upload a valid ID (JPEG/PNG, max 5MB)",
                    validator: () => {
                        if (!depValidID.files?.[0]) return false;
                        const file = depValidID.files[0];
                        const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                        const maxSize = 5 * 1024 * 1024;
                        return validTypes.includes(file.type) && file.size <= maxSize;
                    }
                }
            };

            function showFieldError(element, message) {
                if (!element) return;
                removeFieldError(element);
                const err = document.createElement("span");
                err.className = "field-error";
                err.textContent = message;
                err.style.color = "#FF0000";
                err.style.fontSize = "12px";
                err.style.display = "block";
                err.style.marginTop = "4px";
                element.style.borderColor = "#FF0000";
                element.closest(".group-col")?.appendChild(err);
            }

            function removeFieldError(element) {
                if (!element) return;
                const group = element.closest(".group-col");
                const old = group?.querySelector(".field-error");
                if (old) old.remove();
                element.style.borderColor = "";
            }

            function validateDepField(name) {
                const field = depFields[name];
                if (!field.element) return true;
                const value = field.element.value;
                const valid = field.validator(value);
                if (!valid) {
                    const msg = field.customErrorMsg ? field.customErrorMsg(value) : field.errorMsg;
                    showFieldError(field.element, msg);
                } else {
                    removeFieldError(field.element);
                }
                return valid;
            }

            function validateDependentModal() {
                return Object.keys(depFields).every(key => validateDepField(key));
            }

            // Real-time validation
            Object.keys(depFields).forEach(key => {
                const el = depFields[key].element;
                if (el) {
                    el.addEventListener("input", () => validateDepField(key));
                    el.addEventListener("change", () => validateDepField(key));
                    el.addEventListener("blur", () => validateDepField(key));
                }
            });

            // Family number toggle
            depHasFamily?.addEventListener("change", function () {
                depFamilyContainer.style.display = this.checked ? "flex" : "none";
                if (!this.checked) {
                    depFamilyNumber.value = "";
                    removeFieldError(depFamilyNumber);
                }
                validateDepField("familyNumber");
            });

            // File upload display + validation
            depValidID?.addEventListener("change", function () {
                const file = this.files[0];
                if (file) {
                    depFileNameSpan.textContent = file.name;
                    validateDepField("validID_front");
                } else {
                    depFileNameSpan.textContent = "No file chosen";
                }
            });

            // MAIN FORM SUBMIT – AJAX with spinner + disable buttons + reload
            form.addEventListener("submit", function (e) {
                e.preventDefault(); // Critical: stops page navigation

                if (!validateDependentModal()) {
                    document.getElementById("addDependentError").textContent = "Please fix the errors above.";
                    document.getElementById("addDependentError").style.color = "#FF0000";
                    return;
                }

                // Disable buttons + show spinner
                saveBtn.disabled = true;
                cancelBtn.disabled = true;
                btnText.textContent = "Adding…";
                spinner.style.display = "inline-block";

                const formData = new FormData(form);

                fetch("add_dependent.php", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        throw new Error(data.message || "Unknown error");
                    }
                })
                .catch(err => {
                    console.error("Add dependent error:", err);
                    // Re-enable buttons
                    saveBtn.disabled = false;
                    cancelBtn.disabled = false;
                    btnText.textContent = "Add Dependent";
                    spinner.style.display = "none";

                    document.getElementById("addDependentError").textContent = 
                        err.message || "Failed to add dependent. Please try again.";
                    document.getElementById("addDependentError").style.color = "#FF0000";
                });
            });
        });

        // Optional: for switching accounts
        function switchAccount(dependentId) {
            window.location.href = `switch_account.php?switch_to=${dependentId}`;
        }
    </script>


    <script>
        function openCancelRequestModal(btn) {
            const id = btn.dataset.id;
            const confirmBtn = document.getElementById('confirmCancelBtn');
            confirmBtn.dataset.id = id;
            showModal('cancelRequestModal');
        }

        function closeCancelRequestModal() {
            hideModal('cancelRequestModal'); 
        }

        document.getElementById('confirmCancelBtn').addEventListener('click', function () {
            const requestId = this.dataset.id;
            if (!requestId) return;

            delayedConfirm(this, 2000, () => {
                return fetch(`cancel_request.php?id=${requestId}`, { method: 'POST' })
                    .then(r => r.json())
                    .then(data => {
                        closeCancelRequestModal();
                        if (data.success) {
                            const successModal = document.getElementById('cancelRequestSuccessModal');
                            successModal.classList.add('show');
                            document.getElementById('cancelRequestSuccessOkBtn').onclick = () => {
                                successModal.classList.remove('show');
                                location.reload();
                            };
                        } else {
                            alert('Failed to cancel: ' + data.message);
                        }
                    })
                    .catch(() => {
                        closeCancelRequestModal();
                        alert('Network error – try again later.');
                    });
            });
        });

        document.getElementById("confirmCancelBtn")?.addEventListener("click", function() {
            const requestId = this.getAttribute('data-id');
            if (requestId) {
                cancelRequest(requestId);
                closeCancelRequestModal();
            }
        });

    </script>


    <script>
        function viewConsultation(btn) {
            const cid = btn.getAttribute('data-cid');
            fetch(`get_consultation_details.php?id=${cid}`)
                .then(r => r.json())
                .then(data => {
                    // Populate modal fields similar to staff's view
                    document.getElementById('view_consultation_type').value = data.consultation_type || '';
                    document.getElementById('view_consultation_date').value = data.consultation_date || '';
                    document.getElementById('view_reason_for_consultation').value = data.reason_for_consultation || '';
                    document.getElementById('view_blood_pressure').value = data.blood_pressure || '';
                    document.getElementById('view_temperature').value = data.temperature || '';
                    document.getElementById('view_diagnosis').value = data.diagnosis || '';
                    document.getElementById('view_prescribed_medicine').value = data.prescribed_medicine || 'Not provided';
                    document.getElementById('view_treatment_given').value = data.treatment_given || 'Not provided';
                    document.getElementById('view_consulting_physician_nurse').value = data.consulting_physician_nurse || '';
                    document.getElementById('viewConsultationModal').classList.add('show');
                })
                .catch(() => alert('Failed to load consultation details.'));
        }

    </script>


    <script>
        function openCancelDependentModal(btn) {
            const id = btn.dataset.id;
            const confirmBtn = document.getElementById('confirmCancelDependentBtn');
            confirmBtn.dataset.id = id;
            showModal('cancelDependentModal');
        }

        function closeCancelDependentModal() {
            hideModal('cancelDependentModal'); 
        }

        function cancelDependent(dependentId) {
            fetch(`cancel_pending_dependent.php?id=${dependentId}`, {
                method: "POST"
            })
            .then(r => r.json())
            .then(data => {
                closeCancelDependentModal(); 
                if (data.success) {
                    const successModal = document.getElementById('cancelPendingDepSuccessModal');
                    successModal.classList.add('show');

                    document.getElementById('cancelPendingDepSuccessOkBtn').onclick = () => {
                        successModal.classList.remove('show');
                        location.reload();
                    };
                } else {
                    alert('Failed to cancel: ' + data.message);
                }
            })
            .catch(() => {
                closeCancelDependentModal();
                alert('Network error – try again later.');
            });
        }

        document.getElementById('confirmCancelDependentBtn')?.addEventListener('click', function () {
            const dependentId = this.dataset.id;
            if (!dependentId) return;

            delayedConfirm(this, 2000, () => {
                return fetch(`cancel_pending_dependent.php?id=${dependentId}`, { method: 'POST' })
                    .then(r => r.json())
                    .then(data => {
                        closeCancelDependentModal();
                        if (data.success) {
                            const successModal = document.getElementById('cancelPendingDepSuccessModal');
                            successModal.classList.add('show');
                            document.getElementById('cancelPendingDepSuccessOkBtn').onclick = () => {
                                successModal.classList.remove('show');
                                location.reload();
                            };
                        } else {
                            alert('Failed to cancel: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(() => {
                        closeCancelDependentModal();
                        alert('Network error – please try again.');
                    });
            });
        });
    </script>


    <script>
        // Logout Confirmation Modal
        function openLogoutConfirmModal() {
            showModal('logoutConfirmModal'); 
        }

        function closeLogoutConfirmModal() {
            hideModal('logoutConfirmModal'); 
        }

        function confirmLogout() {
            window.location.href = "logout.php";
        }
    </script>


    <script>
        // Switch Account Confirmation Modal
        let pendingSwitchId = null;

        function openSwitchAccountConfirmModal(id) {
            pendingSwitchId = id;
            document.getElementById('confirmSwitchAccountBtn').dataset.id = id;
            showModal('switchAccountConfirmModal');
        }

        function closeSwitchAccountConfirmModal() {
            pendingSwitchId = null;
            hideModal('switchAccountConfirmModal'); 
        }

        document.getElementById("confirmSwitchAccountBtn")?.addEventListener("click", function() {
            if (pendingSwitchId) {
                window.location.href = `switch_account.php?switch_to=${pendingSwitchId}`;
                closeSwitchAccountConfirmModal();
            }
        });
        
    </script>

    <script>
        // Select the hamburger toggle and navigation links container
        const menuToggle = document.getElementById('menu-toggle');
        const navLinks = document.querySelector('.nav-links');

        // Toggle the menu visibility when the hamburger icon is clicked
        menuToggle.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            menuToggle.classList.toggle('open');

            // Change icon (bars ↔ close)
            const icon = menuToggle.querySelector('i');
            if (menuToggle.classList.contains('open')) {
                icon.classList.replace('fa-bars', 'fa-times');
            } else {
                icon.classList.replace('fa-times', 'fa-bars');
            }
        });

        // Optional: close menu when a link is clicked (on mobile)
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                if (navLinks.classList.contains('active')) {
                    navLinks.classList.remove('active');
                    menuToggle.classList.remove('open');
                    const icon = menuToggle.querySelector('i');
                    icon.classList.replace('fa-times', 'fa-bars');
                }
            });
        });
    </script>
    <script>
    // --------------------------------------------------------------
    //  Edit Profile Modal – NEW submit handler with button disabling
    // --------------------------------------------------------------
    document.addEventListener("DOMContentLoaded", function () {
        const editModal   = document.getElementById("editProfileModal");
        const form        = document.getElementById("editProfileForm");
        const saveBtn     = document.getElementById("editSaveBtn");      // <-- new id
        const cancelBtn   = document.getElementById("editCancelBtn");    // <-- new id
        const btnText     = saveBtn.querySelector(".btn-text");
        const spinner     = saveBtn.querySelector(".fa-spinner");

        // ----------  Existing validation code (unchanged) ----------
        const isDependent = <?= $is_dependent ? 'true' : 'false' ?>;
        const phoneInput  = document.getElementById("phone_number");
        const emailInput  = document.getElementById("email");

        const validators = {
            first_name: { element: document.getElementById("first_name"),
                          validator: v => v.trim().length >= 2,
                          message: "First name must be at least 2 characters" },
            last_name:  { element: document.getElementById("last_name"),
                          validator: v => v.trim().length >= 2,
                          message: "Last name must be at least 2 characters" },
            middle_name:{ element: document.getElementById("middle_name"),
                          validator: () => true, message: "" },
            gender:     { element: document.getElementById("gender"),
                          validator: v => v === "Male" || v === "Female",
                          message: "Please select a gender" },
            birthday:   { element: document.getElementById("birthday"),
                          validator: v => {
                              if (!v) return false;
                              const birth = new Date(v);
                              const today = new Date();
                              let age = today.getFullYear() - birth.getFullYear();
                              const m = today.getMonth() - birth.getMonth();
                              if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
                              return age >= 18;
                          },
                          message: "You must be at least 18 years old" },
            address:    { element: document.getElementById("address"),
                          validator: v => v.trim().length >= 10,
                          message: "Please enter a complete address" }
        };

        if (!isDependent) {
            validators.phone_number = {
                element: phoneInput,
                validator: v => /^(\+63|0)9[0-9]{9}$/.test(v.replace(/\s/g, '')),
                message: "Invalid PH mobile number (e.g. 09123456789 or +639123456789)"
            };
            validators.email = {
                element: emailInput,
                validator: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v),
                message: "Please enter a valid email address"
            };
        }

        function showError(input, msg) {
            removeError(input);
            const err = document.createElement("span");
            err.className = "field-error";
            err.style.color = "#d32f2f";
            err.style.fontSize = "12px";
            err.style.display = "block";
            err.style.marginTop = "4px";
            err.textContent = msg;
            input.style.borderColor = "#d32f2f";
            input.closest(".group-col").appendChild(err);
        }
        function removeError(input) {
            const group = input.closest(".group-col");
            const old = group.querySelector(".field-error");
            if (old) old.remove();
            input.style.borderColor = "";
        }
        function clearAllErrors() {
            Object.values(validators).forEach(f => f.element && removeError(f.element));
        }

        // Clear previous field errors
        function clearFieldErrors() {
            document.querySelectorAll(".field-error").forEach(el => el.remove());
            document.querySelectorAll("input, select").forEach(el => el.style.borderColor = "");
        }

        // Show field-specific error
        function showFieldError(fieldName, message) {
            const input = document.getElementById(fieldName);
            if (!input) return;
            removeError(input);
            const err = document.createElement("span");
            err.className = "field-error";
            err.style.color = "#d32f2f";
            err.style.fontSize = "12px";
            err.style.display = "block";
            err.style.marginTop = "4px";
            err.textContent = message;
            input.style.borderColor = "#d32f2f";
            input.closest(".group-col").appendChild(err);
        }

        function validateField(key) {
            const f = validators[key];
            const ok = f.validator(f.element.value.trim());
            if (!ok && f.message) showError(f.element, f.message);
            else removeError(f.element);
            return ok;
        }
        function validateAll() {
            return Object.keys(validators).every(k => validateField(k));
        }

        // real-time validation
        Object.keys(validators).forEach(k => {
            const el = validators[k].element;
            if (el) {
                el.addEventListener("input", () => { validateField(k); updateSaveButton(); });
                el.addEventListener("blur",  () => { validateField(k); updateSaveButton(); });
            }
        });

        function updateSaveButton() {
            saveBtn.disabled = !validateAll();
        }

        // ----------  NEW SUBMIT HANDLER ----------
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            clearAllErrors();

            if (!validateAll()) return;               // client-side guard

            // ---- 1. DISABLE BOTH BUTTONS + SPINNER ----
            saveBtn.disabled   = true;
            cancelBtn.disabled = true;
            btnText.textContent = "Saving…";
            spinner.style.display = "inline-block";

            const formData = new FormData(form);

            fetch("update_profile.php", {
                method: "POST",
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                } else {
                    // Re-enable buttons
                    saveBtn.disabled = false;
                    cancelBtn.disabled = false;
                    btnText.textContent = "Save";
                    spinner.style.display = "none";

                    // Show field-specific errors
                    if (data.field_errors) {
                        Object.keys(data.field_errors).forEach(field => {
                            showFieldError(field, data.field_errors[field]);
                        });
                    }
                }
            })
            .catch(err => {
                console.error(err);
                saveBtn.disabled = false;
                cancelBtn.disabled = false;
                btnText.textContent = "Save";
                spinner.style.display = "none";
            });
        });

        // open / close helpers (unchanged except for clearing UI state)
        window.openEditProfileModal = function () {
            clearAllErrors();
            editModal.classList.add("show");
            updateSaveButton();
        };
        window.closeEditProfileModal = function () {
            editModal.classList.remove("show");
            clearAllErrors();
            clearFieldErrors();
            form.reset();
            // reset button UI in case it was left in “saving” state
            saveBtn.disabled   = false;
            cancelBtn.disabled = false;
            btnText.textContent = "Save";
            spinner.style.display = "none";
        };

        // initial button state
        updateSaveButton();
    });
</script>
</body>
</html>