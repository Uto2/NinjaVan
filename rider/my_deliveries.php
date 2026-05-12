<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'rider'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "My Deliveries";
$activePage = "deliveries";
$riderId    = $_SESSION['rider_id'];
$success    = "";
$error      = "";

// Handle Status Updates
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $shpmId = $conn->real_escape_string($_POST['shpm_id']);
    $ordId  = $conn->real_escape_string($_POST['ord_id']);
    $atmpId = $conn->real_escape_string($_POST['atmp_id']);
    $dateNow = date('Y-m-d H:i:s');

    $conn->begin_transaction();
    try {
        if($action === 'pickup') {
            $conn->query("UPDATE SHIPMENT SET Shpm_Status='In Transit', Shpm_PickDt='$dateNow' WHERE Shpm_ID='$shpmId'");
            $conn->query("UPDATE `ORDER` SET Ord_Status='In Transit' WHERE Ord_ID='$ordId'");
            $_SESSION['toast_success'] = "Parcel marked as picked up!";
        }
        elseif($action === 'out_for_delivery') {
            $conn->query("UPDATE SHIPMENT SET Shpm_Status='Out for Delivery' WHERE Shpm_ID='$shpmId'");
            $conn->query("UPDATE `ORDER` SET Ord_Status='Out for Delivery' WHERE Ord_ID='$ordId'");
            $_SESSION['toast_success'] = "Parcel marked as Out for Delivery!";
        }
        elseif($action === 'attempt') {
            $result = $conn->real_escape_string($_POST['result']);
            $reason = $conn->real_escape_string($_POST['reason'] ?? '');
            $sign   = $conn->real_escape_string($_POST['signature'] ?? '');

            // Update current attempt
            $conn->query("UPDATE DELIVERY_ATTEMPT SET Atmp_Rslt='$result', Atmp_FailRsn='$reason', Atmp_Sign='$sign', Atmp_Date='$dateNow' WHERE Atmp_ID='$atmpId'");
            
            // Increment attempt count
            $conn->query("UPDATE SHIPMENT SET Shpm_AtmCnt = Shpm_AtmCnt + 1 WHERE Shpm_ID='$shpmId'");

            if($result === 'Successful') {
                $conn->query("UPDATE SHIPMENT SET Shpm_Status='Delivered', Shpm_DlvDt='$dateNow' WHERE Shpm_ID='$shpmId'");
                $conn->query("UPDATE `ORDER` SET Ord_Status='Delivered' WHERE Ord_ID='$ordId'");
                $_SESSION['toast_success'] = "Delivery successful!";
            } else {
                // Check if max attempts reached
                $shpmData = $conn->query("SELECT Shpm_AtmCnt FROM SHIPMENT WHERE Shpm_ID='$shpmId'")->fetch_assoc();
                if($shpmData['Shpm_AtmCnt'] >= 3) {
                    $conn->query("UPDATE SHIPMENT SET Shpm_Status='RTS' WHERE Shpm_ID='$shpmId'");
                    $conn->query("UPDATE `ORDER` SET Ord_Status='RTS' WHERE Ord_ID='$ordId'");
                    $_SESSION['toast_error'] = "Max attempts reached. Parcel is marked as Return to Sender.";
                } else {
                    $conn->query("UPDATE SHIPMENT SET Shpm_Status='In Transit' WHERE Shpm_ID='$shpmId'");
                    $conn->query("UPDATE `ORDER` SET Ord_Status='In Transit' WHERE Ord_ID='$ordId'");
                    
                    // Create new pending attempt for next try
                    $newAtmpId = 'ATM' . strtoupper(substr(md5(uniqid()), 0, 5));
                    $conn->query("INSERT INTO DELIVERY_ATTEMPT (Atmp_ID, Atmp_ShpmID, Atmp_RdrID, Atmp_Date, Atmp_Rslt) 
                                  VALUES ('$newAtmpId', '$shpmId', '$riderId', '$dateNow', 'Pending')");
                    
                    $_SESSION['toast_error'] = "Delivery failed. Will retry tomorrow.";
                }
            }
        }
        $conn->commit();
        header("Location: /ninjavan/rider/my_deliveries.php"); exit();
    } catch(Exception $e) {
        $conn->rollback();
        $error = "Update failed: " . $e->getMessage();
    }
}

