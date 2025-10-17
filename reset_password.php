<?php
//reset_password.php
session_start();
include 'config.php';

$error = '';
$success = '';

if (!isset($_SESSION['verified_email'])) {
    header("Location: forgot_password.php");
    exit;
}

$email = $_SESSION['verified_email'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in both password fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = :password WHERE email = :email");
        $stmt->execute([':password' => $hashed_password, ':email' => $email]);

        // Delete used code
        $conn->prepare("DELETE FROM password_reset_tokens WHERE email = :email")->execute([':email' => $email]);

        // Clear session
        unset($_SESSION['verified_email']);
        unset($_SESSION['reset_email']);

        $success = "Your password has been reset successfully. <a href='login.php'>Log in</a> now.";
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
                        <div></div>
                        <input type="password" name="new_password" placeholder="New Password" required>
                        <small class="field-hint">At least 8 characters with uppercase, lowercase, and numbers</small>
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