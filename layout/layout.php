<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? "NinjaVan PH" ?> | NinjaVan</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        /* ===========================
           NINJAVAN BRAND VARIABLES
        =========================== */
        :root {
            --nv-red:       #e8002d;
            --nv-red-dark:  #b80024;
            --nv-red-light: #ff1a45;
            --nv-ink:       #0d0d0d;
            --nv-ink-soft:  #1a1a1a;
            --nv-gray:      #f5f5f5;
            --nv-muted:     #6b7280;
            --nv-border:    #e5e7eb;
            --nv-white:     #ffffff;
            --radius-lg:    16px;
            --radius-md:    10px;
            --radius-sm:    6px;
            --shadow-card:  0 4px 24px rgba(0,0,0,0.08);
            --shadow-hover: 0 8px 40px rgba(232,0,45,0.18);
            --transition:   all 0.28s cubic-bezier(0.4,0,0.2,1);
        }

        /* ===========================
           BASE
        =========================== */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: #ffffff;
            color: var(--nv-ink);
            padding-top: 72px;
            overflow-x: hidden;
        }

        h1,h2,h3,h4,h5,h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        /* ===========================
           NAVBAR
        =========================== */
        .nv-navbar {
            background: #ffffff;
            border-bottom: 1px solid var(--nv-border);
            padding: 0;
            height: 72px;
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            box-shadow: 0 1px 12px rgba(0,0,0,0.06);
        }

        .nv-navbar .container {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nv-brand {
            font-family: 'Poppins', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: var(--nv-ink) !important;
            text-decoration: none;
            letter-spacing: -0.04em;
        }

        .nv-brand span {
            color: var(--nv-red);
        }

        .nv-nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nv-nav-item { position: relative; }

        .nv-nav-link {
            color: #374151;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            white-space: nowrap;
        }

        .nv-nav-link:hover,
        .nv-nav-item:hover > .nv-nav-link {
            color: var(--nv-red);
            background: rgba(232,0,45,0.06);
        }

        .nv-nav-link .nav-chevron {
            font-size: 10px;
            transition: transform 0.2s ease;
        }

        .nv-nav-item:hover > .nv-nav-link .nav-chevron {
            transform: rotate(180deg);
        }

        /* ===========================
           DROPDOWN MENUS
        =========================== */
        .nv-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            border: 1px solid var(--nv-border);
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 60px rgba(0,0,0,0.12), 0 4px 16px rgba(0,0,0,0.06);
            padding: 8px;
            min-width: 220px;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s;
            transform: translateX(-50%) translateY(-6px);
            z-index: 500;
        }

        /* Wide mega dropdown */
        .nv-dropdown.mega {
            min-width: 560px;
            padding: 20px;
        }

        .nv-dropdown::before {
            content: '';
            position: absolute;
            top: -6px;
            left: 50%;
            transform: translateX(-50%);
            width: 12px; height: 12px;
            background: #fff;
            border-left: 1px solid var(--nv-border);
            border-top: 1px solid var(--nv-border);
            transform: translateX(-50%) rotate(45deg);
        }

        .nv-nav-item:hover > .nv-dropdown {
            opacity: 1;
            visibility: visible;
            pointer-events: all;
            transform: translateX(-50%) translateY(0);
        }

        /* Standard dropdown item */
        .dd-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--nv-ink);
            transition: var(--transition);
        }

        .dd-item:hover {
            background: rgba(232,0,45,0.05);
            color: var(--nv-red);
        }

        .dd-icon {
            width: 36px; height: 36px;
            border-radius: 8px;
            background: rgba(232,0,45,0.08);
            color: var(--nv-red);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
            transition: var(--transition);
        }

        .dd-item:hover .dd-icon {
            background: var(--nv-red);
            color: #fff;
        }

        .dd-text-title {
            font-size: 13px;
            font-weight: 600;
            color: inherit;
            line-height: 1.2;
        }

        .dd-text-sub {
            font-size: 11px;
            color: var(--nv-muted);
            line-height: 1.4;
            margin-top: 2px;
        }

        .dd-divider { height: 1px; background: var(--nv-border); margin: 6px 4px; }

        /* Mega dropdown grid */
        .mega-cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .mega-col-header {
            font-size: 10px;
            font-weight: 700;
            color: var(--nv-muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 0 4px 8px;
            border-bottom: 1px solid var(--nv-border);
            margin-bottom: 8px;
        }

        /* Track Parcel panel */
        .track-dd-panel {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .track-dd-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--nv-ink);
            margin-bottom: 4px;
        }

        .track-dd-sub {
            font-size: 12px;
            color: var(--nv-muted);
            line-height: 1.5;
        }

        .track-dd-input-wrap {
            display: flex;
            gap: 8px;
            margin-top: 6px;
        }

        .track-dd-input {
            flex: 1;
            border: 1.5px solid var(--nv-border);
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 13px;
            outline: none;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
        }

        .track-dd-input:focus { border-color: var(--nv-red); box-shadow: 0 0 0 3px rgba(232,0,45,0.08); }

        .track-status-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px; }

        .track-status-step {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--nv-muted);
            padding: 5px 8px;
            border-radius: 6px;
        }

        .track-status-step .step-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--nv-border);
            flex-shrink: 0;
        }

        .track-status-step.active { color: var(--nv-ink); font-weight: 600; background: rgba(232,0,45,0.05); }
        .track-status-step.active .step-dot { background: var(--nv-red); box-shadow: 0 0 0 3px rgba(232,0,45,0.2); }

        .nv-nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ===========================
           BUTTONS
        =========================== */
        .btn-nv {
            background: var(--nv-red);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 10px 24px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-nv:hover {
            background: var(--nv-red-dark);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: var(--shadow-hover);
        }

        .btn-nv-outline {
            background: transparent;
            color: var(--nv-ink);
            border: 2px solid var(--nv-border);
            border-radius: 50px;
            padding: 9px 22px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-nv-outline:hover {
            border-color: var(--nv-red);
            color: var(--nv-red);
        }

        .btn-nv-outline-light {
            background: transparent;
            color: #fff;
            border: 2px solid rgba(255,255,255,0.5);
            border-radius: 50px;
            padding: 9px 22px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .btn-nv-outline-light:hover {
            border-color: #fff;
            background: rgba(255,255,255,0.12);
            color: #fff;
        }

        /* ===========================
           CARDS
        =========================== */
        .nv-card {
            background: #fff;
            border: 1px solid var(--nv-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            transition: var(--transition);
        }

        .nv-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-3px);
        }

        /* ===========================
           FOOTER
        =========================== */
        .nv-footer {
            background: var(--nv-ink);
            color: rgba(255,255,255,0.7);
            padding: 64px 0 32px;
        }

        .nv-footer-brand {
            font-family: 'Poppins', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.04em;
            margin-bottom: 12px;
        }

        .nv-footer-brand span { color: var(--nv-red); }

        .nv-footer h6 {
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 16px;
        }

        .nv-footer a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 14px;
            display: block;
            margin-bottom: 8px;
            transition: var(--transition);
        }

        .nv-footer a:hover { color: var(--nv-red); }

        .nv-footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 24px;
            margin-top: 48px;
            font-size: 13px;
            color: rgba(255,255,255,0.4);
        }

        /* ===========================
           TOAST
        =========================== */
        .toast-container { z-index: 9999; }

        /* ===========================
           USER DROPDOWN
        =========================== */
        .nv-user-btn {
            background: var(--nv-red);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 8px 18px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .nv-user-btn:hover {
            background: var(--nv-red-dark);
            color: #fff;
        }

        .dropdown-menu {
            border: 1px solid var(--nv-border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-card);
            padding: 8px;
        }

        .dropdown-item {
            border-radius: var(--radius-sm);
            font-size: 14px;
            padding: 8px 14px;
        }

        .dropdown-item:hover {
            background: rgba(232,0,45,0.06);
            color: var(--nv-red);
        }
    </style>
</head>
<body>

<!-- ===========================
     NAVBAR
=========================== -->
<nav class="nv-navbar">
    <div class="container">

        <!-- BRAND -->
        <a href="/ninjavan/index.php" class="nv-brand">
            ninja<span>van</span>
        </a>

        <!-- NAV LINKS -->
        <ul class="nv-nav-links d-none d-lg-flex">

            <!-- SOLUTIONS -->
            <li class="nv-nav-item">
                <a href="#solutions" class="nv-nav-link">
                    Solutions <i class="bi bi-chevron-down nav-chevron"></i>
                </a>
                <div class="nv-dropdown mega">
                    <div class="mega-cols">
                        <div>
                            <div class="mega-col-header">Delivery Services</div>
                            <a href="#solutions" class="dd-item">
                                <div class="dd-icon"><i class="bi bi-send-fill"></i></div>
                                <div>
                                    <div class="dd-text-title">Ninja Dash</div>
                                    <div class="dd-text-sub">Last-mile e-commerce parcel delivery</div>
                                </div>
                            </a>
                            <a href="#solutions" class="dd-item">
                                <div class="dd-icon"><i class="bi bi-truck"></i></div>
                                <div>
                                    <div class="dd-text-title">Ninja Restock</div>
                                    <div class="dd-text-sub">B2B & direct-to-store replenishment</div>
                                </div>
                            </a>
                        </div>
                        <div>
                            <div class="mega-col-header">Business Solutions</div>
                            <a href="#solutions" class="dd-item">
                                <div class="dd-icon"><i class="bi bi-box-seam"></i></div>
                                <div>
                                    <div class="dd-text-title">Fulfillment & Warehousing</div>
                                    <div class="dd-text-sub">3PL with automated inventory management</div>
                                </div>
                            </a>
                            <a href="#solutions" class="dd-item">
                                <div class="dd-icon"><i class="bi bi-credit-card"></i></div>
                                <div>
                                    <div class="dd-text-title">Card & Document Delivery</div>
                                    <div class="dd-text-sub">Secure delivery for banks & government</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </li>

            <!-- ABOUT US -->
            <li class="nv-nav-item">
                <a href="#" class="nv-nav-link">
                    About Us <i class="bi bi-chevron-down nav-chevron"></i>
                </a>
                <div class="nv-dropdown" style="min-width:240px;">
                    <a href="#" class="dd-item">
                        <div class="dd-icon"><i class="bi bi-building"></i></div>
                        <div>
                            <div class="dd-text-title">Our Company</div>
                            <div class="dd-text-sub">Story, mission & values</div>
                        </div>
                    </a>
                    <a href="#" class="dd-item">
                        <div class="dd-icon"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="dd-text-title">Leadership Team</div>
                            <div class="dd-text-sub">Meet the people behind NinjaVan</div>
                        </div>
                    </a>
                    <a href="#" class="dd-item">
                        <div class="dd-icon"><i class="bi bi-geo-alt-fill"></i></div>
                        <div>
                            <div class="dd-text-title">Coverage Areas</div>
                            <div class="dd-text-sub">Where we operate in the Philippines</div>
                        </div>
                    </a>
                    <div class="dd-divider"></div>
                    <a href="/ninjavan/auth/register.php" class="dd-item">
                        <div class="dd-icon"><i class="bi bi-briefcase-fill"></i></div>
                        <div>
                            <div class="dd-text-title">Careers</div>
                            <div class="dd-text-sub">Join the NinjaVan team</div>
                        </div>
                    </a>
                </div>
            </li>

            <!-- NEWSROOM -->
            <li class="nv-nav-item">
                <a href="#" class="nv-nav-link">
                    Newsroom <i class="bi bi-chevron-down nav-chevron"></i>
                </a>
                <div class="nv-dropdown" style="min-width:240px;">
                    <a href="#" class="dd-item">
                        <div class="dd-icon"><i class="bi bi-newspaper"></i></div>
                        <div>
                            <div class="dd-text-title">Press Releases</div>
                            <div class="dd-text-sub">Latest announcements & milestones</div>
                        </div>
                    </a>
                    <a href="#" class="dd-item">
                        <div class="dd-icon"><i class="bi bi-journal-richtext"></i></div>
                        <div>
                            <div class="dd-text-title">Blog & Insights</div>
                            <div class="dd-text-sub">E-commerce tips and logistics guides</div>
                        </div>
                    </a>
                    <a href="#" class="dd-item">
                        <div class="dd-icon"><i class="bi bi-trophy-fill"></i></div>
                        <div>
                            <div class="dd-text-title">Awards & Recognition</div>
                            <div class="dd-text-sub">Our industry achievements</div>
                        </div>
                    </a>
                </div>
            </li>

            <!-- TRACK PARCEL -->
            <li class="nv-nav-item">
                <a href="/ninjavan/index.php#track" class="nv-nav-link">
                    Track Parcel <i class="bi bi-chevron-down nav-chevron"></i>
                </a>
                <div class="nv-dropdown" style="min-width:340px; padding:20px;">
                    <div class="track-dd-panel">
                        <div>
                            <div class="track-dd-title">Track Your Parcel</div>
                            <div class="track-dd-sub">Enter your tracking number to get real-time updates on your delivery status.</div>
                        </div>
                        <div class="track-dd-input-wrap">
                            <input type="text" class="track-dd-input" id="navTrackInput" placeholder="e.g. NV1234567890PH">
                            <button class="btn-nv" style="padding:9px 16px;font-size:13px;border-radius:8px;" onclick="doNavTrack()">
                                <i class="bi bi-search"></i> Track
                            </button>
                        </div>
                        <div class="dd-divider"></div>
                        <ul class="track-status-list">
                            <li class="track-status-step"><span class="step-dot"></span> Order Confirmed</li>
                            <li class="track-status-step"><span class="step-dot"></span> Picked Up</li>
                            <li class="track-status-step active"><span class="step-dot"></span> Origin Sorting Hub</li>
                            <li class="track-status-step"><span class="step-dot"></span> Out for Delivery</li>
                            <li class="track-status-step"><span class="step-dot"></span> Delivered</li>
                        </ul>
                    </div>
                </div>
            </li>

        </ul>

        <!-- ACTIONS -->
        <div class="nv-nav-actions">
            <?php if(isset($_SESSION['account_id'])): ?>

                <!-- Visible role chip -->
                <div style="display:flex;align-items:center;gap:6px;padding:5px 12px;border:1px solid var(--nv-border);border-radius:50px;font-size:12px;color:var(--nv-muted);">
                    <span style="width:7px;height:7px;border-radius:50%;background:#00b37d;display:inline-block;"></span>
                    <?= ucfirst($_SESSION['role'] ?? 'user') ?>
                </div>

                <!-- Visible Dashboard button -->
                <a href="/ninjavan/<?= strtolower($_SESSION['role'] ?? 'shipper') ?>/dashboard.php"
                   class="btn-nv" style="gap:7px;">
                    <i class="bi bi-grid-fill"></i>
                    Dashboard
                </a>

                <!-- Logout link -->
                <a href="/ninjavan/auth/logout.php" class="btn-nv-outline" style="font-size:13px;padding:8px 16px;">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>

            <?php else: ?>

                <a href="/ninjavan/auth/login.php" class="btn-nv-outline">
                    Log in
                </a>
                <a href="/ninjavan/auth/register.php" class="btn-nv">
                    Sign up free
                </a>

            <?php endif; ?>
        </div>

    </div>
</nav>

<!-- PROFILE MODAL -->
<?php if(isset($_SESSION['account_id'])): ?>
<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 shadow border-0">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">My Profile</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <div style="width:72px;height:72px;background:var(--nv-red);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;">
                        <i class="bi bi-person-fill text-white" style="font-size:32px;"></i>
                    </div>
                </div>
                <h5 class="fw-bold mb-1"><?= htmlspecialchars($_SESSION['display_name'] ?? '') ?></h5>
                <span class="badge rounded-pill" style="background:var(--nv-red);">
                    <?= ucfirst($_SESSION['role'] ?? '') ?>
                </span>
                <div class="mt-3 text-muted" style="font-size:14px;">
                    <p class="mb-1"><i class="bi bi-envelope me-2"></i><?= htmlspecialchars($_SESSION['email'] ?? '') ?></p>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button class="btn-nv" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>
<?php endif; ?>

<!-- MAIN CONTENT STARTS HERE -->
