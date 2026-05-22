<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Reports & Analytics";
$activePage = "reports";

$ordersSnap = $db->getReference('orders')->getSnapshot();
$usersSnap = $db->getReference('users')->getSnapshot();

$usersMap = $usersSnap->getValue() ?: [];
$orders = $ordersSnap->getValue() ?: [];

// Computations
$revTotal = 0;
$revMonth = 0;

$totalOrders = 0;
$monthOrders = 0;
$delivered = 0;
$rts = 0;

$shippersStats = []; // Usr_ID => ['orders' => 0, 'dlvd' => 0]
$ridersStats = [];   // Usr_ID => ['success' => 0, 'total' => 0]

$statusBreak = [];   // status => cnt
$dailyOrders = [];   // Y-m-d => cnt
$svcBreak = [];      // svcName => cnt

$thisMonth = date('Y-m');
$sevenDaysAgo = strtotime('-7 days');

foreach ($orders as $k => $o) {
    $totalOrders++;
    
    $st = $o['Ord_Status'] ?? '';
    $statusBreak[$st] = ($statusBreak[$st] ?? 0) + 1;
    if ($st === 'Delivered') $delivered++;
    if ($st === 'RTS') $rts++;
    
    $crtd = $o['Ord_CrtdDt'] ?? '';
    $dt = substr($crtd, 0, 10);
    if ($dt && strtotime($dt) >= $sevenDaysAgo) {
        $dailyOrders[$dt] = ($dailyOrders[$dt] ?? 0) + 1;
    }
    
    if (strpos($crtd, $thisMonth) === 0) {
        $monthOrders++;
    }
    
    $fee = (float)($o['fee']['Fee_Total'] ?? 0);
    if ($st === 'Delivered') {
        $revTotal += $fee;
        if (strpos($crtd, $thisMonth) === 0) {
            $revMonth += $fee;
        }
    }
    
    $shprId = $o['Ord_ShprID'] ?? '';
    if ($shprId) {
        if (!isset($shippersStats[$shprId])) $shippersStats[$shprId] = ['orders' => 0, 'dlvd' => 0];
        $shippersStats[$shprId]['orders']++;
        if ($st === 'Delivered') $shippersStats[$shprId]['dlvd']++;
    }
    
    $svc = $o['service']['Svc_Name'] ?? 'Standard';
    $svcBreak[$svc] = ($svcBreak[$svc] ?? 0) + 1;
    
    if (isset($o['delivery_attempts']) && is_array($o['delivery_attempts'])) {
        foreach ($o['delivery_attempts'] as $att) {
            $rid = $att['Atmp_RdrID'] ?? '';
            $res = $att['Atmp_Rslt'] ?? '';
            if ($rid) {
                if (!isset($ridersStats[$rid])) $ridersStats[$rid] = ['success' => 0, 'total' => 0];
                $ridersStats[$rid]['total']++;
                if ($res === 'Successful') $ridersStats[$rid]['success']++;
            }
        }
    }
}

$deliveryRate = $totalOrders > 0 ? round(($delivered / $totalOrders) * 100, 1) : 0;

$topShippers = [];
foreach ($shippersStats as $id => $s) {
    $topShippers[] = [
        'Usr_Name' => $usersMap[$id]['Usr_Name'] ?? 'Unknown Shipper',
        'orders' => $s['orders'],
        'dlvd' => $s['dlvd']
    ];
}
usort($topShippers, function($a, $b) { return $b['orders'] <=> $a['orders']; });
$topShippers = array_slice($topShippers, 0, 5);

$topRiders = [];
foreach ($ridersStats as $id => $r) {
    $topRiders[] = [
        'Rdr_Name' => $usersMap[$id]['Usr_Name'] ?? 'Unknown Rider',
        'success' => $r['success'],
        'total' => $r['total']
    ];
}
usort($topRiders, function($a, $b) { return $b['success'] <=> $a['success']; });
$topRiders = array_slice($topRiders, 0, 5);

ksort($dailyOrders);
$days = [];
foreach ($dailyOrders as $dt => $cnt) {
    $days[] = ['dt' => $dt, 'cnt' => $cnt];
}
$dayCounts = array_column($days, 'cnt');
$maxD = !empty($dayCounts) ? max($dayCounts) : 1;

arsort($statusBreak);
$statusRows = [];
foreach ($statusBreak as $st => $cnt) {
    if ($st) $statusRows[] = ['Ord_Status' => $st, 'cnt' => $cnt];
}
$grandTotal = $totalOrders ?: 1;

arsort($svcBreak);
$svcList = [];
foreach ($svcBreak as $svc => $cnt) {
    $svcList[] = ['Svc_Name' => $svc, 'cnt' => $cnt];
}

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
                'Order Created'          => 'badge-pending',
                'Pickup / Drop-off'   => 'badge-confirmed',
                'Origin Sorting Hub'       => 'badge-transit',
                'Main Sorting Hub'       => 'badge-transit',
                'Regional Hub'       => 'badge-transit',
                'Destination Hub'       => 'badge-transit',
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
            if(!empty($svcList)):
                foreach($svcList as $sv):
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
            <?php endforeach; endif;
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
                if(!empty($topShippers)):
                    $i = 1;
                    foreach($topShippers as $ts):
                        $hasShippers = true;
                ?>
                <tr>
                    <td style="font-weight:700;color:var(--red);"><?= $i++ ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($ts['Usr_Name']) ?></td>
                    <td style="font-weight:700;"><?= $ts['orders'] ?></td>
                    <td style="color:var(--green);font-weight:700;"><?= $ts['dlvd'] ?></td>
                </tr>
                <?php endforeach; endif;
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
                if(!empty($topRiders)):
                    $i = 1;
                    foreach($topRiders as $tr):
                        $hasRiders = true;
                        $rate = $tr['total'] > 0 ? round(($tr['success'] / $tr['total']) * 100) : 0;
                ?>
                <tr>
                    <td style="font-weight:700;color:var(--red);"><?= $i++ ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($tr['Rdr_Name']) ?></td>
                    <td style="font-weight:700;"><?= $tr['success'] ?></td>
                    <td><span style="color:<?= $rate >= 80 ? 'var(--green)' : 'var(--amber)' ?>;font-weight:700;"><?= $rate ?>%</span></td>
                </tr>
                <?php endforeach; endif;
                if(!$hasRiders): ?>
                <tr><td colspan="4"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-bicycle"></i></div><p>No delivery data yet</p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include "../layout/dashboard_footer.php"; ?>
