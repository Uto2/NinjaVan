<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'rider'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Dashboard";
$activePage = "dashboard";
$riderId    = $_SESSION['rider_id'] ?? '';

// ---- STATS ----
$assigned = 0;
$delivered = 0;
$failed = 0;
$total = 0;
$today = [];

$ordersRef = $db->getReference('orders');
$ordersSnap = $ordersRef->getSnapshot();

if ($ordersSnap->hasChildren()) {
    $allOrders = $ordersSnap->getValue();
    
    // Sort for recent
    uasort($allOrders, function($a, $b) {
        return strtotime($b['Ord_CrtdDt'] ?? 0) <=> strtotime($a['Ord_CrtdDt'] ?? 0);
    });

    foreach ($allOrders as $o) {
        // Check if assigned to this rider via Rider_ID or delivery_attempts
        $isAssigned = false;
        
        if (($o['Rider_ID'] ?? '') === $riderId) {
            $isAssigned = true;
        } else if (isset($o['delivery_attempts'])) {
            foreach ($o['delivery_attempts'] as $att) {
                if (($att['Atmp_RdrID'] ?? '') === $riderId) {
                    $isAssigned = true; break;
                }
            }
        }
        
        if ($isAssigned) {
            $status = $o['Ord_Status'] ?? '';
            
            // Count attempts
            if (isset($o['delivery_attempts'])) {
                foreach ($o['delivery_attempts'] as $att) {
                    if (($att['Atmp_RdrID'] ?? '') === $riderId) {
                        $total++;
                        $r = $att['Atmp_Rslt'] ?? '';
                        $type = $att['Atmp_Type'] ?? 'Delivery';
                        
                        if ($r === 'Successful' && $type !== 'Pickup') {
                            $delivered++;
                        }
                        if ($r === 'Failed' || $r === 'Unavailable') {
                            $failed++;
                        }
                    }
                }
            }
            
            // Active deliveries assigned to this rider
            if (in_array($status, ['Pickup / Drop-off', 'Out for Delivery'])) {
                $assigned++;
                if (count($today) < 5) {
                    $today[] = $o;
                }
            }
        }
    }
}

$rate = $total > 0 ? round(($delivered / $total) * 100) : 0;

include "../layout/dashboard_layout.php";
?>

<!-- STATS -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="bi bi-truck"></i></div>
            <div class="stat-value"><?= $assigned ?></div>
            <div class="stat-label">Active Deliveries</div>
            <div class="stat-trend neu"><i class="bi bi-dash"></i> In progress</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-value"><?= $delivered ?></div>
            <div class="stat-label">Delivered</div>
            <div class="stat-trend up"><i class="bi bi-arrow-up"></i> Completed</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card red">
            <div class="stat-icon red"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-value"><?= $failed ?></div>
            <div class="stat-label">Failed</div>
            <div class="stat-trend down"><i class="bi bi-arrow-down"></i> Unsuccessful</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="bi bi-graph-up"></i></div>
            <div class="stat-value"><?= $rate ?>%</div>
            <div class="stat-label">Success Rate</div>
            <div class="stat-trend <?= $rate >= 80 ? 'up' : 'neu' ?>">
                <i class="bi bi-<?= $rate >= 80 ? 'arrow-up' : 'dash' ?>"></i>
                <?= $rate >= 80 ? 'Great job!' : 'Keep it up' ?>
            </div>
        </div>
    </div>
</div>

<!-- ACTIVE DELIVERIES + STATUS GUIDE -->
<div class="row g-4">

    <!-- ACTIVE DELIVERIES -->
    <div class="col-lg-8">
        <div class="nv-card">
            <div style="padding:18px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                <h5 style="font-size:15px; margin:0;">Active Deliveries</h5>
                <a href="/ninjavan/rider/my_deliveries.php" class="btn-nv-ghost" style="font-size:12px; padding:6px 14px;">View all</a>
            </div>
            <div style="overflow-x:auto;">
                <table class="nv-table">
                    <thead>
                        <tr>
                            <th>Tracking No.</th>
                            <th>Recipient</th>
                            <th>Area</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(empty($today)): ?>
                        <tr><td colspan="4">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="bi bi-truck"></i></div>
                                <h4>No active deliveries</h4>
                                <p>You're all caught up!</p>
                            </div>
                        </td></tr>
                    <?php else: foreach($today as $r): ?>
                        <tr>
                            <td>
                                <span style="font-family:'Sora',sans-serif; font-size:13px; font-weight:700; color:var(--red);">
                                    <?= htmlspecialchars($r['awb']['AWB_TrkNum'] ?? $r['Ord_ID']) ?>
                                </span>
                            </td>
                            <td style="font-weight:500;"><?= htmlspecialchars($r['recipient']['Rcpt_Name'] ?? '') ?></td>
                            <td style="color:var(--muted); font-size:13px;"><?= htmlspecialchars($r['recipient']['Rcpt_Area'] ?? '—') ?></td>
                            <td><?php
                                $s = $r['Ord_Status'] ?? '';
                                $map = [
                                    'Pickup / Drop-off'=>'badge-confirmed',
                                    'Origin Sorting Hub'=>'badge-transit','Main Sorting Hub'=>'badge-transit','Regional Hub'=>'badge-transit','Destination Hub'=>'badge-transit',
                                    'Out for Delivery'=>'badge-delivery',
                                    'Delivered'=>'badge-delivered',
                                    'Failed'=>'badge-failed',
                                    'RTS'=>'badge-cancelled',
                                ];
                                $cls = $map[$s] ?? 'badge-pending';
                                echo "<span class='badge-status $cls'>$s</span>";
                            ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- STATUS FLOW GUIDE -->
    <div class="col-lg-4">
        <div class="nv-card p-4">
            <h5 style="font-size:15px; margin-bottom:20px;">Delivery Status Flow</h5>

            <?php
            $steps = [
                ['Pickup / Drop-off',   'badge-confirmed', 'Pickup / Drop-off',   'Parcel confirmed, ready for pickup'],
                ['Origin Sorting Hub',       'badge-transit',   'Hub Processing',       'Parcel is being processed at hubs'],
                ['Out for Delivery', 'badge-delivery',  'Out for Delivery', 'Last mile — delivering now'],
                ['Delivered',        'badge-delivered', 'Delivered',        'Successfully handed to recipient'],
            ];
            foreach($steps as $i => [$key,$cls,$label,$desc]):
            ?>
            <div style="display:flex; gap:12px; margin-bottom:0;">
                <div style="display:flex; flex-direction:column; align-items:center; width:20px; flex-shrink:0;">
                    <div style="width:20px;height:20px;border-radius:50%;background:var(--surface);border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:var(--muted);">
                        <?= $i+1 ?>
                    </div>
                    <?php if($i < count($steps)-1): ?>
                    <div style="flex:1;width:2px;background:var(--border);min-height:28px;margin:4px 0;"></div>
                    <?php endif; ?>
                </div>
                <div style="padding-top:1px; margin-bottom:<?= $i < count($steps)-1 ? '12px':'0' ?>;">
                    <span class="badge-status <?= $cls ?>" style="margin-bottom:3px; display:inline-flex;"><?= $label ?></span>
                    <div style="font-size:11px; color:var(--muted); line-height:1.4;"><?= $desc ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<?php include "../layout/dashboard_footer.php"; ?>
