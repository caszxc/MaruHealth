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

$announcement = null;
$announcement_id = $_GET['id'] ?? null;

if ($announcement_id) {
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE id = :id");
    $stmt->bindParam(':id', $announcement_id, PDO::PARAM_INT);
    $stmt->execute();
    $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch recent announcements
$recentStmt = $conn->prepare("SELECT * FROM announcements WHERE status = 'active' ORDER BY created_at DESC LIMIT 3");
$recentStmt->execute();
$recentPosts = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>


<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="stylesheet" href="css/view_announcement.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <title>Announcement</title>
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
    <!-- Announcement Section -->
    <section class="container">
        <div class="address-contact">
            <p><i class="fa fa-map-marker"></i> 3S Center Marulas, Market, Valenzuela, Metro Manila</p>
            <p><i class="fa fa-phone"></i> 0968 351 1100</p>
        </div>
        <div class="announcement">
            <p class="breadcrumb"><a href="index.php">Home</a> > <a href="announcements_list.php">Announcements</a> > <?= htmlspecialchars($announcement['title']) ?></p>
            <h2 class="title"><?= htmlspecialchars($announcement['title']) ?></h2>
            <div class="announcement-container">
                <?php if ($announcement): ?>
                    <div class="recent-posts">
                        <h3>Recent Post</h3>
                        <?php foreach ($recentPosts as $recent): ?>
                            <div class="post">
                            <img src="images/uploads/announcement_images/<?= !empty($recent['image']) ? htmlspecialchars($recent['image']) : 'default_announcement.png' ?>" alt="Post Image">
                            <div class="post-info">
                                    <div class="post-content">
                                        <p class="date"><?= date('M d, h:i A', strtotime($recent['created_at'])) ?></p>
                                        <h4><?= htmlspecialchars($recent['title']) ?></h4>
                                        <p class="announcement-content"><?= htmlspecialchars($recent['content']) ?></p>
                                    </div>
                                    <a href="view_announcement.php?id=<?= $recent['id'] ?>">Read More</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="main-post">
                        <p class="date"><?= date('M d, h:i A', strtotime($announcement['created_at'])) ?></p>
                        <img src="images/uploads/announcement_images/<?= !empty($announcement['image']) ? htmlspecialchars($announcement['image']) : 'default_announcement.png' ?>" alt="Main Image">
                        <h3><?= htmlspecialchars($announcement['title']) ?></h3>
                        <p class="content"><?= nl2br(htmlspecialchars($announcement['content'])) ?></p>
                    </div>
                <?php else: ?>
                    <p style="color: white;">Announcement not found.</p>
                <?php endif; ?>
            </div>
        </div>
        
    </section> 
</body>
</html>
