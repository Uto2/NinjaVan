<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Dashboard";
$activePage = "dashboard";

// ---- STATS ----
$totalOrders = 0;
$totalShippers = 0;
$totalRiders = 0;
$delivered = 0;
$staging = 0;
$revenue = 0;

$usersSnap = $db->getReference('users')->getSnapshot();
if ($usersSnap->hasChildren()) {
    foreach ($usersSnap->getValue() as $u) {
        $type = $u['Usr_Type'] ?? '';
        if ($type === 'shipper') $totalShippers++;
        if ($type === 'rider') $totalRiders++;
    }
}

$statusBreakdown = [];
$recentOrders = [];

$ordersSnap = $db->getReference('orders')->getSnapshot();
if ($ordersSnap->hasChildren()) {
    $allOrders = $ordersSnap->getValue();
    $totalOrders = count($allOrders);
    
    // Sort for recent
    uasort($allOrders, function($a, $b) {
        return strtotime($b['Ord_CrtdDt'] ?? 0) <=> strtotime($a['Ord_CrtdDt'] ?? 0);
    });

    foreach ($allOrders as $o) {
        $s = $o['Ord_Status'] ?? 'Order Created';
        
        if ($s === 'Delivered') {
            $delivered++;
            $revenue += ($o['fee']['Fee_Total'] ?? 0);
        }
        if ($s === 'Order Created') {
            $staging++;
        }
        
        if (!isset($statusBreakdown[$s])) $statusBreakdown[$s] = 0;
        $statusBreakdown[$s]++;
        
        if (count($recentOrders) < 8) {
            $recentOrders[] = $o;
        }
    }
}

// Sort status breakdown by count desc
arsort($statusBreakdown);

// ---- AVAILABLE RIDERS ----
$availRiders = [];
if ($usersSnap->hasChildren()) {
    foreach ($usersSnap->getValue() as $u) {
        if (($u['Usr_Type'] ?? '') === 'rider' && ($u['Usr_Status'] ?? '') === 'Active') {
            $availRiders[] = $u;
            if(count($availRiders) >= 4) break;
        }
    }
}

include "../layout/dashboard_layout.php";
?>

<!-- STATS ROW -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card red">
            <div class="stat-icon red"><i class="bi bi-boxes"></i></div>
            <div class="stat-value"><?= number_format($totalOrders) ?></div>
            <div class="stat-label">Total Orders</div>
            <div class="stat-trend neu"><i class="bi bi-dash"></i> All time</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="bi bi-cash-coin"></i></div>
            <div class="stat-value">₱<?= number_format($revenue, 0) ?></div>
            <div class="stat-label">Revenue (Delivered)</div>
            <div class="stat-trend up"><i class="bi bi-arrow-up"></i> Collected fees</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
            <div class="stat-value"><?= $totalShippers ?></div>
            <div class="stat-label">Registered Shippers</div>
            <div class="stat-trend up"><i class="bi bi-arrow-up"></i> Growing</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="stat-value"><?= $staging ?></div>
            <div class="stat-label">Order Created</div>
            <div class="stat-trend <?= $staging > 0 ? 'down' : 'up' ?>">
                <i class="bi bi-<?= $staging > 0 ? 'exclamation-circle' : 'check-circle' ?>"></i>
                <?= $staging > 0 ? 'Needs action' : 'All processed' ?>
            </div>
        </div>
    </div>
</div>

