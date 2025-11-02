<?php
// healthstaff_dashboard.php
session_start();
require_once "config.php";

// Check if user is logged in and is health staff
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'health_staff') {
    header("Location: login.php");
    exit();
}

// Fetch staff info
$adminId = $_SESSION['admin_id'];
$adminStmt = $conn->prepare("SELECT * FROM admin_staff WHERE id = :id");
$adminStmt->bindParam(':id', $adminId);
$adminStmt->execute();
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
$adminName = $admin ? $admin['full_name'] : $_SESSION['admin_name'];
$adminRole = $admin ? $admin['role'] : $_SESSION['admin_role'];
$displayRole = ucwords(str_replace('_', ' ', $adminRole));

// ---------------------------------------------------------------------
// 1. ALERTS
// ---------------------------------------------------------------------
// Expired batches
$expiredStmt = $conn->query("SELECT COUNT(*) FROM medicine_batches WHERE expiry_status = 'Expired'");
$expiredCount = $expiredStmt->fetchColumn();

// Expiring within a week
$weekStmt = $conn->query("SELECT COUNT(*) FROM medicine_batches WHERE expiry_status = 'Expiring within a week'");
$expiringWeekCount = $weekStmt->fetchColumn();

// Expiring within a month
$monthStmt = $conn->query("SELECT COUNT(*) FROM medicine_batches WHERE expiry_status = 'Expiring within a month'");
$expiringMonthCount = $monthStmt->fetchColumn();

// Out of stock
$outOfStockStmt = $conn->query("SELECT COUNT(*) FROM medicines_catalog WHERE stock_status = 'Out of Stock'");
$outOfStockCount = $outOfStockStmt->fetchColumn();

// Low stock
$lowStockStmt = $conn->query("SELECT COUNT(*) FROM medicines_catalog WHERE stock_status = 'Low Stock'");
$lowStockCount = $lowStockStmt->fetchColumn();

// Pending medicine requests
$pendingReqStmt = $conn->query("SELECT COUNT(*) FROM medicine_requests WHERE request_status = 'pending'");
$pendingReqCount = $pendingReqStmt->fetchColumn();

// To be claimed
$toClaimStmt = $conn->query("SELECT COUNT(*) FROM medicine_requests WHERE request_status = 'to be claimed'");
$toClaimCount = $toClaimStmt->fetchColumn();

