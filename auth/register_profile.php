<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['reg_step']) || $_SESSION['reg_step'] < 4){
    header("Location: register.php"); exit();
}

$error = "";

// Philippine provinces list
$provinces = [
    "Metro Manila",
    "Abra","Agusan del Norte","Agusan del Sur","Aklan","Albay",
    "Antique","Apayao","Aurora","Basilan","Bataan","Batanes",
    "Batangas","Benguet","Biliran","Bohol","Bukidnon","Bulacan",
    "Cagayan","Camarines Norte","Camarines Sur","Camiguin","Capiz",
    "Catanduanes","Cavite","Cebu","Compostela Valley","Cotabato",
    "Davao de Oro","Davao del Norte","Davao del Sur","Davao Occidental",
    "Davao Oriental","Dinagat Islands","Eastern Samar","Guimaras",
    "Ifugao","Ilocos Norte","Ilocos Sur","Iloilo","Isabela","Kalinga",
    "La Union","Laguna","Lanao del Norte","Lanao del Sur","Leyte",
    "Maguindanao","Marinduque","Masbate","Misamis Occidental",
    "Misamis Oriental","Mountain Province","Negros Occidental",
    "Negros Oriental","Northern Samar","Nueva Ecija","Nueva Vizcaya",
    "Occidental Mindoro","Oriental Mindoro","Palawan","Pampanga",
    "Pangasinan","Quezon","Quirino","Rizal","Romblon","Samar",
    "Sarangani","Siquijor","Sorsogon","South Cotabato","Southern Leyte",
    "Sultan Kudarat","Sulu","Surigao del Norte","Surigao del Sur",
    "Tarlac","Tawi-Tawi","Zambales","Zamboanga del Norte",
    "Zamboanga del Sur","Zamboanga Sibugay",
];

