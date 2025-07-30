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
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="stylesheet" href="css/about_us.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>About Us</title>
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="images/3s logo.png">
            <p>Maru-Health <br> Barangay Marulas 3S <br> Health Station</p>
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
        <div class="title-container">
            <div class="title">
                <h1 data-aos="fade-in" data-aos-delay="150">About Us</h1> 
            </div>
            <p class="tagline" data-aos="fade-up" data-aos-delay="300">MaruHealth is the official web-based health services management system of Marulas 3S Health Center, dedicated to delivering efficient, accessible, and community-driven healthcare services to residents of Barangay Marulas.</p>
        </div>
    </div>


    <div class="container">
        <section class="mission-vision" data-aos="fade-right">
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

        <section class="about-content" data-aos="fade-left">
            <div class="img-content">
                <img src="images/3s logo.png" alt="">
            </div>
            <div class="text-content">
                <h2>About 3S Health Center</h2>
                <p>The 3S (Simple, Speed, Service) Program of Valenzuela City is a government initiative designed to provide fast, efficient, and accessible public services to residents. It focuses on simplifying processes, ensuring quick response times, and delivering high-quality service in various sectors, including healthcare. Through this approach, the Marulas 3S Health Center upholds these principles by streamlining medical services, minimizing waiting times, and prioritizing the well-being of the community with a people-centered healthcare system.
                </p>
            </div>
            
        </section>
        <section class="about-content" data-aos="fade-right">
            <div class="text-content">
                <h2>PhilHealth Support for 3S Health Centers</h2>
                <p>3S Health Center is a PhilHealth-accredited facility dedicated to providing accessible and affordable healthcare to the community. As part of Valenzuela City's 3S (Simple, Speed, and Service) Health Centers, it ensures that residents can avail of PhilHealth-covered medical services, including free consultations, laboratory tests, and essential treatments. With this support, patients can receive quality healthcare while maximizing their PhilHealth benefits for outpatient services, maternity care, and other essential medical needs. The center remains committed to delivering efficient and people-centered healthcare, ensuring that every resident receives the medical attention they deserve.
                </p>
            </div>
            <div class="img-content">
                <img src="images/philhealth_logo.png" alt="">
            </div>
        </section>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 1000, // duration of animation in ms
            once: true      // whether animation should happen only once
        });
    </script>

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

        // Change every 5 seconds
        setInterval(changeBannerBackground, 4000);
    </script>

</body>
</html>
