<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'shipper') {
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Track My Rider";
$activePage = "track_rider";
$shipperId  = $_SESSION['shipper_id'];

// ── Resolve tracking number from GET or from shipper's active orders ──────────
$trk   = isset($_GET['trk']) ? trim($_GET['trk']) : '';
$order = null;
$rider = null;
$riderGPS  = null;
$timeline  = [];
$hubCoords = null;

// Branch hub coordinates are now fetched dynamically from the DB query

if ($trk) {
    // Look up the order — must belong to this shipper
    $stmt = $conn->prepare("
        SELECT o.Ord_ID, o.Ord_Status, o.Ord_CrtdDt,
               p.Pcl_Wght, p.Pcl_IsCOD, p.Pcl_CODAmt,
               rc.Rcpt_Name, rc.Rcpt_Area, rc.Rcpt_Addr,
               sv.Svc_Name,
               aw.AWB_TrkNum,
               sh.Shpm_ID, sh.Shpm_Status, sh.Shpm_PickDt, sh.Shpm_DlvDt, sh.Shpm_AtmCnt,
               h.Hub_ID, h.Hub_Area, h.Hub_Name, h.Hub_Lat, h.Hub_Lng
        FROM AIRWAY_BILL aw
        JOIN `ORDER` o       ON aw.AWB_OrdID    = o.Ord_ID
        JOIN SHIPPER s       ON o.Ord_ShprID     = s.Shpr_ID
        JOIN PARCEL p        ON o.Ord_PclID      = p.Pcl_ID
        JOIN RECIPIENT rc    ON p.Pcl_RcptID     = rc.Rcpt_ID
        JOIN SERVICE_TYPE sv ON o.Ord_SvcID      = sv.Svc_ID
        LEFT JOIN SHIPMENT sh ON sh.Shpm_OrdID   = o.Ord_ID
        LEFT JOIN HUB h       ON h.Hub_ID         = sh.Shpm_HubID
        WHERE aw.AWB_TrkNum = ?
          AND s.Shpr_ID = ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $trk, $shipperId);
    $stmt->execute();
    $result = $stmt->get_result();
    $order  = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($order && $order['Shpm_ID']) {
        // Get assigned rider + their current GPS
        $stmt2 = $conn->prepare("
            SELECT rd.Rdr_ID, rd.Rdr_Name, rd.Rdr_VhcTyp, rd.Rdr_Phone,
                   da.Atmp_ID, da.Atmp_Rslt,
                   g.GPS_Lat, g.GPS_Lng,
                   TIMESTAMPDIFF(MINUTE, g.GPS_UpdatedAt, NOW()) AS gps_age_min,
                   DATE_FORMAT(g.GPS_UpdatedAt, '%h:%i %p') AS gps_updated
            FROM DELIVERY_ATTEMPT da
            JOIN RIDER rd ON da.Atmp_RdrID = rd.Rdr_ID
            LEFT JOIN RIDER_GPS g ON g.GPS_RdrID = rd.Rdr_ID
            WHERE da.Atmp_ShpmID = ?
              AND da.Atmp_Rslt   = 'Pending'
            LIMIT 1
        ");
        $stmt2->bind_param('s', $order['Shpm_ID']);
        $stmt2->execute();
        $r2    = $stmt2->get_result();
        $rider = $r2->num_rows > 0 ? $r2->fetch_assoc() : null;
        $stmt2->close();

        if ($rider && $rider['GPS_Lat']) {
            $riderGPS = [
                'lat'     => (float)$rider['GPS_Lat'],
                'lng'     => (float)$rider['GPS_Lng'],
                'age_min' => (int)$rider['gps_age_min'],
                'updated' => $rider['gps_updated'],
                'status'  => (int)$rider['gps_age_min'] < 5 ? 'online'
                           : ((int)$rider['gps_age_min'] < 30 ? 'idle' : 'offline'),
            ];
        }

        // Resolve hub coordinates dynamically from DB
        $lat = $order['Hub_Lat'] ? (float)$order['Hub_Lat'] : null;
        $lng = $order['Hub_Lng'] ? (float)$order['Hub_Lng'] : null;
        
        if (!$lat || !$lng) {
            $harea = strtolower($order['Hub_Area'] ?? '');
            if (str_contains($harea, 'cebu') || str_contains($harea, 'visayas')) { $lat=10.3157; $lng=123.8854; }
            elseif (str_contains($harea, 'davao') || str_contains($harea, 'mindanao')) { $lat=7.1907; $lng=125.4553; }
            else { $lat=14.5995; $lng=120.9842; } // Manila default
        }
        
        $hubCoords = [
            'id'   => $order['Hub_ID'] ?? '',
            'lat'  => $lat,
            'lng'  => $lng,
            'name' => $order['Hub_Name'] ?? 'NinjaVan Hub'
        ];

        // Build timeline
        $currStatus = $order['Ord_Status'];
        $statusOrder = [
            'Order Created' => 0,
            'Pickup / Drop-off' => 1,
            'Origin Sorting Hub' => 2,
            'Main Sorting Hub' => 3,
            'Regional Hub' => 4,
            'Destination Hub' => 5,
            'Out for Delivery' => 6,
            'Delivered' => 7,
            'RTS' => 8
        ];
        $currIdx = $statusOrder[$currStatus] ?? 0;
        $baseDate = strtotime($order['Ord_CrtdDt']);

        // 1. Order Created
        $timeline[] = [
            'date'  => date('Y-m-d H:i:s', $baseDate),
            'title' => 'Order Created',
            'desc'  => 'Parcel booked and received by NinjaVan.',
            'icon'  => 'bi-file-earmark-check-fill',
            'done'  => true,
            'color' => null,
        ];

        // 2. Pickup
        if($currIdx >= 1) {
            $date = $order['Shpm_PickDt'] ?: date('Y-m-d H:i:s', $baseDate + 3600);
            $timeline[] = [
                'date' => $date,
                'title' => 'Pickup / Drop-off',
                'desc' => 'Parcel handed over to Ninja Van.',
                'icon' => 'bi-box-seam-fill',
                'done' => true,
                'color' => null,
            ];
        }

        // 3. Origin Sorting Hub
        if($currIdx >= 2) {
            $timeline[] = [
                'date' => date('Y-m-d H:i:s', $baseDate + 7200),
                'title' => 'Origin Sorting Hub',
                'desc' => 'Parcel received at origin facility, scanned and sorted.',
                'icon' => 'bi-building',
                'done' => $currIdx > 2,
                'color' => null,
            ];
        }

        // 4. Main Sorting Hub
        if($currIdx >= 3) {
            $timeline[] = [
                'date' => date('Y-m-d H:i:s', $baseDate + 86400),
                'title' => 'Main Sorting Hub',
                'desc' => 'Parcel arrived at the central sorting facility.',
                'icon' => 'bi-diagram-3',
                'done' => $currIdx > 3,
                'color' => null,
            ];
        }

        // 5. Regional Hub
        if($currIdx >= 4) {
            $timeline[] = [
                'date' => date('Y-m-d H:i:s', $baseDate + 172800),
                'title' => 'Regional Hub',
                'desc' => 'Parcel transported to the regional distribution center.',
                'icon' => 'bi-geo-alt',
                'done' => $currIdx > 4,
                'color' => null,
            ];
        }

        // 6. Destination Hub
        if($currIdx >= 5) {
            $timeline[] = [
                'date' => date('Y-m-d H:i:s', $baseDate + 200000),
                'title' => 'Destination Hub',
                'desc' => 'Parcel received at the final branch responsible for delivery.',
                'icon' => 'bi-house-door',
                'done' => $currIdx > 5,
                'color' => null,
            ];
        }

        // 7. Delivery attempts
        if($currIdx >= 6) {
            $aStmt = $conn->prepare("
                SELECT da.Atmp_Date, da.Atmp_Rslt, da.Atmp_Sign, da.Atmp_FailRsn, rd.Rdr_Name
                FROM DELIVERY_ATTEMPT da
                JOIN RIDER rd ON da.Atmp_RdrID = rd.Rdr_ID
                WHERE da.Atmp_ShpmID = ?
                ORDER BY da.Atmp_Date ASC
            ");
            $aStmt->bind_param('s', $order['Shpm_ID']);
            $aStmt->execute();
            $attempts = $aStmt->get_result();
            $aStmt->close();

            $hasPending = false;
            while ($att = $attempts->fetch_assoc()) {
                if ($att['Atmp_Rslt'] === 'Successful') {
                    $timeline[] = [
                        'date'  => $att['Atmp_Date'],
                        'title' => 'Delivered',
                        'desc'  => 'Received by ' . htmlspecialchars($att['Atmp_Sign'] ?? '—')
                                 . ' · Rider: ' . htmlspecialchars($att['Rdr_Name']),
                        'icon'  => 'bi-check-circle-fill',
                        'done'  => true,
                        'color' => 'var(--green)',
                    ];
                } elseif ($att['Atmp_Rslt'] === 'Failed') {
                    $timeline[] = [
                        'date'  => $att['Atmp_Date'],
                        'title' => 'Delivery attempt failed',
                        'desc'  => htmlspecialchars($att['Atmp_FailRsn'] ?? 'No reason given')
                                 . ' · Rider: ' . htmlspecialchars($att['Rdr_Name']),
                        'icon'  => 'bi-x-circle-fill',
                        'done'  => true,
                        'color' => '#dc2626',
                    ];
                } elseif($att['Atmp_Rslt'] === 'Pending') {
                    $hasPending = true;
                }
            }

            // Active out-for-delivery step
            if ($currIdx === 6 || $hasPending) {
                $timeline[] = [
                    'date'  => date('Y-m-d H:i:s'),
                    'title' => 'Out for Delivery',
                    'desc'  => 'Rider is on the way to your recipient now.',
                    'icon'  => 'bi-truck',
                    'done'  => false,
                    'color' => 'var(--blue)',
                ];
            }
        }
        
        if($currIdx === 8) {
             $timeline[] = [
                'date' => date('Y-m-d H:i:s'),
                'title' => 'Return to Sender (RTS)',
                'desc' => 'Parcel is being returned to the sender.',
                'icon' => 'bi-arrow-return-left',
                'done' => true,
                'color' => 'var(--red)'
            ];
        }

        usort($timeline, fn($a, $b) => strtotime($a['date']) - strtotime($b['date']));
    }
}

// ── Shipper's active orders for quick-select ──────────────────────────────────
$activeOrders = $conn->query("
    SELECT aw.AWB_TrkNum, o.Ord_Status, rc.Rcpt_Name
    FROM `ORDER` o
    JOIN PARCEL p      ON o.Ord_PclID   = p.Pcl_ID
    JOIN RECIPIENT rc  ON p.Pcl_RcptID  = rc.Rcpt_ID
    LEFT JOIN AIRWAY_BILL aw ON aw.AWB_OrdID = o.Ord_ID
    WHERE o.Ord_ShprID = '$shipperId'
      AND o.Ord_Status IN ('Pickup / Drop-off','Origin Sorting Hub','Main Sorting Hub','Regional Hub','Destination Hub','Out for Delivery')
    ORDER BY o.Ord_CrtdDt DESC
    LIMIT 10
");

// Leaflet injected into <head>
$extraHead = '
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
';

include "../layout/dashboard_layout.php";
?>

<style>
/* ── Page layout ───────────────────────────────────── */
.tracker-grid {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 20px;
    align-items: start;
}

/* ── Map ───────────────────────────────────────────── */
#riderMap {
    height: 520px;
    width: 100%;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    position: relative;   /* no z-index — avoids stacking context issue */
}

/* ── Sidebar panel ─────────────────────────────────── */
.tracker-panel {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* ── Rider card ────────────────────────────────────── */
.rider-info-card {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.rider-card-hero {
    background: var(--ink-2);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 14px;
}

.rider-avatar-lg {
    width: 52px; height: 52px;
    border-radius: 50%;
    background: var(--red);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Sora', sans-serif;
    font-weight: 800; font-size: 18px; color: #fff;
    flex-shrink: 0;
}

.rider-hero-name  { font-family:'Sora',sans-serif; font-weight:700; font-size:15px; color:#fff; }
.rider-hero-vhcl  { font-size:12px; color:rgba(255,255,255,0.5); margin-top:2px; }

.rider-status-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
}
.rider-status-pill.online  { background:rgba(0,179,125,0.15); color:var(--green); }
.rider-status-pill.idle    { background:rgba(245,158,11,0.15); color:var(--amber); }
.rider-status-pill.offline { background:rgba(138,133,128,0.12); color:var(--muted); }
.rider-status-pill .dot    { width:6px; height:6px; border-radius:50%; background:currentColor; }
.rider-status-pill.online .dot { animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.35} }

.rider-card-body { padding: 16px 20px; }
.rider-meta-row {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; color: var(--muted);
    padding: 6px 0; border-bottom: 1px solid var(--border);
}
.rider-meta-row:last-child { border-bottom: none; }
.rider-meta-row i { width: 16px; text-align: center; color: var(--red); }
.rider-meta-val { font-weight: 600; color: var(--ink); margin-left: auto; }

/* ── Order summary card ────────────────────────────── */
.order-summary-card {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 18px 20px;
}

.order-trk {
    font-family: 'Sora', sans-serif;
    font-size: 20px; font-weight: 800;
    color: var(--red); letter-spacing: 0.02em;
    margin-bottom: 4px;
}

.order-meta-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 12px; margin-top: 14px;
    padding-top: 14px; border-top: 1px solid var(--border);
}

.order-meta-block .lbl {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .07em; color: var(--muted); margin-bottom: 3px;
}
.order-meta-block .val { font-size: 13px; font-weight: 600; color: var(--ink); }
.order-meta-block .sub { font-size: 11px; color: var(--muted); margin-top: 1px; }

/* ── Timeline ──────────────────────────────────────── */
.mini-timeline { list-style: none; padding: 0; margin: 0; }
.mini-tl-item {
    display: flex; gap: 12px;
    padding-bottom: 18px; position: relative;
}
.mini-tl-item:not(:last-child) .mini-tl-dot::after {
    content: ''; position: absolute;
    top: 22px; left: 9px; width: 2px;
    background: var(--border); bottom: 0;
}
.mini-tl-dot { position: relative; width: 20px; flex-shrink: 0; }
.mini-tl-circle {
    width: 20px; height: 20px; border-radius: 50%;
    border: 2px solid var(--border); background: var(--surface-2);
    display: flex; align-items: center; justify-content: center;
    font-size: 9px; color: var(--muted);
}
.mini-tl-circle.done   { border-color: var(--green); background: var(--green); color: #fff; }
.mini-tl-circle.active { border-color: var(--blue);  background: var(--blue);  color: #fff; box-shadow: 0 0 0 4px rgba(59,130,246,0.18); }
.mini-tl-title { font-size: 13px; font-weight: 600; color: var(--ink); }
.mini-tl-desc  { font-size: 11px; color: var(--muted); margin-top: 2px; line-height: 1.4; }
.mini-tl-time  { font-size: 10px; color: var(--muted-2); margin-top: 3px; }

/* ── No rider / not out-for-delivery states ────────── */
.state-card {
    background: var(--surface-2); border: 1px solid var(--border);
    border-radius: var(--radius); box-shadow: var(--shadow);
    padding: 40px 28px; text-align: center;
}
.state-icon {
    width: 64px; height: 64px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; margin: 0 auto 16px;
}

/* ── Search bar ────────────────────────────────────── */
.trk-search-wrap {
    background: var(--surface-2); border: 1px solid var(--border);
    border-radius: var(--radius); box-shadow: var(--shadow); padding: 20px;
}

/* ── ETA pill ──────────────────────────────────────── */
.eta-pill {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(59,130,246,0.08); color: var(--blue);
    border: 1px solid rgba(59,130,246,0.2);
    border-radius: 20px; padding: 5px 14px;
    font-size: 12px; font-weight: 700;
}

/* ── Map overlay badge ─────────────────────────────── */
.map-info-badge {
    position: absolute; top: 12px; right: 12px; z-index: 800;
    background: var(--surface-2); border: 1px solid var(--border);
    border-radius: 10px; padding: 10px 14px;
    box-shadow: var(--shadow); font-size: 12px;
    min-width: 160px;
}
.map-info-badge .badge-title { font-weight: 700; font-size: 12px; color: var(--ink); margin-bottom: 4px; }
.map-info-badge .badge-sub   { color: var(--muted); font-size: 11px; }

/* ── Leaflet popup ─────────────────────────────────── */
.leaflet-popup-content-wrapper {
    border-radius: var(--radius-sm) !important;
    box-shadow: var(--shadow-lg) !important;
    border: 1px solid var(--border) !important;
    font-family: 'DM Sans', sans-serif !important;
}

/* ── Active order chips ────────────────────────────── */
.order-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 8px;
    border: 1.5px solid var(--border); background: var(--surface-2);
    font-size: 12px; font-weight: 600; color: var(--ink);
    text-decoration: none; transition: var(--trans); margin: 3px;
}
.order-chip:hover { border-color: var(--red); color: var(--red); background: rgba(232,0,45,0.03); }
.order-chip.active-chip { border-color: var(--red); background: rgba(232,0,45,0.05); color: var(--red); }

@media (max-width: 900px) {
    .tracker-grid { grid-template-columns: 1fr; }
    #riderMap { height: 380px; }
}
</style>

<!-- Page header -->
<div class="page-header">
    <div>
        <h1><i class="bi bi-geo-alt-fill" style="color:var(--red);"></i> Track My Rider</h1>
        <p>See exactly where your rider is during delivery</p>
    </div>
    <?php if ($order && in_array($order['Ord_Status'], ['Origin Sorting Hub','Main Sorting Hub','Regional Hub','Destination Hub','Out for Delivery'])): ?>
    <div class="eta-pill">
        <i class="bi bi-clock"></i>
        <span id="etaLabel">Calculating ETA...</span>
    </div>
    <?php endif; ?>
</div>

<!-- ── Tracking number search ─────────────────────── -->
<div class="trk-search-wrap mb-4">
    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap;">
        <div style="position:relative; flex:1; min-width:220px;">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;"></i>
            <input type="text" name="trk" class="nv-input"
                   style="padding-left:38px; font-family:'Sora',sans-serif; font-size:14px; font-weight:700; letter-spacing:0.04em;"
                   placeholder="Enter tracking number (e.g. NVPH...)"
                   value="<?= htmlspecialchars($trk) ?>">
        </div>
        <button type="submit" class="btn-nv">
            <i class="bi bi-geo-alt-fill"></i> Track Rider
        </button>
    </form>

    <?php if ($activeOrders && $activeOrders->num_rows > 0): ?>
    <div style="margin-top:14px; padding-top:14px; border-top:1px solid var(--border);">
        <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--muted); margin-bottom:8px;">
            Your active shipments
        </div>
        <?php while ($ao = $activeOrders->fetch_assoc()):
            $isActive = $ao['AWB_TrkNum'] === $trk;
        ?>
        <a href="?trk=<?= urlencode($ao['AWB_TrkNum']) ?>"
           class="order-chip <?= $isActive ? 'active-chip' : '' ?>">
            <i class="bi bi-box-seam" style="font-size:11px;"></i>
            <?= htmlspecialchars($ao['AWB_TrkNum']) ?>
            <span style="font-size:10px; color:var(--muted); font-weight:400;">→ <?= htmlspecialchars($ao['Rcpt_Name']) ?></span>
        </a>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (!$trk): ?>
<!-- ── Empty state — no search yet ──────────────────── -->
<div class="state-card" style="max-width:520px; margin:0 auto;">
    <div class="state-icon" style="background:rgba(59,130,246,0.08); color:var(--blue);">
        <i class="bi bi-geo-alt"></i>
    </div>
    <h4 style="font-family:'Sora',sans-serif; font-weight:700; margin-bottom:6px;">Enter a tracking number</h4>
    <p style="color:var(--muted); font-size:14px;">
        Paste your NinjaVan tracking number above to see your rider's live location on the map.
        Live tracking is only available when a rider is <strong>Out for Delivery</strong>.
    </p>
</div>

<?php elseif (!$order): ?>
<!-- ── Not found ─────────────────────────────────────── -->
<div class="state-card" style="max-width:480px; margin:0 auto;">
    <div class="state-icon" style="background:rgba(239,68,68,0.08); color:#dc2626;">
        <i class="bi bi-search"></i>
    </div>
    <h4 style="font-family:'Sora',sans-serif; font-weight:700; margin-bottom:6px;">Tracking number not found</h4>
    <p style="color:var(--muted); font-size:14px;">
        <strong><?= htmlspecialchars($trk) ?></strong> doesn't match any of your orders.
        Check the number and try again.
    </p>
    <a href="?" class="btn-nv-ghost" style="margin-top:12px; justify-content:center;">Clear search</a>
</div>

<?php else:
    $s      = $order['Ord_Status'];
    $badgeMap = [
        'Order Created'          => 'badge-pending',
        'Pickup / Drop-off'   => 'badge-confirmed',
        'Origin Sorting Hub' => 'badge-transit','Main Sorting Hub' => 'badge-transit','Regional Hub' => 'badge-transit','Destination Hub' => 'badge-transit',
        'Out for Delivery' => 'badge-delivery',
        'Delivered'        => 'badge-delivered',
        'RTS'              => 'badge-failed',
    ];
    $badgeCls = $badgeMap[$s] ?? 'badge-pending';
    $isLive   = in_array($s, ['Origin Sorting Hub','Main Sorting Hub','Regional Hub','Destination Hub','Out for Delivery']);
    $isOFD    = $s === 'Out for Delivery';
?>

<!-- ── Main tracker layout ──────────────────────────── -->
<div class="tracker-grid">

    <!-- LEFT PANEL -->
    <div class="tracker-panel">

        <!-- Order summary -->
        <div class="order-summary-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div class="order-trk"><?= htmlspecialchars($order['AWB_TrkNum']) ?></div>
                <span class="badge-status <?= $badgeCls ?>"><?= $s ?></span>
            </div>
            <div class="order-meta-grid">
                <div class="order-meta-block">
                    <div class="lbl">Recipient</div>
                    <div class="val"><?= htmlspecialchars($order['Rcpt_Name']) ?></div>
                    <div class="sub"><?= htmlspecialchars($order['Rcpt_Area']) ?></div>
                </div>
                <div class="order-meta-block">
                    <div class="lbl">Service</div>
                    <div class="val"><?= htmlspecialchars($order['Svc_Name']) ?></div>
                    <div class="sub"><?= $order['Pcl_Wght'] ?> kg</div>
                </div>
                <div class="order-meta-block">
                    <div class="lbl">Hub</div>
                    <div class="val"><?= htmlspecialchars($order['Hub_Name'] ?? '—') ?></div>
                    <div class="sub"><?= htmlspecialchars($order['Hub_Area'] ?? '') ?> Branch</div>
                </div>
                <div class="order-meta-block">
                    <div class="lbl">Attempt</div>
                    <div class="val"><?= ($order['Shpm_AtmCnt'] ?? 0) + 1 ?> of 3</div>
                    <div class="sub">Max 3 tries</div>
                </div>
            </div>
        </div>

        <!-- Rider info -->
        <?php if ($rider): ?>
        <div class="rider-info-card">
            <div class="rider-card-hero">
                <div class="rider-avatar-lg">
                    <?= strtoupper(substr($rider['Rdr_Name'], 0, 2)) ?>
                </div>
                <div style="flex:1; min-width:0;">
                    <div class="rider-hero-name"><?= htmlspecialchars($rider['Rdr_Name']) ?></div>
                    <div class="rider-hero-vhcl"><?= htmlspecialchars($rider['Rdr_VhcTyp']) ?></div>
                    <?php if ($riderGPS): ?>
                    <div style="margin-top:7px;">
                        <span class="rider-status-pill <?= $riderGPS['status'] ?>">
                            <span class="dot"></span>
                            <?= ucfirst($riderGPS['status']) ?>
                            · updated <?= $riderGPS['updated'] ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="rider-card-body">
                <?php if ($rider['Rdr_Phone'] ?? null): ?>
                <div class="rider-meta-row">
                    <i class="bi bi-telephone-fill"></i>
                    <span>Contact</span>
                    <span class="rider-meta-val"><?= htmlspecialchars($rider['Rdr_Phone']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($riderGPS): ?>
                <div class="rider-meta-row">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>GPS</span>
                    <span class="rider-meta-val" style="color:var(--green); font-size:11px;">
                        <?= number_format($riderGPS['lat'], 4) ?>, <?= number_format($riderGPS['lng'], 4) ?>
                    </span>
                </div>
                <div class="rider-meta-row">
                    <i class="bi bi-reception-4"></i>
                    <span>Signal age</span>
                    <span class="rider-meta-val"><?= $riderGPS['age_min'] ?> min ago</span>
                </div>
                <?php else: ?>
                <div class="rider-meta-row">
                    <i class="bi bi-geo-slash"></i>
                    <span>GPS</span>
                    <span class="rider-meta-val" style="color:var(--muted);">No signal yet</span>
                </div>
                <?php endif; ?>
                <div class="rider-meta-row">
                    <i class="bi bi-geo-alt"></i>
                    <span>Delivering to</span>
                    <span class="rider-meta-val" style="font-size:11px; max-width:140px; text-align:right; white-space:normal;">
                        <?= htmlspecialchars($order['Rcpt_Area']) ?>
                    </span>
                </div>
            </div>
        </div>

        <?php elseif ($isLive): ?>
        <!-- Rider assigned but no GPS yet -->
        <div class="state-card" style="padding:28px;">
            <div class="state-icon" style="background:var(--amber-soft); color:var(--amber); margin-bottom:12px; width:48px; height:48px; font-size:22px;">
                <i class="bi bi-bicycle"></i>
            </div>
            <div style="font-weight:700; font-size:14px; margin-bottom:4px;">Rider assigned</div>
            <p style="color:var(--muted); font-size:12px; margin:0;">
                Your rider hasn't shared their GPS yet. The map will update automatically once location is available.
            </p>
        </div>

        <?php else: ?>
        <!-- Not yet dispatched -->
        <div class="state-card" style="padding:28px;">
            <div class="state-icon" style="background:var(--blue-soft); color:var(--blue); margin-bottom:12px; width:48px; height:48px; font-size:22px;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div style="font-weight:700; font-size:14px; margin-bottom:4px;">Not yet dispatched</div>
            <p style="color:var(--muted); font-size:12px; margin:0;">
                Live rider tracking will appear here once your parcel is <strong>Out for Delivery</strong>.
                Current status: <span class="badge-status <?= $badgeCls ?>" style="font-size:10px;"><?= $s ?></span>
            </p>
        </div>
        <?php endif; ?>

        <!-- Delivery timeline -->
        <?php if (!empty($timeline)): ?>
        <div class="nv-card p-4">
            <h5 style="font-size:14px; font-weight:700; margin-bottom:18px;">
                <i class="bi bi-clock-history" style="color:var(--muted); margin-right:6px;"></i>
                Delivery timeline
            </h5>
            <ul class="mini-timeline">
                <?php foreach (array_reverse($timeline) as $i => $item):
                    $circleCls = $item['done'] ? 'done' : ($i === 0 ? 'active' : '');
                    $borderOverride = ($item['color'] && $item['done'])
                        ? "border-color:{$item['color']};background:{$item['color']};"
                        : '';
                ?>
                <li class="mini-tl-item">
                    <div class="mini-tl-dot">
                        <div class="mini-tl-circle <?= $circleCls ?>" style="<?= $borderOverride ?>">
                            <i class="bi <?= $item['icon'] ?>"></i>
                        </div>
                    </div>
                    <div>
                        <div class="mini-tl-title"
                             style="<?= ($item['color'] && $item['done']) ? 'color:'.$item['color'].';' : '' ?>">
                            <?= $item['title'] ?>
                        </div>
                        <div class="mini-tl-desc"><?= $item['desc'] ?></div>
                        <div class="mini-tl-time"><?= date('M d, Y · h:i A', strtotime($item['date'])) ?></div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

    </div><!-- end left panel -->

    <!-- RIGHT: MAP -->
    <div style="position:relative;">
        <div id="riderMap"></div>

        <!-- Map info badge overlay -->
        <div class="map-info-badge" id="mapBadge">
            <?php if ($riderGPS && $isLive): ?>
            <div class="badge-title">
                <i class="bi bi-geo-alt-fill" style="color:var(--red);"></i>
                <?= htmlspecialchars($rider['Rdr_Name']) ?>
            </div>
            <div class="badge-sub" id="badgeStatus">
                <?= ucfirst($riderGPS['status']) ?> · <?= $riderGPS['updated'] ?>
            </div>
            <?php elseif ($isLive): ?>
            <div class="badge-title"><i class="bi bi-satellite"></i> Waiting for GPS</div>
            <div class="badge-sub">Rider hasn't shared location</div>
            <?php else: ?>
            <div class="badge-title"><i class="bi bi-map"></i> Hub location</div>
            <div class="badge-sub"><?= htmlspecialchars($hubCoords['name'] ?? 'NinjaVan Hub') ?></div>
            <?php endif; ?>
        </div>

        <!-- Live pulse indicator — only when OFD with GPS -->
        <?php if ($isOFD && $riderGPS): ?>
        <div style="position:absolute; bottom:14px; left:14px; z-index:800;
                    background:rgba(0,179,125,0.12); border:1px solid var(--green);
                    border-radius:8px; padding:7px 12px; font-size:12px; font-weight:700; color:var(--green);
                    display:flex; align-items:center; gap:6px;">
            <span style="width:8px;height:8px;border-radius:50%;background:var(--green);animation:pulse 1.5s infinite;display:inline-block;"></span>
            Live · updates every 15s
        </div>
        <?php endif; ?>
    </div>

</div><!-- end tracker-grid -->

<?php endif; /* order found */ ?>

<!-- ── Leaflet map script ─────────────────────────── -->
<script>
document.addEventListener('DOMContentLoaded', function () {

// PHP → JS data bridge
const ORDER_STATUS  = <?= json_encode($order['Ord_Status'] ?? '') ?>;
const IS_LIVE       = <?= json_encode($isLive ?? false) ?>;
const IS_OFD        = <?= json_encode($isOFD  ?? false) ?>;
const RIDER_GPS     = <?= json_encode($riderGPS) ?>;
const HUB           = <?= json_encode($hubCoords ?? ['lat' => 14.5995, 'lng' => 120.9842, 'name' => 'Manila Hub']) ?>;
const RIDER_NAME    = <?= json_encode($rider['Rdr_Name'] ?? '') ?>;
const RCPT_AREA     = <?= json_encode($order['Rcpt_Area'] ?? '') ?>;
const RCPT_ADDR     = <?= json_encode($order['Rcpt_Addr'] ?? '') ?>;
const TRK           = <?= json_encode($trk) ?>;

const mapEl = document.getElementById('riderMap');
if (!mapEl) return;

// ── Map center logic ───────────────────────────────
let mapCenter, mapZoom;
if (RIDER_GPS) {
    mapCenter = [RIDER_GPS.lat, RIDER_GPS.lng];
    mapZoom   = 14;
} else {
    mapCenter = [HUB.lat, HUB.lng];
    mapZoom   = 13;
}

const map = L.map('riderMap', { center: mapCenter, zoom: mapZoom, zoomControl: true });

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
}).addTo(map);

setTimeout(() => map.invalidateSize(), 250);

// ── Hub marker ─────────────────────────────────────
const hubSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 34 34">
    <circle cx="17" cy="17" r="15" fill="#e8002d" stroke="white" stroke-width="2.5"/>
    <text x="17" y="22" text-anchor="middle" font-size="14" font-family="sans-serif">🏭</text>
</svg>`;
const hubIcon = L.divIcon({ html: hubSvg, iconSize:[34,34], iconAnchor:[17,17], className:'' });
const hubMarker = L.marker([HUB.lat, HUB.lng], { icon: hubIcon })
    .addTo(map)
    .bindPopup(`<div style="padding:4px;min-width:140px;">
        <div style="font-family:'Sora',sans-serif;font-weight:700;font-size:13px;margin-bottom:4px;">🏭 ${HUB.name}</div>
        <div style="font-size:12px;color:#666;">Your parcel's branch hub</div>
    </div>`);

// ── Destination marker (recipient area) ────────────
const destSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="30" height="36" viewBox="0 0 30 36">
    <path d="M15 0 C6 0 0 7 0 15 C0 25 15 36 15 36 C15 36 30 25 30 15 C30 7 24 0 15 0Z" fill="#3b82f6"/>
    <circle cx="15" cy="15" r="8" fill="white" opacity="0.9"/>
    <text x="15" y="20" text-anchor="middle" font-size="11" font-family="sans-serif">📦</text>
</svg>`;
const destIcon = L.divIcon({ html: destSvg, iconSize:[30,36], iconAnchor:[15,36], popupAnchor:[0,-36], className:'' });

// ── Rider marker ────────────────────────────────────
const riderSvg = (status) => {
    const c = status === 'online' ? '#00b37d' : status === 'idle' ? '#f59e0b' : '#8a8580';
    return `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="50" viewBox="0 0 40 50">
        <filter id="ds2"><feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-opacity="0.3"/></filter>
        <ellipse cx="20" cy="46" rx="8" ry="3" fill="rgba(0,0,0,0.18)"/>
        <path d="M20 0 C9 0 0 9 0 20 C0 33 20 50 20 50 C20 50 40 33 40 20 C40 9 31 0 20 0Z"
              fill="${c}" filter="url(#ds2)"/>
        <circle cx="20" cy="20" r="12" fill="white" opacity="0.92"/>
        <text x="20" y="26" text-anchor="middle" font-size="15" font-family="sans-serif">🛵</text>
    </svg>`;
};
const makeRiderIcon = (status) => L.divIcon({
    html: riderSvg(status), iconSize:[40,50], iconAnchor:[20,50], popupAnchor:[0,-50], className:''
});

let riderMarker = null;
let routeLine   = null;
let destMarker  = null;
let simInterval = null;

// ── Route + destination drawing ────────────────────
async function loadAndDrawRoute() {
    if (!IS_LIVE) return;

    try {
        const res = await fetch(`/ninjavan/api/geocode_area.php?area=${encodeURIComponent(RCPT_AREA)}&hub_id=${encodeURIComponent(HUB.id || HUB.name)}&seed=${encodeURIComponent(TRK)}&addr=${encodeURIComponent(RCPT_ADDR)}&_=${Date.now()}`);
        const geo = await res.json();
        
        const dLat = geo.lat;
        const dLng = geo.lng;

        // Fictional simulation state variables
        const steps  = geo.waypoints.length || 40;
        let   step   = 0;
        const status = 'idle';

        // Add destination marker
        if (destMarker) map.removeLayer(destMarker);
        destMarker = L.marker([dLat, dLng], { icon: destIcon })
            .addTo(map)
            .bindPopup(`<div style="padding:4px;min-width:140px;">
                <div style="font-family:'Sora',sans-serif;font-weight:700;font-size:13px;">📦 Delivery destination</div>
                <div style="font-size:12px;color:#666;margin-bottom:6px;">${geo.zone}</div>
                <div style="font-size:11px;color:#000;">Rider: ${RIDER_NAME || 'Your Rider'}</div>
                <div style="font-size:11px;color:#e8002d;font-weight:600;">Status: ${ORDER_STATUS}</div>
            </div>`);

        // Route line: from OSRM waypoints
        const rCoords = geo.waypoints.map(w => [w[0], w[1]]);
        if (routeLine) map.removeLayer(routeLine);
        
        if (IS_OFD) {
            routeLine = L.polyline(rCoords, {
                color: '#3b82f6', weight: 4, dashArray: '10,10', opacity: 0.8,
            }).addTo(map);
        }

        // Fit map bounds to show route
        map.fitBounds([[HUB.lat, HUB.lng], [dLat, dLng]], { padding: [40, 40] });

        // If rider has no GPS, simulate along the OSRM route!
        if (!RIDER_GPS) {
            placeRider(HUB.lat, HUB.lng, status, RIDER_NAME || 'Your Rider',
                '<div style="font-size:11px;color:#f59e0b;margin-top:5px;padding-top:5px;border-top:1px solid #eee;">⚡ Simulated — waiting for real GPS</div>');

            simInterval = setInterval(() => {
                if (step >= geo.waypoints.length) { clearInterval(simInterval); return; }
                const w = geo.waypoints[step];
                placeRider(w[0], w[1], status, RIDER_NAME || 'Your Rider',
                    '<div style="font-size:11px;color:#f59e0b;margin-top:5px;padding-top:5px;border-top:1px solid #eee;">⚡ Simulated — waiting for real GPS</div>');
                step += 3; // speed up slightly
            }, 1000);
        }

    } catch(e) {
        console.warn('Geocode routing failed', e);
    }
}

// ── Place or update rider marker ───────────────────
function placeRider(lat, lng, status, name, popupExtra) {
    const icon = makeRiderIcon(status);
    const popup = `<div style="padding:6px 2px;min-width:170px;">
        <div style="font-family:'Sora',sans-serif;font-weight:700;font-size:14px;margin-bottom:4px;">${name}</div>
        <div style="font-size:12px;color:#555;margin-top:3px;">
            <span style="display:inline-block;width:7px;height:7px;border-radius:50%;
                background:${status==='online'?'#00b37d':status==='idle'?'#f59e0b':'#aaa'};
                margin-right:4px;vertical-align:middle;"></span>
            ${status.charAt(0).toUpperCase()+status.slice(1)} · heading to ${RCPT_AREA}
        </div>
        ${popupExtra || ''}
    </div>`;

    if (riderMarker) {
        riderMarker.setLatLng([lat, lng]).setIcon(icon).getPopup().setContent(popup);
    } else {
        riderMarker = L.marker([lat, lng], { icon }).addTo(map).bindPopup(popup, { maxWidth: 220 });
    }
}

// ── ETA calculation (straight-line distance / avg speed) ──
function calcETA(rLat, rLng, dLat, dLng) {
    const R    = 6371; // km
    const dLa  = (dLat - rLat) * Math.PI / 180;
    const dLo  = (dLng - rLng) * Math.PI / 180;
    const a    = Math.sin(dLa/2)**2
               + Math.cos(rLat*Math.PI/180) * Math.cos(dLat*Math.PI/180) * Math.sin(dLo/2)**2;
    const dist = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    const mins = Math.round((dist / 25) * 60); // assume 25 km/h avg speed
    const el   = document.getElementById('etaLabel');
    if (!el) return;
    if (mins < 2)       el.textContent = 'Arriving soon';
    else if (mins < 60) el.textContent = `~${mins} min away`;
    else                el.textContent = `~${Math.round(mins/60)}h ${mins%60}m away`;
}

// ── Main: Setup Map ───────
if (IS_LIVE) {
    // 1. Fetch OSRM route, place destination, and draw line.
    // If no GPS, this also starts the simulation.
    loadAndDrawRoute();
    
    // 2. If real GPS exists, place the rider immediately
    if (RIDER_GPS) {
        placeRider(RIDER_GPS.lat, RIDER_GPS.lng, RIDER_GPS.status, RIDER_NAME, '');
        map.setView([RIDER_GPS.lat, RIDER_GPS.lng], 14);
        calcETA(RIDER_GPS.lat, RIDER_GPS.lng, HUB.lat, HUB.lng);
    } else {
        const etaEl = document.getElementById('etaLabel');
        if (etaEl) etaEl.textContent = 'GPS signal pending...';
    }
} else {
    // Not yet live — just show hub pin, fit map
    map.setView([HUB.lat, HUB.lng], 13);
}

// ── Auto-refresh GPS every 15s when live ──────────
if (IS_LIVE && TRK) {
    setInterval(async () => {
        try {
            const res  = await fetch('/ninjavan/api/rider_positions.php?_=' + Date.now());
            const data = await res.json();
            if (!Array.isArray(data)) return;

            // Find the assigned rider in the response
            const rdrName = RIDER_NAME;
            const found   = data.find(r => r.name === rdrName);
            if (!found) return;

            // Update marker
            if (simInterval) { clearInterval(simInterval); simInterval = null; }
            placeRider(found.lat, found.lng, found.status, found.name, '');

            // Update badge
            const badgeSub = document.getElementById('badgeStatus');
            if (badgeSub) {
                badgeSub.textContent = found.status.charAt(0).toUpperCase()+found.status.slice(1)
                    + ' · ' + found.updated_at;
            }

            calcETA(found.lat, found.lng, HUB.lat, HUB.lng);
        } catch(e) {
            console.warn('GPS refresh failed', e);
        }
    }, 15000);
}

window.addEventListener('beforeunload', () => {
    if (simInterval) clearInterval(simInterval);
});

}); // end DOMContentLoaded
</script>

<?php include "../layout/dashboard_footer.php"; ?>
