<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'staff'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Walk-in Booking";
$activePage = "walkin";
$hubId      = $_SESSION['hub_id'];
$staffId    = $_SESSION['staff_id'];
$error      = "";

// Get hub info
$hub = $conn->query("SELECT * FROM HUB WHERE Hub_ID='$hubId'")->fetch_assoc();

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
    // Sender info
    $senderName  = $conn->real_escape_string(trim($_POST['sender_name']));
    $senderPhone = $conn->real_escape_string(trim($_POST['sender_phone']));
    $senderEmail = $conn->real_escape_string(trim($_POST['sender_email']));

    // Recipient
    $rcptName  = $conn->real_escape_string(trim($_POST['rcpt_name']));
    $rcptPhone = $conn->real_escape_string(trim($_POST['rcpt_phone']));
    $rcptAddr  = $conn->real_escape_string(trim($_POST['rcpt_addr']));
    $rcptArea  = $conn->real_escape_string(trim($_POST['rcpt_area']));

    // Parcel
    $weight  = (float)$_POST['weight'];
    $declVal = (float)$_POST['decl_val'];
    $isCOD   = isset($_POST['is_cod']) ? 'Yes' : 'No';
    $codAmt  = $isCOD === 'Yes' ? (float)$_POST['cod_amt'] : 0;
    $svcId   = $conn->real_escape_string($_POST['service_id']);

    if(empty($senderName) || empty($rcptName) || empty($rcptAddr)) {
        $error = "Please fill in all required fields.";
    }

    if(!$error) {
        $conn->begin_transaction();
        try {
            // Check or create shipper account for walk-in
            $existUser = $conn->query("SELECT u.Usr_ID, sh.Shpr_ID FROM USER_ACCOUNT u LEFT JOIN SHIPPER sh ON sh.Shpr_UsrID=u.Usr_ID WHERE u.Usr_Email='$senderEmail' LIMIT 1");
            if($existUser && $existUser->num_rows > 0) {
                $eu = $existUser->fetch_assoc();
                $shprId = $eu['Shpr_ID'];
                if(!$shprId) {
                    $shprId = 'SHP' . strtoupper(substr(md5(uniqid()), 0, 5));
                    $conn->query("INSERT INTO SHIPPER (Shpr_ID, Shpr_UsrID, Shpr_BizName, Shpr_PickAddr) VALUES ('$shprId', '{$eu['Usr_ID']}', '$senderName', '{$hub['Hub_Addr']}')");
                }
            } else {
                $usrId = 'USR' . strtoupper(substr(md5(uniqid()), 0, 5));
                $hash = password_hash('ninja123', PASSWORD_DEFAULT);
                $conn->query("INSERT INTO USER_ACCOUNT (Usr_ID, Usr_Email, Usr_Pass, Usr_Name, Usr_Phone, Usr_Type, Usr_Status, Usr_DateReg) VALUES ('$usrId', '$senderEmail', '$hash', '$senderName', '$senderPhone', 'shipper', 'Active', NOW())");
                $shprId = 'SHP' . strtoupper(substr(md5(uniqid()), 0, 5));
                $conn->query("INSERT INTO SHIPPER (Shpr_ID, Shpr_UsrID, Shpr_BizName, Shpr_PickAddr) VALUES ('$shprId', '$usrId', '$senderName', '{$hub['Hub_Addr']}')");
            }

            // Recipient
            $rcptId = generateId($conn, 'RCPT', 'RECIPIENT', 'Rcpt_ID');
            $conn->query("INSERT INTO RECIPIENT (Rcpt_ID, Rcpt_Name, Rcpt_Phone, Rcpt_Addr, Rcpt_Area) VALUES ('$rcptId', '$rcptName', '$rcptPhone', '$rcptAddr', '$rcptArea')");

            // Parcel
            $pclId = generateId($conn, 'PCL', 'PARCEL', 'Pcl_ID');
            $conn->query("INSERT INTO PARCEL (Pcl_ID, Pcl_ShprID, Pcl_RcptID, Pcl_Wght, Pcl_DeclVal, Pcl_IsCOD, Pcl_CODAmt, Pcl_BookDt, Pcl_ProbFlg) VALUES ('$pclId', '$shprId', '$rcptId', $weight, $declVal, '$isCOD', $codAmt, NOW(), 0)");

            // Order - Walk-in is Dropoff, so starts as Staging
            $ordId = generateId($conn, 'ORD', '`ORDER`', 'Ord_ID');
            $conn->query("INSERT INTO `ORDER` (Ord_ID, Ord_PclID, Ord_SvcID, Ord_ShprID, Ord_Status, Ord_PickPref, Ord_CrtdDt) VALUES ('$ordId', '$pclId', '$svcId', '$shprId', 'Staging', 'Dropoff', NOW())");

            // Airway Bill
            $awbId = generateId($conn, 'AWB', 'AIRWAY_BILL', 'AWB_ID');
            $trkNum = 'NVPH' . strtoupper(substr(md5(uniqid()), 0, 8));
            $conn->query("INSERT INTO AIRWAY_BILL (AWB_ID, AWB_OrdID, AWB_TrkNum, AWB_PrtDt, AWB_PrtFmt, AWB_BrCode) VALUES ('$awbId', '$ordId', '$trkNum', NOW(), 'Thermal', '$trkNum')");

            // Shipping Fee
            $svcData = $conn->query("SELECT * FROM SERVICE_TYPE WHERE Svc_ID='$svcId'")->fetch_assoc();
            $feeBase = $svcData['Svc_BaseRte'];
            $feeInsur = $declVal > 5000 ? $declVal * 0.02 : 0;
            $feeCod = $isCOD === 'Yes' ? $codAmt * 0.02 : 0;
            $feeTotal = $feeBase + $feeInsur + $feeCod;
            $feeId = generateId($conn, 'FEE', 'SHIPPING_FEE', 'Fee_ID');
            $conn->query("INSERT INTO SHIPPING_FEE (Fee_ID, Fee_OrdID, Fee_Base, Fee_Insur, Fee_Rerte, Fee_Store, Fee_CODHdl, Fee_Total) VALUES ('$feeId', '$ordId', $feeBase, $feeInsur, 0, 0, $feeCod, $feeTotal)");

            $conn->commit();
            $_SESSION['toast_success'] = "Walk-in booked! Tracking: $trkNum";
            header("Location: /ninjavan/staff/dashboard.php"); exit();
        } catch(Exception $e) {
            $conn->rollback();
            $error = "Booking failed: " . $e->getMessage();
        }
    }
}

