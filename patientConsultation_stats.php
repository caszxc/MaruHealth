<?php
session_start();
require_once "config.php";
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Count general check up
$generalCheckUpQuery = "SELECT COUNT(*) AS generalCheckUp FROM consultations WHERE consultation_type = 'General Check Up'";
$generalCheckUpStmt = $conn->prepare($generalCheckUpQuery);
$generalCheckUpStmt->execute();
$generalCheckUpCount = $generalCheckUpStmt->fetch(PDO::FETCH_ASSOC)['generalCheckUp'];

// Count vaccination
$vaccinationQuery = "SELECT COUNT(*) AS vaccination FROM consultations WHERE consultation_type = 'vaccination'";
$vaccinationStmt = $conn->prepare($vaccinationQuery);
$vaccinationStmt->execute();
$vaccinationCount = $vaccinationStmt->fetch(PDO::FETCH_ASSOC)['vaccination'];

// Count prenatal
$prenatalQuery = "SELECT COUNT(*) AS prenatal FROM consultations WHERE consultation_type = 'prenatal'";
$prenatalStmt = $conn->prepare($prenatalQuery);
$prenatalStmt->execute();
$prenatalCount = $prenatalStmt->fetch(PDO::FETCH_ASSOC)['prenatal'];

// Count dentistry
$dentistryQuery = "SELECT COUNT(*) AS dentistry FROM consultations WHERE consultation_type = 'dentistry'";
$dentistryStmt = $conn->prepare($dentistryQuery);
$dentistryStmt->execute();
$dentistryCount = $dentistryStmt->fetch(PDO::FETCH_ASSOC)['dentistry'];

// Count family planning
$familyPlanningQuery = "SELECT COUNT(*) AS familyPlanning FROM consultations WHERE consultation_type = 'Family Planning'";
$familyPlanningStmt = $conn->prepare($familyPlanningQuery);
$familyPlanningStmt->execute();
$familyPlanningCount = $familyPlanningStmt->fetch(PDO::FETCH_ASSOC)['familyPlanning'];

// Fetch all consultations with their counts
$consultationQuery = "
    SELECT consultation_type, COUNT(*) AS consultation_count
    FROM consultations
    GROUP BY consultation_type
    ORDER BY consultation_count DESC";  

$consultationStmt = $conn->prepare($consultationQuery);
$consultationStmt->execute();
$consultations = $consultationStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for Chart.js
$consultationType = [];
$consultationCounts = [];

foreach ($consultations as $consultation) {
    $consultationType[] = $consultation['consultation_type'];
    $consultationCounts[] = (int) $consultation['consultation_count'];
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
            DATE_FORMAT(consultation_date, '%Y-%u') AS week, 
            consultation_type, 
            COUNT(*) AS count
        FROM consultations
        WHERE YEAR(consultation_date) = :year
        GROUP BY week, consultation_type
        ORDER BY week ASC";
        break;   

    case 'yearly':
        $dateQuery = "
        SELECT 
            YEAR(consultation_date) AS year, 
            consultation_type, 
            COUNT(*) AS count
        FROM consultations
        GROUP BY year, consultation_type
        ORDER BY year ASC";
        break;   

    case 'custom':
        if ($start && $end) {
            $dateQuery = "
            SELECT 
                DATE_FORMAT(consultation_date, '%Y-%m-%d') AS date, 
                consultation_type, 
                COUNT(*) AS count
            FROM consultations
            WHERE consultation_date BETWEEN :start AND :end
            GROUP BY date, consultation_type
            ORDER BY consultation_date ASC    ";
        } else {
            $dateQuery = ""; // Handle error case if needed
        }
        break;
        
        
    case 'monthly':
        default:
            $dateQuery = "
            SELECT DATE_FORMAT(consultation_date, '%M') AS month, consultation_type, COUNT(*) AS count
            FROM consultations
            WHERE YEAR(consultation_date) = :year
            GROUP BY month, consultation_type
            ORDER BY MONTH(STR_TO_DATE(month, '%b'))";
        break;   
}

$monthlyStmt = $conn->prepare($dateQuery);

// Bind the necessary parameters based on the filter
if ($filter === 'custom' && $start && $end) {
    $monthlyStmt->bindParam(':start', $start);
    $monthlyStmt->bindParam(':end', $end);
} else {
    if ($filter !== 'yearly') {
        // Bind :year parameter for all filters except 'yearly'
        $monthlyStmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
    }
}

$monthlyStmt->execute();
$monthlyResults = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

// Create an array of month names for easier handling later
$allMonths = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

// Process data for JS
$monthlyGrouped = [];
$months = [];

