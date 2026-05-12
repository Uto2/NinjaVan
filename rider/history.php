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
$where = "WHERE da.Atmp_RdrID = '$riderId' AND da.Atmp_Rslt IN ('Successful','Failed','Unavailable')";
if($filter === 'success') $where .= " AND da.Atmp_Rslt = 'Successful'";
elseif($filter === 'failed') $where .= " AND da.Atmp_Rslt IN ('Failed','Unavailable')";

$history = $conn->query("
    SELECT da.*, s.Shpm_Status, o.Ord_ID,
           p.Pcl_Wght, p.Pcl_IsCOD, p.Pcl_CODAmt,
           r.Rcpt_Name, r.Rcpt_Area,
           aw.AWB_TrkNum
    FROM DELIVERY_ATTEMPT da
    JOIN SHIPMENT s ON da.Atmp_ShpmID = s.Shpm_ID
    JOIN `ORDER` o ON s.Shpm_OrdID = o.Ord_ID
    JOIN PARCEL p ON o.Ord_PclID = p.Pcl_ID
    JOIN RECIPIENT r ON p.Pcl_RcptID = r.Rcpt_ID
    LEFT JOIN AIRWAY_BILL aw ON aw.AWB_OrdID = o.Ord_ID
    $where
    ORDER BY da.Atmp_Date DESC
");

$totalDone = $conn->query("SELECT COUNT(*) c FROM DELIVERY_ATTEMPT WHERE Atmp_RdrID='$riderId' AND Atmp_Rslt='Successful'")->fetch_assoc()['c'];
$totalFail = $conn->query("SELECT COUNT(*) c FROM DELIVERY_ATTEMPT WHERE Atmp_RdrID='$riderId' AND Atmp_Rslt IN('Failed','Unavailable')")->fetch_assoc()['c'];
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
        <?php if($history->num_rows === 0): ?>
            <tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-clock-history"></i></div><h4>No delivery history</h4><p>Completed deliveries will appear here</p></div></td></tr>
        <?php else: while($r = $history->fetch_assoc()):
            $isSuccess = $r['Atmp_Rslt'] === 'Successful';
        ?>
            <tr>
                <td><div style="font-family:'Sora',sans-serif;font-size:13px;font-weight:700;color:var(--red);"><?= htmlspecialchars($r['AWB_TrkNum'] ?? $r['Ord_ID']) ?></div></td>
                <td style="font-weight:500;"><?= htmlspecialchars($r['Rcpt_Name']) ?></td>
                <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($r['Rcpt_Area']??'—') ?></td>
                <td style="font-size:13px;"><?= $r['Pcl_Wght'] ?> kg</td>
                <td><?= $r['Pcl_IsCOD']==='Yes' ? '<span style="color:var(--amber);font-weight:700;">₱'.number_format($r['Pcl_CODAmt'],2).'</span>' : '<span style="color:var(--muted);">—</span>' ?></td>
                <td><span class="badge-status <?= $isSuccess?'badge-delivered':'badge-failed' ?>"><?= $r['Atmp_Rslt'] ?></span></td>
                <td style="font-size:12px;color:var(--muted);"><?= date('M j, g:iA', strtotime($r['Atmp_Date'])) ?></td>
                <td style="font-size:12px;color:var(--muted);"><?= $isSuccess ? 'Signed: '.htmlspecialchars($r['Atmp_Sign']??'—') : htmlspecialchars($r['Atmp_FailRsn']??'—') ?></td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div></div>

<?php include "../layout/dashboard_footer.php"; ?>
