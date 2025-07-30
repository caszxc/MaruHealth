<?php
//reset_password.php
session_start();
include 'config.php';

$error = '';
$success = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    $error = "Invalid or missing token.";
} else {
    // Check if token is valid, not expired, and belongs to a resident
    $stmt = $conn->prepare("SELECT * FROM password_reset_tokens WHERE token = :token AND user_id IS NOT NULL AND expires_at > NOW()");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$token_data) {
        $error = "Invalid or expired token, or not associated with a resident account.";
    } elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate passwords
        if (empty($new_password) || empty($confirm_password)) {
            $error = "Please fill in both password fields.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            // Update resident password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :user_id");
            $stmt->execute([':password' => $hashed_password, ':user_id' => $token_data['user_id']]);

            // Delete used token
            $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = :token");
            $stmt->bindParam(':token', $token);
            $stmt->execute();

            $success = "Your password has been reset successfully. <a href='login.php'>Log in</a> now.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <title>Reset Password</title>
</head>
<body>
    <div class="main-login">
        <div class="left-panel">
            <img src="images/3s logo.png" alt="Logo">
            <h1>Maru-Health<br>Barangay Marulas<br>3S Health Station</h1>
        </div>

        <div class="right-panel">
            <div class="login-box">
                <form method="POST" class="login-form">
                    <h3>Reset Password</h3>
                    <p>Enter a new password for your resident account.</p>
                    <?php if ($error) { echo "<p class='error'>$error</p>"; } ?>
                    <?php if ($success) { echo "<p class='success'>$success</p>"; } ?>
                    <?php if (!$error && !$success) { ?>
                        <input type="password" name="new_password" placeholder="New Password" required>
                        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                        <button type="submit">Reset Password</button>
                    <?php } ?>
                    <p><a href="login.php">Back to Login</a></p>
                </form>
            </div>
        </div>
    </div>
</body>
</html>