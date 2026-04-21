<?php
include('../db.php');
session_start();

$owner_id = $_SESSION['user_id'];

$sql = "
SELECT 
  t.turf_id,
  t.turf_name,
  t.location,
  c.city_name,
  (
    SELECT image_path 
    FROM turf_imagestb ti 
    WHERE ti.turf_id = t.turf_id 
    LIMIT 1
  ) AS image
FROM turftb t
LEFT JOIN citytb c ON c.city_id = t.city_id
WHERE t.owner_id = $owner_id
ORDER BY t.turf_id DESC
";

$res = mysqli_query($conn, $sql);

/* ===============================
   FETCH TOURNAMENTS (NEW BLOCK)
================================*/
$tournament_sql = "
SELECT 
    tt.*,
    t.turf_name,
    t.location,
    c.city_name,
    GROUP_CONCAT(tc.court_name SEPARATOR ', ') AS courts

FROM tournamenttb tt

LEFT JOIN tournament_courtstb tct 
    ON tt.tournament_id = tct.tournament_id

LEFT JOIN turf_courtstb tc 
    ON tct.court_id = tc.court_id

LEFT JOIN turftb t 
    ON tt.turf_id = t.turf_id

LEFT JOIN citytb c 
    ON t.city_id = c.city_id

WHERE tt.vendor_id = $owner_id

GROUP BY tt.tournament_id

ORDER BY tt.tournament_id DESC
";

