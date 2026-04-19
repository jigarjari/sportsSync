<?php
session_start();
include("../db.php");

$owner_id = $_SESSION['user_id'] ?? 0;

$turfs = [];
$sports = [];
$courts = [];

$successMessage = "";
$errorMessage = "";
$errors = [];

$formData = [
  'turf_id' => '',
  'sport_id' => '',
  'tournament_name' => '',
  'max_participation' => '',
  'start_date' => '',
  'end_date' => '',
  'tournament_time' => '',
  'terms_conditions' => ''
];

$selectedCourts = [];

/* FETCH DATA */

if ($owner_id > 0) {
  $stmt = mysqli_prepare($conn, "SELECT turf_id, turf_name, location FROM turftb WHERE owner_id = ?");
  mysqli_stmt_bind_param($stmt, "i", $owner_id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($row = mysqli_fetch_assoc($res)) $turfs[] = $row;
}

$res = mysqli_query($conn, "SELECT sport_id, sport_name FROM sportstb");
while ($row = mysqli_fetch_assoc($res)) $sports[] = $row;

if ($owner_id > 0) {
  $stmt = mysqli_prepare($conn,
    "SELECT tc.court_id, tc.turf_id, tc.sport_id, tc.court_name, tc.status
     FROM turf_courtstb tc
     JOIN turftb t ON t.turf_id = tc.turf_id
     WHERE t.owner_id = ?"
  );
  mysqli_stmt_bind_param($stmt, "i", $owner_id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($row = mysqli_fetch_assoc($res)) $courts[] = $row;
}

/* FORM SUBMIT */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // clean input
  foreach ($formData as $key => $val) {
    $formData[$key] = trim($_POST[$key] ?? '');
  }

  $selectedCourts = isset($_POST['court_ids']) ? array_map('intval', $_POST['court_ids']) : [];

  /* VALIDATION */

  // required
  foreach ($formData as $key => $val) {
    if ($val === '') {
      $errors[$key] = "This field is required";
    }
  }

  // name
  if (!isset($errors['tournament_name']) &&
      !preg_match("/^[a-zA-Z0-9 ]{3,100}$/", $formData['tournament_name'])) {
    $errors['tournament_name'] = "Only letters & numbers (3–100 chars)";
  }

  // participation
  if (!isset($errors['max_participation'])) {
    $max = (int)$formData['max_participation'];
    if ($max < 2 || $max > 1000) {
      $errors['max_participation'] = "Must be between 2–1000";
    }
  }

  // dates
  if (!isset($errors['start_date']) && !isset($errors['end_date'])) {
    $start = strtotime($formData['start_date']);
    $end = strtotime($formData['end_date']);
    $today = strtotime(date("Y-m-d"));

    if ($start < $today) {
      $errors['start_date'] = "Cannot be past date";
    }

    if ($end < $start) {
      $errors['end_date'] = "End must be after start";
    }
  }

  // time
  if (!isset($errors['tournament_time']) &&
      !preg_match("/^(?:[01]\d|2[0-3]):[0-5]\d$/", $formData['tournament_time'])) {
    $errors['tournament_time'] = "Invalid time";
  }

  // terms
  if (strlen($formData['terms_conditions']) > 1000) {
    $errors['terms_conditions'] = "Max 1000 characters";
  }

  // courts
  if (empty($selectedCourts)) {
    $errors['courts'] = "Select at least one court";
  }

  /* INSERT ONLY IF NO ERRORS */

  if (empty($errors)) {

    $turfId = (int)$formData['turf_id'];
    $sportId = (int)$formData['sport_id'];

    // validate turf ownership
    $check = mysqli_prepare($conn, "SELECT turf_id FROM turftb WHERE turf_id=? AND owner_id=?");
    mysqli_stmt_bind_param($check, "ii", $turfId, $owner_id);
    mysqli_stmt_execute($check);
    $res = mysqli_stmt_get_result($check);

    if (mysqli_num_rows($res) === 0) {
      $errorMessage = "Invalid turf selected";
    } else {

      // valid courts
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
          $errorMessage = "Invalid court selected";
          break;
        }
      }

      if ($errorMessage === "") {

        mysqli_begin_transaction($conn);

    $insertTournament = mysqli_prepare(
      $conn,
      "INSERT INTO tournamenttb (tournament_name, turf_id, sport_id, max_participation, start_date, end_date, tournament_time, terms_conditions,vendor_id)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?,?)"
    );

    if (!$insertTournament) {
      throw new Exception("Unable to prepare tournament insert.");
    }

    mysqli_stmt_bind_param(
      $insertTournament,
      "siiissssi",
      $formData['tournament_name'],
      $turfId,
      $sportId,
      $maxParticipation,
      $formData['start_date'],
      $formData['end_date'],
      $formData['tournament_time'],
      $formData['terms_conditions'],
      $owner_id
    );
    mysqli_stmt_execute($insertTournament);

        mysqli_stmt_execute($stmt);
        $tournamentId = mysqli_insert_id($conn);

        $courtStmt = mysqli_prepare($conn,
          "INSERT INTO tournament_courtstb (tournament_id, court_id) VALUES (?, ?)"
        );

        foreach ($selectedCourts as $cid) {
          mysqli_stmt_bind_param($courtStmt, "ii", $tournamentId, $cid);
          mysqli_stmt_execute($courtStmt);
        }

        mysqli_commit($conn);
        $successMessage = "Tournament created successfully!";

        // reset
        foreach ($formData as $k => $v) $formData[$k] = '';
        $selectedCourts = [];
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tournament</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
  --bg-main: #0e0f11;
  --card-bg: rgba(16, 16, 20, 0.92);
  --card-border: rgba(149, 38, 243, 0.34);
  --accent: #9526F3;
  --accent-soft: rgba(149, 38, 243, 0.18);
  --text-main: #f8f9fb;
  --text-muted: #9ca3af;
}

*,
*::before,
*::after {
  box-sizing: border-box;
}

body {
  margin: 0;
  min-height: 100vh;
  background-color: var(--bg-main);
  background-image:
    linear-gradient(45deg, #1f1f1f 25%, transparent 25%),
    linear-gradient(-45deg, #1f1f1f 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, #1f1f1f 75%),
    linear-gradient(-45deg, transparent 75%, #1f1f1f 75%);
  background-size: 6px 6px;
  background-position: 0 0, 0 3px, 3px -3px, -3px 0;
  color: var(--text-main);
  font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

.page-shell {
  width: min(1100px, calc(100% - 2rem));
  margin: 32px auto;
}

.hero-card,
.form-card {
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: 28px;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
}

.hero-card {
  padding: 28px;
  margin-bottom: 22px;
  position: relative;
  overflow: hidden;
}

.hero-card::before {
  content: "";
  position: absolute;
  inset: auto -60px -80px auto;
  width: 220px;
  height: 220px;
  pointer-events: none;
}

.eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 8px 14px;
  border-radius: 999px;
  background: var(--accent-soft);
  color: #d9b6ff;
  font-size: 0.9rem;
  margin-bottom: 12px;
}

.hero-card h1 {
  margin: 0 0 8px;
  font-size: clamp(1.9rem, 4vw, 2.8rem);
  color: #ffffff;
}

.hero-card p {
  margin: 0;
  max-width: 720px;
  color: var(--text-muted);
  line-height: 1.6;
}

.form-card {
  padding: 28px;
}

.section-title {
  margin: 0 0 18px;
  font-size: 1.1rem;
  color: var(--accent);
  font-weight: 700;
}

.section-block + .section-block {
  margin-top: 28px;
  padding-top: 24px;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.form-label {
  color: #f5f5f5;
  font-weight: 600;
  margin-bottom: 8px;
}

.form-control,
.form-select,
.form-control:focus,
.form-select:focus {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #ffffff;
  min-height: 48px;
  border-radius: 16px;
  box-shadow: none;
}

.form-control::placeholder {
  color: #8f96a3;
}

.form-select option {
  color: #111827;
}

.stat-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  margin-top: 18px;
}

.stat-chip {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 18px;
  padding: 16px;
}

.stat-chip small {
  display: block;
  color: var(--text-muted);
  margin-bottom: 6px;
}

.stat-chip strong {
  color: #ffffff;
  font-size: 1.1rem;
}

.btn-primary {
  background: linear-gradient(135deg, #9526F3, #6d11bf);
  border: none;
  min-height: 52px;
  border-radius: 18px;
  font-weight: 700;
}

.btn-primary:hover,
.btn-primary:focus {
  background: linear-gradient(135deg, #a240ff, #7516cd);
}

.empty-note {
  padding: 14px 16px;
  border-radius: 16px;
  background: rgba(255, 193, 7, 0.08);
  border: 1px solid rgba(255, 193, 7, 0.18);
  color: #ffe08a;
}

.court-panel {
  min-height: 120px;
  padding: 16px;
  border-radius: 18px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.03);
}

.court-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.court-option {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.07);
  background: rgba(255, 255, 255, 0.04);
}

.court-option input[type="checkbox"] {
  accent-color: var(--accent);
  transform: scale(1.15);
}

.court-empty {
  color: var(--text-muted);
  margin: 0;
}

@media (max-width: 767px) {
  .page-shell {
    width: min(100% - 1rem, 100%);
    margin: 16px auto;
  }

  .hero-card,
  .form-card {
    padding: 18px;
    border-radius: 22px;
  }

  .stat-grid,
  .court-grid {
    grid-template-columns: 1fr;
  }
}
</style>
</head>
<body>
  <div class="page-shell">
    <section class="hero-card">
      <div class="eyebrow">
        <i class="bi bi-trophy-fill"></i>
        Tournament Setup
      </div>
      <h1>Create a new tournament</h1>
      <p>Link the tournament to one of your turfs, choose the sport, and assign the courts that will be used.</p>

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
          <strong><?php echo count(array_filter($courts, static fn($court) => $court['status'] === 'A')); ?></strong>
        </div>
      </div>
    </section>

    <section class="form-card">
      <?php if ($successMessage !== ""): ?>
        <div class="alert alert-success mb-4" role="alert">
          <?php echo htmlspecialchars($successMessage); ?>
        </div>
      <?php endif; ?>

      <?php if ($errorMessage !== ""): ?>
        <div class="alert alert-danger mb-4" role="alert">
          <?php echo htmlspecialchars($errorMessage); ?>
        </div>
      <?php endif; ?>

      <?php if (empty($turfs)): ?>
        <div class="empty-note mb-4">
          No turfs found for this owner yet. Add a turf first so tournaments can be linked to a venue.
        </div>
      <?php endif; ?>

      <form method="post" novalidate>

<div class="section-block">
<h2 class="section-title">Tournament Details</h2>
<div class="row g-3">

<!-- Turf -->
<div class="col-md-6">
<label class="form-label">Select Turf <span class="text-danger">*</span></label>
<select name="turf_id"
class="form-select <?php echo isset($errors['turf_id']) ? 'is-invalid' : ''; ?>">
<option value="">Choose turf</option>
<?php foreach ($turfs as $turf): ?>
<option value="<?php echo $turf['turf_id']; ?>"
<?php if($formData['turf_id']==$turf['turf_id']) echo 'selected'; ?>>
<?php echo htmlspecialchars($turf['turf_name'].' - '.$turf['location']); ?>
</option>
<?php endforeach; ?>
</select>
<div class="invalid-feedback"><?php echo $errors['turf_id'] ?? ''; ?></div>
</div>

<!-- Sport -->
<div class="col-md-6">
<label class="form-label">Sport <span class="text-danger">*</span></label>
<select name="sport_id"
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
class="form-control <?php echo isset($errors['tournament_name']) ? 'is-invalid' : ''; ?>">
<div class="invalid-feedback"><?php echo $errors['tournament_name'] ?? ''; ?></div>
</div>

<!-- Participation -->
<div class="col-md-6">
<label class="form-label">Maximum Participation <span class="text-danger">*</span></label>
<input type="number" name="max_participation" min="2"
value="<?php echo htmlspecialchars($formData['max_participation']); ?>"
class="form-control <?php echo isset($errors['max_participation']) ? 'is-invalid' : ''; ?>">
<div class="invalid-feedback"><?php echo $errors['max_participation'] ?? ''; ?></div>
</div>

<!-- Start -->
<div class="col-md-4">
<label class="form-label">Start Date <span class="text-danger">*</span></label>
<input type="date" name="start_date"
value="<?php echo htmlspecialchars($formData['start_date']); ?>"
min="<?php echo date('Y-m-d'); ?>"
class="form-control <?php echo isset($errors['start_date']) ? 'is-invalid' : ''; ?>">
<div class="invalid-feedback"><?php echo $errors['start_date'] ?? ''; ?></div>
</div>

<!-- End -->
<div class="col-md-4">
<label class="form-label">End Date <span class="text-danger">*</span></label>
<input type="date" name="end_date"
value="<?php echo htmlspecialchars($formData['end_date']); ?>"
min="<?php echo date('Y-m-d'); ?>"
class="form-control <?php echo isset($errors['end_date']) ? 'is-invalid' : ''; ?>">
<div class="invalid-feedback"><?php echo $errors['end_date'] ?? ''; ?></div>
</div>

<!-- Time -->
<div class="col-md-4">
<label class="form-label">Time <span class="text-danger">*</span></label>
<input type="time" name="tournament_time"
value="<?php echo htmlspecialchars($formData['tournament_time']); ?>"
class="form-control <?php echo isset($errors['tournament_time']) ? 'is-invalid' : ''; ?>">
<div class="invalid-feedback"><?php echo $errors['tournament_time'] ?? ''; ?></div>
</div>

</div>
</div>

<!-- Courts -->
<div class="section-block">
<h2 class="section-title">Court Selection <span class="text-danger">*</span></h2>

<div class="court-panel
<?php echo isset($errors['courts']) ? 'border border-danger' : ''; ?>">

<div id="courtContainer" class="court-grid"></div>

<p id="courtEmptyState" class="court-empty mb-0">
Select turf and sport to view available courts.
</p>

<?php if(isset($errors['courts'])): ?>
<div class="text-danger mt-2"><?php echo $errors['courts']; ?></div>
<?php endif; ?>

</div>
</div>

<!-- Terms -->
<div class="section-block">
<h2 class="section-title">Terms and Conditions</h2>
<textarea name="terms_conditions"
class="form-control"><?php echo htmlspecialchars($formData['terms_conditions']); ?></textarea>
</div>

<!-- Submit -->
<div class="section-block">
<button type="submit" class="btn btn-primary w-100"
<?php echo empty($turfs) ? 'disabled' : ''; ?>>
Save Tournament Details
</button>
</div>

</form>
    </section>
  </div>

  <script>
    const allCourts = <?php echo json_encode($courts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const selectedCourtIds = new Set(<?php echo json_encode(array_values($selectedCourts)); ?>);
    const turfSelect = document.getElementById("turf_id");
    const sportSelect = document.getElementById("sport_id");
    const courtContainer = document.getElementById("courtContainer");
    const courtEmptyState = document.getElementById("courtEmptyState");

    function renderCourts() {
      if (!courtContainer || !courtEmptyState || !turfSelect || !sportSelect) {
        return;
      }

      const turfId = turfSelect.value;
      const sportId = sportSelect.value;

      courtContainer.innerHTML = "";

      if (!turfId || !sportId) {
        courtEmptyState.textContent = "Select turf and sport to view available courts.";
        courtEmptyState.style.display = "block";
        return;
      }

      const filteredCourts = allCourts.filter(function (court) {
        return String(court.turf_id) === turfId &&
          String(court.sport_id) === sportId &&
          court.status === "A";
      });

      if (filteredCourts.length === 0) {
        courtEmptyState.textContent = "No active courts found for the selected turf and sport.";
        courtEmptyState.style.display = "block";
        return;
      }

      courtEmptyState.style.display = "none";

      filteredCourts.forEach(function (court) {
        const wrapper = document.createElement("label");
        wrapper.className = "court-option";

        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.name = "court_ids[]";
        checkbox.value = court.court_id;
        checkbox.checked = selectedCourtIds.has(Number(court.court_id));

        const text = document.createElement("span");
        text.textContent = court.court_name;

        wrapper.appendChild(checkbox);
        wrapper.appendChild(text);
        courtContainer.appendChild(wrapper);
      });
    }

    if (turfSelect) {
      turfSelect.addEventListener("change", renderCourts);
    }

    if (sportSelect) {
      sportSelect.addEventListener("change", renderCourts);
    }

    renderCourts();

    document.querySelectorAll("input[type='number']").forEach(input => {
  input.addEventListener("input", function () {
    this.value = this.value.replace(/[^0-9]/g, '');
  });
});
  </script>
</body>
</html>
