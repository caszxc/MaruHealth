<?php
// verify_code.php
session_start();
include 'config.php';
include 'settings.php';

$error = '';
$success = '';

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = trim($_POST['code']);
    $email = $_SESSION['reset_email'];

    $stmt = $conn->prepare("SELECT * FROM password_reset_tokens 
                            WHERE email = :email AND code = :code AND expires_at > NOW()");
    $stmt->execute([':email' => $email, ':code' => $code]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($token_data) {
        $_SESSION['verified_email'] = $email;
        header("Location: reset_password.php");
        exit;
    } else {
        $error = "Invalid or expired reset code.";
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
    <link rel="icon" href="<?= $logo_url ?>" type="image/x-icon">
    <title>Verify Reset Code</title>
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
                            <h3>Verify Reset Code</h3>
                            <p>Enter the 6-digit code sent to your email.</p>

                            <div class="field-container">
                                <input type="text" name="code" placeholder="Enter Code" required maxlength="6">
                                <?php if ($error) echo "<p class='error'>$error</p>"; ?>
                            </div>
                            
                            <button type="submit">Verify Code</button>
                            
                            <p id="link"><a href="forgot_password.php">Back</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
