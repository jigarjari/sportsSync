<?php
session_start();
include_once('../../db.php');

if ($conn->connect_error) {
    die("DB Error");
}

$vendor_id = (int)$_SESSION['user_id'];

// Fetch plans
$plans = $conn->query("SELECT * FROM ad_plans ORDER BY priority_score ASC");

// Fetch vendor turfs
$turfs = $conn->query("SELECT turf_id, turf_name FROM turftb WHERE owner_id = $vendor_id");

// Handle AJAX
if(isset($_POST['buy_plan'])) {

    $plan_id = (int)$_POST['plan_id'];
    $vendor_id = (int)$_SESSION['user_id'];

    if (!isset($_POST['turf_ids']) || !is_array($_POST['turf_ids'])) {
        echo "Invalid turf selection";
        exit;
    }

    $turf_ids = $_POST['turf_ids'];

    $plan = $conn->query("SELECT * FROM ad_plans WHERE id=$plan_id")->fetch_assoc();

    if (!$plan) {
        echo "Invalid plan";
        exit;
    }

    $start = date('Y-m-d H:i:s');
    $end = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));

    foreach($turf_ids as $turf_id){

        $turf_id = (int)$turf_id;

        $check = $conn->query("
            SELECT id FROM turf_ads 
            WHERE turf_id = $turf_id 
            AND is_active = 1 
            AND NOW() < end_date
        ");

        if($check->num_rows > 0){
            continue;
        }

        if (!$conn->query("
            INSERT INTO turf_ads (turf_id, vendor_id, plan_id, start_date, end_date, is_active, payment_status)
            VALUES ($turf_id, $vendor_id, $plan_id, '$start', '$end', 1, 'paid')
        ")) {
            echo "DB Error: " . $conn->error;
            exit;
        }
    }

    echo "success";
    exit;
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Boost Turf</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: radial-gradient(circle at 20% 20%, #1a0033, #050510 70%);
            color: white;
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
        }

        /* Background glow blobs */
        .bg-glow {
            position: fixed;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(155, 0, 255, 0.3), transparent);
            filter: blur(120px);
            z-index: 0;
            pointer-events: none;
        }

        .glow1 {
            top: -100px;
            left: -100px;
        }

        .glow2 {
            bottom: -100px;
            right: -100px;
        }

        .header {
            text-align: center;
            margin: 80px 0 40px;
            position: relative;
            z-index: 2;
        }

        .header h1 {
            font-size: 3rem;
            font-weight: 800;
            letter-spacing: 1px;
            background: linear-gradient(90deg, #7b2ff7, #ffffff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header p {
            color: #aaa;
        }

        .plan-card {
            position: relative;
            z-index: 2;
            background: rgba(247, 246, 242, 0.05); 
            backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            transition: all 0.35s ease;
            border: 1px solid rgba(189, 189, 189, 0.2);
            overflow: hidden;
        }

        /* Glow border effect */
        .plan-card::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 16px;
            padding: 1px;
            background: linear-gradient(135deg,#9526F3,transparent,#9526F3);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        .plan-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 0 25px rgba(149, 38, 243, 0.25);
            border-color: #9526F3;
        }

        .plan-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #e6eef7; /* match hero text */
            letter-spacing: 0.5px;
        }

        .price {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 15px 0;
            color: #9526F3; 
        }

        .features {
            font-size: 0.9rem;
            color: #aaa;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .btn-neon {
            border: 2px solid #9526F3;
            background: transparent;
            color: #9526F3;
            border-radius: 25px;
            padding: 10px 24px;
            transition: 0.35s;
            position: relative;
            overflow: hidden;
        }

        /* fill animation */
        .btn-neon::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #9526F3, #7a1fd6);
            transform: scaleX(0);
            transform-origin: left;
            transition: 0.4s ease;
            z-index: 0;
        }

        .btn-neon:hover::before {
            transform: scaleX(1);
        }

        .btn-neon span {
            position: relative;
            z-index: 1;
        }

        .btn-neon:hover {
            color: #fff;
        }

        /* Best plan emphasis */
        .best {
            transform: scale(1.08);
            box-shadow: 0 0 30px rgba(0, 119, 255, 0.5);
        }

        .badge-best {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #9526F3;
            color: #fff;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            box-shadow: 0 0 10px rgba(149, 38, 243, 0.4);
        }

        .turf-card {
            padding: 15px;
            border-radius: 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
        }

        .turf-card:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(123,47,247,0.5);
        }

        .turf-card.active {
            background: linear-gradient(45deg, #7b2ff7, #00c6ff);
            border: none;
            box-shadow: 0 0 20px rgba(0,198,255,0.6);
        }
        </style>
</head>

<body>

    <div class="bg-glow glow1"></div>
    <div class="bg-glow glow2"></div>

    <div class="container">

        <div class="header">
            <h1>Boost Visibility</h1>
            <p>Get discovered. Get booked. Dominate listings.</p>
        </div>

        <div class="row justify-content-center">

            <?php while($row = $plans->fetch_assoc()) { ?>

            <div class="col-md-4 mb-4">
                <div class="plan-card">

                <?php if($row['name'] == 'Drift') { ?>
                    <div class="badge-best">Most Popular</div>
                <?php } ?>

                    <div class="plan-title">
                        <?= $row['name']; ?>
                    </div>
                    <div class="price">₹
                        <?= $row['price']; ?>
                    </div>

                    <div class="features">
                        ⚡
                        <?= $row['duration_days']; ?> Days Boost<br>
                        🚀 Top Listing Priority<br>
                        📈 More Bookings
                    </div>

                    <button class="btn-neon buy-btn" data-id="<?= $row['id']; ?>" data-name="<?= $row['name']; ?>"
                        data-price="<?= $row['price']; ?>"><span>
                        Activate Boost
                    </span></button>

                </div>
            </div>

            <?php } ?>

        </div>
    </div>

    <!-- PAYMENT MODAL -->
    <div class="modal fade" id="paymentModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white">

                <div class="modal-header">
                    <h5>Select Turfs</h5>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div id="turfSelect" class="row">

    <?php 
    $turfs->data_seek(0); // reset pointer
    while($t = $turfs->fetch_assoc()) { ?>

    <div class="col-6 mb-3">
        <div class="turf-card" data-id="<?= $t['turf_id']; ?>">
            <?= $t['turf_name']; ?>
        </div>
    </div>

    <?php } ?>
</div>

                    <hr>

                    <p>Plan: <span id="planName"></span></p>
                    <p>Price per turf: ₹<span id="planPrice"></span></p>
                    <p>GST (5%): ₹<span id="gst"></span></p>
                    <h5>Total: ₹<span id="total"></span></h5>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal"><span>Cancel</span></button>
                    <button class="btn-neon" onclick="confirmBooking()" id="confirmBtn"><span> Confirm </span></button>
                </div>

            </div>
        </div>
    </div>

    <!-- SUCCESS MODAL -->
    <div class="modal fade" id="successModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center bg-dark text-white">
                <div class="modal-body p-5">
                    <h2 style="color:#00ffcc;">BOOST ACTIVE</h2>
                    <p>Your turf is now dominating the listings.</p>
                    <button class="btn-neon" data-bs-dismiss="modal"><span>Continue</span></button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
let selectedPlanId = null;
let planPrice = 0;
let selectedTurfs = [];

// PLAN CLICK
$(document).on("click", ".buy-btn", function (e) {
    e.preventDefault();

    selectedPlanId = $(this).data("id");
    planPrice = parseFloat($(this).data("price"));

    $("#planName").text($(this).data("name"));
    $("#planPrice").text(planPrice);

    calculate();

    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
});


// TURF SELECT
$(document).on("click", ".turf-card", function () {
    let id = $(this).data("id");

    if ($(this).hasClass("active")) {
        $(this).removeClass("active");
        selectedTurfs = selectedTurfs.filter(t => t != id);
    } else {
        $(this).addClass("active");
        selectedTurfs.push(id);
    }

    calculate();
});


// CALCULATE TOTAL
function calculate() {
    let count = selectedTurfs.length;

    let base = planPrice * count;
    let gst = base * 0.05;
    let total = base + gst;

    $("#gst").text(gst.toFixed(2));
    $("#total").text(total.toFixed(2));
}


// CONFIRM PAYMENT
function confirmBooking() {

    if (selectedTurfs.length === 0) {
        alert("Select at least one turf");
        return;
    }

    let count = selectedTurfs.length;
    let base = planPrice * count;
    let gst = base * 0.05;
    let total = Math.ceil(base + gst);

    // ✅ CREATE ORDER (CORRECT API)
    fetch("../../user/apiBooking/create_order.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ amount: total })
    })
    .then(r => r.json())
    .then(data => {

        if (!data.id) {
            console.error("Order Error:", data);
            alert("Order creation failed");
            return;
        }

        var options = {
            key: "rzp_test_SYtytZXZKMEOF5", 
            amount: data.amount,
            currency: data.currency,
            order_id: data.id,

            name: "SportSync",
            description: "Promotion Boost",

            handler: function (response) {

                // CALL YOUR NEW BACKEND (NOT verify_promo_payment.php)
                fetch("activate_promo.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        plan_id: selectedPlanId,
                        turf_ids: selectedTurfs,
                        payment_id: response.razorpay_payment_id,
                        order_id: response.razorpay_order_id,
                        amount: total
                    })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.status === "success") {
                        const modal = new bootstrap.Modal(document.getElementById('successModal'));
                        modal.show();
                    } else {
                        alert("DB Error: " + res.message);
                    }
                });

            },

            theme: { color: "#9526F3" }
        };

        var rzp = new Razorpay(options);

        rzp.on('payment.failed', function (response) {
            console.error("Payment Failed:", response.error);
            alert("Payment Failed: " + response.error.description);
        });

        rzp.open();

    })
    .catch(err => {
        console.error("Fetch Error:", err);
        alert("Something went wrong");
    });
}
</script>

</body>

</html>