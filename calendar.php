<?php
// calendar.php
session_start();
include 'config.php';
include 'settings.php';

$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$profilePic = 'images/uploads/profile_pictures/profile-placeholder.png'; // default picture

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'user') {
    // Use active_user_id if set, otherwise fall back to user_id
    $active_user_id = isset($_SESSION['active_user_id']) ? $_SESSION['active_user_id'] : $_SESSION['user_id'];

    // Validate the active_user_id
    $stmt = $conn->prepare("SELECT profile_picture, primary_user_id FROM users WHERE id = :active_user_id");
    $stmt->bindParam(':active_user_id', $active_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ensure the user exists and is either the primary user or a dependent of the logged-in primary user
    if ($user && ($active_user_id == $_SESSION['user_id'] || $user['primary_user_id'] == $_SESSION['user_id'])) {
        if (!empty($user['profile_picture'])) {
            $profilePic = htmlspecialchars($user['profile_picture']);
        }
    } else {
        // Invalid active_user_id, fall back to default picture
        $profilePic = 'images/uploads/profile_pictures/profile-placeholder.png';
    }
}

require_once "deletion_notice.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <title>Calendar</title>
    <link rel="stylesheet" href="css/calendar.css">
    <link rel="stylesheet" href="css/nav_footer.css">

    <script>
        function loadCalendar(month, year) {
            fetch(`calendar_ajax.php?month=${month}&year=${year}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById("calendar").innerHTML = html;
                document.getElementById("currentMonth").value = month;
                document.getElementById("currentYear").value = year;
                
                // After loading the calendar, handle today's date if we're in the current month/year
                const today = new Date();
                if (today.getMonth() + 1 === month && today.getFullYear() === year) {
                    // Let's trigger the click on today's date to show today's events
                    const todayDay = today.getDate();
                    setTimeout(() => {
                        showEvents(year, month, todayDay);
                    }, 100);
                }
            });
        }
        
        function showEvents(year, month, day) {
            // First, remove selected-day class from all cells
            document.querySelectorAll('#calendar td').forEach(cell => {
                cell.classList.remove('selected-day');
            });
            
            // Add selected-day class to the clicked cell
            const selectedDate = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const selectedCell = document.querySelector(`td[data-date="${selectedDate}"]`);
            if (selectedCell) {
                selectedCell.classList.add('selected-day');
            }

            // Format the date for display
            const date = new Date(year, month - 1, day);
            const options = { month: 'long', day: 'numeric', weekday: 'long' };
            const formattedDate = date.toLocaleDateString('en-US', options);

            document.getElementById("selectedDate").innerText = formattedDate;

            // Fetch and display events
            fetch(`get_events.php?date=${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`)
                .then(response => response.json())
                .then(data => {
                    let eventList = document.getElementById("eventList");
                    eventList.innerHTML = "";

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

                            let startTime = formatTime(event.start);
                            let endTime = formatTime(event.end);

                            eventDetails.innerHTML = `<strong>${event.title}</strong>${startTime} - ${endTime}<br><em>${event.venue}</em>`;

                            eventCard.appendChild(eventImg);
                            eventCard.appendChild(eventDetails);
                            eventList.appendChild(eventCard);
                        });
                    }
                })
            .catch(error => console.error("Error loading events:", error));
        }

        // Function to convert military time (24-hour) to 12-hour format with AM/PM
        function formatTime(time) {
            let [hours, minutes] = time.split(':');
            hours = parseInt(hours);
            let period = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12; // Convert 0 to 12 for 12 AM
            return `${hours}:${minutes} ${period}`;
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

        window.onload = function () {
            let date = new Date();
            let todayYear = date.getFullYear();
            let todayMonth = date.getMonth() + 1;
            let todayDay = date.getDate();

            loadCalendar(todayMonth, todayYear);

            // Give time for the calendar to load before trying to show events
            setTimeout(() => {
                showEvents(todayYear, todayMonth, todayDay); 
            }, 300); // Increased delay to ensure DOM elements exist
        };

        
    </script>
</head>
<!-- ==== EVENT IMAGE MODAL ==== -->
<div id="eventImageModal" class="event-image-modal">
    <span class="modal-close">&times;</span>
    <img class="modal-content" id="modalImage" src="" alt="Event image">
    <div id="modalCaption"></div>
</div>
<body>
    <nav>
        <div class="logo-container">
            <img src="<?= $logo_url ?>" alt="Logo">
            <div>
                <h1>
                    <span class="maruhealth"><?= htmlspecialchars($site_name) ?></span>
                    <span class="barangay-title">Barangay Marulas 3S Health Center</span>
                </h1>
            </div>
        </div>

        <!-- Hamburger Icon for Small Screens -->
        <div class="menu-toggle" id="menu-toggle">
            <i class="fa fa-bars"></i>
        </div>

        <div class="nav-links">
            <ul>
                <li><a href="index.php" class="links">HOME</a></li>
                <li><a href="calendar.php" class="links">CALENDAR</a></li>
                <li><a href="request_medicine.php" class="links">MEDICINE REQUEST</a></li>
                <li><a href="about_us.php" class="links">ABOUT US</a></li>

                <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'user'): ?>
                    <li class="profile-nav">
                        <a href="profile.php" class="profile">
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile Picture" class="nav-profile-pic">
                            <span class="nav-profile-name"><?= htmlspecialchars($_SESSION['name'] ?? '') ?></span>
                        </a>                    
                    </li>
                <?php elseif (!isset($_SESSION['admin_id'])): ?>
                    <li><a href="login.php" class="login-button">LOG IN</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

   <div class="container">
        <div class="calendar-container">
            <aside class="event-panel">
                <div class="event-header">
                    <h2 id="selectedDate" style="text-align: center;"></h2>
                    <hr>
                    <h3 class="title">Events</h3>
                </div>
                
                <div class="event-container">
                    <div class="event-wrapper">
                        <div id="eventList"></div>
                    </div>
                </div>
            </aside>

            <main id="calendar"></main>
        </div>
    </div>

    <input type="hidden" id="currentMonth" value="<?= $month ?>">
    <input type="hidden" id="currentYear" value="<?= $year ?>">

    <script>
        // Select the hamburger toggle and navigation links container
        const menuToggle = document.getElementById('menu-toggle');
        const navLinks = document.querySelector('.nav-links');

        // Toggle the menu visibility when the hamburger icon is clicked
        menuToggle.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            menuToggle.classList.toggle('open');

            // Change icon (bars ↔ close)
            const icon = menuToggle.querySelector('i');
            if (menuToggle.classList.contains('open')) {
                icon.classList.replace('fa-bars', 'fa-times');
            } else {
                icon.classList.replace('fa-times', 'fa-bars');
            }
        });

        // Optional: close menu when a link is clicked (on mobile)
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                if (navLinks.classList.contains('active')) {
                    navLinks.classList.remove('active');
                    menuToggle.classList.remove('open');
                    const icon = menuToggle.querySelector('i');
                    icon.classList.replace('fa-times', 'fa-bars');
                }
            });
        });

        /* ==== EVENT IMAGE MODAL ==== */
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('eventImageModal');
            const modalImg = document.getElementById('modalImage');
            const closeBtn = document.querySelector('.modal-close');

            // Open modal when an event-card image is clicked
            document.getElementById('eventList').addEventListener('click', e => {
                const img = e.target.closest('.event-card img');
                if (!img) return;

                modal.style.display = 'flex';
                modalImg.src = img.src;
                document.body.style.overflow = 'hidden';   // prevent background scroll
            });

            // Close modal
            const closeModal = () => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            };
            closeBtn.onclick = closeModal;
            modal.onclick = e => { if (e.target === modal) closeModal(); };
        });
    </script>
    
</body>
</html>
