<?php
session_start();
require_once "../config/db.php";
require_once "../config/Helper.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'staff'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Hub Inventory";
$activePage = "inventory";
$hubId      = $_SESSION['hub_id'];

$hubSnap = $db->getReference('hubs/' . $hubId)->getSnapshot();
$hub = $hubSnap->getValue() ?? ['Hub_Name' => 'Unknown Hub', 'Hub_Area' => ''];
$hubArea = $hub['Hub_Area'] ?? '';

// Handle Actions
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['ord_id'])) {
    $action = $_POST['action'];
    $ordId = trim($_POST['ord_id']);
    
    $newStatus = '';
    if($action === 'to_main') $newStatus = 'Main Sorting Hub';
    elseif($action === 'to_regional') $newStatus = 'Regional Hub';
    elseif($action === 'to_destination') $newStatus = 'Destination Hub';
    
    if($newStatus) {
        $orderSnap = $db->getReference('orders/' . $ordId)->getSnapshot();
        if ($orderSnap->exists()) {
            $orderData = $orderSnap->getValue();
            $status = $orderData['Ord_Status'] ?? '';
            $rcptArea = $orderData['recipient']['Rcpt_Area'] ?? '';
            $pickAddr = $orderData['Ord_PickAddr'] ?? '';
            
            if (Helper::isAreaMatch($hubArea, $rcptArea, $pickAddr, $status)) {
                $db->getReference('orders/' . $ordId . '/Ord_Status')->set($newStatus);
                $_SESSION['toast_success'] = "Order forwarded to " . $newStatus;
            } else {
                $_SESSION['toast_error'] = "Unauthorized: Parcel not in your jurisdiction.";
            }
        }
    }
    header("Location: hub_inventory.php"); exit();
}

// Filter
$filter = $_GET['filter'] ?? 'all';

// Get users for Shipper Names
$usersSnap = $db->getReference('users')->getSnapshot();
$usersData = $usersSnap->getValue() ?? [];

$inventory = [];
$cStaging = 0;
$cPending = 0;
$cTransit = 0;

$ordersSnap = $db->getReference('orders')->getSnapshot();
if ($ordersSnap->hasChildren()) {
    $allOrders = $ordersSnap->getValue();
    
    uasort($allOrders, function($a, $b) {
        return strtotime($b['Ord_CrtdDt'] ?? 0) <=> strtotime($a['Ord_CrtdDt'] ?? 0);
    });

    foreach ($allOrders as $o) {
        $status = $o['Ord_Status'] ?? '';
        if (in_array($status, ['Delivered', 'RTS'])) continue;
        
        $rcptArea = $o['recipient']['Rcpt_Area'] ?? '';
        $pickAddr = $o['Ord_PickAddr'] ?? '';
        
        $isMyArea = Helper::isAreaMatch($hubArea, $rcptArea, $pickAddr, $status);
        if (!$isMyArea) continue;

        // Stats
        if ($status === 'Order Created') $cStaging++;
        if ($status === 'Pickup / Drop-off') $cPending++;
        if (in_array($status, ['Origin Sorting Hub','Main Sorting Hub','Regional Hub','Destination Hub','Out for Delivery'])) $cTransit++;
        
        // Filter
        $match = true;
        if ($filter === 'staging' && $status !== 'Order Created') $match = false;
        if ($filter === 'pending' && $status !== 'Pickup / Drop-off') $match = false;
        if ($filter === 'transit' && !in_array($status, ['Origin Sorting Hub','Main Sorting Hub','Regional Hub','Destination Hub','Out for Delivery'])) $match = false;
        
        if ($match) {
            // Find ShipperName and RiderName
            $shprId = $o['Ord_ShprID'] ?? '';
            $rdrId = $o['Rider_ID'] ?? '';
            $o['ShipperName'] = $usersData[$shprId]['Usr_Name'] ?? 'Unknown Shipper';
            $o['RiderName'] = $usersData[$rdrId]['Usr_Name'] ?? '';
            
            $inventory[] = $o;
        }
    }
}

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
        <?php if(empty($inventory)): ?>
            <tr><td colspan="9"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-box-seam"></i></div><h4>No parcels in inventory</h4><p>All parcels have been dispatched or delivered</p></div></td></tr>
        <?php else: foreach($inventory as $r):
            $s=$r['Ord_Status'] ?? ''; 
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
                <td><div style="font-family:'Sora',sans-serif;font-size:13px;font-weight:700;color:var(--red);"><?= htmlspecialchars($r['awb']['AWB_TrkNum'] ?? $r['Ord_ID']) ?></div></td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['ShipperName']) ?></td>
                <td style="font-weight:500;"><?= htmlspecialchars($r['recipient']['Rcpt_Name'] ?? '') ?></td>
                <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($r['recipient']['Rcpt_Area']??'—') ?></td>
                <td style="font-size:13px;"><?= $r['parcel']['Pcl_Wght'] ?? 0 ?> kg</td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['service']['Svc_Name'] ?? '') ?></td>
                <td style="font-size:13px;"><?= !empty($r['RiderName']) ? htmlspecialchars($r['RiderName']) : '<span style="color:var(--muted);">—</span>' ?></td>
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
                    <?php elseif($s === 'Destination Hub'): ?>
                        <a href="/ninjavan/staff/dispatch.php" class="btn btn-sm" style="font-size:12px; background:var(--green); color:#fff; border:none; text-decoration:none; padding:4px 10px; border-radius:4px;">Dispatch to Rider</a>
                    <?php else: ?>
                        <span style="color:var(--muted); font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div></div>

