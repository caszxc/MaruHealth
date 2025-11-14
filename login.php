<?php
// login.php
session_start();
include 'config.php';
include 'settings.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier']); // email, phone, or username
    $password = $_POST['password'];

    // First, check the users table for residents
    $stmt = $conn->prepare("SELECT * FROM users WHERE (email = :identifier OR phone_number = :identifier) AND role = 'user'");
    $stmt->bindParam(':identifier', $identifier);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Store resident details in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['phone'] = $user['phone_number'];
        $_SESSION['role'] = 'user';
        $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];

        // Check for dependents
        $stmt = $conn->prepare("SELECT COUNT(*) FROM dependent_relationships WHERE primary_user_id = :user_id");
        $stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
        $stmt->execute();
        $dependent_count = $stmt->fetchColumn();

        // Redirect based on whether the user has dependents
        if ($dependent_count > 0) {
            header("Location: switch_account.php");
        } else {
            header("Location: index.php");
        }
        exit();
    } else {
        // If not found in users table, check admin_staff table
        $stmt = $conn->prepare("SELECT * FROM admin_staff WHERE (username = :identifier OR email = :identifier)");
        $stmt->bindParam(':identifier', $identifier);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            // Store admin details in session
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['username'] = $admin['username'];

            // Redirect based on admin role
            switch ($admin['role']) {
                case 'super_admin':
                    header("Location: superadmin_dashboard.php");
                    break;
                case 'admin':
                    header("Location: admin_dashboard.php");
                    break;
                case 'health_staff':
                    header("Location: healthstaff_dashboard.php");
                    break;
                default:
                    header("Location: login.php");
                    break;
            }
            exit();
        } else {
            $error = "Invalid credentials! Please check your email, phone, username, or password.";
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
    <!-- Font Awesome for eye icons (you can also use SVG) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <title>Login</title>
</head>
<body>    
    <div class="main-login">
        <div class="login-con <?php echo isset($error) ? 'has-error' : 'animate'; ?>">
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
                            <h3 id="login-title">Login</h3>
                            <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
                            <div class="field-container">
                                <input type="text" name="identifier" placeholder="Email/Phone/Username" autocomplete="off" required>

                                <!-- PASSWORD FIELD WITH TOGGLE -->
                                <div class="password-wrapper">
                                    <input type="password" name="password" id="password" placeholder="Password" required>
                                    <i class="toggle-password fas fa-eye-slash" onclick="togglePass(this)"></i>
                                </div>

                                <a href="forgot_password.php" class="forgot-link" id="forgot-link">Forgot password?</a>
                            </div>
                            
                            <button type="submit">Log In</button>
                            <div id="signup-link">
                                <p id="link">Don't have an account? <a href="register.php">Sign Up</a></p>
                                <p id="link">or <br><a href="index.php">Stay signed out</a></p>
                            </div>     
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ---------- JavaScript for toggle ---------- -->
    <script>
        // Ensure animations play after full load
        window.addEventListener('load', () => {
            document.body.style.opacity = 1;
        });
        
        function togglePass(icon) {
            const input = icon.previousElementSibling; // the password input
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            }
        }
    </script>
</body>
</html>