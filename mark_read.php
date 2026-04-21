<?php
session_start();
include('db.php');

if (!isset($_SESSION['user_id'])) exit;

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

echo "success";