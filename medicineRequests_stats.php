<?php
session_start();
require_once "config.php";
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Fetch all requested medicines with their counts
$medicineQuery = "
    SELECT medicine_name, COUNT(*) AS request_count
    FROM medicine_requests
    GROUP BY medicine_name
    ORDER BY request_count DESC";  

$medicineStmt = $conn->prepare($medicineQuery);
$medicineStmt->execute();
$medicines = $medicineStmt->fetchAll(PDO::FETCH_ASSOC);



// Prepare data for Chart.js
$medicineNames = [];
$requestCounts = [];

foreach ($medicines as $index => $medicine) {
    $medicineNames[] = $medicine['medicine_name'];
    $requestCounts[] = $medicine['request_count'];
}

//bottom bar chart
$filter = $_GET['filter'] ?? 'monthly';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$selectedYear = $_GET['year'] ?? date('Y');

// Prepare the query based on the selected filter
switch ($filter) {
    case 'weekly':
        $dateQuery = "
            SELECT 
                CONCAT(
                    DATE_FORMAT(DATE_ADD(request_date, INTERVAL(1 - DAYOFWEEK(request_date)) DAY), '%b %e'),
                    ' - ',
                    DATE_FORMAT(DATE_ADD(request_date, INTERVAL(7 - DAYOFWEEK(request_date)) DAY), '%b %e')
                ) AS label,
                COUNT(*) AS total
            FROM medicine_requests
            WHERE YEAR(request_date) = :year
              AND YEAR(request_date) = :year  -- Use selected year
            GROUP BY YEARWEEK(request_date, 1)
            ORDER BY MIN(request_date)";
        break;   

    case 'yearly':
        $dateQuery = "
        SELECT YEAR(request_date) AS label, COUNT(*) AS total
            FROM medicine_requests
            GROUP BY label
            ORDER BY label ASC";
        break;   

    case 'custom':
        if ($start && $end) {
            $dateQuery = "
                SELECT 'Custom Range' AS label, COUNT(*) AS total
                FROM medicine_requests
                WHERE DATE(request_date) BETWEEN :start AND :end";
        } else {
            $dateQuery = ""; // Handle error case if needed
        }
        break;
        
        
    case 'monthly':
        default:
            $dateQuery = "
            SELECT DATE_FORMAT(request_date, '%M') AS label, COUNT(*) AS total
            FROM medicine_requests
            WHERE YEAR(request_date) = :year
            GROUP BY MONTH(request_date)
            ORDER BY MONTH(request_date)";
        break;   
}

$monthlyRequestStmt = $conn->prepare($dateQuery);

// Bind the necessary parameters based on the filter
if ($filter === 'custom' && $start && $end) {
    $monthlyRequestStmt->bindParam(':start', $start);
    $monthlyRequestStmt->bindParam(':end', $end);
} else {
    if ($filter !== 'yearly') {
        // Bind :year parameter for all filters except 'yearly'
        $monthlyRequestStmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
    }
}

$monthlyRequestStmt->execute();
$monthlyData = $monthlyRequestStmt->fetchAll(PDO::FETCH_ASSOC);

// Create an array of month names for easier handling later
$allMonths = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

$monthlyTotals = [];
foreach ($monthlyData as $data) {
    $monthlyTotals[$data['label']] = (int) $data['total'];
}


$months = [];
$totalRequests = [];

if ($filter === 'monthly') {
    $months = $allMonths;
    foreach ($allMonths as $month) {
        $totalRequests[] = $monthlyTotals[$month] ?? 0;
    }
} else if ($filter === 'custom' && $start && $end) {
    $startDate = date("M j", strtotime($start));
    $endDate = date("M j, Y", strtotime($end));
    $months[] = $startDate . ' - ' . $endDate;
    $totalRequests[] = (int) $monthlyData[0]['total'];
} else {
    foreach ($monthlyTotals as $label => $count) {
        $months[] = $label;
        $totalRequests[] = $count;
    }
}

// Fetch the admin's name
$adminStmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE role = 'admin' LIMIT 1");
$adminStmt->execute();
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

