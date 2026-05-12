<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'shipper'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Track Parcel";
$activePage = "track";
$shipperId  = $_SESSION['shipper_id'];

$trk = isset($_GET['trk']) ? $conn->real_escape_string(trim($_GET['trk'])) : '';
$order = null;
$timeline = [];

if($trk) {
    // Look up the order using the tracking number AND ensuring it belongs to this shipper
    // (Or allow tracking any parcel if they have the number? Standard is usually public tracking, but here we restrict to their own for privacy, or allow all since they have the AWB)
    // Let's allow tracking if they have the exact number, but flag if it's theirs.
    $q = $conn->query("
        SELECT o.*, p.Pcl_Wght, aw.AWB_TrkNum, 
               r.Rcpt_Name, r.Rcpt_Area, r.Rcpt_Addr,
               s.Svc_Name,
               sh.Shpm_ID, sh.Shpm_Status, sh.Shpm_PickDt, sh.Shpm_DlvDt
        FROM AIRWAY_BILL aw
        JOIN `ORDER` o ON aw.AWB_OrdID = o.Ord_ID
        JOIN PARCEL p ON o.Ord_PclID = p.Pcl_ID
        JOIN RECIPIENT r ON p.Pcl_RcptID = r.Rcpt_ID
        JOIN SERVICE_TYPE s ON o.Ord_SvcID = s.Svc_ID
        LEFT JOIN SHIPMENT sh ON sh.Shpm_OrdID = o.Ord_ID
        WHERE aw.AWB_TrkNum = '$trk'
    ");
    
    if($q && $q->num_rows > 0) {
        $order = $q->fetch_assoc();
        
        // Build Timeline Array
        // 1. Order Created
        $timeline[] = [
            'date' => $order['Ord_CrtdDt'],
            'title' => 'Order Created',
            'desc' => 'Parcel booked and data received.',
            'icon' => 'bi-file-earmark-check',
            'done' => true
        ];
        
        // 2. Pickup / Dropoff
        if($order['Ord_Status'] !== 'Staging') {
             $isDone = !empty($order['Shpm_PickDt']);
             $timeline[] = [
                'date' => $order['Shpm_PickDt'] ?? $order['Ord_CrtdDt'], // Approximate if not picked up yet
                'title' => 'Parcel Picked Up',
                'desc' => 'Parcel has been handed over to NinjaVan.',
                'icon' => 'bi-box-seam',
                'done' => $isDone
            ];
        }

        // 3. Transit & Attempts
        if($order['Shpm_ID']) {
            $attQ = $conn->query("
                SELECT da.*, rd.Rdr_Name 
                FROM DELIVERY_ATTEMPT da
                JOIN RIDER rd ON da.Atmp_RdrID = rd.Rdr_ID
                WHERE da.Atmp_ShpmID = '{$order['Shpm_ID']}'
                ORDER BY da.Atmp_Date ASC
            ");
            while($att = $attQ->fetch_assoc()) {
                if($att['Atmp_Rslt'] === 'Successful') {
                    $timeline[] = [
                        'date' => $att['Atmp_Date'],
                        'title' => 'Delivered',
                        'desc' => 'Parcel delivered to ' . $att['Atmp_Sign'] . ' by ' . $att['Rdr_Name'],
                        'icon' => 'bi-check-circle-fill',
                        'done' => true,
                        'color' => 'var(--green)'
                    ];
                } elseif($att['Atmp_Rslt'] === 'Failed') {
                    $timeline[] = [
                        'date' => $att['Atmp_Date'],
                        'title' => 'Delivery Attempt Failed',
                        'desc' => 'Reason: ' . $att['Atmp_FailRsn'] . ' (Rider: ' . $att['Rdr_Name'] . ')',
                        'icon' => 'bi-x-circle-fill',
                        'done' => true,
                        'color' => 'var(--red)'
                    ];
                }
                // Ignore 'Pending' attempts since they represent active Out for Delivery status
            }
            
            // If currently In Transit or Out for Delivery
            if(in_array($order['Shpm_Status'], ['In Transit', 'Out for Delivery'])) {
                $timeline[] = [
                    'date' => date('Y-m-d H:i:s'),
                    'title' => $order['Shpm_Status'],
                    'desc' => 'Parcel is currently ' . strtolower($order['Shpm_Status']) . '.',
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
            $map = [
                'Staging'=>'badge-pending','Pending Pickup'=>'badge-confirmed',
                'In Transit'=>'badge-transit','Out for Delivery'=>'badge-delivery',
                'Delivered'=>'badge-delivered','RTS'=>'badge-failed'
            ];
            $cls = $map[$s] ?? 'badge-pending';
        ?>
        
        <!-- Order Summary -->
        <div class="nv-card p-4 mb-4">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
                <div>
                    <div style="font-size:12px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Tracking Number</div>
                    <div style="font-family:'Sora',sans-serif; font-size:24px; font-weight:800; color:var(--red);"><?= htmlspecialchars($order['AWB_TrkNum']) ?></div>
                </div>
                <span class="badge-status <?= $cls ?>" style="font-size:13px; padding:6px 12px;"><?= $s ?></span>
            </div>
            
            <div class="row g-3" style="padding-top:20px; border-top:1px solid var(--border);">
                <div class="col-sm-4">
                    <div style="font-size:11px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Recipient</div>
                    <div style="font-weight:600; color:var(--ink); font-size:14px;"><?= htmlspecialchars($order['Rcpt_Name']) ?></div>
                    <div style="color:var(--muted); font-size:12px;"><?= htmlspecialchars($order['Rcpt_Area']) ?></div>
                </div>
                <div class="col-sm-4">
                    <div style="font-size:11px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Service</div>
                    <div style="font-weight:600; color:var(--ink); font-size:14px;"><?= htmlspecialchars($order['Svc_Name']) ?></div>
                    <div style="color:var(--muted); font-size:12px;">Max <?= htmlspecialchars($order['Pcl_Wght']) ?> kg</div>
                </div>
                <div class="col-sm-4">
                    <div style="font-size:11px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Booked On</div>
                    <div style="font-weight:600; color:var(--ink); font-size:14px;"><?= date('M d, Y h:i A', strtotime($order['Ord_CrtdDt'])) ?></div>
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
