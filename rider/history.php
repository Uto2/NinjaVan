<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'rider'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Delivery History";
$activePage = "history";
$riderId    = $_SESSION['rider_id'];

$filter = $_GET['result'] ?? '';

$history = [];
$totalDone = 0;
$totalFail = 0;

$ordersSnap = $db->getReference('orders')->getSnapshot();
if ($ordersSnap->hasChildren()) {
    $allOrders = $ordersSnap->getValue();
    
    foreach ($allOrders as $o) {
        if (isset($o['delivery_attempts'])) {
            foreach ($o['delivery_attempts'] as $att) {
                if (($att['Atmp_RdrID'] ?? '') === $riderId) {
                    $res = $att['Atmp_Rslt'] ?? '';
                    $type = $att['Atmp_Type'] ?? 'Delivery';
                    
                    if ($type === 'Pickup') continue; // Ignore pickups in delivery history

                    if ($res === 'Successful') {
                        $totalDone++;
                    } else if ($res === 'Failed' || $res === 'Unavailable') {
                        $totalFail++;
                    }
                    
                    if (in_array($res, ['Successful', 'Failed', 'Unavailable'])) {
                        // Check filter
                        $passFilter = true;
                        if ($filter === 'success' && $res !== 'Successful') $passFilter = false;
                        if ($filter === 'failed' && !in_array($res, ['Failed', 'Unavailable'])) $passFilter = false;
                        
                        if ($passFilter) {
                            $row = $o;
                            $row['Attempt'] = $att;
                            $history[] = $row;
                        }
                    }
                }
            }
        }
    }
    
    uasort($history, function($a, $b) {
        return strtotime($b['Attempt']['Atmp_Date'] ?? 0) <=> strtotime($a['Attempt']['Atmp_Date'] ?? 0);
    });
}

$totalAll = $totalDone + $totalFail;
$rate = $totalAll > 0 ? round(($totalDone/$totalAll)*100) : 0;

include "../layout/dashboard_layout.php";
?>

<div class="page-header">
    <div><h1>Delivery History</h1><p>Your past completed and failed deliveries</p></div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card green"><div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-value"><?= $totalDone ?></div><div class="stat-label">Successful Deliveries</div></div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card red"><div class="stat-icon red"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-value"><?= $totalFail ?></div><div class="stat-label">Failed Attempts</div></div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card amber"><div class="stat-icon amber"><i class="bi bi-graph-up"></i></div>
            <div class="stat-value"><?= $rate ?>%</div><div class="stat-label">Success Rate</div></div>
    </div>
</div>

<!-- Filter -->
<div style="display:flex;gap:6px;margin-bottom:24px;">
    <?php foreach(['' => 'All', 'success' => 'Successful', 'failed' => 'Failed'] as $k=>$v): ?>
    <a href="?result=<?= $k ?>" style="padding:7px 16px;border-radius:50px;font-size:12px;font-weight:600;text-decoration:none;<?= $filter===$k?'background:var(--ink);color:#fff;':'background:var(--surface-2);color:var(--muted);border:1.5px solid var(--border);' ?>"><?= $v ?></a>
    <?php endforeach; ?>
</div>

<div class="nv-card"><div style="overflow-x:auto;">
    <table class="nv-table">
        <thead><tr><th>Tracking</th><th>Recipient</th><th>Area</th><th>Weight</th><th>COD</th><th>Result</th><th>Date</th><th>Details</th></tr></thead>
        <tbody>
        <?php if(empty($history)): ?>
            <tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-clock-history"></i></div><h4>No delivery history</h4><p>Completed deliveries will appear here</p></div></td></tr>
        <?php else: foreach($history as $r):
            $isSuccess = ($r['Attempt']['Atmp_Rslt'] ?? '') === 'Successful';
        ?>
            <tr>
                <td><div style="font-family:'Sora',sans-serif;font-size:13px;font-weight:700;color:var(--red);"><?= htmlspecialchars($r['awb']['AWB_TrkNum'] ?? $r['Ord_ID']) ?></div></td>
                <td style="font-weight:500;"><?= htmlspecialchars($r['recipient']['Rcpt_Name'] ?? '') ?></td>
                <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($r['recipient']['Rcpt_Area'] ?? '—') ?></td>
                <td style="font-size:13px;"><?= $r['parcel']['Pcl_Wght'] ?? 0 ?> kg</td>
                <td><?= ($r['parcel']['Pcl_IsCOD']??'')==='Yes' ? '<span style="color:var(--amber);font-weight:700;">₱'.number_format($r['parcel']['Pcl_CODAmt']??0,2).'</span>' : '<span style="color:var(--muted);">—</span>' ?></td>
                <td><span class="badge-status <?= $isSuccess?'badge-delivered':'badge-failed' ?>"><?= $r['Attempt']['Atmp_Rslt'] ?? '' ?></span></td>
                <td style="font-size:12px;color:var(--muted);"><?= date('M j, g:iA', strtotime($r['Attempt']['Atmp_Date'] ?? 'now')) ?></td>
                <td style="font-size:12px;color:var(--muted);"><?= $isSuccess ? 'Signed: '.htmlspecialchars($r['Attempt']['Atmp_Sign']??'—') : htmlspecialchars($r['Attempt']['Atmp_FailRsn']??'—') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div></div>

<?php include "../layout/dashboard_footer.php"; ?>
