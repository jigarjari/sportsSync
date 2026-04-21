<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id'])) exit;

$user_id = $_SESSION['user_id'];

// unread count
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
$countStmt->bind_param("i", $user_id);
$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();

// latest notifications
$listStmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$listStmt->bind_param("i", $user_id);
$listStmt->execute();
$listResult = $listStmt->get_result();

$notifications = [];
while ($row = $listResult->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode([
    "count" => $countResult['total'],
    "notifications" => $notifications
]);