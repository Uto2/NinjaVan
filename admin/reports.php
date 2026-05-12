<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Reports & Analytics";
$activePage = "reports";

// ── Revenue ──────────────────────────────────────────────────
$revTotalRes = $conn->query("SELECT IFNULL(SUM(f.Fee_Total),0) c FROM SHIPPING_FEE f JOIN `ORDER` o ON f.Fee_OrdID=o.Ord_ID WHERE o.Ord_Status='Delivered'");
$revTotal    = $revTotalRes ? $revTotalRes->fetch_assoc()['c'] : 0;

$revMonthRes = $conn->query("SELECT IFNULL(SUM(f.Fee_Total),0) c FROM SHIPPING_FEE f JOIN `ORDER` o ON f.Fee_OrdID=o.Ord_ID WHERE o.Ord_Status='Delivered' AND MONTH(o.Ord_CrtdDt)=MONTH(NOW()) AND YEAR(o.Ord_CrtdDt)=YEAR(NOW())");
$revMonth    = $revMonthRes ? $revMonthRes->fetch_assoc()['c'] : 0;

// ── Order stats ───────────────────────────────────────────────
$totalOrders = $conn->query("SELECT COUNT(*) c FROM `ORDER`")->fetch_assoc()['c'] ?? 0;
$monthOrders = $conn->query("SELECT COUNT(*) c FROM `ORDER` WHERE MONTH(Ord_CrtdDt)=MONTH(NOW()) AND YEAR(Ord_CrtdDt)=YEAR(NOW())")->fetch_assoc()['c'] ?? 0;
$delivered   = $conn->query("SELECT COUNT(*) c FROM `ORDER` WHERE Ord_Status='Delivered'")->fetch_assoc()['c'] ?? 0;
$rts         = $conn->query("SELECT COUNT(*) c FROM `ORDER` WHERE Ord_Status='RTS'")->fetch_assoc()['c'] ?? 0;
$deliveryRate = $totalOrders > 0 ? round(($delivered / $totalOrders) * 100, 1) : 0;

