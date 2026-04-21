<?php
session_start();
include('../db.php');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_role = strtolower($_SESSION['role'] ?? 'user'); // 'user' or 'vendor'

/* ══════════════════════════════════════════════
   1. BROWSE — approved tournaments (status = A)
══════════════════════════════════════════════ */
$browseSql = "
    SELECT tt.*,
           tf.turf_name, tf.location,
           c.city_name,
           GROUP_CONCAT(DISTINCT tc.court_id ORDER BY tc.court_id SEPARATOR ', ') AS courts,
           COUNT(DISTINCT tr.id) AS registered_teams
    FROM tournamenttb tt
    LEFT JOIN turftb tf       ON tf.turf_id   = tt.turf_id
    LEFT JOIN citytb c        ON c.city_id    = tf.city_id
    LEFT JOIN tournament_courtstb tc ON tc.tournament_id = tt.tournament_id
    LEFT JOIN tournament_registrations tr ON tr.tournament_id = tt.tournament_id
    WHERE tt.status = 'A'
    GROUP BY tt.tournament_id
    ORDER BY tt.start_date ASC
";
$browseRes = mysqli_query($conn, $browseSql);

/* ══════════════════════════════════════════════
   2. MY REGISTRATIONS — tournaments this user enrolled in
══════════════════════════════════════════════ */
$myRegSql = "
    SELECT tr.*,
           tt.tournament_name, tt.start_date, tt.end_date,
           tt.tournament_time, tt.end_time, tt.entry_fee,
           tt.winner_prize, tt.runnerup_prize, tt.status AS tournament_status,
           tf.turf_name, tf.location,
           c.city_name
    FROM tournament_registrations tr
    JOIN tournamenttb tt  ON tt.tournament_id = tr.tournament_id
    LEFT JOIN turftb tf   ON tf.turf_id = tt.turf_id
    LEFT JOIN citytb c    ON c.city_id  = tf.city_id
    WHERE tr.user_id = ?
    ORDER BY tr.created_at DESC
";
$myRegStmt = mysqli_prepare($conn, $myRegSql);
mysqli_stmt_bind_param($myRegStmt, "i", $user_id);
mysqli_stmt_execute($myRegStmt);
$myRegRes = mysqli_stmt_get_result($myRegStmt);

/* ══════════════════════════════════════════════
   3. MY HOSTED TOURNAMENTS — tournaments created by this user
══════════════════════════════════════════════ */
$hostedSql = "
    SELECT tt.*,
           tf.turf_name, tf.location,
           COUNT(DISTINCT tr.id) AS total_registrations
    FROM tournamenttb tt
    LEFT JOIN turftb tf ON tf.turf_id = tt.turf_id
    LEFT JOIN tournament_registrations tr ON tr.tournament_id = tt.tournament_id
    WHERE tt.host_id = ? AND tt.created_by = 'USER'
    GROUP BY tt.tournament_id
    ORDER BY tt.created_at DESC
";
$hostedStmt = mysqli_prepare($conn, $hostedSql);
mysqli_stmt_bind_param($hostedStmt, "i", $user_id);
mysqli_stmt_execute($hostedStmt);
$hostedRes = mysqli_stmt_get_result($hostedStmt);

