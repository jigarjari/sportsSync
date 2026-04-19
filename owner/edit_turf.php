
<?php

include("../db.php");

  if (!isset($_GET['turf_id']) || !is_numeric($_GET['turf_id'])) {
    die('Invalid turf ID'); 
}
$turf_id = (int) $_GET['turf_id'];

$stmt = $conn->prepare("SELECT * FROM turftb WHERE turf_id = ?");
$stmt->bind_param("i", $turf_id);
$stmt->execute();

$result = $stmt->get_result();
$turf = $result->fetch_assoc();

if (!$turf) {
    die('Turf not found');
}
// ----------------amenities-----------------
$amenityIds = [];

$stmt2 = $conn->prepare("SELECT amenity_id FROM turf_amenitiestb WHERE turf_id = ?");
$stmt2->bind_param("i", $turf_id);
$stmt2->execute();
$result2 = $stmt2->get_result();

while ($row = $result2->fetch_assoc()) {
    $amenityIds[] = $row['amenity_id'];
}

$sportsData = [];
$courts = [];

$stmt3 = $conn->prepare(
    "SELECT sport_id, no_of_courts 
     FROM turf_sportstb 
     WHERE turf_id = ?"
);
$stmt3->bind_param("i", $turf_id);
$stmt3->execute();
$result3 = $stmt3->get_result();

while ($row = $result3->fetch_assoc()) {
    $sportsData[] = $row['sport_id'];
    $courts[] = $row['no_of_courts'];
}

//city
$cities = [];

$res = $conn->query("SELECT city_id, city_name FROM citytb ORDER BY city_name");

while ($row = $res->fetch_assoc()) {
    $cities[] = $row;
}


$priceData = [];

