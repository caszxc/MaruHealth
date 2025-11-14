<?php
// system_settings.php
session_start();
require_once "config.php";
include 'settings.php';

// Restrict to Super Admin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'super_admin') {
    header("Location: login.php");
    exit();
}

$success = $error = '';

// ---------- FORM SUBMISSION ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = trim($_POST['site_name']);
    $tagline = trim($_POST['site_tagline']);
    $address = trim($_POST['contact_address']);
    $phone = trim($_POST['contact_phone']);
    $copyright = trim($_POST['footer_copyright']);

    // ---- Logo upload ----
    $logo_path = getSetting($conn, 'logo_path', 'images/site-logo.png');
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'png' && $file['size'] <= 2 * 1024 * 1024) {
            $target = 'images/site-logo.png';
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $logo_path = $target;
                $success .= "Logo updated. ";
            } else {
                $error .= "Failed to upload logo. ";
            }
        } else {
            $error .= "Only PNG files ≤ 2 MB allowed. ";
        }
    }

    // ---- DB updates ----
    $updates = [
        'site_name' => $site_name,
        'site_tagline' => $tagline,
        'contact_address' => $address,
        'contact_phone' => $phone,
        'footer_copyright' => $copyright,
        'logo_path' => $logo_path
    ];

    try {
        foreach ($updates as $key => $value) {
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = :value WHERE setting_key = :key");
            $stmt->execute([':value' => $value, ':key' => $key]);
        }
        $success .= "Settings saved successfully!";
    } catch (Exception $e) {
        $error .= "Database error: " . $e->getMessage();
    }

    // Output JSON for AJAX response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'error' => $error]);
        exit();
    }
}

