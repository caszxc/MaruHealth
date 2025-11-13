<?php
//pending_requests.php
session_start();
require_once "config.php";
require_once "auto_return_unclaimed.php";

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Get search term from GET request and sanitize it
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch medicine requests with 'to be claimed' status and optional search filter
$requestsQuery = "SELECT mr.id, mr.request_id, mr.full_name, 
                 DATE_FORMAT(mr.request_date, '%m/%d/%Y %h:%i%p') as formatted_request_date,
                 DATE_FORMAT(mr.claim_date, '%m/%d/%Y') as formatted_claim_date,
                 DATE_FORMAT(mr.claim_until_date, '%m/%d/%Y') as formatted_until_date, 
                 mr.request_status 
                 FROM medicine_requests mr 
                 WHERE mr.request_status = 'to be claimed'";
if (!empty($searchTerm)) {
    $requestsQuery .= " AND (mr.request_id LIKE :search OR mr.full_name LIKE :search)";
}
$requestsQuery .= " ORDER BY mr.claim_date ASC";

$requestsStmt = $conn->prepare($requestsQuery);
if (!empty($searchTerm)) {
    $searchParam = "%$searchTerm%";
    $requestsStmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
}
$requestsStmt->execute();
$requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch the admin's name
$adminId = $_SESSION['admin_id'];
$adminStmt = $conn->prepare("SELECT * FROM admin_staff WHERE id = :id");
$adminStmt->bindParam(':id', $adminId);
$adminStmt->execute();
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

// Default to session information if query fails
$adminName = $admin ? $admin['full_name'] : $_SESSION['admin_name'];
$adminRole = $admin ? $admin['role'] : $_SESSION['admin_role'];

