<?php
session_start();
require_once "../config/db.php";
require_once "../config/Helper.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'staff'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Dispatch Parcels";
$activePage = "dispatch";
$hubId      = $_SESSION['hub_id'];
$success    = "";
$error      = "";

// Handle Dispatch
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispatch'])) {
    csrf_verify();
    $ordId = $_POST['order_id'];
    $rdrId = $_POST['rider_id'];
    $mode  = $_POST['dispatch_mode'] ?? 'pickup';
    // Whitelist dispatch mode
    if(!in_array($mode, ['pickup','delivery'])) { $error = "Invalid dispatch mode."; goto endPost; }

    if(!$ordId || !$rdrId) {
        $error = "Please select an order and a rider.";
    } else {
        try {
            $dateNow = date('c');
            $orderRef = $db->getReference('orders/' . $ordId);
            $orderSnap = $orderRef->getSnapshot();
            
            if ($orderSnap->exists()) {
                $orderData = $orderSnap->getValue();
                
                // Get Hub Area for validation
                $hubSnapshot = $db->getReference('hubs/' . $hubId)->getSnapshot();
                $hubArea = $hubSnapshot->exists() ? ($hubSnapshot->getValue()['Hub_Area'] ?? '') : '';
                
                // Security Validation: Verify order is in this hub's jurisdiction
                $status = $orderData['Ord_Status'] ?? '';
                $rcptArea = $orderData['recipient']['Rcpt_Area'] ?? '';
                $pickAddr = $orderData['Ord_PickAddr'] ?? '';
                
                if (!Helper::isAreaMatch($hubArea, $rcptArea, $pickAddr, $status)) {
                    $error = "Unauthorized: This order is not in your hub's jurisdiction.";
                    goto endPost;
                }
                
                // Get Rider Name for the attempt
                $riderName = 'Rider';
                $rdrSnap = $db->getReference('users/' . $rdrId)->getSnapshot();
                if ($rdrSnap->exists()) {
                    $riderName = $rdrSnap->getValue()['Usr_Name'] ?? 'Rider';
                }

                $atmpId = Helper::generateId('ATM-');
                $attempt = [
                    'Atmp_ID' => $atmpId,
                    'Atmp_RdrID' => $rdrId,
                    'Rdr_Name' => $riderName,
                    'Atmp_Date' => $dateNow,
                    'Atmp_Rslt' => 'Pending',
                    'Atmp_Type' => $mode === 'pickup' ? 'Pickup' : 'Delivery'
                ];

                if ($mode === 'pickup') {
                    $orderData['Ord_Status'] = 'Pickup / Drop-off';
                } else if ($mode === 'delivery') {
                    // Mark previous pending attempts as failed/reassigned
                    if (isset($orderData['delivery_attempts'])) {
                        foreach ($orderData['delivery_attempts'] as $key => $att) {
                            if (($att['Atmp_Rslt'] ?? '') === 'Pending') {
                                $orderData['delivery_attempts'][$key]['Atmp_Rslt'] = 'Failed';
                                $orderData['delivery_attempts'][$key]['Atmp_FailRsn'] = 'Reassigned';
                            }
                        }
                    }
                    $orderData['Ord_Status'] = 'Out for Delivery';
                }
                
                $orderData['Rider_ID'] = $rdrId; // Assign the rider
                $orderData['delivery_attempts'][$atmpId] = $attempt;

                $orderRef->set($orderData);

                $_SESSION['toast_success'] = "Order dispatched to rider successfully!";
                header("Location: /ninjavan/staff/dispatch.php"); exit();
            } else {
                $error = "Order not found.";
            }
        } catch(Exception $e) {
            $error = "Dispatch failed: " . $e->getMessage();
        }
    }
    endPost:
}

// Get Hub Area
$hubSnapshot = $db->getReference('hubs/' . $hubId)->getSnapshot();
$hubData = $hubSnapshot->getValue();
$hubArea = $hubData['Hub_Area'] ?? '';
$hubName = $hubData['Hub_Name'] ?? 'Hub';

// Get Orders for Dispatch (Order Created for pickup, Destination Hub for delivery)
$orders = [];
$ordersSnap = $db->getReference('orders')->getSnapshot();
if ($ordersSnap->hasChildren()) {
    foreach ($ordersSnap->getValue() as $o) {
        $status = $o['Ord_Status'] ?? '';
        
        // For delivery, destination must match hub area loosely
        if ($status === 'Destination Hub') {
            $rcptArea = $o['recipient']['Rcpt_Area'] ?? '';
            $isMyArea = Helper::isAreaMatch($hubArea, $rcptArea, '', $status);
            
            if ($isMyArea) {
                $orders[] = $o;
            }
        } 
        // For pickup, we check if the origin area matches (based on Recipient Area for now, or just let staff see pickups in their general vicinity)
        // Since we don't store Shipper Area in the order, we'll allow Order Created if booked at this hub (walk-in)
        // Or if it's a shipper pickup. 
        else if ($status === 'Order Created') {
            // Check if walkin at this hub
            $pickAddr = $o['Ord_PickAddr'] ?? '';
            $rcptArea = $o['recipient']['Rcpt_Area'] ?? '';
            
            $isMyArea = Helper::isAreaMatch($hubArea, $rcptArea, $pickAddr, $status);
            
            if ($isMyArea) {
                $orders[] = $o;
            }
        }
    }
}

