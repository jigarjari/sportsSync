<?php
session_start();

$demoMessage = "";

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

    // Basic fields
    $tournament_name = $_POST['tournament_name'];
    $full_name = $_POST['full_name'];
    $mobile_number = $_POST['mobile_number'];
    $email = $_POST['email'];
    $age = $_POST['age'];

    $team_name = $_POST['team_name'];
    $captain_name = $_POST['captain_name'];
    $captain_mobile = $_POST['captain_mobile'];
    $captain_email = $_POST['captain_email'];

    $emergency_contact = $_POST['team_emergency_contact'];
    $player_list = $_POST['player_list'];
    $team_notes = $_POST['team_notes'];

    $accepted_terms = isset($_POST['accept_terms']) ? 1 : 0;

    // FILE UPLOADS
    function uploadFile($fileInputName) {
        if (!empty($_FILES[$fileInputName]['name'])) {
            $targetDir = "uploads/";
            $fileName = time() . "_" . basename($_FILES[$fileInputName]["name"]);
            $targetFile = $targetDir . $fileName;

            move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $targetFile);

            return $fileName;
        }
        return null;
    }

    $profile_photo = uploadFile("profile_photo");
    $team_logo = uploadFile("team_logo");

    // INSERT QUERY (prepared statement)
    $stmt = $conn->prepare("
        INSERT INTO tournament_registrations (
            tournament_name,
            full_name,
            mobile_number,
            email,
            age,
            profile_photo,
            team_name,
            captain_name,
            captain_mobile,
            captain_email,
            team_logo,
            emergency_contact,
            player_list,
            team_notes,
            accepted_terms
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssssisssssssssi",
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
        $demoMessage = "Registration saved successfully!";
    } else {
        $demoMessage = "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
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

     <form method="post" enctype="multipart/form-data">
  <div class="section-block">
    <h2 class="section-title">Tournament & Basic Details</h2>
    <div class="row g-3">

      <div class="col-md-6">
        <label for="tournament_name" class="form-label">Tournament Name</label>
        <select class="form-select" id="tournament_name" name="tournament_name">
          <option value="">Select Tournament</option>
          <option value="summer_cup">Summer Cup</option>
          <option value="champions_league">Champions League</option>
          <option value="night_league">Night League</option>
        </select>
      </div>

      <div class="col-md-6">
        <label for="full_name" class="form-label">Your Name</label>
        <input type="text" class="form-control" id="full_name" name="full_name" placeholder="Enter your name">
      </div>

      <div class="col-md-6">
        <label for="mobile_number" class="form-label">Mobile Number</label>
        <input type="tel" class="form-control" id="mobile_number" name="mobile_number" placeholder="Enter your mobile number">
      </div>

      <div class="col-md-6">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email">
      </div>

      <div class="col-md-4">
        <label for="age" class="form-label">Age</label>
        <input type="number" min="1" class="form-control" id="age" name="age" placeholder="Enter your age">
      </div>

      <div class="col-md-4">
        <label for="profile_photo" class="form-label">Profile Photo</label>
        <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept="image/*">
      </div>

    </div>
  </div>

  <div class="section-block">
    <h2 class="section-title">Team Details</h2>
    <div class="row g-3">

      <div class="col-md-6">
        <label for="team_name" class="form-label">Team Name</label>
        <input type="text" class="form-control" id="team_name" name="team_name" placeholder="Enter your team name">
      </div>

      <div class="col-md-6">
        <label for="captain_name" class="form-label">Captain Name</label>
        <input type="text" class="form-control" id="captain_name" name="captain_name" placeholder="Enter your captain name">
      </div>

      <div class="col-md-6">
        <label for="captain_mobile" class="form-label">Captain Mobile</label>
        <input type="tel" class="form-control" id="captain_mobile" name="captain_mobile" placeholder="Enter your captain mobile">
      </div>

      <div class="col-md-6">
        <label for="captain_email" class="form-label">Captain Email</label>
        <input type="email" class="form-control" id="captain_email" name="captain_email" placeholder="Enter your captain email">
      </div>

      <div class="col-md-4">
        <label for="team_logo" class="form-label">Team Logo</label>
        <input type="file" class="form-control" id="team_logo" name="team_logo" accept="image/*">
      </div>

      <div class="col-md-4">
        <label for="team_emergency_contact" class="form-label">Emergency Contact</label>
        <input type="tel" class="form-control" id="team_emergency_contact" name="team_emergency_contact" placeholder="Enter your Emergency number">
      </div>

      <div class="col-12">
        <label for="player_list" class="form-label">Player List</label>
        <textarea class="form-control" id="player_list" name="player_list" placeholder="Enter one player per line"></textarea>
      </div>

      <div class="col-12">
        <label for="team_notes" class="form-label">Team Notes</label>
        <textarea class="form-control" id="team_notes" name="team_notes" placeholder="Enter your team notes"></textarea>
      </div>

    </div>
  </div>

  <div class="section-block">
    <h2 class="section-title">Confirmation</h2>

    <label class="checkbox-line">
      <input type="checkbox" name="accept_terms" value="1">
      <span>I agree to the tournament rules.</span>
    </label>
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