<?php
include 'settings.php';
// deletion_notice.php
if (!isset($conn) || !isset($_SESSION['user_id'])) {
    return;   // silently ignore if called too early
}

$active_user_id  = $_SESSION['active_user_id'] ?? $_SESSION['user_id'];
$primary_user_id = $_SESSION['user_id'];

// -------------------------------------------------
// 1. Get the profile picture of the ACTIVE user
// -------------------------------------------------
$picStmt = $conn->prepare("
    SELECT profile_picture
    FROM users
    WHERE id = :uid
    LIMIT 1
");
$picStmt->execute([':uid' => $active_user_id]);
$picRow   = $picStmt->fetch(PDO::FETCH_ASSOC);
$profilePic = !empty($picRow['profile_picture'])
    ? $picRow['profile_picture']
    : 'images/uploads/profile_pictures/profile-placeholder.png';

// -------------------------------------------------
// 2. Look for a pending deletion request
// -------------------------------------------------
$delQ = $conn->prepare("
    SELECT id, requested_at, reason, status
    FROM account_deletion_requests
    WHERE user_id = :uid
      AND status = 'pending'
    LIMIT 1
");
$delQ->execute([':uid' => $active_user_id]);
$delReq = $delQ->fetch(PDO::FETCH_ASSOC);

if ($delReq) {
    $reqId     = (int)$delReq['id'];
    $requested = date('F j, Y \a\t g:i A', strtotime($delReq['requested_at']));
    $reason    = htmlspecialchars($delReq['reason']);

    // -------------------------------------------------
    // 3. Output the overlay + full navigation bar + MODALS
    // -------------------------------------------------
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deletion Notice</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/nav_footer.css">
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" href="$logo_url" type="image/x-icon">
    <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:"Istok Web";}
    .deletion-notice-overlay{
        position:fixed;top:0;left:0;width:100%;height:100%;
        background:linear-gradient(rgba(220,0,0,.8),rgba(220,0,0,.6)),
                   url(./images/calendar-banner.jpg) center/cover no-repeat;
        z-index:9999;display:flex;flex-direction:column;
    }
    nav{
        background:#800000;display:flex;justify-content:space-between;
        align-items:center;padding:10px 20px;width:100%;height:80px;
        box-shadow:0 4px 8px rgba(0,0,0,.2);
    }
    .nav-links{list-style:none;display:flex;align-items:center;gap:15px;}
    .nav-links a{color:#fff;text-decoration:none;}
    .nav-profile-pic {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        border:2px solid #fff;
    }
    .deletion-container{
        margin-top: 80px;
        flex:1;width:100%;display:flex;justify-content:center;align-items:center;
    }
    .deletion-notice-box{
        background:#fff;padding:2rem 2.5rem;border-radius:12px;
        max-width:460px;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,.2);
    }
    .deletion-notice-box i{font-size:3rem;color:#e74c3c;margin-bottom:.8rem;}
    .deletion-notice-box h2{font-size:1.6rem;margin-bottom:.8rem;}
    .deletion-notice-box p{font-size:1rem;line-height:1.5;margin-bottom:1rem;}
    .deletion-notice-actions{
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: .4rem;
    }
    .deletion-notice-actions button,
    .deletion-notice-actions a{
        display:inline-block;padding:.6rem 1.2rem;
        border:none;border-radius:6px;font-weight:600;
        cursor:pointer;text-decoration:none;
    }
    .btn-cancel-deletion{background:#27ae60;color:#fff;}
    .btn-cancel-deletion:hover{background:#219150;}
    .btn-logout{background:#95a5a6;color:#fff;}
    .btn-logout:hover{background:#7f8c8d;}

    /* MODAL STYLES */
    .modal {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.6);
        z-index: 10000;
        justify-content: center;
        align-items: center;
    }
    .modal.show { display: flex; }
    .modal-content {
        background: #fff;
        padding: 2rem;
        border-radius: 12px;
        max-width: 400px;
        width: 90%;
        text-align: center;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .modal-content i {
        font-size: 48px;
        color: #28a745;
        margin-bottom: 15px;
    }
    .modal-content h2 { margin: 15px 0; }
    .modal-footer {
        margin-top: 20px;
        display: flex;
        justify-content: center;
        gap: 10px;
    }
    .modal-footer button {
        padding: 0.6rem 1.4rem;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
    }
    .confirm-btn { background: #27ae60; color: #fff; }
    .confirm-btn:hover { background: #219150; }
    .cancel-btn { background: #95a5a6; color: #fff; }
    .cancel-btn:hover { background: #7f8c8d; }

    @media (max-width: 480px) {
        .deletion-notice-box{
            width: 80%;
        }
    }
    </style>
</head>
<body>

<div class="deletion-notice-overlay">

    <!-- NAVIGATION -->
    <nav>
        <div class="logo-container">
            <img src="$logo_url" alt="Logo">
            <div>
                <h1>
                    <span class="maruhealth">$site_name</span>
                    <span class="barangay-title">Barangay Marulas 3S Health Center</span>
                </h1>
            </div>
        </div>
        <div class="nav-links" id="nav-links">
            <img src="$profilePic" alt="Profile Picture" class="nav-profile-pic">
        </div>
    </nav>

    <!-- NOTICE -->
    <div class="deletion-container">
        <div class="deletion-notice-box">
            <i class="fas fa-exclamation-triangle"></i>
            <h2>Account Deletion Requested</h2>
            <p>
                You requested deletion of <strong>this account</strong> on <strong>{$requested}</strong>.
                <br>Reason: <em>{$reason}</em>
            </p>
            <p>
                The request is <strong>pending</strong> admin approval.
                Until it is approved, you can still <strong>cancel</strong> it.
            </p>

            <div class="deletion-notice-actions">
                <button class="btn-cancel-deletion" data-req-id="{$reqId}">
                    Cancel Deletion
                </button>
                <a href="logout.php" class="btn-logout">Log Out</a>
            </div>
        </div>
    </div>

</div>

<!-- CANCEL CONFIRMATION MODAL -->
<div id="cancelConfirmModal" class="modal">
    <div class="modal-content">
        <h2>Cancel Deletion Request?</h2>
        <p>Are you sure you want to cancel this deletion request?</p>
        <div class="modal-footer">
            <button type="button" class="cancel-btn" onclick="closeCancelConfirmModal()">No</button>
            <button type="button" class="confirm-btn" id="confirmCancelBtn">Yes, Cancel It</button>
        </div>
    </div>
</div>

<!-- SUCCESS MODAL -->
<div id="cancelSuccessModal" class="modal">
    <div class="modal-content">
        <i class="fas fa-check-circle"></i>
        <h2>Cancellation Successful</h2>
        <p>Your deletion request has been cancelled.</p>
        <div class="modal-footer">
            <button type="button" class="confirm-btn" id="successOkBtn">OK</button>
        </div>
    </div>
</div>

<script>
/* Open Cancel Confirmation Modal */
document.querySelectorAll('.btn-cancel-deletion').forEach(btn => {
    btn.addEventListener('click', function () {
        const reqId = this.dataset.reqId;
        document.getElementById('confirmCancelBtn').dataset.reqId = reqId;
        document.getElementById('cancelConfirmModal').classList.add('show');
    });
});

/* Close Confirm Modal */
function closeCancelConfirmModal() {
    document.getElementById('cancelConfirmModal').classList.remove('show');
}

/* Confirm Cancellation */
document.getElementById('confirmCancelBtn').addEventListener('click', function () {
    const reqId = this.dataset.reqId;
    if (!reqId) return;

    fetch('cancel_deletion.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'request_id=' + reqId
    })
    .then(r => r.json())
    .then(d => {
        closeCancelConfirmModal();

        if (d.success) {
            const successModal = document.getElementById('cancelSuccessModal');
            successModal.classList.add('show');
            document.getElementById('successOkBtn').onclick = () => {
                location.reload();
            };
        } else {
            alert(d.message);
        }
    })
    .catch(() => {
        closeCancelConfirmModal();
        alert('Network error – please try again later.');
    });
});
</script>

</body>
</html>
HTML;

    // -------------------------------------------------
    // 4. STOP the rest of the page from loading
    // -------------------------------------------------
    exit;
}
?>