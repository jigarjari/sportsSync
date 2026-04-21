<?php
session_start();
include("../db.php");

// Single user table — role stored in session as 'User', 'Vendor', or 'admin'
$host_id    = $_SESSION['user_id'] ?? 0;
$sessionRole = $_SESSION['role']   ?? '';

if ($host_id <= 0 || $sessionRole === '') {
    header("Location: ../login.php");
    exit;
}

// Map session role → created_by enum value used in DB
if (strtolower($sessionRole) === 'vendor') {
    $created_by = 'VENDOR';
} elseif (strtolower($sessionRole) === 'user') {
    $created_by = 'USER';
} else {
    // Admin or unrecognised — block access to hosting form
    header("Location: ../index.php");
    exit;
}

$turfs  = [];
$sports = [];
$courts = [];

$successMessage = "";
$errorMessage   = "";
$errors         = [];

$formData = [
    'turf_id'             => '',
    'sport_id'            => '',
    'tournament_name'     => '',
    'max_participation'   => '',
    'max_players_per_team'=> '',
    'start_date'          => '',
    'end_date'            => '',
    'tournament_time'     => '',
    'end_time'            => '',
    'winner_prize'        => '',
    'runnerup_prize'      => '',
    'entry_fee'           => '',
    'terms_conditions'    => '',
    'is_external_turf'    => '0',
    'external_turf_name'  => '',
    'external_location'   => '',
];

$selectedCourts  = [];
$selectedRewards = [];

/* ─── FETCH DATA ──────────────────────────────────────────────── */

