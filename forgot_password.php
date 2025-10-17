<?php
//forgot_password.php
session_start();
include 'config.php';
include 'email_function.php';

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier']);

    if (empty($identifier)) {
        $error = "Please enter your email or phone number.";
    } else {
        $stmt = $conn->prepare("SELECT id, email, CONCAT(first_name, ' ', last_name) AS full_name 
                                FROM users 
                                WHERE (email = :identifier OR phone_number = :identifier) 
                                AND role = 'user'");
        $stmt->bindParam(':identifier', $identifier);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Generate 6-digit code
            $reset_code = random_int(100000, 999999);
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            // Delete any existing codes for this user
            $conn->prepare("DELETE FROM password_reset_tokens WHERE email = :email")->execute([':email' => $user['email']]);

            // Store new reset code
            $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, email, code, expires_at)
                                    VALUES (:user_id, :email, :code, :expires_at)");
            $stmt->execute([
                ':user_id' => $user['id'],
                ':email' => $user['email'],
                ':code' => $reset_code,
                ':expires_at' => $expires_at
            ]);

            // Send email
            $subject = "Your Password Reset Code";
            $recipient_name = $user['full_name'];
            $message = "
                <h2>Password Reset Code</h2>
                <p>Dear $recipient_name,</p>
                <p>Your password reset code is: <strong>$reset_code</strong></p>
                <p>This code will expire in 10 minutes.</p>
                <p>If you did not request a password reset, please ignore this message.</p>
                <p>Best regards,<br>Maru-Health Team</p>
            ";

            $email_result = sendEmail($user['email'], $recipient_name, $subject, $message);

            if ($email_result['success']) {
                $_SESSION['reset_email'] = $user['email'];
                header("Location: verify_code.php");
                exit;
            } else {
                $error = "Failed to send reset code. Please try again later.";
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
            <div>
                <h1>Maru-Health</h1>
                <p>Barangay Marulas 3S Health Station</p>
            </div>
        </div>

        <div class="right-panel">
            <div class="login-box">
                <form method="POST" class="login-form">
                    <div>
                        <h3>Forgot Password</h3>
                        <p>Enter your email or phone number to receive a password reset code.</p>
                        
                        <div class="field-container">
                            <?php if ($success) { echo "<p class='success'>$success</p>"; } ?>
                            <input type="text" name="identifier" placeholder="E-mail/Phone Number" required>
                            <?php if ($error) { echo "<p class='error'>$error</p>"; } ?>
                        </div>
                        
                        <button type="submit">Send Reset Code</button>

                        <p id="link"><a href="login.php">Back to Login</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>