// Default to "Admin" if no admin is found
$adminName = $admin ? $admin['first_name'] . ' ' . $admin['last_name'] : 'Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


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
                <p class="role">Admin</p>
            </div>
        </div>
        <div class="menu">
            <?php 
                $current_page = basename($_SERVER['PHP_SELF']); 
            ?>
            <p class="menu-header">ANALYTICS</p>

            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/dashboard_icon_active.png" alt="">
                <a href="admin_dashboard.php" class="<?= ($current_page == 'admin_dashboard.php' || $current_page == 'medicineRequests_stats.php') ? 'active' : '' ?>">Dashboard</a>
            </div>
            

            <p class="menu-header">BASE</p>
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
            
            
            <p class="menu-header">OTHERS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/logout_icon.png" alt="">
                <a href="logout.php" class="logout-button">Log Out</a>
            </div>
            
        </div>
    </div>

    <div class="dashboard-content">
        <div class="title-con">
            <a href="admin_dashboard.php" class="back-button">← Back</a>
            <h2>Medicine Requests</h2>
        </div>
        <h3>Most Requested Medicines</h3>
        <div class="tiles-container">
            <?php
            if (!empty($medicines)) {
                $maxRequestCount = $medicines[0]['request_count']; // Get the highest request count
                foreach ($medicines as $medicine) {
                    if ($medicine['request_count'] == $maxRequestCount) { // Display all medicines with the highest count
            ?>
                    <a href="#">
                        <div class="tile">
                            <img src="images/icons/medicines_icon_active.png" alt="">
                            <h3><?= htmlspecialchars($medicine['medicine_name']) ?></h3>
                            <p><?= $medicine['request_count'] ?></p>
                        </div>
                    </a>
            <?php
                    }
                }
            } else {
                echo "<p>No data available.</p>";
            }
            ?>
        </div>
        
        <div class="two-chart-container">
            <div class="pie-box">
                <div>
                    <h3 style="margin-bottom: 10px;">Most Requested Medicine</h3>
                </div>
                <div class="pie-con">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
            <div class="bar-box">
                <div>
                    <h3 style="margin-bottom: 10px;">Most Requested Medicine</h3>
                </div>
                <div class="bar-con">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>

        <div class="chart-container">
            <div class="filter-title">
                <h3 style="margin-bottom: 10px;"> 
                    <?php
                    switch ($filter) {
                        case 'weekly':
                            echo "Weekly Total Request";
                            break;
                        case 'monthly':
                            echo "Monthly Total Request";
                            break;
                        case 'yearly':
                            echo "Yearly Total Request";
                            break;
                        case 'custom':
                            echo "Total Request from " . date("M j, Y", strtotime($start)) . " to " . date("M j, Y", strtotime($end));
                            break;
                        default:
                            echo "Total Request";
                            break;
                    }
                    ?>
                </h3>
                <div class="filter-con">
                    <form method="GET" class="filter-form" style="margin: 10px;" onsubmit="return validateDates()">
                        <select name="filter" id="filter" onchange="toggleDateInputs(this.value)">
                            <option value="monthly" <?= (isset($_GET['filter']) && $_GET['filter'] == 'monthly') ? 'selected' : '' ?>>Monthly</option>
                            <option value="weekly" <?= (isset($_GET['filter']) && $_GET['filter'] == 'weekly') ? 'selected' : '' ?>>Weekly</option>
                            <option value="yearly" <?= (isset($_GET['filter']) && $_GET['filter'] == 'yearly') ? 'selected' : '' ?>>Yearly</option>
                            <option value="custom" <?= (isset($_GET['filter']) && $_GET['filter'] == 'custom') ? 'selected' : '' ?>>Specific Date</option>
                        </select>

                        <div id="year-container" style="display:none;">
                            <select name="year" id="year">
                                <?php
                                $currentYear = date("Y");
                                for ($i = $currentYear; $i >= 2000; $i--) {
                                    echo "<option value=\"$i\" " . (($i == ($_GET['year'] ?? $currentYear)) ? 'selected' : '') . ">$i</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div id="custom-date-container" style="display:none;">
                            <input type="date" name="start" id="start" value="<?= $_GET['start'] ?? '' ?>" style="display:inline-block;">
                            <span>-</span>
                            <input type="date" name="end" id="end" value="<?= $_GET['end'] ?? '' ?>" style="display:inline-block;">
                        </div>

                        <button class="generate-btn" type="submit">Generate Report</button>
                    </form>
                </div>
            </div>
            <canvas id="requestChart" style="margin: 0 20px;"></canvas>
        </div>
    </div>

    <script>
        function validateDates() {
            const filter = document.getElementById('filter').value;
            const start = document.getElementById('start');
            const end = document.getElementById('end');

            // Only validate when 'custom' filter is selected
            if (filter === 'custom') {
                if (!start.value || !end.value) {
                    alert('Both start and end dates are required for the "Specific Date" filter.');
                    return false; // Prevent form submission if dates are missing
                }
            }
            return true; // Allow form submission if validation passes
        }

        function toggleDateInputs(filter) {
            const dateContainer = document.getElementById('custom-date-container');
            const start = document.getElementById('start');
            const end = document.getElementById('end');
            const yearContainer = document.getElementById('year-container');
            const display = (filter === 'custom') ? 'inline-block' : 'none';
            
            dateContainer.style.display = display;
            yearContainer.style.display = (filter === 'weekly' || filter === 'monthly') ? 'inline-block' : 'none';

            // Remove custom validity when switching away from 'custom' filter
            if (filter !== 'custom') {
                start.setCustomValidity('');
                end.setCustomValidity('');
            }
        }

        window.onload = () => toggleDateInputs(document.getElementById('filter').value);

        // Generate dynamic colors
        function generateColors(count) {
            const colors = [];
            for (let i = 0; i < count; i++) {
                colors.push(`hsl(${(i * 60) % 360}, 70%, 50%)`); // Generates distinct hues
            }
            return colors;
        }

        // Convert PHP arrays to JavaScript
        const medicineNames = <?= json_encode($medicineNames) ?>;
        const requestCounts = <?= json_encode($requestCounts) ?>;
        const backgroundColors = generateColors(medicineNames.length);

        // PIE CHART (with percentages)
        const pieCtx = document.getElementById('pieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: medicineNames,
                datasets: [{
                    label: 'Medicine Requests',
                    data: requestCounts,
                    backgroundColor: backgroundColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'left',
                        labels: {
                            font: { size: 14 },
                            usePointStyle: true,
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(tooltipItem) {
                                const total = requestCounts.reduce((a, b) => a + b, 0);
                                const percentage = ((tooltipItem.raw / total) * 100).toFixed(1);
                                return `${tooltipItem.label}: ${tooltipItem.raw} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // BAR CHART
        const barCtx = document.getElementById('barChart').getContext('2d');

        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: medicineNames,
                datasets: [{
                    label: 'Requests',
                    data: requestCounts,
                    backgroundColor: backgroundColors,
                    borderColor: '#640000',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 },
                        title: { display: true, text: 'Number of Requests' }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (tooltipItem) => `Requests: ${tooltipItem.raw}`
                        }
                    }
                }
            }
        });

        // MONTHLY REQUEST CHART (Bar Chart)
        const requestCtx = document.getElementById('requestChart').getContext('2d');
        const months = <?= json_encode($months) ?>;  // This now holds months as "March 2025"
        const totalRequests = <?= json_encode($totalRequests) ?>;
        const requestGradient = requestCtx.createLinearGradient(0, 0, 0, 400);
        requestGradient.addColorStop(0, "#8B0000");
        requestGradient.addColorStop(1, "#D32F2F");

        new Chart(requestCtx, {
            type: 'bar',
            data: {
                labels: months,  // Months in word format (e.g., "March 2025")
                datasets: [{
                    label: 'Requests',
                    data: totalRequests,
                    borderColor: '#8B0000',
                    backgroundColor: requestGradient
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false, position: 'bottom' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 },
                        title: { display: true, text: 'Number of Requests' }
                    }
                }
            }
        });
    </script>


</body>
</html>
