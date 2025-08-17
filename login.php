<?php
// login.php (Residents only)
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier']); // email or phone for residents
    $password = $_POST['password'];

    // For residents, check the users table
    $stmt = $conn->prepare("SELECT * FROM users WHERE (email = :identifier OR phone_number = :identifier) AND role = 'user'");
    $stmt->bindParam(':identifier', $identifier);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Store user details in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['phone'] = $user['phone_number'];
        $_SESSION['role'] = 'user';
        $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];

        // Redirect to user dashboard
        header("Location: index.php");
        exit();
    } else {
        $error = "Invalid resident credentials!";
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
    <title>Resident Login</title>
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
                    <h3 id="login-title">Login as a Resident</h3>
                    <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
                    <input type="text" name="identifier" placeholder="E-mail/Phone Number" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <a href="forgot_password.php" class="forgot-link" id="forgot-link">Forgot password?</a>
                    <button type="submit">Log In</button>
                    <div id="signup-link">
                        <p id="link">Don't have an account? <a href="register.php">Sign Up</a></p>
                        <p id="link">or <br><a href="index.php">Stay signed out</a></p>
                    </div>     
                </form>
            </div>
        </div>
    </div>
</body>
</html>