// Status label helper
function statusBadge($s) {
    return match($s) {
        'A' => '<span class="badge-status approved">Approved</span>',
        'R' => '<span class="badge-status rejected">Rejected</span>',
        default => '<span class="badge-status pending">Pending</span>',
    };
}
function payBadge($s) {
    return match(strtolower($s ?? '')) {
        'paid'   => '<span class="badge-status approved">Paid</span>',
        'failed' => '<span class="badge-status rejected">Failed</span>',
        default  => '<span class="badge-status pending">Pending</span>',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tournaments</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
  --bg: #0e0f11;
  --card: rgba(17,24,39,0.7);
  --border: rgba(149,38,243,0.28);
  --accent: #9526F3;
  --accent-soft: rgba(149,38,243,0.15);
  --muted: #9ca3af;
}
*,*::before,*::after{box-sizing:border-box}
body {
  margin:0; min-height:100vh;
  background:#0e0f11;
  background-image:linear-gradient(#1a1a1a 1px,transparent 1px),
    linear-gradient(90deg,#1a1a1a 1px,transparent 1px);
  background-size:40px 40px;
  color:#f1f5f9;
  font-family:'Segoe UI',sans-serif;
}
body::before {
  content:"";position:fixed;width:500px;height:500px;
  background:radial-gradient(circle,#9526F3,transparent 65%);
  top:-120px;left:-120px;filter:blur(110px);opacity:.35;
  animation:glow 9s ease-in-out infinite alternate;z-index:-1;
}
@keyframes glow{to{transform:translate(120px,80px)}}

/* ── TAB NAV ── */
.tab-nav {
  display:flex;gap:8px;
  background:rgba(255,255,255,0.04);
  border:1px solid var(--border);
  border-radius:20px;padding:6px;
  margin-bottom:28px;
}
.tab-btn {
  flex:1;padding:10px 14px;border:none;border-radius:14px;
  background:transparent;color:var(--muted);font-weight:600;
  font-size:.92rem;cursor:pointer;transition:.2s;display:flex;
  align-items:center;justify-content:center;gap:8px;
}
.tab-btn.active {
  background:var(--accent);color:#fff;
  box-shadow:0 4px 18px rgba(149,38,243,.4);
}
.tab-btn:not(.active):hover{background:rgba(255,255,255,0.06);color:#fff}
.tab-count {
  background:rgba(255,255,255,.15);
  border-radius:999px;padding:2px 8px;font-size:.78rem;
}
.tab-btn.active .tab-count{background:rgba(255,255,255,.25)}

/* ── PANEL ── */
.tab-panel{display:none}.tab-panel.active{display:block}

/* ── PAGE HEADER ── */
.page-header {
  text-align:center;padding:40px 0 28px;
}
.page-header h1{font-size:clamp(1.8rem,4vw,2.6rem);font-weight:800;color:#fff;margin:0 0 8px}
.page-header p{color:var(--muted);margin:0}
.eyebrow {
  display:inline-flex;align-items:center;gap:.5rem;
  padding:6px 14px;border-radius:999px;
  background:var(--accent-soft);color:#d9b6ff;
  font-size:.88rem;margin-bottom:12px;
}

/* ── TOURNAMENT CARD ── */
.t-card {
  position:relative;border-radius:20px;padding:22px;height:100%;
  background:var(--card);backdrop-filter:blur(10px);
  border:1px solid var(--border);
  transition:transform .3s,box-shadow .3s;overflow:hidden;
}
.t-card::before {
  content:"";position:absolute;inset:0;border-radius:20px;padding:1px;
  background:linear-gradient(120deg,transparent,#9526F3,transparent);
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
  -webkit-mask-composite:xor;mask-composite:exclude;
}
.t-card:hover{transform:translateY(-6px);box-shadow:0 18px 45px rgba(149,38,243,.3)}
.t-title{font-size:1.15rem;font-weight:700;color:#fff;margin-bottom:10px}
.t-info{font-size:.85rem;color:#cbd5e1;margin-bottom:5px;display:flex;align-items:center;gap:8px}
.t-info i{color:var(--accent)}
.t-terms{
  margin-top:12px;padding:10px 14px;border-radius:12px;
  background:rgba(2,6,23,.7);font-size:.8rem;color:#94a3b8;
  border:1px solid rgba(149,38,243,.18);max-height:70px;overflow:hidden;
}
.prize-row{
  display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;
}
.prize-chip{
  padding:4px 12px;border-radius:999px;font-size:.78rem;font-weight:600;
}
.prize-gold{background:rgba(234,179,8,.15);color:#fbbf24;border:1px solid rgba(234,179,8,.25)}
.prize-silver{background:rgba(156,163,175,.12);color:#d1d5db;border:1px solid rgba(156,163,175,.2)}
.prize-entry{background:var(--accent-soft);color:#d9b6ff;border:1px solid rgba(149,38,243,.25)}
.slots-bar{
  margin-top:12px;height:6px;border-radius:3px;
  background:rgba(255,255,255,.08);overflow:hidden;
}
.slots-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,#9526F3,#a855f7)}

.btn-participate {
  margin-top:14px;width:100%;padding:10px;border-radius:30px;border:none;
  background:linear-gradient(135deg,#9526F3,#6d1ed4);color:#fff;
  font-weight:600;position:relative;overflow:hidden;transition:.3s;
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn-participate::after{
  content:"";position:absolute;width:120%;height:100%;
  background:linear-gradient(120deg,transparent,rgba(255,255,255,.35),transparent);
  top:0;left:-120%;transition:.5s;
}
.btn-participate:hover::after{left:120%}
.btn-participate:hover{transform:scale(1.03);box-shadow:0 10px 28px rgba(149,38,243,.4)}
.btn-participate:disabled{opacity:.5;cursor:not-allowed;transform:none}

/* ── MY REGISTRATIONS ── */
.reg-card{
  background:var(--card);border:1px solid var(--border);
  border-radius:18px;padding:20px;margin-bottom:16px;
  display:grid;grid-template-columns:1fr auto;gap:16px;align-items:start;
}
.reg-title{font-size:1rem;font-weight:700;color:#fff;margin:0 0 6px}
.reg-meta{font-size:.82rem;color:var(--muted)}
.reg-badges{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}

/* ── BADGES ── */
.badge-status{
  padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:700;
}
.badge-status.approved{background:rgba(34,197,94,.15);color:#4ade80;border:1px solid rgba(34,197,94,.25)}
.badge-status.rejected{background:rgba(239,68,68,.12);color:#f87171;border:1px solid rgba(239,68,68,.2)}
.badge-status.pending{background:rgba(234,179,8,.12);color:#fbbf24;border:1px solid rgba(234,179,8,.2)}

/* ── HOSTED / MANAGE ── */
.hosted-card{
  background:var(--card);border:1px solid var(--border);
  border-radius:18px;padding:20px;margin-bottom:16px;
}
.hosted-header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px}
.hosted-title{font-size:1.05rem;font-weight:700;color:#fff;margin:0 0 4px}
.stat-row{display:flex;gap:12px;margin-top:12px;flex-wrap:wrap}
.stat-box{
  background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);
  border-radius:12px;padding:10px 16px;text-align:center;min-width:80px;
}
.stat-box strong{display:block;font-size:1.2rem;color:#fff}
.stat-box small{color:var(--muted);font-size:.75rem}
.btn-manage{
  padding:7px 16px;border-radius:12px;border:1px solid var(--border);
  background:var(--accent-soft);color:#d9b6ff;font-size:.85rem;
  font-weight:600;text-decoration:none;transition:.2s;
}
.btn-manage:hover{background:var(--accent);color:#fff;border-color:var(--accent)}

/* ── REGISTRANTS TABLE ── */
.reg-table-wrap{margin-top:14px;display:none}
.reg-table-wrap.open{display:block}
.reg-table{width:100%;border-collapse:collapse;font-size:.82rem}
.reg-table th{color:var(--muted);padding:8px 10px;text-align:left;
  border-bottom:1px solid rgba(255,255,255,.06);font-weight:600}
.reg-table td{padding:8px 10px;color:#e2e8f0;
  border-bottom:1px solid rgba(255,255,255,.04)}
.reg-table tr:last-child td{border:none}

/* ── EMPTY STATE ── */
.empty-state{
  text-align:center;padding:60px 20px;color:var(--muted);
}
.empty-state i{font-size:3rem;opacity:.3;display:block;margin-bottom:14px}

/* HOST BTN */
.btn-host{
  display:inline-flex;align-items:center;gap:8px;
  padding:10px 22px;border-radius:14px;
  background:linear-gradient(135deg,#9526F3,#6d11bf);
  color:#fff;font-weight:700;text-decoration:none;
  border:none;font-size:.92rem;transition:.2s;
}
.btn-host:hover{box-shadow:0 8px 24px rgba(149,38,243,.4);color:#fff}

@media(max-width:640px){
  .tab-btn span{display:none}
  .reg-card{grid-template-columns:1fr}
}
</style>
</head>
<body>
<div class="container" style="max-width:1100px">

  <div class="page-header">
    <div class="eyebrow"><i class="bi bi-trophy-fill"></i> Tournaments</div>
    <h1>Tournament Hub</h1>
    <p>Browse open tournaments, track your registrations, and manage what you host.</p>
  </div>

  <!-- ── TAB NAV ── -->
  <?php
    $browseCount = mysqli_num_rows($browseRes);
    $myRegCount  = mysqli_num_rows($myRegRes);
    $hostedCount = mysqli_num_rows($hostedRes);
    // reset pointers
    mysqli_data_seek($browseRes, 0);
    mysqli_data_seek($myRegRes, 0);
    mysqli_data_seek($hostedRes, 0);
  ?>
  <div class="tab-nav">
    <button class="tab-btn active" onclick="switchTab('browse',this)">
      <i class="bi bi-search"></i>
      <span>Browse</span>
      <span class="tab-count"><?php echo $browseCount; ?></span>
    </button>
    <button class="tab-btn" onclick="switchTab('myregs',this)">
      <i class="bi bi-person-check-fill"></i>
      <span>My Registrations</span>
      <span class="tab-count"><?php echo $myRegCount; ?></span>
    </button>
    <button class="tab-btn" onclick="switchTab('hosted',this)">
      <i class="bi bi-flag-fill"></i>
      <span>Host & Manage</span>
      <span class="tab-count"><?php echo $hostedCount; ?></span>
    </button>
  </div>

  <!-- ══════════════════════════════════════════
       TAB 1 — BROWSE TOURNAMENTS
  ══════════════════════════════════════════ -->
  <div class="tab-panel active" id="panel-browse">
    <?php if ($browseCount === 0): ?>
      <div class="empty-state">
        <i class="bi bi-calendar-x"></i>
        <h4>No open tournaments right now</h4>
        <p>Check back later or host your own!</p>
      </div>
    <?php else: ?>
    <div class="row g-4">
    <?php while ($row = mysqli_fetch_assoc($browseRes)):
      $maxP  = (int)$row['max_participation'];
      $reged = (int)$row['registered_teams'];
      $pct   = $maxP > 0 ? min(100, round(($reged / $maxP) * 100)) : 0;
      $full  = $reged >= $maxP;
      $venue = $row['is_external_turf']
             ? htmlspecialchars($row['external_turf_name'] . ' — ' . $row['external_location'])
             : htmlspecialchars(($row['turf_name'] ?? '?') . ' (' . ($row['city_name'] ?? '') . ')');
    ?>
      <div class="col-lg-4 col-md-6">
        <div class="t-card">
          <div class="t-title"><?php echo htmlspecialchars($row['tournament_name']); ?></div>

          <div class="t-info"><i class="bi bi-geo-alt-fill"></i><?php echo $venue; ?></div>
          <div class="t-info"><i class="bi bi-calendar-event"></i>
            <?php echo $row['start_date']; ?> → <?php echo $row['end_date']; ?>
          </div>
          <div class="t-info"><i class="bi bi-clock"></i>
            <?php echo substr($row['tournament_time'],0,5); ?>
            – <?php echo substr($row['end_time'],0,5); ?>
          </div>
          <div class="t-info"><i class="bi bi-people-fill"></i>
            <?php echo $reged; ?> / <?php echo $maxP; ?> teams registered
          </div>
          <?php if ($row['max_players_per_team']): ?>
          <div class="t-info"><i class="bi bi-person-fill"></i>
            Max <?php echo $row['max_players_per_team']; ?> players/team
          </div>
          <?php endif; ?>

          <!-- Slot fill bar -->
          <div class="slots-bar">
            <div class="slots-fill" style="width:<?php echo $pct; ?>%"></div>
          </div>

          <!-- Prizes + entry fee -->
          <div class="prize-row">
            <?php if ($row['entry_fee'] > 0): ?>
              <span class="prize-chip prize-entry">
                <i class="bi bi-ticket-perforated"></i> ₹<?php echo number_format($row['entry_fee'],2); ?> entry
              </span>
            <?php else: ?>
              <span class="prize-chip prize-entry">Free entry</span>
            <?php endif; ?>
            <?php if ($row['winner_prize']): ?>
              <span class="prize-chip prize-gold">🥇 ₹<?php echo number_format($row['winner_prize'],2); ?></span>
            <?php endif; ?>
            <?php if ($row['runnerup_prize']): ?>
              <span class="prize-chip prize-silver">🥈 ₹<?php echo number_format($row['runnerup_prize'],2); ?></span>
            <?php endif; ?>
          </div>

          <?php if ($row['terms_conditions']): ?>
          <div class="t-terms">
            <strong style="color:#d9b6ff">Terms:</strong><br>
            <?php echo nl2br(htmlspecialchars($row['terms_conditions'])); ?>
          </div>
          <?php endif; ?>

          <?php if ($full): ?>
            <button class="btn-participate" disabled>Tournament Full</button>
          <?php else: ?>
            <a href="tournament_participation.php?tournament_id=<?php echo $row['tournament_id']; ?>"
               class="btn-participate">
              <i class="bi bi-rocket-takeoff-fill"></i> Participate Now
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══════════════════════════════════════════
       TAB 2 — MY REGISTRATIONS
  ══════════════════════════════════════════ -->
  <div class="tab-panel" id="panel-myregs">
    <?php if ($myRegCount === 0): ?>
      <div class="empty-state">
        <i class="bi bi-journal-x"></i>
        <h4>You haven't registered in any tournament yet</h4>
        <p>Browse open tournaments and hit Participate Now!</p>
      </div>
    <?php else:
      while ($r = mysqli_fetch_assoc($myRegRes)):
        $venue2 = htmlspecialchars(($r['turf_name'] ?? 'External venue') . ($r['city_name'] ? ' (' . $r['city_name'] . ')' : ''));
    ?>
      <div class="reg-card">
        <div>
          <div class="reg-title"><?php echo htmlspecialchars($r['tournament_name']); ?></div>
          <div class="reg-meta">
            <i class="bi bi-geo-alt" style="color:var(--accent)"></i> <?php echo $venue2; ?> &nbsp;|&nbsp;
            <i class="bi bi-calendar2"></i> <?php echo $r['start_date']; ?> → <?php echo $r['end_date']; ?>
          </div>
          <div class="reg-meta mt-1">
            <i class="bi bi-people" style="color:var(--accent)"></i>
            Team: <strong style="color:#fff"><?php echo htmlspecialchars($r['team_name']); ?></strong>
            &nbsp;|&nbsp; Captain: <?php echo htmlspecialchars($r['captain_name']); ?>
          </div>
          <?php if ($r['entry_fee'] > 0): ?>
          <div class="reg-meta mt-1">
            <i class="bi bi-currency-rupee" style="color:var(--accent)"></i>
            Entry fee: ₹<?php echo number_format($r['entry_fee'],2); ?>
          </div>
          <?php endif; ?>
          <div class="reg-badges">
            <?php echo statusBadge($r['tournament_status']); ?>
            <?php echo payBadge($r['payment_status'] ?? 'pending'); ?>
          </div>
        </div>
        <div style="text-align:right;min-width:120px">
          <div style="font-size:.8rem;color:var(--muted)">Registered on</div>
          <div style="font-size:.85rem;color:#e2e8f0"><?php echo date('d M Y', strtotime($r['created_at'])); ?></div>
        </div>
      </div>
    <?php endwhile; endif; ?>
  </div>

  <!-- ══════════════════════════════════════════
       TAB 3 — HOST & MANAGE
  ══════════════════════════════════════════ -->
  <div class="tab-panel" id="panel-hosted">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
      <div>
        <h5 class="mb-1" style="color:#fff">Your Hosted Tournaments</h5>
        <p style="color:var(--muted);margin:0;font-size:.88rem">
          Tournaments you submitted — admin approval required before they go live.
        </p>
      </div>
      <a href="tournament.php" class="btn-host">
        <i class="bi bi-plus-circle-fill"></i> Host New Tournament
      </a>
    </div>

    <?php if ($hostedCount === 0): ?>
      <div class="empty-state">
        <i class="bi bi-flag"></i>
        <h4>You haven't hosted any tournaments yet</h4>
        <a href="tournament.php" class="btn-host mt-3">
          <i class="bi bi-plus-circle-fill"></i> Host Your First Tournament
        </a>
      </div>
    <?php else:
      while ($h = mysqli_fetch_assoc($hostedRes)):
        $totalReg = (int)$h['total_registrations'];
        $maxP2    = (int)$h['max_participation'];
        // Fetch registrants for this tournament
        $regFetch = mysqli_prepare($conn,
          "SELECT tr.id, tr.full_name, tr.mobile_number, tr.email, tr.team_name,
                  tr.captain_name, tr.captain_mobile, tr.player_list,
                  tr.payment_status, tr.created_at
           FROM tournament_registrations tr
           WHERE tr.tournament_id = ?
           ORDER BY tr.created_at DESC");
        mysqli_stmt_bind_param($regFetch, "i", $h['tournament_id']);
        mysqli_stmt_execute($regFetch);
        $regRows = mysqli_stmt_get_result($regFetch);
        $tid = $h['tournament_id'];
    ?>
      <div class="hosted-card">
        <div class="hosted-header">
          <div>
            <div class="hosted-title"><?php echo htmlspecialchars($h['tournament_name']); ?></div>
            <div style="font-size:.82rem;color:var(--muted)">
              <i class="bi bi-calendar2" style="color:var(--accent)"></i>
              <?php echo $h['start_date']; ?> → <?php echo $h['end_date']; ?>
              &nbsp;|&nbsp;
              <?php echo substr($h['tournament_time'],0,5); ?> – <?php echo substr($h['end_time'],0,5); ?>
            </div>
            <div class="mt-2">
              <?php echo statusBadge($h['status']); ?>
              <?php if ($h['status'] === 'P'): ?>
                <span style="font-size:.78rem;color:var(--muted);margin-left:6px">
                  Waiting for admin approval
                </span>
              <?php endif; ?>
            </div>
          </div>
          <div style="display:flex;gap:8px;align-items:flex-start">
            <?php if ($totalReg > 0): ?>
            <button class="btn-manage"
              onclick="toggleRegs('regs-<?php echo $tid; ?>', this)">
              <i class="bi bi-people-fill"></i>
              <?php echo $totalReg; ?> Team<?php echo $totalReg!==1?'s':''; ?>
            </button>
            <?php endif; ?>
          </div>
        </div>

        <div class="stat-row">
          <div class="stat-box">
            <strong><?php echo $totalReg; ?></strong>
            <small>Registered</small>
          </div>
          <div class="stat-box">
            <strong><?php echo $maxP2; ?></strong>
            <small>Max Teams</small>
          </div>
          <div class="stat-box">
            <strong><?php echo max(0,$maxP2-$totalReg); ?></strong>
            <small>Slots Left</small>
          </div>
          <?php if ($h['entry_fee'] > 0): ?>
          <div class="stat-box">
            <strong>₹<?php echo number_format($h['entry_fee']*$totalReg,0); ?></strong>
            <small>Total Fees</small>
          </div>
          <?php endif; ?>
        </div>

        <!-- Registrants table (collapsed by default) -->
        <?php if ($totalReg > 0): ?>
        <div class="reg-table-wrap" id="regs-<?php echo $tid; ?>">
          <div style="font-size:.85rem;color:var(--muted);margin:14px 0 8px;font-weight:600">
            Registered Teams
          </div>
          <div style="overflow-x:auto">
          <table class="reg-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Team</th>
                <th>Captain</th>
                <th>Mobile</th>
                <th>Contact Person</th>
                <th>Email</th>
                <th>Payment</th>
                <th>Registered</th>
              </tr>
            </thead>
            <tbody>
            <?php $i=1; while($reg = mysqli_fetch_assoc($regRows)): ?>
              <tr>
                <td><?php echo $i++; ?></td>
                <td><strong style="color:#fff"><?php echo htmlspecialchars($reg['team_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($reg['captain_name']); ?></td>
                <td><?php echo htmlspecialchars($reg['captain_mobile']); ?></td>
                <td><?php echo htmlspecialchars($reg['full_name']); ?></td>
                <td><?php echo htmlspecialchars($reg['email']); ?></td>
                <td><?php echo payBadge($reg['payment_status'] ?? 'pending'); ?></td>
                <td style="color:var(--muted)"><?php echo date('d M y', strtotime($reg['created_at'])); ?></td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
          </div>
        </div>
        <?php endif; ?>

      </div>
    <?php endwhile; endif; ?>
  </div>

</div><!-- /container -->

<script>
function switchTab(name, btn) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('panel-' + name).classList.add('active');
  btn.classList.add('active');
}
function toggleRegs(id, btn) {
  const el = document.getElementById(id);
  el.classList.toggle('open');
  btn.textContent = el.classList.contains('open') ? 'Hide Teams' : btn.textContent;
}
</script>
</body>
</html>