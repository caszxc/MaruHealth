<?php
session_start();
include 'config.php';
include 'settings.php';

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

$announcementsPerPage = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $announcementsPerPage;

// Get total announcements count
$totalStmt = $conn->prepare("SELECT COUNT(*) FROM announcements WHERE status = 'active'");
$totalStmt->execute();
$totalAnnouncements = $totalStmt->fetchColumn();
$totalPages = ceil($totalAnnouncements / $announcementsPerPage);


$stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name 
                        FROM announcements a 
                        LEFT JOIN users u ON u.id = a.id 
                        WHERE a.status = 'active' 
                        ORDER BY a.created_at DESC 
                        LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $announcementsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);


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
    <link rel="stylesheet" href="css/announcements_list.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Announcements</title>
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
    <!-- Announcements Section -->
    <section class="container">
        <div class="address-contact">
            <p><i class="fa fa-map-marker"></i> <?= htmlspecialchars($contact_address) ?></p>
            <p><i class="fa fa-phone"></i> <?= htmlspecialchars($contact_phone) ?></p>
        </div>
        <div class="announcements">
            <p class="breadcrumb"><a href="index.php">Home</a> > Announcements</p>
            <h2 class="title">Announcements</h2>
            <div class="announcement-content">
                <div class="announcement-container">
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
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="page-btn">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="page-btn">Next</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>    
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
