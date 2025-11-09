<?php
// about_us.php
session_start();
include 'config.php';

$profilePic = 'images/uploads/profile_pictures/profile-placeholder.png'; // default picture

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'user') {
    // Use active_user_id if set, otherwise fall back to user_id
    $active_user_id = isset($_SESSION['active_user_id']) ? $_SESSION['active_user_id'] : $_SESSION['user_id'];

    // Validate the active_user_id
    $stmt = $conn->prepare("SELECT profile_picture, primary_user_id FROM users WHERE id = :active_user_id");
    $stmt->bindParam(':active_user_id', $active_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ensure the user exists and is either the primary user or a dependent of the logged-in primary user
    if ($user && ($active_user_id == $_SESSION['user_id'] || $user['primary_user_id'] == $_SESSION['user_id'])) {
        if (!empty($user['profile_picture'])) {
            $profilePic = htmlspecialchars($user['profile_picture']);
        }
    } else {
        // Invalid active_user_id, fall back to default picture
        $profilePic = 'images/uploads/profile_pictures/profile-placeholder.png';
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
    <link rel="stylesheet" href="css/about_us.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>About Us</title>
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="images/3s logo.png">
            <div>
                <h1>MaruHealth</h1>
                <p>Barangay Marulas 3S Health Station</p>
            </div>
        </div>

        <!-- Hamburger Icon for Small Screens -->
        <div class="menu-toggle" id="menu-toggle">
            <i class="fa fa-bars"></i>
        </div>

        <div class="nav-links">
            <ul>
                <li><a href="index.php">HOME</a></li>
                <li><a href="calendar.php">CALENDAR</a></li>
                <li><a href="request_medicine.php" class="links">MEDICINE REQUEST</a></li>
                <li><a href="about_us.php">ABOUT US</a></li>

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

    <div class="banner">
        <div class="address-contact">
            <p><i class="fa fa-map-marker"></i> 3S Center Marulas, Market, Valenzuela, Metro Manila</p>
            <p><i class="fa fa-phone"></i> 0968 351 1100</p>
        </div>

        <div class="about-header">
            <div class="title-container">
                <div class="title-con">
                    <div class="title">
                        <h1>About Us</h1> 
                    </div>
                    <p class="tagline">MaruHealth is the official web-based health services management system of Marulas 3S Health Center, dedicated to delivering efficient, accessible, and community-driven healthcare services to residents of Barangay Marulas.</p>
                </div>
            </div>

            <div class="container">
                <section class="mission-vision">
                    <div class="mission">
                        <img src="./images/mission-symbol.png" alt="">
                        <h2 class="title">Our Mission</h2>
                        <p class="content">To provide reliable, accessible, and efficient healthcare services that improve the well-being of Barangay Marulas residents through innovative solutions and community engagement.</p>
                    </div>
                    <hr>
                    <div class="vision">
                        <img src="./images/vision-symbol.png" alt="">
                        <h2 class="title">Our Vision</h2>
                        <p class="content">A healthy and empowered community where every resident of Barangay Marulas has easy access to quality healthcare services.</p>
                    </div>

                </section>

                <section class="about-content">
                    <div class="img-content">
                        <img src="images/3s logo.png" alt="">
                    </div>
                    <div class="text-content">
                        <h2>About 3S Health Center</h2>
                        <p>The 3S (Simple, Speed, Service) Program of Valenzuela City is a government initiative designed to provide fast, efficient, and accessible public services to residents. It focuses on simplifying processes, ensuring quick response times, and delivering high-quality service in various sectors, including healthcare. Through this approach, the Marulas 3S Health Center upholds these principles by streamlining medical services, minimizing waiting times, and prioritizing the well-being of the community with a people-centered healthcare system.</p>
                    </div>
                    
                </section>
                <section class="about-content phil">
                    <div class="text-content">
                        <h2>PhilHealth Support for 3S Health Centers</h2>
                        <p>3S Health Center is a PhilHealth-accredited facility dedicated to providing accessible and affordable healthcare to the community. As part of Valenzuela City's 3S (Simple, Speed, and Service) Health Centers, it ensures that residents can avail of PhilHealth-covered medical services, including free consultations, laboratory tests, and essential treatments. With this support, patients can receive quality healthcare while maximizing their PhilHealth benefits for outpatient services, maternity care, and other essential medical needs. The center remains committed to delivering efficient and people-centered healthcare, ensuring that every resident receives the medical attention they deserve.</p>
                    </div>
                    <div class="img-content">
                        <img src="images/philhealth_logo.png" alt="">
                    </div>
                </section>

            </div>
        </div>
    </div>

    <script>
        const banner = document.querySelector('.banner');
        const images = [
            'images/about-banner.jpg',
            'images/about-banner-2.jpg',
            'images/about-banner-3.jpg'
        ];

        let currentIndex = 0;

        // Show the first image immediately on load
        banner.style.backgroundImage = `url('${images[currentIndex]}')`;

        function changeBannerBackground() {
            currentIndex = (currentIndex + 1) % images.length;
            banner.style.backgroundImage = `url('${images[currentIndex]}')`;
        }

        // Change every 4 seconds
        setInterval(changeBannerBackground, 4000);
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
