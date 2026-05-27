<?php
session_start();
require_once "config/db.php";

$title = "Track Parcel - Ninja Van";
include "layout/layout.php";

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
        $order = reset($results);
        $currStatus = $order['Ord_Status'] ?? 'Order Created';
        $baseDate = strtotime($order['Ord_CrtdDt']);
        
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
            $statusOrder = [
                'Order Created' => 0, 'Pickup / Drop-off' => 1, 'Origin Sorting Hub' => 2,
                'Main Sorting Hub' => 3, 'Regional Hub' => 4, 'Destination Hub' => 5,
                'Out for Delivery' => 6, 'Delivered' => 7, 'RTS' => 8
            ];
            $currIdx = $statusOrder[$currStatus] ?? 0;
            
            $timeline[] = ['date' => date('Y-m-d H:i:s', $baseDate), 'title' => 'Order Created', 'desc' => 'Parcel booked by sender.', 'icon' => 'bi-file-earmark-check', 'done' => true];
            if($currIdx >= 1) $timeline[] = ['date' => date('Y-m-d H:i:s', $baseDate + 3600), 'title' => 'Pickup / Drop-off', 'desc' => 'Parcel handed over to logistics.', 'icon' => 'bi-box-seam', 'done' => true];
            if($currIdx >= 2) $timeline[] = ['date' => date('Y-m-d H:i:s', $baseDate + 7200), 'title' => 'Origin Sorting Hub', 'desc' => 'Parcel arrived at origin sorting facility.', 'icon' => 'bi-geo-alt', 'done' => true];
            if($currIdx >= 3) $timeline[] = ['date' => date('Y-m-d H:i:s', $baseDate + 14400), 'title' => 'Main Sorting Hub', 'desc' => 'Parcel arrived at main sorting facility.', 'icon' => 'bi-geo-alt', 'done' => true];
            if($currIdx >= 4) $timeline[] = ['date' => date('Y-m-d H:i:s', $baseDate + 21600), 'title' => 'Regional Hub', 'desc' => 'Parcel arrived at regional distribution hub.', 'icon' => 'bi-geo-alt', 'done' => true];
            if($currIdx >= 5) $timeline[] = ['date' => date('Y-m-d H:i:s', $baseDate + 28800), 'title' => 'Destination Hub', 'desc' => 'Parcel arrived at final destination hub.', 'icon' => 'bi-geo-alt', 'done' => true];
            if($currIdx >= 6) $timeline[] = ['date' => date('Y-m-d H:i:s', $baseDate + 32400), 'title' => 'Out for Delivery', 'desc' => 'Parcel is on its way to you.', 'icon' => 'bi-truck', 'done' => true];
        }

        if (isset($order['delivery_attempts'])) {
            foreach ($order['delivery_attempts'] as $att) {
                $type = $att['Atmp_Type'] ?? 'Delivery';
                if($att['Atmp_Rslt'] === 'Successful' && $type !== 'Pickup') {
                    $timeline[] = ['date' => $att['Atmp_Date'], 'title' => 'Delivered', 'desc' => 'Parcel delivered to ' . ($att['Atmp_Sign']??'') . ' by ' . ($att['Rdr_Name']??''), 'icon' => 'bi-check-circle-fill', 'done' => true, 'color' => 'var(--nv-success, #28a745)'];
                } elseif($att['Atmp_Rslt'] === 'Failed') {
                    $timeline[] = ['date' => $att['Atmp_Date'], 'title' => 'Delivery Attempt Failed', 'desc' => 'Reason: ' . ($att['Atmp_FailRsn']??'') . ' (Rider: ' . ($att['Rdr_Name']??'') . ')', 'icon' => 'bi-x-circle-fill', 'done' => true, 'color' => 'var(--nv-red, #E5202B)'];
                }
            }
        } elseif($currStatus === 'Out for Delivery') {
            $timeline[] = ['date' => date('Y-m-d H:i:s'), 'title' => 'Out for Delivery', 'desc' => 'Parcel is on its way to you.', 'icon' => 'bi-truck', 'done' => false, 'color' => '#007bff'];
        }

        usort($timeline, function($a, $b) { return strtotime($a['date']) - strtotime($b['date']); });
    }
}
?>

