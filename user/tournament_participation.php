<?php
session_start();

$demoMessage = "";
$errors = [];

// DB CONNECTION
$host = "localhost";
$user = "root";
$pass = "";
$db   = "turf_booking_system"; // CHANGE THIS

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// FORM SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    function clean($data) {
        return htmlspecialchars(trim($data));
    }

    $tournament_name = clean($_POST['tournament_name']);
    $full_name = clean($_POST['full_name']);
    $mobile_number = clean($_POST['mobile_number']);
    $email = clean($_POST['email']);
    $age = clean($_POST['age']);

    $team_name = clean($_POST['team_name']);
    $captain_name = clean($_POST['captain_name']);
    $captain_mobile = clean($_POST['captain_mobile']);
    $captain_email = clean($_POST['captain_email']);

    $emergency_contact = clean($_POST['team_emergency_contact']);
    $player_list = clean($_POST['player_list']);
    $team_notes = clean($_POST['team_notes']);

    $accepted_terms = isset($_POST['accept_terms']) ? 1 : 0;

    // VALIDATIONS

    if (empty($tournament_name)) $errors['tournament_name'] = "Required";

    if (empty($full_name) || !preg_match("/^[a-zA-Z ]+$/", $full_name)) {
        $errors['full_name'] = "Only letters allowed";
    }

    if (!preg_match("/^[0-9]{10}$/", $mobile_number)) {
        $errors['mobile_number'] = "Enter valid 10 digit number";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email";
    }

    if (!filter_var($age, FILTER_VALIDATE_INT) || $age < 10 || $age > 60) {
        $errors['age'] = "Invalid age";
    }

    if (empty($team_name)) $errors['team_name'] = "Required";

    if (!preg_match("/^[a-zA-Z ]+$/", $captain_name)) {
        $errors['captain_name'] = "Only letters allowed";
    }

    if (!preg_match("/^[0-9]{10}$/", $captain_mobile)) {
        $errors['captain_mobile'] = "Invalid number";
    }

    if (!filter_var($captain_email, FILTER_VALIDATE_EMAIL)) {
        $errors['captain_email'] = "Invalid email";
    }

    if (!preg_match("/^[0-9]{10}$/", $emergency_contact)) {
        $errors['team_emergency_contact'] = "Invalid number";
    }

    if (!$accepted_terms) {
        $errors['accept_terms'] = "You must accept terms";
    }

    // FILE VALIDATION (IMPORTANT)
    function uploadFile($input) {
        if (!empty($_FILES[$input]['name'])) {

            $allowed = ['image/jpeg','image/png','image/jpg'];
            if (!in_array($_FILES[$input]['type'], $allowed)) {
                return null;
            }

            if ($_FILES[$input]['size'] > 2 * 1024 * 1024) {
                return null;
            }

            $fileName = time() . "_" . basename($_FILES[$input]['name']);
            move_uploaded_file($_FILES[$input]['tmp_name'], "uploads/" . $fileName);
            return $fileName;
        }
        return null;
    }

    // ONLY INSERT IF NO ERRORS
    if (empty($errors)) {

        $profile_photo = uploadFile("profile_photo");
        $team_logo = uploadFile("team_logo");

        $stmt = $conn->prepare("INSERT INTO tournament_registrations 
        (tournament_name, full_name, mobile_number, email, age, profile_photo,
        team_name, captain_name, captain_mobile, captain_email, team_logo,
        emergency_contact, player_list, team_notes, accepted_terms)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("ssssisssssssssi",
            $tournament_name,
            $full_name,
            $mobile_number,
            $email,
            $age,
            $profile_photo,
            $team_name,
            $captain_name,
            $captain_mobile,
            $captain_email,
            $team_logo,
            $emergency_contact,
            $player_list,
            $team_notes,
            $accepted_terms
        );

        if ($stmt->execute()) {
            $demoMessage = "Registration successful!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tournament Participation</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="../whole.css" rel="stylesheet">
  <style>
    :root {
      --bg-main: #0e0f11;
      --card-bg: rgba(16, 16, 20, 0.94);
      --card-border: rgba(149, 38, 243, 0.32);
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
      width: min(1080px, calc(100% - 2rem));
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
      inset: auto -80px -90px auto;
      width: 240px;
      height: 240px;
      background: radial-gradient(circle, rgba(149, 38, 243, 0.34), transparent 70%);
      pointer-events: none;
    }

    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 8px 14px;
      border-radius: 999px;
      background: var(--accent-soft);
      color: #e3c7ff;
      font-size: 0.92rem;
      margin-bottom: 12px;
    }

    .hero-card h1 {
      margin: 0 0 8px;
      font-size: clamp(1.9rem, 4vw, 2.8rem);
      color: #ffffff;
    }

    .hero-card p {
      margin: 0;
      max-width: 760px;
      color: var(--text-muted);
      line-height: 1.6;
    }

    .form-card {
      padding: 28px;
    }

    .section-block + .section-block {
      margin-top: 28px;
      padding-top: 24px;
      border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .section-title {
      margin: 0 0 18px;
      font-size: 1.1rem;
      color: var(--accent);
      font-weight: 700;
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

    textarea.form-control {
      min-height: 120px;
      resize: vertical;
    }

    .mode-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
    }

    .mode-card {
      position: relative;
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 20px;
      padding: 18px;
      background: rgba(255, 255, 255, 0.03);
      transition: border-color 0.25s ease, background 0.25s ease, transform 0.25s ease;
      cursor: pointer;
    }

    .mode-card:hover {
      transform: translateY(-2px);
      border-color: rgba(149, 38, 243, 0.45);
    }

    .mode-card input {
      position: absolute;
      top: 16px;
      right: 16px;
      accent-color: var(--accent);
      transform: scale(1.1);
    }

    .mode-card.active {
      border-color: rgba(149, 38, 243, 0.7);
      background: rgba(149, 38, 243, 0.12);
      box-shadow: 0 0 0 1px rgba(149, 38, 243, 0.16) inset;
    }

    .mode-card h3 {
      margin: 0 0 8px;
      font-size: 1.1rem;
      color: #ffffff;
    }

    .mode-card p {
      margin: 0;
      color: var(--text-muted);
      line-height: 1.5;
    }

    .conditional-section {
      display: none;
    }

    .conditional-section.active {
      display: block;
    }

    .note-box {
      padding: 14px 16px;
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.08);
      color: var(--text-muted);
    }

    .checkbox-line {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 14px 16px;
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .checkbox-line input[type="checkbox"] {
      margin-top: 4px;
      accent-color: var(--accent);
      transform: scale(1.15);
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

      .mode-grid {
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
        Tournament Registration
      </div>
      <h1>Join the tournament your way</h1>
    </section>

    <section class="form-card">
      <?php if ($demoMessage !== ""): ?>
        <div class="alert alert-success mb-4" role="alert">
          <?php echo htmlspecialchars($demoMessage); ?>
        </div>
      <?php endif; ?>

    <form method="post" enctype="multipart/form-data" novalidate>

<div class="section-block">
<h2 class="section-title">Tournament & Basic Details</h2>
<div class="row g-3">

<!-- Tournament -->
<div class="col-md-6">
<label class="form-label">Tournament Name <span class="text-danger">*</span></label>
<select name="tournament_name"
class="form-select <?php echo isset($errors['tournament_name']) ? 'is-invalid' : ''; ?>">
<option value="">Select Tournament</option>
<option value="summer_cup" <?php if(($tournament_name ?? '')=='summer_cup') echo 'selected'; ?>>Summer Cup</option>
<option value="champions_league" <?php if(($tournament_name ?? '')=='champions_league') echo 'selected'; ?>>Champions League</option>
<option value="night_league" <?php if(($tournament_name ?? '')=='night_league') echo 'selected'; ?>>Night League</option>
</select>
<div class="invalid-feedback"><?php echo $errors['tournament_name'] ?? ''; ?></div>
</div>

<!-- Name -->
<div class="col-md-6">
<label class="form-label">Your Name <span class="text-danger">*</span></label>
<input type="text" name="full_name"
value="<?php echo htmlspecialchars($full_name ?? ''); ?>"
class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>">
<div class="invalid-feedback"><?php echo $errors['full_name'] ?? ''; ?></div>
</div>

<!-- Mobile -->
<div class="col-md-6">
<label class="form-label">Mobile Number <span class="text-danger">*</span></label>
<input type="tel" name="mobile_number" maxlength="10"
value="<?php echo htmlspecialchars($mobile_number ?? ''); ?>"
class="form-control <?php echo isset($errors['mobile_number']) ? 'is-invalid' : ''; ?>">
<div class="invalid-feedback"><?php echo $errors['mobile_number'] ?? ''; ?></div>
</div>

<!-- Email -->
<div class="col-md-6">
<label class="form-label">Email <span class="text-danger">*</span></label>
<input type="email" name="email"
value="<?php echo htmlspecialchars($email ?? ''); ?>"
class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>">
<div class="invalid-feedback"><?php echo $errors['email'] ?? ''; ?></div>
</div>

<!-- Age -->
<div class="col-md-4">
<label class="form-label">Age <span class="text-danger">*</span></label>
<input type="number" name="age" min="10" max="60"
value="<?php echo htmlspecialchars($age ?? ''); ?>"
class="form-control <?php echo isset($errors['age']) ? 'is-invalid' : ''; ?>">
<div class="invalid-feedback"><?php echo $errors['age'] ?? ''; ?></div>
</div>

<!-- Profile -->
<div class="col-md-4">
<label class="form-label">Profile Photo</label>
<input type="file" name="profile_photo" class="form-control">
</div>

</div>
</div>


<div class="section-block">
<h2 class="section-title">Team Details</h2>
<div class="row g-3">

<!-- Team -->
<div class="col-md-6">
<label class="form-label">Team Name <span class="text-danger">*</span></label>
<input type="text" name="team_name"
value="<?php echo htmlspecialchars($team_name ?? ''); ?>"
class="form-control <?php echo isset($errors['team_name']) ? 'is-invalid' : ''; ?>">
<div class="invalid-feedback"><?php echo $errors['team_name'] ?? ''; ?></div>
</div>

<!-- Captain -->
<div class="col-md-6">
<label class="form-label">Captain Name <span class="text-danger">*</span></label>
<input type="text" name="captain_name"
value="<?php echo htmlspecialchars($captain_name ?? ''); ?>"
class="form-control <?php echo isset($errors['captain_name']) ? 'is-invalid' : ''; ?>">
<div class="invalid-feedback"><?php echo $errors['captain_name'] ?? ''; ?></div>
</div>

<!-- Captain Mobile -->
<div class="col-md-6">
<label class="form-label">Captain Mobile <span class="text-danger">*</span></label>
<input type="tel" name="captain_mobile" maxlength="10"
value="<?php echo htmlspecialchars($captain_mobile ?? ''); ?>"
class="form-control <?php echo isset($errors['captain_mobile']) ? 'is-invalid' : ''; ?>">
<div class="invalid-feedback"><?php echo $errors['captain_mobile'] ?? ''; ?></div>
</div>

<!-- Captain Email -->
<div class="col-md-6">
<label class="form-label">Captain Email <span class="text-danger">*</span></label>
<input type="email" name="captain_email"
value="<?php echo htmlspecialchars($captain_email ?? ''); ?>"
class="form-control <?php echo isset($errors['captain_email']) ? 'is-invalid' : ''; ?>">
<div class="invalid-feedback"><?php echo $errors['captain_email'] ?? ''; ?></div>
</div>

<!-- Emergency -->
<div class="col-md-4">
<label class="form-label">Emergency Contact <span class="text-danger">*</span></label>
<input type="tel" name="team_emergency_contact" maxlength="10"
value="<?php echo htmlspecialchars($emergency_contact ?? ''); ?>"
class="form-control <?php echo isset($errors['team_emergency_contact']) ? 'is-invalid' : ''; ?>">
<div class="invalid-feedback"><?php echo $errors['team_emergency_contact'] ?? ''; ?></div>
</div>

<!-- Logo -->
<div class="col-md-4">
<label class="form-label">Team Logo</label>
<input type="file" name="team_logo" class="form-control">
</div>

<!-- Player List -->
<div class="col-12">
<label class="form-label">Player List</label>
<textarea name="player_list" class="form-control"><?php echo htmlspecialchars($player_list ?? ''); ?></textarea>
</div>

<!-- Notes -->
<div class="col-12">
<label class="form-label">Team Notes</label>
<textarea name="team_notes" class="form-control"><?php echo htmlspecialchars($team_notes ?? ''); ?></textarea>
</div>

</div>
</div>


<div class="section-block">
<h2 class="section-title">Confirmation</h2>

<label class="checkbox-line">
<input type="checkbox" name="accept_terms" value="1">
<span>I agree to the tournament rules <span class="text-danger">*</span></span>
</label>

<?php if(isset($errors['accept_terms'])): ?>
<div class="text-danger mt-2"><?php echo $errors['accept_terms']; ?></div>
<?php endif; ?>

</div>

<div class="section-block">
<button type="submit" class="btn btn-primary w-100">Submit Registration</button>
</div>

</form>
    </section>
  </div>

  <script>
    const modeCards = {
      team: document.getElementById("teamCard"),
      individual: document.getElementById("individualCard")
    };
    const sections = {
      team: document.getElementById("teamFields"),
      individual: document.getElementById("individualFields")
    };
    const radios = document.querySelectorAll('input[name="registration_type"]');

    document.querySelectorAll("input[type='tel'], input[type='number']").forEach(input => {
    input.addEventListener("input", function () {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
});

    function updateMode(selectedMode) {
      Object.keys(modeCards).forEach(function (mode) {
        modeCards[mode].classList.toggle("active", mode === selectedMode);
        sections[mode].classList.toggle("active", mode === selectedMode);
      });
    }

    radios.forEach(function (radio) {
      radio.addEventListener("change", function () {
        updateMode(radio.value);
      });
    });

    updateMode("team");
  </script>
</body>
</html>