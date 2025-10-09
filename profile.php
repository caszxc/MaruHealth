<?php
// profile.php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

$primary_user_id = $_SESSION['user_id'];
$active_user_id = isset($_SESSION['active_user_id']) ? $_SESSION['active_user_id'] : $primary_user_id;
$is_dependent = ($active_user_id != $primary_user_id);
$requests = []; // default to empty
$patient = null; // linked patient record (if any)
$consultations = []; // patient's consultation history
$dependents = []; // dependents list

// Fetch user details for the active user (primary or dependent)
$sql = "SELECT first_name, last_name, middle_name, gender, birthday, address, phone_number, email, profile_picture, family_number, primary_user_id 
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

// Fetch medicine requests for the active user
$sql = "SELECT id, request_id, request_date, request_status FROM medicine_requests WHERE user_id = :active_user_id ORDER BY request_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':active_user_id', $active_user_id, PDO::PARAM_INT);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Try to link the active user to a patient record (if existing)
try {
    $patientLookup = $conn->prepare("SELECT * FROM patients WHERE first_name = :fn AND last_name = :ln AND birthdate = :bd ORDER BY id DESC LIMIT 1");
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
    <style>
        /* Scoped styles for the resident consultation details modal */
        .modal-content form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            padding: 20px;
        }
        .modal-content form .group-row {
            display: flex;
            gap: 10px;
        }
        .modal-content form .group-col {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .modal-content form label {
            font-size: 14px;
            margin-bottom: 5px;
        }
        .modal-content form input, .modal-content form select {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }
        .modal-content form input[type="password"] {
            width: 100%;
        }
        .error-message {
            color: #FF0000;
            font-size: 12px;
            margin-top: 5px;
        }
        .dependents-con {
            width: 100%;
            padding: 20px;
        }
        .add-dependent-btn {
            background-color: #8B0000;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .dependents-table {
            width: 100%;
            border-collapse: collapse;
        }
        .dependents-table th, .dependents-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .dependents-table th {
            background-color: #8b0000;
        }
        .switch-account-btn {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .modal-content .file-upload {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-content .file-upload label {
            background-color: #8B0000;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        .modal-content .file-upload input[type="file"] {
            display: none;
        }
        .modal-content .file-name {
            font-size: 14px;
        }
    </style>
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

        <div class="nav-links">
            <ul>
                <li><a href="index.php">HOME</a></li>
                <li><a href="calendar.php">CALENDAR</a></li>
                <li><a href="request_medicine.php" class="links">MEDICINE REQUEST</a></li>
                <li><a href="about_us.php">ABOUT US</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] === 'user'): ?>
                    <li>
                        <a href="profile.php">
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile Picture" class="nav-profile-pic">
                        </a>
                    </li>
                <?php endif; ?>
                <?php else: ?>
                    <li><a href="login.php" class="login-button">LOG IN</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="profile-container">
        <h2 class="page-title">Profile</h2>
        <div class="profile-box">
            <div class="profile-img">
                <div class="profile-pic-wrapper" onclick="openProfilePicModal()">
                    <img src="<?= htmlspecialchars($profilePic) ?>" alt="profile">
                    <button class="icon-button" onclick="openProfilePicModal(event)">
                        <i class="fas fa-pen"></i>
                    </button>
                </div>
                <p class="full-name"><?php echo htmlspecialchars($user['first_name'] . " " . $user['middle_name'] . " " . $user['last_name']); ?></p>
                <button class="edit-profile-btn" onclick="openEditProfileModal()">Edit Profile</button>
                <?php if ($is_dependent): ?>
                <a href="switch_account.php" class="switch-acc-btn">Switch Account</a>
                <?php endif; ?>
                <?php if (!$is_dependent): ?>
                <button class="change-password-btn" onclick="openChangePasswordModal()">Change Password</button>
                <?php endif; ?>
                <form action="logout.php" method="POST">
                    <button type="submit" class="logout-button">Log Out</button>
                </form>
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
                
                <!-- Details Tab -->
                <div id="details" class="tab-content" style="display: flex;">
                    <div class="content-con">
                        <h3 class="title">General Information</h3>
                        <div class="group-row">
                            <?php if (!empty($user['family_number'])): ?>
                            <div class="row">
                                <p class="label">Family Number</p>
                                <p class="value"><?php echo htmlspecialchars($user['family_number']); ?></p>
                            </div>
                            <?php endif; ?>
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
                        <table>
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Date & Time Requested</th>
                                    <th>Status</th>
                                    <th></th>
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
                                            'pending' => 'style="background-color: #bbb; color: white;"',
                                            'declined' => 'style="background-color: #dc3545; color: white;"',
                                            'to be claimed' => 'style="background-color: #ffc107; color: black;"'
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
                                            <td><button class="view-btn" data-id="<?= $request['id'] ?>" onclick="viewRequest(this)">View Request</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Patient Record Tab -->
                <div id="patient-record" class="tab-content" style="display: none;">
                    <div class="patient-con">
                        <div class="content-con">
                            <h3 class="title">Anthropometric Measurement</h3>
                            <?php if ($patient): ?>
                            <div class="metrics-grid">
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

                        <div class="content-con">
                            <h3 class="title">Consultation History</h3>
                            <?php if ($patient && !empty($consultations)): ?>
                                <div class="table-container">
                                    <table class="record-table">
                                        <thead>
                                            <tr>
                                                <th>Date & Time Requested</th>
                                                <th>Type</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($consultations as $c): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars(date('n/j/Y g:ia', strtotime($c['consultation_date']))) ?></td>
                                                    <td><?= htmlspecialchars($c['consultation_type']) ?></td>
                                                    <td><button class="view-btn" data-cid="<?= $c['id'] ?>" onclick="viewConsultation(this)">View</button></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif ($patient): ?>
                                <p>No consultations yet.</p>
                            <?php else: ?>
                                <p>Consultations will appear once a patient record is created.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Dependents Tab -->
                <?php if (!$is_dependent): ?>
                <div id="dependents" class="tab-content" style="display: none;">
                    <div class="dependents-con">
                        <button class="add-dependent-btn" onclick="openAddDependentModal()">Add Dependent</button>
                        <?php if (empty($dependents)): ?>
                            <p>No dependents added yet.</p>
                        <?php else: ?>
                            <table class="dependents-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Relationship</th>
                                        <th>Date of Birth</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dependents as $dependent): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($dependent['first_name'] . ' ' . $dependent['middle_name'] . ' ' . $dependent['last_name']) ?></td>
                                            <td><?= htmlspecialchars($dependent['relationship']) ?></td>
                                            <td><?= htmlspecialchars(date('F j, Y', strtotime($dependent['birthday']))) ?></td>
                                            <td><button class="switch-account-btn" onclick="switchAccount(<?= $dependent['id'] ?>)">Switch to Account</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Profile Picture Modal -->
    <div id="changeProfileModal" class="modal">
        <div class="modal-content">
            <h2>Change Profile Picture</h2>
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
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeProfilePicModal()">Cancel</button>
                    <button type="submit" class="save-btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <h2>Edit Profile</h2>
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
                <div class="error-message" id="editProfileError"></div>
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeEditProfileModal()">Cancel</button>
                    <button type="submit" class="save-btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password Modal (Only for Primary User) -->
    <?php if (!$is_dependent): ?>
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <h2>Change Password</h2>
            <form id="changePasswordForm" action="change_password.php" method="POST">
                <div class="group-col">
                    <label for="current_password">Current Password <span class="required">*</span></label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="group-col">
                    <label for="new_password">New Password <span class="required">*</span></label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="group-col">
                    <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="error-message" id="changePasswordError"></div>
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeChangePasswordModal()">Cancel</button>
                    <button type="submit" class="save-btn">Save</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Dependent Modal (Only for Primary User) -->
    <?php if (!$is_dependent): ?>
    <div id="addDependentModal" class="modal">
        <div class="modal-content">
            <h2>Add Dependent</h2>
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
                <div class="error-message" id="addDependentError"></div>
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeAddDependentModal()">Cancel</button>
                    <button type="submit" class="save-btn">Add Dependent</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- View Consultation Modal -->
    <div id="viewConsultationModal" class="modal">
        <div class="modal-content">
            <h2 class="title">Consultation Details</h2>
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
                <div class="two-col">
                    <div class="form-group">
                        <div class="form-row">
                            <label>Blood Pressure</label>
                            <input type="text" id="view_blood_pressure" readonly>
                        </div>
                        <div class="form-row">
                            <label>Temperature</label>
                            <input type="text" id="view_temperature" readonly>
                        </div>
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
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="document.getElementById('viewConsultationModal').classList.remove('show')">Close</button>
            </div>
        </div>
    </div>

    <!-- View Request Modal -->
    <div id="viewRequestModal" class="modal"> 
        <div class="modal-content">
            <h2>Request Medicine Details</h2>
            <div class="modal-container">
                <div class="row">
                    <div>
                        <label>Request ID</label>
                        <span id="requestId"></span>
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Request Status</label>
                        <span id="requestStatus"></span>
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Patient's Full Name</label>
                        <span id="req_fullName"></span>
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Sex</label>
                        <span id="req_sex"></span>
                    </div>
                    <div>
                        <label>Birthdate</label>
                        <span id="req_birthdate"></span>
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Address</label>
                        <span id="req_address"></span>
                    </div>
                    <div>
                        <label>Contact Number</label>
                        <span id="req_phone"></span>
                    </div>
                </div>
                <div id="medicine-group">
                    <label>Requested Medicines</label>
                </div>
                <div class="row">
                    <div>
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
                <div id="claim-info" style="display: none;">
                    <div class="row">
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
                <div id="note-info" style="display: none;">
                    <div class="row">
                        <div>
                            <label>Note</label>
                            <span id="req_note"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeViewModal()">Close</button>
                </div>
            </div>
        </div>
    </div>

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

        function closeViewModal() {
            document.getElementById("viewRequestModal").classList.remove("show");
        }

        function openChangePasswordModal() {
            document.getElementById("changePasswordModal").classList.add("show");
            document.getElementById("changePasswordError").textContent = "";
        }

        function closeChangePasswordModal() {
            document.getElementById("changePasswordModal").classList.remove("show");
            document.getElementById("changePasswordForm").reset();
            document.getElementById("changePasswordError").textContent = "";
        }

        function openAddDependentModal() {
            document.getElementById("addDependentModal").classList.add("show");
            document.getElementById("addDependentError").textContent = "";
            document.getElementById("addDependentForm").reset();
            document.getElementById("dep_file_name").textContent = "No file chosen";
            document.getElementById("dep_familyNumberContainer").style.display = "none";
        }

        function closeAddDependentModal() {
            document.getElementById("addDependentModal").classList.remove("show");
            document.getElementById("addDependentForm").reset();
            document.getElementById("addDependentError").textContent = "";
        }

        document.getElementById("addDependentForm")?.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch("add_dependent.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const errorDiv = document.getElementById("addDependentError");
                errorDiv.textContent = data.message;
                
                if (data.success) {
                    errorDiv.style.color = "#28a745"; // Green for success
                    setTimeout(() => {
                        location.reload(); // Reload to reflect new dependent
                    }, 1500);
                } else {
                    errorDiv.style.color = "#FF0000"; // Red for error
                }
            })
            .catch(error => {
                document.getElementById("addDependentError").textContent = "An error occurred. Please try again.";
                console.error(error);
            });
        });

        document.getElementById("dep_hasFamilyNumber")?.addEventListener("change", function() {
            document.getElementById("dep_familyNumberContainer").style.display = this.checked ? "flex" : "none";
            if (!this.checked) {
                document.getElementById("dep_familyNumber").value = "";
            }
        });

        document.getElementById("dep_validID_front")?.addEventListener("change", function() {
            const fileNameSpan = document.getElementById("dep_file_name");
            if (this.files.length > 0) {
                fileNameSpan.textContent = this.files[0].name;
            } else {
                fileNameSpan.textContent = "No file chosen";
            }
        });

        function switchAccount(dependentId) {
            window.location.href = `switch_account.php?switch_to=${dependentId}`;
        }

        document.getElementById("changePasswordForm")?.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch("change_password.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const errorDiv = document.getElementById("changePasswordError");
                errorDiv.textContent = data.message;
                
                if (data.success) {
                    errorDiv.style.color = "#28a745"; // Green for success
                    setTimeout(() => {
                        closeChangePasswordModal();
                    }, 1500);
                } else {
                    errorDiv.style.color = "#FF0000"; // Red for error
                }
            })
            .catch(error => {
                document.getElementById("changePasswordError").textContent = "An error occurred. Please try again.";
                console.error(error);
            });
        });

        function openEditProfileModal() {
            document.getElementById("editProfileModal").classList.add("show");
            document.getElementById("editProfileError").textContent = "";
        }

        function closeEditProfileModal() {
            document.getElementById("editProfileModal").classList.remove("show");
            document.getElementById("editProfileError").textContent = "";
        }

        document.getElementById("editProfileForm").addEventListener("submit", function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch("update_profile.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const errorDiv = document.getElementById("editProfileError");
                errorDiv.textContent = data.message;
                
                if (data.success) {
                    errorDiv.style.color = "#28a745"; // Green for success
                    setTimeout(() => {
                        location.reload(); // Reload to reflect updated profile data
                    }, 1500);
                } else {
                    errorDiv.style.color = "#FF0000"; // Red for error
                }
            })
            .catch(error => {
                document.getElementById("editProfileError").textContent = "An error occurred. Please try again.";
                console.error(error);
            });
        });

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
                    }, 1500);
                } else {
                    errorDiv.style.color = "#FF0000"; // Red for error
                }
            })
            .catch(error => {
                document.getElementById("profilePicError").textContent = "An error occurred. Please try again.";
                console.error(error);
            });
        });

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
                    data.medicines.forEach(med => {
                        const row = document.createElement('div');
                        row.classList.add('row', 'medicine-entry');
                        row.innerHTML = `
                            <div><label>Medicine Name</label><span>${med.medicine_name}</span></div>
                            <div><label>Dosage</label><span>${med.dosage || 'N/A'}</span></div>
                            <div><label>Quantity</label><span>${med.quantity}</span></div>
                            <div><label>Status</label><span class="status-badge status-${med.status}">${med.status.charAt(0).toUpperCase() + med.status.slice(1)}</span></div>
                        `;
                        medicineGroup.appendChild(row);
                    });

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
</body>
</html>