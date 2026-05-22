<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'rider'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "My Deliveries";
$activePage = "deliveries";
$riderId    = $_SESSION['rider_id'] ?? '';
if (empty($riderId)) {
    $riderId = $_SESSION['account_id'];
    $_SESSION['rider_id'] = $riderId;
}
$success    = "";
$error      = "";

function generateId($prefix) {
    return $prefix . strtoupper(substr(uniqid(), -6));
}

// Handle Status Updates
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $ordId  = $_POST['ord_id'];
    $atmpId = $_POST['atmp_id'] ?? '';
    $dateNow = date('c');

    try {
        $orderRef = $db->getReference('orders/' . $ordId);
        $orderSnap = $orderRef->getSnapshot();
        
        if ($orderSnap->exists()) {
            $orderData = $orderSnap->getValue();
            
            if($action === 'pickup') {
                $orderData['Ord_Status'] = 'Origin Sorting Hub';
                if(isset($orderData['delivery_attempts'][$atmpId])) {
                    $orderData['delivery_attempts'][$atmpId]['Atmp_Rslt'] = 'Successful';
                    $orderData['delivery_attempts'][$atmpId]['Atmp_Date'] = $dateNow;
                    $orderData['delivery_attempts'][$atmpId]['Atmp_Type'] = 'Pickup';
                }
                $orderRef->set($orderData);
                $_SESSION['toast_success'] = "Parcel marked as picked up!";
            }
            elseif($action === 'attempt') {
                $result = trim($_POST['result']);
                $reason = trim($_POST['reason'] ?? '');
                $sign   = trim($_POST['signature'] ?? '');

                // Update current attempt
                if(isset($orderData['delivery_attempts'][$atmpId])) {
                    $orderData['delivery_attempts'][$atmpId]['Atmp_Rslt'] = $result;
                    $orderData['delivery_attempts'][$atmpId]['Atmp_FailRsn'] = $reason;
                    $orderData['delivery_attempts'][$atmpId]['Atmp_Sign'] = $sign;
                    $orderData['delivery_attempts'][$atmpId]['Atmp_Date'] = $dateNow;
                }
                
                // Count attempts
                $atmCnt = isset($orderData['delivery_attempts']) ? count($orderData['delivery_attempts']) : 0;

                if($result === 'Successful') {
                    $orderData['Ord_Status'] = 'Delivered';
                    $_SESSION['toast_success'] = "Delivery successful!";
                } else {
                    // Check if max attempts reached
                    if($atmCnt >= 3) {
                        $orderData['Ord_Status'] = 'RTS';
                        $_SESSION['toast_error'] = "Max attempts reached. Parcel is marked as Return to Sender.";
                    } else {
                        $orderData['Ord_Status'] = 'Out for Delivery';
                        
                        // Create new pending attempt for next try
                        $newAtmpId = generateId('ATM-');
                        $orderData['delivery_attempts'][$newAtmpId] = [
                            'Atmp_ID' => $newAtmpId,
                            'Atmp_RdrID' => $riderId,
                            'Atmp_Date' => $dateNow,
                            'Atmp_Rslt' => 'Pending',
                            'Atmp_Type' => 'Delivery'
                        ];
                        
                        $_SESSION['toast_error'] = "Delivery failed. Will retry tomorrow.";
                    }
                }
                $orderRef->set($orderData);
            }
            header("Location: /ninjavan/rider/my_deliveries.php"); exit();
        }
    } catch(Exception $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// Get Active Deliveries
$deliveries = [];
$ordersSnap = $db->getReference('orders')->getSnapshot();
if ($ordersSnap->hasChildren()) {
    $allOrders = $ordersSnap->getValue();
    
    uasort($allOrders, function($a, $b) {
        return strtotime($a['Ord_CrtdDt'] ?? 0) <=> strtotime($b['Ord_CrtdDt'] ?? 0);
    });

    foreach ($allOrders as $o) {
        $status = $o['Ord_Status'] ?? '';
        // Only active statuses
        if (in_array($status, ['Pickup / Drop-off', 'Out for Delivery'])) {
            
            // Look for pending attempt assigned to this rider
            if (isset($o['delivery_attempts'])) {
                $pendingAtmp = null;
                foreach ($o['delivery_attempts'] as $att) {
                    if (($att['Atmp_RdrID'] ?? '') === $riderId && ($att['Atmp_Rslt'] ?? '') === 'Pending') {
                        $pendingAtmp = $att;
                        break;
                    }
                }
                
                if ($pendingAtmp) {
                    $o['Active_Attempt'] = $pendingAtmp;
                    $o['Attempt_Count'] = count($o['delivery_attempts']);
                    $deliveries[] = $o;
                }
            }
        }
    }
}

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
    <?php if(empty($deliveries)): ?>
    <div class="col-12">
        <div class="nv-card p-5 text-center">
            <div class="empty-state-icon"><i class="bi bi-bicycle"></i></div>
            <h4 style="font-family:'Sora',sans-serif; font-weight:700;">No active deliveries</h4>
            <p style="color:var(--muted); font-size:14px;">Wait for the branch staff to dispatch parcels to you.</p>
        </div>
    </div>
    <?php else: foreach($deliveries as $d): 
        $s = $d['Ord_Status'] ?? '';
        $map = [
            'Pickup / Drop-off'=>'badge-confirmed',
            'Out for Delivery'=>'badge-delivery'
        ];
        $cls = $map[$s] ?? 'badge-pending';
        $attemptCount = $d['Attempt_Count'] ?? 1;
        $atmpId = $d['Active_Attempt']['Atmp_ID'] ?? '';
    ?>
    <div class="col-12">
        <div class="nv-card" style="display:flex; flex-direction:column; padding:0;">
            <!-- Header -->
            <div style="padding:16px 24px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.01);">
                <div style="display:flex; align-items:center; gap:16px;">
                    <span style="font-family:'Sora',sans-serif; font-size:16px; font-weight:800; color:var(--red);">
                        <?= htmlspecialchars($d['awb']['AWB_TrkNum'] ?? $d['Ord_ID']) ?>
                    </span>
                    <span style="color:var(--muted); font-size:12px; font-weight:600;"><i class="bi bi-arrow-repeat"></i> Attempt <?= $attemptCount ?> of 3</span>
                </div>
                <div>
                    <span class="badge-status <?= $cls ?>"><?= $s ?></span>
                </div>
            </div>
            
            <!-- Body -->
            <div style="padding:24px; display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:24px;">
                
                <?php if($s === 'Pickup / Drop-off'): ?>
                <!-- Pickup Info -->
                <div>
                    <div style="font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Pickup Location</div>
                    <div style="font-weight:600; color:var(--ink); font-size:14px;"><?= htmlspecialchars($d['Ord_PickPref'] ?? 'Dropoff') ?></div>
                    <div style="color:var(--muted); font-size:13px; margin-top:2px; max-width:250px;"><?= htmlspecialchars($d['Ord_PickAddr'] ?: 'Hub Dropoff') ?></div>
                </div>
                <?php else: ?>
                <!-- Delivery Info -->
                <div>
                    <div style="font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Deliver To</div>
                    <div style="font-weight:600; color:var(--ink); font-size:14px;"><?= htmlspecialchars($d['recipient']['Rcpt_Name'] ?? '') ?></div>
                    <div style="color:var(--muted); font-size:13px; margin-top:2px;"><i class="bi bi-telephone"></i> <?= htmlspecialchars($d['recipient']['Rcpt_Phone'] ?? '') ?></div>
                    <div style="color:var(--muted); font-size:13px; margin-top:2px; max-width:250px;"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($d['recipient']['Rcpt_Addr'] ?? '') ?></div>
                </div>
                <?php endif; ?>

                <div>
                    <div style="font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Parcel</div>
                    <div style="color:var(--ink); font-size:13px; margin-bottom:4px;"><span style="color:var(--muted);">Weight:</span> <?= htmlspecialchars($d['parcel']['Pcl_Wght'] ?? 0) ?> kg</div>
                    
                    <?php if(($d['parcel']['Pcl_IsCOD'] ?? 'No') === 'Yes'): ?>
                        <div style="display:inline-block; background:var(--amber-soft); color:var(--amber); font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; margin-bottom:6px; margin-top:6px;">COD ₱<?= number_format($d['parcel']['Pcl_CODAmt'] ?? 0, 2) ?></div>
                    <?php else: ?>
                        <div style="display:inline-block; background:var(--surface); color:var(--muted); font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; margin-bottom:6px; margin-top:6px;">NON-COD</div>
                    <?php endif; ?>
                </div>

                <div style="display:flex; flex-direction:column; gap:8px; justify-content:center;">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ord_id" value="<?= $d['Ord_ID'] ?>">
                        <input type="hidden" name="atmp_id" value="<?= $atmpId ?>">
                        
                        <?php if($s === 'Pickup / Drop-off'): ?>
                            <button type="submit" name="action" value="pickup" class="btn-nv w-100" style="justify-content:center; background:var(--ink);">
                                <i class="bi bi-box-arrow-up"></i> Mark as Picked Up
                            </button>
                        <?php elseif($s === 'Out for Delivery'): ?>
                            <button type="button" class="btn-nv w-100" style="justify-content:center; background:var(--green);" data-bs-toggle="modal" data-bs-target="#attemptModal<?= $atmpId ?>">
                                <i class="bi bi-check2-square"></i> Log Attempt
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Attempt Modal -->
    <div class="modal fade" id="attemptModal<?= $atmpId ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius:16px; overflow:hidden;">
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="modal-header" style="background:var(--surface); border-bottom:1px solid var(--border); padding:20px 24px;">
                        <h5 class="modal-title" style="font-family:'Sora',sans-serif; font-size:16px; font-weight:800;">Log Delivery Attempt</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding:24px;">
                        <input type="hidden" name="ord_id" value="<?= $d['Ord_ID'] ?>">
                        <input type="hidden" name="atmp_id" value="<?= $atmpId ?>">
                        <input type="hidden" name="action" value="attempt">
                        
                        <div class="nv-form-group">
                            <label>Attempt Result</label>
                            <select name="result" class="nv-input attempt-res-sel" required onchange="toggleAttemptFields(this, '<?= $atmpId ?>')">
                                <option value="">Select result...</option>
                                <option value="Successful">Successful</option>
                                <option value="Failed">Failed / Unavailable</option>
                            </select>
                        </div>

                        <div id="successFields<?= $atmpId ?>" style="display:none;">
                            <div class="nv-form-group mt-3">
                                <label>Received By (Signature / Name)</label>
                                <input type="text" name="signature" class="nv-input" placeholder="Name of person who received it">
                            </div>
                            <?php if(($d['parcel']['Pcl_IsCOD'] ?? 'No') === 'Yes'): ?>
                            <div class="nv-success mt-3" style="margin-bottom:0;">
                                <i class="bi bi-cash-stack"></i>
                                <span>Remember to collect <strong>₱<?= number_format($d['parcel']['Pcl_CODAmt'] ?? 0, 2) ?></strong></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div id="failFields<?= $atmpId ?>" style="display:none;">
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
    <?php endforeach; endif; ?>
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
