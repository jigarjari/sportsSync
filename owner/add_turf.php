<?php
session_start();
include("../db.php");
$sportPrefixes = [
  1 => 'F', // Football
  2 => 'C', // Cricket
  3 => 'P', // Pickleball
  4 => 'T', // Tennis
];
//fetch city for dropdown
$cities = [];
$res = mysqli_query($conn, "SELECT city_id, city_name FROM citytb ORDER BY city_name");
while ($row = mysqli_fetch_assoc($res)) {
  $cities[] = $row;
}
if ($_SERVER['REQUEST_METHOD'] == "POST") {
  mysqli_begin_transaction($conn);
  try {
    if (empty($_POST['sports']) || empty($_POST['price'])) {
      throw new Exception("Sports and pricing required");
    }
    $start_time = $_POST['start_time']; // e.g. 06:00
    $end_time = $_POST['end_time'];   // e.g. 23:00

    $startTs = strtotime($start_time);
    $endTs = strtotime($end_time);

    if ($endTs <= $startTs) {
      $endTs += 86400; // next day
    }

    $turf_name = $_POST["turf_name"];
    $location = $_POST["turf_add"];
    $description = $_POST["description"];
    $owner_id = $_SESSION['user_id'];

    if (empty($_POST['city_id'])) {
      throw new Exception("City is required");
    }
    $city_id = (int) $_POST['city_id'];

    $latitude = (float) $_POST['latitude'];
    $longitude = (float) $_POST['longitude'];

    if (!$latitude || !$longitude) {
      throw new Exception("Please select turf location on map");
    }
    
    // =================Turf tb=====================
    $sql = "INSERT INTO turftb
(owner_id, turf_name, start_time, end_time, city_id, location, latitude, longitude, description)
VALUES (?,?,?,?,?,?,?,?,?)";


    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
      $stmt,
      "isssisdds",
      $owner_id,
      $turf_name,
      $start_time,
      $end_time,
      $city_id,
      $location,
      $latitude,
      $longitude,
      $description
    );

    mysqli_stmt_execute($stmt);


    $turf_id = mysqli_insert_id($conn);
    //amenities mapped for turd
    if (!empty($_POST['amenities'])) {
      foreach ($_POST['amenities'] as $amenity_id) {
        $sql = "INSERT INTO turf_amenitiestb (turf_id, amenity_id) VALUES (?,?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $turf_id, $amenity_id);
        mysqli_stmt_execute($stmt);
      }
    }
    //sports mapped for turf
    foreach ($_POST['sports'] as $sport_id) {
      if (
        !isset($_POST['courts'][$sport_id]) ||
        !is_numeric($_POST['courts'][$sport_id]) ||
        $_POST['courts'][$sport_id] < 1
      ) {
        throw new Exception("Invalid number of courts for sport $sport_id");
      }

      $courts = (int) $_POST['courts'][$sport_id];

      $sql = "INSERT INTO turf_sportstb (turf_id, sport_id, no_of_courts)
          VALUES (?,?,?)";

      $stmt = mysqli_prepare($conn, $sql);
      mysqli_stmt_bind_param($stmt, "iii", $turf_id, $sport_id, $courts);
      mysqli_stmt_execute($stmt);

      // ================= TURF COURTS =================
      if (!isset($sportPrefixes[$sport_id])) {
        throw new Exception("Court prefix not defined for sport $sport_id");
      }
      $prefix = $sportPrefixes[$sport_id];
      for ($i = 1; $i <= $courts; $i++) {

        $courtName = $prefix . $i; // C1, C2, F1...

        $sql = "INSERT INTO turf_courtstb (turf_id, sport_id, court_name, status)
          VALUES (?,?,?, 'A')";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iis", $turf_id, $sport_id, $courtName);
        mysqli_stmt_execute($stmt);
      }
    }
    // =================Turf slot tb=================