foreach ($monthlyResults as $row) {
    $label = '';

    if ($filter === 'monthly') {
        $label = $row['month'];
    } elseif ($filter === 'weekly') {
        $label = "Week " . explode('-', $row['week'])[1];
    } elseif ($filter === 'yearly') {
        $label = $row['year'];
    } elseif ($filter === 'custom') {
        $label = $row['date'];
    }

    if (!in_array($label, $months)) {
        $months[] = $label;
    }

    $type = $row['consultation_type'];
    $monthlyGrouped[$type][$label] = $row['count'];
}

// Now format for Chart.js
$formattedData = [];
$colors = ['#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#F44336', '#00BCD4', '#8BC34A'];
$colorIndex = 0;

foreach ($monthlyGrouped as $type => $data) {
    $dataset = [
        'label' => $type,
        'data' => [],
        'backgroundColor' => $colors[$colorIndex % count($colors)],
    ];

    foreach ($months as $month) {
        $dataset['data'][] = $data[$month] ?? 0;
    }

    $formattedData[] = $dataset;
    $colorIndex++;
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
                <a href="admin_dashboard.php" class="<?= ($current_page == 'admin_dashboard.php' || $current_page == 'patientConsultation_stats.php') ? 'active' : '' ?>">Dashboard</a>
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
            <a href="admin_dashboard.php" class="back-button">← Back</a>
            <h2>Patient Consultation</h2>
        </div>
        <div class="tiles-container">
            <a href="">
                <div class="tile">
                    <img src="images/icons/genCheck_active.png" alt="">
                    <h3>General Check Up</h3>
                    <p><?= $generalCheckUpCount ?></p>
                </div>
            </a>
            <a href="">
                <div class="tile">
                    <img src="images/icons/vaccination_active.png" alt="">
                    <h3>Vaccination</h3>
                    <p><?= $vaccinationCount ?></p>
                </div>
            </a>
            <a href="">
                <div class="tile">
                    <img src="images/icons/prenatal_active.png" alt="">
                    <h3>Prenatal</h3>
                    <p><?= $prenatalCount ?></p>
                </div>
            </a>
            <a href="">
                <div class="tile">
                    <img src="images/icons/dentistry_active.png" alt="">
                    <h3>Dentistry</h3>
                    <p><?= $dentistryCount ?></p>
                </div>
            </a>
            <a href="">
                <div class="tile">
                    <img src="images/icons/familyP_active.png" alt="">
                    <h3>Family Planning</h3>
                    <p><?= $familyPlanningCount ?></p>
                </div>
            </a>
            
        </div>

        <div class="two-chart-container">
            <div class="pie-box">
                <div>
                    <h3 style="margin-bottom: 10px;">Patient Consultation</h3>
                </div>
                <div class="pie-con">
                    <canvas id="consultationChart"></canvas>
                </div>
            </div>
        </div>

        <div class="chart-container">
        <div class="filter-title">
                <h3 style="margin-bottom: 10px;"> 
                    <?php
                    switch ($filter) {
                        case 'weekly':
                            echo "Weekly Total Patient Consultation";
                            break;
                        case 'monthly':
                            echo "Monthly Total Patient Consultation";
                            break;
                        case 'yearly':
                            echo "Yearly Total Patient Consultation";
                            break;
                        case 'custom':
                            echo "Total Patient Consultation from " . date("M j, Y", strtotime($start)) . " to " . date("M j, Y", strtotime($end));
                            break;
                        default:
                            echo "Total Patient Consultation";
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
            <canvas id="barChart" style="margin: 0 20px;"></canvas>
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
        const consultationType = <?= json_encode($consultationType) ?>;
        const consultationCounts = <?= json_encode($consultationCounts) ?>;
        const backgroundColors = generateColors(consultationType.length);

        // PIE CHART (with percentages)
        const pieCtx = document.getElementById('consultationChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: consultationType,
                datasets: [{
                    label: 'Count',
                    data: consultationCounts,
                    backgroundColor: backgroundColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
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

        const months = <?= json_encode($months) ?>;
        const barGroupedData = <?= json_encode($formattedData) ?>;
        const colors = generateColors(barGroupedData.length);

        // Assign matching colors to bar datasets
        barGroupedData.forEach((dataset, index) => {
            dataset.backgroundColor = colors[index];
        });


        // BAR CHART - Monthly Patient Consultations
        const barCtx = document.getElementById('barChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: barGroupedData
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { size: 12 },
                            usePointStyle: true
                        }
                    },
                    title: {
                        display: false,
                        text: 'Monthly Consultations'
                    }
                },
                scales: {
                    y: {
                        ticks: { stepSize: 1 },
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Consultations'
                        }
                    }
                }
            }
        });  
    </script>

</body>
</html>
