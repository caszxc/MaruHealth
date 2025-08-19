<?php
session_start();
require_once "config.php";
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Count supplements
$supplementQuery = "SELECT name, SUM(quantity) AS total_stock FROM medicines WHERE type = 'supplement' GROUP BY name";
$supplementStmt = $conn->prepare($supplementQuery);
$supplementStmt->execute();
$supplements = $supplementStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for Chart.js
$medicineNames = [];
$remainingStocks = [];

foreach ($supplements as $index => $supplement) {
    $medicineNames[] = $supplement['name'];
    $remainingStocks[] = $supplement['total_stock'];
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
                <a href="admin_dashboard.php" class="<?= ($current_page == 'admin_dashboard.php' || $current_page == 'medicines_stats.php') ? 'active' : '' ?>">Dashboard</a>
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
                <img class="menu-icon" src="images/icons/calendar_icon.png" alt="">
                <a href="content_management.php" class="<?= $current_page == 'content_management.php' ? 'active' : '' ?>">Content Management</a>
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
            <a href="medicines_stats.php" class="back-button">← Back</a>
            <h2>Supplement</h2>
        </div>
        <h3>Remaining Stocks</h3>
        <div class="tiles-container">
            <?php foreach ($supplements as $supplement): ?>
                <a href="">
                    <div class="tile">
                        <img src="images/icons/medicines_icon_active.png" alt="">
                        <h3><?= htmlspecialchars($supplement['name']) ?></h3>
                        <p><?= $supplement['total_stock'] ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="two-chart-container">
            <div class="pie-box">
                <div>
                    <h3 style="margin-bottom: 10px;">Total Supplement Distribution</h3>
                </div>
                <div class="pie-con">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
            <div class="bar-box">
                <div>
                    <h3 style="margin-bottom: 10px;">Total Supplement Distribution</h3>
                </div>
                <div class="bar-con">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
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
        const remainingStocks = <?= json_encode($remainingStocks) ?>;
         
        const backgroundColors = generateColors(medicineNames.length);

        // PIE CHART (with percentages)
        const pieCtx = document.getElementById('pieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: medicineNames,
                datasets: [{
                    label: 'Remaining Stocks',
                    data: remainingStocks,
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
                    label: 'Remaining Stocks',
                    data: remainingStocks,
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
                        ticks: { stepSize: 5 },
                        title: { display: true, text: 'Number of Medicine' }
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
    </script>
    

</body>
</html>
