<?php
// request_medicine.php
session_start();
require 'config.php'; // Include your DB connection
include 'settings.php';

$profilePic = 'images/uploads/profile_pictures/profile-placeholder.png'; // default picture
$userData = [];

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'user') {
    // Use active_user_id if set, otherwise fall back to user_id
    $active_user_id = isset($_SESSION['active_user_id']) ? $_SESSION['active_user_id'] : $_SESSION['user_id'];

    // Validate the active_user_id and fetch profile picture and user data
    $stmt = $conn->prepare("SELECT profile_picture, primary_user_id, first_name, last_name, middle_name, gender, birthday, address, phone_number FROM users WHERE id = :active_user_id");
    $stmt->bindParam(':active_user_id', $active_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ensure the user exists and is either the primary user or a dependent of the logged-in primary user
    if ($user && ($active_user_id == $_SESSION['user_id'] || $user['primary_user_id'] == $_SESSION['user_id'])) {
        if (!empty($user['profile_picture'])) {
            $profilePic = htmlspecialchars($user['profile_picture']);
        }
        $userData = [
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'middle_name' => $user['middle_name'],
            'gender' => $user['gender'],
            'birthday' => $user['birthday'],
            'address' => $user['address'],
            'phone_number' => $user['phone_number']
        ];
    } else {
        // Invalid active_user_id, fall back to default picture and empty user data
        $profilePic = 'images/uploads/profile_pictures/profile-placeholder.png';
        $userData = [];
    }
}
require_once "deletion_notice.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="stylesheet" href="css/request_med.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <title>Medicine Request</title>
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

    <div class="container">
        <div class="form-box">
            <h1>Medicine Request Form</h1>
            <div class="form-desc">
                <p>Please fill out the form carefully. Your request will still need to be validated. Kindly check your SMS or email for updates on your request status.</p>
            </div>

            <?php if (!isset($_SESSION['user_id'])): ?>
                <!-- Pop-up Modal -->
                <div id="requireModal" class="modal info">
                    <div class="modal-content">
                        <div class="modal-icon">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                        <h2>Login Required</h2>
                        <p>To request medicine, please log in first.</p>
                        <button class="btn-primary" onclick="redirectToLogin()">Log In</button>
                    </div>
                </div>

                <script>
                    document.addEventListener("DOMContentLoaded", function () {
                        document.getElementById("requireModal").style.display = "flex";
                    });

                    function redirectToLogin() {
                        window.location.href = "login.php";
                    }
                </script>
            <?php endif; ?>

            <!-- Success Modal -->
            <div id="successModal" class="modal success">
                <div class="modal-content">
                    <div class="modal-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2>Success!</h2>
                    <p id="successMessage"></p>
                    <button id="closeSuccessModal" class="btn-success">OK</button>
                </div>
            </div>

            <form id="requestForm" action="submit_request.php" method="POST" enctype="multipart/form-data">
                <div class="form-container">
                    <div class="row">
                        <div>
                            <label>Patient's Full Name</label>
                            <input type="text" name="full_name" value="<?= isset($userData['last_name']) ? htmlspecialchars($userData['last_name'] . ', ' . $userData['first_name'] . ' ' . $userData['middle_name']) : '' ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div>
                            <label>Gender</label>
                            <input type="text" name="gender" value="<?= htmlspecialchars($userData['gender'] ?? '') ?>" readonly>
                        </div>
                        <div>
                            <label>Birthdate</label>
                            <input type="date" name="birthdate" value="<?= htmlspecialchars($userData['birthday'] ?? '') ?>" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label>Address</label>
                            <input type="text" name="address" value="<?= htmlspecialchars($userData['address'] ?? '') ?>" readonly>
                        </div>
                        <div>
                            <label>Contact Number</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($userData['phone_number'] ?? '') ?>" readonly>
                        </div>
                    </div>

                    <div id="medicine-group">
                        <div class="row medicine-entry">
                            <div class="med-name">
                                <label>Medicine Name <span class="required"></span></label>
                                <input type="text" name="medicine_name[]" required autocomplete="off">
                                <small class="field-hint">Enter the generic or brand name (e.g. Paracetamol, Biogesic)</small>

                            </div>
                            <div>
                                <label>Dosage <span class="required"></span></label>
                                <input type="text" name="dosage[]" required autocomplete="off">
                                <small class="field-hint">Separate the number and unit (e.g. 500 mg, 10 mL)</small>
                            </div>
                            <div>
                                <label>Quantity <span class="required"></span></label>
                                <input type="number" name="quantity[]" min="1" required>
                            </div>
                            <button type="button" class="remove-medicine-btn" style="margin-top: 24px;"><i class="fa fa-trash"></i></button>
                        </div>
                    </div>

                    <div class="row">
                        <button type="button" class="add-medicine-btn">+ Add Medicine</button>
                    </div>

                    <div class="row">
                        <div>
                            <label>Reason for Request (Optional)</label>
                            <textarea name="reason" rows="4"></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div>
                            <label>Upload Prescription <span class="required"></span></label>
                            <div class="file-upload">
                                <label for="file-upload" class="custom-file-upload">
                                    <i class="fas fa-cloud-upload-alt"></i> Add File
                                </label>
                                <input id="file-upload" type="file" name="prescription" onchange="updateFileName()" accept="image/jpeg,image/jpg,image/png">
                                <span id="file-name">No file chosen</span>
                            </div>
                            <small class="field-hint">Upload a clear photo of your prescription (JPG or PNG only)</small>
                            <span id="prescription-error" class="error-message"></span>
                        </div>
                    </div>
                    
                    <label class="checkbox-label">
                        <input type="checkbox" name="confirm" required>
                        By submitting this form, I confirm that the information provided is accurate. I understand that some medicines require a prescription and that availability depends on the health center’s stock.
                    </label>

                    <button type="submit" class="submit-btn">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.querySelector('.add-medicine-btn').addEventListener('click', function () {
            const container = document.getElementById('medicine-group');
            const entry = document.querySelector('.medicine-entry');
            const clone = entry.cloneNode(true);

            // Clear inputs
            clone.querySelector('input[name="medicine_name[]"]').value = '';
            clone.querySelector('input[name="dosage[]"]').value = '';
            clone.querySelector('input[name="quantity[]"]').value = '';

            container.appendChild(clone);
        });

        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-medicine-btn')) {
                const allEntries = document.querySelectorAll('.medicine-entry');
                if (allEntries.length > 1) {
                    e.target.closest('.medicine-entry').remove();
                } 
            }
        });

        function updateFileName() {
            const fileInput = document.getElementById('file-upload');
            const fileNameSpan = document.getElementById('file-name');
            const errorSpan = document.getElementById('prescription-error');

            if (fileInput.files.length > 0) {
                fileNameSpan.textContent = fileInput.files[0].name;
                errorSpan.textContent = ''; // clear error
            } else {
                fileNameSpan.textContent = 'No file chosen';
            }
        }


        // Handle login modal
        document.addEventListener("DOMContentLoaded", function () {
            <?php if (!isset($_SESSION['user_id'])): ?>
                document.getElementById("requireModal").classList.add("show");
            <?php endif; ?>

            const urlParams = new URLSearchParams(window.location.search);
            const requestId = urlParams.get('request_id');
            if (requestId) {
                const successModal = document.getElementById('successModal');
                const successMessage = document.getElementById('successMessage');
                successMessage.textContent = `Medicine request submitted successfully! Your Request ID is: ${requestId}`;
                successModal.classList.add('show');
                // Clean URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        function redirectToLogin() {
            window.location.href = "login.php";
        }

        // Close success modal
        document.getElementById('closeSuccessModal').addEventListener('click', function () {
            document.getElementById('successModal').classList.remove('show');
            window.location.href = 'request_medicine.php'; // Refresh
        });
        
        document.getElementById('requestForm').addEventListener('submit', function (e) {
            const fileInput = document.getElementById('file-upload');
            const errorSpan = document.getElementById('prescription-error');

            // Clear old error
            errorSpan.textContent = '';

            // If no file uploaded
            if (fileInput.files.length === 0) {
                e.preventDefault(); // stop form from submitting
                errorSpan.textContent = 'Please upload a prescription before submitting.';
                fileInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
</body>

</html>