<!-- SECOND ROW -->
<div class="row g-4 mb-4">

    <!-- STATUS BREAKDOWN -->
    <div class="col-lg-4">
        <div class="nv-card p-4 h-100">
            <h5 style="font-size:15px; margin-bottom:20px;">Order Status Breakdown</h5>

            <?php
            $statusMeta = [
                'Order Created'        => ['badge-pending',   'Order Created'],
                'Pickup / Drop-off' => ['badge-confirmed', 'Pickup / Drop-off'],
                'Origin Sorting Hub'     => ['badge-transit',   'Origin Sorting Hub'],
                'Main Sorting Hub'     => ['badge-transit',   'Main Sorting Hub'],
                'Regional Hub'     => ['badge-transit',   'Regional Hub'],
                'Destination Hub'     => ['badge-transit',   'Destination Hub'],
                'Delivered'      => ['badge-delivered',  'Delivered'],
                'RTS'            => ['badge-failed',    'RTS'],
            ];
            $rows = [];
            foreach ($statusBreakdown as $s => $cnt) $rows[] = ['Ord_Status' => $s, 'cnt' => $cnt];
            $grandTotal = array_sum(array_column($rows, 'cnt')) ?: 1;

            foreach($rows as $r):
                $s   = $r['Ord_Status'];
                $cnt = $r['cnt'];
                $pct = round(($cnt / $grandTotal) * 100);
                [$cls, $lbl] = $statusMeta[$s] ?? ['badge-pending', $s];
            ?>
            <div style="margin-bottom:14px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                    <span class="badge-status <?= $cls ?>"><?= $lbl ?></span>
                    <span style="font-family:'Sora',sans-serif; font-size:13px; font-weight:700;"><?= $cnt ?> <span style="font-weight:400; color:var(--muted); font-size:11px;">(<?= $pct ?>%)</span></span>
                </div>
                <div style="height:5px; background:var(--border); border-radius:3px; overflow:hidden;">
                    <div style="height:100%; width:<?= $pct ?>%; background:var(--red); border-radius:3px; transition:width 0.6s ease;"></div>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </div>

    <!-- AVAILABLE RIDERS -->
    <div class="col-lg-4">
        <div class="nv-card p-4 h-100">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h5 style="font-size:15px; margin:0;">Active Riders</h5>
                <a href="/ninjavan/admin/manage_riders.php" class="btn-nv-ghost" style="font-size:12px; padding:5px 12px;">All</a>
            </div>

            <?php if(empty($availRiders)): ?>
            <div class="empty-state" style="padding:30px 0;">
                <div class="empty-state-icon"><i class="bi bi-bicycle"></i></div>
                <h4>No riders available</h4>
                <p>All riders are currently on leave</p>
            </div>
            <?php else: foreach($availRiders as $c): ?>
            <div style="display:flex; align-items:center; gap:12px; padding:12px; border-radius:10px; border:1px solid var(--border); margin-bottom:8px;">
                <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--ink),var(--ink-3));display:flex;align-items:center;justify-content:center;color:#fff;font-family:'Sora',sans-serif;font-weight:700;font-size:14px;flex-shrink:0;">
                    <?= strtoupper(substr($c['Usr_Name'] ?? 'R',0,1)) ?>
                </div>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:13px; font-weight:700; color:var(--ink);">
                        <?= htmlspecialchars($c['Usr_Name'] ?? 'Rider') ?>
                    </div>
                    <div style="font-size:11px; color:var(--muted);"><?= htmlspecialchars($c['Rdr_VhcTyp'] ?? 'Vehicle') ?></div>
                </div>
                <span class="badge-status badge-active"><?= $c['Usr_Status'] ?? 'Active' ?></span>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- QUICK STATS -->
    <div class="col-lg-4">
        <div class="nv-card p-4 h-100">
            <h5 style="font-size:15px; margin-bottom:20px;">Quick Overview</h5>

            <?php
            $qstats = [
                ['Active Riders',     $totalRiders, 'bi-bicycle',          'var(--blue)'],
                ['Delivered Orders',  $delivered,   'bi-check-circle-fill','var(--green)'],
                ['Order Created',    $staging,     'bi-hourglass-split',  'var(--amber)'],
            ];
            foreach($qstats as [$label, $val, $icon, $color]):
            ?>
            <div style="display:flex; align-items:center; gap:14px; padding:14px 0; border-bottom:1px solid var(--border);">
                <div style="width:38px;height:38px;border-radius:10px;background:rgba(0,0,0,0.03);display:flex;align-items:center;justify-content:center;font-size:17px;color:<?= $color ?>;flex-shrink:0;">
                    <i class="bi <?= $icon ?>"></i>
                </div>
                <div style="flex:1;">
                    <div style="font-size:12px; color:var(--muted);"><?= $label ?></div>
                </div>
                <div style="font-family:'Sora',sans-serif; font-size:20px; font-weight:800; color:var(--ink);"><?= $val ?></div>
            </div>
            <?php endforeach; ?>

            <div style="margin-top:20px; display:flex; flex-direction:column; gap:8px;">
                <a href="/ninjavan/admin/manage_parcels.php" class="btn-nv" style="justify-content:center;">
                    <i class="bi bi-boxes"></i> Manage Parcels
                </a>
                <a href="/ninjavan/admin/reports.php" class="btn-nv-ghost" style="justify-content:center;">
                    <i class="bi bi-bar-chart-fill"></i> View Reports
                </a>
            </div>
        </div>
    </div>

