<?php
include("../../db.php");

$search = $_POST['search'] ?? '';
$city = $_POST['city'] ?? 'all';
$userLat = $_POST['lat'] ?? null;
$userLng = $_POST['lng'] ?? null;

$where = [];

// search
if (!empty($_POST['search'])) {
  $search = mysqli_real_escape_string($conn, $_POST['search']);
  $where[] = "(t.turf_name LIKE '%$search%' OR t.location LIKE '%$search%')";
}

// city
if (!empty($_POST['city']) && $_POST['city'] != 'all') {
  $city = (int) $_POST['city'];
  $where[] = "t.city_id = $city";
}

// sport
if (!empty($_POST['sport']) && $_POST['sport'] != 'all') {
  $sport = (int) $_POST['sport'];
  $where[] = "ts.sport_id = $sport";
}

$whereSql = 'WHERE 1=1';

if (!empty($where)) {
  $whereSql .= ' AND ' . implode(' AND ', $where);
}

$distanceSql = '';
$orderBy = 'ORDER BY priority DESC, t.turf_id DESC';

if (!empty($userLat) && !empty($userLng)) {
  $userLat = (float) $userLat;
  $userLng = (float) $userLng;

  $distanceSql = ",
    (
      6371 * acos(
        cos(radians($userLat)) *
        cos(radians(t.latitude)) *
        cos(radians(t.longitude) - radians($userLng)) +
        sin(radians($userLat)) *
        sin(radians(t.latitude))
      )
    ) AS distance";

  $orderBy = 'ORDER BY priority DESC, distance ASC';
}

$havingSql = '';

if (!empty($_POST['distance']) && !empty($userLat) && !empty($userLng)) {
  $radius = (int) $_POST['distance'];
  $havingSql = "HAVING distance <= $radius";
}

$sql = "
SELECT 
  t.turf_id,
  t.turf_name,
  t.location,
  IFNULL(ap.priority_score, 0) AS priority,
  c.city_name" .
  (!empty($distanceSql) ? $distanceSql : '') . "
  ,
  (
    SELECT image_path 
    FROM turf_imagestb ti 
    WHERE ti.turf_id = t.turf_id 
    LIMIT 1
  ) AS image,
  GROUP_CONCAT(s.sport_name SEPARATOR ', ') AS sports
FROM turftb t
LEFT JOIN citytb c ON c.city_id = t.city_id
LEFT JOIN turf_sportstb ts ON ts.turf_id = t.turf_id
LEFT JOIN sportstb s ON s.sport_id = ts.sport_id
LEFT JOIN turf_ads ta 
  ON ta.turf_id = t.turf_id 
  AND ta.is_active = 1 
  AND ta.end_date >= NOW()
LEFT JOIN ad_plans ap 
  ON ap.id = ta.plan_id
$whereSql
and status != 'blocked'
GROUP BY t.turf_id
$havingSql
$orderBy
";

$res = mysqli_query($conn, $sql);

$html = '';

while ($row = mysqli_fetch_assoc($res)) {

  $img = $row['image']
    ? "../owner/turf_images/" . $row['image']
    : "../images/default_turf.jpg";

  // ✅ NEW: detect boosted turf
  $isBoosted = !empty($row['priority']) && $row['priority'] > 0;

  $html .= '
<div class="col-md-4 mb-4">
  <div class="card h-100">
    
    <div style="position:relative;">
      <img src="' . $img . '" class="card-img-top" style="height:220px;object-fit:cover;">';

  // ✅ NEW: badge
  if ($isBoosted) {
    $html .= '
      <span class="popular-badge">
        <i class="bi bi-star-fill"></i> Popular
      </span>';
  }

  $html .= '
    </div>

    <div class="card-body">
      <h5 class="card-title">' . $row['turf_name'] . '</h5>
      <p class="card-text">
        <strong>City:</strong> ' . $row['city_name'] . '<br>
        <strong>Location:</strong> ' . $row['location'] . '<br>';

  if (isset($row['distance'])) {
    $html .= '<small class="text-muted">' . round($row['distance'], 2) . ' km away</small><br>';
  } else {
    $html .= '<small class="text-muted">Location not enabled</small><br>';
  }

  $html .= '<strong>Sports:</strong> ' . $row['sports'] . '
      </p>
      <div class="text-center">
        <a href="../user/turf_view.php?turf_id=' . $row['turf_id'] . '" class="btn btn-success">
          View
        </a>
      </div>
    </div>
  </div>
</div>';

}

echo $html ?: '<p class="text-center text-light ">No turfs found</p>';