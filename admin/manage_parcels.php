<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Manage Parcels";
$activePage = "parcels";

// ---- UPDATE STATUS ----
if(isset($_POST['update_status'])){
    $ordId     = $conn->real_escape_string($_POST['ord_id']);
    $newStatus = $conn->real_escape_string($_POST['new_status']);
    $conn->query("UPDATE `ORDER` SET Ord_Status='$newStatus' WHERE Ord_ID='$ordId'");
    $shpm = $conn->query("SELECT Shpm_ID FROM SHIPMENT WHERE Shpm_OrdID='$ordId' LIMIT 1");
    if($shpm && $shpm->num_rows > 0){
        $sid = $shpm->fetch_assoc()['Shpm_ID'];
        $conn->query("UPDATE SHIPMENT SET Shpm_Status='$newStatus' WHERE Shpm_ID='$sid'");
        if($newStatus === 'Delivered') $conn->query("UPDATE SHIPMENT SET Shpm_DlvDt=NOW() WHERE Shpm_ID='$sid'");
    }
    $_SESSION['toast_success'] = "Status updated to $newStatus!";
    header("Location: manage_parcels.php"); exit();
}

// ---- FILTERS ----
$filterStatus = $_GET['status'] ?? 'all';
$search       = $conn->real_escape_string($_GET['q'] ?? '');
$where = "WHERE 1=1";
if($filterStatus !== 'all') $where .= " AND o.Ord_Status = '$filterStatus'";
if($search) $where .= " AND (aw.AWB_TrkNum LIKE '%$search%' OR u.Usr_Name LIKE '%$search%' OR r.Rcpt_Name LIKE '%$search%')";

$parcels = $conn->query("
    SELECT o.*, p.Pcl_Wght, p.Pcl_IsCOD, p.Pcl_CODAmt,
           u.Usr_Name as ShipperName, r.Rcpt_Name, r.Rcpt_Area,
           sv.Svc_Name, aw.AWB_TrkNum, f.Fee_Total,
           rd.Rdr_Name as RiderName
    FROM `ORDER` o
    JOIN PARCEL p ON o.Ord_PclID = p.Pcl_ID
    JOIN SHIPPER sh ON o.Ord_ShprID = sh.Shpr_ID
    JOIN USER_ACCOUNT u ON sh.Shpr_UsrID = u.Usr_ID
    JOIN RECIPIENT r ON p.Pcl_RcptID = r.Rcpt_ID
    JOIN SERVICE_TYPE sv ON o.Ord_SvcID = sv.Svc_ID
    LEFT JOIN AIRWAY_BILL aw ON aw.AWB_OrdID = o.Ord_ID
    LEFT JOIN SHIPPING_FEE f ON f.Fee_OrdID = o.Ord_ID
    LEFT JOIN SHIPMENT shpm ON shpm.Shpm_OrdID = o.Ord_ID
    LEFT JOIN DELIVERY_ATTEMPT da ON da.Atmp_ShpmID = shpm.Shpm_ID AND da.Atmp_Rslt = 'Pending'
    LEFT JOIN RIDER rd ON da.Atmp_RdrID = rd.Rdr_ID
    $where ORDER BY o.Ord_CrtdDt DESC
");

include "../layout/dashboard_layout.php";
?>

<div class="page-header">
    <div><h1>Manage Parcels</h1><p>View and manage all parcels across the system</p></div>
</div>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:8px;flex:1;min-width:220px;">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
        <div style="position:relative;flex:1;">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);"></i>
            <input type="text" name="q" class="nv-input" style="padding-left:36px;" placeholder="Search tracking, shipper, recipient..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn-nv">Search</button>
        <?php if($search): ?><a href="?status=<?= $filterStatus ?>" class="btn-nv-ghost">Clear</a><?php endif; ?>
    </form>
</div>

<div style="display:flex;gap:6px;margin-bottom:24px;flex-wrap:wrap;">
    <?php foreach(['all'=>'All','Order Created'=>'Order Created','Pickup / Drop-off'=>'Pickup / Drop-off','Origin Sorting Hub'=>'Origin Sorting Hub','Main Sorting Hub'=>'Main Sorting Hub','Regional Hub'=>'Regional Hub','Destination Hub'=>'Destination Hub','Delivered'=>'Delivered','RTS'=>'RTS'] as $k=>$v): ?>
    <a href="?status=<?= urlencode($k) ?>" style="padding:7px 16px;border-radius:50px;font-size:12px;font-weight:600;text-decoration:none;<?= $filterStatus===$k?'background:var(--ink);color:#fff;':'background:var(--surface-2);color:var(--muted);border:1.5px solid var(--border);' ?>"><?= $v ?></a>
    <?php endforeach; ?>
