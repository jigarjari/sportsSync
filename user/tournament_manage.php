<?php
session_start();
include('../db.php');

$owner_id = $_SESSION['user_id'] ?? 0;
if (!$owner_id) {
    header("Location: ../login.php");
    exit;
}

$tournament_id = (int)($_GET['id'] ?? 0);
if ($tournament_id <= 0) {
    header("Location: my_turfs.php");
    exit;
}

// Fetch tournament — must belong to this vendor
$tStmt = mysqli_prepare($conn,
    "SELECT tt.*, tf.turf_name, tf.location, c.city_name, s.sport_name
     FROM tournamenttb tt
     LEFT JOIN turftb tf    ON tf.turf_id  = tt.turf_id
     LEFT JOIN citytb c     ON c.city_id   = tf.city_id
     LEFT JOIN sportstb s   ON s.sport_id  = tt.sport_id
     WHERE tt.tournament_id = ? AND tt.host_id = ? AND tt.created_by = 'USER'");
mysqli_stmt_bind_param($tStmt, "ii", $tournament_id, $owner_id);
mysqli_stmt_execute($tStmt);
$tournament = mysqli_fetch_assoc(mysqli_stmt_get_result($tStmt));

if (!$tournament) {
    header("Location: my_turfs.php");
    exit;
}

// Fetch all registered teams
$regStmt = mysqli_prepare($conn,
    "SELECT * FROM tournament_registrations
     WHERE tournament_id = ?
     ORDER BY created_at ASC");
mysqli_stmt_bind_param($regStmt, "i", $tournament_id);
mysqli_stmt_execute($regStmt);
$regRes  = mysqli_stmt_get_result($regStmt);
$regRows = [];
while ($r = mysqli_fetch_assoc($regRes)) $regRows[] = $r;
$totalReg = count($regRows);

// Fetch rewards
$rewStmt = mysqli_prepare($conn,
    "SELECT reward_type FROM tournament_rewards WHERE tournament_id = ?");
mysqli_stmt_bind_param($rewStmt, "i", $tournament_id);
mysqli_stmt_execute($rewStmt);
$rewRes = mysqli_stmt_get_result($rewStmt);
$rewards = [];
while ($rw = mysqli_fetch_assoc($rewRes)) $rewards[] = $rw['reward_type'];

$rewardLabels = [
    'MAN_OF_MATCH'      => 'Man of the Match',
    'MAN_OF_TOURNAMENT' => 'Man of the Tournament',
    'BEST_BATSMAN'      => 'Best Batsman',
    'BEST_BOWLER'       => 'Best Bowler',
    'BEST_FIELDER'      => 'Best Fielder',
    'BEST_GOALKEEPER'   => 'Best Goalkeeper',
    'BEST_PLAYER'       => 'Best Player',
    'FAIR_PLAY'         => 'Fair Play Award',
];

function statusBadge($s) {
    return match($s) {
        'A' => '<span class="bs approved">Approved</span>',
        'R' => '<span class="bs rejected">Rejected</span>',
        default => '<span class="bs pending">Pending</span>',
    };
}
function payBadge($s) {
    return match(strtolower($s ?? '')) {
        'paid'  => '<span class="bs approved">Paid</span>',
        'free'  => '<span class="bs free">Free</span>',
        default => '<span class="bs pending">Pending</span>',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage — <?php echo htmlspecialchars($tournament['tournament_name']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="../whole.css" rel="stylesheet">
<style>
:root{--accent:#9526F3;--accent-soft:rgba(149,38,243,.15);--border:rgba(149,38,243,.3);--muted:#9ca3af;--card:rgba(16,16,22,.92)}
*,*::before,*::after{box-sizing:border-box}
body{
  margin:0;min-height:100vh;color:#f1f5f9;font-family:'Segoe UI',sans-serif;
  background:#0e0f11;
  background-image:linear-gradient(45deg,#1f1f1f 25%,transparent 25%),
    linear-gradient(-45deg,#1f1f1f 25%,transparent 25%),
    linear-gradient(45deg,transparent 75%,#1f1f1f 75%),
    linear-gradient(-45deg,transparent 75%,#1f1f1f 75%);
  background-size:6px 6px;background-position:0 0,0 3px,3px -3px,-3px 0;
}
.shell{width:min(1200px,calc(100% - 2rem));margin:32px auto}
.back-btn{
  display:inline-flex;align-items:center;gap:8px;
  color:var(--muted);text-decoration:none;font-size:.9rem;margin-bottom:20px;
  transition:.2s;
}
.back-btn:hover{color:#fff}

/* ── HERO ── */
.hero{
  background:var(--card);border:1px solid var(--border);
  border-radius:24px;padding:26px 28px;margin-bottom:22px;
}
.hero h1{font-size:clamp(1.5rem,3vw,2rem);color:#fff;margin:0 0 6px}
.hero-meta{display:flex;flex-wrap:wrap;gap:18px;margin-top:12px}
.hm{display:flex;align-items:center;gap:7px;font-size:.85rem;color:var(--muted)}
.hm i{color:var(--accent)}
.prize-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.chip{padding:4px 12px;border-radius:999px;font-size:.78rem;font-weight:600}
.chip-gold{background:rgba(234,179,8,.15);color:#fbbf24;border:1px solid rgba(234,179,8,.25)}
.chip-silver{background:rgba(156,163,175,.12);color:#d1d5db;border:1px solid rgba(156,163,175,.2)}
.chip-entry{background:var(--accent-soft);color:#d9b6ff;border:1px solid rgba(149,38,243,.25)}
.chip-reward{background:rgba(99,102,241,.15);color:#a5b4fc;border:1px solid rgba(99,102,241,.25)}

/* ── STAT BOXES ── */
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
.stat-box{
  background:var(--card);border:1px solid var(--border);
  border-radius:18px;padding:18px;text-align:center;
}
.stat-box strong{display:block;font-size:1.6rem;color:#fff;font-weight:800}
.stat-box small{color:var(--muted);font-size:.8rem}

/* ── SEARCH / FILTER ── */
.controls{
  display:flex;gap:10px;flex-wrap:wrap;
  margin-bottom:18px;align-items:center;
}
.search-box{
  flex:1;min-width:200px;background:rgba(255,255,255,.05);
  border:1px solid rgba(255,255,255,.1);border-radius:14px;
  color:#fff;padding:10px 16px;font-size:.9rem;
}
.search-box::placeholder{color:#6b7280}
.search-box:focus{outline:none;border-color:var(--accent)}
.filter-sel{
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);
  border-radius:14px;color:#fff;padding:10px 14px;font-size:.88rem;
}
.filter-sel option{color:#111}

/* ── TABLE ── */
.table-wrap{
  background:var(--card);border:1px solid var(--border);
  border-radius:20px;overflow:hidden;
}
.rtable{width:100%;border-collapse:collapse;font-size:.85rem}
.rtable thead tr{background:rgba(149,38,243,.12)}
.rtable th{
  padding:12px 14px;text-align:left;color:var(--muted);
  font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.5px;
  border-bottom:1px solid rgba(255,255,255,.06);white-space:nowrap;
}
.rtable td{
  padding:13px 14px;color:#e2e8f0;
  border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;
}
.rtable tbody tr:last-child td{border:none}
.rtable tbody tr:hover{background:rgba(149,38,243,.06)}
.team-name{font-weight:700;color:#fff}
.player-list-cell{
  max-width:180px;overflow:hidden;text-overflow:ellipsis;
  white-space:nowrap;cursor:pointer;color:#a5b4fc;
}

/* ── BADGES ── */
.bs{padding:3px 10px;border-radius:999px;font-size:.73rem;font-weight:700}
.bs.approved{background:rgba(34,197,94,.15);color:#4ade80;border:1px solid rgba(34,197,94,.25)}
.bs.rejected{background:rgba(239,68,68,.12);color:#f87171;border:1px solid rgba(239,68,68,.2)}
.bs.pending{background:rgba(234,179,8,.12);color:#fbbf24;border:1px solid rgba(234,179,8,.2)}
.bs.free{background:rgba(99,102,241,.12);color:#a5b4fc;border:1px solid rgba(99,102,241,.2)}

/* ── DETAIL MODAL ── */
.modal-content{background:#13131a;border:1px solid var(--border);border-radius:20px;color:#f1f5f9}
.modal-header{border-bottom:1px solid rgba(255,255,255,.07)}
.modal-footer{border-top:1px solid rgba(255,255,255,.07)}
.drow{display:flex;gap:10px;margin-bottom:10px;font-size:.88rem}
.drow .dlabel{color:var(--muted);min-width:150px;flex-shrink:0}
.drow .dval{color:#fff;word-break:break-word}
.section-divider{
  font-size:.78rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.8px;color:var(--accent);margin:16px 0 8px;
}

/* ── EMPTY ── */
.empty-state{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-state i{font-size:3rem;opacity:.3;display:block;margin-bottom:14px}

@media(max-width:900px){
  .stat-row{grid-template-columns:repeat(2,1fr)}
  .rtable{font-size:.78rem}
}
@media(max-width:580px){
  .stat-row{grid-template-columns:1fr 1fr}
  .shell{margin:16px auto}
}
</style>
</head>
<body>
<div class="shell">

 <a href="tournaments_list.php" class="back-btn">
  <i class="bi bi-arrow-left"></i> Back to Tournaments
</a>

  <!-- ── HERO ── -->
  <div class="hero">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;align-items:flex-start">
      <div>
        <div style="font-size:.82rem;color:var(--muted);margin-bottom:4px">
          <i class="bi bi-trophy-fill" style="color:var(--accent)"></i> Tournament Management
        </div>
        <h1><?php echo htmlspecialchars($tournament['tournament_name']); ?></h1>
      </div>
      <?php echo statusBadge($tournament['status']); ?>
    </div>

    <div class="hero-meta">
      <div class="hm"><i class="bi bi-geo-alt-fill"></i>
        <?php echo htmlspecialchars(($tournament['turf_name'] ?? 'External') . ' — ' . ($tournament['location'] ?? $tournament['external_location'] ?? '')); ?>
      </div>
      <div class="hm"><i class="bi bi-calendar-event"></i>
        <?php echo $tournament['start_date']; ?> → <?php echo $tournament['end_date']; ?>
      </div>
      <div class="hm"><i class="bi bi-clock"></i>
        <?php echo substr($tournament['tournament_time'],0,5); ?> – <?php echo substr($tournament['end_time'],0,5); ?>
      </div>
      <div class="hm"><i class="bi bi-controller"></i>
        <?php echo htmlspecialchars($tournament['sport_name'] ?? ''); ?>
      </div>
    </div>

    <div class="prize-row">
      <?php if ($tournament['entry_fee'] > 0): ?>
        <span class="chip chip-entry"><i class="bi bi-ticket-perforated"></i> ₹<?php echo number_format($tournament['entry_fee'],2); ?> entry</span>
      <?php else: ?>
        <span class="chip chip-entry">Free Entry</span>
      <?php endif; ?>
      <?php if ($tournament['winner_prize']): ?>
        <span class="chip chip-gold">🥇 ₹<?php echo number_format($tournament['winner_prize'],2); ?></span>
      <?php endif; ?>
      <?php if ($tournament['runnerup_prize']): ?>
        <span class="chip chip-silver">🥈 ₹<?php echo number_format($tournament['runnerup_prize'],2); ?></span>
      <?php endif; ?>
      <?php foreach ($rewards as $rw): ?>
        <span class="chip chip-reward"><i class="bi bi-award-fill"></i> <?php echo htmlspecialchars($rewardLabels[$rw] ?? $rw); ?></span>
      <?php endforeach; ?>
    </div>

    <?php if ($tournament['terms_conditions']): ?>
    <div style="margin-top:12px;padding:10px 14px;border-radius:12px;
      background:rgba(2,6,23,.7);font-size:.8rem;color:#94a3b8;
      border:1px solid rgba(149,38,243,.18)">
      <strong style="color:#d9b6ff">Terms:</strong>
      <?php echo nl2br(htmlspecialchars($tournament['terms_conditions'])); ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── STATS ── -->
  <div class="stat-row">
    <div class="stat-box">
      <strong><?php echo $totalReg; ?></strong>
      <small>Registered Teams</small>
    </div>
    <div class="stat-box">
      <strong><?php echo $tournament['max_participation']; ?></strong>
      <small>Max Teams</small>
    </div>
    <div class="stat-box">
      <strong><?php echo max(0, $tournament['max_participation'] - $totalReg); ?></strong>
      <small>Slots Left</small>
    </div>
    <div class="stat-box">
      <strong>
        <?php
          $paidCount = count(array_filter($regRows, fn($r) => strtolower($r['payment_status'] ?? '') === 'paid'));
          echo $tournament['entry_fee'] > 0
            ? '₹' . number_format($tournament['entry_fee'] * $paidCount, 0)
            : '—';
        ?>
      </strong>
      <small><?php echo $tournament['entry_fee'] > 0 ? 'Total Fee Collected' : 'Free Tournament'; ?></small>
    </div>
  </div>

  <!-- ── REGISTERED TEAMS TABLE ── -->
  <?php if ($totalReg === 0): ?>
    <div class="empty-state">
      <i class="bi bi-people"></i>
      <h4>No teams registered yet</h4>
      <p>Share your tournament to attract participants.</p>
    </div>
  <?php else: ?>

  <!-- Search / Filter -->
  <div class="controls">
    <input type="text" class="search-box" id="searchInput"
      placeholder="Search team name, captain, contact...">
    <select class="filter-sel" id="payFilter">
      <option value="">All Payments</option>
      <option value="paid">Paid</option>
      <option value="free">Free</option>
      <option value="pending">Pending</option>
    </select>
    <div style="color:var(--muted);font-size:.85rem;margin-left:auto">
      Showing <span id="visibleCount"><?php echo $totalReg; ?></span> of <?php echo $totalReg; ?> teams
    </div>
  </div>

  <div class="table-wrap">
    <div style="overflow-x:auto">
    <table class="rtable" id="regTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Team</th>
          <th>Captain</th>
          <th>Captain Mobile</th>
          <th>Contact Person</th>
          <th>Email</th>
          <th>Team Size</th>
          <th>Payment</th>
          <th>Registered</th>
          <th>Details</th>
        </tr>
      </thead>
      <tbody id="regTableBody">
        <?php foreach ($regRows as $i => $reg): ?>
        <tr data-pay="<?php echo strtolower($reg['payment_status'] ?? 'pending'); ?>"
            data-search="<?php echo strtolower(
              htmlspecialchars($reg['team_name'] . ' ' . $reg['captain_name'] . ' ' .
              $reg['captain_mobile'] . ' ' . $reg['full_name'] . ' ' . $reg['email'])
            ); ?>">
          <td style="color:var(--muted)"><?php echo $i + 1; ?></td>
          <td><span class="team-name"><?php echo htmlspecialchars($reg['team_name']); ?></span></td>
          <td><?php echo htmlspecialchars($reg['captain_name']); ?></td>
          <td><?php echo htmlspecialchars($reg['captain_mobile']); ?></td>
          <td><?php echo htmlspecialchars($reg['full_name']); ?></td>
          <td style="color:#93c5fd"><?php echo htmlspecialchars($reg['email']); ?></td>
          <td style="text-align:center"><?php echo $reg['team_size']; ?></td>
          <td><?php echo payBadge($reg['payment_status'] ?? 'pending'); ?></td>
          <td style="color:var(--muted);white-space:nowrap"><?php echo date('d M Y', strtotime($reg['created_at'])); ?></td>
          <td>
            <button class="btn btn-sm" onclick="showDetail(<?php echo htmlspecialchars(json_encode($reg), ENT_QUOTES); ?>)"
              style="background:var(--accent-soft);color:#d9b6ff;border:1px solid var(--border);border-radius:10px;padding:4px 12px;font-size:.78rem">
              <i class="bi bi-eye-fill"></i> View
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- ── DETAIL MODAL ── -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTeamName">Team Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="modalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Search + Filter ── */
const searchInput  = document.getElementById('searchInput');
const payFilter    = document.getElementById('payFilter');
const rows         = document.querySelectorAll('#regTableBody tr');
const visibleCount = document.getElementById('visibleCount');

function filterTable() {
  const q   = searchInput ? searchInput.value.toLowerCase() : '';
  const pay = payFilter   ? payFilter.value.toLowerCase()   : '';
  let count = 0;
  rows.forEach(row => {
    const matchSearch = !q   || row.dataset.search.includes(q);
    const matchPay    = !pay || row.dataset.pay === pay;
    const show = matchSearch && matchPay;
    row.style.display = show ? '' : 'none';
    if (show) count++;
  });
  if (visibleCount) visibleCount.textContent = count;
}
if (searchInput) searchInput.addEventListener('input', filterTable);
if (payFilter)   payFilter.addEventListener('change', filterTable);

/* ── Detail Modal ── */
function showDetail(reg) {
  document.getElementById('modalTeamName').textContent = reg.team_name + ' — Full Details';

  const players = (reg.player_list || '').trim()
    ? reg.player_list.split('\n').map((p,i) => `<li>${i+1}. ${p}</li>`).join('')
    : '<li style="color:#6b7280">Not provided</li>';

  document.getElementById('modalBody').innerHTML = `
    <div class="section-divider">Registration Info</div>
    <div class="drow"><span class="dlabel">Contact Person</span><span class="dval">${reg.full_name}</span></div>
    <div class="drow"><span class="dlabel">Mobile</span><span class="dval">${reg.mobile_number}</span></div>
    <div class="drow"><span class="dlabel">Email</span><span class="dval">${reg.email}</span></div>
    <div class="drow"><span class="dlabel">Age</span><span class="dval">${reg.age}</span></div>
    <div class="drow"><span class="dlabel">Emergency Contact</span><span class="dval">${reg.emergency_contact}</span></div>
    <div class="drow"><span class="dlabel">Registered On</span><span class="dval">${reg.created_at}</span></div>

    <div class="section-divider">Team Info</div>
    <div class="drow"><span class="dlabel">Team Name</span><span class="dval">${reg.team_name}</span></div>
    <div class="drow"><span class="dlabel">Team Size</span><span class="dval">${reg.team_size} players</span></div>
    <div class="drow"><span class="dlabel">Captain</span><span class="dval">${reg.captain_name}</span></div>
    <div class="drow"><span class="dlabel">Captain Mobile</span><span class="dval">${reg.captain_mobile}</span></div>
    <div class="drow"><span class="dlabel">Captain Email</span><span class="dval">${reg.captain_email}</span></div>
    ${reg.team_notes ? `<div class="drow"><span class="dlabel">Notes</span><span class="dval">${reg.team_notes}</span></div>` : ''}

    <div class="section-divider">Player List</div>
    <ul style="padding-left:18px;color:#e2e8f0;font-size:.88rem">${players}</ul>

    <div class="section-divider">Payment</div>
    <div class="drow"><span class="dlabel">Payment Status</span><span class="dval">${reg.payment_status ?? 'pending'}</span></div>
    ${reg.razorpay_payment_id ? `<div class="drow"><span class="dlabel">Razorpay ID</span><span class="dval" style="font-size:.78rem;color:#93c5fd">${reg.razorpay_payment_id}</span></div>` : ''}
  `;

  new bootstrap.Modal(document.getElementById('detailModal')).show();
}
</script>
</body>
</html>