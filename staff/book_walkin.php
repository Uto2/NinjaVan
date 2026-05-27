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
$hubSnapshot = $db->getReference('hubs/' . $hubId)->getSnapshot();
$hub = $hubSnapshot->getValue() ?? ['Hub_Name' => 'Unknown Hub', 'Hub_Addr' => '—'];

// Ensure SERVICE_TYPE has data in Firebase
$servicesRef = $db->getReference('services');
$servicesSnapshot = $servicesRef->getSnapshot();
if (!$servicesSnapshot->hasChildren()) {
    $servicesRef->set([
        'SVC00001' => ['Svc_ID' => 'SVC00001', 'Svc_Name' => 'Standard Delivery', 'Svc_MaxWght' => 20, 'Svc_BaseRte' => 85, 'Svc_LeadTm' => '3-5 Days'],
        'SVC00002' => ['Svc_ID' => 'SVC00002', 'Svc_Name' => 'Express Delivery', 'Svc_MaxWght' => 20, 'Svc_BaseRte' => 120, 'Svc_LeadTm' => '1-2 Days'],
        'SVC00003' => ['Svc_ID' => 'SVC00003', 'Svc_Name' => 'Same-Day Delivery', 'Svc_MaxWght' => 5, 'Svc_BaseRte' => 150, 'Svc_LeadTm' => 'Same Day'],
        'SVC00004' => ['Svc_ID' => 'SVC00004', 'Svc_Name' => 'Next-Day Delivery', 'Svc_MaxWght' => 20, 'Svc_BaseRte' => 100, 'Svc_LeadTm' => 'Before 6PM'],
        'SVC00005' => ['Svc_ID' => 'SVC00005', 'Svc_Name' => 'COD Standard', 'Svc_MaxWght' => 20, 'Svc_BaseRte' => 85, 'Svc_LeadTm' => '3-5 Days']
    ]);
    $servicesSnapshot = $servicesRef->getSnapshot();
}
$services = $servicesSnapshot->getValue() ?? [];

