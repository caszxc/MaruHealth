<?php
//announcements.php
session_start();
require 'config.php';

// Check if user is logged in as super admin or admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch announcements with creator details
$filter = isset($_GET['filter']) && $_GET['filter'] === 'archived' ? 'archived' : 'active';
$stmt = $conn->prepare("
    SELECT a.*, s.full_name, s.role 
    FROM announcements a 
    JOIN admin_staff s ON a.admin_id = s.id 
    WHERE a.status = ? 
    ORDER BY a.created_at DESC
");
$stmt->execute([$filter]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch the logged-in admin's name and role
$adminId = $_SESSION['admin_id'];
$adminStmt = $conn->prepare("SELECT * FROM admin_staff WHERE id = :id");
$adminStmt->bindParam(':id', $adminId);
$adminStmt->execute();
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

// Default to session information if query fails
$adminName = $admin ? $admin['full_name'] : $_SESSION['admin_name'];
$adminRole = $admin ? $admin['role'] : $_SESSION['admin_role'];

// Format role for display (convert super_admin to Super Admin)
$displayRole = ucwords(str_replace('_', ' ', $adminRole));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements</title>
    <link rel="stylesheet" href="css/announcements.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="images/3s logo.png">
            <div>
                <h1>Maru-Health</h1>
                <p>Barangay Marulas 3S Health Station</p>
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
                $dashboard_url = '';
                if ($adminRole === 'super_admin') {
                    $dashboard_url = 'superadmin_dashboard.php';
                } elseif ($adminRole === 'admin') {
                    $dashboard_url = 'admin_dashboard.php';
                } elseif ($adminRole === 'staff') {
                    $dashboard_url = 'staff_dashboard.php';
                }
            ?>
            <p class="menu-header">ANALYTICS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/dashboard_icon.png" alt="">
                <a href="<?= htmlspecialchars($dashboard_url) ?>" class="<?= $current_page == $dashboard_url ? 'active' : '' ?>">Dashboard</a>
            </div>
            
            <p class="menu-header">BASE</p>
            <?php if ($adminRole == 'super_admin'): ?>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/account_approval_icon.png" alt="">
                <a href="manage_staff.php" class="<?= $current_page == 'manage_staff.php' ? 'active' : '' ?>">Manage Staff</a>
            </div>
            <?php endif; ?>
            
            <?php if ($adminRole == 'super_admin' || $adminRole == 'admin'): ?>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/account_approval_icon.png" alt="">
                <a href="account_approval.php" class="<?= $current_page == 'account_approval.php' ? 'active' : '' ?>">Account Approval</a>
            </div>
            
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/announcement_icon_active.png" alt="">
                <a href="announcements.php" class="<?= $current_page == 'announcements.php' ? 'active' : '' ?>">Announcement</a>
            </div>
            
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/calendar_icon.png" alt="">
                <a href="edit_calendar.php" class="<?= $current_page == 'edit_calendar.php' ? 'active' : '' ?>">Calendar</a>
            </div>

            <div class="menu-link">
                <img class="menu-icon" src="images/icons/calendar_icon.png" alt="">
                <a href="content_management.php" class="<?= $current_page == 'content_management.php' ? 'active' : '' ?>">Content Management</a>
            </div>
            <?php endif; ?>

            <?php if ($adminRole == 'super_admin' || $adminRole == 'staff'): ?>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/patient_icon.png" alt="">
                <a href="patient_management.php" class="<?= $current_page == 'patient_management.php' ? 'active' : '' ?>">Patient Management</a>
            </div>
            
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/med_icon.png" alt="">
                <a href="medicine_management.php" class="<?= $current_page == 'medicine_management.php' ? 'active' : '' ?>">Medicine Management</a>
            </div>
            
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/reqmd_icon.png" alt="">
                <a href="medicine_requests.php" class="<?= $current_page == 'medicine_requests.php' ? 'active' : '' ?>">Medicine Requests</a>
            </div>
            <?php endif; ?>

            <p class="menu-header">OTHERS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/logout_icon.png" alt="">
                <a href="logout.php" class="logout-button">Log Out</a>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="announcement-container">
            <div class="sort-controls">
                <select id="filter" onchange="filterAnnouncements()">
                    <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Announcements</option>
                    <option value="archived" <?= $filter === 'archived' ? 'selected' : '' ?>>Archives</option>
                </select>
                <button class="new-post-btn" onclick="openModal()">New Post</button>
            </div>

            <?php if (empty($announcements)): ?>
                <div class="no-announcements">
                    <p>No announcements available at the moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-box">
                        <div class="post-details">
                            <div class="post-details-text">
                                <strong><?= htmlspecialchars($announcement['full_name']) ?></strong>
                                <p><?= htmlspecialchars(ucwords(str_replace('_', ' ', $announcement['role']))) ?> - <?php 
                                    date_default_timezone_set('Asia/Manila');
                                    echo date("F j, Y, g:i A", strtotime($announcement['created_at']));  
                                ?></p>
                            </div>
                        </div>

                        <div class="actions">
                            <button class="kebab-menu" onclick="toggleMenu(<?= $announcement['id']; ?>)">⋮</button>
                            <div class="dropdown-menu" id="menu-<?= $announcement['id']; ?>">
                                <button onclick="openEditModal(<?= $announcement['id']; ?>, '<?= htmlspecialchars($announcement['title']); ?>', '<?= htmlspecialchars($announcement['content']); ?>', '<?= htmlspecialchars($announcement['image']); ?>')">Edit</button>
                                <button onclick="toggleArchive(<?= $announcement['id']; ?>)">
                                    <?= $filter === 'archived' ? 'Unarchive' : 'Archive' ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="announcement-content">
                            <div class="announcement-con">
                                <div class="image-con">
                                    <img src="images/uploads/announcement_images/<?= !empty($announcement['image']) ? htmlspecialchars($announcement['image']) : 'default_announcement.png' ?>" alt="Announcement Image" class="announcement-image">
                                </div>
                                <div class="content-con">
                                    <h2><?= htmlspecialchars($announcement['title']); ?></h2>
                                    <div class="post-content">
                                        <p><?= nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="postModal" class="modal">
        <div class="modal-content">
            <form action="save_announcement.php" method="POST" enctype="multipart/form-data">
                <h2 class="title">Create Announcement</h2>
                <div class="form-container">
                    <div class="row">
                        <label>Announcement Title</label>
                        <input type="text" name="title" placeholder="Type title here.." required>
                    </div>
                    <div class="row">
                        <label>Description</label>
                        <textarea name="content" placeholder="Write something.." required></textarea>
                    </div>
                    <div class="row">
                        <div class="file-upload">
                            <label for="file-upload" class="custom-file-upload">
                                <i class="fas fa-cloud-upload-alt"></i> Add Image
                            </label>
                            <input id="file-upload" type="file" name="image" onchange="updateFileName()" accept="image/*">
                            <span id="file-name">No file chosen</span>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="post-btn">Post</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <form id="editForm" action="update_announcement.php" method="POST" enctype="multipart/form-data">
                <h2 class="title">Edit Announcement</h2>
                <div class="form-container">
                    <input type="hidden" name="announcement_id" id="editAnnouncementId">
                    <div class="row">
                        <label>Announcement Title</label>
                        <input type="text" name="title" id="editTitle" placeholder="Type title here.." required>
                    </div>
                    <div class="row">
                        <label>Description</label>
                        <textarea name="content" id="editContent" placeholder="Write something.." required></textarea>
                    </div>
                    <div class="row">
                        <label>Current Image</label>
                        <div class="preview-container">
                            <img id="editPreviewImage" src="" alt="Preview" style="max-width: 100%; max-height: 200px;">
                        </div>
                    </div>
                    <div class="row">
                        <div class="file-upload">
                            <label for="edit-file-upload" class="custom-file-upload">
                                <i class="fas fa-cloud-upload-alt"></i> Change Image
                            </label>
                            <input id="edit-file-upload" type="file" name="image" onchange="previewEditImage()" accept="image/*">
                            <span id="edit-file-name">No file chosen</span>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="post-btn">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const fileInput = document.getElementById('file-upload');
            const fileNameSpan = document.getElementById('file-name');

            fileInput.addEventListener('change', function () {
                if (fileInput.files.length > 0) {
                    fileNameSpan.textContent = fileInput.files[0].name;
                } else {
                    fileNameSpan.textContent = 'No file chosen';
                }
            });

            // Add confirmation for edit form submission
            const editForm = document.getElementById('editForm');
            editForm.addEventListener('submit', function (event) {
                if (!confirm('Are you sure you want to update this announcement?')) {
                    event.preventDefault(); // Prevent form submission if user cancels
                }
            });
        });

        function toggleMenu(id) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu.id !== "menu-" + id) {
                    menu.style.display = "none";
                }
            });
            var menu = document.getElementById("menu-" + id);
            menu.style.display = menu.style.display === "block" ? "none" : "block";
        }

        document.addEventListener("click", function(event) {
            if (!event.target.matches('.kebab-menu')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = "none";
                });
            }
        });

        function openModal() {
            document.getElementById("postModal").style.display = "flex";
        }

        function closeModal() {
            document.getElementById("postModal").style.display = "none";
        }

        function openEditModal(id, title, content, image) {
            document.getElementById("editAnnouncementId").value = id;
            document.getElementById("editTitle").value = title;
            document.getElementById("editContent").value = content;
            const previewImage = document.getElementById("editPreviewImage");
            if (image) {
                previewImage.src = "images/uploads/announcement_images/" + image;
            } else {
                previewImage.src = "images/uploads/announcement_images/default_announcement.png";
            }
            document.getElementById("editModal").style.display = "flex";
        }

        function closeEditModal() {
            document.getElementById("editModal").style.display = "none";
            const fileInput = document.getElementById('edit-file-upload');
            fileInput.value = "";
            const fileNameSpan = document.getElementById('edit-file-name');
            fileNameSpan.textContent = "No file chosen";
        }

        function previewEditImage() {
            const fileInput = document.getElementById('edit-file-upload');
            const fileNameSpan = document.getElementById('edit-file-name');
            const previewImage = document.getElementById('editPreviewImage');
            if (fileInput.files && fileInput.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                };
                reader.readAsDataURL(fileInput.files[0]);
                fileNameSpan.textContent = fileInput.files[0].name;
            } else {
                fileNameSpan.textContent = 'No file chosen';
            }
        }

        function toggleArchive(id) {
            let filter = document.getElementById("filter").value;
            let action = (filter === "archived") ? "unarchive" : "archive";
            if (confirm(`Are you sure you want to ${action} this announcement?`)) {
                fetch(`toggle_announcement.php?id=${id}&action=${action}`, {
                    method: 'GET'
                }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert("Failed to update the announcement.");
                    }
                }).catch(error => console.error('Error:', error));
            }
        }

        function filterAnnouncements() {
            const filter = document.getElementById("filter").value;
            window.location.href = `announcements.php?filter=${filter}`;
        }
    </script>
</body>
</html>