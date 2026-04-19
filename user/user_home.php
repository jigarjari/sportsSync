<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Elite Grounds</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../whole.css">
  <script src="../jquery-3.7.1.min.js"></script>
  <style>
     body { 
  background-color: #0e0f11; 
  background-image: linear-gradient(45deg, #1f1f1f 25%, transparent 25%), 
                    linear-gradient(-45deg, #1f1f1f 25%, transparent 25%), 
                    linear-gradient(45deg, transparent 75%, #1f1f1f 75%),
                    linear-gradient(-45deg, transparent 75%, #1f1f1f 75%); 
   background-size: 6px 6px; 
   background-position: 0 0, 0 3px, 3px -3px, -3px 0px; 
  } 

    /* Title */
    h2 {
      text-align: center;
      color: #ffffff;
      margin-bottom: 20px;
      border-bottom: 2px solid #9526F3;
      padding-bottom: 10px;
    }

    /* Filters */
    .form-select,
    .form-control {
      background: var(--card-bg);
      color: var(--bg-dark);
      border: 1px solid var(--border);
    }

    .form-select:focus,
    .form-control:focus {
      border-color: #9526F3;
      box-shadow: 0 0 5px #9526F3;
    }

    /* Card */
    .card {
      background: var(--card-bg);
      border: 1px solid var(--divider);
      transition: .3s;
    }

    .card:hover {
      transform: scale(1.03);
      box-shadow: 0 0 15px #9526F3;
    }

    .card-title {
      color: #9526F3;
      font-weight: 600;
    }

    .card-text {
      color: #0e0d0dff;
    }
    
    /* view button */
    .btn-success {
       margin: 0.5rem; 
       background-color: transparent; 
       border: 2px solid #9526F3; 
       border-radius: 25px; 
       padding: 6px 30px; 
       color: #9526F3; 
       cursor: pointer; 
       position: relative; 
       overflow: hidden; 
       transition: color 0.35s ease, box-shadow 0.35s ease, background-color 0.35s ease, border-color 0.35s ease;
       outline: none; /* kills default focus glow */
    }

    .btn-success:hover,
    .btn-success:focus,
    .btn-success:active,
    .btn-success.active,
    .btn-success:focus-visible,
    .btn-check:checked + .btn-success,
    .btn-check:active + .btn-success,
    .show > .btn-success.dropdown-toggle {
      background-color: #9526F3;
      border-color: #9526F3;
      color: #fff;
      box-shadow: 0 0 10px #9526f38c;
    }

    .btn-success:focus,
    .btn-success:focus-visible,
    .btn-check:checked + .btn-success:focus,
    .btn-check:active + .btn-success:focus {
      box-shadow: 0 0 0 0.2rem rgba(149, 38, 243, 0.25);
    }

    .filter-bar {
      display: flex;
      gap: 12px;
      background: #111;
      padding: 14px;
      border-radius: 14px;
      align-items: center;
    }

    .filter-item {
      flex: 1;
    }

    .filter-item select,
    .search-box input {
      width: 100%;
      height: 44px;
      border-radius: 10px;
      border: 1px solid #333;
      background: #1c1c1c;
      color: #fff;
      padding: 0 14px;
    }

    .search-box {
      position: relative;
      flex: 2;
    }

    .search-box i {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: #aaa;
    }

    .search-box input {
      padding-left: 40px;
    }

    .sort-dropdown {
      position: relative;
    }

    .sort-toggle {
      width: 100%;
      height: 44px;
      border-radius: 10px;
      border: 1px solid #333;
      background: #1c1c1c;
      color: #fff;
      padding: 0 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      cursor: pointer;
    }

    .sort-toggle i {
      transition: transform 0.25s ease;
    }

    .sort-dropdown.open .sort-toggle i {
      transform: rotate(180deg);
    }

    .sort-menu {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      width: min(340px, 92vw);
      background: #111;
      border: 1px solid #333;
      border-radius: 14px;
      padding: 16px;
      box-shadow: 0 18px 35px rgba(0, 0, 0, 0.35);
      display: none;
      z-index: 50;
    }

    .sort-dropdown.open .sort-menu {
      display: block;
    }

    .sort-group {
      margin-bottom: 14px;
    }

    .sort-group:last-child {
      margin-bottom: 0;
    }

    .sort-group label {
      display: block;
      color: #bbb;
      font-size: 0.9rem;
      margin-bottom: 6px;
      text-align: left;
    }

    .sort-actions {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      margin-top: 16px;
    }

    .sort-actions button {
      flex: 1;
      height: 42px;
      border-radius: 10px;
      border: 1px solid #9526F3;
      background: transparent;
      color: #9526F3;
      transition: all 0.25s ease;
    }

    .sort-actions button:hover {
      background: #9526F3;
      color: #fff;
      box-shadow: 0 0 14px #9526f359;
    }

    @media (max-width: 768px) {
      .filter-bar {
        flex-direction: column;
        align-items: stretch;
      }

      .search-box,
      .filter-item {
        width: 100%;
      }

      .sort-menu {
        left: 0;
        right: auto;
        width: 100%;
      }
    }
  </style>
</head>

<body>

  <div class="container">
    <br><br>
    <h2>Elite Grounds</h2>

    <div class="filter-bar shadow-sm">
      <div class="filter-item search-box">
        <i class="bi bi-search"></i>
        <input type="text" id="searchBox" placeholder="Search turf or location">
      </div>

      <div class="filter-item sort-dropdown" id="sortDropdown">
        <button type="button" class="sort-toggle" id="sortToggle">
          <span><i class="bi bi-funnel-fill"></i> Sort</span>
          <i class="bi bi-chevron-down"></i>
        </button>

        <div class="sort-menu" id="sortMenu">
          <div class="sort-group">
            <label for="cityFilter">Location</label>
            <select id="cityFilter"></select>
          </div>

          <div class="sort-group">
            <label for="sportFilter">Sport</label>
            <select id="sportFilter"></select>
          </div>

          <div class="sort-group">
            <label for="distanceFilter">Distance</label>
            <select id="distanceFilter">
              <option value="">Distance</option>
              <option value="5">Within 5 km</option>
              <option value="10">Within 10 km</option>
              <option value="25">Within 25 km</option>
            </select>
          </div>

          <div class="sort-actions">
            <button type="button" id="clearSortBtn">Clear</button>
            <button type="button" id="applySortBtn">Apply</button>
          </div>
        </div>
      </div>
    </div>


    <!-- Turf Cards -->
    <div class="row mt-5" id="turfContainer">
      <!-- Cards will load here via AJAX -->
    </div>
  </div>
  <script>
    let userLat = null;
    let userLng = null;
    $(document).ready(function () {

      loadCities();
      loadSports();
      loadTurfs();

      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          function (pos) {
            userLat = pos.coords.latitude;
            userLng = pos.coords.longitude;
            loadTurfs(); // load with distance
          },
          function () {
            loadTurfs(); // load without distance
          }
        );
      } else {
        loadTurfs();
      }

      $('#searchBox').on('keyup', function () {
        loadTurfs();
      });

      $('#cityFilter, #sportFilter, #distanceFilter').on('change', function () {
        loadTurfs();
      });

      $('#sortToggle').on('click', function (e) {
        e.stopPropagation();
        $('#sortDropdown').toggleClass('open');
      });

      $('#sortMenu').on('click', function (e) {
        e.stopPropagation();
      });

      $('#applySortBtn').on('click', function () {
        loadTurfs();
        $('#sortDropdown').removeClass('open');
      });

      $('#clearSortBtn').on('click', function () {
        $('#cityFilter').val('');
        $('#sportFilter').val('');
        $('#distanceFilter').val('');
        loadTurfs();
      });

      $(document).on('click', function () {
        $('#sortDropdown').removeClass('open');
      });

    });

    /* ================= LOAD TURFS ================= */
    function loadTurfs() {
      $.ajax({
        url: 'apiSearch/APIfetch_turfs.php',
        method: 'POST',
        data: {
          search: $('#searchBox').val(),
          city: $('#cityFilter').val(),
          sport: $('#sportFilter').val(),
          distance: $('#distanceFilter').val(),
          lat: userLat,
          lng: userLng
        },
        success: function (res) {
          $('#turfContainer').html(res);
        }
      });
    }


    /* ================= LOAD CITIES ================= */
    function loadCities() {
      $.ajax({
        url: 'apiSearch/APIfetch_cities.php',
        success: function (res) {
          $('#cityFilter').html(res);
        }
      });
    }

    /* ================= LOAD SPORTS ================= */
    function loadSports() {
      $.ajax({
        url: 'apiSearch/APIfetch_sports.php',
        success: function (res) {
          $('#sportFilter').html(res);
        }
      });
    }

  </script><br><br><br><br><br>
</body>

</html>