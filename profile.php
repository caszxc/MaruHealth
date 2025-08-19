<?php
//profile.php
session_start();
require_once "config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$requests = []; // default to empty

// Fetch user details
$sql = "SELECT first_name, last_name, middle_name, gender, birthday, address, phone_number, email, profile_picture FROM users WHERE id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$profilePic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'images/uploads/profile_pictures/profile-placeholder.png'; 

// Redirect if user not found
if (!$user) {
    header("Location: login.php");
    exit();
}

$sql = "SELECT id, request_id, request_date, request_status FROM medicine_requests WHERE user_id = :user_id ORDER BY request_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="stylesheet" href="css/profile.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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

        <div class="nav-links">
            <ul>
                <li><a href="index.php">HOME</a></li>
                <li><a href="calendar.php">CALENDAR</a></li>
                <li><a href="request_medicine.php" class="links">MEDICINE REQUEST</a></li>
                <li><a href="about_us.php">ABOUT US</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] === 'user'): ?>
                    <li>
                        <a href="profile.php">
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile Picture" class="nav-profile-pic">
                        </a>
                    </li>
                <?php endif; ?>
                <?php else: ?>
                    <li><a href="login.php" class="login-button">LOG IN</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="profile-container">
        <h2 class="page-title">Profile</h2>
        <div class="profile-box">
            <div class="profile-img">
                <div class="profile-pic-wrapper" onclick="openModal()">
                    <img src="<?= htmlspecialchars($profilePic) ?>" alt="profile">
                    <button class="icon-button" onclick="openModal(event)">
                        <i class="fas fa-pen"></i>
                    </button>
                </div>
                <p class="full-name"><?php echo htmlspecialchars($user['first_name'] . " " . $user['middle_name'] . " " . $user['last_name']); ?></p>
                <form action="logout.php" method="POST">
                    <button type="submit" class="logout-button">Log Out</button>
                </form>
            </div>

            <div class="profile-info">
                <!-- Tab buttons -->
                <div class="tab-buttons">
                    <button class="tab-button active" onclick="openTab(event, 'details')">Details</button>
                    <button class="tab-button" onclick="openTab(event, 'request-history')">Request History</button>
                </div>
                
                <!-- Details buttons -->
                <div id="details" class="tab-content" style="display: flex;">
                    <div class="content-con">
                        <h3 class="title">General Information</h3>
                        <div class="group-row">
                            <div class="row">
                                <p class="label">Last Name</p>
                                <p class="value"><?php echo htmlspecialchars($user['last_name']); ?></p>
                            </div>
                            <div class="row">
                                <p class="label">First Name</p>
                                <p class="value"><?php echo htmlspecialchars($user['first_name']); ?></p>
                            </div>
                            <div class="row">
                                <p class="label">Middle Name</p>
                                <p class="value"><?php echo htmlspecialchars($user['middle_name']); ?></p>
                            </div>
                            <div class="row">
                                <p class="label">Gender</p>
                                <p class="value"><?php echo htmlspecialchars($user['gender']); ?></p>
                            </div>
                            <div class="row">
                                <p class="label">Date of Birth</p>
                                <p class="value"> <?php 
                                    $birthdate = date("F j, Y", strtotime($user['birthday'])); 
                                    echo htmlspecialchars($birthdate);
                                ?></p>
                            </div>
                            <div class="row">
                                <p class="label">Address</p>
                                <p class="value"><?php echo htmlspecialchars($user['address']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="content-con">
                        <h3 class="title">Contact Information</h3>
                        <div class="group-row">
                            <div class="row">
                                <p class="label">Phone Number</p>
                                <p class="value"><?php echo htmlspecialchars($user['phone_number']); ?></p>
                            </div>
                            <div class="row">
                                <p class="label">Email Address</p>
                                <p class="value"><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Request History buttons -->
                <div id="request-history" class="tab-content" style="display: none;">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Date & Time Requested</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($requests)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center;">No requests found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                        $statusColors = [
                                            'claimed' => 'style="background-color: #28a745; color: white;"',
                                            'pending' => 'style="background-color: #bbb; color: white;"',
                                            'declined' => 'style="background-color: #dc3545; color: white;"',
                                            'to be claimed' => 'style="background-color: #ffc107; color: black;"'
                                        ];
                                    ?>
                                    <?php foreach ($requests as $request): ?>
                                        <?php 
                                            $status = strtolower($request['request_status']);
                                            $formattedDate = date("n/j/Y g:iA", strtotime($request['request_date']));
                                            $colorStyle = $statusColors[$status] ?? 'style="background-color: #ccc; color: black;"';
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($request['request_id']) ?></td>
                                            <td><?= htmlspecialchars($formattedDate) ?></td>
                                            <td><span class="status-badge" <?= $colorStyle ?>><?= ucfirst($status) ?></span></td>
                                            <td><button class="view-btn" data-id="<?= $request['id'] ?>" onclick="viewRequest(this)">View Request</button></td>
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

    <div id="changeProfileModal" class="modal">
        <div class="modal-content">
            <h2>Change Profile</h2>
            <form action="upload_profilePic.php" method="POST" enctype="multipart/form-data">
                <div class="image-preview">
                    <img id="preview-img" src="<?= htmlspecialchars($profilePic) ?>" alt="Preview">
                </div>
                <div>
                    <input type="file" name="profile_photo" id="profile-photo" accept="image/*">
                </div>
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="save-btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="viewRequestModal" class="modal"> 
        <div class="modal-content">
            <h2>Request Medicine Details</h2>
            <div class="modal-container">
                <div class="row">
                    <div>
                        <label>Request ID</label>
                        <span id="requestId"></span>
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Request Status</label>
                        <span id="requestStatus"></span>
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Patient's Full Name</label>
                        <span id="fullName"></span>
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Sex</label>
                        <span id="sex"></span>
                    </div>
                    <div>
                        <label>Birthdate</label>
                        <span id="birthdate"></span>
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Address</label>
                        <span id="address"></span>
                    </div>
                    <div>
                        <label>Contact Number</label>
                        <span id="phone"></span>
                    </div>
                </div>
                <div id="medicine-group">
                    <label>Requested Medicines</label>
                </div>
                <div class="row">
                    <div>
                        <label>Reason for Request</label>
                        <span id="reason"></span>
                    </div>
                </div>
                <div class="prescription-preview">
                    <div class="group-col">
                        <label>Prescription</label>
                        <img id="prescriptionImg" src="" alt="Prescription Image">
                    </div>
                </div>
                <div id="claim-info" style="display: none;">
                    <div class="row">
                        <div>
                            <label>Claim Information</label>
                            <div class="claim-details">
                                <p><strong>Claim Date:</strong> <span id="claimDate"></span></p>
                                <p><strong>Claim Until:</strong> <span id="claimUntil"></span></p>
                                <p><strong>Claimed Date:</strong> <span id="claimedDate"></span></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="note-info" style="display: none;">
                    <div class="row">
                        <div>
                            <label>Note</label>
                            <span id="note"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeViewModal()">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(e) {
            if (e) e.stopPropagation();
            const modal = document.getElementById("changeProfileModal");
            modal.classList.add("show");
        }

        function closeModal() {
            document.getElementById("changeProfileModal").classList.remove("show");
        }

        function closeViewModal() {
            document.getElementById("viewRequestModal").classList.remove("show");
        }

        document.getElementById("profile-photo").addEventListener("change", function(event) {
            const file = event.target.files[0];
            const preview = document.getElementById("preview-img");
            if (file && file.type.startsWith("image/")) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        function openTab(evt, tabName) {
            const tabContents = document.getElementsByClassName("tab-content");
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].style.display = "none";
            }
            const tabButtons = document.getElementsByClassName("tab-button");
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove("active");
            }
            document.getElementById(tabName).style.display = "flex";
            evt.currentTarget.classList.add("active");
        }

        function viewRequest(button) {
            const requestId = button.getAttribute('data-id');
            fetch(`get_requests_profile.php?id=${requestId}`)
                .then(response => response.json())
                .then(data => {
                    // Fill modal fields
                    document.getElementById('requestId').textContent = data.request_id || 'N/A';
                    document.getElementById('requestStatus').textContent = data.request_status ? data.request_status.charAt(0).toUpperCase() + data.request_status.slice(1) : 'N/A';
                    document.getElementById('fullName').textContent = data.full_name;
                    document.getElementById('sex').textContent = data.sex;
                    document.getElementById('birthdate').textContent = data.birthdate;
                    document.getElementById('address').textContent = data.address;
                    document.getElementById('phone').textContent = data.phone;
                    document.getElementById('reason').textContent = data.reason || 'No reason provided';
                    document.getElementById('prescriptionImg').src = data.prescription;

                    // Medicine entries with status
                    const medicineGroup = document.getElementById('medicine-group');
                    medicineGroup.innerHTML = '<label>Requested Medicines</label>';
                    data.medicines.forEach(med => {
                        const row = document.createElement('div');
                        row.classList.add('row', 'medicine-entry');
                        row.innerHTML = `
                            <div><label>Medicine Name</label><span>${med.medicine_name}</span></div>
                            <div><label>Dosage</label><span>${med.dosage || 'N/A'}</span></div>
                            <div><label>Quantity</label><span>${med.quantity}</span></div>
                            <div><label>Status</label><span class="status-badge status-${med.status}">${med.status.charAt(0).toUpperCase() + med.status.slice(1)}</span></div>
                        `;
                        medicineGroup.appendChild(row);
                    });

                    // Claim information
                    const claimInfo = document.getElementById('claim-info');
                    const claimDate = document.getElementById('claimDate');
                    const claimUntil = document.getElementById('claimUntil');
                    const claimedDate = document.getElementById('claimedDate');
                    if (data.request_status === 'to be claimed' || data.request_status === 'claimed') {
                        claimDate.textContent = data.claim_date || 'N/A';
                        claimUntil.textContent = data.claim_until_date || 'N/A';
                        claimedDate.textContent = data.claimed_date || 'N/A';
                        claimInfo.style.display = 'block';
                    } else {
                        claimInfo.style.display = 'none';
                    }

                    // Note
                    const noteInfo = document.getElementById('note-info');
                    const note = document.getElementById('note');
                    if ((data.request_status === 'to be claimed' || data.request_status === 'claimed') && data.note && data.note.trim() !== '') {
                        note.textContent = data.note;
                        noteInfo.style.display = 'block';
                    } else {
                        noteInfo.style.display = 'none';
                    }

                    document.getElementById("viewRequestModal").classList.add("show");
                })
                .catch(error => {
                    alert("Failed to load request data.");
                    console.error(error);
                });
        }

        document.querySelector("#viewRequestModal .close").addEventListener("click", function () {
            closeViewModal();
        });
    </script>
</body>
</html>