<script type="module">
  import { initializeApp } from "https://www.gstatic.com/firebasejs/10.8.1/firebase-app.js";
  import { getDatabase, ref, onValue } from "https://www.gstatic.com/firebasejs/10.8.1/firebase-database.js";

  const app = initializeApp({
    databaseURL: "https://ninjavanph-3f985-default-rtdb.asia-southeast1.firebasedatabase.app/"
  });
  const db = getDatabase(app);
  let isFirstLoad = true;

  onValue(ref(db, 'orders'), (snapshot) => {
      if (isFirstLoad) {
          isFirstLoad = false;
          return;
      }
      // Show skeleton loaders during fetch
      const tbody = document.querySelector('.nv-table tbody');
      if (tbody) {
          tbody.innerHTML = `
              <tr>
                  <td><span class="skeleton-box" style="width: 80px;"></span></td>
                  <td><span class="skeleton-box" style="width: 120px;"></span></td>
                  <td><span class="skeleton-box" style="width: 150px;"></span></td>
                  <td><span class="skeleton-box" style="width: 90px;"></span></td>
                  <td><span class="skeleton-box" style="width: 50px;"></span></td>
                  <td><span class="skeleton-box" style="width: 100px;"></span></td>
                  <td><span class="skeleton-box" style="width: 130px;"></span></td>
                  <td><span class="skeleton-box" style="width: 80px;"></span></td>
                  <td><span class="skeleton-box" style="width: 80px;"></span></td>
              </tr>
              <tr>
                  <td><span class="skeleton-box" style="width: 80px;"></span></td>
                  <td><span class="skeleton-box" style="width: 120px;"></span></td>
                  <td><span class="skeleton-box" style="width: 150px;"></span></td>
                  <td><span class="skeleton-box" style="width: 90px;"></span></td>
                  <td><span class="skeleton-box" style="width: 50px;"></span></td>
                  <td><span class="skeleton-box" style="width: 100px;"></span></td>
                  <td><span class="skeleton-box" style="width: 130px;"></span></td>
                  <td><span class="skeleton-box" style="width: 80px;"></span></td>
                  <td><span class="skeleton-box" style="width: 80px;"></span></td>
              </tr>
          `;
      }
      
      // Silently fetch the updated page and replace the DOM elements
      fetch(window.location.href)
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Replace Stats
            const newStats = doc.querySelector('.row.g-3.mb-4');
            if (newStats) document.querySelector('.row.g-3.mb-4').innerHTML = newStats.innerHTML;
            
            // Replace Table
            const newTable = doc.querySelector('.nv-table');
            if (newTable) document.querySelector('.nv-table').innerHTML = newTable.innerHTML;
        });
  });
</script>

<?php include "../layout/dashboard_footer.php"; ?>