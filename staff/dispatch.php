<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'staff'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Dispatch Parcels";
$activePage = "dispatch";
$hubId      = $_SESSION['hub_id'];
$success    = "";
$error      = "";

// Helper to generate ID
function generateId($conn, $prefix, $table, $column) {
    $res = $conn->query("SELECT $column FROM $table ORDER BY $column DESC LIMIT 1");
    if($res && $res->num_rows > 0) {
        $lastId = $res->fetch_assoc()[$column];
        $num = (int)substr($lastId, strlen($prefix));
        return $prefix . str_pad($num + 1, 5, '0', STR_PAD_LEFT);
    }
    return $prefix . '00001';
}

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
        $conn->begin_transaction();
        try {
            $dateNow = date('Y-m-d H:i:s');
            
            if ($mode === 'pickup') {
                // 1. Create Shipment
                $shpmId = generateId($conn, 'SHP', 'SHIPMENT', 'Shpm_ID');
                $conn->query("INSERT INTO SHIPMENT (Shpm_ID, Shpm_OrdID, Shpm_HubID, Shpm_Status, Shpm_AtmCnt) 
                              VALUES ('$shpmId', '$ordId', '$hubId', 'Pickup / Drop-off', 0)");

                // 2. Create initial Delivery Attempt to link rider
                $atmpId = generateId($conn, 'ATM', 'DELIVERY_ATTEMPT', 'Atmp_ID');
                $conn->query("INSERT INTO DELIVERY_ATTEMPT (Atmp_ID, Atmp_ShpmID, Atmp_RdrID, Atmp_Date, Atmp_Rslt) 
                              VALUES ('$atmpId', '$shpmId', '$rdrId', '$dateNow', 'Pending')");

                // 3. Update Order Status
                $conn->query("UPDATE `ORDER` SET Ord_Status = 'Pickup / Drop-off' WHERE Ord_ID = '$ordId'");
            } else if ($mode === 'delivery') {
                // Find existing shipment
                $shpmRes = $conn->query("SELECT Shpm_ID FROM SHIPMENT WHERE Shpm_OrdID = '$ordId'");
                if($shpmRes->num_rows > 0) {
                    $shpmId = $shpmRes->fetch_assoc()['Shpm_ID'];
                    
                    // Update Shipment
                    $conn->query("UPDATE SHIPMENT SET Shpm_Status = 'Out for Delivery', Shpm_HubID = '$hubId' WHERE Shpm_ID = '$shpmId'");
                    
                    // Clear previous pending attempts if any, then Create new delivery attempt
                    $conn->query("UPDATE DELIVERY_ATTEMPT SET Atmp_Rslt = 'Failed', Atmp_FailRsn = 'Reassigned' WHERE Atmp_ShpmID = '$shpmId' AND Atmp_Rslt = 'Pending'");
                    $atmpId = generateId($conn, 'ATM', 'DELIVERY_ATTEMPT', 'Atmp_ID');
                    $conn->query("INSERT INTO DELIVERY_ATTEMPT (Atmp_ID, Atmp_ShpmID, Atmp_RdrID, Atmp_Date, Atmp_Rslt) 
                                  VALUES ('$atmpId', '$shpmId', '$rdrId', '$dateNow', 'Pending')");
                                  
                    // Update Order Status
                    $conn->query("UPDATE `ORDER` SET Ord_Status = 'Out for Delivery' WHERE Ord_ID = '$ordId'");
                }
            }

            $conn->commit();
            $_SESSION['toast_success'] = "Order dispatched to rider successfully!";
            header("Location: /ninjavan/staff/dispatch.php"); exit();
        } catch(Exception $e) {
            $conn->rollback();
            $error = "Dispatch failed: " . $e->getMessage();
        }
    }
    endPost:
}

// Get Hub Area
$hubData = $conn->query("SELECT Hub_Area FROM HUB WHERE Hub_ID = '$hubId'")->fetch_assoc();
$hubArea = $conn->real_escape_string($hubData['Hub_Area'] ?? '');

// Get Orders for Dispatch (Order Created for pickup, Destination Hub for delivery)
$orders = $conn->query("
    SELECT o.*, p.Pcl_Wght, r.Rcpt_Name, r.Rcpt_Area, s.Svc_Name, aw.AWB_TrkNum, sh.Shpr_PickAddr
    FROM `ORDER` o
    JOIN PARCEL p ON o.Ord_PclID = p.Pcl_ID
    JOIN RECIPIENT r ON p.Pcl_RcptID = r.Rcpt_ID
    JOIN SERVICE_TYPE s ON o.Ord_SvcID = s.Svc_ID
    JOIN SHIPPER sh ON o.Ord_ShprID = sh.Shpr_ID
    LEFT JOIN AIRWAY_BILL aw ON aw.AWB_OrdID = o.Ord_ID
    WHERE o.Ord_Status IN ('Order Created', 'Destination Hub')
    ORDER BY o.Ord_Status ASC, o.Ord_CrtdDt ASC
");

// Get Available Riders
$riders = $conn->query("SELECT rd.*, h.Hub_Name FROM RIDER rd LEFT JOIN HUB h ON h.Hub_ID = rd.Rdr_HubID WHERE rd.Rdr_Status = 'Active' ORDER BY h.Hub_Name, rd.Rdr_Name");
$riderOptions = "";
while($r = $riders->fetch_assoc()) {
    $hubLabel = $r['Hub_Name'] ? " [{$r['Hub_Name']}]" : '';
    $riderOptions .= "<option value='{$r['Rdr_ID']}'>{$r['Rdr_Name']} ({$r['Rdr_VhcTyp']}){$hubLabel}</option>";
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
        <span class="sb-badge" style="position:static; margin:0; padding:4px 10px; font-size:12px;"><?= $orders->num_rows ?> Orders</span>
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
            <?php if($orders->num_rows === 0): ?>
                <tr><td colspan="5">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-check-circle"></i></div>
                        <h4>All Caught Up!</h4>
                        <p>There are no orders waiting for dispatch.</p>
                    </div>
                </td></tr>
            <?php else: while($o = $orders->fetch_assoc()): 
                $dispatchMode = ($o['Ord_Status'] === 'Destination Hub') ? 'delivery' : 'pickup';
                $modeLabel = ($dispatchMode === 'pickup') ? '<span class="badge-status badge-pending">Pickup</span>' : '<span class="badge-status badge-transit">Delivery</span>';
            ?>
                <tr>
                    <td>
                        <div style="font-family:'Sora',sans-serif;font-size:14px;font-weight:700;color:var(--red);margin-bottom:4px;"><?= htmlspecialchars($o['AWB_TrkNum'] ?? $o['Ord_ID']) ?></div>
                        <div style="font-size:11px;color:var(--muted);"><i class="bi bi-clock"></i> <?= date('M d, h:i A', strtotime($o['Ord_CrtdDt'])) ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:13px;"><?= $modeLabel ?></div>
                        <div style="font-size:11px;color:var(--muted);margin-top:4px;max-width:200px;white-space:normal;">
                            <?php if($dispatchMode === 'pickup'): ?>
                                <strong>From:</strong> <?= htmlspecialchars($o['Ord_PickAddr'] ?? $o['Shpr_PickAddr']) ?>
                            <?php else: ?>
                                <strong>To:</strong> <?= htmlspecialchars($o['Rcpt_Area']) ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($o['Rcpt_Name']) ?></div>
                        <div style="font-size:11px;color:var(--muted);margin-top:2px;"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($o['Rcpt_Area']) ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($o['Svc_Name']) ?></div>
                        <div style="font-size:11px;color:var(--muted);margin-top:2px;"><?= htmlspecialchars($o['Pcl_Wght']) ?> kg</div>
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
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "../layout/dashboard_footer.php"; ?>