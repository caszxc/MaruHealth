<?php
//request_medicine.php
session_start();
require 'config.php'; // Include your DB connection

$profilePic = 'images/uploads/profile_pictures/profile-placeholder.png'; // default picture

if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && !empty($user['profile_picture'])) {
        $profilePic = htmlspecialchars($user['profile_picture']);
    }
}

$userData = [];

if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT first_name, last_name, middle_name, gender, birthday, address, phone_number FROM users WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

?>

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
            <img src="images/3s logo.png">
            <div>
                <h1>Maru-Health</h1>
                <p>Barangay Marulas 3S Health Station</p>
            </div>
        </div>

        <div class="nav-links">
            <ul>
                <li><a href="index.php" class="links">HOME</a></li>
                <li><a href="calendar.php" class="links">CALENDAR</a></li>
                <li><a href="request_medicine.php" class="links">MEDICINE REQUEST</a></li>
                <li><a href="about_us.php" class="links">ABOUT US</a></li>

                <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'user'): ?>
                    <li>
                        <a href="profile.php" class="profile">
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile Picture" class="nav-profile-pic">
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

            <?php if (!isset($_SESSION['user_id'])): ?>
                <!-- Pop-up Modal -->
                <div id="popupModal" class="modal">
                    <div class="modal-content">
                        <h2>Note</h2>
                        <p>To request a medicine, you need to log in first.</p>
                        <button id="closeModal" onclick="redirectToLogin()">OK</button>
                    </div>
                </div>

                <script>
                    document.addEventListener("DOMContentLoaded", function () {
                        document.getElementById("popupModal").style.display = "flex";
                    });

                    function redirectToLogin() {
                        window.location.href = "login.php";
                    }
                </script>
            <?php endif; ?>

            <form action="submit_request.php" method="POST" enctype="multipart/form-data">
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
                            <div>
                                <label>Medicine Name</label>
                                <input type="text" name="medicine_name[]" required>
                            </div>
                            <div>
                                <label>Dosage</label>
                                <input type="text" name="dosage[]">
                            </div>
                            <div>
                                <label>Quantity</label>
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
                            <label>Upload Prescription</label>
                            <div class="file-upload">
                                <label for="file-upload" class="custom-file-upload">
                                    <i class="fas fa-cloud-upload-alt"></i> Add File
                                </label>
                                <input id="file-upload" type="file" name="prescription" onchange="updateFileName()" accept="image/*,application/pdf" required>
                                <span id="file-name">No file chosen</span>
                            </div>
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
            clone.querySelectorAll('input').forEach(input => input.value = '');

            container.appendChild(clone);
        });

        // Remove entry when clicking 🗑
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-medicine-btn')) {
                const allEntries = document.querySelectorAll('.medicine-entry');
                if (allEntries.length > 1) {
                    e.target.closest('.medicine-entry').remove();
                } else {
                    alert("At least one medicine entry must remain.");
                }
            }
        });

        document.addEventListener("DOMContentLoaded", function () {
            const fileInput = document.getElementById('file-upload');
            const fileNameSpan = document.getElementById('file-name');

            fileInput.addEventListener('change', function () {
                if (fileInput.files.length > 0) {
                    fileNameSpan.textContent = fileInput.files[0].name;
                } else {
                    fileNameSpan.textContent = 'No file chosen';
                }
            });
        });
    </script>

</body>
</html>
