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

    <title>Privacy and Policy</title>
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
            <p class="breadcrumb"><a href="index.php">Home</a> > Privacy & Policy</p>
            <h2 class="title">Privacy & Policy</h2>
            <div class="policy-terms-container">
                <div class="policy-content">
                    <h3 class="section-title">Privacy Policy</h3>
                    <p class="intro">MaruHealth is committed to protecting your privacy. This Privacy Policy explains how we collect, use, and safeguard your personal information.</p>

                    <ol>
                        <li>
                            <strong>Information We Collect</strong>
                            <ul>
                                <li>Personal Information: Name, sex, birth date, civil status, occupation, contact number, email address and 1 valid ID for proof that you are a resident of Barangay Marulas.</li>
                                <li>Medical Information: Consultation history, family folder number and family members.</li>
                                <li>Usage Data: System access logs, device information, and interactions with the platform.</li>
                            </ul>
                        </li>
                        <li>
                            <strong>How Do We Use Your Information</strong>
                            <ul>
                                <li>Providing and improving health services.</li>
                                <li>Sending notifications regarding health center updates and medicine availability.</li>
                                <li>Ensuring security and proper system functionality.</li>
                                <li>Complying with legal and regulatory requirements.</li>
                            </ul>
                        </li>
                        <li>
                            <strong>Data Sharing and Security</strong>
                            <ul>
                                <li>We do not sell or share user data with third parties, except as required by law or for health service purposes.</li>
                                <li>Personal data is encrypted and stored securely to prevent unauthorized access.</li>
                                <li>Users are responsible for keeping their login credentials confidential.</li>
                            </ul>
                        </li>
                        <li>
                            <strong>User Rights</strong>
                            <ul>
                                <li>Access and review their personal information.</li>
                                <li>Request corrections to inaccurate data.</li>
                                <li>Request deletion of their data, subject to legal and operational requirements.</li>
                            </ul>
                        </li>
                        <li>
                            <strong>Contact Information</strong>
                            <p>For privacy-related concerns, please contact Barangay Marulas 3S Health Center.<br>
                            By using MaruHealth, you acknowledge and agree to this Privacy Policy.</p>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
        
    </section> 
</body>
</html>
