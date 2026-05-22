<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'staff'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Hub Inventory";
$activePage = "inventory";
$hubId      = $_SESSION['hub_id'];

$hub = $conn->query("SELECT * FROM HUB WHERE Hub_ID='$hubId'")->fetch_assoc();
$hubArea = $conn->real_escape_string($hub['Hub_Area'] ?? '');

// Handle Actions
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['ord_id'])) {
    $action = $_POST['action'];
    $ordId = $conn->real_escape_string($_POST['ord_id']);
    
    $newStatus = '';
    if($action === 'to_main') $newStatus = 'Main Sorting Hub';
    elseif($action === 'to_regional') $newStatus = 'Regional Hub';
    elseif($action === 'to_destination') $newStatus = 'Destination Hub';
    
    if($newStatus) {
        $conn->query("UPDATE `ORDER` SET Ord_Status='$newStatus' WHERE Ord_ID='$ordId'");
        $conn->query("UPDATE SHIPMENT SET Shpm_Status='$newStatus' WHERE Shpm_OrdID='$ordId'");
    }
    header("Location: hub_inventory.php"); exit();
}

// Filter
$filter = $_GET['filter'] ?? 'all';

// Get parcels at this hub - shipments currently at hub that aren't delivered/RTS
$where = "WHERE 1=1";
if($filter === 'staging') $where .= " AND o.Ord_Status = 'Order Created'";
elseif($filter === 'pending') $where .= " AND o.Ord_Status = 'Pickup / Drop-off'";
elseif($filter === 'transit') $where .= " AND o.Ord_Status IN ('Origin Sorting Hub','Main Sorting Hub','Regional Hub','Destination Hub','Out for Delivery')";

