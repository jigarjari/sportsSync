<?php
session_start();
include('../db.php');
require_once 'apiBooking/config.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) { header("Location: ../login.php"); exit; }

$tid = (int)($_GET['id'] ?? 0);
if (!$tid) { header("Location: tournaments_list.php"); exit; }

// Fetch tournament — must belong to this user and be A_UNPAID
$stmt = mysqli_prepare($conn,
    "SELECT tt.*, tf.turf_name, tf.location,
            c.city_name
     FROM tournamenttb tt
     LEFT JOIN turftb tf ON tf.turf_id = tt.turf_id
     LEFT JOIN citytb c  ON c.city_id  = tf.city_id
     WHERE tt.tournament_id = ? AND tt.host_id = ? AND tt.status = 'A_UNPAID'");
mysqli_stmt_bind_param($stmt, "ii", $tid, $user_id);
mysqli_stmt_execute($stmt);
$t = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$t) { header("Location: tournaments_list.php"); exit; }

// Calculate amount
// Replace the old $totalAmount calculation with:
$days  = (int)((strtotime($t['end_date']) - strtotime($t['start_date'])) / 86400) + 1;
$hours = (strtotime($t['end_time']) - strtotime($t['tournament_time'])) / 3600;

$priceRes = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT AVG(price_per_hour) as avg_price 
     FROM turf_price_slotstb
     WHERE turf_id = {$t['turf_id']} 
       AND sport_id = {$t['sport_id']}
       AND start_time >= '{$t['tournament_time']}'
       AND end_time <= '{$t['end_time']}'"));
$avgPrice = (float)($priceRes['avg_price'] ?? 500);

$courtsRes    = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as cnt FROM tournament_courtstb WHERE tournament_id = $tid"));
$courtCount   = (int)$courtsRes['cnt'];
$totalAmount  = $avgPrice * $hours * $courtCount * $days;

$successMessage = '';

