<?php
session_start();
include 'config.php';

$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$profilePic = 'images/uploads/profile_pictures/profile-placeholder.png'; // default picture

if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && !empty($user['profile_picture'])) {
        $profilePic = htmlspecialchars($user['profile_picture']);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <title>Calendar</title>
    <link rel="stylesheet" href="css/calendar.css">
    <link rel="stylesheet" href="css/nav_footer.css">

    <style>
        .month-navigation {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            padding: 17px;
            box-shadow: 0px 3px 3px rgba(0, 0, 0, 0.3); /* Only bottom shadow */
        }

        .month-navigation h2 {
            font-size: 24px;
            font-weight: bold;
            color: #8B0000;
            min-width: 200px;
            text-align: center
        }

        .nav-btn {
            width: 40px;
            height: 40px;
            border: 2px solid #8B0000;
            background: white;
            border-radius: 50%;
            font-size: 20px;
            color: #8B0000;
            cursor: pointer;
            transition: 0.3s ease-in-out;
        }

        .nav-btn:hover {
            background: #8B0000;
            color: white;
        }
    </style>

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
<body>
    <nav>
        <div class="logo-container">
            <img src="images/3s logo.png">
            <p>Maru-Health <br> Barangay Marulas 3S <br> Health Station</p>
        </div>

        <div class="nav-links">
            <ul>
                <li><a href="index.php">HOME</a></li>
                <li><a href="calendar.php">CALENDAR</a></li>
                <li><a href="request_medicine.php" class="links">MEDICINE REQUEST</a></li>
                <li><a href="about_us.php">ABOUT US</a></li>

                <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'user'): ?>
                    <li>
                        <a href="profile.php" class="profile">
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile Picture" class="nav-profile-pic">
                        </a>                    
                    </li>
                <?php elseif (!isset($_SESSION['admin_id'])): ?>
                    <li><a href="login.php" class="login-button">LOG IN</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="container">
        <aside class="event-panel">
            <h2 id="selectedDate" style="text-align: center;" ></h2>
            <hr>
            <h3 class="title">Events</h3>
            <div id="eventList">
                <!-- Events load dynamically here -->
            </div>
        </aside>

        <main id="calendar">
            <!-- Calendar loads dynamically here -->
        </main>
    </div>

    <input type="hidden" id="currentMonth" value="<?= $month ?>">
    <input type="hidden" id="currentYear" value="<?= $year ?>">

</body>
</html>
