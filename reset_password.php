<?php
// reset_password.php
session_start();
include 'config.php';
include 'settings.php';

$error = '';
$success = false;

if (!isset($_SESSION['verified_email'])) {
    header("Location: forgot_password.php");
    exit;
}

$email = $_SESSION['verified_email'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in both password fields.";
    }
    // Validate password strength (same as register.php)
    elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $new_password)) {
        $error = "Password must be at least 8 characters and include uppercase, lowercase, and numbers.";
    }
    // Validate password confirmation
    elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = :password WHERE email = :email");
        $stmt->execute([':password' => $hashed_password, ':email' => $email]);

        // Delete used code
        $conn->prepare("DELETE FROM password_reset_tokens WHERE email = :email")->execute([':email' => $email]);

        // Clear session
        unset($_SESSION['verified_email']);
        unset($_SESSION['reset_email']);

        $success = true;
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" href="<?= $logo_url ?>" type="image/x-icon">
    <title>Reset Password</title>
 
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
                    <form method="POST" class="login-form" novalidate>
                        <div class="login-container">
                            <h3>Reset Password</h3>
                            <p>Enter a new password for your account.</p>
                            <div class="field-container">
                                <div class="group-col">
                                    <div class="password-wrapper">
                                        <input type="password" name="new_password" id="new_password" placeholder="New Password" required>
                                        <i class="toggle-password fas fa-eye-slash" onclick="togglePass(this)"></i>
                                    </div>
                                    
                                    <small class="field-hint">At least 8 characters with uppercase, lowercase, and numbers</small>
                                </div>
                                <div class="group-col">
                                    <div class="password-wrapper">
                                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                                        <i class="toggle-password fas fa-eye-slash" onclick="togglePass(this)"></i>
                                    </div>
                                    
                                    <small class="field-hint">Must match the new password</small>
                                </div>
                            </div>
                            <button type="submit" id="resetBtn" disabled>Reset Password</button>
                            <p id="link"><a href="login.php">Back to Login</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="success-modal" style="display: <?php echo $success ? 'flex' : 'none'; ?>;">
        <div class="success-content">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 class="success-title">Password Reset Successful!</h3>
            <p class="success-message">Your password has been reset successfully. You can now log in with your new password.</p>
            <button class="success-button" id="goToLoginBtn">Go to Login</button>
        </div>
    </div>

    <script>
        function togglePass(icon) {
            const input = icon.previousElementSibling;
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

        document.addEventListener("DOMContentLoaded", function () {
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const resetBtn = document.getElementById('resetBtn');

            const passwordValidator = {
                new_password: {
                    element: newPasswordInput,
                    errorMsg: "Password must be at least 8 characters and include uppercase, lowercase, and numbers",
                    validator: (value) => /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(value)
                },
                confirm_password: {
                    element: confirmPasswordInput,
                    errorMsg: "Passwords do not match",
                    validator: (value) => value === newPasswordInput.value && value.length > 0
                }
            };

            // --- SHAKE ONLY ONCE PER FIELD ---
            function showFieldError(inputEl, message) {
                const groupCol = inputEl.closest('.group-col');
                const oldError = groupCol.querySelector('.field-error');
                if (oldError) oldError.remove();

                const err = document.createElement('span');
                err.className = 'field-error';
                err.textContent = message;

                if (!groupCol.dataset.hasError) {
                    err.classList.add('shake');
                    groupCol.dataset.hasError = 'true';
                }

                groupCol.appendChild(err);
                inputEl.style.borderColor = '#FF0000';
                inputEl.classList.remove('valid');
            }

            function removeFieldError(inputEl) {
                const groupCol = inputEl.closest('.group-col');
                const old = groupCol.querySelector('.field-error');
                if (old) old.remove();
                inputEl.style.borderColor = '#ccc';
                inputEl.classList.add('valid');
                delete groupCol.dataset.hasError; 
            }

            function validateField(fieldName) {
                const field = passwordValidator[fieldName];
                const value = field.element.value.trim();
                if (field.validator(value)) {
                    removeFieldError(field.element);
                    return true;
                } else {
                    showFieldError(field.element, field.errorMsg);
                    return false;
                }
            }

            function updateSubmitButton() {
                const isNewValid = passwordValidator.new_password.validator(newPasswordInput.value);
                const isConfirmValid = passwordValidator.confirm_password.validator(confirmPasswordInput.value);
                resetBtn.disabled = !(isNewValid && isConfirmValid);
            }

            [newPasswordInput, confirmPasswordInput].forEach(input => {
                input.addEventListener('input', function () {
                    validateField(this.id);
                    updateSubmitButton();
                });
                input.addEventListener('blur', function () {
                    validateField(this.id);
                    updateSubmitButton();
                });
            });

            updateSubmitButton();

            document.getElementById("goToLoginBtn")?.addEventListener("click", () => {
                window.location.href = "login.php";
            });
        });
    </script>
</body>
</html>