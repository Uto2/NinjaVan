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
    $ordId = $conn->real_escape_string($_POST['order_id']);
    $rdrId = $conn->real_escape_string($_POST['rider_id']);

    if(!$ordId || !$rdrId) {
        $error = "Please select an order and a rider.";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Create Shipment
            $shpmId = generateId($conn, 'SHP', 'SHIPMENT', 'Shpm_ID');
            $dateNow = date('Y-m-d H:i:s');
            
            // Note: Shpm_Status starts as Pending Pickup since Rider hasn't picked it up from sender/hub yet.
            $conn->query("INSERT INTO SHIPMENT (Shpm_ID, Shpm_OrdID, Shpm_HubID, Shpm_Status, Shpm_AtmCnt) 
                          VALUES ('$shpmId', '$ordId', '$hubId', 'Pending Pickup', 0)");

            // 2. Create initial Delivery Attempt to link rider
            $atmpId = generateId($conn, 'ATM', 'DELIVERY_ATTEMPT', 'Atmp_ID');
            // We use 'Assigned' or similar as initial result. Schema says Successful/Failed/Unavailable.
            // We'll leave Atmp_Rslt blank or 'Pending' until they actually attempt it.
            $conn->query("INSERT INTO DELIVERY_ATTEMPT (Atmp_ID, Atmp_ShpmID, Atmp_RdrID, Atmp_Date, Atmp_Rslt) 
                          VALUES ('$atmpId', '$shpmId', '$rdrId', '$dateNow', 'Pending')");

            // 3. Update Order Status
            $conn->query("UPDATE `ORDER` SET Ord_Status = 'Pending Pickup' WHERE Ord_ID = '$ordId'");

            $conn->commit();
            $_SESSION['toast_success'] = "Order dispatched to rider successfully!";
            header("Location: /ninjavan/staff/dispatch.php"); exit();
        } catch(Exception $e) {
            $conn->rollback();
            $error = "Dispatch failed: " . $e->getMessage();
        }
    }
}

// Get Hub Area
$hubData = $conn->query("SELECT Hub_Area FROM HUB WHERE Hub_ID = '$hubId'")->fetch_assoc();
$hubArea = $conn->real_escape_string($hubData['Hub_Area'] ?? '');

// Get Orders needing dispatch (Staging status)
// Filtered by Hub Area
$orders = $conn->query("
    SELECT o.*, p.Pcl_Wght, r.Rcpt_Name, r.Rcpt_Area, s.Svc_Name, aw.AWB_TrkNum, sh.Shpr_PickAddr
    FROM `ORDER` o
    JOIN PARCEL p ON o.Ord_PclID = p.Pcl_ID
    JOIN RECIPIENT r ON p.Pcl_RcptID = r.Rcpt_ID
    JOIN SERVICE_TYPE s ON o.Ord_SvcID = s.Svc_ID
    JOIN SHIPPER sh ON o.Ord_ShprID = sh.Shpr_ID
    LEFT JOIN AIRWAY_BILL aw ON aw.AWB_OrdID = o.Ord_ID
    WHERE o.Ord_Status = 'Staging'
      AND ('$hubArea' = '' OR r.Rcpt_Area LIKE '%$hubArea%' OR o.Ord_PickAddr LIKE '%$hubArea%')
    ORDER BY o.Ord_CrtdDt ASC
");

// Get Available Riders for this Hub
$riders = $conn->query("SELECT * FROM RIDER WHERE Rdr_HubID = '$hubId' AND Rdr_Status = 'Active'");
$riderOptions = "";
while($r = $riders->fetch_assoc()) {
    $riderOptions .= "<option value='{$r['Rdr_ID']}'>{$r['Rdr_Name']} ({$r['Rdr_VhcTyp']})</option>";
}

include "../layout/dashboard_layout.php";
?>

<div class="page-header">
    <div>
        <h1>Dispatch to Rider</h1>
        <p>Assign staging orders to local branch riders</p>
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
                    <th>Pickup Details</th>
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
            <?php else: while($o = $orders->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div style="font-family:'Sora',sans-serif;font-size:14px;font-weight:700;color:var(--red);margin-bottom:4px;"><?= htmlspecialchars($o['AWB_TrkNum'] ?? $o['Ord_ID']) ?></div>
                        <div style="font-size:11px;color:var(--muted);"><i class="bi bi-clock"></i> <?= date('M d, h:i A', strtotime($o['Ord_CrtdDt'])) ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($o['Ord_PickPref']) ?></div>
                        <div style="font-size:11px;color:var(--muted);margin-top:2px;max-width:200px;white-space:normal;">
                            <?= htmlspecialchars($o['Ord_PickAddr'] ?? $o['Shpr_PickAddr']) ?>
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
                            <input type="hidden" name="order_id" value="<?= $o['Ord_ID'] ?>">
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