// Vendors: only their own turfs. Users: all listed turfs.
if ($created_by === 'VENDOR') {
    // turftb.owner_id references user.id directly (same table, role=Vendor)
    $stmt = mysqli_prepare($conn,
        "SELECT turf_id, turf_name, location FROM turftb WHERE owner_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $host_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $turfs[] = $row;
} else {
    // Regular users can pick any active/listed turf
    $res = mysqli_query($conn,
        "SELECT turf_id, turf_name, location FROM turftb WHERE status = 'active'");
    while ($row = mysqli_fetch_assoc($res)) $turfs[] = $row;
}

$res = mysqli_query($conn, "SELECT sport_id, sport_name FROM sportstb");
while ($row = mysqli_fetch_assoc($res)) $sports[] = $row;

// Courts: vendor → their own, user → from chosen turf (loaded via JS after turf select)
if ($created_by === 'VENDOR') {
    $stmt = mysqli_prepare($conn,
        "SELECT tc.court_id, tc.turf_id, tc.sport_id, tc.court_name, tc.status
         FROM turf_courtstb tc
         JOIN turftb t ON t.turf_id = tc.turf_id
         WHERE t.owner_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $host_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $courts[] = $row;
} else {
    // Load all courts; JS filters by selected turf + sport
    $res = mysqli_query($conn,
        "SELECT court_id, turf_id, sport_id, court_name, status FROM turf_courtstb");
    while ($row = mysqli_fetch_assoc($res)) $courts[] = $row;
}

// Available reward/trophy types — keep in sync with your ENUM
$allRewardTypes = [
    'MAN_OF_MATCH'       => 'Man of the Match',
    'MAN_OF_TOURNAMENT'  => 'Man of the Tournament',
    'BEST_BATSMAN'       => 'Best Batsman',
    'BEST_BOWLER'        => 'Best Bowler',
    'BEST_FIELDER'       => 'Best Fielder',
    'BEST_GOALKEEPER'    => 'Best Goalkeeper',
    'BEST_PLAYER'        => 'Best Player',
    'FAIR_PLAY'          => 'Fair Play Award',
];

/* ─── FORM SUBMIT ─────────────────────────────────────────────── */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sanitise scalar fields
    foreach ($formData as $key => $val) {
        $formData[$key] = trim($_POST[$key] ?? '');
    }

    $isExternal      = ($formData['is_external_turf'] === '1');
    $selectedCourts  = isset($_POST['court_ids'])
                       ? array_map('intval', $_POST['court_ids']) : [];
    $selectedRewards = isset($_POST['reward_types'])
                       ? array_intersect($_POST['reward_types'], array_keys($allRewardTypes))
                       : [];

    /* ── VALIDATION ──────────────────────────────────────────── */

    // Required base fields
    $requiredBase = ['tournament_name', 'sport_id', 'max_participation',
                     'max_players_per_team', 'start_date', 'end_date',
                     'tournament_time', 'end_time', 'entry_fee'];
    foreach ($requiredBase as $key) {
        if ($formData[$key] === '') {
            $errors[$key] = "This field is required";
        }
    }

    // Turf: either listed turf_id OR external details
    if ($isExternal) {
        if (trim($formData['external_turf_name']) === '') {
            $errors['external_turf_name'] = "Turf name is required";
        }
        if (trim($formData['external_location']) === '') {
            $errors['external_location'] = "Location is required";
        }
        $formData['turf_id'] = null;
    } else {
        if ($formData['turf_id'] === '') {
            $errors['turf_id'] = "Please select a turf";
        }
    }

    // Tournament name
    if (!isset($errors['tournament_name']) &&
        !preg_match("/^[a-zA-Z0-9 ]{3,100}$/", $formData['tournament_name'])) {
        $errors['tournament_name'] = "Only letters & numbers (3–100 chars)";
    }

    // Participation
    if (!isset($errors['max_participation'])) {
        $maxP = (int)$formData['max_participation'];
        if ($maxP < 2 || $maxP > 1000) {
            $errors['max_participation'] = "Must be between 2–1000";
        }
    }

    // Players per team
    if (!isset($errors['max_players_per_team'])) {
        $maxT = (int)$formData['max_players_per_team'];
        if ($maxT < 1 || $maxT > 50) {
            $errors['max_players_per_team'] = "Must be between 1–50";
        }
    }

    // Dates
    if (!isset($errors['start_date']) && !isset($errors['end_date'])) {
        $start = strtotime($formData['start_date']);
        $end   = strtotime($formData['end_date']);
        $today = strtotime(date("Y-m-d"));

        if ($start < $today) {
            $errors['start_date'] = "Cannot be a past date";
        }
        if ($end < $start) {
            $errors['end_date'] = "End date must be after start date";
        }
    }

    // Times
    $timeRegex = "/^(?:[01]\d|2[0-3]):[0-5]\d$/";
    if (!isset($errors['tournament_time']) &&
        !preg_match($timeRegex, $formData['tournament_time'])) {
        $errors['tournament_time'] = "Invalid start time";
    }
    if (!isset($errors['end_time']) &&
        !preg_match($timeRegex, $formData['end_time'])) {
        $errors['end_time'] = "Invalid end time";
    }
    if (!isset($errors['tournament_time']) && !isset($errors['end_time'])) {
        if ($formData['end_time'] <= $formData['tournament_time']) {
            $errors['end_time'] = "End time must be after start time";
        }
    }

    // Prize amounts (optional but must be numeric if provided)
    foreach (['winner_prize', 'runnerup_prize', 'entry_fee'] as $prizeKey) {
        if ($formData[$prizeKey] !== '' && !is_numeric($formData[$prizeKey])) {
            $errors[$prizeKey] = "Must be a valid amount";
        }
        if ($formData[$prizeKey] !== '' && (float)$formData[$prizeKey] < 0) {
            $errors[$prizeKey] = "Cannot be negative";
        }
    }

    // Terms length
    if (strlen($formData['terms_conditions']) > 1000) {
        $errors['terms_conditions'] = "Max 1000 characters";
    }

    // Courts required only for listed turfs
    if (!$isExternal && empty($selectedCourts)) {
        $errors['courts'] = "Select at least one court";
    }

    /* ── INSERT ──────────────────────────────────────────────── */

    if (empty($errors)) {

        $turfId     = $isExternal ? null : (int)$formData['turf_id'];
        $sportId    = (int)$formData['sport_id'];
        $maxP       = (int)$formData['max_participation'];
        $maxT       = (int)$formData['max_players_per_team'];
        $isExtInt   = $isExternal ? 1 : 0;
        $winPrize   = $formData['winner_prize']   !== '' ? (float)$formData['winner_prize']   : null;
        $runPrize   = $formData['runnerup_prize']  !== '' ? (float)$formData['runnerup_prize'] : null;
        $entryFee   = $formData['entry_fee']       !== '' ? (float)$formData['entry_fee']       : null;
        $extName    = $isExternal ? $formData['external_turf_name'] : null;
        $extLoc     = $isExternal ? $formData['external_location']  : null;
        $endTime    = $formData['end_time'];
        $statusInit = 'P'; // Always pending until admin approves

        // Vendor ownership check for listed turf
        if (!$isExternal && $created_by === 'VENDOR') {
            $check = mysqli_prepare($conn,
                "SELECT turf_id FROM turftb WHERE turf_id=? AND owner_id=?");
            mysqli_stmt_bind_param($check, "ii", $turfId, $host_id);
            mysqli_stmt_execute($check);
            $res = mysqli_stmt_get_result($check);
            if (mysqli_num_rows($res) === 0) {
                $errorMessage = "Invalid turf selected.";
                goto end_of_insert;
            }
        }

        // Validate courts belong to selected turf+sport and are active
        if (!$isExternal && !empty($selectedCourts)) {
            $validCourtIds = array_column(
                array_filter($courts, fn($c) =>
                    $c['turf_id'] == $turfId &&
                    $c['sport_id'] == $sportId &&
                    $c['status'] == 'A'
                ),
                'court_id'
            );
            foreach ($selectedCourts as $cid) {
                if (!in_array($cid, $validCourtIds)) {
                    $errorMessage = "Invalid court selected.";
                    goto end_of_insert;
                }
            }
        }

        mysqli_begin_transaction($conn);
        try {

            $insertTournament = mysqli_prepare($conn,
                "INSERT INTO tournamenttb
                   (tournament_name, turf_id, sport_id, max_participation,
                    max_players_per_team, winner_prize, runnerup_prize, entry_fee,
                    is_external_turf, external_turf_name, external_location,
                    start_date, end_date, tournament_time, end_time,
                    terms_conditions, vendor_id, status, created_by, host_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $insertTournament,
                "siiiidddisssssssissi",
                $formData['tournament_name'],
                $turfId,
                $sportId,
                $maxP,
                $maxT,
                $winPrize,
                $runPrize,
                $entryFee,
                $isExtInt,
                $extName,
                $extLoc,
                $formData['start_date'],
                $formData['end_date'],
                $formData['tournament_time'],
                $endTime,
                $formData['terms_conditions'],
                $host_id,   // vendor_id column (host's id regardless of role)
                $statusInit,
                $created_by,
                $host_id
            );

            mysqli_stmt_execute($insertTournament);
            $tournamentId = mysqli_insert_id($conn);

            // Insert courts (listed turf only)
            if (!$isExternal && !empty($selectedCourts)) {
                $courtStmt = mysqli_prepare($conn,
                    "INSERT INTO tournament_courtstb (tournament_id, court_id) VALUES (?, ?)");
                foreach ($selectedCourts as $cid) {
                    mysqli_stmt_bind_param($courtStmt, "ii", $tournamentId, $cid);
                    mysqli_stmt_execute($courtStmt);
                }
            }

            // Insert rewards/trophies
            if (!empty($selectedRewards)) {
                $rewardStmt = mysqli_prepare($conn,
                    "INSERT INTO tournament_rewards (tournament_id, reward_type) VALUES (?, ?)");
                foreach ($selectedRewards as $rtype) {
                    mysqli_stmt_bind_param($rewardStmt, "is", $tournamentId, $rtype);
                    mysqli_stmt_execute($rewardStmt);
                }
            }

            mysqli_commit($conn);
            $successMessage = "Tournament submitted! It will be visible once an admin approves it.";

            // Reset form
            foreach ($formData as $k => $v) $formData[$k] = '';
            $formData['is_external_turf'] = '0';
            $selectedCourts  = [];
            $selectedRewards = [];

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errorMessage = "Something went wrong. Please try again.";
        }

        end_of_insert:;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Host a Tournament</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
  --bg-main: #0e0f11;
  --card-bg: rgba(16,16,20,0.92);
  --card-border: rgba(149,38,243,0.34);
  --accent: #9526F3;
  --accent-soft: rgba(149,38,243,0.18);
  --text-main: #f8f9fb;
  --text-muted: #9ca3af;
}
*,*::before,*::after{box-sizing:border-box}
body{
  margin:0;min-height:100vh;background-color:var(--bg-main);
  background-image:linear-gradient(45deg,#1f1f1f 25%,transparent 25%),
    linear-gradient(-45deg,#1f1f1f 25%,transparent 25%),
    linear-gradient(45deg,transparent 75%,#1f1f1f 75%),
    linear-gradient(-45deg,transparent 75%,#1f1f1f 75%);
  background-size:6px 6px;background-position:0 0,0 3px,3px -3px,-3px 0;
  color:var(--text-main);font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif;
}
.page-shell{width:min(1100px,calc(100% - 2rem));margin:32px auto}
.hero-card,.form-card{
  background:var(--card-bg);border:1px solid var(--card-border);
  border-radius:28px;box-shadow:0 24px 60px rgba(0,0,0,0.35);
}
.hero-card{padding:28px;margin-bottom:22px;overflow:hidden}
.eyebrow{
  display:inline-flex;align-items:center;gap:.5rem;padding:8px 14px;
  border-radius:999px;background:var(--accent-soft);color:#d9b6ff;
  font-size:.9rem;margin-bottom:12px;
}
.hero-card h1{margin:0 0 8px;font-size:clamp(1.9rem,4vw,2.8rem);color:#fff}
.hero-card p{margin:0;max-width:720px;color:var(--text-muted);line-height:1.6}
.form-card{padding:28px}
.section-title{margin:0 0 18px;font-size:1.1rem;color:var(--accent);font-weight:700}
.section-block+.section-block{
  margin-top:28px;padding-top:24px;
  border-top:1px solid rgba(255,255,255,0.06);
}
.form-label{color:#f5f5f5;font-weight:600;margin-bottom:8px}
.form-control,.form-select,
.form-control:focus,.form-select:focus{
  background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);
  color:#fff;min-height:48px;border-radius:16px;box-shadow:none;
}
.form-control::placeholder{color:#8f96a3}
.form-select option{color:#111827}
.stat-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:18px}
.stat-chip{
  background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.07);
  border-radius:18px;padding:16px;
}
.stat-chip small{display:block;color:var(--text-muted);margin-bottom:6px}
.stat-chip strong{color:#fff;font-size:1.1rem}
.btn-primary{
  background:linear-gradient(135deg,#9526F3,#6d11bf);border:none;
  min-height:52px;border-radius:18px;font-weight:700;
}
.btn-primary:hover,.btn-primary:focus{background:linear-gradient(135deg,#a240ff,#7516cd)}
.btn-primary:disabled{opacity:.5;cursor:not-allowed}
.empty-note{
  padding:14px 16px;border-radius:16px;
  background:rgba(255,193,7,0.08);border:1px solid rgba(255,193,7,0.18);color:#ffe08a;
}
.pending-note{
  padding:14px 16px;border-radius:16px;
  background:rgba(149,38,243,0.08);border:1px solid rgba(149,38,243,0.25);color:#d9b6ff;
}
.court-panel{
  min-height:120px;padding:16px;border-radius:18px;
  border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);
}
.court-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.court-option{
  display:flex;align-items:center;gap:10px;padding:12px 14px;
  border-radius:14px;border:1px solid rgba(255,255,255,0.07);
  background:rgba(255,255,255,0.04);cursor:pointer;
}
.court-option input[type="checkbox"]{accent-color:var(--accent);transform:scale(1.15)}
.court-empty{color:var(--text-muted);margin:0}

/* Trophy / reward checkboxes */
.reward-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}
.reward-option{
  display:flex;align-items:center;gap:10px;padding:12px 16px;
  border-radius:14px;border:1px solid rgba(255,255,255,0.07);
  background:rgba(255,255,255,0.04);cursor:pointer;transition:border-color .2s;
}
.reward-option:has(input:checked){
  border-color:var(--accent);background:var(--accent-soft);
}
.reward-option input[type="checkbox"]{accent-color:var(--accent);transform:scale(1.15)}
.reward-option .ri{font-size:1.2rem;color:#d9b6ff}

/* Toggle switch for external turf */
.toggle-wrap{display:flex;align-items:center;gap:12px}
.form-check-input.switch{
  width:48px;height:26px;border-radius:13px;cursor:pointer;
  background-color:rgba(255,255,255,0.15);border:none;
  background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e");
}
.form-check-input.switch:checked{background-color:var(--accent)}
.form-check-input.switch:focus{box-shadow:none}

.prize-prefix{
  background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.1);
  border-right:none;color:var(--text-muted);border-radius:16px 0 0 16px;
  padding:0 14px;display:flex;align-items:center;min-height:48px;
}
.prize-input{border-radius:0 16px 16px 0!important}

@media(max-width:767px){
  .page-shell{width:min(100% - 1rem,100%);margin:16px auto}
  .hero-card,.form-card{padding:18px;border-radius:22px}
  .stat-grid,.court-grid,.reward-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>
<div class="page-shell">

  <!-- ── HERO ── -->
  <section class="hero-card">
    <div class="eyebrow">
      <i class="bi bi-trophy-fill"></i>
      Tournament Setup
    </div>
    <h1>Host a new tournament</h1>
    <p>Fill in the details below. Your tournament will go live once an admin approves it.
       <?php if ($created_by === 'USER'): ?>
       You can host at any listed turf or enter an unlisted / external venue.
       <?php else: ?>
       You can host at one of your own turfs.
       <?php endif; ?>
    </p>
    <div class="stat-grid">
      <div class="stat-chip">
        <small>Available turfs</small>
        <strong><?php echo count($turfs); ?></strong>
      </div>
      <div class="stat-chip">
        <small>Sports loaded</small>
        <strong><?php echo count($sports); ?></strong>
      </div>
      <div class="stat-chip">
        <small>Active courts</small>
        <strong><?php echo count(array_filter($courts, fn($c) => $c['status'] === 'A')); ?></strong>
      </div>
    </div>
  </section>

  <!-- ── FORM CARD ── -->
  <section class="form-card">

    <?php if ($successMessage !== ""): ?>
      <div class="alert alert-success mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php echo htmlspecialchars($successMessage); ?>
      </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ""): ?>
      <div class="alert alert-danger mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo htmlspecialchars($errorMessage); ?>
      </div>
    <?php endif; ?>

    <div class="pending-note mb-4">
      <i class="bi bi-info-circle-fill me-2"></i>
      Tournaments require <strong>admin approval</strong> before they are publicly visible and
      bookings are created.
    </div>

    <form method="post" novalidate>

      <!-- ── 1. VENUE ── -->
      <div class="section-block">
        <h2 class="section-title"><i class="bi bi-geo-alt-fill me-2"></i>Venue</h2>

        <?php if ($created_by === 'USER'): ?>
        <!-- External turf toggle (only for users) -->
        <div class="mb-3 toggle-wrap">
          <div class="form-check form-switch ps-0">
            <input class="form-check-input switch" type="checkbox" id="externalToggle"
                   name="is_external_turf" value="1"
                   <?php if($formData['is_external_turf']==='1') echo 'checked'; ?>
                   onchange="toggleExternalTurf(this.checked)">
          </div>
          <label for="externalToggle" class="form-label mb-0">
            This tournament is at an <strong>external / unlisted</strong> venue
          </label>
        </div>
        <?php else: ?>
          <input type="hidden" name="is_external_turf" value="0">
        <?php endif; ?>

        <!-- Listed turf block -->
        <div id="listedTurfBlock">
          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label">Select Turf <span class="text-danger">*</span></label>
              <select name="turf_id" id="turf_id"
                class="form-select <?php echo isset($errors['turf_id']) ? 'is-invalid' : ''; ?>">
                <option value="">Choose turf</option>
                <?php foreach ($turfs as $turf): ?>
                  <option value="<?php echo $turf['turf_id']; ?>"
                    <?php if($formData['turf_id']==$turf['turf_id']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($turf['turf_name'].' — '.$turf['location']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback"><?php echo $errors['turf_id'] ?? ''; ?></div>
            </div>
          </div>
        </div>

        <!-- External turf block -->
        <div id="externalTurfBlock" style="display:none">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Venue / Turf Name <span class="text-danger">*</span></label>
              <input type="text" name="external_turf_name"
                value="<?php echo htmlspecialchars($formData['external_turf_name']); ?>"
                placeholder="e.g. City Sports Ground"
                class="form-control <?php echo isset($errors['external_turf_name']) ? 'is-invalid' : ''; ?>">
              <div class="invalid-feedback"><?php echo $errors['external_turf_name'] ?? ''; ?></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Location / Address <span class="text-danger">*</span></label>
              <input type="text" name="external_location"
                value="<?php echo htmlspecialchars($formData['external_location']); ?>"
                placeholder="e.g. Ring Road, Surat"
                class="form-control <?php echo isset($errors['external_location']) ? 'is-invalid' : ''; ?>">
              <div class="invalid-feedback"><?php echo $errors['external_location'] ?? ''; ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── 2. TOURNAMENT DETAILS ── -->
      <div class="section-block">
        <h2 class="section-title"><i class="bi bi-clipboard-data-fill me-2"></i>Tournament Details</h2>
        <div class="row g-3">

          <!-- Sport -->
          <div class="col-md-6">
            <label class="form-label">Sport <span class="text-danger">*</span></label>
            <select name="sport_id" id="sport_id"
              class="form-select <?php echo isset($errors['sport_id']) ? 'is-invalid' : ''; ?>">
              <option value="">Choose sport</option>
              <?php foreach ($sports as $sport): ?>
                <option value="<?php echo $sport['sport_id']; ?>"
                  <?php if($formData['sport_id']==$sport['sport_id']) echo 'selected'; ?>>
                  <?php echo htmlspecialchars($sport['sport_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?php echo $errors['sport_id'] ?? ''; ?></div>
          </div>

          <!-- Name -->
          <div class="col-md-6">
            <label class="form-label">Tournament Name <span class="text-danger">*</span></label>
            <input type="text" name="tournament_name"
              value="<?php echo htmlspecialchars($formData['tournament_name']); ?>"
              placeholder="e.g. Surat Premier League"
              class="form-control <?php echo isset($errors['tournament_name']) ? 'is-invalid' : ''; ?>">
            <div class="invalid-feedback"><?php echo $errors['tournament_name'] ?? ''; ?></div>
          </div>

          <!-- Max teams -->
          <div class="col-md-6">
            <label class="form-label">Max Teams (Participants) <span class="text-danger">*</span></label>
            <input type="number" name="max_participation" min="2" max="1000"
              value="<?php echo htmlspecialchars($formData['max_participation']); ?>"
              placeholder="e.g. 16"
              class="form-control <?php echo isset($errors['max_participation']) ? 'is-invalid' : ''; ?>">
            <div class="invalid-feedback"><?php echo $errors['max_participation'] ?? ''; ?></div>
          </div>

          <!-- Max players per team -->
          <div class="col-md-6">
            <label class="form-label">Max Players per Team <span class="text-danger">*</span></label>
            <input type="number" name="max_players_per_team" min="1" max="50"
              value="<?php echo htmlspecialchars($formData['max_players_per_team']); ?>"
              placeholder="e.g. 11"
              class="form-control <?php echo isset($errors['max_players_per_team']) ? 'is-invalid' : ''; ?>">
            <div class="invalid-feedback"><?php echo $errors['max_players_per_team'] ?? ''; ?></div>
          </div>

          <!-- Start date -->
          <div class="col-md-3">
            <label class="form-label">Start Date <span class="text-danger">*</span></label>
            <input type="date" name="start_date"
              value="<?php echo htmlspecialchars($formData['start_date']); ?>"
              min="<?php echo date('Y-m-d'); ?>"
              class="form-control <?php echo isset($errors['start_date']) ? 'is-invalid' : ''; ?>">
            <div class="invalid-feedback"><?php echo $errors['start_date'] ?? ''; ?></div>
          </div>

          <!-- End date -->
          <div class="col-md-3">
            <label class="form-label">End Date <span class="text-danger">*</span></label>
            <input type="date" name="end_date"
              value="<?php echo htmlspecialchars($formData['end_date']); ?>"
              min="<?php echo date('Y-m-d'); ?>"
              class="form-control <?php echo isset($errors['end_date']) ? 'is-invalid' : ''; ?>">
            <div class="invalid-feedback"><?php echo $errors['end_date'] ?? ''; ?></div>
          </div>

          <!-- Start time -->
          <div class="col-md-3">
            <label class="form-label">Start Time <span class="text-danger">*</span></label>
            <input type="time" name="tournament_time"
              value="<?php echo htmlspecialchars($formData['tournament_time']); ?>"
              class="form-control <?php echo isset($errors['tournament_time']) ? 'is-invalid' : ''; ?>">
            <div class="invalid-feedback"><?php echo $errors['tournament_time'] ?? ''; ?></div>
          </div>

          <!-- End time -->
          <div class="col-md-3">
            <label class="form-label">End Time <span class="text-danger">*</span></label>
            <input type="time" name="end_time"
              value="<?php echo htmlspecialchars($formData['end_time']); ?>"
              class="form-control <?php echo isset($errors['end_time']) ? 'is-invalid' : ''; ?>">
            <div class="invalid-feedback"><?php echo $errors['end_time'] ?? ''; ?></div>
            <div class="form-text text-muted" style="font-size:.78rem">
              Slots between start &amp; end time will be blocked daily from start date to end date.
            </div>
          </div>

        </div>
      </div>

      <!-- ── 3. ENTRY FEE + PRIZE MONEY ── -->
      <div class="section-block">
        <h2 class="section-title"><i class="bi bi-ticket-perforated-fill me-2"></i>Entry Fee &amp; Prize Money</h2>
        <div class="row g-3">

          <!-- Entry fee — required -->
          <div class="col-md-12">
            <label class="form-label">Entry Fee per Team (₹) <span class="text-danger">*</span></label>
            <div class="d-flex">
              <span class="prize-prefix"><i class="bi bi-currency-rupee"></i></span>
              <input type="number" name="entry_fee" min="0" step="0.01"
                value="<?php echo htmlspecialchars($formData['entry_fee']); ?>"
                placeholder="0.00 — enter 0 for a free tournament"
                class="form-control prize-input <?php echo isset($errors['entry_fee']) ? 'is-invalid' : ''; ?>">
            </div>
            <div class="invalid-feedback d-block"><?php echo $errors['entry_fee'] ?? ''; ?></div>
            <div class="form-text text-muted" style="font-size:.78rem">
              Set to <strong>0</strong> for a free / open tournament.
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Winner Prize (₹) <span class="text-muted fw-normal">(optional)</span></label>
            <div class="d-flex">
              <span class="prize-prefix"><i class="bi bi-currency-rupee"></i></span>
              <input type="number" name="winner_prize" min="0" step="0.01"
                value="<?php echo htmlspecialchars($formData['winner_prize']); ?>"
                placeholder="0.00"
                class="form-control prize-input <?php echo isset($errors['winner_prize']) ? 'is-invalid' : ''; ?>">
            </div>
            <div class="invalid-feedback d-block"><?php echo $errors['winner_prize'] ?? ''; ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Runner-Up Prize (₹)</label>
            <div class="d-flex">
              <span class="prize-prefix"><i class="bi bi-currency-rupee"></i></span>
              <input type="number" name="runnerup_prize" min="0" step="0.01"
                value="<?php echo htmlspecialchars($formData['runnerup_prize']); ?>"
                placeholder="0.00"
                class="form-control prize-input <?php echo isset($errors['runnerup_prize']) ? 'is-invalid' : ''; ?>">
            </div>
            <div class="invalid-feedback d-block"><?php echo $errors['runnerup_prize'] ?? ''; ?></div>
          </div>
        </div>
      </div>

      <!-- ── 4. TROPHIES / AWARDS ── -->
      <div class="section-block">
        <h2 class="section-title"><i class="bi bi-award-fill me-2"></i>Trophies &amp; Awards <span class="text-muted fw-normal fs-6">(optional)</span></h2>
        <p style="color:var(--text-muted);font-size:.9rem;margin-bottom:14px">
          Select the individual awards that will be given at this tournament.
        </p>
        <div class="reward-grid">
          <?php foreach ($allRewardTypes as $rKey => $rLabel):
            $checked = in_array($rKey, $selectedRewards) ? 'checked' : '';
          ?>
          <label class="reward-option">
            <input type="checkbox" name="reward_types[]"
                   value="<?php echo $rKey; ?>" <?php echo $checked; ?>>
            <i class="bi bi-trophy ri"></i>
            <span><?php echo htmlspecialchars($rLabel); ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ── 5. COURT SELECTION (listed turf only) ── -->
      <div class="section-block" id="courtSection">
        <h2 class="section-title"><i class="bi bi-grid-3x3-gap-fill me-2"></i>Court Selection <span class="text-danger">*</span></h2>
        <div class="court-panel <?php echo isset($errors['courts']) ? 'border border-danger' : ''; ?>">
          <div id="courtContainer" class="court-grid"></div>
          <p id="courtEmptyState" class="court-empty mb-0">
            Select a turf and sport above to view available courts.
          </p>
          <?php if (isset($errors['courts'])): ?>
            <div class="text-danger mt-2"><?php echo $errors['courts']; ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── 6. TERMS ── -->
      <div class="section-block">
        <h2 class="section-title"><i class="bi bi-file-earmark-text-fill me-2"></i>Terms &amp; Conditions <span class="text-muted fw-normal fs-6">(optional)</span></h2>
        <textarea name="terms_conditions" rows="4"
          maxlength="1000"
          placeholder="Enter any rules, registration requirements, or conditions participants must agree to…"
          class="form-control <?php echo isset($errors['terms_conditions']) ? 'is-invalid' : ''; ?>"
          ><?php echo htmlspecialchars($formData['terms_conditions']); ?></textarea>
        <div class="invalid-feedback"><?php echo $errors['terms_conditions'] ?? ''; ?></div>
        <div class="form-text text-muted" style="font-size:.78rem">Max 1000 characters</div>
      </div>

      <!-- ── SUBMIT ── -->
      <div class="section-block">
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-send-fill me-2"></i>
          Submit Tournament for Approval
        </button>
        <p class="text-center mt-3" style="color:var(--text-muted);font-size:.85rem">
          <i class="bi bi-clock-history me-1"></i>
          Status will be <strong style="color:#d9b6ff">Pending</strong> until an admin reviews your request.
        </p>
      </div>

    </form>
  </section>
</div>

<script>
const allCourts      = <?php echo json_encode($courts, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
const selectedCourtIds = new Set(<?php echo json_encode(array_values($selectedCourts)); ?>);
const isVendor       = <?php echo ($created_by === 'VENDOR') ? 'true' : 'false'; ?>;

const turfSelect      = document.getElementById("turf_id");
const sportSelect     = document.getElementById("sport_id");
const courtContainer  = document.getElementById("courtContainer");
const courtEmptyState = document.getElementById("courtEmptyState");
const courtSection    = document.getElementById("courtSection");
const listedBlock     = document.getElementById("listedTurfBlock");
const externalBlock   = document.getElementById("externalTurfBlock");
const externalToggle  = document.getElementById("externalToggle");

/* ── External turf toggle ── */
function toggleExternalTurf(isExternal) {
  listedBlock.style.display   = isExternal ? "none" : "block";
  externalBlock.style.display = isExternal ? "block" : "none";
  courtSection.style.display  = isExternal ? "none" : "block";
  if (!isExternal) renderCourts();
}

// Apply on page load (e.g. after validation error re-render)
if (externalToggle) {
  toggleExternalTurf(externalToggle.checked);
}

/* ── Court renderer ── */
function renderCourts() {
  if (!courtContainer || !courtEmptyState || !turfSelect || !sportSelect) return;

  const turfId  = turfSelect ? turfSelect.value : "";
  const sportId = sportSelect.value;

  courtContainer.innerHTML = "";

  if (!turfId || !sportId) {
    courtEmptyState.textContent = "Select a turf and sport to view available courts.";
    courtEmptyState.style.display = "block";
    return;
  }

  const filtered = allCourts.filter(c =>
    String(c.turf_id)  === turfId  &&
    String(c.sport_id) === sportId &&
    c.status === "A"
  );

  if (filtered.length === 0) {
    courtEmptyState.textContent = "No active courts found for the selected turf and sport.";
    courtEmptyState.style.display = "block";
    return;
  }

  courtEmptyState.style.display = "none";

  filtered.forEach(court => {
    const label    = document.createElement("label");
    label.className = "court-option";

    const checkbox      = document.createElement("input");
    checkbox.type       = "checkbox";
    checkbox.name       = "court_ids[]";
    checkbox.value      = court.court_id;
    checkbox.checked    = selectedCourtIds.has(Number(court.court_id));

    const text      = document.createElement("span");
    text.textContent = court.court_name;

    label.appendChild(checkbox);
    label.appendChild(text);
    courtContainer.appendChild(label);
  });
}

if (turfSelect)  turfSelect.addEventListener("change", renderCourts);
if (sportSelect) sportSelect.addEventListener("change", renderCourts);
renderCourts();

/* ── Numeric-only inputs ── */
document.querySelectorAll("input[type='number']").forEach(inp => {
  inp.addEventListener("input", function () {
    // Allow decimals for prize fields
    if (this.name === "winner_prize" || this.name === "runnerup_prize" || this.name === "entry_fee") return;
    this.value = this.value.replace(/[^0-9]/g, "");
  });
});
</script>
</body>
</html>