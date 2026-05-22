<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'shipper'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Track Parcel";
$activePage = "track";
$shipperId  = $_SESSION['shipper_id'];

$trk = isset($_GET['trk']) ? trim($_GET['trk']) : '';
$order = null;
$timeline = [];

if($trk) {
    $ordersSnapshot = $db->getReference('orders')
                         ->orderByChild('awb/AWB_TrkNum')
                         ->equalTo($trk)
                         ->getSnapshot();
                         
    if($ordersSnapshot->hasChildren()) {
        $results = $ordersSnapshot->getValue();
        $order = reset($results); // Get the first match
        $currStatus = $order['Ord_Status'] ?? 'Order Created';
        
        $currStatus = $order['Ord_Status'] ?? 'Order Created';
        
        $baseDate = strtotime($order['Ord_CrtdDt']);
        $timeline = [];
        
        if (isset($order['tracking']) && count($order['tracking']) > 0) {
            foreach ($order['tracking'] as $t) {
                $timeline[] = [
                    'date'  => $t['Trk_Date'],
                    'title' => $t['Trk_Status'],
                    'desc'  => $t['Trk_Desc'],
                    'icon'  => $t['Trk_Status'] === 'Order Created' ? 'bi-file-earmark-check' : 
                              ($t['Trk_Status'] === 'Pickup / Drop-off' ? 'bi-box-seam' : 
                              ($t['Trk_Status'] === 'Out for Delivery' ? 'bi-truck' : 'bi-geo-alt')),
                    'done'  => true,
                    'color' => null
                ];
            }
        } else {
            // fallback if no tracking array exists (mathematical timeline based on Ord_Status)
            $statusOrder = [
                'Order Created' => 0, 'Pickup / Drop-off' => 1, 'Origin Sorting Hub' => 2,
                'Main Sorting Hub' => 3, 'Regional Hub' => 4, 'Destination Hub' => 5,
                'Out for Delivery' => 6, 'Delivered' => 7, 'RTS' => 8
            ];
            $currIdx = $statusOrder[$currStatus] ?? 0;
            
            $timeline[] = [
                'date' => date('Y-m-d H:i:s', $baseDate),
                'title' => 'Order Created',
                'desc' => 'Parcel booked by sender.',
                'icon' => 'bi-file-earmark-check',
                'done' => true
            ];
            if($currIdx >= 1) {
                $timeline[] = ['date' => date('Y-m-d H:i:s', $baseDate + 3600), 'title' => 'Pickup / Drop-off', 'desc' => 'Parcel handed over to logistics.', 'icon' => 'bi-box-seam', 'done' => true];
            }
            if($currIdx >= 2) {
                $timeline[] = ['date' => date('Y-m-d H:i:s', $baseDate + 7200), 'title' => 'Origin Sorting Hub', 'desc' => 'Parcel arrived at origin sorting facility.', 'icon' => 'bi-geo-alt', 'done' => true];
            }
            if($currIdx >= 3) {
                $timeline[] = ['date' => date('Y-m-d H:i:s', $baseDate + 14400), 'title' => 'Main Sorting Hub', 'desc' => 'Parcel arrived at main sorting facility.', 'icon' => 'bi-geo-alt', 'done' => true];
            }
            if($currIdx >= 4) {
                $timeline[] = ['date' => date('Y-m-d H:i:s', $baseDate + 21600), 'title' => 'Regional Hub', 'desc' => 'Parcel arrived at regional distribution hub.', 'icon' => 'bi-geo-alt', 'done' => true];
            }
            if($currIdx >= 5) {
                $timeline[] = ['date' => date('Y-m-d H:i:s', $baseDate + 28800), 'title' => 'Destination Hub', 'desc' => 'Parcel arrived at final destination hub.', 'icon' => 'bi-geo-alt', 'done' => true];
            }
            if($currIdx >= 6) {
                $timeline[] = ['date' => date('Y-m-d H:i:s', $baseDate + 32400), 'title' => 'Out for Delivery', 'desc' => 'Parcel is on its way to you.', 'icon' => 'bi-truck', 'done' => true];
            }
        }

        // Delivery Attempts
        if (isset($order['delivery_attempts'])) {
            foreach ($order['delivery_attempts'] as $att) {
                $type = $att['Atmp_Type'] ?? 'Delivery';
                if($att['Atmp_Rslt'] === 'Successful' && $type !== 'Pickup') {
                    $timeline[] = [
                        'date' => $att['Atmp_Date'],
                        'title' => 'Delivered',
                        'desc' => 'Parcel delivered to ' . ($att['Atmp_Sign']??'') . ' by ' . ($att['Rdr_Name']??''),
                        'icon' => 'bi-check-circle-fill',
                        'done' => true,
                        'color' => 'var(--green)'
                    ];
                } elseif($att['Atmp_Rslt'] === 'Failed') {
                    $timeline[] = [
                        'date' => $att['Atmp_Date'],
                        'title' => 'Delivery Attempt Failed',
                        'desc' => 'Reason: ' . ($att['Atmp_FailRsn']??'') . ' (Rider: ' . ($att['Rdr_Name']??'') . ')',
                        'icon' => 'bi-x-circle-fill',
                        'done' => true,
                        'color' => 'var(--red)'
                    ];
                }
            }
        } else {
            // If Out for Delivery but no delivery attempt logged yet
            if($currStatus === 'Out for Delivery') {
                $timeline[] = [
                    'date' => date('Y-m-d H:i:s'),
                    'title' => 'Out for Delivery',
                    'desc' => 'Parcel is on its way to you.',
                    'icon' => 'bi-truck',
                    'done' => false,
                    'color' => 'var(--blue)'
                ];
            }
        }

        // Sort timeline by date
        usort($timeline, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
    }
}

include "../layout/dashboard_layout.php";
?>

<div class="page-header">
    <div>
        <h1>Track Parcel</h1>
        <p>Enter your tracking number to see delivery status</p>
    </div>
</div>

<div class="row justify-content-center mb-5">
    <div class="col-lg-8">
        <div class="nv-card p-4">
            <form method="GET" style="display:flex; gap:12px;">
                <input type="text" name="trk" class="nv-input" style="flex:1; font-family:'Sora',sans-serif; font-size:16px; font-weight:700; letter-spacing:0.05em;" placeholder="Enter Tracking Number (e.g. NVPH...)" value="<?= htmlspecialchars($trk) ?>" required>
                <button type="submit" class="btn-nv px-4"><i class="bi bi-search"></i> Track</button>
            </form>
        </div>
    </div>
</div>

<?php if($trk): ?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        
        <?php if(!$order): ?>
        <div class="nv-card p-5 text-center">
            <div class="empty-state-icon"><i class="bi bi-search"></i></div>
            <h4 style="font-family:'Sora',sans-serif; font-weight:700;">Tracking Number Not Found</h4>
            <p style="color:var(--muted); font-size:14px;">Please check the tracking number and try again.</p>
        </div>
        <?php else: 
            $s = $order['Ord_Status'];
            $map=[
                'Order Created'=>'badge-pending',
                'Pickup / Drop-off'=>'badge-confirmed',
                'Origin Sorting Hub'=>'badge-transit',
                'Main Sorting Hub'=>'badge-transit',
                'Regional Hub'=>'badge-transit',
                'Destination Hub'=>'badge-transit',
                'Out for Delivery'=>'badge-delivery',
                'Delivered'=>'badge-delivered',
                'RTS'=>'badge-failed'
            ]; 
            $cls=$map[$s]??'badge-pending';
        ?>
        
        <!-- Order Summary -->
        <div class="nv-card p-4 mb-4">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
                <div>
                    <div style="font-size:12px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Tracking Number</div>
                    <div style="font-family:'Sora',sans-serif; font-size:24px; font-weight:800; color:var(--red);"><?= htmlspecialchars($order['awb']['AWB_TrkNum'] ?? '') ?></div>
                </div>
                <span class="badge-status <?= $cls ?>" style="font-size:13px; padding:6px 12px;"><?= $s ?></span>
            </div>
            
            <div class="row g-3" style="padding-top:20px; border-top:1px solid var(--border);">
                <div class="col-sm-4">
                    <div style="font-size:11px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Recipient</div>
                    <div style="font-weight:600; color:var(--ink); font-size:14px;"><?= htmlspecialchars($order['recipient']['Rcpt_Name'] ?? '') ?></div>
                    <div style="color:var(--muted); font-size:12px;"><?= htmlspecialchars($order['recipient']['Rcpt_Area'] ?? '') ?></div>
                </div>
                <div class="col-sm-4">
                    <div style="font-size:11px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Service</div>
                    <div style="font-weight:600; color:var(--ink); font-size:14px;"><?= htmlspecialchars($order['service']['Svc_Name'] ?? '') ?></div>
                    <div style="color:var(--muted); font-size:12px;">Max <?= htmlspecialchars($order['parcel']['Pcl_Wght'] ?? '') ?> kg</div>
                </div>
                <div class="col-sm-4">
                    <div style="font-size:11px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Booked On</div>
                    <div style="font-weight:600; color:var(--ink); font-size:14px;"><?= date('M d, Y h:i A', strtotime($order['Ord_CrtdDt'] ?? 'now')) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Timeline -->
        <div class="nv-card p-4">
            <h5 style="font-size:16px; margin-bottom:24px; color:var(--ink);"><i class="bi bi-clock-history me-2"></i>Delivery Timeline</h5>
            
            <ul class="track-timeline">
                <?php foreach(array_reverse($timeline) as $i => $item): ?>
                <li class="track-item">
                    <div class="track-dot">
                        <div class="track-dot-circle <?= $i===0 && !$item['done'] ? 'active' : ($item['done'] ? 'done' : '') ?>" 
                             style="<?= isset($item['color']) && $item['done'] ? 'background:'.$item['color'].';border-color:'.$item['color'].';' : '' ?>">
                            <i class="bi <?= $item['icon'] ?>" style="<?= $i===0 && !$item['done'] ? '' : 'color:#fff;' ?>"></i>
                        </div>
                    </div>
                    <div class="track-content">
                        <div class="track-status" style="<?= isset($item['color']) && $item['done'] ? 'color:'.$item['color'].';' : '' ?>"><?= $item['title'] ?></div>
                        <div class="track-notes"><?= $item['desc'] ?></div>
                        <div class="track-time"><?= date('M d, Y - h:i A', strtotime($item['date'])) ?></div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include "../layout/dashboard_footer.php"; ?>