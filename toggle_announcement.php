<?php
//toggle_announcement.php
session_start();
require 'config.php';

if (!isset($_GET['id'], $_GET['action'])) {
    echo json_encode(['success'=>false, 'message'=>'Invalid request']);
    exit();
}

if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    echo json_encode(['success'=>false, 'message'=>'Unauthorized']);
    exit();
}

$id       = (int)$_GET['id'];
$action   = $_GET['action'];
$newStat  = ($action === 'unarchive') ? 'active' : 'archived';
$oldStat  = $newStat === 'active' ? 'archived' : 'active';

try {
    // title for log
    $t = $conn->prepare("SELECT title FROM announcements WHERE id=?");
    $t->execute([$id]);
    $title = $t->fetchColumn() ?: 'Untitled';

    // toggle
    $up = $conn->prepare("UPDATE announcements SET status=? WHERE id=?");
    $up->execute([$newStat, $id]);

    // log
    $log = $conn->prepare("
        INSERT INTO activity_logs (admin_id, action_type, action_details, target_id)
        VALUES (:admin_id, 'announcement_toggle', :details, :target_id)
    ");
    $log->execute([
        ':admin_id'   => $_SESSION['admin_id'],
        ':details'    => "Changed announcement \"{$title}\" from {$oldStat} to {$newStat}.",
        ':target_id'  => $id
    ]);

    // **set flash message for the UI**
    $_SESSION['announcement_message'] = "Announcement {$action}d successfully.";

    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    $_SESSION['announcement_message'] = "Failed: " . $e->getMessage();
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>