if(isset($_POST['step4'])){

    $address  = trim($_POST['address']);
    $barangay = trim($_POST['barangay']);
    $city     = trim($_POST['city']);
    $province = trim($_POST['province']);
    $zip      = trim($_POST['zip_code']);

    if(empty($address) || empty($city) || empty($province) || empty($barangay)){
        $error = "Please fill in all required fields.";
    }
    else {
        // Build full address string
        $fullAddress = $address . ', ' . $barangay . ', ' . $city . ', ' . $province
                     . ($zip ? ' ' . $zip : '');

        // Generate shipper ID
        $shprId = 'SHP' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $name = $_SESSION['reg_fname'] . ' ' . $_SESSION['reg_lname'];

        try {
            // Create user in Firebase Auth
            $authProps = [
                'email' => $_SESSION['reg_email'],
                'emailVerified' => true,
                'password' => $_SESSION['reg_password'],
                'displayName' => $name,
            ];
            
            // Append +63 to phone if valid format (assuming 10 digits 9xxxxxxxxx)
            if (strlen($_SESSION['reg_phone']) == 10) {
                $authProps['phoneNumber'] = '+63' . $_SESSION['reg_phone'];
            }

            $createdUser = $auth->createUser($authProps);
            $usrId = $createdUser->uid;

            // Insert into Realtime Database users node
            $db->getReference('users/' . $usrId)->set([
                'Usr_ID' => $usrId,
                'Usr_Name' => $name,
                'Usr_Email' => $_SESSION['reg_email'],
                'Usr_Phone' => $_SESSION['reg_phone'],
                'Usr_Type' => 'shipper',
                'Usr_Status' => 'Active',
                'Usr_DateReg' => date('c'),
                'Shpr_ID' => $shprId,
                'Shpr_BizName' => $name,
                'Shpr_PickAddr' => $fullAddress
            ]);

            // Clear registration session
            unset(
                $_SESSION['reg_step'],   $_SESSION['reg_fname'],
                $_SESSION['reg_lname'],  $_SESSION['reg_email'],
                $_SESSION['reg_phone'],  $_SESSION['reg_otp'],
                $_SESSION['reg_otp_time'], $_SESSION['reg_password']
            );
        } catch (\Exception $e) {
            $error = "Error creating account: " . $e->getMessage();
        }

        if (!$error) {
            $_SESSION['success_msg'] = "Account created successfully! Welcome to NinjaVan.";
            header("Location: login.php"); exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Profile — Step 4 | NinjaVan</title>
    <?php include 'register_head.php'; ?>
</head>
<body>

<?php include 'register_brand.php'; ?>

<section class="form-panel">
<div class="form-inner">

    <?php stepperHtml(4); ?>

    <h2>Complete your profile</h2>
    <p class="subtitle">Just a few more details and you're all set!</p>

    <!-- PROFILE SUMMARY CARD -->
    <div class="profile-card">
        <div class="profile-avatar">
            <i class="bi bi-person-fill"></i>
        </div>
        <div>
            <h5><?= htmlspecialchars($_SESSION['reg_fname'] . ' ' . $_SESSION['reg_lname']) ?></h5>
            <p><?= htmlspecialchars($_SESSION['reg_email']) ?>
               <?php if($_SESSION['reg_phone']): ?>
               &nbsp;·&nbsp; <?= htmlspecialchars($_SESSION['reg_phone']) ?>
               <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if($error): ?>
    <div class="nv-error">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">

        <!-- PROVINCE + ZIP in a row -->
        <div class="row-fields">
            <div class="nv-field-group">
                <label>Province *</label>
                <div class="nv-select-wrap">
                    <select name="province" id="provinceSelect" class="nv-select" required>
                        <option value="">Select province</option>
                        <?php foreach($provinces as $prov): ?>
                        <option value="<?= $prov ?>"
                            <?= (($_POST['province'] ?? '') === $prov) ? 'selected' : '' ?>>
                            <?= $prov ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <i class="bi bi-chevron-down nv-select-icon"></i>
                </div>
            </div>

            <div class="nv-field-group">
                <label>ZIP Code</label>
                <input type="text" name="zip_code" class="nv-field"
                       placeholder="e.g. 1100" maxlength="4"
                       inputmode="numeric"
                       value="<?= htmlspecialchars($_POST['zip_code'] ?? '') ?>">
            </div>
        </div>

        <!-- CITY -->
        <div class="nv-field-group">
            <label>City / Municipality *</label>
            <div class="nv-select-wrap">
                <select name="city" id="citySelect" class="nv-select" required>
                    <option value="">Select city/municipality</option>
                </select>
                <i class="bi bi-chevron-down nv-select-icon"></i>
            </div>
        </div>

        <!-- BARANGAY -->
        <div class="nv-field-group">
            <label>Barangay *</label>
            <div class="nv-select-wrap">
                <select name="barangay" id="barangaySelect" class="nv-select" required>
                    <option value="">Select barangay</option>
                </select>
                <i class="bi bi-chevron-down nv-select-icon"></i>
            </div>
        </div>

        <!-- STREET ADDRESS -->
        <div class="nv-field-group">
            <label>Street / Building / House No. *</label>
            <input type="text" name="address" class="nv-field" required placeholder="e.g. 123 Main St, Block 4 Lot 5">
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4">
            <a href="register_password.php" class="back-link">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <button type="submit" name="step4" class="btn-register">
                Create Account <i class="bi bi-check2-circle ms-1"></i>
            </button>
        </div>

    </form>

</div>
</section>

<script>
// ZIP code: numbers only
document.querySelector('input[name="zip_code"]').addEventListener('input', function(){
    this.value = this.value.replace(/\D/g, '').slice(0, 4);
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
        citySel.innerHTML = '<option value="">Select city/municipality</option>';
        brgySel.innerHTML = '<option value="">Select barangay</option>';
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
        brgySel.innerHTML = '<option value="">Select barangay</option>';
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
    setupPhAddressDropdowns('provinceSelect', 'citySelect', 'barangaySelect');
});
</script>

</body>
</html>