//for weekdays
    foreach ($_POST['sports'] as $sport_id) {
      if (!isset($_POST['price'][$sport_id])) {
        throw new Exception("Pricing missing for sport $sport_id");
      }

      if (empty($_POST['price'][$sport_id]['weekday'])) {
        throw new Exception("Weekday price missing for sport $sport_id");
      }

      for ($t = $startTs; $t < $endTs; $t += 3600) {

        $slotStart = date("H:i", $t);
        $slotEnd = date("H:i", min($t + 3600, $endTs));

        $hour = (int) date("H", $t);
        if ($hour < 12) {
          $price = $_POST['price'][$sport_id]['weekday']['morning'];
        } elseif ($hour < 18) {
          $price = $_POST['price'][$sport_id]['weekday']['evening'];
        } else {
          $price = $_POST['price'][$sport_id]['weekday']['night'];
        }
        if (!is_numeric($price) || $price <= 0) {
          throw new Exception("Invalid price");
        }

        $sql = "INSERT INTO turf_price_slotstb
            (turf_id, sport_id, start_time, end_time, price_per_hour, is_weekend)
            VALUES (?,?,?,?,?,0)";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
          $stmt,
          "iissd",
          $turf_id,
          $sport_id,
          $slotStart,
          $slotEnd,
          $price
        );
        mysqli_stmt_execute($stmt);
      }
    }
    //for weekend
    foreach ($_POST['sports'] as $sport_id) {

      if (empty($_POST['price'][$sport_id]['weekend'])) {
        continue;
      }
      if (!isset($_POST['price'][$sport_id])) {
        throw new Exception("Pricing missing for sport $sport_id");
      }
      for ($t = $startTs; $t < $endTs; $t += 3600) {

        $slotStart = date("H:i", $t);
        $slotEnd = date("H:i", min($t + 3600, $endTs));
        $hour = (int) date("H", $t);

        if ($hour < 12) {
          $price = $_POST['price'][$sport_id]['weekend']['morning'];
        } elseif ($hour < 18) {
          $price = $_POST['price'][$sport_id]['weekend']['evening'];
        } else {
          $price = $_POST['price'][$sport_id]['weekend']['night'];
        }

        if (!is_numeric($price) || $price <= 0) {
          continue;//this if weekend price not provided
          throw new Exception("Invalid weekend price");
        }

        $sql = "INSERT INTO turf_price_slotstb
            (turf_id, sport_id, start_time, end_time, price_per_hour, is_weekend)
            VALUES (?,?,?,?,?,1)";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
          $stmt,
          "iissd",
          $turf_id,
          $sport_id,
          $slotStart,
          $slotEnd,
          $price
        );
        mysqli_stmt_execute($stmt);
      }
    }
    //hot hours
// HOT HOURS (SAFE + FINAL)
    foreach ($_POST['sports'] as $sport_id) {

      if (empty($_POST['hot'][$sport_id]) || !is_array($_POST['hot'][$sport_id])) {
        continue;
      }

      foreach ($_POST['hot'][$sport_id] as $hot) {

        // Skip incomplete rows
        if (
          empty($hot['start']) ||
          empty($hot['end']) ||
          !isset($hot['price']) ||
          $hot['price'] === ''
        ) {
          continue;
        }

        $baseDate = date("Y-m-d", $startTs);
        $hotStart = strtotime($baseDate . " " . $hot['start']);
        $hotEnd = strtotime($baseDate . " " . $hot['end']);

        if ($hotEnd <= $hotStart) {
          $hotEnd += 86400;
        }

        // Must be hour-aligned
        if (($hotEnd - $hotStart) % 3600 !== 0) {
          throw new Exception("Hot hour must be full-hour based");
        }

        $hotPrice = (float) $hot['price'];

        if ($hotPrice < 0) {
          throw new Exception("Invalid hot hour price");
        }

        // Must be inside operating hours
        if ($hotStart < $startTs || $hotEnd > $endTs) {
          throw new Exception("Hot hour outside operating time");
        }

        // Update each affected hour slot
        for ($t = $hotStart; $t < $hotEnd; $t += 3600) {

          $slotStart = date("H:i", $t);
          $slotEnd = date("H:i", min($t + 3600, $endTs));
          

          $sql = "UPDATE turf_price_slotstb
                    SET price_per_hour = ?
                    WHERE turf_id = ?
                    AND sport_id = ?
                    AND start_time = ?
                    AND end_time = ?";

          $stmt = mysqli_prepare($conn, $sql);
          mysqli_stmt_bind_param(
            $stmt,
            "diiss",
            $hotPrice,
            $turf_id,
            $sport_id,
            $slotStart,
            $slotEnd
          );
          if (mysqli_stmt_affected_rows($stmt) === 0) {
            error_log("HOT HOUR NOT APPLIED: $slotStart - $slotEnd | Sport $sport_id");
          }

        }
      }
    }


    // =================Turf image tb=================
    if (
      isset($_FILES['turf_images']) &&
      is_array($_FILES['turf_images']['name']) &&
      count($_FILES['turf_images']['name']) > 0 &&
      $_FILES['turf_images']['name'][0] !== ''
    ) {

      foreach ($_FILES['turf_images']['name'] as $key => $img_name) {

        $tmp_name = $_FILES['turf_images']['tmp_name'][$key];

        $folder = "turf_images/";
        $newName = uniqid("turf_", true) . "_" . basename($img_name);
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

        if (!in_array(mime_content_type($tmp_name), $allowed)) {
          throw new Exception("Invalid image type");
        }

        move_uploaded_file($tmp_name, $folder . $newName);

        $sql3 = "INSERT INTO turf_imagestb (turf_id, image_path)
                 VALUES (?, ?)";

        $stmt3 = mysqli_prepare($conn, $sql3);
        mysqli_stmt_bind_param($stmt3, "is", $turf_id, $newName);
        mysqli_stmt_execute($stmt3);
      }
    }
    mysqli_commit($conn);
    //echo "<script>alert(\"success\")</script>";
    $success = true;
  } catch (Exception $e) {
    mysqli_rollback($conn);
    //echo "<script>alert(\"not ok\")</script>";
    die("Error occurred: " . $e->getMessage());
  }

}

