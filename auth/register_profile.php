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
    $city     = trim($_POST['city']);
    $province = trim($_POST['province']);
    $zip      = trim($_POST['zip_code']);

    if(empty($address) || empty($city) || empty($province)){
        $error = "Please fill in all required fields.";
    }
    else {
        // Build full address string
        $fullAddress = $address . ', ' . $city . ', ' . $province
                     . ($zip ? ' ' . $zip : '');

        // Generate IDs
        $usrId = 'USR' . strtoupper(substr(md5(uniqid()), 0, 5));
        $shprId = 'SHP' . strtoupper(substr(md5(uniqid()), 0, 5));

        $hashed = password_hash($_SESSION['reg_password'], PASSWORD_DEFAULT);

        // Insert account
        $name = $_SESSION['reg_fname'] . ' ' . $_SESSION['reg_lname'];
        $stmt1 = $conn->prepare("
            INSERT INTO USER_ACCOUNT
                (Usr_ID, Usr_Email, Usr_Pass, Usr_Name, Usr_Phone, Usr_Type, Usr_Status, Usr_DateReg)
            VALUES (?, ?, ?, ?, ?, 'shipper', 'Active', NOW())
        ");
        $stmt1->bind_param("sssss", $usrId, $_SESSION['reg_email'], $hashed, $name, $_SESSION['reg_phone']);
        $stmt1->execute();

        // Insert shipper
        $stmt2 = $conn->prepare("
            INSERT INTO SHIPPER
                (Shpr_ID, Shpr_UsrID, Shpr_BizName, Shpr_PickAddr)
            VALUES (?, ?, ?, ?)
        ");
        $stmt2->bind_param(
            "ssss",
            $shprId, $usrId,
            $name,
            $fullAddress
        );
        $stmt2->execute();

        // Clear registration session
        unset(
            $_SESSION['reg_step'],   $_SESSION['reg_fname'],
            $_SESSION['reg_lname'],  $_SESSION['reg_email'],
            $_SESSION['reg_phone'],  $_SESSION['reg_otp'],
            $_SESSION['reg_otp_time'], $_SESSION['reg_password']
        );

        $_SESSION['success_msg'] = "Account created successfully! Welcome to NinjaVan.";
        header("Location: login.php"); exit();
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

        <!-- STREET ADDRESS -->
        <div class="nv-field-group">
            <label>Street / Barangay Address *</label>
            <input type="text" name="address" class="nv-field"
                   placeholder="e.g. 123 Rizal St, Brgy. San Antonio"
                   value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" required>
        </div>

        <!-- CITY -->
        <div class="nv-field-group">
            <label>City / Municipality *</label>
            <input type="text" name="city" class="nv-field"
                   placeholder="e.g. Quezon City"
                   value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" required>
        </div>

        <!-- PROVINCE + ZIP in a row -->
        <div class="row-fields">
            <div class="nv-field-group">
                <label>Province *</label>
                <div class="nv-select-wrap">
                    <select name="province" class="nv-select" required>
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
</script>

</body>
</html>
