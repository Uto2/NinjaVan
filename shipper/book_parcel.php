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
    $rcptFName = $conn->real_escape_string(trim($_POST['rcpt_first_name']));
    $rcptLName = $conn->real_escape_string(trim($_POST['rcpt_last_name']));
    $rcptName  = $rcptFName . ' ' . $rcptLName;
    $rcptPhone = $conn->real_escape_string(trim($_POST['rcpt_phone']));
    
    $rcptProv  = $conn->real_escape_string(trim($_POST['rcpt_province']));
    $rcptCity  = $conn->real_escape_string(trim($_POST['rcpt_city']));
    $rcptBrgy  = $conn->real_escape_string(trim($_POST['rcpt_barangay']));
    $rcptAddr  = $rcptBrgy . ', ' . $rcptCity . ', ' . $rcptProv;

    // Auto-map Province to Area for recipient
    $visayas_provinces = ['Aklan', 'Antique', 'Bohol', 'Capiz', 'Cebu', 'Guimaras', 'Iloilo', 'Leyte', 'Biliran', 'Eastern Samar', 'Northern Samar', 'Samar', 'Southern Leyte', 'Siquijor', 'Negros Oriental', 'Negros Occidental'];
    $mindanao_provinces = ['Agusan del Norte', 'Agusan del Sur', 'Basilan', 'Bukidnon', 'Camiguin', 'Compostela Valley', 'Cotabato', 'Davao de Oro', 'Davao del Norte', 'Davao del Sur', 'Davao Occidental', 'Davao Oriental', 'Dinagat Islands', 'Lanao del Norte', 'Lanao del Sur', 'Maguindanao', 'Misamis Occidental', 'Misamis Oriental', 'Sarangani', 'South Cotabato', 'Sultan Kudarat', 'Sulu', 'Surigao del Norte', 'Surigao del Sur', 'Tawi-Tawi', 'Zamboanga del Norte', 'Zamboanga del Sur', 'Zamboanga Sibugay'];
    $metro_manila = ['Metro Manila'];

    if (in_array($rcptProv, $metro_manila)) {
        $rcptArea = 'Metro Manila';
    } elseif (in_array($rcptProv, $visayas_provinces)) {
        $rcptArea = 'Visayas';
    } elseif (in_array($rcptProv, $mindanao_provinces)) {
        $rcptArea = 'Mindanao';
    } else {
        $rcptArea = 'Luzon';
    }

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
                        <label>First Name *</label>
                        <input type="text" name="rcpt_first_name" class="nv-input" required placeholder="e.g. Juan">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Last Name *</label>
                        <input type="text" name="rcpt_last_name" class="nv-input" required placeholder="e.g. Dela Cruz">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Phone Number *</label>
                        <input type="text" name="rcpt_phone" class="nv-input" required placeholder="09xxxxxxxxx">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Province *</label>
                        <select name="rcpt_province" id="rcptProvince" class="nv-input" required>
                            <option value="">Select Province</option>
                            <?php
                            $provinces = ["Metro Manila", "Abra","Agusan del Norte","Agusan del Sur","Aklan","Albay","Antique","Apayao","Aurora","Basilan","Bataan","Batanes","Batangas","Benguet","Biliran","Bohol","Bukidnon","Bulacan","Cagayan","Camarines Norte","Camarines Sur","Camiguin","Capiz","Catanduanes","Cavite","Cebu","Compostela Valley","Cotabato","Davao de Oro","Davao del Norte","Davao del Sur","Davao Occidental","Davao Oriental","Dinagat Islands","Eastern Samar","Guimaras","Ifugao","Ilocos Norte","Ilocos Sur","Iloilo","Isabela","Kalinga","La Union","Laguna","Lanao del Norte","Lanao del Sur","Leyte","Maguindanao","Marinduque","Masbate","Misamis Occidental","Misamis Oriental","Mountain Province","Negros Occidental","Negros Oriental","Northern Samar","Nueva Ecija","Nueva Vizcaya","Occidental Mindoro","Oriental Mindoro","Palawan","Pampanga","Pangasinan","Quezon","Quirino","Rizal","Romblon","Samar","Sarangani","Siquijor","Sorsogon","South Cotabato","Southern Leyte","Sultan Kudarat","Sulu","Surigao del Norte","Surigao del Sur","Tarlac","Tawi-Tawi","Zambales","Zamboanga del Norte","Zamboanga del Sur","Zamboanga Sibugay"];
                            foreach($provinces as $prov) {
                                echo "<option value=\"$prov\">$prov</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>City / Municipality *</label>
                        <select name="rcpt_city" id="rcptCity" class="nv-input" required>
                            <option value="">Select City/Municipality</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Street / Barangay *</label>
                        <select name="rcpt_barangay" id="rcptBarangay" class="nv-input" required>
                            <option value="">Select Barangay</option>
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

    const phLocations = {
        "Metro Manila": {
            "Makati City": ["Bel-Air", "San Lorenzo", "Urdaneta", "Guadalupe Nuevo", "Pembo", "Rizal", "Poblacion", "Tejeros", "Pio del Pilar"],
            "Quezon City": ["Commonwealth", "Batasan Hills", "San Jose", "Bagong Silangan", "Payatas", "Socorro", "Novaliches", "Diliman"],
            "Taguig City": ["Fort Bonifacio", "Central Signal Village", "Western Bicutan", "Ususan", "Tuktukan", "Pinagsama"],
            "Manila": ["Ermita", "Malate", "Intramuros", "Binondo", "Quiapo", "Sampaloc", "Tondo", "Paco", "Santa Cruz"],
            "Pasig City": ["San Antonio", "Kapitolyo", "Caniogan", "Manggahan", "Rosario", "Bambang"]
        },
        "Cebu": {
            "Cebu City": ["Lahug", "Mabolo", "Guadalupe", "Talamban", "Apas", "Capitol Site", "Banilad", "Pardo", "Tisa"],
            "Mandaue City": ["Tipolo", "Banilad", "Bakilid", "Subangdaku", "Centro", "Looc", "Cabancalan", "Maguikay"],
            "Lapu-Lapu City": ["Maribago", "Mactan", "Pajo", "Basak", "Gun-ob", "Babag", "Punta Engaño"],
            "Consolacion": ["Tugbongan", "Nangka", "Pitogo", "Tayud", "Poblacion Occidental", "Poblacion Oriental"]
        },
        "Davao del Sur": {
            "Davao City": ["Buhangin", "Talomo", "Agdao", "Toril", "Bunawan", "Poblacion", "Matina Crossing", "Ma-a"]
        },
        "Bulacan": {
            "Malolos": ["Sumapang Bata", "Dakila", "San Vicente", "Mojon", "Catmon", "Bagasbas"],
            "Meycauayan": ["Calvario", "Banga", "Saluysoy", "Libtong", "Bancal"]
        },
        "Cavite": {
            "Imus": ["Anabu I-A", "Bucandala I", "Malagasang I-A", "Poblacion", "Toclong"],
            "Dasmariñas": ["Salawag", "Langkaan I", "Sampaloc I", "Paliparan III", "San Jose"]
        },
        "Laguna": {
            "Calamba": ["Canlubang", "Real", "Pansol", "Bucal", "Halang", "Parian"],
            "Santa Rosa": ["Balibago", "Don Jose", "Macabling", "Tagapo", "Market Area", "Sinalhan"]
        },
        "Pangasinan": {
            "Dagupan": ["Tapuac", "Poblacion Oeste", "Puelay", "Caranglaan", "Bonuan Gueset"],
            "Urdaneta": ["Poblacion", "Nancayasan", "Pinmaludpod", "San Vicente"]
        },
        "Pampanga": {
            "San Fernando": ["Dolores", "Sindalan", "Calulut", "San Agustin", "San Jose"],
            "Angeles City": ["Balibago", "Malabanias", "Pandan", "Sto. Cristo", "Cutcut"]
        },
        "Iloilo": {
            "Iloilo City": ["Mandurriao", "Molo", "Jaro", "La Paz", "Arevalo", "City Proper"]
        },
        "Leyte": {
            "Tacloban": ["Poblacion", "San Jose", "Abucay", "Marasbaras", "Utap"]
        },
        "Misamis Oriental": {
            "Cagayan de Oro": ["Carmen", "Balulang", "Kauswagan", "Nazareth", "Macasandig", "Lapasan"]
        },
        "Zamboanga del Sur": {
            "Zamboanga City": ["Pasonanca", "Tetuan", "Guiwan", "Santa Maria", "Poblacion"]
        }
    };

    function setupPhAddressDropdowns(provinceSelectId, citySelectId, barangaySelectId) {
        const provSel = document.getElementById(provinceSelectId);
        const citySel = document.getElementById(citySelectId);
        const brgySel = document.getElementById(barangaySelectId);

        function getCitiesForProvince(prov) {
            if (phLocations[prov]) {
                return Object.keys(phLocations[prov]);
            }
            return [
                prov + " Capital City",
                prov + " Centro",
                "North " + prov,
                "South " + prov,
                "East " + prov,
                "West " + prov
            ];
        }

        function getBarangaysForCity(prov, city) {
            if (phLocations[prov] && phLocations[prov][city]) {
                return phLocations[prov][city];
            }
            return [
                "Poblacion",
                "San Jose",
                "San Antonio",
                "Santa Maria",
                "San Roque",
                "Santo Rosario",
                "Barangay I",
                "Barangay II",
                "Barangay III",
                "Bagong Pag-asa"
            ];
        }

        provSel.addEventListener('change', function() {
            const prov = this.value;
            citySel.innerHTML = '<option value="">Select City/Municipality</option>';
            brgySel.innerHTML = '<option value="">Select Barangay</option>';
            if(!prov) return;

            const cities = getCitiesForProvince(prov);
            cities.forEach(function(c) {
                const opt = document.createElement('option');
                opt.value = c;
                opt.textContent = c;
                citySel.appendChild(opt);
            });
        });

        citySel.addEventListener('change', function() {
            const prov = provSel.value;
            const city = this.value;
            brgySel.innerHTML = '<option value="">Select Barangay</option>';
            if(!city) return;

            const brgys = getBarangaysForCity(prov, city);
            brgys.forEach(function(b) {
                const opt = document.createElement('option');
                opt.value = b;
                opt.textContent = b;
                brgySel.appendChild(opt);
            });
        });
    }

    setupPhAddressDropdowns('rcptProvince', 'rcptCity', 'rcptBarangay');
});
</script>

<?php include "../layout/dashboard_footer.php"; ?>
