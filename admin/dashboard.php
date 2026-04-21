<?php
session_start();
// Check for the admin session we just set
if (!isset($_SESSION['admin']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../signin.php");
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | Sport Sync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="whole.css">
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark px-3 px-md-4 py-3">
        <div class="container-fluid">

            <!-- Logo -->
            <a class="navbar-brand fw-bold text-orange" href="#">
                SPORT SYNC <span class="text-white">ADMIN</span>
            </a>

            <!-- Toggle Button (Mobile) -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Right Side -->
            <div class="collapse navbar-collapse justify-content-end" id="adminNavbar">
                <div
                    class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center gap-2 gap-lg-3 mt-3 mt-lg-0">

                    <!-- Email -->
                    <span class="text-secondary small text-truncate" style="max-width: 180px;">
                        <?= $_SESSION['email'] ?>
                    </span>

                    <!-- Logout -->
                    <a href="../logout.php" class="btn btn-outline-danger btn-sm w-100 w-lg-auto">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12 mb-4">
                <h2 class="text-orange ">Welcome back, Admin</h2>
                <p class="text-white">Manage your turf requests and platform users from here.</p>
            </div>

            <div class="row g-3 g-md-4">

                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card card-custom p-4 h-100">
                        <i class="bi bi-person-badge text-orange fs-1 mb-3"></i>
                        <h5 class="text-white">Vendor Requests</h5>
                        <p class="text-secondary small">Review and approve new turf owners.</p>
                        <a href="vendor_requests.php" class="btn btn-orange w-100 mt-auto"><span> Open Requests </span></a>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card card-custom p-4 h-100">
                        <i class="bi bi-chat-dots-fill text-orange fs-1 mb-3"></i>
                        <h5 class="text-white">Admin Chat</h5>
                        <p class="text-secondary small">Chat with users and vendors.</p>
                        <a href="chat.php" class="btn btn-orange w-100 mt-auto"><span> Open Chat </span></a>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card card-custom p-4 h-100">
                        <i class="bi bi-people-fill text-orange fs-1 mb-3"></i>
                        <h5 class="text-white">User Management</h5>
                        <p class="text-secondary small">Monitor users and control access.</p>
                        <a href="manage_users.php" class="btn btn-orange w-100 mt-auto"><span> Manage Users</span></a>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card card-custom p-4 h-100">
                        <i class="bi bi-people-fill text-orange fs-1 mb-3"></i>
                        <h5 class="text-white">Turf Management</h5>
                        <p class="text-secondary small">Manage turfs, owners and courts.</p>
                        <a href="manage_turfs.php" class="btn btn-orange w-100 mt-auto"><span> Manage Turfs </span></a>
                    </div>
                </div>   
                
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card card-custom p-4 h-100">
                        <i class="bi bi-envelope-fill text-orange fs-1 mb-3"></i>
                        <h5 class="text-white">Contact Messages</h5>
                        <p class="text-secondary small">View messages submitted from contact form.</p>
                        <a href="view_contacts.php" class="btn btn-orange w-100 mt-auto"><span> View Messages </span></a>
                    </div>
                </div>      
                
               <div class="col-12 col-md-6 col-lg-4">
                    <div class="card card-custom p-4 h-100">
                    <i class="bi bi-megaphone-fill text-orange fs-1 mb-3"></i>
                    <h5 class="text-white">Ads Plans</h5>
                    <p class="text-secondary small">Create and manage promotion plans for vendors.</p>
                    <a href="manage_plans.php" class="btn btn-orange w-100 mt-auto"><span> Manage Plans </span></a>
                </div>
            </div>  
            <div class="col-12 col-md-6 col-lg-4">
                    <div class="card card-custom p-4 h-100">
                        <i class="bi bi-trophy-fill text-orange fs-1 mb-3"></i>
                        <h5 class="text-white">Tournament Requests</h5>
                        <p class="text-secondary small">Review and approve tournament submissions.</p>
                        <a href="tournament_requests.php" class="btn btn-orange w-100 mt-auto"><span>Approve Tournaments</span></a>
                    </div>
                </div>   
            </div>
        </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>