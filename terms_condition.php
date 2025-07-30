<?php
session_start();
include 'config.php';

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

// Fetch the admin's name
$adminStmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE role = 'admin' LIMIT 1");
$adminStmt->execute();
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

// Default to "Admin" if no admin is found
$adminName = $admin ? $admin['first_name'] . ' ' . $admin['last_name'] : 'Admin';


?>


<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="stylesheet" href="css/policy_terms.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <title>Terms and Conditions</title>
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="images/3s logo.png">
            <p>Maru-Health <br> Barangay Marulas 3S <br> Health Station</p>
        </div>

        <div class="nav-links">
            <ul>
                <li><a href="index.php" class="links">HOME</a></li>
                <li><a href="calendar.php" class="links">CALENDAR</a></li>
                <li><a href="request_medicine.php" class="links">MEDICINE REQUEST</a></li>
                <li><a href="about_us.php" class="links">ABOUT US</a></li>

                <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] === 'user'): ?>
                    <li>
                        <a href="profile.php" class="profile">
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

    <section class="container">
        <div class="address-contact">
            <p><i class="fa fa-map-marker"></i> 3S Center Marulas, Market, Valenzuela, Metro Manila</p>
            <p><i class="fa fa-phone"></i> 0968 351 1100</p>
        </div>
        <div class="policy-terms">
            <p class="breadcrumb"><a href="index.php">Home</a> > Terms & Conditions</p>
            <h2 class="title">Terms & Conditions</h2>
            <div class="policy-terms-container">
                <div class="policy-content">
                    <h3 class="section-title">Terms & Conditions</h3>
                    <p class="intro">Welcome to MaruHealth, by accessing and using this system, you agree to comply with and be bound by the following terms and conditions. Please read them carefully.</p>

                    <ol>
                        <li>
                            <strong>Acceptance of Terms</strong>
                            <p>By using MaruHealth, you acknowledge that you have read, understood, and agreed to these terms. If you do not agree with any part of these terms, you must discontinue use of the system.</p>
                        </li>

                        <li>
                            <strong>User Registration & Responsibilities</strong>
                            <ul>
                                <li>Users must provide accurate and complete information during registration.</li>
                                <li>Each user is responsible for maintaining the confidentiality of their account credentials.</li>
                                <li>Unauthorized access or use of another user's account is strictly prohibited.</li>
                            </ul>
                        </li>

                        <li>
                            <strong>Services Provided</strong>
                            <p>MaruHealth offers the following features:</p>
                            <ul>
                                <li>Checking health center schedules, announcements and health-related events.</li>
                                <li>Online medicine request.</li>
                                <li>Receiving notifications regarding the status of the requested medicine.</li>
                            </ul>
                        </li>

                        <li>
                            <strong>Privacy and Data Protection</strong>
                            <ul>
                                <li>MaruHealth values user privacy and ensures that personal data is protected in accordance with applicable data protection laws.</li>
                                <li>User information will only be used for health services management purposes.</li>
                            </ul>
                        </li>

                        <li>
                            <strong>Acceptable Use</strong>
                            <p>Users agree to:</p>
                            <ul>
                                <li>Use MaruHealth only for lawful purposes.</li>
                                <li>Refrain from transmitting any malicious software, hacking attempts, or engaging in unauthorized access.</li>
                                <li>Not disrupt the operation of the system or compromise its security.</li>
                            </ul>
                        </li>

                        <li>
                            <strong>Limitation of Liability</strong>
                            <ul>
                                <li>MaruHealth and its administrators are not liable for any damages or losses incurred due to misuse, system downtimes, or incorrect information provided by users.</li>
                                <li>The system does not replace professional medical consultations.</li>
                            </ul>
                        </li>

                        <li>
                            <strong>Termination of Access</strong>
                            <p>MaruHealth may suspend or terminate user access if there is a violation of these terms. Any misuse or unauthorized activity may lead to account deactivation or legal action.</p>
                        </li>

                        <li>
                            <strong>Governing Law</strong>
                            <p>These terms are governed by the laws of the Philippines, and any disputes shall be resolved within the appropriate legal jurisdiction.</p>
                        </li>

                        <li>
                            <strong>Contact Information</strong>
                            <p>For any inquiries or concerns regarding these terms, please contact Barangay Marulas 3S Health Center.</p>
                        </li>
                    </ol>

                    <p class="closing">By using MaruHealth, you acknowledge and agree to these terms and conditions.<br>Thank you for using our system responsibly.</p>
                </div>
            </div>
        </div>
        
    </section> 
</body>
</html>
