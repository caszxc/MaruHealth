<?php
// activity_logs.php
session_start();
require_once "config.php";
include 'settings.php';

// Restrict to Super Admin only
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'super_admin') {
    header("Location: login.php");
    exit();
}

// Fetch admin info
$adminId = $_SESSION['admin_id'];
$adminStmt = $conn->prepare("SELECT * FROM admin_staff WHERE id = :id");
$adminStmt->bindParam(':id', $adminId);
$adminStmt->execute();
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
$adminName = $admin ? $admin['full_name'] : $_SESSION['admin_name'];
$adminRole = $admin ? $admin['role'] : $_SESSION['admin_role'];
$displayRole = ucwords(str_replace('_', ' ', $adminRole));

// Filters
$search      = trim($_GET['search'] ?? '');
$action_type = $_GET['action_type'] ?? '';
$date_from   = $_GET['date_from'] ?? '';
$date_to     = $_GET['date_to'] ?? '';

// Build WHERE conditions
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(a.full_name LIKE :search OR al.action_details LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($action_type !== '') {
    $where[] = "al.action_type = :action_type";
    $params[':action_type'] = $action_type;
}

if ($date_from !== '') {
    $where[] = "DATE(al.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to !== '') {
    $where[] = "DATE(al.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countStmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM activity_logs al
    LEFT JOIN admin_staff a ON al.admin_id = a.id
    $whereClause
");
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalRecords = $countStmt->fetchColumn();

// Fetch all logs (no pagination)
$stmt = $conn->prepare("
    SELECT al.*, a.full_name AS admin_name, a.role AS admin_role
    FROM activity_logs al
    LEFT JOIN admin_staff a ON al.admin_id = a.id
    $whereClause
    ORDER BY al.created_at DESC
");

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Action type to readable name + icon mapping
$actionConfig = [
    'user_approval'           => ['label' => 'Approved User Account',           'icon' => 'fas fa-user-check',      'color' => '#27ae60'],
    'user_rejection'          => ['label' => 'Rejected User Account',          'icon' => 'fas fa-user-slash',      'color' => '#e74c3c'],
    'announcement_create'     => ['label' => 'Created Announcement',           'icon' => 'fas fa-bullhorn',        'color' => '#9b59b6'],
    'announcement_update'     => ['label' => 'Updated Announcement',           'icon' => 'fas fa-edit',            'color' => '#8e44ad'],
    'announcement_toggle'     => ['label' => 'Toggled Announcement Status',    'icon' => 'fas fa-toggle-on',       'color' => '#71368a'],
    'event_create'            => ['label' => 'Created Event',                  'icon' => 'fas fa-calendar-plus',   'color' => '#e67e22'],
    'event_delete'            => ['label' => 'Deleted Event',                  'icon' => 'fas fa-calendar-minus',  'color' => '#d35400'],
    'service_create'          => ['label' => 'Created Service',                'icon' => 'fas fa-plus-circle',     'color' => '#2980b9'],
    'service_update'          => ['label' => 'Updated Service',                'icon' => 'fas fa-cogs',            'color' => '#3498db'],
    'service_delete'          => ['label' => 'Deleted Service',                'icon' => 'fas fa-trash-alt',       'color' => '#c0392b'],
    'add_patient_record'      => ['label' => 'Added Patient Record',           'icon' => 'fas fa-user-plus',       'color' => '#3498db'],
    'update_patient_record'   => ['label' => 'Updated Patient Record',         'icon' => 'fas fa-edit',            'color' => '#f39c12'],
    'archive_patient'         => ['label' => 'Archived Patient',               'icon' => 'fas fa-archive',         'color' => '#95a5a6'],
    'restore_patient'         => ['label' => 'Restored Patient',               'icon' => 'fas fa-undo',            'color' => '#1abc9c'],
    'approve_request'         => ['label' => 'Approved Medicine Request',      'icon' => 'fas fa-check-circle',    'color' => '#27ae60'],
    'decline_request'         => ['label' => 'Declined Medicine Request',      'icon' => 'fas fa-times-circle',    'color' => '#e74c3c'],
    'mark_request_claimed'    => ['label' => 'Marked Request as Claimed',      'icon' => 'fas fa-prescription-bottle', 'color' => '#2ecc71'],
    'return_unclaimed_request'=> ['label' => 'Returned Unclaimed Medicines',   'icon' => 'fas fa-undo',            'color' => '#e67e22'],
    'add_medicine'            => ['label' => 'Added Medicine to Catalog',      'icon' => 'fas fa-pills',           'color' => '#27ae60'],
    'update_medicine'         => ['label' => 'Updated Medicine Catalog',       'icon' => 'fas fa-edit',            'color' => '#f39c12'],
    'delete_medicine'         => ['label' => 'Deleted Medicine from Catalog',  'icon' => 'fas fa-trash',           'color' => '#e74c3c'],
    'add_batch'               => ['label' => 'Added Medicine Batch',           'icon' => 'fas fa-box',             'color' => '#27ae60'],
    'update_batch'            => ['label' => 'Updated Medicine Batch',         'icon' => 'fas fa-edit',            'color' => '#f39c12'],
    'delete_batch'            => ['label' => 'Deleted Medicine Batch',         'icon' => 'fas fa-trash',           'color' => '#e74c3c'],
    'add_family_member'       => ['label' => 'Added Family Member',            'icon' => 'fas fa-users',           'color' => '#9b59b6'],
    'add_consultation'        => ['label' => 'Added Consultation Record',      'icon' => 'fas fa-notes-medical',   'color' => '#3498db'],
    'delete_consultation'     => ['label' => 'Deleted Consultation Record',    'icon' => 'fas fa-trash',           'color' => '#e74c3c'],
    'delete_user'             => ['label' => 'Deleted User Account',           'icon' => 'fas fa-user-times',      'color' => '#c0392b'],
];

// Helper: Time ago
function timeAgo($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $interval = $now->diff($past);

    if ($interval->y > 0) return $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
    if ($interval->m > 0) return $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
    if ($interval->d > 0) return $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
    if ($interval->h > 0) return $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
    if ($interval->i > 0) return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs & Audit Trail | Super Admin</title>
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="stylesheet" href="css/activity_logs.css">
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" href="<?= $logo_url ?>" type="image/x-icon">
</head>
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
                } elseif ($adminRole === 'health_staff') {
                    $dashboard_url = 'healthstaff_dashboard.php';
                }
            ?>
            <p class="menu-header">ANALYTICS</p>
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/dashboard_icon_active.png" alt="">
                <a href="<?= htmlspecialchars($dashboard_url) ?>" class="<?= in_array($current_page, [$dashboard_url, 'activity_logs.php']) ? 'active' : '' ?>">Dashboard</a>
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
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/settings_icon.png" alt="">
                <a href="system_settings.php" class="<?= $current_page == 'system_settings.php' ? 'active' : '' ?>">
                    System Settings
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($adminRole == 'admin'): ?>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/announcement_icon.png" alt="">
                <a href="announcements.php" class="<?= $current_page == 'announcements.php' ? 'active' : '' ?>">Announcement</a>
            </div>
            
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/calendar_icon.png" alt="">
                <a href="edit_calendar.php" class="<?= $current_page == 'edit_calendar.php' ? 'active' : '' ?>">Calendar</a>
            </div>

            <div class="menu-link">
                <img class="menu-icon" src="images/icons/service_icon.png" alt="">
                <a href="service_management.php" class="<?= $current_page == 'service_management.php' ? 'active' : '' ?>">Service Management</a>
            </div>
            <?php endif; ?>

            <?php if ($adminRole == 'health_staff'): ?>
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

    <div class="activity-logs">
        <div class="title-con">
            <a href="superadmin_dashboard.php" class="back-button">← Back</a>
            <h2>Activity Logs & Audit Trail</h2>
        </div>
        
        <div class="activity-container">
            <!-- Filters -->
            <div class="filters-card">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <input type="text" name="search" placeholder="Search logs..." value="<?= htmlspecialchars($search) ?>">
                        <select name="action_type">
                            <option value="">All Actions</option>
                            <?php 
                            // Extract all action_type keys from $actionConfig
                            $actionTypes = array_keys($actionConfig);
                            foreach ($actionTypes as $type): 
                            ?>
                                <option value="<?= htmlspecialchars($type) ?>" <?= $action_type === $type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($actionConfig[$type]['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" placeholder="From">
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" placeholder="To">
                        <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                        <a href="activity_logs.php" class="btn-clear">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Logs List -->
            <div class="logs-container">
                <?php if (empty($logs)): ?>
                    <div class="no-logs">
                        <i class="fas fa-inbox fa-3x" style="color: #ddd; margin-bottom: 20px;"></i>
                        <p>No activity logs found matching your criteria.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($logs as $log): 
                        $config = $actionConfig[$log['action_type']] ?? ['label' => ucwords(str_replace('_', ' ', $log['action_type'])), 'icon' => 'fas fa-cube', 'color' => '#7f8c8d'];
                    ?>
                        <div class="log-item">
                            <div class="log-header">
                                <div class="log-icon" style="background: <?= $config['color'] ?>;">
                                    <i class="<?= $config['icon'] ?>"></i>
                                </div>
                                <div style="flex: 1;">
                                    <strong><?= htmlspecialchars($log['admin_name'] ?? 'Unknown Admin') ?></strong>
                                    <span style="color: <?= $config['color'] ?>; margin-left: 8px;">
                                        <?= $config['label'] ?>
                                    </span>
                                </div>
                                <div class="log-meta">
                                    <?= timeAgo($log['created_at']) ?>
                                    <br><small><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></small>
                                </div>
                            </div>

                            <?php if (!empty($log['action_details'])): ?>
                                <div class="log-details">
                                    <?= nl2br(htmlspecialchars($log['action_details'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>   
    </div>
</body>
</html>