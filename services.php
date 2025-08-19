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

// Determine active service
$selectedService = $_GET['service'] ?? '';

$serviceStmt = $conn->prepare("SELECT * FROM services WHERE name = :name");
$serviceStmt->bindParam(':name', $selectedService);
$serviceStmt->execute();
$service = $serviceStmt->fetch(PDO::FETCH_ASSOC);

$subServices = [];

if ($service) {
    $subStmt = $conn->prepare("SELECT id, name FROM sub_services WHERE service_id = :service_id");
    $subStmt->execute(['service_id' => $service['id']]);
    $subs = $subStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subs as $sub) {
        $schedStmt = $conn->prepare("SELECT day_of_schedule FROM schedules WHERE sub_service_id = :sub_service_id");
        $schedStmt->execute(['sub_service_id' => $sub['id']]);
        $schedules = $schedStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $subServices[] = [
            'name' => $sub['name'],
            'schedule' => $schedules
        ];
    }
    
    // Fetch service images for slideshow
    $imageStmt = $conn->prepare("SELECT image_path FROM service_images WHERE service_id = :service_id");
    $imageStmt->execute(['service_id' => $service['id']]);
    $serviceImages = $imageStmt->fetchAll(PDO::FETCH_COLUMN);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="stylesheet" href="css/services.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Services</title>
    <style>
        /* Slideshow container */
        .slideshow-container {
            position: relative;
            max-width: 100%;
            margin-bottom: 20px;
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Service images */
        .service-slide {
            display: none;
            width: 100%;
            height: 500px;
            object-fit: cover;
        }

        /* Active slide */
        .active-slide {
            display: block;
        }

        /* Next & previous buttons */
        .prev, .next {
            cursor: pointer;
            position: absolute;
            top: 50%;
            width: auto;
            margin-top: -22px;
            padding: 16px;
            color: white;
            font-weight: bold;
            font-size: 18px;
            transition: 0.6s ease;
            border-radius: 0 3px 3px 0;
            user-select: none;
            background-color: rgba(0,0,0,0.4);
        }

        /* Position the "next" button to the right */
        .next {
            right: 0;
            border-radius: 3px 0 0 3px;
        }

        /* On hover, add a black background color with a little bit see-through */
        .prev:hover, .next:hover {
            background-color: rgba(0,0,0,0.8);
        }

        /* Dots/bullets/indicators */
        .dots-container {
            text-align: center;
            margin-top: -30px;
            position: relative;
            z-index: 1;
        }

        .dot {
            cursor: pointer;
            height: 12px;
            width: 12px;
            margin: 0 4px;
            background-color: #bbb;
            border-radius: 50%;
            display: inline-block;
            transition: background-color 0.6s ease;
        }

        .active-dot, .dot:hover {
            background-color: #717171;
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
    <!-- Services Section -->
    <section class="container">
        <div class="address-contact">
            <p><i class="fa fa-map-marker"></i> 3S Center Marulas, Market, Valenzuela, Metro Manila</p>
            <p><i class="fa fa-phone"></i> 0968 351 1100</p>
        </div>
        <div class="services">
            <p class="breadcrumb"><a href="index.php">Home</a> > <?= htmlspecialchars($service['name'] ?? 'Services') ?></p>
            <h2 class="title"><?= htmlspecialchars($service['name'] ?? 'Services') ?></h2>
            <div class="services-container">
                <div class="schedule">
                    <!-- Schedule -->
                    <div class="service-schedule">
                        <h4 class="schedule-title">Schedule</h4>
                        <div class="schedule-table">
                            <div class="schedule-header">
                                <span class="header">Services</span>
                                <span class="header">Day</span>
                            </div>
                            <?php if (!empty($subServices)): ?>
                                <?php foreach ($subServices as $i => $sub): ?>
                                    <?php foreach ($sub['schedule'] as $index => $day): ?>
                                        <div class="schedule-row<?= $index === 0 ? ' first-row' : '' ?>">
                                            <?php if ($index === 0): ?>
                                                <div class="service-name"><?= htmlspecialchars($sub['name']) ?></div>
                                            <?php else: ?>
                                                <div class="service-name empty"></div>
                                            <?php endif; ?>
                                            <div class="schedule-day"><?= htmlspecialchars($day) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if ($i < count($subServices) - 1): ?>
                                        <hr class="schedule-divider">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No schedule available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Content Area -->
                <div class="service-content">
                    <!-- Image Slideshow -->
                    <div class="slideshow-container">
                        <?php if (!empty($serviceImages)): ?>
                            <?php foreach ($serviceImages as $index => $imagePath): ?>
                                <img src="<?= htmlspecialchars($imagePath) ?>" 
                                     class="service-slide <?= $index === 0 ? 'active-slide' : '' ?>" 
                                     alt="<?= htmlspecialchars($service['name']) ?> Image">
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Default image if no service images are available -->
                            <img src="images/about-img.png" class="service-slide active-slide" alt="Default Service Image">
                        <?php endif; ?>
                        
                        <!-- Next and previous buttons -->
                        <?php if (!empty($serviceImages) && count($serviceImages) > 1): ?>
                            <a class="prev" onclick="plusSlides(-1)">&#10094;</a>
                            <a class="next" onclick="plusSlides(1)">&#10095;</a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Dots/circles -->
                    <?php if (!empty($serviceImages) && count($serviceImages) > 1): ?>
                        <div class="dots-container">
                            <?php foreach ($serviceImages as $index => $imagePath): ?>
                                <span class="dot <?= $index === 0 ? 'active-dot' : '' ?>" onclick="currentSlide(<?= $index + 1 ?>)"></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="service-description">
                        <h3><?= htmlspecialchars($service['name'] ?? 'Service') ?></h3>
                        <p><?= $service['description'] ?? 'No description available.' ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <script>
        let slideIndex = 1;
        showSlides(slideIndex);
        
        // Auto cycle through slides every 5 seconds
        let slideInterval = setInterval(() => {
            plusSlides(1);
        }, 5000);
        
        function plusSlides(n) {
            showSlides(slideIndex += n);
        }
        
        function currentSlide(n) {
            showSlides(slideIndex = n);
        }
        
        function showSlides(n) {
            let i;
            let slides = document.getElementsByClassName("service-slide");
            let dots = document.getElementsByClassName("dot");
            
            if (slides.length === 0) return;
            
            // Reset to beginning if we've gone past the end
            if (n > slides.length) {slideIndex = 1}
            
            // Go to end if we've gone before the beginning
            if (n < 1) {slideIndex = slides.length}
            
            // Hide all slides
            for (i = 0; i < slides.length; i++) {
                slides[i].classList.remove("active-slide");
            }
            
            // Remove active class from all dots
            for (i = 0; i < dots.length; i++) {
                dots[i].classList.remove("active-dot");
            }
            
            // Show the current slide and activate its dot
            slides[slideIndex-1].classList.add("active-slide");
            if (dots.length > 0) {
                dots[slideIndex-1].classList.add("active-dot");
            }
            
            // Reset interval on manual navigation
            clearInterval(slideInterval);
            slideInterval = setInterval(() => {
                plusSlides(1);
            }, 5000);
        }
    </script>
</body>
</html>