// Hub inventory includes orders in the hub area
$inventory = $conn->query("
    SELECT o.*, p.Pcl_Wght, p.Pcl_IsCOD, p.Pcl_CODAmt,
           r.Rcpt_Name, r.Rcpt_Area,
           sv.Svc_Name, aw.AWB_TrkNum,
           u.Usr_Name as ShipperName,
           shpm.Shpm_ID, shpm.Shpm_Status,
           rd.Rdr_Name as RiderName
    FROM `ORDER` o
    JOIN PARCEL p ON o.Ord_PclID = p.Pcl_ID
    JOIN SHIPPER sh ON o.Ord_ShprID = sh.Shpr_ID
    JOIN USER_ACCOUNT u ON sh.Shpr_UsrID = u.Usr_ID
    JOIN RECIPIENT r ON p.Pcl_RcptID = r.Rcpt_ID
    JOIN SERVICE_TYPE sv ON o.Ord_SvcID = sv.Svc_ID
    LEFT JOIN AIRWAY_BILL aw ON aw.AWB_OrdID = o.Ord_ID
    LEFT JOIN SHIPMENT shpm ON shpm.Shpm_OrdID = o.Ord_ID AND shpm.Shpm_HubID = '$hubId'
    LEFT JOIN DELIVERY_ATTEMPT da ON da.Atmp_ShpmID = shpm.Shpm_ID AND da.Atmp_Rslt = 'Pending'
    LEFT JOIN RIDER rd ON da.Atmp_RdrID = rd.Rdr_ID
    $where
    AND o.Ord_Status NOT IN ('Delivered','RTS')
    ORDER BY o.Ord_CrtdDt DESC
");

// Stats
$cStaging = $conn->query("SELECT COUNT(*) c FROM `ORDER` o WHERE o.Ord_Status='Order Created'")->fetch_assoc()['c'];
$cPending = $conn->query("SELECT COUNT(*) c FROM `ORDER` o WHERE o.Ord_Status='Pickup / Drop-off'")->fetch_assoc()['c'];
$cTransit = $conn->query("SELECT COUNT(*) c FROM SHIPMENT WHERE Shpm_HubID='$hubId' AND Shpm_Status IN ('Origin Sorting Hub','Main Sorting Hub','Regional Hub','Destination Hub','Out for Delivery')")->fetch_assoc()['c'];

include "../layout/dashboard_layout.php";
?>

<div class="page-header">
    <div><h1>Hub Inventory</h1><p><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($hub['Hub_Name']) ?> — Parcels in branch</p></div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card amber"><div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-value"><?= $cStaging ?></div><div class="stat-label">Order Created (Awaiting Dispatch)</div></div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card blue"><div class="stat-icon blue"><i class="bi bi-box-seam-fill"></i></div>
            <div class="stat-value"><?= $cPending ?></div><div class="stat-label">Pickup / Drop-off</div></div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card green"><div class="stat-icon green"><i class="bi bi-truck"></i></div>
            <div class="stat-value"><?= $cTransit ?></div><div class="stat-label">In Transit (Hubs) / Out for Delivery</div></div>
    </div>
</div>

<!-- Filter Tabs -->
<div style="display:flex;gap:6px;margin-bottom:24px;">
    <?php foreach(['all'=>'All Parcels','staging'=>'Order Created','pending'=>'Pickup / Drop-off','transit'=>'In Transit'] as $k=>$v): ?>
    <a href="?filter=<?= $k ?>" style="padding:7px 16px;border-radius:50px;font-size:12px;font-weight:600;text-decoration:none;<?= $filter===$k?'background:var(--ink);color:#fff;':'background:var(--surface-2);color:var(--muted);border:1.5px solid var(--border);' ?>"><?= $v ?></a>
    <?php endforeach; ?>
</div>

<div class="nv-card"><div style="overflow-x:auto;">
    <table class="nv-table">
        <thead><tr><th>Tracking</th><th>Shipper</th><th>Recipient</th><th>Area</th><th>Weight</th><th>Service</th><th>Rider</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php if($inventory->num_rows === 0): ?>
            <tr><td colspan="9"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-box-seam"></i></div><h4>No parcels in inventory</h4><p>All parcels have been dispatched or delivered</p></div></td></tr>
        <?php else: while($r = $inventory->fetch_assoc()):
            $s=$r['Ord_Status']; 
            $map=[
                'Order Created'=>'badge-pending',
                'Pickup / Drop-off'=>'badge-confirmed',
                'Origin Sorting Hub'=>'badge-transit',
                'Main Sorting Hub'=>'badge-transit',
                'Regional Hub'=>'badge-transit',
                'Destination Hub'=>'badge-transit',
                'Out for Delivery'=>'badge-delivery'
            ]; 
            $cls=$map[$s]??'badge-pending';
        ?>
            <tr>
                <td><div style="font-family:'Sora',sans-serif;font-size:13px;font-weight:700;color:var(--red);"><?= htmlspecialchars($r['AWB_TrkNum'] ?? $r['Ord_ID']) ?></div></td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['ShipperName']) ?></td>
                <td style="font-weight:500;"><?= htmlspecialchars($r['Rcpt_Name']) ?></td>
                <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($r['Rcpt_Area']??'—') ?></td>
                <td style="font-size:13px;"><?= $r['Pcl_Wght'] ?> kg</td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['Svc_Name']) ?></td>
                <td style="font-size:13px;"><?= $r['RiderName'] ? htmlspecialchars($r['RiderName']) : '<span style="color:var(--muted);">—</span>' ?></td>
                <td><span class="badge-status <?= $cls ?>"><?= $s ?></span></td>
                <td>
                    <?php if($s === 'Origin Sorting Hub'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="to_main">
                            <input type="hidden" name="ord_id" value="<?= $r['Ord_ID'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary" style="font-size:12px;">To Main Hub</button>
                        </form>
                    <?php elseif($s === 'Main Sorting Hub'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="to_regional">
                            <input type="hidden" name="ord_id" value="<?= $r['Ord_ID'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary" style="font-size:12px;">To Regional Hub</button>
                        </form>
                    <?php elseif($s === 'Regional Hub'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="to_destination">
                            <input type="hidden" name="ord_id" value="<?= $r['Ord_ID'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary" style="font-size:12px;">To Dest Hub</button>
                        </form>
                    <?php else: ?>
                        <span style="color:var(--muted); font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div></div>

<?php include "../layout/dashboard_footer.php"; ?>