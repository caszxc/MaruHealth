<?php
// index.php
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
        // Invalid active_user_id, fall back to default or handle error
        $profilePic = 'images/uploads/profile_pictures/profile-placeholder.png';
    }
}

require_once "deletion_notice.php";

// Fetch active announcements
try {
    $stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name 
                            FROM announcements a 
                            LEFT JOIN users u ON u.id = a.id 
                            WHERE status = 'active' 
                            ORDER BY created_at DESC 
                            LIMIT 4"); // Fetch the latest 3 announcements
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

$bannerImages = [
    'images/about-banner.jpg',
    'images/about-banner-2.jpg',
    'images/calendar-banner.jpg',   // fallback / last one
];

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <title>Home</title>
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="images/3s logo.png">
            <div>
                <h1>
                    <span class="maruhealth">MaruHealth</span>
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

    <div class="banner">
        <!-- 1. SLIDES -->
        <?php foreach ($bannerImages as $i => $img): ?>
            <div class="slide" style="background-image:url('<?= htmlspecialchars($img) ?>');"
                data-index="<?= $i ?>"></div>
        <?php endforeach; ?>

        <div class="address-contact">
            <p><i class="fa fa-map-marker"></i> 3S Center Marulas, Market, Valenzuela, Metro Manila</p>
            <p><i class="fa fa-phone"></i> 0968 351 1100</p>
        </div>
        <div class="title-container">
            <div class="title">
                <p data-aos="fade-in" data-aos-delay="150">WELCOME TO</p>
                <br><h1 data-aos="fade-in" data-aos-delay="300">MARUHEALTH</h1> 
                <span class="tagline" data-aos="fade-in" data-aos-delay="600">Your Health, Our Priority Making Quality Care More Accessible in Barangay Marulas</span>
            </div>
            <div class="announcement-event-wrapper">
                <!-- Navigation Buttons (visible only on small screens) -->
                <button class="scroll-btn prev-btn" aria-label="Previous">&lt;</button>
                <button class="scroll-btn next-btn" aria-label="Next">&gt;</button>

                <div class="announcement-event">
                    <!-- 1. Announcement -->
                    <div class="announcement" data-aos="fade-up" data-aos-delay="600">
                        <h3>Latest Announcement</h3>
                        <?php if (!empty($announcements)): ?>
                            <?php $latest = $announcements[0]; ?>
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

                    <!-- Open Hours -->
                    <div class="open-hours" data-aos="fade-up" data-aos-delay="900">
                        <h3>Open Hours and Schedules</h3>
                        <div class="open-hours-banner">
                            <p style="text-align: center;">Barangay Marulas 3S Health Center is open Monday to Friday.<br><br>8:00AM - 6:00PM</p>
                        </div>
                    </div>

                    <!-- Event -->
                    <div class="event" data-aos="fade-up" data-aos-delay="750">
                        <h3 style="text-align: center; color: #800000;">Latest Event</h3>
                        <?php if (!empty($events)): ?>
                            <?php $latest = $events[0]; ?>
                            <div class="latest-event-banner">
                                <img src="images/uploads/event_images/<?= !empty($latest['image']) ? htmlspecialchars($latest['image']) : 'default_event.png' ?>" 
                                    alt="<?= htmlspecialchars($latest['title']) ?>" 
                                    class="event-image">
                                <div class="event-details">
                                    <h3 style="color: #800000;"><?= htmlspecialchars($latest['title']); ?></h3>
                                    <p><?= date("F j, Y", strtotime($latest['event_date'])); ?></p>
                                    <p><?= date("g:i A", strtotime($latest['start'])); ?> - <?= date("g:i A", strtotime($latest['end'])); ?></p>
                                    <p><?= htmlspecialchars($latest['venue']); ?></p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="latest-event-banner">
                                <p style="text-align: center;">No current events.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Services Section -->
    <section class="services" data-aos="fade-up">
        <h2>Services in Marulas 3S</h2>
        <div class="card-container">
            <?php if (!empty($services)): ?>
                <?php foreach ($services as $index => $service): ?>
                    <div class="service-card" 
                        data-aos="fade-up" 
                        data-aos-delay="<?= 100 + ($index * 100) ?>" 
                        data-aos-duration="700">
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
                            <h3><?= htmlspecialchars($announcement['title']) ?></h3>
                            <small><?= date('M d, h:i A', strtotime($announcement['created_at'])) ?></small>
                            <p><?= htmlspecialchars(substr($announcement['content'], 0, 130)) ?>...</p>
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
    <section class="events" data-aos="fade-in">
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
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-container">
            <div class="footer-logo-container">
                <div class="footer-logo-section">
                    <img src="images/3s logo.png" alt="3S Logo" class="footer-logo">
                    <h3>MaruHealth<br>Barangay Marulas<br>3S Health Station</h3>
                </div>
                <div class="footer-text">
                    <p><i class="fa fa-map-marker"></i> 3S Center Marulas, Market, Valenzuela, Metro Manila</p>
                    <p><i class="fa fa-phone"></i> 0968 351 1100</p>
                </div>
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
    /* ----------  BANNER SLIDER  ---------- */
    document.addEventListener('DOMContentLoaded', () => {
        const slides   = document.querySelectorAll('.banner .slide');
        const total    = slides.length;
        let   current  = 0;

        if (total <= 1) return;               // nothing to slide

        const fadeNext = () => {
            // hide current
            slides[current].style.opacity = '0';

            // next index
            current = (current + 1) % total;

            // show next
            slides[current].style.opacity = '1';
        };

        // start the loop
        let timer = setInterval(fadeNext, 7000);
    });

    document.addEventListener('DOMContentLoaded', () => {
        const container = document.querySelector('.announcement-event');
        const prevBtn   = document.querySelector('.prev-btn');
        const nextBtn   = document.querySelector('.next-btn');

        if (!container || !prevBtn || !nextBtn) return;

        const scrollAmount = container.clientWidth * 0.25;   // same as before

        /* ---------- BUTTONS ---------- */
        nextBtn.addEventListener('click', () => {
            container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        });

        prevBtn.addEventListener('click', () => {
            container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        });

        /* ---------- DISABLE BUTTONS AT ENDS ---------- */
        const updateButtons = () => {
            const atStart = container.scrollLeft <= 10;
            const atEnd   = container.scrollLeft >= (container.scrollWidth - container.clientWidth - 10);

            prevBtn.style.opacity = atStart ? '0.3' : '1';
            prevBtn.style.pointerEvents = atStart ? 'none' : 'auto';

            nextBtn.style.opacity = atEnd ? '0.3' : '1';
            nextBtn.style.pointerEvents = atEnd ? 'none' : 'auto';
        };
        container.addEventListener('scroll', updateButtons);

        /* ---------- START WITH EVENT IN THE CENTER (mobile only) ---------- */
        const startWithEventCentered = () => {
            if (window.innerWidth > 600) return;                 // only on mobile
            const eventCard = container.children[1];            // 2nd child = .event
            if (!eventCard) return;

            const cardLeft   = eventCard.offsetLeft;            // distance from left edge of container
            const cardWidth  = eventCard.offsetWidth;
            const viewWidth  = container.clientWidth;

            const targetScroll = cardLeft - (viewWidth - cardWidth) / 2;   // center it
            container.scrollTo({ left: targetScroll, behavior: 'smooth' });
        };

        // Run after a tiny delay so layout is ready
        setTimeout(startWithEventCentered, 100);
        window.addEventListener('resize', startWithEventCentered);
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
        AOS.init({
            duration: 800,          // Faster, snappier
            easing: 'ease-out-cubic',
            once: true,             // Animate only once
            mirror: false,
            offset: 120,            // Account for fixed nav (80px) + padding
            anchorPlacement: 'top-bottom',

            // Mobile: lighter animation
            // (AOS doesn't support media queries natively, so we adjust globally)
            // We'll handle mobile via CSS media query below
        });

        // Re-init AOS on window resize (for responsive cards)
        window.addEventListener('resize', () => {
            AOS.refresh();
        });
    </script>

</body>
</html>