<style>
body { background: #F9F9FB; }
.track-page-header { background: var(--nv-ink); color: #fff; padding: 60px 0 40px; text-align: center; }
.track-page-header h1 { font-weight: 800; margin-bottom: 15px; font-size: 32px; }
.track-page-header p { color: rgba(255,255,255,0.7); max-width: 500px; margin: 0 auto; }

.track-container { margin-top: -30px; margin-bottom: 80px; }
.track-card { background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); padding: 30px; border: 1px solid rgba(0,0,0,0.05); }

/* Timeline CSS */
.track-timeline { list-style: none; padding: 0; margin: 0; position: relative; }
.track-timeline::before { content: ''; position: absolute; left: 19px; top: 20px; bottom: 20px; width: 2px; background: #e9ecef; }
.track-item { display: flex; margin-bottom: 30px; position: relative; z-index: 1; }
.track-item:last-child { margin-bottom: 0; }
.track-dot { width: 40px; flex-shrink: 0; display: flex; justify-content: center; }
.track-dot-circle { width: 40px; height: 40px; background: #fff; border: 2px solid #e9ecef; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #adb5bd; font-size: 18px; transition: 0.3s; }
.track-dot-circle.done { border-color: var(--nv-red, #E5202B); background: var(--nv-red, #E5202B); color: #fff; }
.track-dot-circle.active { border-color: var(--nv-red, #E5202B); color: var(--nv-red, #E5202B); box-shadow: 0 0 0 4px rgba(232,0,45,0.1); }
.track-content { padding-left: 20px; flex: 1; padding-top: 8px; }
.track-status { font-weight: 700; font-size: 16px; color: #343a40; margin-bottom: 4px; }
.track-notes { color: #6c757d; font-size: 14px; margin-bottom: 6px; }
.track-time { font-size: 12px; color: #adb5bd; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }

.badge-status { padding: 6px 14px; border-radius: 50px; font-size: 12px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; }
.badge-pending { background: #fff3cd; color: #856404; }
.badge-transit { background: #cce5ff; color: #004085; }
.badge-delivery { background: #d4edda; color: #155724; }
.badge-delivered { background: #28a745; color: #fff; }
.badge-failed { background: #f8d7da; color: #721c24; }
</style>

<div class="track-page-header">
    <div class="container">
        <h1>Track Your Parcel</h1>
        <p>Stay updated on your delivery's journey in real-time.</p>
    </div>
</div>

<div class="container track-container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="track-card mb-4">
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="trk" class="form-control" style="padding: 12px 20px; font-weight: 600; border-radius: 8px;" placeholder="Enter Airway Bill (AWB) number..." value="<?= htmlspecialchars($trk) ?>" required>
                    <button type="submit" class="btn-nv" style="padding: 12px 30px; border-radius: 8px;">Track</button>
                </form>
            </div>
            
            <?php if($trk): ?>
                <?php if(!$order): ?>
                <div class="track-card text-center py-5">
                    <i class="bi bi-search" style="font-size: 48px; color: #dee2e6; margin-bottom: 20px; display: block;"></i>
                    <h4 style="font-weight: 700; color: #343a40;">Tracking Number Not Found</h4>
                    <p style="color: #6c757d; margin: 0;">We couldn't find a parcel with that AWB number. Please double check and try again.</p>
                </div>
                <?php else: 
                    $s = $order['Ord_Status'];
                    $map=['Order Created'=>'badge-pending','Pickup / Drop-off'=>'badge-pending','Origin Sorting Hub'=>'badge-transit','Main Sorting Hub'=>'badge-transit','Regional Hub'=>'badge-transit','Destination Hub'=>'badge-transit','Out for Delivery'=>'badge-delivery','Delivered'=>'badge-delivered','RTS'=>'badge-failed']; 
                    $cls=$map[$s]??'badge-pending';
                ?>
                
                <div class="track-card mb-4">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <div style="font-size: 11px; color: #6c757d; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 4px;">Tracking Number</div>
                            <div style="font-size: 24px; font-weight: 800; color: var(--nv-red, #E5202B);"><?= htmlspecialchars($order['awb']['AWB_TrkNum'] ?? '') ?></div>
                        </div>
                        <span class="badge-status <?= $cls ?>"><?= $s ?></span>
                    </div>
                    
                    <div class="row g-3 pt-3" style="border-top: 1px solid #e9ecef;">
                        <div class="col-sm-4">
                            <div style="font-size: 11px; color: #6c757d; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 4px;">Recipient</div>
                            <div style="font-weight: 600; color: #343a40; font-size: 14px;"><?= htmlspecialchars($order['recipient']['Rcpt_Name'] ?? '') ?></div>
                        </div>
                        <div class="col-sm-4">
                            <div style="font-size: 11px; color: #6c757d; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 4px;">Service</div>
                            <div style="font-weight: 600; color: #343a40; font-size: 14px;"><?= htmlspecialchars($order['service']['Svc_Name'] ?? '') ?></div>
                        </div>
                        <div class="col-sm-4">
                            <div style="font-size: 11px; color: #6c757d; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 4px;">Booked On</div>
                            <div style="font-weight: 600; color: #343a40; font-size: 14px;"><?= date('M d, Y', strtotime($order['Ord_CrtdDt'] ?? 'now')) ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="track-card">
                    <h5 style="font-size: 16px; font-weight: 700; margin-bottom: 30px;"><i class="bi bi-clock-history me-2 text-muted"></i> Delivery Timeline</h5>
                    <ul class="track-timeline">
                        <?php foreach(array_reverse($timeline) as $i => $item): ?>
                        <li class="track-item">
                            <div class="track-dot">
                                <div class="track-dot-circle <?= $i===0 && !$item['done'] ? 'active' : ($item['done'] ? 'done' : '') ?>" 
                                     style="<?= isset($item['color']) && $item['done'] ? 'background:'.$item['color'].';border-color:'.$item['color'].';' : '' ?>">
                                    <i class="bi <?= $item['icon'] ?>"></i>
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
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include "layout/footer.php"; ?>
