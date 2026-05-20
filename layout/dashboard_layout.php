<?php
if(session_status() === PHP_SESSION_NONE) session_start();

// $title, $activePage, $role must be set before including this file
$role       = $_SESSION['role']         ?? '';
$name       = $_SESSION['display_name'] ?? 'User';
$email      = $_SESSION['email']        ?? '';
$activePage = $activePage               ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Dashboard') ?> — NinjaVan</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <?php if (!empty($extraHead)) echo $extraHead; ?>

    <style>
        /* =============================================
           NINJAVAN DASHBOARD — DESIGN SYSTEM
        ============================================= */
        :root {
            --red:          #e8002d;
            --red-dark:     #b80024;
            --red-glow:     rgba(232,0,45,0.18);
            --ink:          #0a0a0a;
            --ink-2:        #1c1c1e;
            --ink-3:        #2c2c2e;
            --surface:      #f8f7f5;
            --surface-2:    #ffffff;
            --border:       #e8e6e1;
            --border-2:     #d4d0c8;
            --muted:        #8a8580;
            --muted-2:      #b8b4ad;
            --green:        #00b37d;
            --green-soft:   rgba(0,179,125,0.1);
            --amber:        #f59e0b;
            --amber-soft:   rgba(245,158,11,0.1);
            --blue:         #3b82f6;
            --blue-soft:    rgba(59,130,246,0.1);

            /* Sidebar */
            --sb-w:         260px;
            --sb-bg:        #0a0a0a;
            --sb-border:    rgba(255,255,255,0.07);
            --sb-text:      rgba(255,255,255,0.55);
            --sb-text-h:    #ffffff;
            --sb-active-bg: rgba(232,0,45,0.12);

            --nav-h:        64px;
            --radius:       14px;
            --radius-sm:    8px;
            --shadow:       0 1px 3px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.05);
            --shadow-lg:    0 8px 32px rgba(0,0,0,0.1);
            --trans:        all 0.22s cubic-bezier(0.4,0,0.2,1);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--surface);
            color: var(--ink);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        h1,h2,h3,h4,h5,h6 {
            font-family: 'Sora', sans-serif;
            font-weight: 700;
        }

        /* =============================================
           TOP NAVBAR
        ============================================= */
        .nv-topbar {
            position: fixed;
            top: 0; left: var(--sb-w); right: 0;
            height: var(--nav-h);
            background: rgba(248,247,245,0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            z-index: 100;
            transition: left 0.3s ease;
        }

        .topbar-left { display: flex; align-items: center; gap: 12px; }

        .topbar-page-title {
            font-family: 'Sora', sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: var(--ink);
        }

        .topbar-breadcrumb {
            font-size: 13px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Notification bell */
        .topbar-icon-btn {
            width: 38px; height: 38px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: var(--surface-2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 16px;
            cursor: pointer;
            transition: var(--trans);
            text-decoration: none;
            position: relative;
        }

        .topbar-icon-btn:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(232,0,45,0.04);
        }

        .notif-badge {
            position: absolute;
            top: -5px; right: -5px;
            min-width: 18px; height: 18px;
            background: var(--red);
            color: #fff;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            font-family: 'Sora', sans-serif;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            border: 2px solid var(--surface);
            z-index: 2;
        }
        .notif-badge.show { display: flex; }

        /* Notification dropdown */
        .notif-dropdown {
            position: absolute;
            top: calc(100% + 10px); right: 0;
            width: 360px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            z-index: 9999;
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
            overflow: hidden;
        }
        .notif-dropdown.open {
            opacity: 1;
            transform: translateY(0);
            pointer-events: all;
        }
        .notif-hdr {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px 10px;
            border-bottom: 1px solid var(--border);
        }
        .notif-hdr-title {
            font-family: 'Sora', sans-serif;
            font-size: 14px;
            font-weight: 700;
        }
        .notif-mark-all {
            font-size: 11px;
            color: var(--red);
            font-weight: 600;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            transition: opacity 0.2s;
        }
        .notif-mark-all:hover { opacity: 0.7; }
        .notif-list {
            max-height: 380px;
            overflow-y: auto;
        }
        .notif-item {
            display: flex;
            gap: 12px;
            padding: 12px 16px;
            cursor: pointer;
            transition: background 0.15s;
            border-bottom: 1px solid var(--border);
            text-decoration: none;
            color: var(--ink);
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: rgba(232,0,45,0.03); }
        .notif-item.unread { background: rgba(232,0,45,0.04); }
        .notif-item.unread:hover { background: rgba(232,0,45,0.07); }
        .notif-icon-wrap {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: rgba(232,0,45,0.08);
            color: var(--red);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }
        .notif-item-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 2px;
            line-height: 1.3;
        }
        .notif-item-body {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .notif-item-ago {
            font-size: 11px;
            color: var(--muted-2);
            margin-top: 4px;
        }
        .notif-unread-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--red);
            flex-shrink: 0;
            margin-top: 5px;
        }
        .notif-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
        }
        .notif-footer {
            padding: 10px 16px;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: 12px;
            color: var(--muted);
        }

        /* User avatar button */
        .topbar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 10px 6px 6px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            background: var(--surface-2);
            cursor: pointer;
            transition: var(--trans);
            text-decoration: none;
        }

        .topbar-user:hover {
            border-color: var(--red);
            box-shadow: 0 0 0 3px var(--red-glow);
        }

        .user-avatar {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--red), #ff4d6d);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Sora', sans-serif;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        .user-info { line-height: 1.2; }

        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
            white-space: nowrap;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 11px;
            color: var(--muted);
            text-transform: capitalize;
        }

        /* =============================================
           SIDEBAR
        ============================================= */
        .nv-sidebar {
            position: fixed;
            top: 0; left: 0; bottom: 0;
            width: var(--sb-w);
            background: var(--sb-bg);
            border-right: 1px solid var(--sb-border);
            display: flex;
            flex-direction: column;
            z-index: 200;
            overflow: hidden;
        }

        /* Subtle noise texture overlay */
        .nv-sidebar::before {
            content: '';
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
        }

        /* Glow accent at bottom */
        .nv-sidebar::after {
            content: '';
            position: absolute;
            bottom: -80px; left: -40px;
            width: 280px; height: 280px;
            background: radial-gradient(circle, rgba(232,0,45,0.12) 0%, transparent 65%);
            pointer-events: none;
            z-index: 0;
        }

        .sb-inner {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 0;
        }

        /* Brand */
        .sb-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px 20px 16px;
            border-bottom: 1px solid var(--sb-border);
            text-decoration: none;
        }

        .sb-brand-icon {
            width: 36px; height: 36px;
            background: var(--red);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(232,0,45,0.4);
        }

        .sb-brand-text {
            font-family: 'Sora', sans-serif;
            font-size: 18px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.04em;
        }

        .sb-brand-text span { color: var(--red); }

        /* Role badge */
        .sb-role-badge {
            margin: 14px 16px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--sb-border);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sb-role-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 6px var(--green);
            flex-shrink: 0;
        }

        .sb-role-label {
            font-size: 12px;
            color: rgba(255,255,255,0.4);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }

        .sb-role-name {
            font-size: 13px;
            color: #fff;
            font-weight: 600;
        }

        /* Nav section label */
        .sb-section-label {
            padding: 16px 20px 6px;
            font-size: 10px;
            font-weight: 700;
            color: rgba(255,255,255,0.22);
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        /* Nav links */
        .sb-nav { 
            flex: 1;
            overflow-y: auto;
            padding: 0 10px;
        }

        .sb-nav::-webkit-scrollbar { width: 0; }

        .sb-link {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 12px;
            border-radius: 10px;
            color: var(--sb-text);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: var(--trans);
            position: relative;
            margin-bottom: 2px;
        }

        .sb-link:hover {
            color: var(--sb-text-h);
            background: rgba(255,255,255,0.06);
        }

        .sb-link.active {
            color: #fff;
            background: var(--sb-active-bg);
            font-weight: 600;
        }

        .sb-link.active::before {
            content: '';
            position: absolute;
            left: 0; top: 25%; bottom: 25%;
            width: 3px;
            background: var(--red);
            border-radius: 0 3px 3px 0;
        }

        .sb-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            background: rgba(255,255,255,0.06);
            flex-shrink: 0;
            transition: var(--trans);
        }

        .sb-link:hover .sb-icon {
            background: rgba(255,255,255,0.1);
        }

        .sb-link.active .sb-icon {
            background: var(--red);
            box-shadow: 0 4px 10px rgba(232,0,45,0.35);
            color: #fff;
        }

        .sb-badge {
            margin-left: auto;
            background: var(--red);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
            min-width: 20px;
            text-align: center;
        }

        /* Divider */
        .sb-divider {
            height: 1px;
            background: var(--sb-border);
            margin: 8px 16px;
        }

        /* Bottom actions */
        .sb-bottom {
            padding: 12px 10px;
            border-top: 1px solid var(--sb-border);
        }

        .sb-link-danger {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 12px;
            border-radius: 10px;
            color: rgba(255,80,80,0.65);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: var(--trans);
            margin-bottom: 2px;
        }

        .sb-link-danger:hover {
            color: #ff6b6b;
            background: rgba(255,80,80,0.08);
        }

        /* =============================================
           MAIN CONTENT AREA
        ============================================= */
        .nv-main {
            margin-left: var(--sb-w);
            margin-top: var(--nav-h);
            padding: 32px 36px;
            min-height: calc(100vh - var(--nav-h));
            flex: 1;
        }

        /* =============================================
           CARDS
        ============================================= */
        .nv-card {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        /* Stat cards */
        .stat-card {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px 24px;
            box-shadow: var(--shadow);
            transition: var(--trans);
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: -30px; right: -30px;
            width: 90px; height: 90px;
            border-radius: 50%;
            opacity: 0.06;
            transition: var(--trans);
        }

        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .stat-card:hover::after { transform: scale(1.3); opacity: 0.1; }

        .stat-card.red::after   { background: var(--red); }
        .stat-card.green::after { background: var(--green); }
        .stat-card.amber::after { background: var(--amber); }
        .stat-card.blue::after  { background: var(--blue); }

        .stat-icon {
            width: 44px; height: 44px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            margin-bottom: 16px;
        }

        .stat-icon.red   { background: rgba(232,0,45,0.08);   color: var(--red); }
        .stat-icon.green { background: var(--green-soft);      color: var(--green); }
        .stat-icon.amber { background: var(--amber-soft);      color: var(--amber); }
        .stat-icon.blue  { background: var(--blue-soft);       color: var(--blue); }

        .stat-value {
            font-family: 'Sora', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: var(--ink);
            line-height: 1;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }

        .stat-label { font-size: 13px; color: var(--muted); }

        .stat-trend {
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            margin-top: 8px;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .stat-trend.up   { background: var(--green-soft); color: var(--green); }
        .stat-trend.down { background: rgba(239,68,68,0.08); color: #ef4444; }
        .stat-trend.neu  { background: rgba(138,133,128,0.08); color: var(--muted); }

        /* =============================================
           TABLE STYLES
        ============================================= */
        .nv-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .nv-table thead th {
            font-family: 'Sora', sans-serif;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--muted);
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            white-space: nowrap;
        }

        .nv-table thead th:first-child { border-radius: var(--radius-sm) 0 0 0; }
        .nv-table thead th:last-child  { border-radius: 0 var(--radius-sm) 0 0; }

        .nv-table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 13.5px;
            color: var(--ink);
            vertical-align: middle;
        }

        .nv-table tbody tr:last-child td { border-bottom: none; }

        .nv-table tbody tr {
            transition: background 0.15s;
        }

        .nv-table tbody tr:hover td {
            background: rgba(232,0,45,0.02);
        }

        /* =============================================
           BADGES / STATUS
        ============================================= */
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .badge-status::before {
            content: '';
            width: 5px; height: 5px;
            border-radius: 50%;
            background: currentColor;
        }

        .badge-pending   { background: var(--amber-soft); color: #b45309; }
        .badge-confirmed { background: var(--blue-soft);  color: #1d4ed8; }
        .badge-picked    { background: rgba(139,92,246,0.1); color: #7c3aed; }
        .badge-transit   { background: rgba(6,182,212,0.1); color: #0891b2; }
        .badge-delivery  { background: rgba(249,115,22,0.1); color: #c2410c; }
        .badge-delivered { background: var(--green-soft); color: #15803d; }
        .badge-failed    { background: rgba(239,68,68,0.08); color: #dc2626; }
        .badge-cancelled { background: rgba(138,133,128,0.08); color: var(--muted); }
        .badge-active    { background: var(--green-soft); color: #15803d; }
        .badge-inactive  { background: rgba(138,133,128,0.08); color: var(--muted); }

        /* =============================================
           BUTTONS
        ============================================= */
        .btn-nv {
            background: var(--red);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 9px 20px;
            font-family: 'Sora', sans-serif;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: var(--trans);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-nv:hover {
            background: var(--red-dark);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(232,0,45,0.3);
        }

        .btn-nv-ghost {
            background: transparent;
            color: var(--ink);
            border: 1.5px solid var(--border);
            border-radius: 50px;
            padding: 8px 18px;
            font-family: 'Sora', sans-serif;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: var(--trans);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-nv-ghost:hover {
            border-color: var(--red);
            color: var(--red);
        }

        .btn-icon {
            width: 34px; height: 34px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 15px;
            cursor: pointer;
            transition: var(--trans);
            text-decoration: none;
        }

        .btn-icon:hover { border-color: var(--red); color: var(--red); background: rgba(232,0,45,0.04); }
        .btn-icon.danger:hover { border-color: #dc2626; color: #dc2626; background: rgba(220,38,38,0.04); }

        /* =============================================
           MODAL OVERRIDES
        ============================================= */
        .modal-content {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
        }

        .modal-header { border-bottom: 1px solid var(--border); padding: 18px 24px; }
        .modal-body   { padding: 24px; }
        .modal-footer { border-top: 1px solid var(--border); padding: 16px 24px; }

        .modal-title {
            font-family: 'Sora', sans-serif;
            font-size: 16px; font-weight: 700;
        }

        /* Form fields in modals */
        .nv-form-group { margin-bottom: 18px; }

        .nv-form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .nv-input {
            width: 100%;
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            font-size: 14px;
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            outline: none;
            transition: var(--trans);
        }

        .nv-input:focus {
            border-color: var(--red);
            background: #fff;
            box-shadow: 0 0 0 3px var(--red-glow);
        }

        /* =============================================
           PAGE HEADER
        ============================================= */
        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 28px;
            gap: 16px;
        }

        .page-header h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.02em;
            margin: 0 0 4px;
        }

        .page-header p {
            font-size: 14px;
            color: var(--muted);
            margin: 0;
        }

        /* =============================================
           TOAST
        ============================================= */
        .nv-toast-wrap {
            position: fixed;
            bottom: 24px; right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .nv-toast {
            background: var(--ink-2);
            color: #fff;
            border-radius: var(--radius);
            padding: 14px 18px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
            border-left: 3px solid var(--green);
            min-width: 280px;
        }

        .nv-toast.error { border-left-color: var(--red); }

        @keyframes slideUp {
            from { opacity:0; transform: translateY(16px); }
            to   { opacity:1; transform: translateY(0); }
        }

        /* =============================================
           TRACKING TIMELINE
        ============================================= */
        .track-timeline { padding: 0; list-style: none; }

        .track-item {
            display: flex;
            gap: 16px;
            padding-bottom: 24px;
            position: relative;
        }

        .track-item:not(:last-child) .track-dot::after {
            content: '';
            position: absolute;
            top: 28px; left: 11px;
            width: 2px;
            background: var(--border);
            bottom: 0;
        }

        .track-dot {
            position: relative;
            width: 24px;
            flex-shrink: 0;
        }

        .track-dot-circle {
            width: 24px; height: 24px;
            border-radius: 50%;
            border: 2px solid var(--border);
            background: var(--surface-2);
            display: flex; align-items: center; justify-content: center;
            font-size: 10px;
            color: var(--muted);
        }

        .track-dot-circle.active {
            border-color: var(--red);
            background: var(--red);
            color: #fff;
            box-shadow: 0 0 0 4px var(--red-glow);
        }

        .track-dot-circle.done {
            border-color: var(--green);
            background: var(--green);
            color: #fff;
        }

        .track-content { padding-top: 2px; }
        .track-status  { font-size: 14px; font-weight: 600; color: var(--ink); }
        .track-notes   { font-size: 12px; color: var(--muted); margin-top: 2px; }
        .track-time    { font-size: 11px; color: var(--muted-2); margin-top: 4px; }

        /* =============================================
           EMPTY STATE
        ============================================= */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }

        .empty-state-icon {
            width: 72px; height: 72px;
            background: var(--surface);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 30px; color: var(--muted-2);
            margin: 0 auto 16px;
        }

        .empty-state h4 {
            font-size: 16px; color: var(--ink);
            margin-bottom: 6px;
        }

        .empty-state p { font-size: 13px; color: var(--muted); }

        /* =============================================
           MOBILE RESPONSIVE
        ============================================= */
        .sb-toggle { display:none; }
        .sb-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:199; opacity:0; transition:opacity 0.3s; }
        .sb-overlay.show { display:block; opacity:1; }

        @media(max-width:991px) {
            .nv-sidebar { transform:translateX(-100%); transition:transform 0.3s ease; }
            .nv-sidebar.open { transform:translateX(0); }
            .nv-topbar { left:0 !important; }
            .nv-main { margin-left:0 !important; }
            .sb-toggle { display:flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--surface-2); color:var(--ink); font-size:18px; cursor:pointer; margin-right:8px; }
        }
    </style>
</head>
<body>

<!-- =============================================
     SIDEBAR OVERLAY (mobile)
============================================= -->
<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<!-- =============================================
     SIDEBAR
============================================= -->
<aside class="nv-sidebar">
<div class="sb-inner">

    <!-- Brand -->
    <a href="/ninjavan/index.php" class="sb-brand">
        <div class="sb-brand-icon"><i class="bi bi-box-seam-fill"></i></div>
        <span class="sb-brand-text">ninja<span>van</span></span>
    </a>

    <!-- Role badge -->
    <div class="sb-role-badge">
        <div class="sb-role-dot"></div>
        <div>
            <div class="sb-role-label">Logged in as</div>
            <div class="sb-role-name"><?= htmlspecialchars($name) ?></div>
        </div>
    </div>

    <!-- Nav -->
    <nav class="sb-nav">

        <?php if($role === 'admin'): ?>
        <!-- ===== ADMIN NAV ===== -->
        <div class="sb-section-label">Overview</div>

        <a href="/ninjavan/admin/dashboard.php"
           class="sb-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-grid-fill"></i></span>
            Dashboard
        </a>

        <div class="sb-section-label">Management</div>

        <a href="/ninjavan/admin/manage_shippers.php"
           class="sb-link <?= $activePage === 'shippers' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-people-fill"></i></span>
            Shippers
        </a>

        <a href="/ninjavan/admin/manage_riders.php"
           class="sb-link <?= $activePage === 'riders' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-bicycle"></i></span>
            Riders
        </a>

        <a href="/ninjavan/admin/manage_staff.php"
           class="sb-link <?= $activePage === 'staff' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-person-badge-fill"></i></span>
            Staff
        </a>

        <a href="/ninjavan/admin/manage_hubs.php"
           class="sb-link <?= $activePage === 'hubs' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-geo-fill"></i></span>
            Hubs / Branches
        </a>

        <a href="/ninjavan/admin/manage_parcels.php"
           class="sb-link <?= $activePage === 'parcels' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-boxes"></i></span>
            Parcels
        </a>

        <a href="/ninjavan/admin/manage_users.php"
           class="sb-link <?= $activePage === 'users' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-people-fill"></i></span>
            Manage Users
        </a>

        <a href="/ninjavan/admin/manage_orders.php"
           class="sb-link <?= $activePage === 'orders' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-diagram-3-fill"></i></span>
            Orders
            <?php
            if(isset($conn)){
                $ures = $conn->query("SELECT COUNT(*) as c FROM `ORDER` WHERE Ord_Status='Staging'");
                $ucnt = $ures ? ($ures->fetch_assoc()['c'] ?? 0) : 0;
                if($ucnt > 0) echo "<span class='sb-badge'>$ucnt</span>";
            }
            ?>
        </a>

        <div class="sb-section-label">Analytics</div>

        <a href="/ninjavan/admin/reports.php"
           class="sb-link <?= $activePage === 'reports' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-bar-chart-fill"></i></span>
            Reports
        </a>

        <a href="/ninjavan/rider/live_map.php"
           class="sb-link <?= $activePage === 'map' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-geo-alt-fill"></i></span>
            Live Map
        </a>

        <?php elseif($role === 'staff'): ?>
        <!-- ===== STAFF NAV ===== -->
        <div class="sb-section-label">Overview</div>

        <a href="/ninjavan/staff/dashboard.php"
           class="sb-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-grid-fill"></i></span>
            Dashboard
        </a>

        <div class="sb-section-label">Operations</div>

        <a href="/ninjavan/staff/book_walkin.php"
           class="sb-link <?= $activePage === 'walkin' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-person-plus-fill"></i></span>
            Walk-in Booking
        </a>

        <a href="/ninjavan/staff/dispatch.php"
           class="sb-link <?= $activePage === 'dispatch' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-send-check-fill"></i></span>
            Dispatch to Rider
        </a>

        <a href="/ninjavan/staff/hub_inventory.php"
           class="sb-link <?= $activePage === 'inventory' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-box-seam-fill"></i></span>
            Hub Inventory
        </a>

        <?php elseif($role === 'shipper'): ?>
        <!-- ===== SHIPPER NAV ===== -->
        <div class="sb-section-label">Overview</div>

        <a href="/ninjavan/shipper/dashboard.php"
           class="sb-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-grid-fill"></i></span>
            Dashboard
        </a>

        <div class="sb-section-label">Shipments</div>

        <a href="/ninjavan/shipper/book_parcel.php"
           class="sb-link <?= $activePage === 'book' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-plus-circle-fill"></i></span>
            Book a Parcel
        </a>

        <a href="/ninjavan/shipper/my_orders.php"
           class="sb-link <?= $activePage === 'orders' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-receipt"></i></span>
            My Orders
        </a>

        <a href="/ninjavan/shipper/track_parcel.php"
            class="sb-link <?= $activePage === 'track' ? 'active' : '' ?>">
             <span class="sb-icon"><i class="bi bi-geo-alt-fill"></i></span>
             Track Parcel
          </a>
 
          <a href="/ninjavan/shipper/track_rider.php"
             class="sb-link <?= $activePage === 'track_rider' ? 'active' : '' ?>">
              <span class="sb-icon"><i class="bi bi-broadcast-pin"></i></span>
              Track My Rider
              <?php
             if (isset($conn) && isset($_SESSION['shipper_id'])) {
                 $sid   = $_SESSION['shipper_id'];
                  $livex = $conn->query("
                      SELECT COUNT(*) as c FROM `ORDER` o
                      WHERE o.Ord_ShprID = '$sid' AND o.Ord_Status = 'Out for Delivery'
                  ");
                  $lc = $livex ? ((int)$livex->fetch_assoc()['c']) : 0;
                  if ($lc > 0) echo "<span class='sb-badge' style='background:var(--green);'>LIVE</span>";
              }
              ?>
          </a>
 
          <?php elseif($role === 'rider'): ?>
        <!-- ===== RIDER NAV ===== -->
        <div class="sb-section-label">Overview</div>

        <a href="/ninjavan/rider/dashboard.php"
           class="sb-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-grid-fill"></i></span>
            Dashboard
        </a>

        <div class="sb-section-label">Deliveries</div>

        <a href="/ninjavan/rider/my_deliveries.php"
           class="sb-link <?= $activePage === 'deliveries' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-truck"></i></span>
            My Deliveries
            <?php
            if(isset($conn) && isset($_SESSION['rider_id'])){
                $rid = $_SESSION['rider_id'];
                $pr = $conn->query("SELECT COUNT(*) as c FROM DELIVERY_ATTEMPT da JOIN SHIPMENT s ON da.Atmp_ShpmID=s.Shpm_ID WHERE da.Atmp_RdrID='$rid' AND s.Shpm_Status IN ('Pending Pickup','In Transit','Out for Delivery')");
                $pc = $pr ? ($pr->fetch_assoc()['c'] ?? 0) : 0;
                if($pc > 0) echo "<span class='sb-badge'>$pc</span>";
            }
            ?>
        </a>

        <a href="/ninjavan/rider/history.php"
           class="sb-link <?= $activePage === 'history' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-clock-history"></i></span>
            History
        </a>

        <a href="/ninjavan/rider/live_map.php"
           class="sb-link <?= $activePage === 'map' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-geo-alt-fill"></i></span>
            Live Map
        </a>

        <?php endif; ?>

        <div class="sb-divider"></div>

        <a href="/ninjavan/auth/change_password.php"
           class="sb-link <?= $activePage === 'password' ? 'active' : '' ?>">
            <span class="sb-icon"><i class="bi bi-shield-lock"></i></span>
            Change Password
        </a>

    </nav>

    <!-- Bottom logout -->
    <div class="sb-bottom">
        <a href="/ninjavan/auth/logout.php" class="sb-link-danger">
            <span class="sb-icon" style="background:rgba(255,80,80,0.08); color:rgba(255,80,80,0.6);">
                <i class="bi bi-box-arrow-left"></i>
            </span>
            Log Out
        </a>
    </div>

</div>
</aside>

<!-- =============================================
     TOP NAR
============================================= -->
<header class="nv-topbar">
    <div class="topbar-left">
        <button class="sb-toggle" id="sbToggle" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
        <span class="topbar-page-title"><?= htmlspecialchars($title ?? 'Dashboard') ?></span>
    </div>

    <div class="topbar-right">
        <!-- Notification Bell -->
        <div style="position:relative;" id="notifWrap">
            <button class="topbar-icon-btn" id="notifBtn" onclick="toggleNotifDropdown()" aria-label="Notifications">
                <i class="bi bi-bell" id="notifBellIcon"></i>
                <span class="notif-badge" id="notifBadge"></span>
            </button>

            <!-- Dropdown -->
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-hdr">
                    <span class="notif-hdr-title">Notifications <span id="notifCount" style="color:var(--muted);font-weight:500;"></span></span>
                    <button class="notif-mark-all" onclick="markAllRead()">Mark all as read</button>
                </div>
                <div class="notif-list" id="notifList">
                    <div class="notif-empty"><i class="bi bi-bell-slash" style="font-size:26px;display:block;margin-bottom:6px;"></i>Loading...</div>
                </div>
                <div class="notif-footer">Only showing last 30 notifications</div>
            </div>
        </div>

        <!-- User dropdown -->
        <div class="dropdown">
            <div class="topbar-user dropdown-toggle" data-bs-toggle="dropdown" style="cursor:pointer;">
                <div class="user-avatar">
                    <?= strtoupper(substr($name, 0, 1)) ?>
                </div>
                <div class="user-info d-none d-md-block">
                    <div class="user-name"><?= htmlspecialchars($name) ?></div>
                    <div class="user-role"><?= ucfirst($role) ?></div>
                </div>
                <i class="bi bi-chevron-down" style="font-size:11px; color:var(--muted); margin-left:2px;"></i>
            </div>

            <ul class="dropdown-menu dropdown-menu-end shadow" style="border:1px solid var(--border); border-radius:var(--radius); min-width:200px; padding:8px;">
                <li>
                    <div style="padding:10px 14px 12px; border-bottom:1px solid var(--border); margin-bottom:6px;">
                        <div style="font-size:13px; font-weight:700; color:var(--ink);"><?= htmlspecialchars($name) ?></div>
                        <div style="font-size:12px; color:var(--muted);"><?= htmlspecialchars($email) ?></div>
                    </div>
                </li>
                <li>
                    <a class="dropdown-item" href="/ninjavan/auth/change_password.php"
                       style="font-size:13px; border-radius:6px; padding:8px 12px; display:flex; align-items:center; gap:8px;">
                        <i class="bi bi-shield-lock" style="color:var(--muted);"></i> Change Password
                    </a>
                </li>
                <li><hr class="dropdown-divider" style="margin:6px 0;"></li>
                <li>
                    <a class="dropdown-item" href="/ninjavan/auth/logout.php"
                       style="font-size:13px; border-radius:6px; padding:8px 12px; color:#dc2626; display:flex; align-items:center; gap:8px;">
                        <i class="bi bi-box-arrow-right"></i> Log Out
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>

<!-- TOAST CONTAINER -->
<div class="nv-toast-wrap" id="toastWrap"></div>

<!-- MAIN CONTENT OPENS HERE -->
<main class="nv-main">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ─── Toast ───────────────────────────────────────────────────────────────────
function showToast(msg, type = 'success'){
    const wrap = document.getElementById('toastWrap');
    if (!wrap) return;
    const t = document.createElement('div');
    t.className = 'nv-toast' + (type === 'error' ? ' error' : '');
    t.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill'}"></i> ${msg}`;
    wrap.appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transform='translateY(8px)'; t.style.transition='all 0.3s'; setTimeout(()=>t.remove(), 300); }, 3500);
}

// Show session toasts after function is defined
<?php if(isset($_SESSION['toast_success'])): ?>
showToast('<?= addslashes($_SESSION['toast_success']) ?>', 'success');
<?php unset($_SESSION['toast_success']); endif; ?>
<?php if(isset($_SESSION['toast_error'])): ?>
showToast('<?= addslashes($_SESSION['toast_error']) ?>', 'error');
<?php unset($_SESSION['toast_error']); endif; ?>

function toggleSidebar(){
    document.querySelector('.nv-sidebar').classList.toggle('open');
    document.getElementById('sbOverlay').classList.toggle('show');
}
function closeSidebar(){
    document.querySelector('.nv-sidebar').classList.remove('open');
    document.getElementById('sbOverlay').classList.remove('show');
}

// ─── Notification Bell ───────────────────────────────────────────────────────
let notifOpen = false;
let notifLoaded = false;

function toggleNotifDropdown() {
    notifOpen = !notifOpen;
    document.getElementById('notifDropdown').classList.toggle('open', notifOpen);
    if (notifOpen && !notifLoaded) {
        fetchNotifications();
        notifLoaded = true;
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', e => {
    if (notifOpen && !document.getElementById('notifWrap').contains(e.target)) {
        notifOpen = false;
        document.getElementById('notifDropdown').classList.remove('open');
    }
});

async function fetchNotifications() {
    try {
        const res  = await fetch('/ninjavan/api/notifications.php?action=list&_=' + Date.now());
        const data = await res.json();
        if (data.error) return;
        renderNotifications(data);
    } catch(e) { console.warn('Notification fetch failed', e); }
}

function renderNotifications(data) {
    const badge   = document.getElementById('notifBadge');
    const bellIcon = document.getElementById('notifBellIcon');
    const countEl = document.getElementById('notifCount');
    const listEl  = document.getElementById('notifList');

    // Badge
    if (data.unread > 0) {
        badge.textContent = data.unread > 99 ? '99+' : data.unread;
        badge.classList.add('show');
        bellIcon.className = 'bi bi-bell-fill';
        bellIcon.style.color = 'var(--red)';
    } else {
        badge.classList.remove('show');
        bellIcon.className = 'bi bi-bell';
        bellIcon.style.color = '';
    }

    countEl.textContent = data.unread > 0 ? `(${data.unread} unread)` : '';

    if (!data.notifications || data.notifications.length === 0) {
        listEl.innerHTML = `<div class="notif-empty">
            <i class="bi bi-bell-slash" style="font-size:26px;display:block;margin-bottom:6px;"></i>
            You have no notifications
        </div>`;
        return;
    }

    let html = '';
    data.notifications.forEach(n => {
        const unreadClass = !n.is_read ? 'unread' : '';
        const linkAttr    = n.link ? `href="${n.link}"` : 'href="#"';
        html += `
        <a ${linkAttr} class="notif-item ${unreadClass}" onclick="markRead(${n.id})">
            <div class="notif-icon-wrap"><i class="bi bi-${n.icon || 'bell'}"></i></div>
            <div style="flex:1;min-width:0;">
                <div class="notif-item-title">${n.title}</div>
                <div class="notif-item-body">${n.body}</div>
                <div class="notif-item-ago"><i class="bi bi-clock"></i> ${n.ago}</div>
            </div>
            ${!n.is_read ? '<div class="notif-unread-dot"></div>' : ''}
        </a>`;
    });
    listEl.innerHTML = html;
}

async function markRead(id) {
    const fd = new FormData();
    fd.append('action', 'mark_read');
    fd.append('id', id);
    await fetch('/ninjavan/api/notifications.php', { method: 'POST', body: fd });
    // Mark item as read visually
    const items = document.querySelectorAll('.notif-item.unread');
    items.forEach(el => {
        if (el.querySelector('.notif-unread-dot')) {
            // will be refreshed on next open
        }
    });
    notifLoaded = false; // force re-fetch on next open
}

async function markAllRead() {
    const fd = new FormData();
    fd.append('action', 'mark_read');
    await fetch('/ninjavan/api/notifications.php', { method: 'POST', body: fd });
    // Refresh UI
    document.getElementById('notifBadge').classList.remove('show');
    document.getElementById('notifBellIcon').className = 'bi bi-bell';
    document.getElementById('notifBellIcon').style.color = '';
    document.getElementById('notifCount').textContent = '';
    document.querySelectorAll('.notif-item.unread').forEach(el => {
        el.classList.remove('unread');
        const dot = el.querySelector('.notif-unread-dot');
        if (dot) dot.remove();
    });
    showToast('All notifications marked as read', 'success');
}

// Auto-poll unread count every 60 seconds (without opening dropdown)
setInterval(async () => {
    if (notifOpen) return;
    try {
        const res  = await fetch('/ninjavan/api/notifications.php?action=list&_=' + Date.now());
        const data = await res.json();
        if (!data.error) {
            const badge = document.getElementById('notifBadge');
            if (data.unread > 0) {
                badge.textContent = data.unread > 99 ? '99+' : data.unread;
                badge.classList.add('show');
                document.getElementById('notifBellIcon').className = 'bi bi-bell-fill';
                document.getElementById('notifBellIcon').style.color = 'var(--red)';
            } else {
                badge.classList.remove('show');
                document.getElementById('notifBellIcon').className = 'bi bi-bell';
                document.getElementById('notifBellIcon').style.color = '';
            }
        }
    } catch(e) {}
}, 60000);

// Initial badge load (no dropdown open)
fetchNotifications();
</script>