// Handle POST — after payment confirmed
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentId = trim($_POST['razorpay_payment_id'] ?? '');
    if (!empty($paymentId)) {
        require_once '../includes/block_tournament_slots.php';
        mysqli_begin_transaction($conn);
        try {
            // Block slots and create booking
            $booking_id = blockTournamentSlots(
                $conn, $t, $user_id, $paymentId, $totalAmount
            );

            // Activate tournament
            mysqli_query($conn,
                "UPDATE tournamenttb SET status = 'A' WHERE tournament_id = $tid");

            // Send live notification
            $msg = "Your tournament '{$t['tournament_name']}' is now LIVE!";
            $nStmt = mysqli_prepare($conn,
                "INSERT INTO notifications 
                    (user_id, type, title, message, reference_id, reference_type)
                 VALUES (?, 'tournament_live', 'Tournament is Live!', ?, ?, 'tournament')");
            mysqli_stmt_bind_param($nStmt, "isi", $user_id, $msg, $tid);
            mysqli_stmt_execute($nStmt);

            mysqli_commit($conn);
            $successMessage = "Payment successful! Your tournament is now live.";

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Something went wrong: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activate Tournament</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--accent:#9526F3;--accent-soft:rgba(149,38,243,.15);--border:rgba(149,38,243,.3);--muted:#9ca3af}
body{margin:0;min-height:100vh;background:#0e0f11;
  background-image:linear-gradient(45deg,#1f1f1f 25%,transparent 25%),
    linear-gradient(-45deg,#1f1f1f 25%,transparent 25%),
    linear-gradient(45deg,transparent 75%,#1f1f1f 75%),
    linear-gradient(-45deg,transparent 75%,#1f1f1f 75%);
  background-size:6px 6px;background-position:0 0,0 3px,3px -3px,-3px 0;
  color:#f1f5f9;font-family:'Segoe UI',sans-serif;}
.shell{width:min(680px,calc(100% - 2rem));margin:40px auto}
.card{background:rgba(16,16,22,.94);border:1px solid var(--border);border-radius:24px;padding:28px}
.t-title{font-size:1.5rem;font-weight:800;color:#fff;margin:0 0 6px}
.meta{display:flex;flex-wrap:wrap;gap:14px;margin-top:10px}
.mi{display:flex;align-items:center;gap:7px;font-size:.85rem;color:var(--muted)}
.mi i{color:var(--accent)}
.divider{border-top:1px solid rgba(255,255,255,.07);margin:20px 0}
.amount-box{
  background:var(--accent-soft);border:1px solid var(--border);
  border-radius:16px;padding:18px 20px;margin:18px 0;
}
.amount-box .label{font-size:.85rem;color:var(--muted)}
.amount-box .val{font-size:2rem;font-weight:800;color:#fff}
.calc-row{display:flex;justify-content:space-between;font-size:.85rem;
  color:var(--muted);margin-bottom:6px}
.calc-row strong{color:#e2e8f0}
.btn-pay{
  width:100%;padding:14px;border-radius:16px;border:none;
  background:linear-gradient(135deg,#9526F3,#6d11bf);color:#fff;
  font-weight:700;font-size:1rem;cursor:pointer;transition:.2s;
}
.btn-pay:hover{box-shadow:0 8px 24px rgba(149,38,243,.4)}
.btn-pay:disabled{opacity:.5;cursor:not-allowed}
.success-wrap{text-align:center;padding:30px 0}
.success-wrap i{font-size:3.5rem;color:#4ade80;display:block;margin-bottom:14px}
</style>
</head>
<body>
<div class="shell">
  <a href="tournaments_list.php" 
     style="display:inline-flex;align-items:center;gap:8px;color:var(--muted);
            text-decoration:none;margin-bottom:20px;font-size:.9rem">
    <i class="bi bi-arrow-left"></i> Back to Tournaments
  </a>

  <div class="card">
    <?php if ($successMessage): ?>
      <div class="success-wrap">
        <i class="bi bi-check-circle-fill"></i>
        <h3 style="color:#fff"><?php echo $successMessage; ?></h3>
        <p style="color:var(--muted)">Your tournament is now visible to all users.</p>
        <a href="tournaments_list.php" class="btn-pay" style="display:inline-block;width:auto;padding:12px 28px;text-decoration:none;margin-top:10px">
          View My Tournaments
        </a>
      </div>
    <?php else: ?>

      <div style="font-size:.82rem;color:var(--muted);margin-bottom:6px">
        <i class="bi bi-trophy-fill" style="color:var(--accent)"></i> Activate Tournament
      </div>
      <div class="t-title"><?php echo htmlspecialchars($t['tournament_name']); ?></div>

      <div class="meta">
        <div class="mi"><i class="bi bi-geo-alt-fill"></i>
          <?php echo htmlspecialchars($t['turf_name'] . ' — ' . $t['location']); ?>
        </div>
        <div class="mi"><i class="bi bi-calendar-event"></i>
          <?php echo $t['start_date']; ?> → <?php echo $t['end_date']; ?>
        </div>
        <div class="mi"><i class="bi bi-clock"></i>
          <?php echo substr($t['tournament_time'],0,5); ?> – <?php echo substr($t['end_time'],0,5); ?>
        </div>
      </div>

      <div class="divider"></div>

      <div style="font-size:.9rem;color:#d9b6ff;font-weight:600;margin-bottom:12px">
        <i class="bi bi-calculator-fill me-2"></i>Slot Booking Calculation
      </div>
      <div class="calc-row">
        <span>Duration per day</span>
        <strong><?php echo $hours; ?> hours</strong>
      </div>
      <div class="calc-row">
        <span>Courts selected</span>
        <strong><?php echo $courtCount; ?></strong>
      </div>
      <div class="calc-row">
        <span>Tournament days</span>
        <strong><?php echo $days; ?></strong>
      </div>
      <div class="calc-row">
  <span>Price per hour (avg)</span>
  <strong>₹<?php echo number_format($avgPrice, 2); ?></strong>
</div>

      <div class="amount-box">
        <div class="label">Total slot booking amount</div>
        <div class="val">₹<?php echo number_format($totalAmount, 2); ?></div>
        <div style="font-size:.78rem;color:var(--muted);margin-top:4px">
<?php echo $hours; ?>hrs × <?php echo $courtCount; ?> courts × <?php echo $days; ?> days × ₹<?php echo number_format($avgPrice,2); ?>/hr        </div>
      </div>

      <div style="padding:12px 16px;border-radius:12px;background:rgba(34,197,94,.08);
        border:1px solid rgba(34,197,94,.2);color:#86efac;font-size:.85rem;margin-bottom:18px">
        <i class="bi bi-info-circle-fill me-2"></i>
        Once paid, your tournament will go <strong>live immediately</strong> and 
        the slots will be blocked from regular bookings.
      </div>

      <form method="post" id="payForm">
        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id" value="">
        <button type="button" class="btn-pay" id="payBtn" onclick="startPayment()">
          <i class="bi bi-lock-fill me-2"></i>
          Pay ₹<?php echo number_format($totalAmount, 2); ?> & Activate Tournament
        </button>
      </form>

    <?php endif; ?>
  </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
async function startPayment() {
  const btn = document.getElementById('payBtn');
  btn.disabled = true;
  btn.textContent = 'Creating order...';

  try {
    const res = await fetch('apiBooking/create_order.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({amount: <?php echo $totalAmount; ?>})
    });
    const order = await res.json();

    if (order.error) {
      alert('Error: ' + order.error);
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Pay ₹<?php echo number_format($totalAmount,2); ?> & Activate Tournament';
      return;
    }

    const options = {
      key: "<?php echo RAZORPAY_KEY_ID; ?>",
      amount: order.amount,
      currency: order.currency,
      order_id: order.id,
      name: 'SportSync',
      description: 'Tournament Slot Activation — <?php echo addslashes($t['tournament_name']); ?>',
      handler: function(response) {
        document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
        document.getElementById('payForm').submit();
      },
      modal: {
        ondismiss: function() {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Pay ₹<?php echo number_format($totalAmount,2); ?> & Activate Tournament';
        }
      },
      prefill: {
        name: '<?php echo addslashes($_SESSION['name'] ?? ''); ?>',
        email: '<?php echo addslashes($_SESSION['email'] ?? ''); ?>'
      },
      theme: {color: '#9526F3'}
    };

    new Razorpay(options).open();

  } catch(e) {
    alert('Could not initiate payment.');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Pay ₹<?php echo number_format($totalAmount,2); ?> & Activate Tournament';
  }
}
</script>
</body>
</html>