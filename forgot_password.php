<?php
//forgot_password.php
session_start();
include 'config.php';
include 'email_function.php';

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier']);

    // Validate input
    if (empty($identifier)) {
        $error = "Please enter your email or phone number.";
    } else {
        // Check the users table for residents only
        $stmt = $conn->prepare("SELECT id, email, CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE (email = :identifier OR phone_number = :identifier) AND role = 'user'");
        $stmt->bindParam(':identifier', $identifier);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token in password_reset_tokens table
            $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, email, token, expires_at) VALUES (:user_id, :email, :token, :expires_at)");
            $stmt->execute([
                ':user_id' => $user['id'],
                ':email' => $user['email'],
                ':token' => $token,
                ':expires_at' => $expires_at
            ]);

            // Send reset email
            $reset_link = "http://maruhealth.site/reset_password.php?token=$token";
            $subject = "Password Reset Request";
            $recipient_name = $user['full_name'];
            $message = "
                <h2>Password Reset Request</h2>
                <p>Dear $recipient_name,</p>
                <p>We received a request to reset your password. Click the link below to reset it:</p>
                <p><a href='$reset_link'>Reset Password</a></p>
                <p>This link will expire in 1 hour. If you did not request a password reset, please ignore this email.</p>
                <p>Best regards,<br>Maru-Health Team</p>
            ";

            $email_result = sendEmail($user['email'], $recipient_name, $subject, $message);

            if ($email_result['success']) {
                $success = "A password reset link has been sent to your email.";
            } else {
                $error = "Failed to send reset link. Please try again later.";
            }
        } else {
            $error = "No resident account found with that email or phone number.";
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
    <title>Forgot Password</title>
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
                    <h3>Forgot Password</h3>
                    <p>Enter your email or phone number to receive a password reset link.</p>
                    <?php if ($error) { echo "<p class='error'>$error</p>"; } ?>
                    <?php if ($success) { echo "<p class='success'>$success</p>"; } ?>
                    <input type="text" name="identifier" placeholder="E-mail/Phone Number" required>
                    <button type="submit">Send Reset Link</button>
                    <p><a href="login.php">Back to Login</a></p>
                </form>
            </div>
        </div>
    </div>
</body>
</html>