<?php
session_start();
include("../db.php");
require_once '../includes/block_tournament_slots.php';
if (!isset($_SESSION['admin']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../signin.php");
    exit;
}

/* ===============================
   HANDLE APPROVE / REJECT
   =============================== */
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];

    if ($_GET['action'] == 'approve') {
        $tRow = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT * FROM tournamenttb WHERE tournament_id = $id"
        ));

        $newStatus = 'A';

        if ($tRow['created_by'] === 'USER' && $tRow['is_external_turf'] == 0) {
            // SCENARIO 3: User + Internal → don't go live yet, ask for payment
            $newStatus = 'A_UNPAID';

            // Send notification to user
            $msg = "Your tournament '{$tRow['tournament_name']}' is approved! Pay the slot fee to make it live.";
            $nStmt = mysqli_prepare(
                $conn,
                "INSERT INTO notifications 
            (user_id, type, title, message, reference_id, reference_type)
         VALUES (?, 'tournament_payment', 'Tournament Approved – Pay to Activate', ?, ?, 'tournament')"
            );
            mysqli_stmt_bind_param($nStmt, "isi", $tRow['host_id'], $msg, $id);
            mysqli_stmt_execute($nStmt);

        } elseif ($tRow['is_external_turf'] == 0 && $tRow['turf_id']) {
    // SCENARIO 1: Vendor + Internal → auto-block slots
    try {
        blockTournamentSlots($conn, $tRow, $tRow['host_id'], 'AUTO', 0);
        mysqli_query($conn,
            "UPDATE tournamenttb SET status = 'A' WHERE tournament_id = $id");
    } catch (Exception $e) {
        $errMsg = $e->getMessage();
        if (str_starts_with($errMsg, 'SLOT_CONFLICT::')) {
            $parts = explode('::', $errMsg);
            // Reject instead and notify vendor
            mysqli_query($conn,
                "UPDATE tournamenttb SET status = 'R' WHERE tournament_id = $id");
            $msg = "Your tournament '{$tRow['tournament_name']}' could not be approved — 
                    the turf is already booked on {$parts[1]} during {$parts[2]}.";
            $nStmt = mysqli_prepare($conn,
                "INSERT INTO notifications 
                    (user_id, type, title, message, reference_id, reference_type)
                 VALUES (?, 'tournament_conflict', 'Tournament Slot Conflict', ?, ?, 'tournament')");
            mysqli_stmt_bind_param($nStmt, "isi", $tRow['host_id'], $msg, $id);
            mysqli_stmt_execute($nStmt);
            // Redirect with error message
            header("Location: tournament_requests.php?conflict=1&name=" . 
                urlencode($tRow['tournament_name']));
            exit;
        }
    }
}
        // SCENARIO 2 & 4: External turf → just approve, nothing to block

        mysqli_query($conn, "UPDATE tournamenttb SET status = '$newStatus' WHERE tournament_id = $id");
    }

    if ($_GET['action'] == 'reject') {
        mysqli_query($conn, "UPDATE tournamenttb SET status='R' WHERE tournament_id=$id");
    }

    header("Location: tournament_requests.php");
    exit;
}

/* ===============================
   FETCH DATA
   =============================== */
$sql = "SELECT t.*, s.sport_name FROM tournamenttb t JOIN sportstb s ON t.sport_id = s.sport_id ORDER BY t.created_at DESC";
$result = mysqli_query($conn, $sql);
?>
<?php if (isset($_GET['conflict'])): ?>
<div class="alert" style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);
  color:#f87171;border-radius:12px;padding:14px 18px;margin-bottom:20px">
  <i class="bi bi-exclamation-triangle-fill me-2"></i>
  Tournament "<strong><?php echo htmlspecialchars($_GET['name'] ?? ''); ?></strong>" 
  was auto-rejected — the turf already has bookings during the requested time slot. 
  The host has been notified.
</div>
<?php endif; ?>
<!DOCTYPE html>
<html>

<head>
    <title>Tournament Requests</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #0e0f11;
        }

        .admin-card {
            background: #121212;
            padding: 24px;
            border: 1px solid #262626;
            border-radius: 12px;
        }

        .table thead th {
            background: #9526F3;
            color: #fff;
        }

        .table tbody tr {
            background: #0b1120;
            color: #fff;
        }

        .status-approved {
            background: #22c55e;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: .8rem;
        }

        .status-rejected {
            background: #ef4444;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: .8rem;
        }

        .status-pending {
            background: #facc15;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: .8rem;
            color: #000;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }
    </style>
</head>

<body>

    <div class="container mt-5 admin-card">

        <h3 class="text-white mb-4">Tournament Management</h3>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">

                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Sport</th>
                        <th>Entry Fee</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>

                            <tr>
                                <td><?= $row['tournament_name'] ?></td>
                                <td><?= $row['sport_name'] ?></td>
                                <td>₹<?= $row['entry_fee'] ?></td>
                                <td><?= $row['start_date'] ?></td>

                                <!-- STATUS -->
                                <td>
                                    <?php if ($row['status'] == 'P'): ?>
                                        <span class="status-pending">Pending</span>
                                    <?php elseif ($row['status'] == 'A'): ?>
                                        <span class="status-approved">Approved</span>
                                    <?php else: ?>
                                        <span class="status-rejected">Rejected</span>
                                    <?php endif; ?>
                                </td>

                                <!-- ACTION -->
                                <td>
                                    <?php if ($row['status'] == 'P'): ?>
                                        <div class="action-buttons">
                                            <a href="?action=approve&id=<?= $row['tournament_id'] ?>"
                                                class="btn btn-success btn-sm">Approve</a>
                                            <a href="?action=reject&id=<?= $row['tournament_id'] ?>"
                                                class="btn btn-danger btn-sm">Reject</a>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-secondary">No Action</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endwhile; ?>
                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="text-center text-secondary">No tournaments found</td>
                        </tr>

                    <?php endif; ?>

                </tbody>
            </table>
        </div>

        <a href="dashboard.php" class="btn btn-outline-light mt-3">Back to Dashboard</a>

    </div>

</body>

</html>