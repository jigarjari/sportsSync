<?php
include('../../db.php'); 
require 'config.php';//razorpay key
require_once __DIR__ . '/../../vendor/autoload.php';//pdf library
session_start();

$data = json_decode(file_get_contents("php://input"), true);//json data->php

//login check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "msg" => "Login required"]);
    exit;
}

$booking_id = (int) $data['booking_id'];//booking id
$user_id = $_SESSION['user_id'];//user id

// Get booking details
$sql = "
SELECT b.booking_id, b.booking_date, b.payment_id, b.paid_amount, b.total_amount, b.turf_id, u.name as user_name, u.mobile as user_mobile, t.turf_name, MIN(s.start_time) as start_time
FROM bookingtb b
LEFT JOIN user u ON u.id = b.user_id
LEFT JOIN turftb t ON t.turf_id = b.turf_id
LEFT JOIN booking_slots_tb bs ON bs.booking_id = b.booking_id
LEFT JOIN turf_price_slotstb s ON s.price_slot_id = bs.slot_id
WHERE b.booking_id = $booking_id
AND b.user_id = $user_id
AND b.status = 'confirmed'
GROUP BY b.booking_id
";

$res = mysqli_query($conn, $sql);
//if query fails
if (!$res) {
    $err = mysqli_error($conn);
    file_put_contents("error_log.txt", "SQL Error: $err\n", FILE_APPEND);
    echo json_encode(["status" => "error", "msg" => "SQL Error: " . $err]);
    exit;
}
//alredy cancled or not found
if (mysqli_num_rows($res) == 0) {
    file_put_contents("error_log.txt", "No rows found for ID $booking_id and User $user_id\n", FILE_APPEND);
    echo json_encode(["status" => "error", "msg" => "Invalid booking or already cancelled. ID: $booking_id"]);
    exit;
}
//fetch result
$row = mysqli_fetch_assoc($res);

$booking_time = strtotime($row['booking_date']." ".$row['start_time']);
$current_time = time();

// 36-hour restriction
if(($booking_time - $current_time) <= (36 * 60 * 60)){
    echo json_encode([
        "status"=>"error",
        "msg"=>"Cannot cancel within 36 hours of the booking slot."
    ]);
    exit;
}

//RAZORPAY REFUND
$payment_id = $row['payment_id'];
$paid_amount = (int) ($row['paid_amount'] ?? 0);

if (!empty($payment_id) && $paid_amount > 0) {
    $amount_to_refund = $paid_amount * 100; // in paisa

    $payload = json_encode(['amount' => $amount_to_refund]);
    //start razorpay api request
    $ch = curl_init("https://api.razorpay.com/v1/payments/$payment_id/refund");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ":" . RAZORPAY_KEY_SECRET);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $refund_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        echo json_encode(["status" => "error", "msg" => "Refund failed (HTTP $http_code): " . $refund_response]);
        exit;
    }
}

try {
    //free slots
    mysqli_query($conn, "DELETE FROM booking_slots_tb WHERE booking_id=$booking_id");

    //find vendor
    $stmt1 = $conn->prepare("SELECT owner_id 
        FROM turftb 
        WHERE turf_id = (
            SELECT turf_id 
            FROM bookingtb 
            WHERE booking_id = ?)");

    $stmt1->bind_param("i", $booking_id);
    $stmt1->execute();
    $resultUser = $stmt1->get_result();

    if ($rowUser = $resultUser->fetch_assoc()) {

        $vendor_user_id = $rowUser['owner_id'];
        $pdf_url = "pdfs/cancellation_$booking_id.pdf";

        // insert notification
        $stmt2 = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message)
            VALUES (?, 'booking_cancelled', 'Booking Cancelled', 'Booking #$booking_id cancelled. Refund of ₹" . $row['paid_amount'] . " processed. <br><br> <a href=\"$pdf_url\" target=\"_blank\" style=\"background:#9526F3; color:white; padding:5px 10px; border-radius:5px; text-decoration:none; display:inline-block; font-size:12px; font-weight:bold;\">Download Cancellation PDF</a>')
        ");
        $stmt2->bind_param("i", $vendor_user_id);
        $stmt2->execute();
    }

    //  GENERATE CANCELLATION PDF
    $pdfDir = __DIR__ . "/../../pdfs/";
    if (!is_dir($pdfDir))
        mkdir($pdfDir, 0777, true);

    $html = "
    <style>
        body { font-family: Arial; background: #fff5f5; padding: 20px; }
        .container { border: 2px solid #ff4444; border-radius: 15px; padding: 25px; background: #fff; }
        .header { background: #ff4444; color: white; padding: 15px; text-align: center; border-radius: 10px; font-weight: bold; }
        .details { margin-top: 20px; font-size: 14px; }
        .box { background: #f8f8f8; padding: 10px; margin-bottom: 10px; border-radius: 5px; }
    </style>
    <div class='container'>
        <div class='header'>✖ BOOKING CANCELLED & REFUNDED</div>
        <div class='details'>
            <div class='box'><b>Booking ID:</b> #$booking_id</div>
            <div class='box'><b>Turf:</b> " . ($row['turf_name'] ?? 'N/A') . "</div>
            <div class='box'><b>User Name:</b> " . ($row['user_name'] ?? 'N/A') . "</div>
            <div class='box'><b>Booking Date:</b> " . ($row['booking_date'] ?? 'N/A') . "</div>
            <hr>
            <div class='box'><b>Total Amount:</b> ₹" . ($row['total_amount'] ?? '0') . "</div>
            <div class='box'><b>Refunded Amount (50%):</b> ₹" . ($row['paid_amount'] ?? '0') . "</div>
            <div class='box' style='color: green;'><b>Refund Status:</b> Processed via Razorpay</div>
            <div class='box'><b>Refund Trans ID:</b> " . ($payment_id ?? 'N/A') . "</div>
        </div>
    </div>
    ";

    $mpdf = new \Mpdf\Mpdf();
    $pdfPath = $pdfDir . "cancellation_$booking_id.pdf";
    $mpdf->WriteHTML($html);
    $mpdf->Output($pdfPath, 'F');

    // UPDATE booking
    mysqli_query($conn, "UPDATE bookingtb SET status='cancelled' WHERE booking_id=$booking_id");

} catch (Exception $e) {
    file_put_contents("error_log.txt", "Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(["status" => "error", "msg" => "System Error: " . $e->getMessage()]);
    exit;
}

echo json_encode([
    "status" => "success",
    "pdf_url" => "pdfs/cancellation_$booking_id.pdf"
]);