// Format role for display
$displayRole = ucwords(str_replace('_', ' ', $adminRole));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>To Be Claimed Medicine Requests</title>
    <link rel="stylesheet" href="css/medicine_requests.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="stylesheet" href="css/claim_date_modal.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <style>
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            text-align: center;
            display: inline-block;
            min-width: 80px;
        }
        .status-to-be-claimed {
            background-color: #007bff;
        }
        .status-claimed {
            background-color: #28a745;
        }
        .status-declined {
            background-color: #dc3545;
        }
        .status-expired {
            background-color: #6c757d;
        }
        .medicine-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            width: 10%;
            text-align: center;
        }
        .status-reserved {
            background-color: #17a2b8;
        }
        .status-declined {
            background-color: #dc3545;
        }
        .date-claim-info {
            background-color: #fff;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            border: 1px solid #ddd;
        }
        .date-claim-info p {
            margin: 5px 0;
            font-size: 14px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .message {
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            transition: opacity .5s ease-in-out;
        }
        .message.success { background:#dff0d8; color:#3c763d; }
        .message.error   { background:#f2dede; color:#a94442; }
    </style>
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="images/3s logo.png">
            <div>
                <h1>
                    <span class="maruhealth">MaruHealth</span>
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
                $dashboard_url = $adminRole === 'super_admin' ? 'superadmin_dashboard.php' : 
                                ($adminRole === 'admin' ? 'admin_dashboard.php' : 'healthstaff_dashboard.php');
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
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/calendar_icon.png" alt="">
                <a href="content_management.php" class="<?= $current_page == 'content_management.php' ? 'active' : '' ?>">Content Management</a>
            </div>
            <?php endif; ?>
            <?php if ($adminRole == 'super_admin' || $adminRole == 'health_staff'): ?>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/patient_icon.png" alt="">
                <a href="patient_management.php" class="<?= $current_page == 'patient_management.php' ? 'active' : '' ?>">Patient Management</a>
            </div>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/med_icon.png" alt="">
                <a href="medicine_management.php" class="<?= $current_page == 'medicine_management.php' ? 'active' : '' ?>">Medicine Management</a>
            </div>
            <div class="menu-link-active">
                <img class="menu-icon" src="images/icons/reqmd_icon_active.png" alt="">
                <a href="medicine_requests.php" class="<?= ($current_page == 'medicine_requests.php' || $current_page == 'pending_requests.php') ? 'active' : '' ?>">Medicine Requests</a>
            </div>
            <?php endif; ?>
            <p class="menu-header">OTHERS</p>
            <div class="menu-link">
                <img class="menu-icon" src="images/icons/logout_icon.png" alt="">
                <a href="logout.php" class="logout-button">Log Out</a>
            </div>
        </div>
    </div>

    <div class="med-req-content">
        <div class="title-con">
            <a href="medicine_requests.php" class="back-button">← Back</a>
            <h2>To Be Claimed Medicine Requests</h2>
        </div>
        <div class="req-container">
            <div class="sort-controls">
                <div class="search-con">
                    <form method="GET" action="pending_requests.php">
                        <input type="text" name="search" placeholder="Search by Request ID or Name" value="<?= htmlspecialchars($searchTerm) ?>">
                        <button type="submit">Search</button>
                    </form>
                </div>
            </div>
            <!-- SUCCESS / ERROR MESSAGE -->
            <?php if (isset($_SESSION['claim_message'])): ?>
                <?php 
                    $msgClass = ($_SESSION['claim_status'] ?? '') === 'error' ? 'error' : 'success';
                ?>
                <div class="message <?= $msgClass ?>">
                    <?= htmlspecialchars($_SESSION['claim_message']) ?>
                </div>
                <?php 
                    unset($_SESSION['claim_message'], $_SESSION['claim_status']);
                ?>
            <?php endif; ?>
            <div class="request-table">
                <div class="table-con">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Name</th>
                                    <th>Date/Time Requested</th>
                                    <th>Date/Time of Claim</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($requests)): ?>
                                    <tr>
                                        <td colspan="6" class="no-requests" style="text-align: center;">No to be claimed medicine requests found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($requests as $request): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($request['request_id']) ?></td>
                                            <td><?= htmlspecialchars($request['full_name']) ?></td>
                                            <td><?= htmlspecialchars($request['formatted_request_date']) ?></td>
                                            <td><?= htmlspecialchars($request['formatted_claim_date']) ?> - <?= htmlspecialchars($request['formatted_until_date']) ?></td>
                                            <td>
                                                <div class="status-badge status-to-be-claimed">
                                                    To Be Claimed
                                                </div>
                                            </td>
                                            <td>
                                                <button class="view-btn" onclick="openModal(<?= $request['id'] ?>)">View</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Request Details Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const msg = document.querySelector('.message');
            if (msg) {
                setTimeout(() => {
                    msg.style.opacity = '0';
                    setTimeout(() => msg.remove(), 600);
                }, 4000);
            }
        });
        function openModal(requestId) {
            fetch('get_pending_request_details.php?id=' + requestId)
                .then(response => response.json())
                .then(data => {
                    populateModal(data);
                    let modal = document.getElementById("viewModal");
                    modal.classList.add("show");
                })
                .catch(error => console.error('Error:', error));
        }

        function closeModal() {
            let modal = document.getElementById("viewModal");
            modal.classList.remove("show");
        }

        function populateModal(data) {
            const modal = document.querySelector('.modal-content');
            let modalHTML = `
                <h2 class="title">Medicine Request Details - Request ID: ${data.request.request_id}</h2>
                <div class="patient-scroll">
                    <div class="patient-details">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Patient's Full Name <span class="sub-label">(Buong Pangalan ng Pasyente)</span></label>
                                <div class="detail-box">${data.request.full_name}</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group half">
                                <label>Gender <span class="sub-label">(Kasarian)</span></label>
                                <div class="detail-box">${data.request.gender}</div>
                            </div>
                            <div class="form-group half">
                                <label>Birthdate <span class="sub-label">(Araw ng Kapanganakan)</span></label>
                                <div class="detail-box">${data.request.birthdate}</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group half">
                                <label>Complete Address <span class="sub-label">(Kompletong Address)</span></label>
                                <div class="detail-box">${data.request.address}</div>
                            </div>
                            <div class="form-group half">
                                <label>Contact Number <span class="sub-label">(Numero ng Telepono)</span></label>
                                <div class="detail-box">${data.request.phone}</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Reason for Request <span class="sub-label">(Rason ng Paghingi)</span></label>
                                <div class="detail-box">${data.request.reason || 'No reason provided'}</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Prescription <span class="sub-label">(Reseta)</span></label>
                                <div class="prescription-image">
                                    <img src="${data.request.prescription}" alt="Prescription">
                                </div>
                            </div>
                        </div>
                        ${data.request.note && data.request.note.trim() !== '' ? `
                        <div class="form-row">
                            <div class="form-group">
                                <label>Note <span class="sub-label">(Mensahe/Nota para sa Residente)</span></label>
                                <div class="detail-box">${data.request.note}</div>
                            </div>
                        </div>
                        ` : ''}
                        <div class="form-row">
                            <div class="form-group">
                                <div class="date-claim-info">
                                    <h4>Claim Information</h4>
                                    <p><strong>Claim Date:</strong> ${data.request.formatted_claim_date}</p>
                                    <p><strong>Until:</strong> ${data.request.formatted_until_date}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <h3>Requested Medicines</h3>
                    <div class="requested-medicines">`;
            
            data.medicines.forEach((medicine) => {
                let statusBadge = '';
                if (medicine.status === 'approved') {
                    statusBadge = `<div class="medicine-status status-reserved">Reserved</div>`;
                } else if (medicine.status === 'declined') {
                    statusBadge = `<div class="medicine-status status-declined">Declined</div>`;
                }
                let distributionInfo = '';
                if (medicine.distribution) {
                    distributionInfo = `
                        <div class="distribution-info">
                            <p><strong>Reserved Medicine:</strong> ${medicine.distribution.medicine_name}</p>
                            <p><strong>Quantity:</strong> ${medicine.distribution.quantity}</p>
                        </div>`;
                }
                modalHTML += `
                    <div class="medicine-item">
                        <div class="medicine-details">
                            <div class="form-row">
                                <div class="form-group third">
                                    <label>Medicine Name <span class="sub-label">(Pangalan ng Gamot)</span></label>
                                    <div class="detail-box">${medicine.medicine_name}</div>
                                </div>
                                <div class="form-group third">
                                    <label>Dosage <span class="sub-label">(Dosis)</span></label>
                                    <div class="detail-box">${medicine.dosage}</div>
                                </div>
                                <div class="form-group third">
                                    <label>Quantity <span class="sub-label">(Bilang)</span></label>
                                    <div class="detail-box">${medicine.quantity}</div>
                                </div>
                            </div>
                            ${statusBadge}
                            ${distributionInfo}
                        </div>
                    </div>`;
            });
            
            modalHTML += `
                </div>
                </div>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Close</button>
                    ${data.request.is_past_due && data.request.request_status === 'to be claimed' ? `
                    <button type="button" class="btn-return" onclick="processReturn(${data.request.id})">Return to Inventory</button>
                    ` : (data.request.request_status === 'to be claimed' ? `
                    <button type="button" class="btn-claim" onclick="processClaim(${data.request.id})">Mark as Claimed</button>
                    ` : `
                    <span class="status-badge status-expired">Expired & Returned</span>
                    `)}
                </div>`;
            
            modal.innerHTML = modalHTML;
        }

        function processClaim(requestId) {
            if (confirm('Are you sure you want to mark this request as claimed?')) {
                const formData = new FormData();
                formData.append('request_id', requestId);
                formData.append('action', 'claim');
                fetch('process_claim.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                    } else {
                        closeModal();
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing your request.');
                });
            }
        }

        function processReturn(requestId) {
            if (confirm('Are you sure you want to return the unclaimed medicines to inventory? This will mark the request as expired.')) {
                const formData = new FormData();
                formData.append('request_id', requestId);
                formData.append('action', 'return');
                fetch('process_return.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        console.error('Server Error:', data.error);
                        alert('Error: ' + data.error);
                    } else {
                        closeModal();
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    alert('An error occurred while processing your request: ' + error.message);
                });
            }
        }
    </script>
</body>
</html>