include "../layout/dashboard_layout.php";
?>

<div class="page-header">
    <div><h1>Walk-in Booking</h1><p>Book a parcel for a walk-in customer at <?= htmlspecialchars($hub['Hub_Name']) ?></p></div>
</div>

<?php if($error): ?>
<div class="nv-toast error position-relative mb-4" style="animation:none;bottom:auto;right:auto;transform:none;opacity:1;">
    <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<form method="POST" class="row g-4">
    <div class="col-lg-7">
        <!-- Sender -->
        <div class="nv-card p-4 mb-4">
            <h5 style="font-size:16px;margin-bottom:20px;color:var(--red);"><i class="bi bi-person-fill me-2"></i>Sender (Walk-in Customer)</h5>
            <div class="row g-3">
                <div class="col-md-6"><div class="nv-form-group"><label>Full Name *</label><input type="text" name="sender_name" class="nv-input" required placeholder="Juan Dela Cruz"></div></div>
                <div class="col-md-6"><div class="nv-form-group"><label>Phone *</label><input type="text" name="sender_phone" class="nv-input" required placeholder="09xxxxxxxxx"></div></div>
                <div class="col-12"><div class="nv-form-group"><label>Email (for account)</label><input type="email" name="sender_email" class="nv-input" placeholder="optional@email.com"></div></div>
            </div>
        </div>

        <!-- Recipient -->
        <div class="nv-card p-4 mb-4">
            <h5 style="font-size:16px;margin-bottom:20px;color:var(--red);"><i class="bi bi-person-bounding-box me-2"></i>Recipient</h5>
            <div class="row g-3">
                <div class="col-md-6"><div class="nv-form-group"><label>Full Name *</label><input type="text" name="rcpt_name" class="nv-input" required></div></div>
                <div class="col-md-6"><div class="nv-form-group"><label>Phone *</label><input type="text" name="rcpt_phone" class="nv-input" required></div></div>
                <div class="col-12"><div class="nv-form-group"><label>Complete Address *</label><input type="text" name="rcpt_addr" class="nv-input" required></div></div>
                <div class="col-md-6"><div class="nv-form-group"><label>Area *</label>
                    <select name="rcpt_area" class="nv-input" required>
                        <option value="">Select Area</option>
                        <option value="Metro Manila">Metro Manila</option><option value="Luzon">Luzon</option>
                        <option value="Visayas">Visayas</option><option value="Mindanao">Mindanao</option>
                    </select>
                </div></div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <!-- Parcel -->
        <div class="nv-card p-4 mb-4">
            <h5 style="font-size:16px;margin-bottom:20px;color:var(--red);"><i class="bi bi-box-seam me-2"></i>Parcel Details</h5>
            <div class="row g-3">
                <div class="col-6"><div class="nv-form-group"><label>Weight (kg) *</label><input type="number" step="0.1" min="0.1" name="weight" class="nv-input" required></div></div>
                <div class="col-6"><div class="nv-form-group"><label>Declared Value (₱)</label><input type="number" min="0" name="decl_val" class="nv-input" value="0"></div></div>
                <div class="col-12">
                    <div class="form-check form-switch" style="padding-left:3rem;">
                        <input class="form-check-input" type="checkbox" id="codSw" name="is_cod" style="width:40px;height:20px;margin-left:-3rem;cursor:pointer;">
                        <label class="form-check-label" for="codSw" style="font-size:14px;font-weight:700;">COD</label>
                    </div>
                </div>
                <div class="col-12" id="codBox" style="display:none;">
                    <div class="nv-form-group"><label>COD Amount (₱)</label><input type="number" min="1" name="cod_amt" id="codAmt" class="nv-input"></div>
                </div>
            </div>
        </div>

        <!-- Service -->
        <div class="nv-card p-4 mb-4">
            <h5 style="font-size:16px;margin-bottom:20px;color:var(--red);"><i class="bi bi-truck me-2"></i>Service</h5>
            <div class="nv-form-group"><label>Select Service *</label>
                <select name="service_id" class="nv-input" required>
                    <option value="">Choose...</option>
                    <?php while($s = $services->fetch_assoc()): ?>
                    <option value="<?= $s['Svc_ID'] ?>"><?= $s['Svc_Name'] ?> — ₱<?= $s['Svc_BaseRte'] ?> (Max <?= $s['Svc_MaxWght'] ?>kg)</option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <button type="submit" class="btn-nv w-100" style="justify-content:center;padding:14px;font-size:15px;">
            <i class="bi bi-check2-circle"></i> Book Walk-in Parcel
        </button>
    </div>
</form>

<script>
document.getElementById('codSw').addEventListener('change', function(){
    document.getElementById('codBox').style.display = this.checked ? 'block' : 'none';
    document.getElementById('codAmt').required = this.checked;
});
</script>

<?php include "../layout/dashboard_footer.php"; ?>
