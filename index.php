<?php
  session_start();
  include('db.php');

  $defaultProfileImage = 'user/profile/default_avatar.jpg';
  $profileImage = $defaultProfileImage;

  if (!empty($_SESSION['profile_image']) && file_exists($_SESSION['profile_image'])) {
    $profileImage = $_SESSION['profile_image'];
  }
?>

<?php
include('db.php');
if (isset($_SESSION['email']))
  {
      $user_id = $_SESSION["user_id"];

      $stmt1 = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM notifications 
    WHERE user_id = ? AND is_read = 0
");
$stmt1->bind_param("i", $user_id);
$stmt1->execute();
$result1 = $stmt1->get_result();
$count = $result1->fetch_assoc()['total'];

      $stmt2 = $conn->prepare("
    SELECT title, message, created_at 
    FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result = $stmt2->get_result();
      if (isset($_POST['clear_all'])) {
    $stmt1 = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt1->bind_param("i", $user_id);
    $stmt1->execute();

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
  }



?>
<?php
$contactStatus = '';
$contactMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $description = trim($_POST['description'] ?? '');

  if ($name === '' || $email === '' || $phone === '' || $description === '') {
    $contactStatus = 'error';
    $contactMessage = 'Please fill in all contact form fields.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $contactStatus = 'error';
    $contactMessage = 'Please enter a valid email address.';
  } else {
    $stmt = $conn->prepare("INSERT INTO contact_us (name, email, phone, description) VALUES (?, ?, ?, ?)");

    if ($stmt) {
      $stmt->bind_param("ssss", $name, $email, $phone, $description);

      if ($stmt->execute()) {
        header("Location: index.php?contact_status=success#contact-us");
        exit();
      } else {
        $contactStatus = 'error';
        $contactMessage = 'Your message could not be saved right now. Please try again.';
      }

      $stmt->close();
    } else {
      $contactStatus = 'error';
      $contactMessage = 'Contact form is temporarily unavailable.';
    }
  }
}

if (isset($_GET['contact_status']) && $_GET['contact_status'] === 'success') {
  $contactStatus = 'success';
  $contactMessage = 'Your message has been sent successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Elite Grounds - Home</title>
  <link rel="shortcut icon" href="favicon.png" type="image/png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Allura&family=Sanchez:ital@1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="whole.css">
</head>
<style>
html {
    scroll-behavior: smooth;
}
/* 🔔 Notification Button */
.notif-btn {
    position: relative;
    display: inline-block;
    color: white;
    font-size: 22px;
    cursor: pointer;
    transition: 0.3s;
}

.notif-btn:hover {
    color: #a855f7;
    transform: scale(1.1);
}

/* 🔴 Badge */
.notif-badge {
    position: absolute;
    top: -6px;
    right: -10px;

    background: linear-gradient(135deg, #ff3b3b, #ff6b6b);
    color: white;

    font-size: 10px;
    font-weight: bold;

    padding: 3px 7px;
    border-radius: 50px;

    min-width: 18px;
    text-align: center;

    box-shadow: 0 0 8px rgba(255, 59, 59, 0.7);
}

/* 📩 Sidebar */
.notif-sidebar {
    position: fixed;
    top: 0;
    right: -380px;
    width: 360px;
    height: 100%;

    background: rgba(15, 15, 20, 0.95);
    backdrop-filter: blur(12px);

    box-shadow: -10px 0 25px rgba(0,0,0,0.8);
    border-left: 1px solid rgba(168, 85, 247, 0.2);

    transition: 0.35s ease;
    z-index: 9999;

    display: flex;
    flex-direction: column;
}

.notif-sidebar.active {
    right: 0;
}

/* Header */
.notif-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    padding: 16px;

    border-bottom: 1px solid rgba(255,255,255,0.05);
    color: white;

    font-size: 18px;
    font-weight: 600;
}

/* Close Button */
.notif-header button {
    background: none;
    border: none;
    color: #aaa;
    font-size: 18px;
    cursor: pointer;
    transition: 0.2s;
}

.notif-header button:hover {
    color: #ff3b3b;
}

/* Content */
.notif-content {
    padding: 15px;
    overflow-y: auto;
}

/* Items */
.notif-item {
    background: linear-gradient(145deg, #1a1a22, #121218);
    padding: 12px;
    border-radius: 12px;
    margin-bottom: 12px;

    border: 1px solid rgba(255,255,255,0.05);

    transition: 0.25s;
}

.notif-item:hover {
    border-color: rgba(168, 85, 247, 0.5);
    box-shadow: 0 0 10px rgba(168, 85, 247, 0.3);
}

/* Text */
.notif-item p {
    margin: 0;
    color: #e5e5e5;
    font-size: 14px;
}

/* Time */
.time {
    font-size: 11px;
    color: #888;
    margin-top: 5px;
}

/* Empty State */
.empty {
    text-align: center;
    color: #777;
    margin-top: 30px;
    font-size: 14px;
}

.clear-btn {
    width: 100%;
    padding: 10px;

    background: linear-gradient(135deg, #7c3aed, #a855f7);
    color: white;

    border: none;
    border-radius: 10px;

    font-size: 14px;
    font-weight: 600;

    cursor: pointer;
    transition: 0.3s ease;

    box-shadow: 0 0 10px rgba(168, 85, 247, 0.4);
}

/* Hover */
.clear-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 18px rgba(168, 85, 247, 0.7);
}

/* Click */
.clear-btn:active {
    transform: scale(0.97);
}

.navbar-center-links .nav-link {
    font-weight: 500;
}
/* navbar animation */
.smart-navbar {
    top: 0;
    margin: 0 auto;
    width: 100%;
    padding: 0.85rem 1rem;
    background: rgba(13, 13, 16, 0.96) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    transition: width 0.35s ease, margin-top 0.35s ease, border-radius 0.35s ease,
        background-color 0.35s ease, box-shadow 0.35s ease, backdrop-filter 0.35s ease,
        transform 0.35s ease;
}

.smart-navbar .navbar-brand,
.smart-navbar .nav-link,
.smart-navbar .navbar-toggler {
    transition: color 0.3s ease, opacity 0.3s ease;
}

.smart-navbar.is-floating {
    top: 12px;
    width: min(96%, 1280px);
    margin-top: 14px;
    border-radius: 22px;
    background: rgba(239, 236, 229, 0.88) !important;
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.45);
}

.smart-navbar.is-floating .navbar-brand,
.smart-navbar.is-floating .nav-link,
.smart-navbar.is-floating .notif-btn,
.smart-navbar.is-floating .navbar-toggler {
    color: #141414 !important;
}

.smart-navbar.is-floating .notif-btn:hover,
.smart-navbar.is-floating .nav-link:hover,
.smart-navbar.is-floating .navbar-brand:hover {
    color: #5d28c6 !important;
}

.smart-navbar.is-floating .navbar-toggler-icon {
    filter: invert(1);
}

.about-us-image-placeholder {
    min-height: 320px;
    border-radius: 20px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.04);
}

.about-us-image-placeholder img {
    width: 100%;
    height: 100%;
    min-height: 320px;
    object-fit: cover;
    display: block;
}

.contact-section {
    padding: 2rem 0 4rem;
}

.contact-wrapper {
    background: linear-gradient(145deg, #121218, #1a1a22);
    border: 1px solid rgba(149, 38, 243, 0.22);
    border-radius: 28px;
    overflow: hidden;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
}

.contact-form-panel {
    padding: 3rem;
}

.contact-alert {
    border-radius: 14px;
    padding: 0.95rem 1rem;
    margin-bottom: 1.4rem;
    font-size: 0.95rem;
}

.contact-alert-success {
    background: rgba(34, 197, 94, 0.12);
    border: 1px solid rgba(34, 197, 94, 0.35);
    color: #dcfce7;
}

.contact-alert-error {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.32);
    color: #fee2e2;
}

.contact-eyebrow {
    display: inline-block;
    margin-bottom: 0.7rem;
    color: #b784ff;
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 0.04em;
}

.contact-title {
    color: #f7f6f2;
    font-size: clamp(2rem, 4vw, 3.2rem);
    font-weight: 700;
    margin-bottom: 0.8rem;
    line-height: 1.05;
}

.contact-description {
    color: #d6d6de;
    text-align: left;
    margin-bottom: 1.8rem;
    line-height: 1.7;
}

.contact-form .form-label {
    color: #f3f4f6;
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}

.contact-form .form-control {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #f7f6f2;
    border-radius: 12px;
    padding: 0.95rem 1rem;
    box-shadow: none;
}

.contact-form .form-control::placeholder {
    color: #9fa3b0;
}

.contact-form .form-control:focus {
    border-color: #9526F3;
    box-shadow: 0 0 0 0.2rem rgba(149, 38, 243, 0.14);
}

.contact-submit-btn {
    background: linear-gradient(135deg, #9526F3, #b44cff);
    color: #fff;
    border: none;
    border-radius: 14px;
    padding: 0.95rem 2rem;
    font-weight: 600;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.contact-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(149, 38, 243, 0.28);
}

.contact-submit-btn:focus,
.contact-submit-btn:active {
    box-shadow: 0 0 0 0.2rem rgba(149, 38, 243, 0.18);
}

.contact-image-panel {
    min-height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.contact-image-panel img {
    width: 100%;
    height: auto;
    max-height: 520px;
    object-fit: cover;
    display: block;
    border-radius: 22px;
}

@media (min-width: 992px) {
    .navbar .container-fluid {
        position: relative;
    }

    .navbar-center-links {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
    }
}

@media (max-width: 991.98px) {
    .smart-navbar {
        width: 100%;
        margin-top: 0;
        border-radius: 0;
    }

    .smart-navbar.is-floating {
        width: calc(100% - 20px);
        margin-top: 10px;
        border-radius: 18px;
    }

    .contact-form-panel {
        padding: 2rem 1.5rem;
    }

    .contact-image-panel img {
        max-height: 320px;
    }
}
  </style>
<body onload="startSlider();">
<nav id="mainNavbar" class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top smart-navbar">
  <div class="container-fluid">

    <a class="navbar-brand" href="index.php">SportsSync</a>

    <!-- TOGGLER -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- MENU -->
    <div class="collapse navbar-collapse" id="navbarNav">
      <?php if (isset($_SESSION['email'])): ?>
        <ul class="navbar-nav navbar-center-links mx-auto align-items-center gap-lg-4">
          <li class="nav-item">
            <a class="nav-link" href="#about-us">About Us</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#explore">Explore</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#contact-us">Contact Us</a>
          </li>
        </ul>
      <?php endif; ?>

      <ul class="navbar-nav align-items-center gap-3 ms-lg-auto">

        <?php if (isset($_SESSION['email'])): ?>

          <!-- 🔔 Notification -->
          <li class="nav-item">
            <a href="#" class="notif-btn" onclick="openSidebar(); return false;">
              <i class="bi bi-bell-fill"></i>

              <?php if ($count > 0): ?>
                <span class="notif-badge">
                  <?php echo ($count > 99) ? '99+' : $count; ?>
                </span>
              <?php endif; ?>
            </a>
          </li>

          <!-- 👤 Profile -->
          <li class="nav-item">
            <a class="nav-link p-0" href="user/user_settings.php">
              <img src="<?php echo htmlspecialchars($profileImage); ?>" 
                   class="navbar-profile-photo">
            </a>
          </li>

          <!-- 🚪 Logout -->
          <li class="nav-item">
            <a class="nav-link" href="logout.php">Logout</a>
          </li>

        <?php else: ?>

          <li class="nav-item">
            <a class="nav-link" href="signin.php">Login</a>
          </li>

          <li class="nav-item">
            <a class="nav-link" href="signup.php">Sign Up</a>
          </li>

        <?php endif; ?>

      </ul>
    </div>

  </div>
</nav>

  <!-- Hero Section --> 
  <section class="hero">
    <h1>
      Find the <span class="highlight-best">Best Grounds</span>. Feel the </br>
    <span class="highlight-game">Real Game</span>
    </h1>
    <p>Game-ready grounds. Pro-level amenities. Real action.<br>Where passion meets performance.</p>
    <div>
      <a href="user/navbar.php" class="btn btn-success"><span>Book Turf</span></a>
      <a href="
      <?php 
        if(!isset($_SESSION["role"])){
            echo "signin.php";
        }else{
          if($_SESSION["role"] == "User"){
            echo "requestToBeVendor.php";
          }else{
            echo "owner/owner.php";
          }
        }
      ?>
      " class="btn btn-success" id="becomeVendorBtn"><?php if(!isset($_SESSION["role"]) || $_SESSION["role"] == "User"){
            echo "<span>Become a Vendor</span>";
          }else{
            echo "<span>Vendor Panel<span>";
          }?>
</a>
    </div>
  </section>

  <!-- Slider -->
  <section class="container my-5" data-aos="fade-up">
    <div id="sliderContainer" class="rounded overflow-hidden">
      <img id="sliderImage" src="images/newbg2.jpg" class="slider-img" alt="Slider">
    </div>
  </section>


  <!-- Stats Section -->
  <section class="container text-center my-5" data-aos="fade-up">
    <div class="row">
      <div class="col-md-3">
        <i class="bi bi-trophy-fill fs-2 text-white"></i>
        <div class="stat-number">500+</div>
        <div class="stat-label">Premium Turfs</div>
      </div>
      <div class="col-md-3">
        <i class="bi bi-people-fill fs-2 text-white"></i>
        <div class="stat-number">50K+</div>
        <div class="stat-label">Happy Players</div>
      </div>
      <div class="col-md-3">
        <i class="bi bi-geo-alt-fill fs-2 text-white"></i>
        <div class="stat-number">25+</div>
        <div class="stat-label">Cities Covered</div>
      </div>
      <div class="col-md-3">
        <i class="bi bi-star-fill fs-2 text-white"></i>
        <div class="stat-number">4.8</div>  
        <div class="stat-label">Average Rating</div>
      </div>
    </div>
  </section>

  <!-- About Us Section -->
  <section class="container my-5 py-4" id="about-us" data-aos="fade-up">
    <h2 class="section-title">About Us</h2>
    <div class="row align-items-center g-4">
      <div class="col-lg-6">
        <div class="about-us-image-placeholder">
          <img src="images/newbg3.jpg" alt="About Us">
        </div>
      </div>
      <div class="col-lg-6">
        <p class="text-light">
          We built SportSync because booking a turf was always frustrating-missed calls, double bookings, and ruined plans.
          Instead of just complaining, the five of us decided to fix it ourselves.
        </p>
        <p class="text-light">
          SportSync is our simple attempt to make booking easy and reliable.
          It's not perfect, but it's real and built from our own experience.
        </p>
      </div>
    </div>
  </section>

  <!-- Sports Section -->

  <section class="py-5 " id="explore" data-aos="fade-up">
    <div class="container">
      <h2 class="section-title">Explore Sports</h2>
      <p class="section-subtitle">From cricket to pickleball, find the perfect turf for your favorite sport</p>
      <div class="row text-center">
        <div class="col-6 col-md-4 col-lg-2 mb-4" data-aos="zoom-in" data-aos-delay="0">
          <div class="sport-card">
            <i class="bi bi-activity fs-1 mb-2"></i><br>Cricket
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2 mb-4" data-aos="zoom-in" data-aos-delay="100">
          <div class="sport-card">
            <i class="bi bi-dribbble fs-1 mb-2"></i><br>Football
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2 mb-4" data-aos="zoom-in" data-aos-delay="200">
          <div class="sport-card">
            <i class="bi bi-basket2-fill fs-1 mb-2"></i><br>Basketball
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2 mb-4" data-aos="zoom-in" data-aos-delay="300">
          <div class="sport-card">
            <i class="bi bi-record-circle fs-1 mb-2"></i><br>Pickleball
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2 mb-4" data-aos="zoom-in" data-aos-delay="400">
          <div class="sport-card">
            <i class="bi bi-circle-half fs-1 mb-2"></i><br>Tennis
          </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2 mb-4" data-aos="zoom-in" data-aos-delay="500">
          <div class="sport-card">
            <i class="bi bi-wind fs-1 mb-2"></i><br>Badminton
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Testimonials -->
  <section class="py-5" data-aos="fade-up">
    <div class="container">
      <h2 class="section-title">What Our Players Say</h2>
      <div class="row">
        <div class="col-md-4 mb-4">
          <div class="bg-dark rounded p-4 border">
            <p class="text-warning">★★★★★</p>
            <p>"TurfBook Pro made it easy to find and book grounds. Seamless process!"</p>
            <small>- Arjun Sharma, Cricket Enthusiast</small>
          </div>
        </div>
        <div class="col-md-4 mb-4">
          <div class="bg-dark rounded p-4 border">
            <p class="text-warning">★★★★★</p>
            <p>"Great platform with excellent turfs. Very reliable!"</p>
            <small>- Priya Singh, Football Player</small>
          </div>
        </div>
        <div class="col-md-4 mb-4">
          <div class="bg-dark rounded p-4 border">
            <p class="text-warning">★★★★★</p>
            <p>"As a coach, I book courts weekly. This site saves me time!"</p>
            <small>- Vikram Patel, Pickleball Coach</small>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- How It Works -->
  <section class="py-5" data-aos="fade-up">
    <div class="container">
      <h2 class="section-title">How It Works</h2>
      <p class="section-subtitle">Book your perfect turf in 3 steps</p>
      <div class="row text-center">
        <div class="col-md-4">
          <div class="p-1 bg-dark">
            <div class="display-4">🔍</div>
            <h5>Search & Filter</h5>
            <p>Find Turfs by City, Sport, and Amenities.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="p-1 bg-dark">
            <div class="display-4">📅</div>
            <h5>Select & Book</h5>
            <p>Pick Time Slot and Complete Booking.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="p-1 bg-dark">
            <div class="display-4">🏆</div>
            <h5>Play & Enjoy</h5>
            <p>Get Confirmation and Play your Sport!</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="contact-section" id="contact-us" data-aos="fade-up">
    <div class="container">
      <div class="contact-wrapper">
        <div class="row g-0 align-items-stretch">
          <div class="col-lg-6">
            <div class="contact-form-panel">
              <span class="contact-eyebrow">Contact Us</span>
              <h3 class="contact-title">Let's Plan Your Next Game</h3>
              <p class="contact-description">
                Reach out for bookings, partnership help, or any questions about SportsSync. We’ll get back to you with the right support.
              </p>
              <?php if ($contactMessage !== ''): ?>
                <div class="contact-alert <?php echo $contactStatus === 'success' ? 'contact-alert-success' : 'contact-alert-error'; ?>">
                  <?php echo htmlspecialchars($contactMessage); ?>
                </div>
              <?php endif; ?>
              <form class="contact-form" method="POST" action="#contact-us">
                <div class="mb-3">
                  <label for="contactName" class="form-label">Name</label>
                  <input type="text" class="form-control" id="contactName" name="name" placeholder="Enter your name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                  <label for="contactEmail" class="form-label">Email</label>
                  <input type="email" class="form-control" id="contactEmail" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                  <label for="contactPhone" class="form-label">Phone</label>
                  <input type="tel" class="form-control" id="contactPhone" name="phone" placeholder="Enter your phone number" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
                <div class="mb-4">
                  <label for="contactDescription" class="form-label">Description</label>
                  <textarea class="form-control" id="contactDescription" name="description" rows="4" placeholder="Tell us how we can help"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
                <button type="submit" name="contact_submit" class="contact-submit-btn">Send Message</button>
              </form>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="contact-image-panel">
              <img src="images/newbg1.jpg" alt="SportsSync Contact">
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <div id="loginPopup" style="
  display:none;
  position:fixed;
  inset:0;
  background:#000000b3;
  backdrop-filter:blur(4px);
  z-index:9999;
  align-items:center;
  justify-content:center;
">
  <div style="
    background:#111;
    padding:30px 40px;
    border-radius:16px;
    text-align:center;
    box-shadow:0 0 25px #9526f359;
  ">
    <h5 style="color:#9526F3;">Login Required</h5>
    <p style="color:#ccc;margin:0;">
      Please sign in before becoming a vendor
    </p>
  </div>
</div>

  <?php include("footer.php"); ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script>
    AOS.init({ duration: 1000 });
    function startSlider() {
      // Best result: use landscape images close to 1600x900 (16:9 ratio).
      const images = ["images/newbg1.jpg", "images/newbg2.jpg", "images/newbg3.jpg", "images/newbg4.jpg", "images/newbg5.jpg"];
      let index = 0;
      setInterval(() => {
        document.getElementById("sliderImage").src = images[++index % images.length];
      }, 2000);
    }
  document.getElementById("becomeVendorBtn").addEventListener("click", function(e) {

  <?php if(!isset($_SESSION["role"])): ?>
    e.preventDefault();

    const popup = document.getElementById("loginPopup");
    popup.style.display = "flex";

    let redirected = false;

    function goToSignin() {
      if (redirected) return;
      redirected = true;
      window.location.replace("signin.php");
    }

    // Auto redirect after 1.5 sec
    const timer = setTimeout(goToSignin, 1000);

    // Redirect on ANY key press
    document.addEventListener("keydown", function handler() {
      clearTimeout(timer);
      document.removeEventListener("keydown", handler);
      goToSignin();
    });

  <?php endif; ?>

});

// Hide popup when page is restored (back button fix) 
window.addEventListener("pageshow", function () {
  const popup = document.getElementById("loginPopup");
  if (popup) popup.style.display = "none";
});


function fetchNotifications() {
    fetch('fetch_notifications.php')
    .then(res => res.json())
    .then(data => {

        // Update badge
        const badge = document.querySelector('.notif-badge');

        if (data.count > 0) {
            if (badge) {
                badge.innerText = data.count > 99 ? '99+' : data.count;
            } else {
                // create badge dynamically
                const bell = document.querySelector('.notif-btn');
                const span = document.createElement('span');
                span.className = 'notif-badge';
                span.innerText = data.count;
                bell.appendChild(span);
            }
        } else if (badge) {
            badge.remove();
        }

        // Update list
        const container = document.getElementById('notifContent');
        if (!container) return;
        container.innerHTML = '';

        if (data.notifications.length === 0) {
            container.innerHTML = '<p class="empty">No notifications</p>';
            return;
        }

        data.notifications.forEach(n => {
            container.innerHTML += `
                <div class="notif-item">
                    <p style="font-size:18px;color:#9526f3;">
                        ${n.title}
                    </p>
                    <p>${n.message}</p>
                    <span class="time">${n.created_at}</span>
                </div>
            `;
        });
        container.innerHTML += `
    <form method="POST" style="margin-top: 15px;">
        <button type="submit" name="clear_all" class="clear-btn">
            Clear All
        </button>
    </form>
`;
    });
}

function openSidebar() {
    document.getElementById("notifSidebar").classList.add("active");

    fetch('mark_read.php'); // mark as read

    // instantly remove badge
    const badge = document.querySelector('.notif-badge');
    if (badge) badge.remove();
}

function closeSidebar() {
    document.getElementById("notifSidebar").classList.remove("active");
}

const mainNavbar = document.getElementById("mainNavbar");

function updateFloatingNavbar() {
    if (!mainNavbar) return;

    if (window.scrollY > 40) {
        mainNavbar.classList.add("is-floating");
    } else {
        mainNavbar.classList.remove("is-floating");
    }
}

updateFloatingNavbar();
window.addEventListener("scroll", updateFloatingNavbar, { passive: true });
fetchNotifications(); // run immediately
setInterval(fetchNotifications, 5000);
</script>


<?php if (isset($_SESSION['email'])): ?>
<div id="notifSidebar" class="notif-sidebar">

    <div class="notif-header">
        <h3>Notifications</h3>
        <button onclick="closeSidebar()">✖</button>
    </div>

    <div id="notifContent" class="notif-content">

<?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
        <div class="notif-item">
          <p style="font-size:25px; color:#9526f359; text-align:left;">
              <?php echo htmlspecialchars($row['title']); ?>
          </p><br>
            <p style = "text-align:left;"><?php echo $row['message']; ?></p>
            <span class="time"><?php echo $row['created_at']; ?></span>
        </div>
    <?php endwhile; ?>
    
<?php else: ?>
    <p class="empty">No notifications</p>
<?php endif; ?>
  <?php endif; ?>

</div>

</div>
</body>
</html>
