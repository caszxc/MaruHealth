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

$eventsPerPage = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $eventsPerPage;

// Count total events
$totalStmt = $conn->prepare("SELECT COUNT(*) FROM events");
$totalStmt->execute();
$totalEvents = $totalStmt->fetchColumn();
$totalPages = ceil($totalEvents / $eventsPerPage);

// Fetch events with LIMIT and OFFSET
try {
    $stmt = $conn->prepare("SELECT * FROM events ORDER BY event_date DESC LIMIT :limit OFFSET :offset"); 
    $stmt->bindValue(':limit', $eventsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching events: " . $e->getMessage());
}

?>


<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="stylesheet" href="css/events_list.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Events</title>
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
    <!-- Events Section -->
    <section class="container">
        <div class="address-contact">
            <p><i class="fa fa-map-marker"></i> 3S Center Marulas, Market, Valenzuela, Metro Manila</p>
            <p><i class="fa fa-phone"></i> 0968 351 1100</p>
        </div>
        <div class="events">
            <p class="breadcrumb"><a href="index.php">Home</a> > Events</p>
            <h2 class="title">Events</h2>
            <div class="event-content">
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
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php
                        $range = 2; // how many pages to show before/after current page

                        if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="page-btn">Previous</a>
                        <?php else: ?>
                            <a class="page-btn disabled">Previous</a>
                        <?php endif; ?>

                        <?php if ($page > $range + 2): ?>
                            <a href="?page=1" class="page-btn">1</a>
                            <span class="dots">...</span>
                        <?php endif; ?>

                        <?php
                        for ($i = max(1, $page - $range); $i <= min($totalPages, $page + $range); $i++): ?>
                            <a href="?page=<?= $i ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages - $range - 1): ?>
                            <span class="dots">...</span>
                            <a href="?page=<?= $totalPages ?>" class="page-btn"><?= $totalPages ?></a>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="page-btn">Next</a>
                        <?php else: ?>
                            <a class="page-btn disabled">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</body>
</html>