</div>

<!-- RECENT ORDERS TABLE -->
<div class="nv-card">
    <div style="padding:18px 22px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h5 style="font-size:15px; margin:0 0 2px;">Recent Orders</h5>
            <p style="font-size:12px; color:var(--muted); margin:0;">Latest 8 orders across all shippers</p>
        </div>
        <a href="/ninjavan/admin/manage_orders.php" class="btn-nv-ghost" style="font-size:12px; padding:6px 14px;">View all</a>
    </div>
    <div style="overflow-x:auto;">
        <table class="nv-table">
            <thead>
                <tr>
                    <th>Tracking No.</th>
                    <th>Shipper</th>
                    <th>Recipient</th>
                    <th>Area</th>
                    <th>Service</th>
                    <th>Status</th>
                    <th>Fee</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($recentOrders)): ?>
                <tr><td colspan="7">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-box"></i></div>
                        <h4>No orders yet</h4>
                    </div>
                </td></tr>
            <?php else: foreach($recentOrders as $r):
                $s   = $r['Ord_Status'] ?? 'Order Created';
                $map = [
                    'Order Created'=>'badge-pending','Pickup / Drop-off'=>'badge-confirmed',
                    'Origin Sorting Hub'=>'badge-transit','Main Sorting Hub'=>'badge-transit','Regional Hub'=>'badge-transit','Destination Hub'=>'badge-transit','Delivered'=>'badge-delivered',
                    'RTS'=>'badge-failed',
                ];
                $cls = $map[$s] ?? 'badge-pending';
                
                // Fetch shipper name from $usersSnap for displaying
                $shipperName = 'Shipper';
                if ($usersSnap->hasChildren()) {
                    foreach ($usersSnap->getValue() as $u) {
                        if (($u['Usr_ID'] ?? '') === ($r['Ord_ShprID'] ?? '')) {
                            $shipperName = $u['Usr_Name'] ?? 'Shipper';
                            break;
                        }
                    }
                }
            ?>
                <tr>
                    <td><span style="font-family:'Sora',sans-serif;font-size:13px;font-weight:700;color:var(--red);"><?= htmlspecialchars($r['awb']['AWB_TrkNum'] ?? $r['Ord_ID']) ?></span></td>
                    <td style="font-size:13px;"><?= htmlspecialchars($shipperName) ?></td>
                    <td style="font-weight:500;"><?= htmlspecialchars($r['recipient']['Rcpt_Name'] ?? '') ?></td>
                    <td style="font-size:13px;color:var(--muted);"><?= htmlspecialchars($r['recipient']['Rcpt_Area'] ?? '—') ?></td>
                    <td style="font-size:13px;"><?= htmlspecialchars($r['service']['Svc_Name'] ?? '') ?></td>
                    <td><span class="badge-status <?= $cls ?>"><?= $s ?></span></td>
                    <td style="font-family:'Sora',sans-serif;font-weight:700;">₱<?= number_format($r['fee']['Fee_Total'] ?? 0, 2) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "../layout/dashboard_footer.php"; ?>
