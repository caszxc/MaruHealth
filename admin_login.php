<?php
//login.php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier']); // username or email for admin
    $password = $_POST['password'];

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/admin_login.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <title>Login</title>
</head>
<body>    
    <div class="main-login">
        <div class="logo-box">
            <img src="images/3s logo.png" alt="Logo">
            <div>
                <h1>Maru-Health</h1>
                <p>Barangay Marulas 3S Health Station</p>
            </div>
        </div>

        <div class="login-box">
            <form method="POST" class="login-form">
                <h3 id="login-title">Login as an Administrator or Health Staff</h3>
                <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
                <input type="text" name="identifier" placeholder="Username/Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Log In</button>   
            </form>
        </div>
    </div>
</body>
</html>