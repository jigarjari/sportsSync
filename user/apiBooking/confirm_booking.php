<?php
session_start();
header('Content-Type: application/json');
require '../../db.php';
require_once '../../libs/phpqrcode/qrlib.php';
require_once __DIR__ . '/../../vendor/autoload.php';
include_once('../../env.php');

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(["status" => "error", "msg" => "Login required"]);
  exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$user_id     = $_SESSION['user_id'];
$turf_id     = (int)$data['turf_id'];
$court_id    = (int)$data['court_id'];
$sport_id    = (int)$data['sport_id']; 
$bookingDate = $data['booking_date'];
$total       = (int)$data['total'];
$paid_amount = (int)$data['paid_amount'];
$payment_id = $data['payment_id'] ?? '';
$slots       = $data['slots'];
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
mysqli_begin_transaction($conn);

try {

  if (empty($payment_id)) {
  throw new Exception("Payment not completed");
  }

  $check = mysqli_query($conn, "
    SELECT booking_id FROM bookingtb WHERE payment_id = '$payment_id'
  ");

  if (mysqli_num_rows($check) > 0) {
    throw new Exception("Duplicate payment");
  }

  // Insert booking (SPORT ID INCLUDED)
  $sql = "
  INSERT INTO bookingtb 
  (turf_id, court_id, sport_id, user_id, booking_date, total_amount, paid_amount, status, payment_id, payment_status)
  VALUES 
  ($turf_id, $court_id, $sport_id, $user_id, '$bookingDate', $total, $paid_amount, 'confirmed', '$payment_id', 'half-paid')
  ";
  mysqli_query($conn, $sql);
  $booking_id = mysqli_insert_id($conn);


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

        $user_id = $rowUser['owner_id'];

        // insert notification
        $stmt2 = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message)
            VALUES (?, 'Reminder', 'New Booking', 'Check the new booking.')
        ");
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();
    }



  // Save payment details
mysqli_query($conn, "
  INSERT INTO payments 
  (booking_id, razorpay_payment_id, amount, currency, status) 
  VALUES 
  ($booking_id, '$payment_id', $paid_amount, 'INR', 'success')
");

  // Generate secure QR token
  $secretKey = $qrSercretKey;
  $raw = $booking_id . "|" . $user_id . "|" . $turf_id . "|" . $bookingDate;

  $qr_token = hash("sha256", $raw . "|" . $secretKey);

   // Ensure folders exist (for PDF and QR)
   $qrDir  = __DIR__ . "/../../qrcodes/";
   $pdfDir = __DIR__ . "/../../pdfs/";
   
   if (!is_dir($qrDir)) mkdir($qrDir, 0777, true);
   if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);

// QR output path
$qrPath = $qrDir . "booking_" . $booking_id . ".png";