$q = $conn->prepare("
    SELECT sport_id, start_time, price_per_hour
    FROM turf_price_slotstb
    WHERE turf_id = ? AND is_weekend = 0
");
$q->bind_param("i", $turf_id);
$q->execute();
$res = $q->get_result();

while ($row = $res->fetch_assoc()) {

    $hour = (int) substr($row['start_time'], 0, 2);

    if ($hour < 12) {
        $slot = 'morning';
    } elseif ($hour < 18) {
        $slot = 'evening';
    } else {
        $slot = 'night';
    }

    // store once per slot
    if (!isset($priceData[$row['sport_id']]['weekday'][$slot])) {
        $priceData[$row['sport_id']]['weekday'][$slot] = $row['price_per_hour'];
    }
}

$images = [];

$stmtImg = $conn->prepare("SELECT image_id, image_path FROM turf_imagestb WHERE turf_id=?");
$stmtImg->bind_param("i", $turf_id);
$stmtImg->execute();

$resImg = $stmtImg->get_result();

while ($row = $resImg->fetch_assoc()) {
    $images[] = $row;
}
?>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    mysqli_begin_transaction($conn);

    try {

        // ===== VALIDATION =====
        if (empty($_POST['turf_name']) || empty($_POST['city_id'])) {
            throw new Exception("Required fields missing");
        }

        if (empty($_POST['sports'])) {
            throw new Exception("Select at least one sport");
        }

        // ===== BASIC DATA =====
        $turf_id = (int) $_POST['turf_id'];
        $turf_name = $_POST['turf_name'];
        $city_id = (int) $_POST['city_id'];
        $location = $_POST['turf_add'];
        $latitude = (float) $_POST['latitude'];
        $longitude = (float) $_POST['longitude'];
        $description = $_POST['description'];

        // ===== UPDATE MAIN TABLE =====
        $stmt = $conn->prepare("
            UPDATE turftb 
            SET turf_name=?, city_id=?, location=?, latitude=?, longitude=?, description=?
            WHERE turf_id=?
        ");

        $stmt->bind_param(
            "sisddsi",
            $turf_name,
            $city_id,
            $location,
            $latitude,
            $longitude,
            $description,
            $turf_id
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        // ===== DELETE OLD RELATED DATA (SAFE) =====
        $tables = [
            "turf_amenitiestb",
            "turf_sportstb",
            "turf_courtstb",
            "turf_price_slotstb"
        ];

        foreach ($tables as $table) {
            $stmt = $conn->prepare("DELETE FROM $table WHERE turf_id=?");
            $stmt->bind_param("i", $turf_id);
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
        }

        // ===== AMENITIES =====
        if (!empty($_POST['amenities'])) {

            $stmt = $conn->prepare("
                INSERT INTO turf_amenitiestb (turf_id, amenity_id) 
                VALUES (?,?)
            ");

            foreach ($_POST['amenities'] as $a) {
                $a = (int)$a;
                $stmt->bind_param("ii", $turf_id, $a);
                if (!$stmt->execute()) {
                    throw new Exception($stmt->error);
                }
            }
        }

        // ===== SPORTS + COURTS =====
        $prefix = [1 => 'F', 2 => 'C', 3 => 'P', 4 => 'T'];

        $sportStmt = $conn->prepare("
            INSERT INTO turf_sportstb (turf_id, sport_id, no_of_courts)
            VALUES (?,?,?)
        ");

        $courtStmt = $conn->prepare("
            INSERT INTO turf_courtstb (turf_id, sport_id, court_name, status)
            VALUES (?,?,?, 'A')
        ");

        foreach ($_POST['sports'] as $sport_id) {

            $sport_id = (int)$sport_id;

            if (!isset($_POST['courts'][$sport_id])) {
                throw new Exception("Courts missing for sport $sport_id");
            }

            $courts = (int)$_POST['courts'][$sport_id];

            $sportStmt->bind_param("iii", $turf_id, $sport_id, $courts);
            if (!$sportStmt->execute()) {
                throw new Exception($sportStmt->error);
            }

            for ($i = 1; $i <= $courts; $i++) {

                $name = ($prefix[$sport_id] ?? 'X') . $i;

                $courtStmt->bind_param("iis", $turf_id, $sport_id, $name);
                if (!$courtStmt->execute()) {
                    throw new Exception($courtStmt->error);
                }
            }
        }

        // ===== PRICING =====
        if (empty($_POST['start_time']) || empty($_POST['end_time'])) {
            throw new Exception("Time required");
        }

        $start = strtotime($_POST['start_time']);
        $end = strtotime($_POST['end_time']);

        if ($end <= $start) $end += 86400;

        $priceStmt = $conn->prepare("
            INSERT INTO turf_price_slotstb
            (turf_id, sport_id, start_time, end_time, price_per_hour, is_weekend)
            VALUES (?,?,?,?,?,0)
        ");

        foreach ($_POST['sports'] as $sport_id) {

            $sport_id = (int)$sport_id;

            for ($t = $start; $t < $end; $t += 3600) {

                $hour = (int)date("H", $t);

                if ($hour < 12) {
                    $price = $_POST['price'][$sport_id]['weekday']['morning'] ?? 0;
                } elseif ($hour < 18) {
                    $price = $_POST['price'][$sport_id]['weekday']['evening'] ?? 0;
                } else {
                    $price = $_POST['price'][$sport_id]['weekday']['night'] ?? 0;
                }

                $slotStart = date("H:i", $t);
                $slotEnd = date("H:i", $t + 3600);

                $priceStmt->bind_param(
                    "iissd",
                    $turf_id,
                    $sport_id,
                    $slotStart,
                    $slotEnd,
                    $price
                );

                if (!$priceStmt->execute()) {
                    throw new Exception($priceStmt->error);
                }
            }
        }

        // ===== IMAGES =====
        // if (!empty($_FILES['turf_images']['name'][0])) {

        //     $stmt = $conn->prepare("DELETE FROM turf_imagestb WHERE turf_id=?");
        //     $stmt->bind_param("i", $turf_id);
        //     $stmt->execute();

        //     foreach ($_FILES['turf_images']['name'] as $key => $img) {

        //         $tmp = $_FILES['turf_images']['tmp_name'][$key];

        //         if (!is_uploaded_file($tmp)) continue;

        //         $mime = mime_content_type($tmp);
        //         $allowed = ['image/jpeg', 'image/png', 'image/webp'];

        //         if (!in_array($mime, $allowed)) {
        //             throw new Exception("Invalid image type");
        //         }

        //         $newName = uniqid() . "_" . basename($img);
        //         move_uploaded_file($tmp, "turf_images/" . $newName);

        //         $stmt = $conn->prepare("
        //             INSERT INTO turf_imagestb (turf_id, image_path)
        //             VALUES (?,?)
        //         ");

        //         $stmt->bind_param("is", $turf_id, $newName);

        //         if (!$stmt->execute()) {
        //             throw new Exception($stmt->error);
        //         }
        //     }
        // }
        // ===== IMAGES =====

// 1. DELETE selected images
if (!empty($_POST['delete_images'])) {

    $ids = explode(',', $_POST['delete_images']);

    $stmt = $conn->prepare("DELETE FROM turf_imagestb WHERE image_id=?");

    foreach ($ids as $id) {

        $id = (int)$id;

        // delete file from folder
        $res = $conn->query("SELECT image_path FROM turf_imagestb WHERE image_id=$id");
        if ($row = $res->fetch_assoc()) {
            $file = "turf_images/" . $row['image_path'];
            if (file_exists($file)) unlink($file);
        }

        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
    }
}


// 2. INSERT new images
if (!empty($_FILES['turf_images']['name'][0])) {

    foreach ($_FILES['turf_images']['name'] as $key => $img) {

        $tmp = $_FILES['turf_images']['tmp_name'][$key];

        if (!is_uploaded_file($tmp)) continue;

        $mime = mime_content_type($tmp);
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mime, $allowed)) {
            throw new Exception("Invalid image type");
        }

        $newName = uniqid() . "_" . basename($img);

        if (!move_uploaded_file($tmp, "turf_images/" . $newName)) {
            throw new Exception("Image upload failed");
        }

        $stmt = $conn->prepare("
            INSERT INTO turf_imagestb (turf_id, image_path)
            VALUES (?,?)
        ");

        $stmt->bind_param("is", $turf_id, $newName);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
    }
}
        mysqli_commit($conn);
        $success = true;

    } catch (Exception $e) {

        mysqli_rollback($conn);
        die("Error: " . $e->getMessage());
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
      --accent-blue: #9526F3;
      /* primary blue */
      --accent-blue-dark: #9526f359;
      /* hover / depth */
      --accent-orange: #9526F3;
      /* highlight only */
      --text-main: #e5e7eb;
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
      color: var(--accent-blue);
      margin-bottom: 36px;
    }

    /* ================= LABELS ================= */
    .vendor-turf-page label {
      font-size: 13px;
      font-weight: 500;
      color: var(--text-muted);
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

    .vendor-turf-page .form-control:focus,
    .vendor-turf-page textarea:focus,
    .vendor-turf-page select:focus {
      outline: none;
      border-color: var(--accent-blue);
      box-shadow: 0 0 0 2px #9526f359;
      background: #ffffff14;
    }

    /* ================= CHECKBOXES ================= */
    .vendor-turf-page input[type="checkbox"] {
      accent-color: var(--accent-blue);
      transform: scale(1.05);
      margin-right: 6px;
    }

    /* ================= MAP ================= */
    #map {
      border-radius: 16px;
      border: 1px solid rgba(255, 255, 255, 0.15);
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
      color: #9526f3;
      margin-bottom: 14px;
    }

    /* ================= HOT HOUR ================= */
    .hotHourRow input {
      font-size: 13px;
    }

    /* ================= PRIMARY BUTTON ================= */
    .vendor-turf-page .btn-custom {
      background: linear-gradient(135deg,
          var(--accent-blue),
          var(--accent-blue-dark));
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
      box-shadow: 0 14px 35px #9526f359;
    }

    /* ================= SECONDARY BUTTON ================= */
    .btn-outline-warning {
      border-color: var(--accent-orange);
      color: var(--accent-orange);
    }

    .btn-outline-warning:hover {
      background: var(--accent-orange);
      color: #111;
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

.btn-outline-warning {
      border-color: var(--accent-purple-highlight);
      color: var(--text-main);
    }

    .btn-outline-warning:hover {
      background: var(--accent-purple-highlight);
      color: #111;
      border-color: #9526F3;
    }

    .back-btn{
      background-color: transparent;
      border: 2px solid #9526F3; 
      padding: 10px 26px; 
      border-radius: 25px; 
      color: #ffffff;
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
    padding: 24px 16px;+
    
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
    <form method="post"  enctype="multipart/form-data">
      <div class="top-bar">
        <div class="container-xl">
          <button class="back-btn" onclick="history.back()">← Back</button>
          <h2>Turf Details</h2>
        </div>
    </div>
      <input type="hidden" name="turf_id" value="<?= $turf_id ?>">
      <!-- Turf Name -->
      <div class="mb-3">
        <label><span class="warning">*</span> Turf Name</label>
        <input type="text" id="turf_name" name="turf_name" class="form-control" placeholder="Enter your Turf Name"
          value="<?= htmlspecialchars($turf['turf_name']) ?>">
      </div><br>

      <!-- Address -->
      <div class="mb-3">
        <label>
          <span class="warning">*</span> City
        </label>

       <select name="city_id" class="form-control" required>
    <option value="">-- Select City --</option>

    <?php foreach ($cities as $city): 
        $cityId = (int)$city['city_id'];
        $selectedCityId = (int)$turf['city_id'];
    ?>
        <option value="<?= $cityId ?>" <?= ($cityId === $selectedCityId) ? 'selected' : '' ?>>
            <?= htmlspecialchars($city['city_name'], ENT_QUOTES, 'UTF-8') ?>
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
          placeholder=" Road, landmark, area (for clarity only)" ><?= htmlspecialchars($turf['location']) ?></textarea>
      </div>

      <input type="hidden" name="latitude" id="latitude" required>
      <input type="hidden" name="longitude" id="longitude" required>
      <br>

      <!--image upload-->
      <!-- <div class="mb-3">
        <label for="imageUpload"><span class="warning">* </span>Upload an Image:</label>
        <input type="file" id="turf_images" name="turf_images[]" multiple accept="image/*">
      </div><br> -->
      <!-- Image Upload Section -->
<div class="mb-3">
  <label><span class="warning">*</span> Turf Images</label>

  <!-- Existing Images -->
  <div id="existingImages" class="d-flex flex-wrap gap-3 mb-3">
    <?php foreach ($images as $img): ?>
      <div class="image-box position-relative" data-id="<?= $img['image_id'] ?>">
        
        <img src="turf_images/<?= htmlspecialchars($img['image_path']) ?>"
             width="120" height="90"
             style="object-fit:cover; border-radius:10px;">

        <button type="button"
                class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1"
                onclick="markDelete(this, <?= $img['image_id'] ?>)">
          ✕
        </button>

      </div>
    <?php endforeach; ?>
  </div>

  <!-- Hidden field for deleted images -->
  <input type="hidden" name="delete_images" id="delete_images">

  <!-- Add Images Button -->
  <button type="button" class="btn btn-outline-warning mb-2"
          onclick="document.getElementById('turf_images').click()">
    + Add Images
  </button>

  <!-- File Input -->
  <input type="file" id="turf_images" name="turf_images[]" multiple accept="image/*" hidden>

  <!-- Preview New Images -->
  <div id="preview" class="d-flex flex-wrap gap-3"></div>

</div>






      <!--description-->
      <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea id="description" name="description" rows="3" class="form-control" placeholder="About your turf"
          required><?= htmlspecialchars($turf['description']) ?></textarea><br>
      </div>
      <!--aminities-->
      <div class="mb-3">
        <label class="form-label">Select your Amenities:</label><br>

        <input type="checkbox" name="amenities[]" value="1" <?=in_array(1, $amenityIds) ? 'checked' : '' ?>
        > Cafeteria<br>

        <input type="checkbox" name="amenities[]" value="2" <?=in_array(2, $amenityIds) ? 'checked' : '' ?>
        > Washroom<br>

        <input type="checkbox" name="amenities[]" value="3" <?=in_array(3, $amenityIds) ? 'checked' : '' ?>
        > Sitting Area<br>

        <input type="checkbox" name="amenities[]" value="4" <?=in_array(4, $amenityIds) ? 'checked' : '' ?>
        > Sports Equipment<br>
      </div>

      <br>

      <!-- Operating Time -->
      <div class="mb-3">
        <label><span class="warning">*</span> Operating Time</label>
        <div class="time-row">
          <input type="time" class="form-control" name="start_time"
       value="<?= substr($turf['start_time'], 0, 5) ?>" required>

        <input type="time" class="form-control" name="end_time"
       value="<?= substr($turf['end_time'], 0, 5) ?>" required>
        </div>
      </div><br>

      <!-- Sports -->
      <div class="mb-3">
        <label class="form-label">
          <span class="warning">*</span> Sports Available
        </label>

        <div class="form-check">
          <input class="form-check-input sportCheck" type="checkbox" name="sports[]" value="1" <?=in_array(1,
            $sportsData) ? 'checked' : '' ?>>
          <label class="form-check-label">Football</label>
        </div>

        <div class="form-check">
          <input class="form-check-input sportCheck" type="checkbox" name="sports[]" value="2" <?=in_array(2,
            $sportsData) ? 'checked' : '' ?>>
          <label class="form-check-label">Cricket</label>
        </div>

        <div class="form-check">
          <input class="form-check-input sportCheck" type="checkbox" name="sports[]" value="3" <?=in_array(3,
            $sportsData) ? 'checked' : '' ?>>
          <label class="form-check-label">PickleBall</label>
        </div>

        <div class="form-check">
          <input class="form-check-input sportCheck" type="checkbox" name="sports[]" value="4" <?=in_array(4,
            $sportsData) ? 'checked' : '' ?>>
          <label class="form-check-label">Tennis</label>
        </div>
      </div>



      <!-- Pricing Cards will appear here -->
      <div id="pricingContainer"></div>

      <template id="pricingTemplate">
        <div class="price-box mt-4 p-3 border rounded">

          <h6 class="text-warning sport-title"></h6>
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

      <button type="submit" class="btn btn-custom w-100 mt-4">Update</button>

    </form>
  </div>
  <script>
  const DB_PRICES = <?= json_encode($priceData) ?>;
  </script>

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
              // ===== PREFILL VALUES FROM DATABASE =====
              if (DB_PRICES[sportId] && DB_PRICES[sportId].weekday) {

                const weekday = DB_PRICES[sportId].weekday;

                if (weekday.morning) {
                  clone.querySelector(
                    `input[name="price[${sportId}][weekday][morning]"]`
                  ).value = weekday.morning;
                }

                if (weekday.evening) {
                  clone.querySelector(
                    `input[name="price[${sportId}][weekday][evening]"]`
                  ).value = weekday.evening;
                }

                if (weekday.night) {
                  clone.querySelector(
                    `input[name="price[${sportId}][weekday][night]"]`
                  ).value = weekday.night;
                }
              }
         

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
      //attribution: '© OpenStreetMap'
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
    // 🔁 Trigger pricing cards for pre-checked sports (EDIT MODE FIX)
    document.querySelectorAll('.sportCheck').forEach(check => {
      if (check.checked) {
        check.dispatchEvent(new Event('change'));
      }
    });
  </script>
  <script>
    let deleteList = [];
let newFiles = [];

function markDelete(btn, id) {
  deleteList.push(id);
  document.getElementById('delete_images').value = deleteList.join(',');
  btn.closest('.image-box').remove();
}

// REMOVE NEW IMAGE (before upload)
function removeNewImage(index, el) {
  newFiles.splice(index, 1);
  renderPreview();
}

// RENDER PREVIEW WITH DELETE BUTTON
function renderPreview() {
  const preview = document.getElementById('preview');
  preview.innerHTML = "";

  newFiles.forEach((file, index) => {

    const reader = new FileReader();

    reader.onload = function (e) {

      const box = document.createElement('div');
      box.className = "image-box position-relative";

      const img = document.createElement('img');
      img.src = e.target.result;
      img.style.width = "120px";
      img.style.height = "90px";
      img.style.objectFit = "cover";
      img.style.borderRadius = "10px";

      const btn = document.createElement('button');
      btn.type = "button";
      btn.className = "btn btn-danger btn-sm position-absolute top-0 end-0 m-1";
      btn.innerHTML = "✕";

      btn.onclick = function () {
        removeNewImage(index, box);
      };

      box.appendChild(img);
      box.appendChild(btn);

      preview.appendChild(box);
    };

    reader.readAsDataURL(file);
  });
}

// HANDLE FILE INPUT
document.getElementById('turf_images').addEventListener('change', function () {

  newFiles = [...this.files]; // store files separately
  renderPreview();

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
    <h3>Turf Successfully Updated</h3>
    <p>Your turf has been updated and is now live.</p>
    <button onclick="closePopup()" class="popup-btn">Continue</button>
  </div>
</div>
<?php endif; ?>
</body>

</html>
