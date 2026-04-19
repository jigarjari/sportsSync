<?php
include('../db.php');
session_start();

/* ===============================
   FETCH TOURNAMENTS WITH DETAILS
================================*/
$sql = "
SELECT 
    tt.*,
    t.turf_name,
    t.location,
    c.city_name,
    tc.court_id

FROM tournamenttb tt

LEFT JOIN turftb t 
    ON t.turf_id = tt.turf_id

LEFT JOIN citytb c 
    ON c.city_id = t.city_id

LEFT JOIN tournament_courtstb tc 
    ON tc.tournament_id = tt.tournament_id

ORDER BY tt.tournament_id DESC
";

$res = mysqli_query($conn, $sql);
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
body {
  margin: 0;
  background: #0e0f11;
  overflow-x: hidden;
  font-family: 'Segoe UI', sans-serif;
}

/* 🔥 Animated Gradient Glow Background */
body::before {
  content: "";
  position: fixed;
  width: 600px;
  height: 600px;
  background: radial-gradient(circle, #9526F3, transparent 60%);
  top: -100px;
  left: -100px;
  filter: blur(120px);
  opacity: 0.4;
  animation: floatGlow 8s ease-in-out infinite alternate;
  z-index: -1;
}

@keyframes floatGlow {
  from { transform: translate(0,0); }
  to { transform: translate(150px, 100px); }
}

/* 🔥 Grid overlay (matches your theme) */
body::after {
  content: "";
  position: fixed;
  width: 100%;
  height: 100%;
  background-image:
    linear-gradient(#1f1f1f 1px, transparent 1px),
    linear-gradient(90deg, #1f1f1f 1px, transparent 1px);
  background-size: 40px 40px;
  opacity: 0.15;
  z-index: -1;
}

/* HEADER */
.page-title {
  text-align: center;
  font-weight: 800;
  color: #fff;
  margin: 40px 0;
  font-size: 32px;
  letter-spacing: 1px;
}

.page-title span {
  color: #9526F3;
  text-shadow: 0 0 12px #9526F3;
}

/* CARD */
.tournament-card {
  position: relative;
  border-radius: 20px;
  padding: 20px;
  height: 100%;

  background: rgba(17, 24, 39, 0.6);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(149, 38, 243, 0.25);

  transition: all 0.35s ease;
  overflow: hidden;
}

/* glow border effect */
.tournament-card::before {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 20px;
  padding: 1px;
  background: linear-gradient(120deg, transparent, #9526F3, transparent);
  -webkit-mask:
    linear-gradient(#000 0 0) content-box,
    linear-gradient(#000 0 0);
  -webkit-mask-composite: xor;
  mask-composite: exclude;
}

.tournament-card:hover {
  transform: translateY(-8px) scale(1.02);
  box-shadow: 0 20px 50px rgba(149, 38, 243, 0.35);
}

/* TITLE */
.tournament-title {
  font-size: 20px;
  font-weight: 700;
  color: #fff;
  margin-bottom: 10px;
}

/* INFO */
.info {
  font-size: 14px;
  color: #cbd5e1;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.info i {
  color: #9526F3;
}

/* TERMS */
.terms {
  margin-top: 12px;
  padding: 12px;
  border-radius: 12px;
  background: rgba(2, 6, 23, 0.7);
  font-size: 13px;
  color: #94a3b8;
  border: 1px solid rgba(149, 38, 243, 0.2);
}

/* BUTTON */
.btn-participate {
  margin-top: 15px;
  width: 100%;
  padding: 10px;
  border-radius: 30px;
  border: none;

  background: linear-gradient(135deg, #9526F3, #6d1ed4);
  color: white;
  font-weight: 600;

  position: relative;
  overflow: hidden;
  transition: 0.3s;
}

/* button shine animation */
.btn-participate::after {
  content: "";
  position: absolute;
  width: 120%;
  height: 100%;
  background: linear-gradient(120deg, transparent, rgba(255,255,255,0.4), transparent);
  top: 0;
  left: -120%;
  transition: 0.5s;
}

.btn-participate:hover::after {
  left: 120%;
}

.btn-participate:hover {
  transform: scale(1.05);
  box-shadow: 0 12px 35px rgba(149, 38, 243, 0.4);
}

/* EMPTY */
.empty {
  text-align: center;
  margin-top: 100px;
  color: #aaa;
}
</style>
</head>

<body>

<div class="container">
<h2 class="page-title">🔥 <span>Live Tournaments</span> </h2>
    <div class="row g-4">

<?php if(mysqli_num_rows($res) > 0){ ?>
<?php while($row = mysqli_fetch_assoc($res)){ ?>

    <div class="col-lg-4 col-md-6">
        <div class="tournament-card">

            <div class="tournament-title">
                <?= htmlspecialchars($row['tournament_name']) ?>
            </div>

            <div class="info">
                <i class="bi bi-geo-alt-fill"></i>
                <?= htmlspecialchars($row['turf_name']) ?> (<?= $row['city_name'] ?>)
            </div>

            <div class="info">
                <i class="bi bi-map"></i>
                <?= htmlspecialchars($row['location']) ?>
            </div>

            <div class="info">
                <i class="bi bi-grid"></i>
                Court ID: <?= $row['court_id'] ?>
            </div>

            <div class="info">
                <i class="bi bi-calendar-event"></i>
                <?= $row['start_date'] ?> → <?= $row['end_date'] ?>
            </div>

            <div class="info">
                <i class="bi bi-clock"></i>
                <?= $row['tournament_time'] ?>
            </div>

            <div class="info">
                <i class="bi bi-people"></i>
                Max Players: <?= $row['max_participation'] ?>
            </div>

            <div class="terms">
                <strong>Terms:</strong><br>
                <?= nl2br(htmlspecialchars($row['terms_conditions'])) ?>
            </div>

            <a href="tournament_participation.php?tournament_id=<?= $row['tournament_id'] ?>" 
               class="btn btn-participate">
               🚀 Participate Now
            </a>

        </div>
    </div>

<?php } ?>
<?php } else { ?>

    <div class="empty">
        <h4>No tournaments available</h4>
        <p>Check back later</p>
    </div>

<?php } ?>

    </div>
</div>

</body>
</html>