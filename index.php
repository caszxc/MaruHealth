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

// Fetch active announcements
try {
    $stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name 
                            FROM announcements a 
                            LEFT JOIN users u ON u.id = a.id 
                            WHERE status = 'active' 
                            ORDER BY created_at DESC 
                            LIMIT 3"); // Fetch the latest 3 announcements
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching announcements: " . $e->getMessage());
}

// Fetch the admin's name
$adminStmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE role = 'admin' LIMIT 1");
$adminStmt->execute();
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

// Default to "Admin" if no admin is found
$adminName = $admin ? $admin['first_name'] . ' ' . $admin['last_name'] : 'Admin';

// Fetch active events
try {
    $stmt = $conn->prepare("SELECT * FROM events ORDER BY event_date DESC LIMIT 4"); // Get the latest 4 events
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching events: " . $e->getMessage());
}

try {
    // Prepare and execute the query to fetch title (name), icon_path, and intro from services table
    $stmt = $conn->prepare("SELECT name, icon_path, intro FROM services");
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching services: " . $e->getMessage());
}

?>


<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Home</title>
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

    <div class="banner">
        <div class="address-contact">
            <p><i class="fa fa-map-marker"></i> 3S Center Marulas, Market, Valenzuela, Metro Manila</p>
            <p><i class="fa fa-phone"></i> 0968 351 1100</p>
        </div>
        <div class="title-container">
            <div class="title">
                <p data-aos="fade-in" data-aos-delay="150">WELCOME TO</p>
                <br><h1 data-aos="fade-in" data-aos-delay="300">MARU-HEALTH</h1> 
                <span class="tagline" data-aos="fade-in" data-aos-delay="600">Your Health, Our Priority Making Quality Care More Accessible in Barangay Marulas</span>
            </div>
            <div class="announcement-event">
                <div class="announcement" data-aos="fade-up" data-aos-delay="600">
                    <h3 style="text-align: center; color: #800000;">Latest Announcement</h3>
                    <?php if (!empty($announcements)): ?>
                        <?php 
                            $latest = $announcements[0]; // Get only the first announcement 
                        ?>
                        <div class="latest-announcement-banner">
                        <img src="images/uploads/announcement_images/<?= !empty($latest['image']) ? htmlspecialchars($latest['image']) : 'default_announcement.png' ?>" 
                        alt="<?= htmlspecialchars($latest['title']) ?>" 
                            class="announcement-image">
                            <div class="announcement-details">
                                <p><strong><?= htmlspecialchars($latest['title']); ?></strong></p>
                                <p><?= date("F j, Y", strtotime($latest['created_at'])); ?></p>
                                <a href="view_announcement.php?id=<?= $latest['id'] ?>" class="read-more">Read More</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="latest-announcement-banner">
                            <p style="text-align: center;">No current announcements.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="event" data-aos="fade-up" data-aos-delay="750">
                    <h3 style="text-align: center; color: #800000;">Latest Event</h3>
                    <?php if (!empty($events)): ?>
                        <?php 
                            $latest = $events[0]; // Get only the first event 
                        ?>
                        <div class="latest-event-banner">
                            <img src="images/uploads/event_images/<?= !empty($latest['image']) ? htmlspecialchars($latest['image']) : 'default_event.png' ?>" 
                            alt="<?= htmlspecialchars($latest['title']) ?>" 
                            class="event-image">
                            <div class="event-details">
                                <h3 style="color: #800000;"><?= htmlspecialchars($latest['title']); ?></h3>
                                <p><?= date("F j, Y", strtotime($latest['event_date'])); ?> - <?= date("g:i A", strtotime($latest['start'])); ?> - <?= date("g:i A", strtotime($latest['end'])); ?></p>
                                <p></p>
                                <p><?= htmlspecialchars($latest['venue']); ?></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="latest-event-banner">
                            <p style="text-align: center;">No current events.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="open-hours" data-aos="fade-up" data-aos-delay="900">
                    <h3 style="text-align: center; color: #800000;">Open Hours and Schedules</h3>
                    <p style="text-align: center;">Barangay Marulas 3S Health Center is open Monday to Friday.<br><br>8:00AM - 6:00PM</p>
                </div>
            </div>
        </div>
    </div>
    <!-- Services Section -->
    <section class="services" data-aos="fade-up">
        <h2>Our Services</h2>
        <div class="card-container">
            <?php if (!empty($services)): ?>
                <?php foreach ($services as $service): ?>
                    <div class="service-card">
                        <img src="<?= htmlspecialchars($service['icon_path']) ?>" alt="<?= htmlspecialchars($service['name']) ?>">
                        <h3><?= htmlspecialchars($service['name']) ?></h3>
                        <p><?= htmlspecialchars($service['intro']) ?></p>
                        <a href="services.php?service=<?= urlencode($service['name']) ?>" class="view-btn">Read More</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No services available at the moment.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Announcements Section -->
    <section class="announcements" data-aos="fade-in">
        <h2>Announcements</h2>
        <div class="announcement-grid">
            <?php if (empty($announcements)): ?>
                <p class="no-content-message">No active announcements at the moment.</p>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-card">
                        <div class="announcement-img">
                            <img src="images/uploads/announcement_images/<?= !empty($announcement['image']) ? htmlspecialchars($announcement['image']) : 'default_announcement.png' ?>" alt="Announcement Image">
                        </div>
                        <div class="announcement-info">
                            <h3><?= $announcement['title'] ?></h3>
                            <small><?= date('M d, h:i A', strtotime($announcement['created_at'])) ?></small>
                            <p><?= substr($announcement['content'], 0, 130) ?>...</p>
                            <a href="view_announcement.php?id=<?= $announcement['id'] ?>" class="read-more">Read More</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="view-more-wrapper">
            <a href="announcements_list.php" class="view-more-btn">View More</a>
        </div>
    </section>


    <!-- Events Section -->
    <section class="events">
        <h2>Upcoming Events</h2>
        <div class="events-container">
            <?php if (empty($events)): ?>
                <p class="no-content-message">No upcoming events at the moment.</p>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <div class="event-box">
                        <img src="images/uploads/event_images/<?= !empty($event['image']) ? htmlspecialchars($event['image']) : 'default_event.png' ?>" 
                        alt="<?= htmlspecialchars($event['title']) ?>" 
                        class="event-image">
                        <div class="event-details">
                            <h3><?= htmlspecialchars($event['title']); ?></h3>
                            <p><strong>Date:</strong> <?= date("F j, Y", strtotime($event['event_date'])); ?></p>
                            <p><strong>Time:</strong> <?= date("g:i A", strtotime($event['start'])); ?> - <?= date("g:i A", strtotime($event['end'])); ?></p>
                            <p><strong>Venue:</strong> <?= htmlspecialchars($event['venue']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="view-more-wrapper">
            <a href="events_list.php" class="view-more-btn">View More</a>
        </div>
    </section>>

    <!-- Footer  -->
    <footer>
        <div class="footer-container">
            <div class="footer-logo-container">
                <div class="footer-logo-section">
                    <img src="images/3s logo.png" alt="3S Logo" class="footer-logo">
                    <h3>Maru-Health<br>Barangay Marulas<br>3S Health Station</h3>
                </div>
                <div class="footer-text">
                    <p><i class="fa fa-map-marker"></i> 3S Center Marulas, Market, Valenzuela, Metro Manila</p>
                    <p><i class="fa fa-phone"></i> 0968 351 1100</p>
                </div>
            </div>
            

            <div class="footer-links">
                <h4>ABOUT US</h4>
                <ul>
                <li><a href="#">Mission and Vision</a></li>
                <li><a href="#">About 3S Health Center</a></li>
                <li><a href="#">PhilHealth Support for 3S Health Centers</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>OUR SERVICES</h4>
                <ul>
                <li><a href="#">Check Up</a></li>
                <li><a href="#">Vaccination</a></li>
                <li><a href="#">Family Planning</a></li>
                <li><a href="#">Dental Care</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>© 2025 3S Barangay Marulas. All Rights Reserved.</p>
            <div class="footer-policy">
            <a href="privacy_policy.php">Privacy & Policy</a> |
            <a href="terms_condition.php">Terms & Conditions</a>
            </div>
        </div>
    </footer>



    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 1000, // duration of animation in ms
            once: true      // whether animation should happen only once
        });

        // Smooth Scroll to Section
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>

</body>
</html>
