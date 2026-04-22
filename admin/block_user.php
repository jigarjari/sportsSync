<?php
include('../db.php');

$id = (int)$_GET['id'];

// 1. BLOCK USER
mysqli_query($conn, "UPDATE user SET status='blocked' WHERE id='$id'");

// 2. CHECK IF USER IS VENDOR
$res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM user WHERE id='$id'"));

if($res['role'] == 'Vendor'){
    
    // BLOCK ALL TURFS OF THIS VENDOR
    mysqli_query($conn, "
        UPDATE turftb 
        SET status='blocked' 
        WHERE owner_id='$id'
    ");
}

header("Location: manage_users.php");
exit;