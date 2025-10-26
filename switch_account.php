<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch primary user details
$sql = "SELECT id, first_name, last_name, middle_name, profile_picture FROM users WHERE id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$primary_user = $stmt->fetch(PDO::FETCH_ASSOC);

$primary_profile_pic = !empty($primary_user['profile_picture']) ? $primary_user['profile_picture'] : 'images/uploads/profile_pictures/profile-placeholder.png';

// Fetch dependents
$sql = "SELECT u.id, u.first_name, u.last_name, u.middle_name, u.profile_picture, dr.relationship 
        FROM users u 
        JOIN dependent_relationships dr ON u.id = dr.dependent_user_id 
        WHERE dr.primary_user_id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$dependents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle account switch
if (isset($_GET['switch_to'])) {
    $switch_to_id = filter_input(INPUT_GET, 'switch_to', FILTER_VALIDATE_INT);
    
    // Verify if the user is either the primary user or a dependent
    if ($switch_to_id == $user_id) {
        $_SESSION['active_user_id'] = $user_id;
        header("Location: profile.php");
        exit();
    }
    
    $stmt = $conn->prepare("SELECT 1 FROM dependent_relationships WHERE primary_user_id = :primary_id AND dependent_user_id = :dep_id");
    $stmt->execute([
        ':primary_id' => $user_id,
        ':dep_id' => $switch_to_id
    ]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['active_user_id'] = $switch_to_id;
        header("Location: profile.php");
        exit();
    } else {
        $error = "Invalid account switch request.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Switch Account</title>
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="stylesheet" href="css/switch_account.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
    </nav>

    <div class="switch-account-container">
        <div class="switch-header">
            <h2 class="page-title">Switch Account</h2>
            <p class="page-subtitle">Choose which account to use. Tap or click an account below to switch between the primary and dependent profiles.</p>
        </div>
        <?php if (isset($error)): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <div class="account-list">
            <!-- Primary User -->
            <div class="account-card" onclick="window.location.href='switch_account.php?switch_to=<?= $primary_user['id'] ?>'">
                <img src="<?= htmlspecialchars($primary_profile_pic) ?>" alt="Profile Picture">
                <div>
                    <p class="name"><?= htmlspecialchars($primary_user['first_name'] . ' ' . $primary_user['middle_name'] . ' ' . $primary_user['last_name']) ?></p>
                    <p>Primary Account</p>
                </div>
            </div>
            <!-- Dependents -->
            <?php foreach ($dependents as $dependent): ?>
                <div class="account-card" onclick="window.location.href='switch_account.php?switch_to=<?= $dependent['id'] ?>'">
                    <img src="<?= htmlspecialchars($dependent['profile_picture'] ?? 'images/uploads/profile_pictures/profile-placeholder.png') ?>" alt="Profile Picture">
                    <div>
                        <p class="name"><?= htmlspecialchars($dependent['first_name'] . ' ' . $dependent['middle_name'] . ' ' . $dependent['last_name']) ?></p>
                        <p><?= htmlspecialchars($dependent['relationship']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>