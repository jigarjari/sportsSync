<?php
/**
 * Blocks slots in booking_slots_tb for a tournament.
 * Creates one bookingtb entry and links all slot rows to it.
 *
 * @param mysqli $conn
 * @param array  $t         — tournament row from tournamenttb
 * @param int    $user_id   — who is paying (host_id)
 * @param string $payment_id — razorpay payment id (or 'AUTO' for vendor)
 * @param float  $paid_amount
 * @return int  booking_id created
 */
function blockTournamentSlots($conn, $t, $user_id, $payment_id, $paid_amount) {
    $turf_id    = (int)$t['turf_id'];
    $sport_id   = (int)$t['sport_id'];
    $tid        = (int)$t['tournament_id'];
    $start_time = $t['tournament_time'];
    $end_time   = $t['end_time'];

    // Get courts for this tournament
    $courtsRes = mysqli_query($conn,
        "SELECT court_id FROM tournament_courtstb WHERE tournament_id = $tid");
    $courtIds = [];
    while ($c = mysqli_fetch_assoc($courtsRes)) $courtIds[] = (int)$c['court_id'];

    if (empty($courtIds)) return 0;

    // Get matching price slots for this turf+sport within tournament time range
    // A slot overlaps if slot.start_time < tournament.end_time AND slot.end_time > tournament.start_time
    $courtList = implode(',', $courtIds);
    $slotsRes = mysqli_query($conn,
        "SELECT price_slot_id FROM turf_price_slotstb
         WHERE turf_id = $turf_id
           AND sport_id = $sport_id
           AND start_time >= '$start_time'
           AND end_time <= '$end_time'");
    $slotIds = [];
    while ($s = mysqli_fetch_assoc($slotsRes)) $slotIds[] = (int)$s['price_slot_id'];

    if (empty($slotIds)) return 0;

    // Count days
    $start = new DateTime($t['start_date']);
    $end   = new DateTime($t['end_date']);
    $end->modify('+1 day');
    $days  = 0;
    $tmp   = clone $start;
    while ($tmp < $end) { $days++; $tmp->modify('+1 day'); }

    // Calculate total amount
    $hours = (strtotime($end_time) - strtotime($start_time)) / 3600;
    $priceRes = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT AVG(price_per_hour) as avg_price FROM turf_price_slotstb
         WHERE turf_id = $turf_id AND sport_id = $sport_id
         AND start_time >= '$start_time' AND end_time <= '$end_time'"));
    $avgPrice    = (float)($priceRes['avg_price'] ?? 0);
    $totalAmount = $avgPrice * $hours * count($courtIds) * $days;

    // Create booking entry in bookingtb
    // Use court_id of first court (main court)
    $firstCourt = $courtIds[0];
    $token = 'TOURN_' . $tid . '_' . time();
    $status = ($payment_id === 'AUTO') ? 'confirmed' : 'confirmed';
    $payStatus = ($payment_id === 'AUTO') ? 'paid' : 'paid';

    $bStmt = mysqli_prepare($conn,
    "INSERT INTO bookingtb 
        (turf_id, sport_id, court_id, user_id, total_amount, booking_date,
         start_time, end_time, booking_qr_token, status, payment_id,
         paid_amount, payment_status, booking_type)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?, ?, 'TOURNAMENT')");

mysqli_stmt_bind_param($bStmt, "iiiidsssssds",
    $turf_id,       // i
    $sport_id,      // i
    $firstCourt,    // i
    $user_id,       // i
    $totalAmount,   // d
    $t['start_date'], // s
    $start_time,    // s
    $end_time,      // s
    $token,         // s
    $payment_id,    // s
    $totalAmount,   // d
    $payStatus      // s
);
    mysqli_stmt_execute($bStmt);
    $booking_id = mysqli_insert_id($conn);

    // Insert into booking_slots_tb for every slot × every date
    $slotStmt = mysqli_prepare($conn,
        "INSERT IGNORE INTO booking_slots_tb (booking_id, slot_id, booking_date)
         VALUES (?, ?, ?)");

    $current = clone $start;
    while ($current < $end) {
        $dateStr = $current->format('Y-m-d');
        foreach ($slotIds as $sid) {
            mysqli_stmt_bind_param($slotStmt, "iis", $booking_id, $sid, $dateStr);
            mysqli_stmt_execute($slotStmt);
        }
        $current->modify('+1 day');
    }

    // Link booking_id back to tournament
    mysqli_query($conn,
        "UPDATE tournamenttb SET booking_id = $booking_id WHERE tournament_id = $tid");

    return $booking_id;
}