// ---------- ADMIN INFO ----------
$adminId = $_SESSION['admin_id'];
$adminStmt = $conn->prepare("SELECT * FROM admin_staff WHERE id = :id");
$adminStmt->bindParam(':id', $adminId);
$adminStmt->execute();
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
$adminName = $admin ? $admin['full_name'] : $_SESSION['admin_name'];
$adminRole = $admin ? $admin['role'] : $_SESSION['admin_role'];
$displayRole = ucwords(str_replace('_', ' ', $adminRole));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings</title>
    <!-- Styles -->
    <link rel="stylesheet" href="css/settings.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <style>
        /* ---------- ALERTS ---------- */
        .alert {
            padding: 12px 20px;
            margin: 15px 0;
            border-radius: 6px;
            position: relative;
            transition: opacity 0.4s ease;
        }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
        .alert .close {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-weight: bold;
            font-size: 1.2em;
        }

        /* ---------- MODAL ---------- */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s;
        }
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            animation: slideIn 0.3s;
        }
        .modal-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .modal-buttons button {
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-confirm { background: #28a745; color: white; }
        .btn-cancel { background: #6c757d; color: white; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>
    <!-- ====================== NAV & SIDEBAR ====================== -->
    <nav>
        <div class="logo-container">
            <img src="<?= $logo_path ?>?t=<?= time() ?>" alt="Logo">
            <div>
                <h1>
                    <span class="maruhealth"><?= htmlspecialchars($site_name) ?></span>
                    <span class="barangay-title">Barangay Marulas 3S Health Center</span>
                </h1>
            </div>
        </div>
    </nav>
    <div class="sidebar">
        <div class="profile">
            <img src="images/profile-placeholder.png" alt="Admin">
            <div class="profile-details">
                <p class="admin_name"><strong><?= htmlspecialchars($adminName) ?></strong></p>
                <p class="role"><?= htmlspecialchars($displayRole) ?></p>
            </div>
        </div>
        <div class="menu">
            <?php
                $current_page = basename($_SERVER['PHP_SELF']);
                $dashboard_url = $adminRole === 'super_admin' ? 'superadmin_dashboard.php' :
                                 ($adminRole === 'admin' ? 'admin_dashboard.php' : 'healthstaff_dashboard.php');
            ?>
            <p class="menu-header">ANALYTICS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/dashboard_icon.png" alt="">
                <a href="<?= $dashboard_url ?>" class="<?= $current_page == $dashboard_url ? 'active' : '' ?>">Dashboard</a>
            </div>
            <p class="menu-header">BASE</p>
            <?php if ($adminRole == 'super_admin'): ?>
                <div class="menu-link">
                    <img class="menu-icon" src="images/icons/admin_icon.png" alt="">
                    <a href="manage_staff.php" class="<?= $current_page == 'manage_staff.php' ? 'active' : '' ?>">Admin Account Management</a>
                </div>
                <div class="menu-link">
                    <img class="menu-icon" src="images/icons/account_approval_icon.png" alt="">
                    <a href="account_approval.php" class="<?= $current_page == 'account_approval.php' ? 'active' : '' ?>">User Account Management</a>
                </div>
                <div class="menu-link-active">
                    <img class="menu-icon" src="images/icons/settings_icon_active.png" alt="">
                    <a href="system_settings.php" class="<?= $current_page == 'system_settings.php' ? 'active' : '' ?>">System Settings</a>
                </div>
            <?php endif; ?>
            <p class="menu-header">OTHERS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/logout_icon.png" alt="">
                <a href="logout.php" class="logout-button">Log Out</a>
            </div>
        </div>
    </div>

    <!-- ====================== MAIN CONTENT ====================== -->
    <div class="settings-content">
        <div class="title-con"><h2>System Settings</h2></div>

        <!-- ----- SUCCESS / ERROR ALERTS ----- -->
        <div id="alertContainer">
            <?php if ($success && empty($_SERVER['HTTP_X_REQUESTED_WITH'])): ?>
                <div class="alert success" id="successAlert">
                    <?= htmlspecialchars($success) ?>
                    <span class="close" onclick="this.parentElement.style.display='none'">×</span>
                </div>
            <?php endif; ?>
            <?php if ($error && empty($_SERVER['HTTP_X_REQUESTED_WITH'])): ?>
                <div class="alert error">
                    <?= htmlspecialchars($error) ?>
                    <span class="close" onclick="this.parentElement.style.display='none'">×</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="settings-container">
            <div class="form-container">
                <form id="settingsForm" enctype="multipart/form-data" class="settings-form">
                    <div class="form-group">
                        <label>Site Name</label>
                        <input type="text" name="site_name" value="<?= htmlspecialchars($site_name) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Site Tagline</label>
                        <textarea name="site_tagline" rows="2" required><?= htmlspecialchars($site_tagline) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Contact Address</label>
                        <input type="text" name="contact_address" value="<?= htmlspecialchars($contact_address) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="text" name="contact_phone" value="<?= htmlspecialchars($contact_phone) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Footer Copyright Text</label>
                        <input type="text" name="footer_copyright" value="<?= htmlspecialchars($footer_copyright) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Logo</label>
                        <input type="file" name="logo" accept=".png" id="logoInput">
                        <img src="images/site-logo.png?t=<?= time() ?>" alt="Current Logo" class="logo-preview" id="logoPreview">
                        <small>Max 2 MB – PNG only</small>
                    </div>
                </form>
            </div>
            <div class="button-container">
                <button type="button" id="saveBtn" class="btn">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- ====================== CONFIRMATION MODAL ====================== -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3>Save Changes?</h3>
            <p>Are you sure you want to save these settings? The page will reload to apply changes.</p>
            <div class="modal-buttons">
                <button class="btn-cancel" id="cancelBtn">Cancel</button>
                <button class="btn-confirm" id="confirmBtn">Save & Reload</button>
            </div>
        </div>
    </div>

    <!-- ====================== JS ====================== -->
    <script>
        // Elements
        const form = document.getElementById('settingsForm');
        const saveBtn = document.getElementById('saveBtn');
        const modal = document.getElementById('confirmModal');
        const confirmBtn = document.getElementById('confirmBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const alertContainer = document.getElementById('alertContainer');

        // Logo Preview
        const logoInput = document.getElementById('logoInput');
        const logoPreview = document.getElementById('logoPreview');
        logoInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file && file.type === 'image/png') {
                const reader = new FileReader();
                reader.onload = e => logoPreview.src = e.target.result;
                reader.readAsDataURL(file);
            } else {
                logoPreview.src = 'images/site-logo.png?t=' + Date.now();
            }
        });

        // Show Modal
        saveBtn.addEventListener('click', () => {
            modal.style.display = 'flex';
        });

        // Cancel
        cancelBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });

        // Confirm Save
        confirmBtn.addEventListener('click', () => {
            const formData = new FormData(form);

            fetch('', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                modal.style.display = 'none';

                // Clear previous alerts
                alertContainer.innerHTML = '';

                // Show new alerts
                if (data.success) {
                    const successDiv = document.createElement('div');
                    successDiv.className = 'alert success';
                    successDiv.innerHTML = data.success + ' <span class="close" onclick="this.parentElement.style.display=\'none\'">×</span>';
                    alertContainer.appendChild(successDiv);

                    // Auto-hide + reload
                    setTimeout(() => {
                        successDiv.style.opacity = '0';
                        setTimeout(() => location.reload(), 600);
                    }, 2000);
                }
                if (data.error) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert error';
                    errorDiv.innerHTML = data.error + ' <span class="close" onclick="this.parentElement.style.display=\'none\'">×</span>';
                    alertContainer.appendChild(errorDiv);
                }
            })
            .catch(() => {
                alertContainer.innerHTML = '<div class="alert error">Network error. Please try again.</div>';
                modal.style.display = 'none';
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    </script>
</body>
</html>