// Get Active Deliveries
// Group by shipment so we only show the LATEST pending attempt per shipment
$deliveries = $conn->query("
    SELECT o.Ord_ID, o.Ord_Status, o.Ord_PickAddr, o.Ord_PickPref,
           p.Pcl_Wght, p.Pcl_IsCOD, p.Pcl_CODAmt,
           r.Rcpt_Name, r.Rcpt_Addr, r.Rcpt_Phone, r.Rcpt_Area,
           aw.AWB_TrkNum,
           sh.Shpm_ID, sh.Shpm_Status, sh.Shpm_AtmCnt,
           da.Atmp_ID
    FROM DELIVERY_ATTEMPT da
    JOIN SHIPMENT sh ON da.Atmp_ShpmID = sh.Shpm_ID
    JOIN `ORDER` o ON sh.Shpm_OrdID = o.Ord_ID
    JOIN PARCEL p ON o.Ord_PclID = p.Pcl_ID
    JOIN RECIPIENT r ON p.Pcl_RcptID = r.Rcpt_ID
    LEFT JOIN AIRWAY_BILL aw ON aw.AWB_OrdID = o.Ord_ID
    WHERE da.Atmp_RdrID = '$riderId' 
      AND da.Atmp_Rslt = 'Pending'
      AND sh.Shpm_Status IN ('Pending Pickup', 'In Transit', 'Out for Delivery')
    ORDER BY sh.Shpm_Status DESC, o.Ord_CrtdDt ASC
");

include "../layout/dashboard_layout.php";
?>

<div class="page-header">
    <div>
        <h1>My Deliveries</h1>
        <p>Manage your active route and log delivery attempts</p>
    </div>
</div>

<?php if($error): ?>
<div class="nv-toast error position-relative mb-4" style="animation:none; bottom:auto; right:auto; transform:none; opacity:1;">
    <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="row g-4">
    <?php if($deliveries->num_rows === 0): ?>
    <div class="col-12">
        <div class="nv-card p-5 text-center">
            <div class="empty-state-icon"><i class="bi bi-bicycle"></i></div>
            <h4 style="font-family:'Sora',sans-serif; font-weight:700;">No active deliveries</h4>
            <p style="color:var(--muted); font-size:14px;">Wait for the branch staff to dispatch parcels to you.</p>
        </div>
    </div>
    <?php else: while($d = $deliveries->fetch_assoc()): 
        $s = $d['Shpm_Status'];
        $map = [
            'Pending Pickup'=>'badge-confirmed',
            'In Transit'=>'badge-transit',
            'Out for Delivery'=>'badge-delivery'
        ];
        $cls = $map[$s] ?? 'badge-pending';
    ?>
    <div class="col-12">
        <div class="nv-card" style="display:flex; flex-direction:column; padding:0;">
            <!-- Header -->
            <div style="padding:16px 24px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.01);">
                <div style="display:flex; align-items:center; gap:16px;">
                    <span style="font-family:'Sora',sans-serif; font-size:16px; font-weight:800; color:var(--red);">
                        <?= htmlspecialchars($d['AWB_TrkNum'] ?? $d['Ord_ID']) ?>
                    </span>
                    <span style="color:var(--muted); font-size:12px; font-weight:600;"><i class="bi bi-arrow-repeat"></i> Attempt <?= $d['Shpm_AtmCnt'] + 1 ?> of 3</span>
                </div>
                <div>
                    <span class="badge-status <?= $cls ?>"><?= $s ?></span>
                </div>
            </div>
            
            <!-- Body -->
            <div style="padding:24px; display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:24px;">
                
                <?php if($s === 'Pending Pickup'): ?>
                <!-- Pickup Info -->
                <div>
                    <div style="font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Pickup Location</div>
                    <div style="font-weight:600; color:var(--ink); font-size:14px;"><?= htmlspecialchars($d['Ord_PickPref']) ?></div>
                    <div style="color:var(--muted); font-size:13px; margin-top:2px; max-width:250px;"><?= htmlspecialchars($d['Ord_PickAddr'] ?: 'Hub Dropoff') ?></div>
                </div>
                <?php else: ?>
                <!-- Delivery Info -->
                <div>
                    <div style="font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Deliver To</div>
                    <div style="font-weight:600; color:var(--ink); font-size:14px;"><?= htmlspecialchars($d['Rcpt_Name']) ?></div>
                    <div style="color:var(--muted); font-size:13px; margin-top:2px;"><i class="bi bi-telephone"></i> <?= htmlspecialchars($d['Rcpt_Phone']) ?></div>
                    <div style="color:var(--muted); font-size:13px; margin-top:2px; max-width:250px;"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($d['Rcpt_Addr']) ?></div>
                </div>
                <?php endif; ?>

                <div>
                    <div style="font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Parcel</div>
                    <div style="color:var(--ink); font-size:13px; margin-bottom:4px;"><span style="color:var(--muted);">Weight:</span> <?= htmlspecialchars($d['Pcl_Wght']) ?> kg</div>
                    
                    <?php if($d['Pcl_IsCOD'] === 'Yes'): ?>
                        <div style="display:inline-block; background:var(--amber-soft); color:var(--amber); font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; margin-bottom:6px; margin-top:6px;">COD ₱<?= number_format($d['Pcl_CODAmt'], 2) ?></div>
                    <?php else: ?>
                        <div style="display:inline-block; background:var(--surface); color:var(--muted); font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; margin-bottom:6px; margin-top:6px;">NON-COD</div>
                    <?php endif; ?>
                </div>

                <div style="display:flex; flex-direction:column; gap:8px; justify-content:center;">
                    <form method="POST">
                        <input type="hidden" name="shpm_id" value="<?= $d['Shpm_ID'] ?>">
                        <input type="hidden" name="ord_id" value="<?= $d['Ord_ID'] ?>">
                        <input type="hidden" name="atmp_id" value="<?= $d['Atmp_ID'] ?>">
                        
                        <?php if($s === 'Pending Pickup'): ?>
                            <button type="submit" name="action" value="pickup" class="btn-nv w-100" style="justify-content:center; background:var(--ink);">
                                <i class="bi bi-box-arrow-up"></i> Mark as Picked Up
                            </button>
                        <?php elseif($s === 'In Transit'): ?>
                            <button type="submit" name="action" value="out_for_delivery" class="btn-nv w-100" style="justify-content:center; background:var(--blue);">
                                <i class="bi bi-truck"></i> Out for Delivery
                            </button>
                        <?php elseif($s === 'Out for Delivery'): ?>
                            <button type="button" class="btn-nv w-100" style="justify-content:center; background:var(--green);" data-bs-toggle="modal" data-bs-target="#attemptModal<?= $d['Atmp_ID'] ?>">
                                <i class="bi bi-check2-square"></i> Log Attempt
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Attempt Modal -->
    <div class="modal fade" id="attemptModal<?= $d['Atmp_ID'] ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius:16px; overflow:hidden;">
                <form method="POST">
                    <div class="modal-header" style="background:var(--surface); border-bottom:1px solid var(--border); padding:20px 24px;">
                        <h5 class="modal-title" style="font-family:'Sora',sans-serif; font-size:16px; font-weight:800;">Log Delivery Attempt</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding:24px;">
                        <input type="hidden" name="shpm_id" value="<?= $d['Shpm_ID'] ?>">
                        <input type="hidden" name="ord_id" value="<?= $d['Ord_ID'] ?>">
                        <input type="hidden" name="atmp_id" value="<?= $d['Atmp_ID'] ?>">
                        <input type="hidden" name="action" value="attempt">
                        
                        <div class="nv-form-group">
                            <label>Attempt Result</label>
                            <select name="result" class="nv-input attempt-res-sel" required onchange="toggleAttemptFields(this, '<?= $d['Atmp_ID'] ?>')">
                                <option value="">Select result...</option>
                                <option value="Successful">Successful</option>
                                <option value="Failed">Failed / Unavailable</option>
                            </select>
                        </div>

                        <div id="successFields<?= $d['Atmp_ID'] ?>" style="display:none;">
                            <div class="nv-form-group mt-3">
                                <label>Received By (Signature / Name)</label>
                                <input type="text" name="signature" class="nv-input" placeholder="Name of person who received it">
                            </div>
                            <?php if($d['Pcl_IsCOD'] === 'Yes'): ?>
                            <div class="nv-success mt-3" style="margin-bottom:0;">
                                <i class="bi bi-cash-stack"></i>
                                <span>Remember to collect <strong>₱<?= number_format($d['Pcl_CODAmt'], 2) ?></strong></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div id="failFields<?= $d['Atmp_ID'] ?>" style="display:none;">
                            <div class="nv-form-group mt-3">
                                <label>Failure Reason</label>
                                <select name="reason" class="nv-input">
                                    <option value="No one home">No one home</option>
                                    <option value="Wrong address">Wrong address</option>
                                    <option value="Refused to accept">Refused to accept</option>
                                    <option value="Consignee unknown">Consignee unknown</option>
                                    <option value="Business closed">Business closed</option>
                                </select>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer" style="border-top:1px solid var(--border); padding:16px 24px;">
                        <button type="button" class="btn-nv-ghost" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-nv">Save Attempt</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endwhile; endif; ?>
</div>

<script>
function toggleAttemptFields(sel, id) {
    const val = sel.value;
    const succ = document.getElementById('successFields' + id);
    const fail = document.getElementById('failFields' + id);
    const sign = succ.querySelector('input[name="signature"]');
    const rsn  = fail.querySelector('select[name="reason"]');

    if(val === 'Successful') {
        succ.style.display = 'block';
        fail.style.display = 'none';
        sign.required = true;
        rsn.required = false;
    } else if(val === 'Failed') {
        succ.style.display = 'none';
        fail.style.display = 'block';
        sign.required = false;
        rsn.required = true;
    } else {
        succ.style.display = 'none';
        fail.style.display = 'none';
        sign.required = false;
        rsn.required = false;
    }
}
</script>

<?php include "../layout/dashboard_footer.php"; ?>