// Overdue reserved (claim_until_date < today and status = 'to be claimed')
$overdueStmt = $conn->prepare("
    SELECT COUNT(*) FROM medicine_requests 
    WHERE request_status = 'to be claimed' 
      AND claim_until_date < CURDATE()
");
$overdueStmt->execute();
$overdueCount = $overdueStmt->fetchColumn();

// ---------------------------------------------------------------------
// 2. SUMMARY
// ---------------------------------------------------------------------
$totalPatientsStmt = $conn->query("SELECT COUNT(*) FROM patients");
$totalPatients = $totalPatientsStmt->fetchColumn();

$totalFamiliesStmt = $conn->query("SELECT COUNT(*) FROM families");
$totalFamilies = $totalFamiliesStmt->fetchColumn();

$totalConsultationsStmt = $conn->query("SELECT COUNT(*) FROM consultations");
$totalConsultations = $totalConsultationsStmt->fetchColumn();

$totalMedicinesStmt = $conn->query("SELECT COUNT(*) FROM medicines_catalog");
$totalMedicines = $totalMedicinesStmt->fetchColumn();

$totalMedRequestsStmt = $conn->query("SELECT COUNT(*) FROM medicine_requests");
$totalMedRequests = $totalMedRequestsStmt->fetchColumn();

// ---------------------------------------------------------------------
// 3. RECENT ACTIVITIES (health staff only)
// ---------------------------------------------------------------------
$activityStmt = $conn->prepare("
    SELECT al.*, a.full_name AS admin_name
    FROM activity_logs al
    LEFT JOIN admin_staff a ON al.admin_id = a.id
    WHERE a.role = 'health_staff'
      AND al.admin_id IS NOT NULL
    ORDER BY al.created_at DESC
    LIMIT 8
");
$activityStmt->execute();
$activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper: Human-readable time ago
function timeAgo($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $interval = $now->diff($past);

    if ($interval->d == 0 && $interval->m == 0 && $interval->y == 0) {
        if ($interval->h > 0) return $interval->h . " hour" . ($interval->h > 1 ? "s" : "") . " ago";
        if ($interval->i > 0) return $interval->i . " minute" . ($interval->i > 1 ? "s" : "") . " ago";
        return "Just now";
    }
    if ($interval->d < 30) return $interval->d . " day" . ($interval->d > 1 ? "s" : "") . " ago";
    if ($interval->m < 12) return $interval->m . " month" . ($interval->m > 1 ? "s" : "") . " ago";
    return $interval->y . " year" . ($interval->y > 1 ? "s" : "") . " ago";
}

$stats = [

    'patients_total' => $totalPatients,
    'consultations_total' => $totalConsultations,
    'families_total' => $totalFamilies,

    // MEDICINE STATISTICS
    'catalog_total' => $totalMedicines,
    'batches_total' => $conn->query("SELECT COUNT(*) FROM medicine_batches")->fetchColumn(),
    'units_total'   => $conn->query("SELECT COALESCE(SUM(stocks), 0) FROM medicine_batches")->fetchColumn(),


    'requests_total'     => $totalMedRequests,
    'requests_pending'   => $conn->query("SELECT COUNT(*) FROM medicine_requests WHERE request_status = 'pending'")->fetchColumn(),
];


// ---------------------------------------------------------------------
// 4. SYSTEM LOGS (medicine request only)
// ---------------------------------------------------------------------
$medSmsLogs = $conn->query("
    SELECT * FROM sms_logs 
    WHERE message LIKE '%medicine%' OR message LIKE '%request%'
    ORDER BY sent_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$medEmailLogs = $conn->query("
    SELECT * FROM email_logs 
    WHERE subject LIKE '%medicine request%'
    ORDER BY sent_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Staff Dashboard</title>
    <link rel="stylesheet" href="css/healthstaff_dashboard.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    
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
            <img src="images/profile-placeholder.png" alt="Staff">
            <div class="profile-details">
                <p class="admin_name"><strong><?= htmlspecialchars($adminName) ?></strong></p>
                <p class="role"><?= htmlspecialchars($displayRole) ?></p>
            </div>
        </div>
        <div class="menu">
            <?php 
                $current_page = basename($_SERVER['PHP_SELF']); 
                $dashboard_url = 'healthstaff_dashboard.php';
            ?>
            <p class="menu-header">ANALYTICS</p>
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/dashboard_icon_active.png" alt="">
                <a href="<?= htmlspecialchars($dashboard_url) ?>" class="<?= $current_page == $dashboard_url ? 'active' : '' ?>">Dashboard</a>
            </div>
            <p class="menu-header">BASE</p>
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
            <h2>Health Staff Dashboard</h2>
        </div>
        <div class="dashboard-sections">
            <div class="section-wrapper">
                <!-- ==================== ALERTS ==================== -->
                <section class="alert-section">
                    <h3>Alerts</h3>
                    <div class="alert-grid">
                        <div class="alert-card <?= $overdueCount > 0 ? 'has-pending' : '' ?>" data-type="overdue">
                            <div class="alert-header">
                                <img src="images/icons/dashboard/overdue_icon.png" alt="Overdue" class="alert-icon">
                                <h4>Overdue Claims</h4>
                            </div>
                            <p class="pending-info">
                                <span class="pending-number"><?= $overdueCount ?></span> Requests
                            </p>
                            <a href="medicine_requests.php?status=overdue" class="manage-link">Notify</a>
                        </div>
                        <div class="alert-card <?= $expiredCount > 0 ? 'has-pending' : '' ?>" data-type="expired">
                            <div class="alert-header">
                                <img src="images/icons/dashboard/expired_icon.png" alt="Expired" class="alert-icon">
                                <h4>Expired Batches</h4>
                            </div>
                            <p class="pending-info">
                                <span class="pending-number"><?= $expiredCount ?></span> Medicine Batches
                            </p>
                            <a href="view_expiring.php?filter=expired" class="manage-link">View</a>
                        </div>

                        <div class="alert-card <?= $outOfStockCount > 0 ? 'has-pending' : '' ?>" data-type="out-of-stock">
                            <div class="alert-header">
                                <img src="images/icons/dashboard/out_of_stock_icon.png" alt="Out of Stock" class="alert-icon">
                                <h4>Out of Stock</h4>
                            </div>
                            <p class="pending-info">
                                <span class="pending-number"><?= $outOfStockCount ?></span> Medicines
                            </p>
                            <a href="medicine_management.php?filter=out" class="manage-link">Restock</a>
                        </div>

                        <div class="alert-card <?= $lowStockCount > 0 ? 'has-pending' : '' ?>" data-type="low-stock">
                            <div class="alert-header">
                                <img src="images/icons/dashboard/low_stock_icon.png" alt="Low Stock" class="alert-icon">
                                <h4>Low Stock</h4>
                            </div>
                            <p class="pending-info">
                                <span class="pending-number"><?= $lowStockCount ?></span> Medicines
                            </p>
                            <a href="medicine_management.php?filter=low" class="manage-link">Restock</a>
                        </div>

                        <div class="alert-card <?= $expiringWeekCount > 0 ? 'has-pending' : '' ?>" data-type="expiring-week">
                            <div class="alert-header">
                                <img src="images/icons/dashboard/expiring_week_icon.png" alt="Expiring Week" class="alert-icon">
                                <h4>Expiring in 7 Days</h4>
                            </div>
                            <p class="pending-info">
                                <span class="pending-number"><?= $expiringWeekCount ?></span> Medicine Batches
                            </p>
                            <a href="view_expiring.php?filter=week" class="manage-link">View</a>
                        </div>

                        <div class="alert-card <?= $expiringMonthCount > 0 ? 'has-pending' : '' ?>" data-type="expiring-month">
                            <div class="alert-header">
                                <img src="images/icons/dashboard/expiring_month_icon.png" alt="Expiring Month" class="alert-icon">
                                <h4>Expiring in 30 Days</h4>
                            </div>
                            <p class="pending-info">
                                <span class="pending-number"><?= $expiringMonthCount ?></span> Medicine Batches
                            </p>
                            <a href="view_expiring.php?filter=month" class="manage-link">View</a>
                        </div>

                        <div class="alert-card <?= $pendingReqCount > 0 ? 'has-pending' : '' ?>" data-type="pending-request">
                            <div class="alert-header">
                                <img src="images/icons/dashboard/pending_icon.png" alt="Pending" class="alert-icon">
                                <h4>Pending Requests</h4>
                            </div>
                            <p class="pending-info">
                                <span class="pending-number"><?= $pendingReqCount ?></span> Requests
                            </p>
                            <a href="requests.php" class="manage-link">Review</a>
                        </div>

                        <div class="alert-card <?= $toClaimCount > 0 ? 'has-pending' : '' ?>" data-type="to-claim">
                            <div class="alert-header">
                                <img src="images/icons/dashboard/to_be_claim_icon.png" alt="To Claim" class="alert-icon">
                                <h4>To Be Claimed</h4>
                            </div>
                            <p class="pending-info">
                                <span class="pending-number"><?= $toClaimCount ?></span> Requests
                            </p>
                            <a href="medicine_requests.php?status=to_be_claimed" class="manage-link">View</a>
                        </div>
                    </div>
                </section>

                <!-- ==================== SUMMARY ==================== -->
                <section class="summary-section">
                    <h3>Summary</h3>
                    <div class="stat-bars">
                        <div class="stat-item">
                            <div class="stat-label">
                                <img src="images/icons/dashboard/total_patients_icon.png" alt="">
                                <span>Total Patients</span>
                            </div>
                            <div class="stat-value"><?= number_format($totalPatients) ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">
                                <img src="images/icons/dashboard/total_families_icon.png" alt="">
                                <span>Total Families</span>
                            </div>
                            <div class="stat-value"><?= number_format($totalFamilies) ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">
                                <img src="images/icons/dashboard/total_consultations_icon.png" alt="">
                                <span>Total Consultations</span>
                            </div>
                            <div class="stat-value"><?= number_format($totalConsultations) ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">
                                <img src="images/icons/dashboard/total_medicines_icon.png" alt="">
                                <span>Total Unique Medicines</span>
                            </div>
                            <div class="stat-value"><?= number_format($totalMedicines) ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">
                                <img src="images/icons/dashboard/total_requests_icon.png" alt="">
                                <span>Total Medicine Requests</span>
                            </div>
                            <div class="stat-value"><?= number_format($totalMedRequests) ?></div>
                        </div>
                    </div>
                </section>

                <!-- ==================== QUICK ACTIONS ==================== -->
                <section class="quick-actions-section">
                    <h3>Quick Actions</h3>
                    <div class="actions-container">
                        <a href="medicine_management.php?action=add" class="action-btn medicine-btn">
                            <img src="images/icons/med_icon.png" alt="Add Medicine">
                            <span>Add Medicine</span>
                        </a>
                        <a href="patient_management.php?action=add" class="action-btn patient-btn">
                            <img src="images/icons/patient_icon.png" alt="Add Patient">
                            <span>Add Patient</span>
                        </a>
                    </div>
                </section>

                <!-- ==================== RECENT ACTIVITIES ==================== -->
                <section class="recent-activities-section">
                    <h3>Recent Activities</h3>
                    <div class="activity-list">
                        <?php if (empty($activities)): ?>
                            <p class="no-activity">No recent activity.</p>
                        <?php else: ?>
                            <?php foreach ($activities as $act): ?>
                                <?php
                                    $iconMap = [
                                        'medicine_add' => ['icon' => 'med_icon.png', 'color' => '#27ae60'],
                                        'medicine_distribute' => ['icon' => 'reqmd_icon.png', 'color' => '#3498db'],
                                        'add_patient_record' => ['icon' => 'patient_icon.png', 'color' => '#9b59b6'],
                                        'archive_patient' => ['icon' => 'patient_icon.png', 'color' => '#9b59b6'],
                                        'restore_patient' => ['icon' => 'patient_icon.png', 'color' => '#9b59b6'],
                                        // Add more as needed
                                    ];
                                    $type = $act['action_type'];
                                    $info = $iconMap[$type] ?? ['icon' => 'dashboard_icon_active.png', 'color' => '#7f8c8d'];

                                    // Humanize action
                                    $actionText = ucwords(str_replace('_', ' ', $type));
                                ?>
                                <details class="activity-item" style="border-left: 4px solid <?= $info['color'] ?>;">
                                    <summary>
                                        <img src="images/icons/<?= $info['icon'] ?>" alt="" class="activity-icon">
                                        <div class="activity-content">
                                            <p class="activity-desc">
                                                <strong><?= htmlspecialchars($act['admin_name'] ?? 'System') ?></strong>
                                                <?= htmlspecialchars($actionText) ?>
                                            </p>
                                            <p class="activity-time"><?= timeAgo($act['created_at']) ?></p>
                                        </div>
                                    </summary>
                                    <div class="activity-details">
                                        <p><?= nl2br(htmlspecialchars($act['action_details'] ?? 'No details recorded.')) ?></p>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- ==================== STATISTICS & REPORTS ==================== -->
                <section class="stats-reports-section">
                    <h3>Statistics and Reports</h3>
                    <div class="stats-card-container">
                        <a href="patient_stats.php" class="stats-card">
                            <div class="stats-icon">
                                <img src="images/icons/patient_icon.png" alt="">
                            </div>
                            <div class="stats-content">
                                <h4>Patient Statistics</h4>
                                <div class="stats-numbers">
                                    <div class="stat-line">
                                        <span class="label">Total Patients</span>
                                        <span class="value"><?= number_format($stats['patients_total']) ?></span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="label">Total Families</span>
                                        <span class="value"><?= number_format($stats['families_total']) ?></span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="label">Total Consultation</span>
                                        <span class="value"><?= number_format($stats['consultations_total']) ?></span>
                                    </div>
                                </div>
                                <p class="view-more">View Detailed Report →</p>
                            </div>
                        </a>
                        <a href="medicine_stats.php" class="stats-card">
                            <div class="stats-icon">
                                <img src="images/icons/med_icon.png" alt="">
                            </div>
                            <div class="stats-content">
                                <h4>Medicine Statistics</h4>
                                <div class="stats-numbers">
                                    <div class="stat-line">
                                        <span class="label">Unique Medicines</span>
                                        <span class="value"><?= number_format($stats['catalog_total']) ?></span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="label">Total Batches</span>
                                        <span class="value"><?= number_format($stats['batches_total']) ?></span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="label">Total Units</span>
                                        <span class="value"><?= number_format($stats['units_total']) ?></span>
                                    </div>
                                </div>
                                <p class="view-more">View Detailed Report →</p>
                            </div>
                        </a>
                        <a href="medrequest_stats.php" class="stats-card">
                            <div class="stats-icon">
                                <img src="images/icons/reqmd_icon.png" alt="">
                            </div>
                            <div class="stats-content">
                                <h4>Request Statistics</h4>
                                <div class="stats-numbers">
                                    <div class="stat-line">
                                        <span class="label">Total Requests </span>
                                        <span class="value"><?= number_format($stats['requests_total']) ?></span>
                                    </div>
                                    <div class="stat-line">
                                        <span class="label">Pending Requests</span>
                                        <span class="value"><?= number_format($stats['requests_pending']) ?></span>
                                    </div>
                                </div>
                                <p class="view-more">View Detailed Report →</p>
                            </div>
                        </a>
                    </div>
                </section>

                <!-- ==================== SYSTEM LOGS (Medicine Only) ==================== -->
                <section class="system-logs-section">
                    <h3>System Logs</h3>
                    <div class="log-tabs">
                        <button class="tab-btn active" data-tab="sms">SMS Logs</button>
                        <button class="tab-btn" data-tab="email">Email Logs</button>
                    </div>

                    <div class="log-content active" id="sms">
                        <?php if (empty($medSmsLogs)): ?>
                            <p class="no-logs">No SMS logs found.</p>
                        <?php else: ?>
                            <div class="log-list">
                                <?php foreach ($medSmsLogs as $log): ?>
                                    <details class="log-item">
                                        <summary>
                                            <span class="log-status <?= $log['status'] === 'success' ? 'success' : 'failed' ?>">
                                                <?= ucfirst($log['status']) ?>
                                            </span>
                                            <span class="log-recipient"><?= htmlspecialchars($log['recipient_name']) ?></span>
                                            <span class="log-time"><?= timeAgo($log['sent_at']) ?></span>
                                        </summary>
                                        <div class="log-details">
                                            <p><strong>Phone:</strong> <?= htmlspecialchars($log['recipient_phone']) ?></p>
                                            <p><strong>Message:</strong> <?= nl2br(htmlspecialchars($log['message'])) ?></p>
                                            <?php if ($log['status'] === 'failed'): ?>
                                                <p class="error-msg"><strong>Error:</strong> <?= htmlspecialchars($log['error_message']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="log-content" id="email">
                        <?php if (empty($medEmailLogs)): ?>
                            <p class="no-logs">No email logs found.</p>
                        <?php else: ?>
                            <div class="log-list">
                                <?php foreach ($medEmailLogs as $log): ?>
                                    <details class="log-item">
                                        <summary>
                                            <span class="log-status <?= $log['status'] === 'success' ? 'success' : 'failed' ?>">
                                                <?= ucfirst($log['status']) ?>
                                            </span>
                                            <span class="log-recipient"><?= htmlspecialchars($log['recipient_name']) ?></span>
                                            <span class="log-time"><?= timeAgo($log['sent_at']) ?></span>
                                        </summary>
                                        <div class="log-details">
                                            <p><strong>Email:</strong> <?= htmlspecialchars($log['recipient_email']) ?></p>
                                            <p><strong>Subject:</strong> <?= htmlspecialchars($log['subject']) ?></p>
                                            <p><strong>Message:</strong> <?= nl2br(htmlspecialchars($log['message'])) ?></p>
                                            <?php if ($log['status'] === 'failed'): ?>
                                                <p class="error-msg"><strong>Error:</strong> <?= htmlspecialchars($log['error_message']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script>
        // Tab switching (reuse from admin)
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = document.querySelectorAll('.tab-btn');
            const contents = document.querySelectorAll('.log-content');
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const target = tab.dataset.tab;
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    contents.forEach(c => c.classList.remove('active'));
                    document.getElementById(target).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>