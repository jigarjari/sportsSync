<?php
session_start();
include('../db.php');
require_once 'apiBooking/config.php';
// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$successMessage = "";
$errors = [];

/* ── FETCH TOURNAMENT ── */
$tournament_id = (int)($_GET['tournament_id'] ?? $_POST['tournament_id'] ?? 0);
if ($tournament_id <= 0) {
    header("Location: tournaments_list.php");
    exit;
}

$tStmt = mysqli_prepare($conn,
    "SELECT tt.*, tf.turf_name, tf.location, c.city_name
     FROM tournamenttb tt
     LEFT JOIN turftb tf ON tf.turf_id = tt.turf_id
     LEFT JOIN citytb c  ON c.city_id  = tf.city_id
     WHERE tt.tournament_id = ? AND tt.status = 'A'");
mysqli_stmt_bind_param($tStmt, "i", $tournament_id);
mysqli_stmt_execute($tStmt);
$tRes = mysqli_stmt_get_result($tStmt);
$tournament = mysqli_fetch_assoc($tRes);

if (!$tournament) {
    header("Location: tournaments_list.php");
    exit;
}

// Check if user already registered
$checkStmt = mysqli_prepare($conn,
    "SELECT id FROM tournament_registrations WHERE tournament_id = ? AND user_id = ?");
mysqli_stmt_bind_param($checkStmt, "ii", $tournament_id, $user_id);
mysqli_stmt_execute($checkStmt);
mysqli_stmt_store_result($checkStmt);
$alreadyRegistered = mysqli_stmt_num_rows($checkStmt) > 0;

// Check if full
$countStmt = mysqli_prepare($conn,
    "SELECT COUNT(*) FROM tournament_registrations WHERE tournament_id = ?");
mysqli_stmt_bind_param($countStmt, "i", $tournament_id);
mysqli_stmt_execute($countStmt);
mysqli_stmt_bind_result($countStmt, $currentCount);
mysqli_stmt_fetch($countStmt);
mysqli_stmt_close($countStmt);
$isFull = $currentCount >= $tournament['max_participation'];

$entryFee = (float)($tournament['entry_fee'] ?? 0);
$hasFee   = $entryFee > 0;

/* ── FORM SUBMIT ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyRegistered && !$isFull) {

    function clean($d){ return htmlspecialchars(trim($d)); }

    $full_name       = clean($_POST['full_name'] ?? '');
    $mobile_number   = clean($_POST['mobile_number'] ?? '');
    $email           = clean($_POST['email'] ?? '');
    $age             = clean($_POST['age'] ?? '');
    $team_name       = clean($_POST['team_name'] ?? '');
    $team_size       = (int)($_POST['team_size'] ?? 0);
    $captain_name    = clean($_POST['captain_name'] ?? '');
    $captain_mobile  = clean($_POST['captain_mobile'] ?? '');
    $captain_email   = clean($_POST['captain_email'] ?? '');
    $emergency       = clean($_POST['emergency_contact'] ?? '');
    $player_list     = clean($_POST['player_list'] ?? '');
    $team_notes      = clean($_POST['team_notes'] ?? '');
    $accepted_terms  = isset($_POST['accept_terms']) ? 1 : 0;
    $razorpay_payment_id = clean($_POST['razorpay_payment_id'] ?? '');
    $payment_status  = 'pending';

    // Determine payment status
    if ($hasFee) {
        if (!empty($razorpay_payment_id)) {
            $payment_status = 'paid';
        } else {
            $errors['payment'] = "Payment is required to register.";
        }
    } else {
        $payment_status = 'free';
    }

    // Validations
    if (empty($full_name) || !preg_match("/^[a-zA-Z ]+$/", $full_name))
        $errors['full_name'] = "Only letters allowed";
    if (!preg_match("/^[0-9]{10}$/", $mobile_number))
        $errors['mobile_number'] = "Enter valid 10-digit number";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors['email'] = "Invalid email";
    if (!filter_var($age, FILTER_VALIDATE_INT) || $age < 10 || $age > 70)
        $errors['age'] = "Age must be between 10–70";
    if (empty($team_name))
        $errors['team_name'] = "Required";
    if ($team_size < 1 || $team_size > (int)$tournament['max_players_per_team'])
        $errors['team_size'] = "Must be between 1–" . $tournament['max_players_per_team'];
    if (!preg_match("/^[a-zA-Z ]+$/", $captain_name))
        $errors['captain_name'] = "Only letters allowed";
    if (!preg_match("/^[0-9]{10}$/", $captain_mobile))
        $errors['captain_mobile'] = "Invalid number";
    if (!filter_var($captain_email, FILTER_VALIDATE_EMAIL))
        $errors['captain_email'] = "Invalid email";
    if (!preg_match("/^[0-9]{10}$/", $emergency))
        $errors['emergency_contact'] = "Invalid number";
    if (!$accepted_terms)
        $errors['accept_terms'] = "You must accept the terms";

    if (empty($errors)) {
        // Upload helper
        function uploadFile($input, $folder = 'uploads/') {
            if (!empty($_FILES[$input]['name'])) {
                $allowed = ['image/jpeg','image/png','image/jpg'];
                if (!in_array($_FILES[$input]['type'], $allowed)) return null;
                if ($_FILES[$input]['size'] > 2 * 1024 * 1024) return null;
                $fileName = time() . '_' . basename($_FILES[$input]['name']);
                move_uploaded_file($_FILES[$input]['tmp_name'], $folder . $fileName);
                return $fileName;
            }
            return null;
        }

        $profile_photo = uploadFile('profile_photo');
        $team_logo     = uploadFile('team_logo');

        $stmt = mysqli_prepare($conn,
            "INSERT INTO tournament_registrations
               (tournament_id, user_id, full_name, mobile_number, email, age,
                profile_photo, team_name, team_size, captain_name, captain_mobile,
                captain_email, team_logo, emergency_contact, player_list,
                team_notes, accepted_terms, payment_status, razorpay_payment_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        mysqli_stmt_bind_param($stmt, "iisssississsssssisi",
            $tournament_id,
            $user_id,
            $full_name,
            $mobile_number,
            $email,
            $age,
            $profile_photo,
            $team_name,
            $team_size,
            $captain_name,
            $captain_mobile,
            $captain_email,
            $team_logo,
            $emergency,
            $player_list,
            $team_notes,
            $accepted_terms,
            $payment_status,
            $razorpay_payment_id
        );

        if (mysqli_stmt_execute($stmt)) {
            $successMessage = "Registration successful! " .
                ($hasFee ? "Payment of ₹" . number_format($entryFee,2) . " confirmed." : "");
            $alreadyRegistered = true;
        } else {
            $errors['db'] = "Something went wrong. Please try again.";
        }
    }
}

$venue = $tournament['is_external_turf']
       ? htmlspecialchars($tournament['external_turf_name'] . ' — ' . $tournament['external_location'])
       : htmlspecialchars(($tournament['turf_name'] ?? '?') . ', ' . ($tournament['location'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — <?php echo htmlspecialchars($tournament['tournament_name']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--bg:#0e0f11;--card:rgba(16,16,20,0.94);--border:rgba(149,38,243,0.32);
  --accent:#9526F3;--accent-soft:rgba(149,38,243,0.18);--muted:#9ca3af;}
*,*::before,*::after{box-sizing:border-box}
body{margin:0;min-height:100vh;background:#0e0f11;
  background-image:linear-gradient(45deg,#1f1f1f 25%,transparent 25%),
    linear-gradient(-45deg,#1f1f1f 25%,transparent 25%),
    linear-gradient(45deg,transparent 75%,#1f1f1f 75%),
    linear-gradient(-45deg,transparent 75%,#1f1f1f 75%);
  background-size:6px 6px;background-position:0 0,0 3px,3px -3px,-3px 0;
  color:#f8f9fb;font-family:'Segoe UI',sans-serif;}
.page-shell{width:min(960px,calc(100% - 2rem));margin:32px auto}
.hero-card,.form-card{background:var(--card);border:1px solid var(--border);
  border-radius:28px;box-shadow:0 24px 60px rgba(0,0,0,.35);}
.hero-card{padding:28px;margin-bottom:20px}
.eyebrow{display:inline-flex;align-items:center;gap:.5rem;padding:7px 14px;
  border-radius:999px;background:var(--accent-soft);color:#d9b6ff;font-size:.88rem;margin-bottom:10px;}
.hero-card h1{margin:0 0 10px;font-size:clamp(1.6rem,3.5vw,2.2rem);color:#fff}
.meta-row{display:flex;gap:20px;flex-wrap:wrap;margin-top:12px}
.meta-item{display:flex;align-items:center;gap:6px;font-size:.85rem;color:var(--muted)}
.meta-item i{color:var(--accent)}
.prize-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.prize-chip{padding:4px 12px;border-radius:999px;font-size:.78rem;font-weight:600}
.prize-gold{background:rgba(234,179,8,.15);color:#fbbf24;border:1px solid rgba(234,179,8,.25)}
.prize-silver{background:rgba(156,163,175,.12);color:#d1d5db;border:1px solid rgba(156,163,175,.2)}
.prize-entry{background:var(--accent-soft);color:#d9b6ff;border:1px solid rgba(149,38,243,.25)}
.form-card{padding:28px}
.section-block+.section-block{margin-top:26px;padding-top:22px;border-top:1px solid rgba(255,255,255,.06)}
.section-title{margin:0 0 16px;font-size:1.05rem;color:var(--accent);font-weight:700}
.form-label{color:#f5f5f5;font-weight:600;margin-bottom:7px}
.form-control,.form-select,.form-control:focus,.form-select:focus{
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);
  color:#fff;min-height:48px;border-radius:14px;box-shadow:none;}
.form-control::placeholder{color:#8f96a3}
.form-select option{color:#111827}
textarea.form-control{min-height:110px;resize:vertical}
.checkbox-line{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;
  border-radius:14px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);}
.checkbox-line input{margin-top:3px;accent-color:var(--accent);transform:scale(1.15)}
.btn-primary{background:linear-gradient(135deg,#9526F3,#6d11bf);border:none;
  min-height:52px;border-radius:16px;font-weight:700;}
.btn-primary:hover{background:linear-gradient(135deg,#a240ff,#7516cd)}
.btn-primary:disabled{opacity:.5;cursor:not-allowed}

/* Payment box */
.pay-box{border:1px solid rgba(149,38,243,.4);border-radius:18px;
  padding:20px;background:rgba(149,38,243,.06);margin-bottom:20px;}
.pay-box h5{color:#d9b6ff;margin:0 0 6px;font-size:1rem}
.pay-amount{font-size:2rem;font-weight:800;color:#fff}
.pay-amount small{font-size:1rem;color:var(--muted)}
.btn-pay{width:100%;padding:13px;border-radius:14px;border:none;
  background:linear-gradient(135deg,#9526F3,#6d11bf);color:#fff;
  font-weight:700;font-size:1rem;cursor:pointer;transition:.2s;margin-top:12px}
.btn-pay:hover{box-shadow:0 8px 24px rgba(149,38,243,.4)}
.pay-success{display:flex;align-items:center;gap:10px;padding:12px 16px;
  border-radius:12px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);
  color:#4ade80;font-weight:600;margin-top:10px}

/* Success state */
.success-wrap{text-align:center;padding:40px 20px}
.success-wrap .icon{font-size:3.5rem;color:#4ade80;display:block;margin-bottom:14px}

@media(max-width:640px){.page-shell{width:calc(100% - 1rem);margin:14px auto}
  .hero-card,.form-card{padding:16px;border-radius:20px}}
</style>
</head>
<body>
<div class="page-shell">

  <!-- HERO -->
  <section class="hero-card">
    <div class="eyebrow"><i class="bi bi-trophy-fill"></i> Tournament Registration</div>
    <h1><?php echo htmlspecialchars($tournament['tournament_name']); ?></h1>
    <div class="meta-row">
      <div class="meta-item"><i class="bi bi-geo-alt-fill"></i><?php echo $venue; ?></div>
      <div class="meta-item">
        <i class="bi bi-calendar-event"></i>
        <?php echo $tournament['start_date']; ?> → <?php echo $tournament['end_date']; ?>
      </div>
      <div class="meta-item">
        <i class="bi bi-clock"></i>
        <?php echo substr($tournament['tournament_time'],0,5); ?> – <?php echo substr($tournament['end_time'],0,5); ?>
      </div>
      <div class="meta-item">
        <i class="bi bi-people-fill"></i>
        Max <?php echo $tournament['max_participation']; ?> teams,
        <?php echo $tournament['max_players_per_team']; ?> players/team
      </div>
    </div>
    <div class="prize-row">
      <?php if ($hasFee): ?>
        <span class="prize-chip prize-entry">
          <i class="bi bi-ticket-perforated"></i> ₹<?php echo number_format($entryFee,2); ?> entry fee
        </span>
      <?php else: ?>
        <span class="prize-chip prize-entry">Free Entry</span>
      <?php endif; ?>
      <?php if ($tournament['winner_prize']): ?>
        <span class="prize-chip prize-gold">🥇 ₹<?php echo number_format($tournament['winner_prize'],2); ?></span>
      <?php endif; ?>
      <?php if ($tournament['runnerup_prize']): ?>
        <span class="prize-chip prize-silver">🥈 ₹<?php echo number_format($tournament['runnerup_prize'],2); ?></span>
      <?php endif; ?>
    </div>
    <?php if ($tournament['terms_conditions']): ?>
    <div style="margin-top:12px;padding:10px 14px;border-radius:12px;
      background:rgba(2,6,23,.7);font-size:.82rem;color:#94a3b8;
      border:1px solid rgba(149,38,243,.18)">
      <strong style="color:#d9b6ff">Terms & Conditions:</strong><br>
      <?php echo nl2br(htmlspecialchars($tournament['terms_conditions'])); ?>
    </div>
    <?php endif; ?>
  </section>

  <section class="form-card">

    <?php if ($successMessage): ?>
      <div class="success-wrap">
        <i class="bi bi-check-circle-fill icon"></i>
        <h3 style="color:#fff"><?php echo htmlspecialchars($successMessage); ?></h3>
        <p style="color:var(--muted)">You're all set! Check your registrations tab to track status.</p>
        <a href="tournaments_list.php" class="btn btn-primary px-4 mt-2">
          <i class="bi bi-arrow-left me-2"></i>Back to Tournaments
        </a>
      </div>

    <?php elseif ($alreadyRegistered): ?>
      <div class="success-wrap">
        <i class="bi bi-info-circle-fill icon" style="color:#a855f7"></i>
        <h3 style="color:#fff">Already Registered</h3>
        <p style="color:var(--muted)">You've already registered your team for this tournament.</p>
        <a href="tournaments_list.php" class="btn btn-primary px-4 mt-2">
          <i class="bi bi-arrow-left me-2"></i>Back to Tournaments
        </a>
      </div>

    <?php elseif ($isFull): ?>
      <div class="success-wrap">
        <i class="bi bi-x-circle-fill icon" style="color:#f87171"></i>
        <h3 style="color:#fff">Tournament Full</h3>
        <p style="color:var(--muted)">All slots have been taken. Try another tournament.</p>
        <a href="tournaments_list.php" class="btn btn-primary px-4 mt-2">Browse Tournaments</a>
      </div>

    <?php else: ?>

    <?php if (!empty($errors['db'])): ?>
      <div class="alert alert-danger"><?php echo $errors['db']; ?></div>
    <?php endif; ?>

    <!-- PAYMENT BOX (shown only if entry fee > 0) -->
    <?php if ($hasFee): ?>
<?php $alreadyPaid = !empty($_POST['razorpay_payment_id']); ?>
<div class="pay-box">
  <h5><i class="bi bi-lock-fill me-2"></i>Entry Fee Payment Required</h5>
  <div class="pay-amount">₹<?php echo number_format($entryFee,2); ?> <small>per team</small></div>
  <p style="color:var(--muted);font-size:.85rem;margin:6px 0 0">
    Pay securely via Razorpay before submitting your registration.
  </p>
  <?php if (!empty($errors['payment'])): ?>
    <div class="text-danger mt-2"><i class="bi bi-exclamation-circle me-1"></i><?php echo $errors['payment']; ?></div>
  <?php endif; ?>

  <div id="paySuccessBox" <?php echo $alreadyPaid ? '' : 'style="display:none"'; ?> class="pay-success">
    <i class="bi bi-check-circle-fill"></i>
    <span>Payment successful! Now fill the form and submit.</span>
  </div>
  <button type="button" class="btn-pay" id="payBtn" 
    <?php echo $alreadyPaid ? 'style="display:none"' : ''; ?>
    onclick="startPayment()">
    <i class="bi bi-credit-card-fill me-2"></i>Pay ₹<?php echo number_format($entryFee,2); ?>
  </button>
</div>
<?php endif; ?>

    <form method="post" enctype="multipart/form-data" novalidate id="regForm">
      <input type="hidden" name="tournament_id" value="<?php echo $tournament_id; ?>">
      <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id" 
  value="<?php echo htmlspecialchars($_POST['razorpay_payment_id'] ?? ''); ?>">

      <!-- ── PERSONAL DETAILS ── -->
      <div class="section-block">
        <h2 class="section-title"><i class="bi bi-person-fill me-2"></i>Your Details</h2>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="full_name"
              value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
              class="form-control <?php echo isset($errors['full_name'])?'is-invalid':''; ?>">
            <div class="invalid-feedback"><?php echo $errors['full_name'] ?? ''; ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Mobile <span class="text-danger">*</span></label>
            <input type="tel" name="mobile_number" maxlength="10"
              value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>"
              class="form-control <?php echo isset($errors['mobile_number'])?'is-invalid':''; ?>">
            <div class="invalid-feedback"><?php echo $errors['mobile_number'] ?? ''; ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email <span class="text-danger">*</span></label>
            <input type="email" name="email"
              value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
              class="form-control <?php echo isset($errors['email'])?'is-invalid':''; ?>">
            <div class="invalid-feedback"><?php echo $errors['email'] ?? ''; ?></div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Age <span class="text-danger">*</span></label>
            <input type="number" name="age" min="10" max="70"
              value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>"
              class="form-control <?php echo isset($errors['age'])?'is-invalid':''; ?>">
            <div class="invalid-feedback"><?php echo $errors['age'] ?? ''; ?></div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Profile Photo</label>
            <input type="file" name="profile_photo" accept="image/*" class="form-control">
          </div>
        </div>
      </div>

      <!-- ── TEAM DETAILS ── -->
      <div class="section-block">
        <h2 class="section-title"><i class="bi bi-people-fill me-2"></i>Team Details</h2>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Team Name <span class="text-danger">*</span></label>
            <input type="text" name="team_name"
              value="<?php echo htmlspecialchars($_POST['team_name'] ?? ''); ?>"
              class="form-control <?php echo isset($errors['team_name'])?'is-invalid':''; ?>">
            <div class="invalid-feedback"><?php echo $errors['team_name'] ?? ''; ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">
              Team Size <span class="text-danger">*</span>
              <small style="color:var(--muted);font-weight:400">
                (max <?php echo $tournament['max_players_per_team']; ?>)
              </small>
            </label>
            <input type="number" name="team_size"
              min="1" max="<?php echo $tournament['max_players_per_team']; ?>"
              value="<?php echo htmlspecialchars($tournament['max_players_per_team'] ?? ''); ?>"
              class="form-control <?php echo isset($errors['team_size'])?'is-invalid':''; ?>">
            <div class="invalid-feedback"><?php echo $errors['team_size'] ?? ''; ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Captain Name <span class="text-danger">*</span></label>
            <input type="text" name="captain_name"
              value="<?php echo htmlspecialchars($_POST['captain_name'] ?? ''); ?>"
              class="form-control <?php echo isset($errors['captain_name'])?'is-invalid':''; ?>">
            <div class="invalid-feedback"><?php echo $errors['captain_name'] ?? ''; ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Captain Mobile <span class="text-danger">*</span></label>
            <input type="tel" name="captain_mobile" maxlength="10"
              value="<?php echo htmlspecialchars($_POST['captain_mobile'] ?? ''); ?>"
              class="form-control <?php echo isset($errors['captain_mobile'])?'is-invalid':''; ?>">
            <div class="invalid-feedback"><?php echo $errors['captain_mobile'] ?? ''; ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Captain Email <span class="text-danger">*</span></label>
            <input type="email" name="captain_email"
              value="<?php echo htmlspecialchars($_POST['captain_email'] ?? ''); ?>"
              class="form-control <?php echo isset($errors['captain_email'])?'is-invalid':''; ?>">
            <div class="invalid-feedback"><?php echo $errors['captain_email'] ?? ''; ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Emergency Contact <span class="text-danger">*</span></label>
            <input type="tel" name="emergency_contact" maxlength="10"
              value="<?php echo htmlspecialchars($_POST['emergency_contact'] ?? ''); ?>"
              class="form-control <?php echo isset($errors['emergency_contact'])?'is-invalid':''; ?>">
            <div class="invalid-feedback"><?php echo $errors['emergency_contact'] ?? ''; ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Team Logo</label>
            <input type="file" name="team_logo" accept="image/*" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label">Player List <small style="color:var(--muted);font-weight:400">(one per line)</small></label>
            <textarea name="player_list" class="form-control"
              placeholder="Player 1&#10;Player 2&#10;..."><?php echo htmlspecialchars($_POST['player_list'] ?? ''); ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Team Notes <small style="color:var(--muted);font-weight:400">(optional)</small></label>
            <textarea name="team_notes" class="form-control"
              placeholder="Any additional info about your team..."><?php echo htmlspecialchars($_POST['team_notes'] ?? ''); ?></textarea>
          </div>
        </div>
      </div>

      <!-- ── CONFIRMATION ── -->
      <div class="section-block">
        <h2 class="section-title"><i class="bi bi-check2-circle me-2"></i>Confirmation</h2>
        <label class="checkbox-line">
          <input type="checkbox" name="accept_terms" value="1"
            <?php echo isset($_POST['accept_terms']) ? 'checked' : ''; ?>>
          <span>
            I agree to the tournament terms &amp; conditions and confirm all details are accurate.
            <span class="text-danger">*</span>
          </span>
        </label>
        <?php if (isset($errors['accept_terms'])): ?>
          <div class="text-danger mt-2 ms-1"><?php echo $errors['accept_terms']; ?></div>
        <?php endif; ?>
      </div>

      <!-- ── SUBMIT ── -->
      <div class="section-block">
        <?php if ($hasFee): ?>
          <button type="submit" class="btn btn-primary w-100" id="submitBtn" 
  <?php echo (!empty($_POST['razorpay_payment_id'])) ? '' : 'disabled'; ?>>
            <i class="bi bi-send-fill me-2"></i>Complete Registration
          </button>
          <p class="text-center mt-2" style="font-size:.8rem;color:var(--muted)">
            Pay above first to enable registration
          </p>
        <?php else: ?>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-send-fill me-2"></i>Submit Registration
          </button>
        <?php endif; ?>
      </div>

    </form>
    <?php endif; ?>
  </section>
</div>

<?php if ($hasFee && !$alreadyRegistered && !$isFull): ?>
<!-- Razorpay SDK -->
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
      body: JSON.stringify({amount: <?php echo $entryFee; ?>})
    });
    const order = await res.json();

    if (order.error) {
      alert('Payment error: ' + order.error);
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-credit-card-fill me-2"></i>Pay ₹<?php echo number_format($entryFee,2); ?>';
      return;
    }

    const options = {
      key: "<?php echo RAZORPAY_KEY_ID; ?>",  // if you expose key from create_order
      amount: order.amount,
      currency: order.currency,
      name: 'SportSync',
      description: '<?php echo addslashes($tournament['tournament_name']); ?> — Entry Fee',
      order_id: order.id,
      handler: function(response) {
        // Payment success — store payment id and enable submit
        document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
        document.getElementById('paySuccessBox').style.display = 'flex';
        document.getElementById('payBtn').style.display = 'none';
        document.getElementById('submitBtn').disabled = false;
        document.getElementById('submitBtn').innerHTML =
          '<i class="bi bi-send-fill me-2"></i>Complete Registration (Paid)';
      },
      modal: {
        ondismiss: function() {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-credit-card-fill me-2"></i>Pay ₹<?php echo number_format($entryFee,2); ?>';
        }
      },
      prefill: {
        name:  '<?php echo addslashes($_SESSION['name'] ?? ''); ?>',
        email: '<?php echo addslashes($_SESSION['email'] ?? ''); ?>'
      },
      theme: {color: '#9526F3'}
    };

    const rzp = new Razorpay(options);
    rzp.open();

  } catch(e) {
    alert('Could not initiate payment. Please try again.');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-credit-card-fill me-2"></i>Pay ₹<?php echo number_format($entryFee,2); ?>';
  }
}
</script>
<?php endif; ?>

<script>
// Numeric only for tel/number inputs
document.querySelectorAll("input[type='tel'],input[type='number']").forEach(i => {
  i.addEventListener('input', function(){
    this.value = this.value.replace(/[^0-9]/g,'');
  });
});
</script>
</body>
</html>