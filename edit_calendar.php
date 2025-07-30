<?php
session_start();
require_once "config.php"; // include your database connection

// Check if user is logged in as super admin or admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch the admin's name
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

$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Calendar</title>
    <link rel="stylesheet" href="css/edit_calendar.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const fileInput = document.getElementById('eventImage');
            const fileNameSpan = document.getElementById('file-name');

            fileInput.addEventListener('change', function () {
                if (fileInput.files.length > 0) {
                    fileNameSpan.textContent = fileInput.files[0].name;
                } else {
                    fileNameSpan.textContent = 'No file chosen';
                }
            });
        });

        function loadCalendar(month, year) {
            fetch(`calendar_ajax.php?month=${month}&year=${year}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById("calendar").innerHTML = html;
                    document.getElementById("currentMonth").value = month;
                    document.getElementById("currentYear").value = year;
                });
        }

        let selectedDate = null; // Store the selected date

        function showEvents(year, month, day) {
    // First, remove selected-day class from all cells
    document.querySelectorAll('#calendar td').forEach(cell => {
        cell.classList.remove('selected-day');
    });
    
    // Format and store the selected date
    const formattedSelectedDate = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    
    // Add selected-day class to the clicked cell
    const selectedCell = document.querySelector(`td[data-date="${formattedSelectedDate}"]`);
    if (selectedCell) {
        selectedCell.classList.add('selected-day');
    }

    // Update the global selectedDate variable
    selectedDate = formattedSelectedDate;
    
    const date = new Date(year, month - 1, day);
    const options = { month: 'long', day: 'numeric', weekday: 'long' };
    const formattedDate = date.toLocaleDateString('en-US', options);
    document.getElementById("selectedDate").innerText = formattedDate;

    fetch(`get_events.php?date=${formattedSelectedDate}`)
        .then(response => response.json())
        .then(data => {
            let eventList = document.getElementById("eventList");
            eventList.innerHTML = ""; // Clear previous events

            if (data.length === 0) {
                eventList.innerHTML = "<p style='text-align: center;'>No events available</p>";
            } else {
                data.forEach(event => {
                    let eventCard = document.createElement("div");
                    eventCard.classList.add("event-card");

                    let eventImg = document.createElement("img");
                    eventImg.src = event.image ? `images/uploads/event_images/${event.image}` : "images/uploads/event_images/default_event.png";
                    eventImg.alt = event.title;

                    let eventDetails = document.createElement("div");
                    eventDetails.classList.add("event-details"); 

                    let startTime = convertTo12HourFormat(event.start);
                    let endTime = convertTo12HourFormat(event.end);

                    eventDetails.innerHTML = `<strong>${event.title}</strong>${startTime} - ${endTime}<br><em>${event.venue}</em>`;

                    let btnContainer = document.createElement("div");
                    btnContainer.classList.add("button-container");

                    // Delete button
                    let deleteBtn = document.createElement("button");
                    deleteBtn.innerText = "Delete";
                    deleteBtn.classList.add("delete-btn");
                    deleteBtn.onclick = () => deleteEvent(event.id);

                    btnContainer.appendChild(deleteBtn);
                    eventCard.appendChild(eventImg);
                    eventCard.appendChild(eventDetails);
                    eventCard.appendChild(btnContainer); 
                    eventList.appendChild(eventCard);
                });
            }
        })
        .catch(error => console.error("Error loading events:", error));
}

        // Delete Event Function
        function deleteEvent(eventId) {
            if (!confirm("Are you sure you want to delete this event?")) return;

            fetch("delete_event.php", {
                method: "POST",
                body: new URLSearchParams({ id: eventId })
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    if (!selectedDate) {
                        let today = new Date();
                        selectedDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
                    }

                    let [year, month, day] = selectedDate.split('-');
                    year = parseInt(year);
                    month = parseInt(month);
                    day = parseInt(day);

                    // Refresh event list
                    showEvents(year, month, day);

                    // Refresh calendar
                    loadCalendar(month, year);
                }
            })
            .catch(error => console.error("Error deleting event:", error));
        }



        // Function to convert 24-hour time to 12-hour format
        function convertTo12HourFormat(time) {
            let [hour, minute] = time.split(':');
            hour = parseInt(hour);
            let period = hour >= 12 ? 'PM' : 'AM';
            hour = hour % 12 || 12; // Convert 0 to 12 for midnight
            return `${hour}:${minute} ${period}`;
        }



        function changeMonth(change) {
            let currentMonth = parseInt(document.getElementById("currentMonth").value);
            let currentYear = parseInt(document.getElementById("currentYear").value);
            
            currentMonth += change;
            if (currentMonth < 1) {
                currentMonth = 12;
                currentYear--;
            } else if (currentMonth > 12) {
                currentMonth = 1;
                currentYear++;
            }

            loadCalendar(currentMonth, currentYear);
        }

        function showAddEventModal() {
            let eventDateInput = document.getElementById("eventDate");

            if (selectedDate) {
                eventDateInput.value = selectedDate; // Set the selected date in modal
            } else {
                eventDateInput.value = ""; // Default if no date selected
            }

            document.getElementById("eventModal").classList.add("show");
        }

        function closeModal() {
            document.getElementById("eventModal").classList.remove("show");
        }

        function addEvent(event) {
            event.preventDefault(); // Prevent default form submission

            let formData = new FormData(document.getElementById("eventForm"));

            fetch("add_event.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    closeModal();

                    let eventDate = new Date(formData.get("date"));
                    let year = eventDate.getFullYear();
                    let month = eventDate.getMonth() + 1;
                    let day = eventDate.getDate();

                    // Refresh event list
                    showEvents(year, month, day);

                    // Refresh the calendar
                    loadCalendar(month, year);

                    // Clear form fields after successful submission
                    document.getElementById("eventForm").reset();
                }
            })
            .catch(error => console.error("Error:", error));
        }

        window.onload = function () {
            let date = new Date();
            let todayYear = date.getFullYear();
            let todayMonth = date.getMonth() + 1;
            let todayDay = date.getDate();

            loadCalendar(todayMonth, todayYear);

            setTimeout(() => {
                showEvents(todayYear, todayMonth, todayDay); 
            }, 100); // Delay to ensure DOM elements exist
        };


    </script>

</head>
<body>

    <nav>
        <div class="logo-container">
            <img src="images/3s logo.png">
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
            
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/calendar_icon_active.png" alt="">
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

    <div class="calendar-container">
    <div class="container">
            <aside class="event-panel">
                <h2 id="selectedDate" style="text-align: center;"></h2>
                <hr>
                <h3 class="title">Events</h3>
                <div id="eventList"></div>
                <div class="btn-con">
                    <button class="addEvent-btn" onclick="showAddEventModal()"><img src="./images/icons/add-event-icon.png" alt="Add Event Icon" class="addEvent-icon">Add Event</button>
                </div>
                
            </aside>

        <main id="calendar"></main>
        </div>

        <div id="eventModal" class="modal">
            <div class="modal-content">
                <h2 class="modal-title">Add Event</h2>

                <form id="eventForm" enctype="multipart/form-data">
                    <div class="form-container">
                        <div class="row">
                            <label for="eventName">Name of event</label>
                            <input type="text" id="eventName" name="title" required>
                        </div>
                        <div class="row">
                            <label for="eventDate">Date</label>
                            <input type="date" id="eventDate" name="date" required>
                        </div>
                        <div class="group-row">
                            <div class="row">
                                <label for="startTime">Start Time</label>
                                <input type="time" id="startTime" name="start" required>
                            </div>
                            <div class="row">
                                <label for="endTime">End Time</label>
                                <input type="time" id="endTime" name="end" required>
                            </div>
                        </div>
                        <div class="row">
                            <label for="venue">Venue</label>
                            <input type="text" id="venue" name="venue" required>
                        </div>
                        <div class="row">
                            <div class="file-upload">
                                <label for="eventImage" class="custom-file-upload">
                                    <i class="fas fa-cloud-upload-alt"></i> Add Image
                                </label>
                                <input type="file" id="eventImage" name="image" onchange="updateFileName()" accept="image/*">
                                <span id="file-name">No file chosen</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                        <button type="button" class="btn-post" onclick="addEvent(event)">Post</button>
                    </div>
                </form>

            </div>
        </div>

        <input type="hidden" id="currentMonth" value="<?= $month ?>">
        <input type="hidden" id="currentYear" value="<?= $year ?>">
        </div>
    </div>
    
</body>
</html>
