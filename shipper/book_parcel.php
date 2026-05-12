<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'shipper'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Book a Parcel";
$activePage = "book";
$shipperId  = $_SESSION['shipper_id'];
$error      = "";
$success    = "";

// Fetch Shipper Info for default pickup address
$shipperRes = $conn->query("SELECT * FROM SHIPPER WHERE Shpr_ID = '$shipperId'");
$shipper = $shipperRes->fetch_assoc();
$defaultAddress = $shipper['Shpr_PickAddr'] ?? '';

// Ensure SERVICE_TYPE has data
$svcCheck = $conn->query("SELECT COUNT(*) c FROM SERVICE_TYPE")->fetch_assoc()['c'];
if($svcCheck == 0){
    $conn->query("INSERT INTO SERVICE_TYPE (Svc_ID, Svc_Name, Svc_MaxWght, Svc_BaseRte, Svc_LeadTm) VALUES 
        ('SVC00001', 'Standard Delivery', 20, 85,  '3-5 Days'),
        ('SVC00002', 'Express Delivery',  20, 120, '1-2 Days'),
        ('SVC00003', 'Same-Day Delivery',  5, 150, 'Same Day'),
        ('SVC00004', 'Next-Day Delivery', 20, 100, 'Before 6PM'),
        ('SVC00005', 'COD Standard',      20, 85,  '3-5 Days')
    ");
}
$services = $conn->query("SELECT * FROM SERVICE_TYPE");

// Helper function to generate IDs
function generateId($conn, $prefix, $table, $column) {
    $res = $conn->query("SELECT $column FROM $table ORDER BY $column DESC LIMIT 1");
    if($res && $res->num_rows > 0) {
        $lastId = $res->fetch_assoc()[$column];
        $num = (int)substr($lastId, strlen($prefix));
        return $prefix . str_pad($num + 1, 5, '0', STR_PAD_LEFT);
    }
    return $prefix . '00001';
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Recipient Info
    $rcptName  = $conn->real_escape_string(trim($_POST['rcpt_name']));
    $rcptPhone = $conn->real_escape_string(trim($_POST['rcpt_phone']));
    $rcptAddr  = $conn->real_escape_string(trim($_POST['rcpt_addr']));
    $rcptArea  = $conn->real_escape_string(trim($_POST['rcpt_area']));

    // 2. Parcel Details
    $weight    = (float)$_POST['weight'];
    $declVal   = (float)$_POST['decl_val'];
    $isCOD     = isset($_POST['is_cod']) ? 'Yes' : 'No';
    $codAmt    = $isCOD === 'Yes' ? (float)$_POST['cod_amt'] : 0;
    
    // 3. Service & Pickup
    $svcId     = $conn->real_escape_string($_POST['service_id']);
    $pickPref  = $conn->real_escape_string($_POST['pick_pref']);
    $pickDt    = $pickPref === 'Scheduled Pickup' ? $conn->real_escape_string($_POST['pick_dt']) : NULL;
    $pickAddr  = $pickPref === 'Scheduled Pickup' ? $conn->real_escape_string(trim($_POST['pick_addr'])) : NULL;

    // Check prohibited items
    if(!isset($_POST['terms_agreed'])) {
        $error = "You must confirm that the parcel contains no prohibited items.";
    }

    if(!$error) {
        // Validation: Weight Limits
        $svcData = $conn->query("SELECT * FROM SERVICE_TYPE WHERE Svc_ID='$svcId'")->fetch_assoc();
        if($weight > $svcData['Svc_MaxWght']) {
            $error = "Weight exceeds maximum limit for " . $svcData['Svc_Name'] . " delivery (Max: " . $svcData['Svc_MaxWght'] . "kg).";
        }
    }

    if(!$error) {
        $conn->begin_transaction();
        try {
            // RECIPIENT
            $rcptId = generateId($conn, 'RCPT', 'RECIPIENT', 'Rcpt_ID');
            $conn->query("INSERT INTO RECIPIENT (Rcpt_ID, Rcpt_Name, Rcpt_Phone, Rcpt_Addr, Rcpt_Area) 
                          VALUES ('$rcptId', '$rcptName', '$rcptPhone', '$rcptAddr', '$rcptArea')");

            // PARCEL
            $pclId = generateId($conn, 'PCL', 'PARCEL', 'Pcl_ID');
            $dateNow = date('Y-m-d H:i:s');
            $conn->query("INSERT INTO PARCEL (Pcl_ID, Pcl_ShprID, Pcl_RcptID, Pcl_Wght, Pcl_DeclVal, Pcl_IsCOD, Pcl_CODAmt, Pcl_BookDt, Pcl_ProbFlg) 
                          VALUES ('$pclId', '$shipperId', '$rcptId', $weight, $declVal, '$isCOD', $codAmt, '$dateNow', 0)");

            // ORDER
            $ordId = generateId($conn, 'ORD', '`ORDER`', 'Ord_ID');
            $pickDtSql = $pickDt ? "'$pickDt'" : "NULL";
            $pickAddrSql = $pickAddr ? "'$pickAddr'" : "NULL";
            $conn->query("INSERT INTO `ORDER` (Ord_ID, Ord_PclID, Ord_SvcID, Ord_ShprID, Ord_Status, Ord_PickPref, Ord_PickDt, Ord_PickAddr, Ord_CrtdDt) 
                          VALUES ('$ordId', '$pclId', '$svcId', '$shipperId', 'Staging', '$pickPref', $pickDtSql, $pickAddrSql, '$dateNow')");

            // AIRWAY_BILL
            $awbId = generateId($conn, 'AWB', 'AIRWAY_BILL', 'AWB_ID');
            $trkNum = 'NVPH' . strtoupper(substr(md5(uniqid()), 0, 8)); // Generate tracking number
            $conn->query("INSERT INTO AIRWAY_BILL (AWB_ID, AWB_OrdID, AWB_TrkNum, AWB_PrtDt, AWB_PrtFmt, AWB_BrCode) 
                          VALUES ('$awbId', '$ordId', '$trkNum', '$dateNow', 'Thermal', '$trkNum')");

            // SHIPPING_FEE Calculation
            $feeBase = $svcData['Svc_BaseRte'];
            $feeInsur = 0;
            if($declVal > 5000) {
                $feeInsur = $declVal * 0.02; // 2% for value above automatic 5000 cover. Simplified rule: 2% of total if they want additional, but documentation says "covers up to 5000, additional is 2% of declared value". Let's apply 2% if > 5000.
            }
            $feeCodHdl = $isCOD === 'Yes' ? ($codAmt * 0.02) : 0; // 2% handling fee
            $feeTotal = $feeBase + $feeInsur + $feeCodHdl;

            $feeId = generateId($conn, 'FEE', 'SHIPPING_FEE', 'Fee_ID');
            $conn->query("INSERT INTO SHIPPING_FEE (Fee_ID, Fee_OrdID, Fee_Base, Fee_Insur, Fee_Rerte, Fee_Store, Fee_CODHdl, Fee_Total) 
                          VALUES ('$feeId', '$ordId', $feeBase, $feeInsur, 0, 0, $feeCodHdl, $feeTotal)");

            $conn->commit();
            $_SESSION['toast_success'] = "Parcel booked successfully! Tracking No: " . $trkNum;
            header("Location: /ninjavan/shipper/my_orders.php"); exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Booking failed: " . $e->getMessage();
        }
    }
}

include "../layout/dashboard_layout.php";
?>

<div class="page-header">
    <div>
        <h1>Book a Parcel</h1>
        <p>Create a new shipment request</p>
    </div>
</div>

<?php if($error): ?>
<div class="nv-toast error position-relative mb-4" style="animation:none; bottom:auto; right:auto; transform:none; opacity:1;">
    <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<form method="POST" class="row g-4" id="bookingForm">
    
    <!-- LEFT COLUMN: Recipient & Parcel -->
    <div class="col-lg-7">
        
        <!-- Recipient Info -->
        <div class="nv-card p-4 mb-4">
            <h5 style="font-size:16px; margin-bottom:20px; color:var(--red);"><i class="bi bi-person-bounding-box me-2"></i>Recipient Information</h5>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Full Name</label>
                        <input type="text" name="rcpt_name" class="nv-input" required placeholder="Juan Dela Cruz">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Phone Number</label>
                        <input type="text" name="rcpt_phone" class="nv-input" required placeholder="09xxxxxxxxx">
                    </div>
                </div>
                <div class="col-12">
                    <div class="nv-form-group">
                        <label>Complete Address</label>
                        <input type="text" name="rcpt_addr" class="nv-input" required placeholder="House No., Street, Barangay, City/Municipality, Province">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Area</label>
                        <select name="rcpt_area" class="nv-input" required>
                            <option value="">Select Area</option>
                            <option value="Metro Manila">Metro Manila</option>
                            <option value="Luzon">Luzon (Provincial)</option>
                            <option value="Visayas">Visayas</option>
                            <option value="Mindanao">Mindanao</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Parcel Details -->
        <div class="nv-card p-4 mb-4">
            <h5 style="font-size:16px; margin-bottom:20px; color:var(--red);"><i class="bi bi-box-seam me-2"></i>Parcel Details</h5>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Weight (kg)</label>
                        <input type="number" step="0.1" min="0.1" name="weight" id="parcelWeight" class="nv-input" required placeholder="e.g. 1.5">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Declared Value (₱)</label>
                        <input type="number" step="1" min="0" name="decl_val" id="parcelVal" class="nv-input" required placeholder="For insurance purposes">
                    </div>
                </div>
                
                <div class="col-12 mt-4">
                    <div class="form-check form-switch" style="padding-left:3rem;">
                        <input class="form-check-input" type="checkbox" role="switch" id="codSwitch" name="is_cod" style="width:40px; height:20px; margin-left:-3rem; cursor:pointer;">
                        <label class="form-check-label" for="codSwitch" style="font-size:14px; font-weight:700; color:var(--ink); cursor:pointer;">Cash on Delivery (COD)</label>
                    </div>
                </div>
                
                <div class="col-md-6" id="codAmtContainer" style="display:none;">
                    <div class="nv-form-group mt-2">
                        <label>COD Amount to Collect (₱)</label>
                        <input type="number" step="1" min="1" name="cod_amt" id="codAmt" class="nv-input" placeholder="Amount rider will collect">
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- RIGHT COLUMN: Service, Pickup & Summary -->
    <div class="col-lg-5">
        
        <!-- Service Type -->
        <div class="nv-card p-4 mb-4">
            <h5 style="font-size:16px; margin-bottom:20px; color:var(--red);"><i class="bi bi-truck me-2"></i>Delivery Service</h5>
            
            <div class="nv-form-group">
                <label>Select Service</label>
                <select name="service_id" id="serviceType" class="nv-input" required>
                    <option value="">Choose a service...</option>
                    <?php while($s = $services->fetch_assoc()): ?>
                    <option value="<?= $s['Svc_ID'] ?>" 
                            data-base="<?= $s['Svc_BaseRte'] ?>" 
                            data-max="<?= $s['Svc_MaxWght'] ?>">
                        <?= $s['Svc_Name'] ?> — Base: ₱<?= $s['Svc_BaseRte'] ?> (Max <?= $s['Svc_MaxWght'] ?>kg)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <!-- Pickup Details -->
        <div class="nv-card p-4 mb-4">
            <h5 style="font-size:16px; margin-bottom:20px; color:var(--red);"><i class="bi bi-geo-alt me-2"></i>Pickup Preferences</h5>
            
            <div class="nv-form-group">
                <label>Preference</label>
                <select name="pick_pref" id="pickPref" class="nv-input" required>
                    <option value="Scheduled Pickup">Scheduled Pickup</option>
                    <option value="Dropoff">Dropoff at Hub/Service Point</option>
                </select>
            </div>

            <div id="pickupDetails">
                <div class="nv-form-group mt-3">
                    <label>Pickup Date & Time</label>
                    <input type="datetime-local" name="pick_dt" class="nv-input" required>
                </div>
                <div class="nv-form-group mt-3">
                    <label>Pickup Address</label>
                    <textarea name="pick_addr" class="nv-input" rows="2" required><?= htmlspecialchars($defaultAddress) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Summary & Terms -->
        <div class="nv-card p-4">
            <h5 style="font-size:16px; margin-bottom:20px;">Fee Estimation</h5>
            
            <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:13px;">
                <span style="color:var(--muted);">Base Fee:</span>
                <span style="font-weight:700;" id="estBase">₱0.00</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:13px;">
                <span style="color:var(--muted);">Insurance (if >₱5,000):</span>
                <span style="font-weight:700;" id="estInsur">₱0.00</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:16px; font-size:13px;" id="estCodRow">
                <span style="color:var(--muted);">COD Handling (2%):</span>
                <span style="font-weight:700;" id="estCod">₱0.00</span>
            </div>
            <div style="display:flex; justify-content:space-between; padding-top:12px; border-top:1px solid var(--border); font-size:16px; font-family:'Sora',sans-serif; font-weight:800;">
                <span>Estimated Total:</span>
                <span style="color:var(--red);" id="estTotal">₱0.00</span>
            </div>

            <div class="mt-4 mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="terms_agreed" id="termsCheck" required style="cursor:pointer;">
                    <label class="form-check-label" for="termsCheck" style="font-size:12px; color:var(--muted); line-height:1.5;">
                        I confirm that this parcel does NOT contain prohibited items (perishables, hazardous materials, illegal items, live animals, cash, or jewelry valued above ₱50,000).
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-nv w-100" style="justify-content:center; padding:12px; font-size:15px;">Book Parcel Now</button>
        </div>

    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const codSwitch = document.getElementById('codSwitch');
    const codAmtContainer = document.getElementById('codAmtContainer');
    const codAmtInput = document.getElementById('codAmt');
    
    const pickPref = document.getElementById('pickPref');
    const pickupDetails = document.getElementById('pickupDetails');
    const pickDtInput = document.querySelector('input[name="pick_dt"]');
    const pickAddrInput = document.querySelector('textarea[name="pick_addr"]');

    const serviceSelect = document.getElementById('serviceType');
    const declValInput = document.getElementById('parcelVal');
    
    // Toggle COD input
    codSwitch.addEventListener('change', function() {
        if(this.checked) {
            codAmtContainer.style.display = 'block';
            codAmtInput.required = true;
        } else {
            codAmtContainer.style.display = 'none';
            codAmtInput.required = false;
            codAmtInput.value = '';
        }
        calcFees();
    });

    // Toggle Pickup fields
    pickPref.addEventListener('change', function() {
        if(this.value === 'Scheduled Pickup') {
            pickupDetails.style.display = 'block';
            pickDtInput.required = true;
            pickAddrInput.required = true;
        } else {
            pickupDetails.style.display = 'none';
            pickDtInput.required = false;
            pickAddrInput.required = false;
        }
    });

    // Fee Calculation
    function calcFees() {
        let base = 0;
        let insur = 0;
        let codHdl = 0;

        // Base
        const opt = serviceSelect.options[serviceSelect.selectedIndex];
        if(opt && opt.value !== '') {
            base = parseFloat(opt.getAttribute('data-base'));
        }

        // Insurance
        const val = parseFloat(declValInput.value) || 0;
        if(val > 5000) {
            insur = val * 0.02;
        }

        // COD
        if(codSwitch.checked) {
            const camt = parseFloat(codAmtInput.value) || 0;
            codHdl = camt * 0.02;
        }

        const total = base + insur + codHdl;

        document.getElementById('estBase').textContent = '₱' + base.toFixed(2);
        document.getElementById('estInsur').textContent = '₱' + insur.toFixed(2);
        document.getElementById('estCod').textContent = '₱' + codHdl.toFixed(2);
        document.getElementById('estTotal').textContent = '₱' + total.toFixed(2);
    }

    serviceSelect.addEventListener('change', calcFees);
    declValInput.addEventListener('input', calcFees);
    codAmtInput.addEventListener('input', calcFees);
    codSwitch.addEventListener('change', calcFees);
});
</script>

<?php include "../layout/dashboard_footer.php"; ?>