</div>

<div class="nv-card"><div style="overflow-x:auto;">
    <table class="nv-table">
        <thead><tr>
            <th>Tracking No.</th><th>Shipper</th><th>Recipient</th><th>Area</th>
            <th>Service</th><th>Rider</th><th>Fee</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if($parcels->num_rows === 0): ?>
            <tr><td colspan="9"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-boxes"></i></div><h4>No parcels found</h4></div></td></tr>
        <?php else: while($r = $parcels->fetch_assoc()):
            $s = $r['Ord_Status'];
            $map = ['Order Created'=>'badge-pending','Pickup / Drop-off'=>'badge-confirmed','Origin Sorting Hub'=>'badge-transit','Main Sorting Hub'=>'badge-transit','Regional Hub'=>'badge-transit','Destination Hub'=>'badge-transit','Out for Delivery'=>'badge-delivery','Delivered'=>'badge-delivered','RTS'=>'badge-failed'];
            $cls = $map[$s] ?? 'badge-pending';
        ?>
            <tr>
                <td><div style="font-family:'Sora',sans-serif;font-size:13px;font-weight:700;color:var(--red);"><?= htmlspecialchars($r['AWB_TrkNum'] ?? $r['Ord_ID']) ?></div>
                    <div style="font-size:10px;color:var(--muted);"><?= date('M j', strtotime($r['Ord_CrtdDt'])) ?></div></td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['ShipperName']) ?></td>
                <td style="font-weight:500;"><?= htmlspecialchars($r['Rcpt_Name']) ?></td>
                <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($r['Rcpt_Area'] ?? '—') ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['Svc_Name']) ?></td>
                <td style="font-size:13px;"><?= $r['RiderName'] ? htmlspecialchars($r['RiderName']) : '<span style="color:var(--amber);font-size:12px;font-weight:600;">—</span>' ?></td>
                <td style="font-family:'Sora',sans-serif;font-weight:700;">₱<?= number_format($r['Fee_Total']??0,2) ?></td>
                <td><span class="badge-status <?= $cls ?>"><?= $s ?></span></td>
                <td><button class="btn-icon" title="Update Status" onclick="openStatus('<?= $r['Ord_ID'] ?>','<?= addslashes($r['AWB_TrkNum'] ?? $r['Ord_ID']) ?>','<?= $s ?>')" data-bs-toggle="modal" data-bs-target="#statusModal"><i class="bi bi-pencil-fill"></i></button></td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div></div>

<div class="modal fade" id="statusModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Update Order Status</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST"><div class="modal-body">
        <input type="hidden" name="ord_id" id="statusOrdId">
        <div style="background:var(--surface);border-radius:8px;padding:12px 16px;margin-bottom:18px;">
            <div style="font-size:11px;color:var(--muted);">Parcel</div>
            <div id="statusTracking" style="font-family:'Sora',sans-serif;font-weight:700;color:var(--red);"></div>
        </div>
        <div class="nv-form-group"><label>New Status *</label>
            <select name="new_status" id="statusSelect" class="nv-input" required>
                <option value="Order Created">Order Created</option><option value="Pickup / Drop-off">Pickup / Drop-off</option>
                <option value="Origin Sorting Hub">Origin Sorting Hub</option><option value="Main Sorting Hub">Main Sorting Hub</option><option value="Regional Hub">Regional Hub</option><option value="Destination Hub">Destination Hub</option><option value="Delivered">Delivered</option>
                <option value="RTS">RTS</option>
            </select>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn-nv-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="update_status" class="btn-nv"><i class="bi bi-check2-circle"></i> Update</button>
    </div></form>
</div></div></div>

<script>
function openStatus(id, trk, cur){
    document.getElementById('statusOrdId').value = id;
    document.getElementById('statusTracking').textContent = trk;
    document.getElementById('statusSelect').value = cur;
}
</script>

<?php include "../layout/dashboard_footer.php"; ?>