// ── Top shippers ──────────────────────────────────────────────
$topShippers = $conn->query("
    SELECT u.Usr_Name, COUNT(o.Ord_ID) as orders,
           SUM(CASE WHEN o.Ord_Status='Delivered' THEN 1 ELSE 0 END) as dlvd
    FROM `ORDER` o
    JOIN SHIPPER sh ON o.Ord_ShprID = sh.Shpr_ID
    JOIN USER_ACCOUNT u ON sh.Shpr_UsrID = u.Usr_ID
    GROUP BY u.Usr_ID ORDER BY orders DESC LIMIT 5
");

// ── Top riders ────────────────────────────────────────────────
$topRiders = $conn->query("
    SELECT rd.Rdr_Name,
           COUNT(CASE WHEN da.Atmp_Rslt='Successful' THEN 1 END) as success,
           COUNT(da.Atmp_ID) as total
    FROM DELIVERY_ATTEMPT da
    JOIN RIDER rd ON da.Atmp_RdrID = rd.Rdr_ID
    GROUP BY rd.Rdr_ID ORDER BY success DESC LIMIT 5
");

// ── Status breakdown ──────────────────────────────────────────
$statusBreak = $conn->query("SELECT Ord_Status, COUNT(*) cnt FROM `ORDER` GROUP BY Ord_Status ORDER BY cnt DESC");

// ── Daily orders — last 7 days ────────────────────────────────
$dailyRes = $conn->query("
    SELECT DATE(Ord_CrtdDt) as dt, COUNT(*) as cnt
    FROM `ORDER`
    WHERE Ord_CrtdDt >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(Ord_CrtdDt) ORDER BY dt ASC
");
$days = [];
if($dailyRes) { while($d = $dailyRes->fetch_assoc()) $days[] = $d; }
$dayCounts = array_column($days, 'cnt');
$maxD = !empty($dayCounts) ? max($dayCounts) : 1;   // ← THE BUG FIX

// ── Service breakdown ─────────────────────────────────────────
$svcBreak = $conn->query("
    SELECT sv.Svc_Name, COUNT(o.Ord_ID) as cnt
    FROM `ORDER` o
    JOIN SERVICE_TYPE sv ON o.Ord_SvcID = sv.Svc_ID
    GROUP BY sv.Svc_ID ORDER BY cnt DESC
");

// Pre-fetch status rows so we can reuse grand total in service section
$statusRows = [];
if($statusBreak) { while($r = $statusBreak->fetch_assoc()) $statusRows[] = $r; }
$grandTotal = array_sum(array_column($statusRows, 'cnt')) ?: 1;

include "../layout/dashboard_layout.php";
?>

<div class="page-header">
    <div><h1>Reports &amp; Analytics</h1><p>Platform performance overview and insights</p></div>
</div>

<!-- ── KPI Row ────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="bi bi-cash-coin"></i></div>
            <div class="stat-value">₱<?= number_format($revTotal, 0) ?></div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-trend up"><i class="bi bi-arrow-up"></i> All time</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="bi bi-calendar3"></i></div>
            <div class="stat-value">₱<?= number_format($revMonth, 0) ?></div>
            <div class="stat-label">Revenue This Month</div>
            <div class="stat-trend up"><i class="bi bi-arrow-up"></i> <?= date('F') ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card red">
            <div class="stat-icon red"><i class="bi bi-boxes"></i></div>
            <div class="stat-value"><?= number_format($totalOrders) ?></div>
            <div class="stat-label">Total Orders</div>
            <div class="stat-trend neu"><i class="bi bi-dash"></i> <?= $monthOrders ?> this month</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="bi bi-graph-up"></i></div>
            <div class="stat-value"><?= $deliveryRate ?>%</div>
            <div class="stat-label">Delivery Success Rate</div>
            <div class="stat-trend <?= $deliveryRate >= 80 ? 'up' : 'down' ?>">
                <i class="bi bi-arrow-<?= $deliveryRate >= 80 ? 'up' : 'down' ?>"></i>
                <?= $delivered ?> of <?= $totalOrders ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Charts Row ─────────────────────────────────────────── -->
<div class="row g-4 mb-4">

    <!-- Status Breakdown -->
    <div class="col-lg-4">
        <div class="nv-card p-4 h-100">
            <h5 style="font-size:15px;margin-bottom:20px;">Order Status Breakdown</h5>
            <?php
            $sMeta = [
                'Staging'          => 'badge-pending',
                'Pending Pickup'   => 'badge-confirmed',
                'In Transit'       => 'badge-transit',
                'Out for Delivery' => 'badge-delivery',
                'Delivered'        => 'badge-delivered',
                'RTS'              => 'badge-failed',
            ];
            if(empty($statusRows)): ?>
                <div class="empty-state" style="padding:30px 0;"><p>No orders yet</p></div>
            <?php else:
                foreach($statusRows as $r):
                    $pct = round(($r['cnt'] / $grandTotal) * 100);
                    $cls = $sMeta[$r['Ord_Status']] ?? 'badge-pending';
            ?>
            <div style="margin-bottom:14px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                    <span class="badge-status <?= $cls ?>"><?= htmlspecialchars($r['Ord_Status']) ?></span>
                    <span style="font-family:'Sora',sans-serif;font-size:13px;font-weight:700;">
                        <?= $r['cnt'] ?> <span style="font-weight:400;color:var(--muted);font-size:11px;">(<?= $pct ?>%)</span>
                    </span>
                </div>
                <div style="height:5px;background:var(--border);border-radius:3px;overflow:hidden;">
                    <div style="height:100%;width:<?= $pct ?>%;background:var(--red);border-radius:3px;"></div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Service Popularity -->
    <div class="col-lg-4">
        <div class="nv-card p-4 h-100">
            <h5 style="font-size:15px;margin-bottom:20px;">Service Popularity</h5>
            <?php
            $hasSvc = false;
            if($svcBreak):
                while($sv = $svcBreak->fetch_assoc()):
                    $hasSvc = true;
                    $pct = round(($sv['cnt'] / $grandTotal) * 100);
            ?>
            <div style="display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid var(--border);">
                <div style="width:38px;height:38px;border-radius:10px;background:rgba(232,0,45,0.08);display:flex;align-items:center;justify-content:center;font-size:17px;color:var(--red);">
                    <i class="bi bi-truck"></i>
                </div>
                <div style="flex:1;">
                    <div style="font-size:14px;font-weight:600;"><?= htmlspecialchars($sv['Svc_Name']) ?></div>
                    <div style="height:4px;background:var(--border);border-radius:2px;margin-top:6px;overflow:hidden;">
                        <div style="height:100%;width:<?= $pct ?>%;background:var(--red);border-radius:2px;"></div>
                    </div>
                </div>
                <div style="font-family:'Sora',sans-serif;font-size:20px;font-weight:800;"><?= $sv['cnt'] ?></div>
            </div>
            <?php endwhile; endif;
            if(!$hasSvc): ?>
                <div class="empty-state" style="padding:30px 0;"><p>No orders yet</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Last 7 Days Volume -->
    <div class="col-lg-4">
        <div class="nv-card p-4 h-100">
            <h5 style="font-size:15px;margin-bottom:20px;">Last 7 Days Volume</h5>
            <?php if(empty($days)): ?>
                <div class="empty-state" style="padding:30px 0;"><p>No orders in last 7 days</p></div>
            <?php else:
                foreach($days as $d):
                    $hPct = round(($d['cnt'] / $maxD) * 100);
            ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                <div style="width:70px;font-size:12px;color:var(--muted);font-weight:600;">
                    <?= date('M j', strtotime($d['dt'])) ?>
                </div>
                <div style="flex:1;height:22px;background:var(--surface);border-radius:4px;overflow:hidden;">
                    <div style="height:100%;width:<?= $hPct ?>%;background:linear-gradient(90deg,var(--red),#ff4d6d);border-radius:4px;display:flex;align-items:center;justify-content:flex-end;padding-right:8px;">
                        <span style="font-size:10px;font-weight:700;color:#fff;"><?= $d['cnt'] ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

</div>

<!-- ── Tables Row ─────────────────────────────────────────── -->
<div class="row g-4">

    <!-- Top Shippers -->
    <div class="col-lg-6">
        <div class="nv-card p-4">
            <h5 style="font-size:15px;margin-bottom:20px;">Top Shippers</h5>
            <table class="nv-table">
                <thead><tr><th>#</th><th>Shipper</th><th>Orders</th><th>Delivered</th></tr></thead>
                <tbody>
                <?php
                $hasShippers = false;
                if($topShippers):
                    $i = 1;
                    while($ts = $topShippers->fetch_assoc()):
                        $hasShippers = true;
                ?>
                <tr>
                    <td style="font-weight:700;color:var(--red);"><?= $i++ ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($ts['Usr_Name']) ?></td>
                    <td style="font-weight:700;"><?= $ts['orders'] ?></td>
                    <td style="color:var(--green);font-weight:700;"><?= $ts['dlvd'] ?></td>
                </tr>
                <?php endwhile; endif;
                if(!$hasShippers): ?>
                <tr><td colspan="4"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-people"></i></div><p>No shipper data yet</p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Riders -->
    <div class="col-lg-6">
        <div class="nv-card p-4">
            <h5 style="font-size:15px;margin-bottom:20px;">Top Riders</h5>
            <table class="nv-table">
                <thead><tr><th>#</th><th>Rider</th><th>Deliveries</th><th>Rate</th></tr></thead>
                <tbody>
                <?php
                $hasRiders = false;
                if($topRiders):
                    $i = 1;
                    while($tr = $topRiders->fetch_assoc()):
                        $hasRiders = true;
                        $rate = $tr['total'] > 0 ? round(($tr['success'] / $tr['total']) * 100) : 0;
                ?>
                <tr>
                    <td style="font-weight:700;color:var(--red);"><?= $i++ ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($tr['Rdr_Name']) ?></td>
                    <td style="font-weight:700;"><?= $tr['success'] ?></td>
                    <td><span style="color:<?= $rate >= 80 ? 'var(--green)' : 'var(--amber)' ?>;font-weight:700;"><?= $rate ?>%</span></td>
                </tr>
                <?php endwhile; endif;
                if(!$hasRiders): ?>
                <tr><td colspan="4"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-bicycle"></i></div><p>No delivery data yet</p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include "../layout/dashboard_footer.php"; ?>
