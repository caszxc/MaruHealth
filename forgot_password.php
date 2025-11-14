<?php
// forgot_password.php
session_start();
include 'config.php';
include 'settings.php';
include 'email_function.php';
include 'semaphore_sms.php'; // <-- Add this line

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier']);

    if (empty($identifier)) {
        $error = "Please enter your email or phone number.";
    } else {
        // Detect if input is email or phone
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        $isPhone = preg_match('/^(09|\+639|639)\d{9}$/', $identifier);

        if (!$isEmail && !$isPhone) {
            $error = "Please enter a valid email address or Philippine mobile number (e.g., 09123456789).";
        } else {
            // Search user by email OR phone number
            $sql = "SELECT id, email, phone_number, CONCAT(first_name, ' ', last_name) AS full_name 
                    FROM users 
                    WHERE " . ($isEmail ? "email = :identifier" : "phone_number = :identifier") . "
                    AND role = 'user'";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':identifier', $identifier);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = "No resident account found with that " . ($isEmail ? "email" : "phone number") . ".";
            } else {
                // Generate 6-digit reset code
                $reset_code = random_int(100000, 999999);
                $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                // Delete old tokens for this email
                $conn->prepare("DELETE FROM password_reset_tokens WHERE email = :email")
                     ->execute([':email' => $user['email']]);

                // Save new token
                $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, email, code, expires_at)
                                        VALUES (:user_id, :email, :code, :expires_at)");
                $stmt->execute([
                    ':user_id' => $user['id'],
                    ':email' => $user['email'],
                    ':code' => $reset_code,
                    ':expires_at' => $expires_at
                ]);

                $sent = false;
                $method = '';

                if ($isEmail) {
                    // === SEND VIA EMAIL ===
                    $subject = "Your Password Reset Code";
                    $message = "
                        <h2>Password Reset Request</h2>
                        <p>Hi <strong>{$user['full_name']}</strong>,</p>
                        <p>Your verification code is:</p>
                        <h1 style='font-size:36px;letter-spacing:5px;'>$reset_code</h1>
                        <p>This code will expire in <strong>10 minutes</strong>.</p>
                        <p>If you didn't request this, please ignore this message.</p>
                        <hr>
                        <small>MaruHealth - Barangay Marulas 3S Health Station</small>
                    ";

                    $email_result = sendEmail($user['email'], $user['full_name'], $subject, $message);
                    $sent = $email_result['success'];
                    $method = 'email';
                } else {
                    // === SEND VIA SMS USING SEMAPHORE ===
                    $cleanPhone = convertTo09($user['phone_number']);
                    $smsMessage = "MaruHealth Password Reset\n\nHi {$user['full_name']},\nYour verification code is: $reset_code\n\nValid for 10 minutes only.\nDo not share this code.";

                    $sms_result = sendSMS($user['phone_number'], $smsMessage);
                    $sent = $sms_result['success'];
                    $method = "SMS (sent to $cleanPhone)";
                }

                if ($sent) {
                    $_SESSION['reset_email'] = $user['email']; // Still store email as identifier for verification step
                    $_SESSION['flash_message'] = "Verification code sent via $method!";
                    header("Location: verify_code.php");
                    exit();
                } else {
                    $error = "Failed to send reset code. Please try again later.";
                    if (!$isEmail && isset($sms_result['error'])) {
                        error_log("SMS Send Failed: " . $sms_result['error']);
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/forgot_password.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <title>Forgot Password</title>
</head>
<body>
    <div class="main-login">
        <div class="login-con <?php echo !empty($error) ? 'has-error' : 'animate'; ?>">
            <div class="left-panel">
                <img src="<?= $logo_url ?>" alt="Logo">
                <div>
                    <h1><?= htmlspecialchars($site_name) ?></h1>
                    <p>Barangay Marulas 3S Health Station</p>
                </div>
            </div>

            <div class="right-panel">
                <div class="login-box">
                    <form method="POST" class="login-form">
                        <div class="login-container">
                            <h3>Forgot Password</h3>
                            <p>Enter your registered <strong>email</strong> or <strong>phone number</strong> to receive a reset code.</p>
                            <div class="field-container">
                                <input type="text" name="identifier" placeholder="Email or Phone Number (09xxxxxxxxx)" 
                                       value="<?= htmlspecialchars($identifier ?? '') ?>" required>
                                <?php if ($error): ?>
                                    <p class="error"><?= $error ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <button type="submit">Send Reset Code</button>

                            <p id="link"><a href="login.php">Back to Login</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>