function generateId($prefix) {
    return $prefix . strtoupper(substr(uniqid(), -6));
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    // Sender info
    $senderFName = trim($_POST['sender_first_name']);
    $senderLName = trim($_POST['sender_last_name']);
    $senderName  = $senderFName . ' ' . $senderLName;
    $senderPhone = trim($_POST['sender_phone']);
    $senderEmail = trim($_POST['sender_email']);

    // Recipient
    $rcptFName = trim($_POST['rcpt_first_name']);
    $rcptLName = trim($_POST['rcpt_last_name']);
    $rcptName  = $rcptFName . ' ' . $rcptLName;
    $rcptPhone = trim($_POST['rcpt_phone']);
    
    $rcptProv  = trim($_POST['rcpt_province']);
    $rcptCity  = trim($_POST['rcpt_city']);
    $rcptBrgy  = trim($_POST['rcpt_barangay']);
    $rcptZip   = trim($_POST['rcpt_zip'] ?? '');
    $rcptStreet = trim($_POST['rcpt_street']);
    
    $rcptAddr  = $rcptStreet . ', ' . $rcptBrgy . ', ' . $rcptCity . ', ' . $rcptProv . ' ' . $rcptZip;

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

    // Parcel
    $weight  = (float)$_POST['weight'];
    $declVal = (float)$_POST['decl_val'];
    $isCOD   = isset($_POST['is_cod']) ? 'Yes' : 'No';
    $codAmt  = $isCOD === 'Yes' ? (float)$_POST['cod_amt'] : 0;
    $svcId   = trim($_POST['service_id']);

    if(empty($senderFName) || empty($senderLName) || empty($rcptFName) || empty($rcptLName) || empty($rcptProv) || empty($rcptCity) || empty($rcptBrgy)) {
        $error = "Please fill in all required fields.";
    } elseif (!preg_match('/^[0-9]{10,11}$/', $senderPhone)) {
        $error = "Invalid sender phone number. Must be 10-11 digits.";
    } elseif (!preg_match('/^[0-9]{10,11}$/', $rcptPhone)) {
        $error = "Invalid recipient phone number. Must be 10-11 digits.";
    }

    if(!$error) {
        try {
            $dateNow = date('c');

            // Find or create shipper
            $shprId = '';
            if ($senderEmail) {
                // Find user by email in RTDB
                $usersSnapshot = $db->getReference('users')->orderByChild('Usr_Email')->equalTo($senderEmail)->getSnapshot();
                if ($usersSnapshot->hasChildren()) {
                    $usersData = $usersSnapshot->getValue();
                    $usr = reset($usersData);
                    $shprId = $usr['Usr_ID'];
                } else {
                    $authProps = [
                        'email' => $senderEmail,
                        'emailVerified' => false,
                        'password' => 'ninja123',
                        'displayName' => $senderName,
                        'disabled' => false,
                    ];
                    $createdUser = $auth->createUser($authProps);
                    $shprId = $createdUser->uid;
                    $db->getReference('users/' . $shprId)->set([
                        'Usr_ID' => $shprId,
                        'Usr_Email' => $senderEmail,
                        'Usr_Name' => $senderName,
                        'Usr_Phone' => $senderPhone,
                        'Usr_Type' => 'shipper',
                        'Usr_Status' => 'Active',
                        'Shpr_BizName' => $senderName,
                        'Shpr_PickAddr' => $hub['Hub_Addr']
                    ]);
                }
            } else {
                $shprId = generateId('SHP-');
            }

            // Shipping Fee
            $svcData = $services[$svcId] ?? null;
            $feeBase = $svcData ? (float)$svcData['Svc_BaseRte'] : 85;
            $feeInsur = $declVal > 5000 ? $declVal * 0.02 : 0;
            $feeCod = $isCOD === 'Yes' ? $codAmt * 0.02 : 0;
            $feeTotal = $feeBase + $feeInsur + $feeCod;
            
            $ordId  = generateId('ORD-');
            $rcptId = generateId('RCPT-');
            $pclId  = generateId('PCL-');
            $awbId  = generateId('AWB-');
            $feeId  = generateId('FEE-');
            $trkNum = 'NVPH' . strtoupper(bin2hex(random_bytes(4)));

            $orderData = [
                'Ord_ID' => $ordId,
                'Ord_PclID' => $pclId,
                'Ord_SvcID' => $svcId,
                'Ord_ShprID' => $shprId,
                'Ord_Status' => 'Order Created',
                'Ord_PickPref' => 'Dropoff',
                'Ord_PickAddr' => $hub['Hub_Addr'],
                'Ord_CrtdDt' => $dateNow,
                
                'recipient' => [
                    'Rcpt_ID' => $rcptId,
                    'Rcpt_Name' => $rcptName,
                    'Rcpt_Phone' => $rcptPhone,
                    'Rcpt_Addr' => $rcptAddr,
                    'Rcpt_Area' => $rcptArea
                ],
                'parcel' => [
                    'Pcl_ID' => $pclId,
                    'Pcl_Wght' => $weight,
                    'Pcl_DeclVal' => $declVal,
                    'Pcl_IsCOD' => $isCOD,
                    'Pcl_CODAmt' => $codAmt,
                    'Pcl_BookDt' => $dateNow,
                    'Pcl_ProbFlg' => 0
                ],
                'service' => $svcData,
                'awb' => [
                    'AWB_ID' => $awbId,
                    'AWB_TrkNum' => $trkNum,
                    'AWB_PrtDt' => $dateNow,
                    'AWB_PrtFmt' => 'Thermal',
                    'AWB_BrCode' => $trkNum
                ],
                'fee' => [
                    'Fee_ID' => $feeId,
                    'Fee_Base' => $feeBase,
                    'Fee_Insur' => $feeInsur,
                    'Fee_Rerte' => 0,
                    'Fee_Store' => 0,
                    'Fee_CODHdl' => $feeCod,
                    'Fee_Total' => $feeTotal
                ]
            ];

            // Push order to RTDB
            $db->getReference('orders/' . $ordId)->set($orderData);

            $_SESSION['toast_success'] = "Walk-in booked! Tracking: $trkNum";
            header("Location: /ninjavan/staff/dashboard.php"); exit();
        } catch(Exception $e) {
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
    <?= csrf_field() ?>
    <div class="col-lg-7">
        <!-- Sender -->
        <div class="nv-card p-4 mb-4">
            <h5 style="font-size:16px;margin-bottom:20px;color:var(--red);"><i class="bi bi-person-fill me-2"></i>Sender (Walk-in Customer)</h5>
            <div class="row g-3">
                <div class="col-md-6"><div class="nv-form-group"><label>First Name *</label><input type="text" name="sender_first_name" class="nv-input" required placeholder="e.g. Juan"></div></div>
                <div class="col-md-6"><div class="nv-form-group"><label>Last Name *</label><input type="text" name="sender_last_name" class="nv-input" required placeholder="e.g. Dela Cruz"></div></div>
                <div class="col-md-6"><div class="nv-form-group"><label>Phone *</label><input type="tel" name="sender_phone" class="nv-input" required placeholder="09xxxxxxxxx" maxlength="11" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')"></div></div>
                <div class="col-12"><div class="nv-form-group"><label>Email (for account)</label><input type="email" name="sender_email" class="nv-input" placeholder="optional@email.com"></div></div>
            </div>
        </div>

        <!-- Recipient -->
        <div class="nv-card p-4 mb-4">
            <h5 style="font-size:16px;margin-bottom:20px;color:var(--red);"><i class="bi bi-person-bounding-box me-2"></i>Recipient</h5>
            <div class="row g-3">
                <div class="col-md-6"><div class="nv-form-group"><label>First Name *</label><input type="text" name="rcpt_first_name" class="nv-input" required placeholder="e.g. Maria"></div></div>
                <div class="col-md-6"><div class="nv-form-group"><label>Last Name *</label><input type="text" name="rcpt_last_name" class="nv-input" required placeholder="e.g. Santos"></div></div>
                <div class="col-md-6"><div class="nv-form-group"><label>Phone *</label><input type="tel" name="rcpt_phone" class="nv-input" required placeholder="09xxxxxxxxx" maxlength="11" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')"></div></div>
                
                <div class="col-md-8">
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
                <div class="col-md-4">
                    <div class="nv-form-group">
                        <label>ZIP Code</label>
                        <input type="text" name="rcpt_zip" id="bookZip" class="nv-input" placeholder="e.g. 6000" maxlength="4">
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
                        <label>Barangay *</label>
                        <select name="rcpt_barangay" id="rcptBarangay" class="nv-input" required>
                            <option value="">Select Barangay</option>
                        </select>
                    </div>
                </div>
                <div class="col-12">
                    <div class="nv-form-group">
                        <label>Street / Building / House No. *</label>
                        <input type="text" name="rcpt_street" class="nv-input" required placeholder="e.g. 123 Main St, Block 4 Lot 5">
                    </div>
                </div>
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
                    <?php foreach($services as $sId => $s): ?>
                    <option value="<?= $s['Svc_ID'] ?>"><?= htmlspecialchars($s['Svc_Name']) ?> — ₱<?= $s['Svc_BaseRte'] ?> (Max <?= $s['Svc_MaxWght'] ?>kg)</option>
                    <?php endforeach; ?>
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

document.addEventListener('DOMContentLoaded', function() {
    setupPhAddressDropdowns('rcptProvince', 'rcptCity', 'rcptBarangay');
});
</script>

<?php include "../layout/dashboard_footer.php"; ?>
