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
    $ordId = $_POST['ord_id'];
    $newSt = $_POST['new_status'];
    try {
        $update = ['Ord_Status' => $newSt];
        if ($newSt === 'Delivered') {
            $update['delivered_at'] = date('Y-m-d H:i:s');
        }
        $db->getReference('orders/' . $ordId)->update($update);
        
        $newTrackId = 'TRK-' . strtoupper(substr(uniqid(), -6));
        $db->getReference('orders/' . $ordId . '/tracking/' . $newTrackId)->set([
            'Trk_ID' => $newTrackId,
            'Trk_Status' => $newSt,
            'Trk_Date' => date('Y-m-d H:i:s')
        ]);
        
        $_SESSION['toast_success'] = "Status updated to $newSt!";
    } catch (Exception $e) {
        $_SESSION['toast_error'] = "Failed to update order.";
    }
    header("Location: manage_parcels.php"); exit();
}

// ---- FILTERS ----
$filterStatus = trim($_GET['status'] ?? 'all');
$search = trim(strtolower($_GET['q'] ?? ''));

$usersMap = [];
$usersSnap = $db->getReference('users')->getSnapshot();
if ($usersSnap->hasChildren()) {
    foreach ($usersSnap->getValue() as $u) {
        $uid = $u['Usr_ID'] ?? '';
        if ($uid) $usersMap[$uid] = $u;
    }
}

$parcelsList = [];

$ordersSnap = $db->getReference('orders')->getSnapshot();
if ($ordersSnap->hasChildren()) {
    foreach ($ordersSnap->getValue() as $k => $o) {
        $st = $o['Ord_Status'] ?? '';

        if ($filterStatus !== 'all' && $st !== $filterStatus) continue;

        $trk = $o['awb']['AWB_TrkNum'] ?? $k;
        $shprId = $o['Ord_ShprID'] ?? '';
        $shprName = $usersMap[$shprId]['Usr_Name'] ?? 'Unknown Shipper';
        $rcptName = $o['recipient']['Rcpt_Name'] ?? '';
        $rcptArea = $o['recipient']['Rcpt_Area'] ?? '';
        
        if ($search) {
            $match = str_contains(strtolower($trk), $search) ||
                     str_contains(strtolower($shprName), $search) ||
                     str_contains(strtolower($rcptName), $search);
            if (!$match) continue;
        }

        $rdrName = '';
        if (isset($o['delivery_attempts']) && is_array($o['delivery_attempts'])) {
            $attempts = array_values($o['delivery_attempts']);
            usort($attempts, function($a, $b) {
                return strtotime($b['Atmp_Date'] ?? 0) <=> strtotime($a['Atmp_Date'] ?? 0);
            });
            $latestAtt = $attempts[0];
            if (($latestAtt['Atmp_Rslt'] ?? '') === 'Pending') {
                $rid = $latestAtt['Atmp_RdrID'] ?? '';
                $rdrName = $usersMap[$rid]['Usr_Name'] ?? '';
            }
        }
        
        $o['Ord_ID'] = $k;
        $o['AWB_TrkNum'] = $trk;
        $o['ShipperName'] = $shprName;
        $o['Rcpt_Name'] = $rcptName;
        $o['Rcpt_Area'] = $rcptArea;
        $o['Svc_Name'] = $o['service']['Svc_Name'] ?? 'Standard';
        $o['RiderName'] = $rdrName;
        $o['Fee_Total'] = $o['fee']['Fee_Total'] ?? 0;
        
        $parcelsList[] = $o;
    }
    
    usort($parcelsList, function($a, $b) {
        return strtotime($b['Ord_CrtdDt'] ?? 0) <=> strtotime($a['Ord_CrtdDt'] ?? 0);
    });
}

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
        <?php if(empty($parcelsList)): ?>
            <tr><td colspan="9"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-boxes"></i></div><h4>No parcels found</h4></div></td></tr>
        <?php else: foreach($parcelsList as $r):
            $s = $r['Ord_Status'];
            $map = ['Order Created'=>'badge-pending','Pickup / Drop-off'=>'badge-confirmed','Origin Sorting Hub'=>'badge-transit','Main Sorting Hub'=>'badge-transit','Regional Hub'=>'badge-transit','Destination Hub'=>'badge-transit','Out for Delivery'=>'badge-delivery','Delivered'=>'badge-delivered','RTS'=>'badge-failed'];
            $cls = $map[$s] ?? 'badge-pending';
        ?>
            <tr>
                <td><div style="font-family:'Sora',sans-serif;font-size:13px;font-weight:700;color:var(--red);"><?= htmlspecialchars($r['AWB_TrkNum'] ?? $r['Ord_ID']) ?></div>
                    <div style="font-size:10px;color:var(--muted);"><?= date('M j', strtotime($r['Ord_CrtdDt']??'now')) ?></div></td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['ShipperName']) ?></td>
                <td style="font-weight:500;"><?= htmlspecialchars($r['Rcpt_Name']) ?></td>
                <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($r['Rcpt_Area'] ?? '—') ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['Svc_Name']??'') ?></td>
                <td style="font-size:13px;"><?= $r['RiderName'] ? htmlspecialchars($r['RiderName']) : '<span style="color:var(--amber);font-size:12px;font-weight:600;">—</span>' ?></td>
                <td style="font-family:'Sora',sans-serif;font-weight:700;">₱<?= number_format($r['Fee_Total']??0,2) ?></td>
                <td><span class="badge-status <?= $cls ?>"><?= $s ?></span></td>
                <td><button class="btn-icon" title="Update Status" onclick="openStatus('<?= $r['Ord_ID'] ?>','<?= addslashes($r['AWB_TrkNum'] ?? $r['Ord_ID']) ?>','<?= $s ?>')" data-bs-toggle="modal" data-bs-target="#statusModal"><i class="bi bi-pencil-fill"></i></button></td>
            </tr>
        <?php endforeach; endif; ?>
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
