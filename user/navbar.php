<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Elite Grounds</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="../whole.css" rel="stylesheet">
    <link rel="shortcut icon" href="../favicon.png" type="image/png">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        /* ================= BODY ================= */
        /* =======================
   GLOBAL
======================= */
         body { 
  background-color: #0e0f11; 
  background-image: linear-gradient(45deg, #1f1f1f 25%, transparent 25%), 
                    linear-gradient(-45deg, #1f1f1f 25%, transparent 25%), 
                    linear-gradient(45deg, transparent 75%, #1f1f1f 75%),
                    linear-gradient(-45deg, transparent 75%, #1f1f1f 75%); 
   background-size: 6px 6px; 
   background-position: 0 0, 0 3px, 3px -3px, -3px 0px; 
  } 

        /* =======================
   LAYOUT
======================= */
        .layout {
            height: 100vh;
            width: 100vw;
        }

        /* =======================
   NAVBAR
======================= */
        .navbar-top {
            min-height: 72px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(90deg,
                    rgba(18, 18, 18, 0.98),
                    rgba(18, 18, 18, 0.9));
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.6rem;
            z-index: 1000;
            border-bottom: 1px solid var(--border-soft);
            flex-wrap: nowrap;
        }

        /* =======================
   LOGO
======================= */
        .logo {
            color: var(--text-light);
            opacity: 0.95;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .logo i {
            font-size: 2rem;
            color: #ffff;
        }

        .logo small {
            font-size: 0.7rem;
            letter-spacing: 0.6px;
            color: var(--muted-text);
        }

        .logoname {
            color: #ffff;
        }


        /* =======================
   NAV LINKS
======================= */
        .nav-links {
            position: absolute;
            top: calc(100% + 12px);
            right: 1.6rem;
            min-width: 240px;
            max-width: min(360px, calc(100vw - 2rem));
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            border-radius: 22px;
            background: rgba(18, 18, 18, 0.96);
            border: 1px solid rgba(149, 38, 243, 0.3);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(12px);
        }

        .menu-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border: 1px solid rgba(149, 38, 243, 0.45);
            border-radius: 14px;
            background: rgba(149, 38, 243, 0.12);
            color: #ffffff;
            font-size: 1.6rem;
            padding: 0;
            line-height: 1;
            transition: background 0.25s ease, border-color 0.25s ease, transform 0.25s ease;
        }

        .menu-toggle:hover {
            background: rgba(149, 38, 243, 0.22);
            border-color: rgba(180, 76, 255, 0.7);
            transform: translateY(-1px);
        }

        .menu-toggle:focus {
            outline: none;
            box-shadow: none;
        }

        .navbar-top.menu-open .nav-links {
            display: flex;
        }

        .navbar-top.menu-open .menu-toggle {
            background: rgba(149, 38, 243, 0.28);
            border-color: rgba(180, 76, 255, 0.8);
        }

        .navbar-top a {
            width: 100%;
            max-width: 100%;
            justify-content: flex-start;
            padding: 12px 16px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #9526F3;
            font-size: 1rem;
            text-decoration: none;
            border: 2px solid #9526F3;
            background: transparent;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            transition:
                color 0.35s ease,
                box-shadow 0.35s ease;
        }

        .navbar-top a::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #9526F3, #7a1fd6, #b44cff);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
            z-index: 0;
        }

        .navbar-top a span {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
        }

        .navbar-top a:hover {
            color: #ffffff;
            box-shadow: 0 0 18px rgba(149, 38, 243, 0.55);
        }

        .navbar-top a:hover::before {
            transform: scaleX(1);
        }

        .navbar-top a:focus,
        .navbar-top a:active {
            outline: none;
            box-shadow: none;
        }

        .navbar-top a.active {
            background-color: transparent;
            border: 2px solid #9526F3;
            color: #ffffff;
        }

        .nav-menu-link {
            flex-shrink: 1;
        }

        /* =======================
   MAIN FRAME
======================= */
        #mainFrame {
            margin-top: 72px;
            width: 100%;
            height: calc(100vh - 72px);
            border: none;
            overflow-y: auto;
            background: var(--bg-dark);
        }

        @media (max-width: 991px) {
            .navbar-top {
                padding: 0.9rem 1rem;
                align-items: center;
            }

            .nav-links {
                width: 100%;
                right: 1rem;
                left: 1rem;
                min-width: 0;
                max-width: none;
            }

            .navbar-top a {
                justify-content: flex-start;
            }

            #mainFrame {
                margin-top: 88px;
                height: calc(100vh - 88px);
            }
        }
    </style>
</head>

<body>

    <div class="layout">

        <div class="navbar-top">
            <div class="logo">
                <i class="bi bi-dribbble"></i>
                <div class="logoname">
                    SportSync
                    <small>Elite Grounds</small>
                </div>
            </div>

            <button class="menu-toggle" type="button" id="menuToggle" aria-label="Toggle navigation" aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>

            <div class="nav-links" id="userNavMenu">
                <a href="user_home.php" class="nav-menu-link active" target="mainFrame" title="Explore">
                    <span><i class="bi bi-search"></i>Explore</span>
                </a>

                <a href="tournaments_list.php" class="nav-menu-link" target="mainFrame" title="Tournament Participation">
                    <span><i class="bi bi-trophy-fill"></i>Tournaments</span>
                </a>

                <a href="user_settings.php" class="nav-menu-link" title="User Settings">
                    <span><i class="bi bi-gear-fill"></i>Settings</span>
                </a>

                <a href="userbooking.php" class="nav-menu-link" target="mainFrame" title="Previous Bookings">
                    <span><i class="bi bi-clock-history"></i>Bookings</span>
                </a>

                <a href="../index.php" class="nav-menu-link" title="Home">
                    <span><i class="bi bi-house-fill"></i>Home</span>
                </a>
            </div>
        </div>

        <iframe name="mainFrame" id="mainFrame" src="user_home.php"></iframe>

    </div>

    <?php include("../footer.php"); ?>

    <script>
        const menuToggle = document.getElementById("menuToggle");
        const navbarTop = document.querySelector(".navbar-top");
        const navMenu = document.getElementById("userNavMenu");
        const navLinks = document.querySelectorAll(".nav-menu-link");

        if (menuToggle && navbarTop) {
            menuToggle.addEventListener("click", function () {
                const isOpen = navbarTop.classList.toggle("menu-open");
                menuToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
            });

            navLinks.forEach(function (link) {
                link.addEventListener("click", function () {
                    const pageTarget = link.getAttribute("target");
                    if (pageTarget === "mainFrame") {
                        navLinks.forEach(function (item) {
                            item.classList.remove("active");
                        });
                        link.classList.add("active");
                    }

                    if (navMenu && navMenu.contains(link)) {
                        navbarTop.classList.remove("menu-open");
                        menuToggle.setAttribute("aria-expanded", "false");
                    }
                });
            });
        }

        document.addEventListener("click", function (event) {
            if (!navbarTop || !navbarTop.classList.contains("menu-open")) {
                return;
            }

            if (!navbarTop.contains(event.target)) {
                navbarTop.classList.remove("menu-open");
                menuToggle.setAttribute("aria-expanded", "false");
            }
        });
    </script>

</body>

</html>