$tournament_res = mysqli_query($conn, $tournament_sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Turfs</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../whole.css" rel="stylesheet">
  <style>
    /* ===============================
   GLOBAL THEME
================================*/
    :root {
      --bg-main: #050914;
      --card-bg: #190f2a;
      --card-border: #9526f359;
      --accent-blue: #9526F3;
      --accent-blue-dark: #9526f359;
      --text-main: #e5e7eb;
      --text-muted: #94a3b8;
    }

    body {
      background-color: #0e0f11;
      background-image: linear-gradient(45deg, #1f1f1f 25%, transparent 25%),
        linear-gradient(-45deg, #1f1f1f 25%, transparent 25%),
        linear-gradient(45deg, transparent 75%, #1f1f1f 75%),
        linear-gradient(-45deg, transparent 75%, #1f1f1f 75%);
      background-size: 6px 6px;
      background-position: 0 0, 0 3px, 3px -3px, -3px 0px;
    }

    /* ===============================
   PAGE HEADER
================================*/
    .page-title {
      font-weight: 700;
      color: var(--accent-blue);
      letter-spacing: 0.4px;
    }

    /* ===============================
   TURF CARD
================================*/
    .turf-card {
      background: linear-gradient(145deg, #0f172a, #020617);
      border-radius: 18px;
      overflow: hidden;
      height: 100%;
      border: 1px solid var(--card-border);
      transition: all 0.35s ease;
    }

    .turf-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 18px 45px rgba(59, 130, 246, 0.25);
    }

    /* ===============================
   IMAGE
================================*/
    .turf-img {
      position: relative;
      height: 190px;
      overflow: hidden;
    }

    .turf-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.5s ease;
    }

    .turf-card:hover img {
      transform: scale(1.08);
    }

    /* ===============================
   CITY BADGE
================================*/
    .city-badge {
      position: absolute;
      top: 12px;
      left: 12px;
      background: rgba(15, 23, 42, 0.85);
      color: var(--accent-blue);
      padding: 6px 14px;
      font-size: 12px;
      border-radius: 20px;
      font-weight: 500;
      border: 1px solid #9526f359;
    }

    /* ===============================
   BODY
================================*/
    .turf-body {
      padding: 18px;
    }

    .turf-title {
      font-size: 18px;
      font-weight: 600;
      color: #fff;
    }

    .turf-location {
      font-size: 14px;
      color: var(--text-muted);
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .turf-location i {
      color: var(--accent-blue);
    }

    /* ===============================
   ACTION BUTTON
================================*/
    .actions {
      display: flex;
    }

    .actions .btn {
      width: 100%;
      border-radius: 30px;
      font-size: 14px;
      font-weight: 600;
      background: linear-gradient(135deg,
          var(--accent-blue),
          var(--accent-blue-dark));
      color: #020617;
      border: none;
      transition: 0.3s;
    }

    .actions .btn:hover {
      transform: scale(1.05);
      box-shadow: 0 12px 35px #9526f359;
    }

    /* ===============================
   ADD TURF BUTTON
================================*/
    .go-vendor-btn {
      border-radius: 30px;
      padding: 10px 22px;
      font-size: 14px;
      font-weight: 600;
      background: linear-gradient(135deg,
          var(--accent-blue),
          var(--accent-blue-dark));
      color: #020617;
      border: none;
      transition: 0.3s;
    }

    .go-vendor-btn:hover {
      transform: translateY(-2px) scale(1.05);
      box-shadow: 0 12px 35px #9526f359;
    }

    /* ===============================
   EMPTY STATE
================================*/
    .empty-state {
      margin-top: 80px;
      text-align: center;
      color: #ffff;
    }


    /* ===============================
   LOAD ANIMATION
================================*/
    .turf-card {
      animation: fadeUp 0.5s ease both;
    }

    @keyframes fadeUp {
      from {
        opacity: 0;
        transform: translateY(12px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 768px) {
      .container.mt-5 {
        margin-top: 1.5rem !important;
        padding-left: 14px;
        padding-right: 14px;
      }

      .container.mt-5>.d-flex {
        flex-direction: column;
        align-items: stretch !important;
        gap: 12px;
      }

      .go-vendor-btn {
        width: 100%;
      }

      .turf-body {
        padding: 16px;
      }

      .actions {
        flex-direction: column;
        gap: 10px;
      }
    }

    .boost-timer {
      position: absolute;
      bottom: 10px;
      right: 10px;
      background: rgba(0, 0, 0, 0.75);
      color: #4ade80;
      padding: 6px 10px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
    }
  </style>
</head>

<body>



  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">

      <h3 class="page-title mb-0">My Turfs</h3>

      <!-- Wrap buttons -->
      <div class="d-flex gap-2">

        <a href="promotion/promotion.php" class="btn btn-primary go-vendor-btn">
          <i class="bi bi-arrow-up-circle"></i> Promote Turfs
        </a>

        <a href="add_turf.php" class="btn btn-primary go-vendor-btn">
          <i class="bi bi-plus-circle"></i> Add Turf
        </a>

      </div>

    </div>

<div class="row g-4">

<?php if (mysqli_num_rows($res) > 0) { ?>
<?php while ($row = mysqli_fetch_assoc($res)) {

    // CHECK IF TURF HAS BOOST
$ad_sql = "SELECT end_date 
           FROM turf_ads 
           WHERE turf_id = {$row['turf_id']} 
           AND is_active = 1
           ORDER BY end_date DESC 
           LIMIT 1";

$ad_res = mysqli_query($conn, $ad_sql);

$remaining_seconds = null;

if ($ad_res && mysqli_num_rows($ad_res) > 0) {
    $ad = mysqli_fetch_assoc($ad_res);

    if (!empty($ad['end_date'])) {
        $remaining_seconds = strtotime($ad['end_date']) - time();

        if ($remaining_seconds <= 0) {
            $remaining_seconds = null; // expired
        }
    }
}

    $img = $row['image']
        ? "../owner/turf_images/" . $row['image']
        : "../images/default_turf.jpg";

?>

     
      <div class="col-xl-3 col-lg-4 col-md-6">
        <div class="turf-card">

          <div class="turf-img">
            <img src="<?= $img ?>" alt="Turf Image">
            <span class="city-badge">
              <?= htmlspecialchars($row['city_name']) ?>
            </span>

            <?php if ($remaining_seconds) { ?>
            <div class="boost-timer" data-time="<?= $remaining_seconds ?>">
              Loading...
            </div>
            <?php } ?>
          </div>

          <div class="turf-body">
            <h5 class="turf-title">
              <?= htmlspecialchars($row['turf_name']) ?>
            </h5>

            <p class="turf-location">
              <i class="bi bi-geo-alt-fill"></i>
              <?= htmlspecialchars($row['location']) ?>
            </p>

            <div class="actions">
              <a href="../user/turf_view.php?turf_id=<?= $row['turf_id'] ?>&from=vendor" class="btn btn-success">
                <i class="bi bi-eye"></i> View Turf
              </a>
            </div>
            <div class="actions">
              <a href="../owner/edit_turf.php?turf_id=<?= $row['turf_id'] ?>&from=vendor" class="btn btn-success">
                <i class="bi bi-eye"></i> Edit Turf
              </a>
            </div>

          </div>
        </div>
      </div>

      <?php } ?>
      <?php } else { ?>

      <div class="empty-state">
        <h5>No turfs added yet</h5>
        <p>Click <strong>Add Turf</strong> to create your first turf</p>
      </div>

      <?php } ?>


      <!-- ===============================
     MY TOURNAMENTS SECTION
================================-->
      <div class="container mt-5">

        <div class="d-flex justify-content-between align-items-center mb-4">
          <h3 class="page-title mb-0">My Tournaments</h3>


        </div>

        <div class="row g-4">

          <?php if (mysqli_num_rows($tournament_res) > 0) { ?>
          <?php while ($t = mysqli_fetch_assoc($tournament_res)) { ?>

          <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="turf-card">


              <div class="turf-body">
                <h5 class="turf-title">
                  <?= htmlspecialchars($t['tournament_name']) ?>
                </h5>

                <p class="turf-location">
                  <i class="bi bi-building"></i>
                  Turf:
                  <?= htmlspecialchars($t['turf_name']) ?>
                </p>

                <p class="turf-location">
                  <i class="bi bi-grid"></i>
                  Courts:
                  <?= htmlspecialchars($t['courts'] ?? 'N/A') ?>
                </p>

                <p class="turf-location">
                  <i class="bi bi-geo-alt"></i>
                  <?= htmlspecialchars($t['location']) ?>,
                  <?= htmlspecialchars($t['city_name']) ?>
                </p>
                <p class="turf-location">
                  <i class="bi bi-calendar-event"></i>
                  <?= $t['start_date'] ?> →
                  <?= $t['end_date'] ?>
                </p>

                <p class="turf-location">
                  <i class="bi bi-people"></i>
                  Max:
                  <?= $t['max_participation'] ?>
                </p>

                <!-- Status badge -->
                <p class="turf-location">
                  <i class="bi bi-circle-fill"
                    style="font-size:.6rem;color:<?= $t['status']==='A'?'#4ade80':($t['status']==='R'?'#f87171':'#fbbf24'); ?>"></i>
                  <?= $t['status']==='A'?'Approved':($t['status']==='R'?'Rejected':'Pending Approval'); ?>
                </p>

                <div class="actions mt-2">
                  <a href="tournament_manage.php?id=<?= $t['tournament_id'] ?>" class="btn btn-success">
                    <i class="bi bi-people-fill"></i> Manage Teams
                  </a>
                </div>

              </div>

            </div>
          </div>

          <?php } ?>
          <?php } else { ?>

          <div class="empty-state">
            <h5>No tournaments created yet</h5>
            <p>Create your first tournament</p>
          </div>

          <?php } ?>

        </div>
      </div>
    </div>
  </div>
<script>
document.querySelectorAll('.boost-timer').forEach(el => {
  let time = parseInt(el.dataset.time);

  function updateTimer() {
    if (time <= 0) {
      el.style.display = "none";
      return;
    }

    if (time >= 86400) {
      let days = Math.floor(time / 86400);
      let hours = Math.floor((time % 86400) / 3600);
      el.innerText = `Boost Time: ${days}d ${hours}h`;
    } else {
      let h = Math.floor(time / 3600);
      let m = Math.floor((time % 3600) / 60);
      let s = time % 60;
      el.innerText = `Boost Time: ${h}h ${m}m ${s}s`;
    }

    time--;
    setTimeout(updateTimer, 1000);
  }

  updateTimer();
});
</script>
</body>

</html>