?>
<!DOCTYPE html>
<html>

<head>
  <title>Vendor Turf Registration</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

  <style>
        /* ================= ROOT THEME ================= */
    :root {
      --bg-main: #050914;
      --bg-gradient: radial-gradient(circle at top, #0f1b3d, #050914);
      --card-glass: rgba(15, 23, 42, 0.78);
      --accent-purple: #9526F3;        /* primary blue */
      --accent-purple-dark: #9526f359;   /* hover / depth */
      --accent-purple-highlight: #9526F3;      /* highlight only */
      --text-main: #ffffff;
      --text-muted: #94a3b8;
    }

    /* ================= PAGE BACKGROUND ================= */
    body.vendor-turf-page {
      min-height: 100vh;
      background: var(--bg-gradient);
      font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
      color: var(--text-main);
    }

    /* ================= MAIN CARD ================= */
    .vendor-turf-page .form-container {
      background: var(--card-glass);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-radius: 22px;
      max-width: 540px;
      margin: 70px auto;
      padding: 44px;
      box-shadow:
        0 30px 70px rgba(0, 0, 0, 0.65),
        inset 0 0 0 1px rgba(255, 255, 255, 0.06);
    }

    /* ================= HEADINGS ================= */
    .vendor-turf-page h2 {
      text-align: center;
      font-size: 26px;
      font-weight: 700;
      letter-spacing: 0.4px;
      color: var(--accent-purple);
      margin-bottom: 36px;
    }

    /* ================= LABELS ================= */
    .vendor-turf-page label {
      font-size: 13px;
      font-weight: 500;
      color: var(--text-main);
      margin-bottom: 6px;
    }

    /* ================= INPUTS ================= */
    .vendor-turf-page .form-control,
    .vendor-turf-page textarea,
    .vendor-turf-page select {
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: var(--text-main);
      border-radius: 12px;
      padding: 10px 12px;
    }

    .vendor-turf-page .form-control::placeholder,
    .vendor-turf-page textarea::placeholder {
      color: #a5a5a5;
      opacity: 1;
    }

    .vendor-turf-page select[name="city_id"] {
      color: #a5a5a5;
    }

    .vendor-turf-page select[name="city_id"] option {
      color: #000000;
      background: #ffffff;
    }

    .vendor-turf-page .form-control:focus,
    .vendor-turf-page textarea:focus,
    .vendor-turf-page select:focus {
      outline: none;
      border-color: var(--accent-purple);
      box-shadow: 0 0 0 2px #9526f359;
      background: rgba(255, 255, 255, 0.08);
    }

    /* ================= CHECKBOXES ================= */
    .vendor-turf-page input[type="checkbox"] {
      accent-color: var(--accent-purple);
      transform: scale(1.05);
      margin-right: 6px;
    }

    /* ================= MAP ================= */
    #map {
      border-radius: 16px;
      border: 1px solid #ffffff26;
    }

    /* ================= PRICE BOX ================= */
    .price-box {
      background: rgba(255, 255, 255, 0.05);
      border-radius: 18px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      padding: 20px;
    }

    .price-box h6 {
      font-size: 15px;
      font-weight: 600;
      color: var(--accent-purple-highlight);
      margin-bottom: 14px;
    }

    /* ================= HOT HOUR ================= */
    .hotHourRow input {
      font-size: 13px;
    }

    /* ================= PRIMARY BUTTON ================= */
    .vendor-turf-page .btn-custom {
      background: linear-gradient(
        135deg,
        var(--accent-purple),
        var(--accent-purple-dark)
      );
      border: none;
      color: #020617;
      font-weight: 700;
      letter-spacing: 0.4px;
      padding: 13px;
      border-radius: 16px;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .vendor-turf-page .btn-custom:hover {
      transform: translateY(-1px);
      box-shadow: 0 14px 35px #9526F3;
    }

    /* ================= SECONDARY BUTTON ================= */
    .btn-outline-warning {
      border-color: var(--accent-purple-highlight);
      color: var(--text-main);
    }

    .btn-outline-warning:hover {
      background: var(--accent-purple-highlight);
      color: #111;
      border-color: #9526F3;
    }

    /* ================= WARNINGS ================= */
    .warning {
      color: #9526F3;
      font-weight: 600;
    }

    .time-row {
      display: flex;
      gap: 15px;
    }

    .hot-hour-row {
      display: flex;
      gap: 8px;
      margin-bottom: 8px;
    }

    .add-hot-btn {
      font-size: 13px;
      color: #9526F3;
      cursor: pointer;
    }

    .back-btn{
      background-color: transparent;
      border: 2px solid #9526F3; 
      padding: 10px 26px; 
      border-radius: 25px; 
      color: #ffffff;
    }

    /* ===== SUCCESS POPUP ===== */
.popup-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(5, 9, 20, 0.75);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.popup-box {
  background: rgba(15, 23, 42, 0.85);
  border-radius: 20px;
  padding: 30px 40px;
  text-align: center;
  box-shadow: 0 20px 60px rgba(0,0,0,0.6);
  border: 1px solid rgba(255,255,255,0.08);
  animation: popupFade 0.3s ease;
}

.popup-icon {
  font-size: 40px;
  color: #9526F3;
  margin-bottom: 10px;
}

.popup-box h3 {
  color: #ffffff;
  margin-bottom: 10px;
}

.popup-box p {
  color: #94a3b8;
  font-size: 14px;
}

.popup-btn {
  margin-top: 20px;
  padding: 10px 25px;
  border-radius: 12px;
  border: none;
  background: linear-gradient(135deg, #9526F3, #9526f359);
  color: #020617;
  font-weight: 600;
  cursor: pointer;
}

.popup-btn:hover {
  box-shadow: 0 10px 30px #9526F3;
}

/* Animation */
@keyframes popupFade {
  from {
    transform: scale(0.9);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

@media (max-width: 768px) {
  body.vendor-turf-page {
    padding: 16px 12px 28px;
  }

  .vendor-turf-page .form-container {
    margin: 0 auto;
    padding: 24px 16px;
    border-radius: 18px;
  }

  .vendor-turf-page h2 {
    font-size: 1.45rem;
    margin-bottom: 24px;
  }

  .time-row,
  .hot-hour-row {
    flex-direction: column;
    gap: 10px;
  }

  .time-row > *,
  .hot-hour-row > * {
    width: 100%;
  }

  .popup-box {
    width: min(100%, 340px);
    padding: 24px 18px;
  }
}
  </style>
</head>

<body class="vendor-turf-page">

  <div class="form-container">
    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">

    <div class="top-bar">
        <div class="container-xl">
          <button class="back-btn" onclick="history.back()">← Back</button>
          <h2>Turf Details</h2>
        </div>
    </div>

      <!-- Turf Name -->
      <div class="mb-3">
        <label><span class="warning">*</span> Turf Name</label>
        <input type="text" id="turf_name" name="turf_name" class="form-control" placeholder="Enter your Turf Name">
      </div><br>

      <!-- Address -->
      <div class="mb-3">
        <label><span class="warning">*</span> City</label>
        <select name="city_id" class="form-control" required>
          <option value="">-- Select City --</option>
          <?php foreach ($cities as $city): ?>
            <option value="<?= $city['city_id'] ?>">
              <?= htmlspecialchars($city['city_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label for="address" class="form-label" style="display: block; margin-bottom: 5px;"><span class="warning">*
          </span>Turf Address:</label>
        <label><span class="warning">*</span> Pick Turf Location on Map</label><br>
        <button type="button" id="useLocation" class="btn btn-sm btn-outline-warning mb-2">📍 Use My Current
          Location</button>
        <div id="map" style="height:300px;border-radius:10px;"></div><br>
        <textarea id="turf_add" name="turf_add" rows="4" cols="40" class="form-control"
          placeholder=" Road, landmark, area (for clarity only)"></textarea>
      </div>

      <input type="hidden" name="latitude" id="latitude" required>
      <input type="hidden" name="longitude" id="longitude" required>
      <br>

      <!--image upload-->
      <div class="mb-3">
        <label for="imageUpload"><span class="warning">* </span>Upload an Image:</label>
        <input type="file" id="turf_images" name="turf_images[]" multiple accept="image/*">
      </div><br>

      <!--description-->
      <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea id="description" name="description" rows="3" class="form-control" placeholder="About your turf"
          required></textarea><br>
      </div>
      <!--aminities-->
      <div class="mb-3">
        <label class="form-label">Select your Amenities:</label><br>
        <input type="checkbox" name="amenities[]" value="1"> Cafeteria<br>
        <input type="checkbox" name="amenities[]" value="2"> Washroom<br>
        <input type="checkbox" name="amenities[]" value="3"> Sitting Area<br>
        <input type="checkbox" name="amenities[]" value="4"> Sports Equipment<br>

      </div>
      <br>

      <!-- Operating Time -->
      <div class="mb-3">
        <label><span class="warning">*</span> Operating Time</label>
        <div class="time-row">
          <input type="time" class="form-control" name="start_time" required>
          <input type="time" class="form-control" name="end_time" required>
        </div>
      </div><br>

      <!-- Sports -->
      <div class="mb-3">
        <label><span class="warning">*</span> Sports Available</label><br>
        <input type="checkbox" class="sportCheck" name="sports[]" value="1"> Football
        <input type="checkbox" class="sportCheck" name="sports[]" value="2"> Cricket
        <input type="checkbox" class="sportCheck" name="sports[]" value="3"> PickleBall
        <input type="checkbox" class="sportCheck" name="sports[]" value="4"> Tennis
      </div>

      <!-- Pricing Cards will appear here -->
      <div id="pricingContainer"></div>

      <template id="pricingTemplate">
        <div class="price-box mt-4 p-3 border rounded">

          <h6 class="sport-title"></h6>
          <div class="mb-2">
            <label class="small">Number of Courts</label>
            <input type="number" class="form-control" min="1" value="1" name="courts[SPORT_ID]" required>
          </div>

          <!-- Weekday Prices -->
          <label>Weekday Prices</label>
          <input type="number" class="form-control mb-2" placeholder="Morning ₹"
            name="price[SPORT_ID][weekday][morning]" required>
          <input type="number" class="form-control mb-2" placeholder="Evening ₹"
            name="price[SPORT_ID][weekday][evening]" required>
          <input type="number" class="form-control mb-2" placeholder="Night ₹" name="price[SPORT_ID][weekday][night]"
            required>
          <!-- Weekend -->
          <div class="mt-2">
            <input type="checkbox" class="weekendToggle">
            <label>Different weekend prices</label>
          </div>

          <div class="weekendPrices mt-2" style="display:none;">
            <input type="number" class="form-control mb-2" placeholder="Weekend Morning ₹"
              name="price[SPORT_ID][weekend][morning]">
            <input type="number" class="form-control mb-2" placeholder="Weekend Evening ₹"
              name="price[SPORT_ID][weekend][evening]">
            <input type="number" class="form-control" placeholder="Weekend Night ₹"
              name="price[SPORT_ID][weekend][night]">
          </div>

          <hr class="text-secondary">

          <!-- HOT HOUR -->
          <div class="mt-2">
            <input type="checkbox" class="hotHourToggle">
            <label><strong>Enable Hot Hour Pricing</strong></label>
          </div>

          <div class="hotHourBox mt-2" style="display:none;">

            <div class="hotHourList"></div>

            <button type="button" class="btn btn-sm btn-outline-warning addHotHour mt-2">
              + Add Hot Hour
            </button>

          </div>

        </div>

        <template class="hotHourRowTemplate">
          <div class="d-flex gap-2 align-items-end hotHourRow mb-2">
            <input type="time" class="form-control" name="hot[SPORT_ID][][start]">
            <input type="time" class="form-control" name="hot[SPORT_ID][][end]">
            <input type="number" class="form-control" name="hot[SPORT_ID][][price]">
            <button type="button" class="btn btn-danger btn-sm removeHotHour">✕</button>
          </div>
        </template>

      </template>

      <button type="submit" class="btn btn-custom w-100 mt-4">Register</button>

    </form>
  </div>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    const sportChecks = document.querySelectorAll('.sportCheck');
    const container = document.getElementById('pricingContainer');
    const template = document.getElementById('pricingTemplate');

    sportChecks.forEach(check => {
      check.addEventListener('change', function () {

        if (this.checked) {
          const clone = template.content.cloneNode(true);
          const box = clone.querySelector('.price-box');

          const sportId = this.value;

          box.dataset.sport = sportId;
          clone.querySelector('.sport-title').innerText =
            this.nextSibling.textContent.trim() + " Pricing";

          clone.querySelectorAll('input').forEach(input => {
            if (input.name) {
              input.name = input.name.replace('SPORT_ID', sportId);
            }
          });

          // Weekend toggle
          const weekendToggle = clone.querySelector('.weekendToggle');
          const weekendBox = clone.querySelector('.weekendPrices');

          weekendToggle.addEventListener('change', function () {
            weekendBox.style.display = this.checked ? "block" : "none";
          });

          // HOT HOUR MULTI
          const hotToggle = clone.querySelector('.hotHourToggle');
          const hotBox = clone.querySelector('.hotHourBox');
          const addBtn = clone.querySelector('.addHotHour');
          const list = clone.querySelector('.hotHourList');
          const rowTemplate = clone.querySelector('.hotHourRowTemplate');

          hotToggle.addEventListener('change', function () {
            hotBox.style.display = this.checked ? "block" : "none";
            if (this.checked && list.children.length === 0) {
              addHotHour();
            }
          });

          function addHotHour() {
            const row = rowTemplate.content.cloneNode(true);
            row.querySelector('.removeHotHour')
              .addEventListener('click', function () {
                this.closest('.hotHourRow').remove();
              });
            row.querySelectorAll('input').forEach(input => {
              if (input.name) {
                input.name = input.name.replace('SPORT_ID', sportId);
              }
            });

            list.appendChild(row);
          }

          addBtn.addEventListener('click', addHotHour);

          container.appendChild(clone);

        }
        else {
          document
            .querySelectorAll(`[data-sport="${this.value}"]`)
            .forEach(el => el.remove());
        }
      });
    });


    const defaultLatLng = [21.1702, 72.8311]; // Surat

    const map = L.map('map').setView(defaultLatLng, 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    }).addTo(map);

    const marker = L.marker(defaultLatLng, { draggable: true }).addTo(map);

    document.getElementById('latitude').value = defaultLatLng[0];
    document.getElementById('longitude').value = defaultLatLng[1];

    marker.on('dragend', function () {
      const pos = marker.getLatLng();
      document.getElementById('latitude').value = pos.lat;
      document.getElementById('longitude').value = pos.lng;
    });

    map.on('click', function (e) {
      marker.setLatLng(e.latlng);
      document.getElementById('latitude').value = e.latlng.lat;
      document.getElementById('longitude').value = e.latlng.lng;
    });

    document.getElementById('useLocation').addEventListener('click', function () {

      if (!navigator.geolocation) {
        alert("Geolocation not supported by your browser");
        return;
      }

      navigator.geolocation.getCurrentPosition(
        function (position) {

          const lat = position.coords.latitude;
          const lng = position.coords.longitude;

          map.setView([lat, lng], 16);
          marker.setLatLng([lat, lng]);

          document.getElementById('latitude').value = lat;
          document.getElementById('longitude').value = lng;

        },
        function () {
          alert("Location permission denied or unavailable");
        }
      );
    });
  </script>
  <script>
function closePopup() {
  document.getElementById('successPopup').style.display = 'none';
  window.location.href = "my_turfs.php"; // redirect after success (optional)
}
</script>
  <?php if (!empty($success)): ?>
<div id="successPopup" class="popup-overlay">
  <div class="popup-box">
    <div class="popup-icon">✔</div>
    <h3>Turf Successfully Added</h3>
    <p>Your turf has been registered and is now live.</p>
    <button onclick="closePopup()" class="popup-btn">Continue</button>
  </div>
</div>
<?php endif; ?>

</body>

</html>
