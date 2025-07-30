<?php
//login.php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier']); // email or phone for residents, username or email for admin
    $password = $_POST['password'];
    $role_type = $_POST['role_type']; // 'resident' or 'admin_staff'
    
    if ($role_type === 'resident') {
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
    } else {
        // For admin staff, check the admin_staff table
        $stmt = $conn->prepare("SELECT * FROM admin_staff WHERE (username = :identifier OR email = :identifier)");
        $stmt->bindParam(':identifier', $identifier);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['password'])) {
            // Store admin details in session
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role'] = $admin['role']; // 'super_admin', 'admin', or 'staff'
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['username'] = $admin['username'];
            
            // Redirect based on admin role
            switch ($_SESSION['admin_role']) {
                case 'super_admin':
                    header("Location: superadmin_dashboard.php");
                    break;
                case 'admin':
                    header("Location: admin_dashboard.php");
                    break;
                case 'staff':
                    header("Location: staff_dashboard.php");
                    break;
                default:
                    header("Location: login.php");
                    break;
            }
            exit();
        } else {
            $error = "Invalid admin credentials!";
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
    <title>Login</title>
</head>
<body>    
    <div class="main-login">
        <div class="left-panel">
            <img src="images/3s logo.png" alt="Logo">
            <h1>Maru-Health<br>Barangay Marulas<br>3S Health Station</h1>
        </div>

        <div class="right-panel">
            <div class="tab-header">
                <button id="resident-tab" class="active" onclick="switchTab('resident')">Resident</button>
                <button id="admin-tab" onclick="switchTab('admin')">Staff Access</button>
            </div>

            <div class="login-box">
                <form method="POST" class="login-form">
                    <h3 id="login-title">Login as a Resident</h3>
                    <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
                    <input type="text" name="identifier" placeholder="E-mail/Phone Number" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <input type="hidden" id="role_type" name="role_type" value="resident">
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

    <script>
        function switchTab(tab) {
            const title = document.getElementById('login-title');
            const roleInput = document.getElementById('role_type');
            const identifierField = document.querySelector('input[name="identifier"]');
            const signupLink = document.getElementById('signup-link');
            const forgotLink = document.getElementById('forgot-link');
            
            document.getElementById('resident-tab').classList.remove('active');
            document.getElementById('admin-tab').classList.remove('active');

            if (tab === 'admin') {
                title.innerText = 'Staff Access Login';
                roleInput.value = 'admin_staff';
                identifierField.placeholder = 'Username/Email';
                document.getElementById('admin-tab').classList.add('active');
                signupLink.style.display = 'none';
                forgotLink.style.display = 'none'; // Hide forgot password for admin
            } else {
                title.innerText = 'Login as a Resident';
                roleInput.value = 'resident';
                identifierField.placeholder = 'E-mail/Phone Number';
                document.getElementById('resident-tab').classList.add('active');
                signupLink.style.display = 'block';
                forgotLink.style.display = 'block'; // Show forgot password for resident
            }
        }
    </script>
</body>
</html>