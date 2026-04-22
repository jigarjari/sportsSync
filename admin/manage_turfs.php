<?php
include('../db.php');
session_start();

// BLOCK / UNBLOCK LOGIC
if(isset($_POST['toggle_turf'])){
    $turf_id = (int)$_POST['turf_id'];

    $res = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT status FROM turftb WHERE turf_id='$turf_id'"
    ));

    $newStatus = ($res['status'] == 'active') ? 'blocked' : 'active';

    mysqli_query($conn,"UPDATE turftb SET status='$newStatus' WHERE turf_id='$turf_id'");

    header("Location: manage_turfs.php");
    exit;
}

// FETCH TURFS
$query = "SELECT t.*, u.name AS owner_name, u.id as owner_id, img.image_path FROM turftb t JOIN user u ON t.owner_id = u.id LEFT JOIN turf_imagestb img ON img.image_id = (SELECT image_id FROM turf_imagestb WHERE turf_id = t.turf_id LIMIT 1)ORDER BY t.turf_id DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>

<html>
<head>
<title>Manage Turfs</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
body { 
  background-color: #0e0f11; 
}

.navbar {
     background: linear-gradient(
        90deg,
        rgba(18, 18, 18, 0.98),
        rgba(18, 18, 18, 0.9)
    );
    border-bottom: 1px solid var(--border-soft);
    backdrop-filter: blur(8px);
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.text-orange {
    color: #9526F3; 
    font-weight: 600;
}

.navbar a {
    color: #fff;
    text-decoration: none;
    border: 1px solid #9526F3;
    padding: 6px 12px;
    border-radius: 6px;
}

.container {
    padding: 20px;
}
.container h2{
    color: white;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 20px;
}

.card {
    background: #121212;
    border: 1px solid #262626;
    border-radius: 12px;
    padding: 15px;
    transition: 0.3s;
    position: relative;
}

.card:hover {
    transform: scale(1.03);
    box-shadow: 0 0 15px #9526F3;
    border-color: #9526F3;
}

/* STATUS BADGE */
.status {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: bold;
}

.active { background: #16a34a; }
.blocked { background: #dc2626; }

.img-box {
    width: 100%;
    height: 150px;
    border-radius: 10px;
    overflow: hidden;
}

.img-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-img {
    width: 100%;
    height: 100%;
    background: #1e293b;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #aaa;
}

.title {
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    margin-top: 10px;
}

.meta {
    font-size: 13px;
    color: #9ca3af;
    margin-top: 5px;
}

.actions {
    margin-top: 12px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.btn {
    flex: 1;
    padding: 7px;
    border-radius: 8px;
    font-size: 12px;
    text-align: center;
    text-decoration: none;
    cursor: pointer;
    border: none;
}

/* BUTTON COLORS */
.msg { background: #2563eb; color: #fff; }
.view { background: #1e293b; color: #fff; }
.block { background: #dc2626; color: #fff; }
.unblock { background: #16a34a; color: #fff; }

@media(max-width:500px){
    .img-box { height:130px; }
}
</style>

</head>

<body>

<div class="navbar text-orange" href="#">
        SPORT SYNC ADMIN
    <a href="dashboard.php">Back</a>
</div>

<div class="container">
<h2><i class="fas fa-futbol"></i> Manage Turfs</h2>

<div class="grid">

<?php if(mysqli_num_rows($result) > 0){ ?>

<?php while($row = mysqli_fetch_assoc($result)) { ?>

<div class="card">

<!-- STATUS -->
<div class="status <?= $row['status']; ?>">
    <?= strtoupper($row['status']); ?>
</div>

<!-- IMAGE -->
<div class="img-box">
    <?php if($row['image_path']){ ?>
        <img src="../owner/turf_images/<?php echo $row['image_path']; ?>">
    <?php } else { ?>
        <div class="no-img">No Image</div>
    <?php } ?>
</div>

<!-- TITLE -->
<div class="title"><?php echo $row['turf_name']; ?></div>

<div class="meta">
    <i class="fas fa-user"></i> <?php echo $row['owner_name']; ?>
</div>

<div class="meta">
    <i class="fas fa-location-dot"></i> <?php echo $row['location']; ?>
</div>

<!-- ACTIONS -->
<div class="actions">

    <!-- MESSAGE -->
    <a href="chat.php?user_id=<?php echo $row['owner_id']; ?>" class="btn msg">
        Message
    </a>

    <!-- VIEW -->
    <a href="../user/turf_view.php?turf_id=<?php echo $row['turf_id']; ?>" class="btn view">
        View
    </a>

    <!-- BLOCK / UNBLOCK -->
    <form method="POST" style="flex:1;">
        <input type="hidden" name="turf_id" value="<?php echo $row['turf_id']; ?>">
        <input type="hidden" name="toggle_turf" value="1">

        <?php if($row['status'] == 'active'){ ?>
            <button class="btn block">Block</button>
        <?php } else { ?>
            <button class="btn unblock">Unblock</button>
        <?php } ?>
    </form>

</div>

</div>

<?php } ?>

<?php } else { ?>

<p>No turfs found.</p>
<?php } ?>

</div>
</div>

</body>
</html>
