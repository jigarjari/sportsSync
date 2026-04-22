<?php
session_start();
require '../db.php';

if (!isset($_GET['turf_id'])) {
  die("Invalid turf");
}
$turf_id = (int) $_GET['turf_id'];
?>
<!DOCTYPE html>
<html>

<head>
  <title>Turf Booking</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <style>
    body {
      background-color: #0e0f11;
      background-image:
        linear-gradient(45deg, #1f1f1f 25%, transparent 25%),
        linear-gradient(-45deg, #1f1f1f 25%, transparent 25%),
        linear-gradient(45deg, transparent 75%, #1f1f1f 75%),
        linear-gradient(-45deg, transparent 75%, #1f1f1f 75%);
      background-size: 6px 6px;
      background-position: 0 0, 0 3px, 3px -3px, -3px 0px;
      color: #ffffff;
      padding-bottom: 80px;
      font-family: Arial, sans-serif;
    }

    /* ================= BOX ================= */
    .box {
      background: #000;
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 20px;
    }

    /* ================= ITEM ================= */
    .item {
      padding: 10px 15px;
      border: 1px solid #2a2a2a;
      border-radius: 8px;
      cursor: pointer;
    }

    .item.selected {
      background: #9526F3;
      color: #000;
    }

    /* ================= SLOT GRID ================= */
    .slots-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      gap: 14px;
    }

    /* SLOT CARD */
    .slot-card {
      background: #000;
      border: 1.5px solid #2a2a2a;
      border-radius: 10px;
      padding: 10px 8px;
      cursor: pointer;
      transition: all 0.18s ease;
      text-align: center;
    }

    .slot-card:hover {
      border-color: #9526F3;
      transform: translateY(-2px);
    }

    /* SELECTED */
    .slot-card.selected {
      background: linear-gradient(180deg, #9526F3, #b44cff);
      border-color: #9526F3;
      color: #000;
    }

    /* TIME */
    .slot-time {
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 0.3px;
    }

    /* PRICE */
    .slot-price {
      font-size: 12px;
      color: #8b8b8b;
      margin-top: 4px;
      transition: color 0.15s ease, font-weight 0.15s ease;
    }

    .slot-card:hover .slot-price {
      color: #9526F3;
      font-weight: 600;
    }

    .slot-card.selected .slot-price {
      color: #000;
      font-weight: 700;
    }

    /* DISABLED */
    .slot-card.disabled {
      opacity: 0.4;
      cursor: not-allowed;
      pointer-events: none;
    }

    /* ================= SPORTS GRID ================= */
    .sports-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
      gap: 14px;
    }

    .sport-card {
      background: #000;
      border: 1px solid #2a2a2a;
      border-radius: 12px;
      padding: 12px 8px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .sport-card:hover {
      transform: translateY(-2px);
      border-color: #9526F3;
    }

    .sport-card.selected {
      background: linear-gradient(180deg, #9526F3, #b44cff);
      border-color: #9526F3;
      color: #000;
    }

    .sport-icon {
      width: 36px;
      height: 36px;
      margin: 0 auto 6px;
      font-size: 26px;
      color: #9526F3;
    }

    .sport-card.selected .sport-icon {
      color: #000;
    }

    .sport-name {
      font-weight: 600;
      font-size: 14px;
    }

    /* ================= DATE STRIP ================= */
    .date-strip {
      display: flex;
      gap: 12px;
      overflow-x: auto;
      padding: 10px 0;
    }

    .date-strip::-webkit-scrollbar {
      height: 6px;
    }

    .date-strip::-webkit-scrollbar-thumb {
      background: #2a2a2a;
      border-radius: 10px;
    }

    .date-card {
      min-width: 70px;
      text-align: center;
      padding: 10px 6px;
      border-radius: 10px;
      border: 1px solid #2a2a2a;
      cursor: pointer;
      background: #000;
      color: #aaa;
      transition: 0.2s;
    }

    .date-card.active {
      background: #9526F3;
      color: #000;
      border-color: #9526F3;
      font-weight: 700;
    }

    /* ================= COURTS ================= */
    .courts-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
      gap: 16px;
    }

    .court-card {
      background: #000;
      border: 1px solid #2a2a2a;
      border-radius: 14px;
      padding: 18px 10px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .court-card:hover {
      transform: translateY(-3px);
      border-color: #9526F3;
    }

    .court-card.selected {
      background: linear-gradient(180deg, #9526F3, #b44cff);
      border-color: #9526F3;
      color: #000;
    }

    .court-name {
      font-size: 18px;
      font-weight: 700;
    }

    .court-sub {
      font-size: 12px;
      color: #aaa;
      margin-top: 4px;
    }

    .court-card.selected .court-sub {
      color: #fff;
    }

    /* ================= TOP BAR ================= */
    .top-bar {
      position: sticky;
      top: 0;
      z-index: 20;
      background: #0e0f11;
      padding: 14px 0 6px;
    }

    .back-btn {
      background: transparent;
      border: 1.5px solid #9526F3;
      color: #9526F3;
      padding: 6px 16px;
      border-radius: 999px;
      font-weight: 600;
      transition: all 0.18s ease;
    }

    .back-btn:hover {
      background: #9526f359;
    }

    .back-btn:active {
      transform: scale(0.96);
    }

    /* ================= BOOK BAR ================= */
    .book-bar {
      position: sticky;
      bottom: 0;
      z-index: 20;
      background: #0e0f11;
      border-top: 1px solid #2a2a2a;
      padding: 12px 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .book-total {
      font-size: 16px;
      font-weight: 600;
      color: #e0e0e0;
    }

    /* CTA */
    .book-btn {
      background: linear-gradient(135deg, #9526F3, #b44cff);
      border: none;
      color: #000;
      padding: 10px 26px;
      font-size: 15px;
      font-weight: 800;
      border-radius: 8px;
      cursor: pointer;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .book-btn:hover:not(:disabled) {
      transform: scale(1.05);
      box-shadow: 0 10px 25px #9526f359;
    }

    .book-btn:active:not(:disabled) {
      transform: scale(0.96);
      box-shadow: 0 6px 14px #9526f359;
    }

    .book-btn:disabled {
      background: #2a2a2a;
      color: #777;
      cursor: not-allowed;
      box-shadow: none;
    }

    /* SUMMARY */
    #sumTime div {
      padding: 4px 0;
      font-weight: 500;
    }
  </style>
</head>

<body class="container py-4">
  <!-- TOP BAR -->
  <div class="top-bar">
    <button class="back-btn" onclick="goBack()">← Back</button>
  </div>

  <h2 id="turfName"></h2>
  <p id="turfLoc"></p>

  <!-- DATE STRIP -->
  <div class="box">
    <label>Select Date</label>
    <div id="dateStrip" class="date-strip"></div>
  </div>

  <div class="box">
    <label>Select Sport</label>
    <div id="sports" class="sports-grid"></div>
  </div>

  <div class="box">
    <label>Select Court</label>
    <div id="courts" class="courts-grid"></div>
  </div>

  <div class="box">
    <label>Select Slots</label>
    <div id="slots" class="slots-grid"></div>
  </div>

  <h4>Total: ₹<span id="total">0</span></h4>

  <!-- BOOK BUTTON BAR -->
  <div class="book-bar">
    <div class="book-total">
      Total Pay Now: ₹<span id="stickyTotal">0</span>
    </div>

    <button class="book-btn" id="confirmBtn" disabled>
      Book Now
    </button>
  </div>

  <script>
    let selectedSlots = [];

    const userSession = {
      name: "<?= $_SESSION['name'] ?? '' ?>",
      email: "<?= $_SESSION['email'] ?? '' ?>",
      mobile: "<?= $_SESSION['mobile'] ?? '' ?>"
    };

    const turf_id = <?= $turf_id ?>;
    let selectedDate = "";
    let sport_id = "";
    let court_id = "";
    let total = 0;

    const dateStrip = document.getElementById("dateStrip");

    /* ---------- DATE STRIP GENERATION (21 DAYS) ---------- */
    function generateDates(days = 21) {
      const today = new Date();

      for (let i = 0; i < days; i++) {
        const d = new Date();
        d.setDate(today.getDate() + i);

        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, "0");
        const dd = String(d.getDate()).padStart(2, "0");
        const apiDate = `${yyyy}-${mm}-${dd}`;

        const card = document.createElement("div");
        card.className = "date-card";
        card.innerHTML = `
      <div class="day">${d.toLocaleDateString("en-US", { weekday: "short" })}</div>
      <div class="date">${dd}</div>
      <div class="month">${d.toLocaleDateString("en-US", { month: "short" })}</div>
    `;

        card.onclick = () => {
          document.querySelectorAll(".date-card").forEach(c => c.classList.remove("active"));
          card.classList.add("active");

          selectedDate = apiDate;

          // reset slots & total
          slots.innerHTML = "";
          total = 0;
          updateTotal();

          // reload logic (SAME AS YOUR CURRENT CODE)
          if (sport_id && court_id) {
            loadSlots();
          } else {
            loadSports();
          }
        };

        // auto select today
        if (i === 0) {
          card.classList.add("active");
          selectedDate = apiDate;
        }

        dateStrip.appendChild(card);
      }
    }

    /* ---------- INITIAL LOAD ---------- */
    generateDates(21);
    loadSports();

    let turfNameText = "";

    fetch(`apiBooking/get_turf.php?turf_id=${turf_id}`)
      .then(r => r.json())
      .then(d => {
        if (d.status !== "success") {
          alert("Failed to load turf details");
          return;
        }
        turfName.innerText = d.turf_name;
        turfLoc.innerText = d.location;
        turfNameText = d.turf_name; 
      });


    /* ---------- Load Sports ---------- */
    function loadSports() {
      fetch(`apiBooking/get_sports.php?turf_id=${turf_id}`)
        .then(r => r.json())
        .then(data => {
          sports.innerHTML = "";
          courts.innerHTML = "";
          slots.innerHTML = "";

          data.forEach(s => {
            let div = document.createElement("div");
            div.className = "sport-card";

            let icon = "🏏";
            if (s.sport_name.toLowerCase().includes("football")) icon = "⚽";
            if (s.sport_name.toLowerCase().includes("badminton")) icon = "🏸";
            if (s.sport_name.toLowerCase().includes("tennis")) icon = "🎾";

            div.innerHTML = `
        <div class="sport-icon">${icon}</div>
        <div class="sport-name">${s.sport_name}</div>
      `;

            div.onclick = () => {
              document.querySelectorAll(".sport-card").forEach(i => i.classList.remove("selected"));
              div.classList.add("selected");
              sport_id = s.sport_id;
              loadCourts();
            };

            sports.appendChild(div);
          });
        });
    }

    function loadCourts() {
      fetch(`apiBooking/get_courts.php?turf_id=${turf_id}&sport_id=${sport_id}`)
        .then(r => r.json())
        .then(data => {
          courts.innerHTML = "";
          slots.innerHTML = "";
          data.forEach(c => {
            let div = document.createElement("div");
            div.className = "court-card";
            div.innerHTML = `
        <div class="court-name">${c.court_name}</div>
        <div class="court-sub">Available</div>
      `;
            div.onclick = () => {
              document.querySelectorAll(".court-card").forEach(i => i.classList.remove("selected"));
              div.classList.add("selected");
              court_id = c.court_id;
              loadSlots();
            };
            courts.appendChild(div);
          });
        });
    }


    function loadSlots() {
      fetch(`apiBooking/get_slots.php?turf_id=${turf_id}&sport_id=${sport_id}&court_id=${court_id}&date=${selectedDate}`)
        .then(r => r.json())
        .then(data => {
          if (data.status === "maintenance") {
            slots.innerHTML = `
  <div style="
    grid-column: 1 / -1;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    padding:40px 20px;
    border-radius:16px;
    background: linear-gradient(135deg, #1a1a1a, #111);
    border:1px solid #2a2a2a;
    text-align:center;
  ">

    <div style="
      font-size:40px;
      margin-bottom:12px;
      color:#9526F3;
    ">
      ⚙️
    </div>

    <div style="
      font-size:18px;
      font-weight:600;
      color:#fff;
      margin-bottom:6px;
    ">
      Court Under Maintenance
    </div>

    <div style="
      font-size:13px;
      color:#aaa;
    ">
      This court is unavailable for the selected date.
    </div>

  </div>
`;

            total = 0;
            updateTotal();
            return;
          }
          slots.innerHTML = "";
          total = 0;
          updateTotal();

          data.forEach(s => {
            let div = document.createElement("div");
            div.className = "slot-card";

            div.innerHTML = `
        <div class="slot-time">
          ${s.start_time.slice(0, 5)} - ${s.end_time.slice(0, 5)}
        </div>
        <div class="slot-price">
          ₹${s.price_per_hour}
        </div>
      `;
            div.dataset.slotId = s.slot_id;
            //disabled already booked
            if (s.is_booked == 1) {
              div.classList.add("disabled");
              div.style.opacity = "0.35";
              div.style.pointerEvents = "none";

              slots.appendChild(div);
              return;
            }

            div.onclick = () => {
              div.classList.toggle("selected");

              if (div.classList.contains("selected")) {
                selectedSlots.push({
                  slot_id: s.slot_id,
                  start: s.start_time.slice(0, 5),
                  end: s.end_time.slice(0, 5)
                });

                total += parseInt(s.price_per_hour);
              } else {
                selectedSlots = selectedSlots.filter(
                  t => t.slot_id !== s.slot_id
                );

                total -= parseInt(s.price_per_hour);
              }

              updateTotal();
            };
            slots.appendChild(div);
          });
        });
    }

    function goBack() {
      window.history.back();
    }

    function updateTotal() {
      const halfAmount = Math.ceil(total / 2);
      document.getElementById("total").innerText = total;
      document.getElementById("stickyTotal").innerText = halfAmount; // Show half amount on button bar
      document.getElementById("confirmBtn").disabled = total <= 0;
    }
    document.getElementById("confirmBtn").onclick = openSummary;

    function openSummary() {
      if (!userSession.email) {
        alert("Please login to continue booking");
        //window.location.href = "../signin.php";
        return;
      }

      if (selectedSlots.length === 0) return;

      // sort by start time
      selectedSlots.sort((a, b) => a.start.localeCompare(b.start));

      document.getElementById("sumTurf").innerText =
        document.getElementById("turfName").innerText;

      document.getElementById("sumUser").innerText = userSession.name;
      document.getElementById("sumEmail").innerText = userSession.email;
      document.getElementById("sumMobile").innerText = userSession.mobile;

      document.getElementById("sumDate").innerText = selectedDate;

      // 🔥 hour-wise display
      document.getElementById("sumTime").innerHTML =
        selectedSlots
          .map(t => `<div>${t.start} - ${t.end}</div>`)
          .join("");

      document.getElementById("sumTotal").innerText = total;
      document.getElementById("sumPayable").innerText = Math.ceil(total / 2);

      document.getElementById("summaryOverlay").style.display = "flex";
    }


    function closeSummary() {
      document.getElementById("summaryOverlay").style.display = "none";
    }

    function confirmBooking() {
      if (total <= 0) {
        alert("Please select slots first");
        return;
      }

      if (!userSession.email) {
        alert("Please login to continue booking");
        return;
      }

      //Only Pay Half Amount For Booking
      const payableAmount = Math.ceil(total / 2);

      fetch("apiBooking/create_order.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ amount: payableAmount })
      })
        .then(r => r.json())
        .then(data => {
          console.log("Order Response:", data);

          if (data.error) {
            alert("Order Error: " + data.error);
            return;
          }

          //order_id check 
          if (!data.id) {
            alert("Order ID not received! Check backend logs.");
            return;
          }

          var options = {
            key: "rzp_test_SYtytZXZKMEOF5",
            amount: data.amount,
            currency: data.currency,
            order_id: data.id,
            name: "SportSync",
            description: "Turf Booking Payment",
            prefill: {
              name: userSession.name,
              email: userSession.email,
              contact: userSession.mobile
            },
            handler: function (response) {
              console.log("Payment Success:", response);

              fetch("apiBooking/confirm_booking.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({
                    turf_id: turf_id,
                    court_id: court_id,
                    sport_id: sport_id,
                    booking_date: selectedDate,
                    total: total,
                    paid_amount: payableAmount,
                    payment_id: response.razorpay_payment_id,
                    order_id: response.razorpay_order_id, // REAL order ID from Razorpay
                    slots: selectedSlots.map(s => s.slot_id)
                  })
              })
                .then(r => r.json())
                .then(res => {
                  console.log("Booking Response:", res);
                  if (res.status === "success") {
                    // The Absolute Path fix
                    const basePath = window.location.pathname.split('/')[1];
                    const pdf_path = window.location.origin + "/" + basePath + "/" + res.pdf_url;
                    console.log("Redirecting to:", pdf_path);
                    window.location.href = pdf_path;
                    alert("Booking Confirmed! We are opening your receipt: " + pdf_path);
                    window.location.href = pdf_path;
                  } else {
                    alert("Database Error: " + res.msg);
                  }
                });
            },
            theme: { color: "#9526F3" }
          };

          //razorpay popup create
          var rzp = new Razorpay(options);

          rzp.on('payment.failed', function (response) {
            console.error("❌ Payment Failed:", response.error);
            alert("Payment Failed: " + response.error.description);
          });

          //popup open
          rzp.open();
        })
        .catch(err => {
          console.error("Fetch Error:", err);
          alert("Network error - check console");
        });
    }



  </script>

  <!-- BOOKING SUMMARY MODAL -->
  <div id="summaryOverlay" style="display:none;
  position:fixed; inset:0; background:rgba(0,0,0,.75);
  z-index:9999; align-items:center; justify-content:center;">

    <div style="background:#111;
    border:1px solid #333;
    border-radius:14px;
    width:420px;
    padding:24px;
    color:#fff;">

      <h5 style="color:#caff33;margin-bottom:16px;">Booking Summary</h5>

      <div class="mb-2"><strong>Turf:</strong> <span id="sumTurf"></span></div>

      <hr style="border-color:#333">

      <div class="mb-2"><strong>Name:</strong> <span id="sumUser"></span></div>
      <div class="mb-2"><strong>Email:</strong> <span id="sumEmail"></span></div>
      <div class="mb-2"><strong>Mobile:</strong> <span id="sumMobile"></span></div>

      <hr style="border-color:#333">

      <div class="mb-2"><strong>Date:</strong> <span id="sumDate"></span></div>
      <div class="mb-2"><strong>Time:</strong> <span id="sumTime"></span></div>

      <hr style="border-color:#333">

      <h5 style="color:#e0e0e0;margin-top:10px;">Total Amount: ₹<span id="sumTotal"></span></h5>
      <h5 style="color:#e0e0e0;margin-top:10px;">Pay Now: ₹<span id="sumPayable"></span></h5>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <button onclick="closeSummary()" class="btn btn-secondary">Cancel</button>
        <button onclick="confirmBooking()" class="btn btn-success">Confirm Booking</button>
      </div>

    </div>
  </div>
</body>

</html>