// Get Available Riders
$riderOptions = "";
$usersSnap = $db->getReference('users')->orderByChild('Usr_Type')->equalTo('rider')->getSnapshot();
if ($usersSnap->hasChildren()) {
    foreach ($usersSnap->getValue() as $r) {
        $rHub = $r['hub_id'] ?? $r['Rdr_HubID'] ?? '';
        if ($rHub === $hubId && ($r['Usr_Status'] ?? '') === 'Active') {
            $rType = $r['Rdr_VhcTyp'] ?? 'Motorcycle';
            $rName = $r['Usr_Name'] ?? 'Unknown';
            $rId = $r['Usr_ID'];
            $riderOptions .= "<option value='{$rId}'>{$rName} ({$rType}) [{$hubName}]</option>";
        }
    }
}

include "../layout/dashboard_layout.php";
?>

<div class="page-header">
    <div>
        <h1>Dispatch to Rider</h1>
        <p>Assign orders for pickup or final delivery to local branch riders</p>
    </div>
</div>

<?php if($error): ?>
<div class="nv-toast error position-relative mb-4" style="animation:none; bottom:auto; right:auto; transform:none; opacity:1;">
    <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="nv-card">
    <div style="padding:18px 22px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
        <h5 style="font-size:15px; margin:0;">Orders Awaiting Dispatch</h5>
        <span class="sb-badge" style="position:static; margin:0; padding:4px 10px; font-size:12px;"><?= count($orders) ?> Orders</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="nv-table">
            <thead>
                <tr>
                    <th>Tracking / Date</th>
                    <th>Mode / Address</th>
                    <th>Recipient / Area</th>
                    <th>Service</th>
                    <th>Assign Rider</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($orders)): ?>
                <tr><td colspan="5">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-check-circle"></i></div>
                        <h4>All Caught Up!</h4>
                        <p>There are no orders waiting for dispatch.</p>
                    </div>
                </td></tr>
            <?php else: foreach($orders as $o): 
                $status = $o['Ord_Status'] ?? '';
                $dispatchMode = ($status === 'Destination Hub') ? 'delivery' : 'pickup';
                $modeLabel = ($dispatchMode === 'pickup') ? '<span class="badge-status badge-pending">Pickup</span>' : '<span class="badge-status badge-transit">Delivery</span>';
            ?>
                <tr>
                    <td>
                        <div style="font-family:'Sora',sans-serif;font-size:14px;font-weight:700;color:var(--red);margin-bottom:4px;"><?= htmlspecialchars($o['awb']['AWB_TrkNum'] ?? $o['Ord_ID']) ?></div>
                        <div style="font-size:11px;color:var(--muted);"><i class="bi bi-clock"></i> <?= date('M d, h:i A', strtotime($o['Ord_CrtdDt'] ?? 'now')) ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:13px;"><?= $modeLabel ?></div>
                        <div style="font-size:11px;color:var(--muted);margin-top:4px;max-width:200px;white-space:normal;">
                            <?php if($dispatchMode === 'pickup'): ?>
                                <strong>From:</strong> <?= htmlspecialchars($o['Ord_PickAddr'] ?? 'Walk-in / Unknown') ?>
                            <?php else: ?>
                                <strong>To:</strong> <?= htmlspecialchars($o['recipient']['Rcpt_Area'] ?? '') ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($o['recipient']['Rcpt_Name'] ?? '') ?></div>
                        <div style="font-size:11px;color:var(--muted);margin-top:2px;"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($o['recipient']['Rcpt_Area'] ?? '') ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($o['service']['Svc_Name'] ?? '') ?></div>
                        <div style="font-size:11px;color:var(--muted);margin-top:2px;"><?= htmlspecialchars($o['parcel']['Pcl_Wght'] ?? '') ?> kg</div>
                    </td>
                    <td style="width:250px;">
                        <form method="POST" style="display:flex; gap:8px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $o['Ord_ID'] ?>">
                            <input type="hidden" name="dispatch_mode" value="<?= $dispatchMode ?>">
                            <select name="rider_id" class="nv-input" style="padding:6px 10px; font-size:12px;" required>
                                <option value="">Select Rider...</option>
                                <?= $riderOptions ?>
                            </select>
                            <button type="submit" name="dispatch" class="btn-nv" style="padding:6px 12px; font-size:12px;">Assign</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

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
                  <td><span class="skeleton-box" style="width: 80px;"></span></td>
                  <td><span class="skeleton-box" style="width: 150px;"></span></td>
              </tr>
              <tr>
                  <td><span class="skeleton-box" style="width: 80px;"></span></td>
                  <td><span class="skeleton-box" style="width: 120px;"></span></td>
                  <td><span class="skeleton-box" style="width: 150px;"></span></td>
                  <td><span class="skeleton-box" style="width: 90px;"></span></td>
                  <td><span class="skeleton-box" style="width: 50px;"></span></td>
                  <td><span class="skeleton-box" style="width: 100px;"></span></td>
                  <td><span class="skeleton-box" style="width: 80px;"></span></td>
                  <td><span class="skeleton-box" style="width: 150px;"></span></td>
              </tr>
          `;
      }

      // Silently fetch the updated page and replace the DOM elements
      fetch(window.location.href)
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Replace Table
            const newTable = doc.querySelector('.nv-table');
            if (newTable) document.querySelector('.nv-table').innerHTML = newTable.innerHTML;
            
            // Replace Badge count
            const newBadge = doc.querySelector('.sb-badge');
            if (newBadge) document.querySelector('.sb-badge').innerHTML = newBadge.innerHTML;
        });
  });
</script>

<?php include "../layout/dashboard_footer.php"; ?>