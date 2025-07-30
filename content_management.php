<?php
//content_management.php
session_start();
require_once "config.php"; 

// Check if user is logged in as super admin or admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// fetch admin name
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

// Fetch services in one query
$servicesStmt = $conn->prepare("SELECT * FROM services");
$servicesStmt->execute();
$services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch sub-services and schedules in one query using JOIN for better performance
$subServiceStmt = $conn->prepare("
    SELECT 
        ss.service_id,
        ss.id AS sub_service_id, 
        ss.name AS sub_service_name, 
        GROUP_CONCAT(s.day_of_schedule ORDER BY s.day_of_schedule SEPARATOR ', ') AS days
    FROM sub_services ss
    LEFT JOIN schedules s ON ss.id = s.sub_service_id
    GROUP BY ss.id, ss.name
    ORDER BY ss.service_id, ss.id
");
$subServiceStmt->execute();

// Organize sub-services by service ID
$subServices = [];
while ($row = $subServiceStmt->fetch(PDO::FETCH_ASSOC)) {
    if (!isset($subServices[$row['service_id']])) {
        $subServices[$row['service_id']] = [];
    }
    $subServices[$row['service_id']][] = $row;
}

// Fetch all service images in one query for better performance
$imageStmt = $conn->prepare("
    SELECT service_id, image_path
    FROM service_images
    ORDER BY service_id, uploaded_at DESC
");
$imageStmt->execute();

// Organize images by service ID
$serviceImages = [];
while ($row = $imageStmt->fetch(PDO::FETCH_ASSOC)) {
    if (!isset($serviceImages[$row['service_id']])) {
        $serviceImages[$row['service_id']] = [];
    }
    $serviceImages[$row['service_id']][] = ['image_path' => $row['image_path']];
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management</title>
    <link rel="stylesheet" href="css/content_management.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="images/3s logo.png" alt="Logo">
            <p>Maru-Health <br> Barangay Marulas 3S <br> Health Station</p>
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

                // Determine dashboard URL based on role
                $dashboard_url = ''; // Default
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
            
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/announcement_icon.png" alt="">
                <a href="announcements.php" class="<?= $current_page == 'announcements.php' ? 'active' : '' ?>">Announcement</a>
            </div>
            
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/calendar_icon.png" alt="">
                <a href="edit_calendar.php" class="<?= $current_page == 'edit_calendar.php' ? 'active' : '' ?>">Calendar</a>
            </div>

            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/calendar_icon_active.png" alt="">
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

    <!-- Main Content -->
    <div class="container">
        <div class="services">
            <div class="tabs-buttons">
                <div class="service-tabs">
                    <?php foreach ($services as $index => $service): ?>
                        <button class="<?= $index == 0 ? 'active' : '' ?>" data-tab="tab<?= $service['id'] ?>">
                            <?= htmlspecialchars($service['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="service-button">
                    <button class="edit-btn" onclick="openModal()">Edit</button>
                    <button class="add-btn" onclick="openAddServiceModal()">Add Service</button>
                    <button class="delete-btn" onclick="deleteService()">Delete</button>
                </div>
            </div>

            <?php foreach ($services as $index => $service): ?>
                <div id="tab<?= $service['id'] ?>" class="tab-content <?= $index == 0 ? 'active' : '' ?>">
                    <!-- Service Card -->
                    <div class="service-card-container">
                        <div class="service-card">
                            <img src="<?= htmlspecialchars($service['icon_path'] ?? 'images/placeholder.png') ?>" alt="<?= htmlspecialchars($service['name']) ?>">
                            <h3><?= htmlspecialchars($service['name']) ?></h3>
                            <p><?= htmlspecialchars($service['intro'] ?? 'No introduction available.') ?></p>
                        </div>
                    </div>

                    <div class="service-title"><?= htmlspecialchars($service['name']) ?></div>
                    <p><?= nl2br(htmlspecialchars($service['description'])) ?></p>

                    <div class="service-schedules">
                        <h3>Service Schedules</h3>
                        <table>
                            <tr>
                                <th>Name of Service</th>
                                <th>Day(s) of Schedule</th>
                            </tr>
                            <?php if (!empty($subServices[$service['id']])): ?>
                                <?php foreach ($subServices[$service['id']] as $sub): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($sub['sub_service_name']) ?></td>
                                        <td><?= htmlspecialchars($sub['days'] ?? 'No schedule') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" style="text-align: center;">No schedules found.</td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>

                    <div class="service-images-section">
                        <h3>Service Images</h3>
                        <div class="service-images">
                            <?php if (!empty($serviceImages[$service['id']])): ?>
                                <?php foreach ($serviceImages[$service['id']] as $image): ?>
                                    <img src="<?= htmlspecialchars($image['image_path']) ?>" alt="Service Image">
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-images">No images uploaded for this service.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Service Modal -->
    <div id="addServiceModal" class="modal">
        <div class="modal-content">
            <h2 class="title">Add New Service</h2>
            <form id="addServiceForm" action="add_service.php" method="POST" enctype="multipart/form-data">
                <div class="form-container">
                    <!-- Service Card Preview -->
                    <div class="row">
                        <label>Service Card Preview</label>
                        <div class="service-card-container">
                            <div class="service-card">
                                <div class="icon-container">
                                    <img id="newServiceIconPreview" src="images/placeholder.png" alt="Service Icon">
                                    <i class="fas fa-pen edit-icon"></i>
                                    <input type="file" id="newServiceIcon" name="serviceIcon" accept="image/*" style="display: none;">
                                </div>
                                <h3 id="newServiceTitlePreview">Service Title</h3>
                                <p id="newServiceIntroPreview">Enter intro text</p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <label for="newServiceTitle">Title</label>
                        <input type="text" id="newServiceTitle" name="serviceTitle" required>
                    </div>
                    <div class="row">
                        <label for="newServiceIntro">Intro</label>
                        <input type="text" id="newServiceIntro" name="serviceIntro">
                    </div>
                    <div class="row">
                        <label for="newServiceDescription">Description</label>
                        <textarea id="newServiceDescription" name="serviceDescription" rows="6" required></textarea>
                    </div>
                    <div class="row">
                        <label>Service Schedules</label>
                        <div class="service-schedules-container">
                            <div id="newServiceSchedulesContainer">
                                <div id="newNoScheduleMessage" style="text-align:center; color: gray; padding: 10px;">No Schedule</div>
                            </div>
                            <button type="button" class="add-service-btn" onclick="addNewService()">+ Add Service</button>
                        </div>
                    </div>
                    <div class="row">
                        <label>Service Images</label>
                        <div class="image-upload">
                            <input type="file" id="newServiceImages" name="serviceImages[]" accept="image/*" multiple>
                            <p class="help-text">Upload images for this service (JPG, PNG, GIF only, max 5MB per image)</p>
                            <div id="newImagePreviewContainer" class="image-preview-container"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="cancel-btn" onclick="closeAddServiceModal()">Cancel</button>
                    <button type="submit" class="save-btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Services Modal -->
    <div id="editServiceModal" class="modal">
        <div class="modal-content">
            <h2 class="title">Edit Service</h2>
            <form id="editServiceForm" action="update_service.php" method="POST" enctype="multipart/form-data">
                <div class="form-container">
                    <input type="hidden" name="serviceId" id="serviceId">
                    <input type="hidden" id="imagesToDelete" name="imagesToDelete">
                    <!-- Service Card Preview -->
                    <div class="row">
                        <label>Service Card Preview</label>
                        <div class="service-card-container">
                            <div class="service-card">
                                <div class="icon-container">
                                    <img id="serviceIconPreview" src="images/placeholder.png" alt="Service Icon">
                                    <i class="fas fa-pen edit-icon"></i>
                                    <input type="file" id="serviceIcon" name="serviceIcon" accept="image/*" style="display: none;">
                                </div>
                                <h3 id="serviceTitlePreview"></h3>
                                <p id="serviceIntroPreview">Intro Text</p>
                            </div>
                        </div>
                    </div>
                        
                    <div class="row">
                        <label for="serviceTitle">Title</label>
                        <input type="text" id="serviceTitle" name="serviceTitle" required>
                    </div>
                    <div class="row">
                        <label for="serviceIntro">Intro</label>
                        <input type="text" id="serviceIntro" name="serviceIntro">
                    </div>
                    <div class="row">
                        <label for="serviceDescription">Description</label>
                        <textarea id="serviceDescription" name="serviceDescription" rows="6" required></textarea>
                    </div>
                    <div class="row">
                        <label>Service Schedules</label>
                        <div class="service-schedules-container">
                            <div id="serviceSchedulesContainer">
                                <div id="noScheduleMessage" style="text-align:center; color: gray; padding: 10px;">No Schedule</div>
                            </div>
                            <button type="button" class="add-service-btn" onclick="addService()">+ Add Service</button>
                        </div>
                    </div>

                    <!-- Image Upload -->
                    <div class="row">
                        <label>Service Images</label>
                        <div class="image-upload">
                            <input type="file" id="serviceImages" name="serviceImages[]" accept="image/*" multiple>
                            <p class="help-text">Upload new images for this service (JPG, PNG, GIF only, max 5MB per image)</p>
                            
                            <!-- Image Preview Container -->
                            <div id="imagePreviewContainer" class="image-preview-container"></div>
                        </div>
                    </div>
                </div>
                <!-- Modal Action Buttons -->
                <div class="modal-actions">
                    <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="save-btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Cache DOM elements and initialize variables
        let addServiceFiles = []; // For Add Service modal
        let editServiceFiles = []; // For Edit Service modal
        let existingImagesData = [];
        let imagesToDelete = [];
        const serviceImages = <?= json_encode($serviceImages) ?>;
        const allSubServices = <?= json_encode($subServices) ?>;
        const allServices = <?= json_encode($services) ?>;

        // Tab navigation functionality
        function setupTabNavigation() {
            const tabButtons = document.querySelectorAll('.service-tabs button');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Remove active class from all tabs and contents
                    document.querySelectorAll('.service-tabs button').forEach(btn => 
                        btn.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(tab => 
                        tab.classList.remove('active'));
                    
                    // Add active class to clicked tab and content
                    button.classList.add('active');
                    const tabId = button.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        }

        // File input setup
        function setupFileInputListener() {
            document.getElementById('serviceImages').addEventListener('change', function(event) {
                if (this.files) {
                    Array.from(this.files).forEach(file => {
                        if (file.type.match('image.*')) {
                            editServiceFiles.push(file);
                        }
                    });
                }
                
                // Update image previews
                updateImagePreviews();
            });
        }

        // Modal functions
        function openModal() {
            const activeTab = document.querySelector('.tab-content.active');
            if (!activeTab) return;

            // Get service information
            const serviceId = activeTab.id.replace('tab', '');
            const service = allServices.find(s => s.id == serviceId);
            if (!service) return;

            const serviceTitle = service.name || '';
            const serviceDescription = service.description || '';
            const serviceIcon = service.icon_path || 'images/placeholder.png';
            const serviceIntro = service.intro || '';

            // Set form values
            document.getElementById('serviceTitle').value = serviceTitle.trim();
            document.getElementById('serviceDescription').value = serviceDescription.trim();
            document.getElementById('serviceIntro').value = serviceIntro.trim();
            document.getElementById('serviceId').value = serviceId;
            document.getElementById('imagesToDelete').value = '';

            // Set service card preview
            document.getElementById('serviceIconPreview').src = serviceIcon;
            document.getElementById('serviceTitlePreview').textContent = serviceTitle.trim() || 'Service Title';
            document.getElementById('serviceIntroPreview').textContent = serviceIntro.trim() || 'Enter intro text';

            // Reset state
            editServiceFiles = [];
            imagesToDelete = [];
            existingImagesData = [];
            
            // Initialize service schedules
            initializeServiceSchedules(serviceId);
            
            // Load existing images
            loadExistingImages(serviceId);
            
            // Show modal
            document.getElementById("editServiceModal").style.display = "flex";
        }

        function closeModal() {
            document.getElementById('editServiceModal').style.display = 'none';
            document.getElementById('serviceImages').value = ''; // Clear file input
            editServiceFiles = []; // Reset array
            updateImagePreviews(); // Update previews
        }

        // Service schedule functions
        function initializeServiceSchedules(serviceId) {
            resetServiceSchedules(); // Clear first
            
            const subServices = allSubServices[serviceId] || [];
            
            if (subServices.length > 0) {
                removeNoScheduleMessage();
                
                subServices.forEach((sub, i) => {
                    const serviceDiv = document.createElement('div');
                    serviceDiv.classList.add('service-item');
                    
                    const days = (sub.days || '').split(',').map(day => day.trim());
                    let daysHtml = '';
                    
                    days.forEach(day => {
                        if (day) {
                            daysHtml += `
                                <div class="schedule-input-group">
                                    <input type="text" name="scheduleDay[${i}][]" value="${day}" required>
                                    <button type="button" class="delete-schedule-btn" onclick="deleteSchedule(this)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            `;
                        }
                    });
                    
                    if (!daysHtml) {
                        daysHtml = `
                            <div class="schedule-input-group">
                                <input type="text" name="scheduleDay[${i}][]" required>
                                <button type="button" class="delete-schedule-btn" onclick="deleteSchedule(this)">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        `;
                    }
                    
                    serviceDiv.innerHTML = `
                        <button type="button" class="delete-btn" onclick="deleteSubService(this)">Delete</button>
                        <div class="group">
                            <div class="row">
                                <label>Name of Service</label>
                                <input type="text" name="serviceName[]" value="${sub.sub_service_name}" required>
                            </div>
                            <div class="row">
                                <div class="schedule-container">
                                    <label>Day of Schedule</label>
                                    <div class="day-container">
                                        ${daysHtml}
                                    </div>
                                    <button type="button" class="add-schedule-btn" onclick="addSchedule(this, ${i})">
                                        + Add Schedule
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('serviceSchedulesContainer').appendChild(serviceDiv);
                });
            }
        }

        function resetServiceSchedules() {
            document.getElementById('serviceSchedulesContainer').innerHTML = `
                <div id="noScheduleMessage" style="text-align:center; color: gray; padding: 10px;">No Schedule</div>
            `;
        }

        function removeNoScheduleMessage() {
            const noScheduleMessage = document.getElementById('noScheduleMessage');
            if (noScheduleMessage) {
                noScheduleMessage.remove();
            }
        }

        function addService() {
            removeNoScheduleMessage();
            
            const serviceItems = document.querySelectorAll('.service-item');
            const nextIndex = serviceItems.length;
            
            const serviceDiv = document.createElement('div');
            serviceDiv.classList.add('service-item');
            
            serviceDiv.innerHTML = `
                <button type="button" class="delete-btn" onclick="deleteSubService(this)">Delete</button>
                <div class="group">
                    <div class="row">
                        <label>Name of Service</label>
                        <input type="text" name="serviceName[]" placeholder="Name of Service" required>
                    </div>
                    <div class="row">
                        <div class="schedule-container">
                            <label>Day of Schedule</label>
                            <div class="day-container">
                                <div class="schedule-input-group">
                                    <input type="text" name="scheduleDay[${nextIndex}][]" required>
                                    <button type="button" class="delete-schedule-btn" onclick="deleteSchedule(this)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="add-schedule-btn" onclick="addSchedule(this, ${nextIndex})">
                                + Add Schedule
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('serviceSchedulesContainer').appendChild(serviceDiv);
        }

        function deleteSubService(button) {
            button.parentElement.remove();
            checkNoSchedule();
        }

        function addSchedule(button, index) {
            const container = button.previousElementSibling;
            
            const scheduleDiv = document.createElement('div');
            scheduleDiv.classList.add('schedule-input-group');
            
            scheduleDiv.innerHTML = `
                <input type="text" name="scheduleDay[${index}][]" required>
                <button type="button" class="delete-schedule-btn" onclick="deleteSchedule(this)">
                    <i class="fa fa-trash"></i>
                </button>
            `;
            
            container.appendChild(scheduleDiv);
        }

        function deleteSchedule(button) {
            const container = button.closest('.day-container');
            const scheduleGroups = container.querySelectorAll('.schedule-input-group');
            
            if (scheduleGroups.length > 1) {
                button.parentElement.remove();
            } else {
                alert("At least one schedule day must remain.");
            }
        }

        function checkNoSchedule() {
            const items = document.querySelectorAll('.service-item');
            if (items.length === 0) {
                resetServiceSchedules();
            }
        }

        // Image management functions
        function loadExistingImages(serviceId) {
            const previewContainer = document.getElementById('imagePreviewContainer');
            previewContainer.innerHTML = '';
            
            const existingImages = serviceImages[serviceId] || [];
            existingImagesData = [];
            
            if (existingImages.length > 0) {
                existingImages.forEach((image, index) => {
                    existingImagesData.push({
                        path: image.image_path,
                        index: index
                    });
                    
                    const previewDiv = document.createElement('div');
                    previewDiv.className = 'image-preview existing-image';
                    previewDiv.dataset.path = image.image_path;
                    
                    const img = document.createElement('img');
                    img.src = image.image_path;
                    
                    const removeBtn = document.createElement('div');
                    removeBtn.className = 'remove-image';
                    removeBtn.innerHTML = '<i class="fa fa-times"></i>';
                    removeBtn.dataset.path = image.image_path;
                    removeBtn.onclick = function() {
                        removeExistingImage(this.dataset.path);
                    };
                    
                    previewDiv.appendChild(img);
                    previewDiv.appendChild(removeBtn);
                    previewContainer.appendChild(previewDiv);
                });
            }
            
            addUploadMoreBox();
        }

        function updateImagePreviews() {
            const previewContainer = document.getElementById('imagePreviewContainer');
            previewContainer.innerHTML = '';
            
            // Re-add existing images that aren't marked for deletion
            existingImagesData.forEach(image => {
                if (!imagesToDelete.includes(image.path)) {
                    const previewDiv = document.createElement('div');
                    previewDiv.className = 'image-preview existing-image';
                    previewDiv.dataset.path = image.path;
                    
                    const img = document.createElement('img');
                    img.src = image.path;
                    
                    const removeBtn = document.createElement('div');
                    removeBtn.className = 'remove-image';
                    removeBtn.innerHTML = '<i class="fa fa-times"></i>';
                    removeBtn.dataset.path = image.path;
                    removeBtn.onclick = function() {
                        removeExistingImage(this.dataset.path);
                    };
                    
                    previewDiv.appendChild(img);
                    previewDiv.appendChild(removeBtn);
                    previewContainer.appendChild(previewDiv);
                }
            });
            
            // Track pending previews to ensure we add the upload box last
            let pendingPreviews = editServiceFiles.length;
            
            if (pendingPreviews === 0) {
                // No new files, add upload box immediately
                addUploadMoreBox();
            } else {
                // Create preview for each new file
                editServiceFiles.forEach((file, index) => {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const previewDiv = document.createElement('div');
                        previewDiv.className = 'image-preview';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        
                        const removeBtn = document.createElement('div');
                        removeBtn.className = 'remove-image';
                        removeBtn.innerHTML = '<i class="fa fa-times"></i>';
                        removeBtn.dataset.index = index;
                        removeBtn.onclick = function() {
                            removeImagePreview(parseInt(this.dataset.index));
                        };
                        
                        previewDiv.appendChild(img);
                        previewDiv.appendChild(removeBtn);
                        previewContainer.appendChild(previewDiv);
                        
                        // Decrement pending previews and add upload box if this is the last one
                        pendingPreviews--;
                        if (pendingPreviews === 0) {
                            addUploadMoreBox();
                        }
                    };
                    
                    reader.readAsDataURL(file);
                });
            }
            
            // Update the actual file input
            updateFileInput();
        }

        function addUploadMoreBox() {
            const previewContainer = document.getElementById('imagePreviewContainer');
            const uploadMoreBox = document.createElement('div');
            uploadMoreBox.className = 'upload-box';
            uploadMoreBox.innerHTML = '<i class="fa fa-plus"></i><span>Add Image</span>';
            uploadMoreBox.onclick = function() {
                document.getElementById('serviceImages').click();
            };
            previewContainer.appendChild(uploadMoreBox);
        }

        function removeImagePreview(index) {
            editServiceFiles.splice(index, 1);
            updateImagePreviews();
        }

        function removeExistingImage(imagePath) {
            // Add path to list of images to delete
            imagesToDelete.push(imagePath);
            document.getElementById('imagesToDelete').value = JSON.stringify(imagesToDelete);
            
            // Update the previews
            updateImagePreviews();
        }

        function updateFileInput() {
            const dt = new DataTransfer();
            editServiceFiles.forEach(file => dt.items.add(file));
            document.getElementById('serviceImages').files = dt.files;
        }
    </script>

    <script>
        // Add Service Modal functions
        function openAddServiceModal() {
            // Reset form and state
            document.getElementById('newServiceTitle').value = '';
            document.getElementById('newServiceDescription').value = '';
            addServiceFiles = []; // Reset Add Service files
            resetNewServiceSchedules();
            updateNewImagePreviews();

            // Show modal
            document.getElementById('addServiceModal').style.display = 'flex';
        }

        // Update service card preview when title or intro changes
        document.getElementById('newServiceTitle').addEventListener('input', function() {
            document.getElementById('newServiceTitlePreview').textContent = this.value || 'Service Title';
        });

        document.getElementById('newServiceIntro').addEventListener('input', function() {
            document.getElementById('newServiceIntroPreview').textContent = this.value || 'Intro Text';
        });

        // Handle service icon preview
        document.getElementById('newServiceIcon').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file && file.type.match('image.*')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('newServiceIconPreview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        document.getElementById('newServiceIconPreview').addEventListener('click', function() {
            document.getElementById('newServiceIcon').click();
        });

        function closeAddServiceModal() {
            document.getElementById('addServiceModal').style.display = 'none';
            document.getElementById('newServiceImages').value = ''; // Clear file input
            addServiceFiles = []; // Reset array
            updateNewImagePreviews(); // Update previews
        }

        // New Service Schedule functions
        function resetNewServiceSchedules() {
            document.getElementById('newServiceSchedulesContainer').innerHTML = `
                <div id="newNoScheduleMessage" style="text-align:center; color: gray; padding: 10px;">No Schedule</div>
            `;
        }

        function removeNewNoScheduleMessage() {
            const noScheduleMessage = document.getElementById('newNoScheduleMessage');
            if (noScheduleMessage) {
                noScheduleMessage.remove();
            }
        }

        function addNewService() {
            removeNewNoScheduleMessage();
            
            const serviceItems = document.querySelectorAll('#newServiceSchedulesContainer .service-item');
            const nextIndex = serviceItems.length;
            
            const serviceDiv = document.createElement('div');
            serviceDiv.classList.add('service-item');
            
            serviceDiv.innerHTML = `
                <button type="button" class="delete-btn" onclick="deleteNewService(this)">Delete</button>
                <div class="group">
                    <div class="row">
                        <label>Name of Service</label>
                        <input type="text" name="serviceName[]" placeholder="Name of Service" required>
                    </div>
                    <div class="row">
                        <div class="schedule-container">
                            <label>Day of Schedule</label>
                            <div class="day-container">
                                <div class="schedule-input-group">
                                    <input type="text" name="scheduleDay[${nextIndex}][]" required>
                                    <button type="button" class="delete-schedule-btn" onclick="deleteNewSchedule(this)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="add-schedule-btn" onclick="addNewSchedule(this, ${nextIndex})">
                                + Add Schedule
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('newServiceSchedulesContainer').appendChild(serviceDiv);
        }

        function deleteNewService(button) {
            button.parentElement.remove();
            checkNewNoSchedule();
        }

        function addNewSchedule(button, index) {
            const container = button.previousElementSibling;
            
            const scheduleDiv = document.createElement('div');
            scheduleDiv.classList.add('schedule-input-group');
            
            scheduleDiv.innerHTML = `
                <input type="text" name="scheduleDay[${index}][]" required>
                <button type="button" class="delete-schedule-btn" onclick="deleteNewSchedule(this)">
                    <i class="fa fa-trash"></i>
                </button>
            `;
            
            container.appendChild(scheduleDiv);
        }

        function deleteNewSchedule(button) {
            const container = button.closest('.day-container');
            const scheduleGroups = container.querySelectorAll('.schedule-input-group');
            
            if (scheduleGroups.length > 1) {
                button.parentElement.remove();
            } else {
                alert("At least one schedule day must remain.");
            }
        }

        function checkNewNoSchedule() {
            const items = document.querySelectorAll('#newServiceSchedulesContainer .service-item');
            if (items.length === 0) {
                resetNewServiceSchedules();
            }
        }

        // New Image management functions
        function setupNewFileInputListener() {
            document.getElementById('newServiceImages').addEventListener('change', function(event) {
                if (this.files) {
                    Array.from(this.files).forEach(file => {
                        if (file.type.match('image.*')) {
                            addServiceFiles.push(file);
                        }
                    });
                }
                updateNewImagePreviews();
            });
        }

        function updateNewImagePreviews() {
            const previewContainer = document.getElementById('newImagePreviewContainer');
            previewContainer.innerHTML = '';
            
            let pendingPreviews = addServiceFiles.length;
            
            if (pendingPreviews === 0) {
                addNewUploadMoreBox();
            } else {
                addServiceFiles.forEach((file, index) => {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const previewDiv = document.createElement('div');
                        previewDiv.className = 'image-preview';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        
                        const removeBtn = document.createElement('div');
                        removeBtn.className = 'remove-image';
                        removeBtn.innerHTML = '<i class="fa fa-times"></i>';
                        removeBtn.dataset.index = index;
                        removeBtn.onclick = function() {
                            removeNewImagePreview(parseInt(this.dataset.index));
                        };
                        
                        previewDiv.appendChild(img);
                        previewDiv.appendChild(removeBtn);
                        previewContainer.appendChild(previewDiv);
                        
                        pendingPreviews--;
                        if (pendingPreviews === 0) {
                            addNewUploadMoreBox();
                        }
                    };
                    
                    reader.readAsDataURL(file);
                });
            }
            
            updateNewFileInput();
        }

        function addNewUploadMoreBox() {
            const previewContainer = document.getElementById('newImagePreviewContainer');
            const uploadMoreBox = document.createElement('div');
            uploadMoreBox.className = 'upload-box';
            uploadMoreBox.innerHTML = '<i class="fa fa-plus"></i><span>Add Image</span>';
            uploadMoreBox.onclick = function() {
                document.getElementById('newServiceImages').click();
            };
            previewContainer.appendChild(uploadMoreBox);
        }

        function removeNewImagePreview(index) {
            addServiceFiles.splice(index, 1);
            updateNewImagePreviews();
        }

        function updateNewFileInput() {
            const dt = new DataTransfer();
            addServiceFiles.forEach(file => dt.items.add(file));
            document.getElementById('newServiceImages').files = dt.files;
        }

        // Initialize new file input listener on DOM load
        document.addEventListener('DOMContentLoaded', function() {
            setupTabNavigation();
            setupFileInputListener();
            setupNewFileInputListener();
        });

        function deleteService() {
            const activeTab = document.querySelector('.tab-content.active');
            if (!activeTab) {
                alert('Please select a service to delete.');
                return;
            }

            const serviceId = activeTab.id.replace('tab', '');
            const serviceName = activeTab.querySelector('.service-title').innerText;

            if (confirm(`Are you sure you want to delete the service "${serviceName}"? This will also delete all associated sub-services, schedules, and images.`)) {
                fetch('delete_service.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'serviceId=' + encodeURIComponent(serviceId)
                })
                .then(response => response.text())
                .then(data => {
                    alert(data);
                    location.reload(); // Reload to update the service list
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the service.');
                });
            }
        }

        // Update service card preview when title or intro changes
        document.getElementById('serviceTitle').addEventListener('input', function() {
            document.getElementById('serviceTitlePreview').textContent = this.value || 'Service Title';
        });

        document.getElementById('serviceIntro').addEventListener('input', function() {
            document.getElementById('serviceIntroPreview').textContent = this.value || 'Enter intro text';
        });

        // Handle service icon preview
        document.getElementById('serviceIcon').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file && file.type.match('image.*')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('serviceIconPreview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Click handler for icon preview
        document.getElementById('serviceIconPreview').addEventListener('click', function() {
            document.getElementById('serviceIcon').click();
        });
    </script>
</body>
</html>