// Generate QR
QRcode::png($qr_token, $qrPath, QR_ECLEVEL_H, 5);

  // Save token
  mysqli_query($conn, "
    UPDATE bookingtb 
    SET booking_qr_token = '$qr_token', qr_generated_at = NOW()
    WHERE booking_id = $booking_id
  ");

  // Insert booking slots
  foreach ($slots as $slot_id) {
    $slot_id = (int)$slot_id;
    $sql = "
      INSERT INTO booking_slots_tb (booking_id, slot_id, booking_date)
      VALUES ($booking_id, $slot_id, '$bookingDate')
    ";
    mysqli_query($conn, $sql);
  }
  $slots = [];

$slotSql = "
    SELECT s.start_time, s.end_time
    FROM booking_slots_tb bs
    JOIN turf_price_slotstb s ON bs.slot_id = s.price_slot_id
    WHERE bs.booking_id = $booking_id
    ORDER BY s.start_time
";
$slotRes = mysqli_query($conn, $slotSql);

while ($row = mysqli_fetch_assoc($slotRes)) {
    $slots[] = date('H:i', strtotime($row['start_time'])) .
               ' - ' .
               date('H:i', strtotime($row['end_time']));
}

// ================== mPDF START ==================

$slotsHtml = "";
foreach ($slots as $slot) {
    $slotsHtml .= "
    <div class='slot'>
        ⏱ $slot
    </div>";
}

$html = "
<style>
body {
    font-family: Arial;
    background: #f4f6f9;
}

.container {
    max-width: 700px;
    margin: auto;
    background: #fff;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

.header {
    background: linear-gradient(90deg, #28a745, #20c997);
    color: white;
    padding: 15px;
    border-radius: 10px;
    text-align: center;
    font-size: 20px;
    font-weight: bold;
}

.booking-id {
    text-align: center;
    margin: 15px 0;
    font-size: 18px;
    font-weight: bold;
}

.section-title {
    text-align: center;
    font-weight: bold;
    margin: 20px 0 10px;
    font-size: 16px;
}

.details {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.details div {
    width: 48%;
}

.box {
    background: #f1f3f5;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 10px;
}

.slot {
    background: #e9ecef;
    padding: 8px;
    border-radius: 8px;
    margin: 5px 0;
    text-align: center;
    font-weight: bold;
}

.qr {
    text-align: center;
    margin-top: 20px;
}

.qr img {
    border: 3px solid #28a745;
    border-radius: 10px;
    padding: 5px;
}

.note {
    text-align: center;
    font-size: 12px;
    margin-top: 10px;
    color: #555;
}

.rules {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    margin-top: 20px;
}

.rules ul {
    margin: 0;
    padding-left: 18px;
}

.rules li {
    margin-bottom: 6px;
}
</style>

<div class='container'>

<div class='header'>
    ✔ BOOKING CONFIRMED
</div>

<div class='booking-id'>
    Booking ID: $booking_id
</div>

<div class='section-title'>BOOKING DETAILS</div>

<div class='details'>
    <div class='box'>
        <b>Name</b><br>
        {$_SESSION['name']}
    </div>

    <div class='box'>
        <b>Booking Date</b><br>
        $bookingDate
    </div>
</div>

<div class='details'>
    <div class='box'>
        <b>Mobile</b><br>
        {$_SESSION['mobile']}
    </div>

    <div class='box'>
        <b>Total Amount</b><br>
        ₹ $total
    </div>
</div>

<div class='details'>
    <div class='box'>
        <b>Paid Amount (50%)</b><br>
        ₹ $paid_amount
    </div>

    <div class='box' style='background: #ffebeb; color: #d32f2f;'>
        <b>Balance Due</b><br>
        ₹ " . ($total - $paid_amount) . "
    </div>
</div>

<div class='section-title'>BOOKED TIME SLOTS</div>

$slotsHtml

<div class='section-title'>SCAN QR AT TURF</div>

<div class='qr'>
    <img src='$qrPath' width='150'>
</div>

<div class='note'>
    Show this QR code at entry for verification
</div>

<div class='rules'>
    <b>⚠ Rules & Instructions</b>
    <ul>
        <li>Booking is non-transferable</li>
        <li>Reach 10 minutes early</li>
        <li>QR must be scanned at entry</li>
        <li>Late arrival may reduce play time</li>
    </ul>
</div>
</div>
";

$mpdf = new \Mpdf\Mpdf();
$pdfPath = $pdfDir . "booking_$booking_id.pdf";
$mpdf->WriteHTML($html);
$mpdf->Output($pdfPath, 'F');

// ================== mPDF END ==================

if (file_exists($qrPath)) {
    unlink($qrPath);
}

mysqli_commit($conn);

echo json_encode([
    "status" => "success",
    "booking_id" => $booking_id,
    "pdf_url" => "pdfs/booking_$booking_id.pdf" // 🚀 This path is relative to the root project
]);
exit;


} catch (Exception $e) {
  mysqli_rollback($conn);
  if ($e->getCode() == 1062) {

        echo json_encode([
            "status" => "error",
            "msg" => "Just now slot is booked by some another user"
        ]);

    } else {

        echo json_encode([
            "status" => "error",
            "msg" => "Database error: " . $e->getMessage()
        ]);
    }
}
