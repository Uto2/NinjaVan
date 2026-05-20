<?php
session_start();
require_once "../config/db.php";

$error = "";
$success = "";

// Fetch Hubs for the dropdown
$hubs = $conn->query("SELECT * FROM HUB ORDER BY Hub_Name ASC");

if(isset($_POST['register_rider'])){
    $firstName = $conn->real_escape_string(trim($_POST['first_name']));
    $lastName  = $conn->real_escape_string(trim($_POST['last_name']));
    $name      = $firstName . ' ' . $lastName;
    $email     = $conn->real_escape_string(trim($_POST['email']));
    $phone     = $conn->real_escape_string(trim($_POST['phone']));
    $vehicle   = $conn->real_escape_string($_POST['vehicle_type']);
    $hubId     = $conn->real_escape_string($_POST['hub_id']);
    $password  = $_POST['password'];
    $confirm   = $_POST['confirm_password'];

    if(empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($vehicle) || empty($hubId) || empty($password)){
        $error = "Please fill in all required fields.";
    }
    else if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = "Please enter a valid email address.";
    }
    else if($password !== $confirm){
        $error = "Passwords do not match.";
    }
    else {
        // Check email uniqueness
        $check = $conn->query("SELECT Usr_ID FROM USER_ACCOUNT WHERE Usr_Email = '$email' LIMIT 1");
        if($check && $check->num_rows > 0){
            $error = "An account with that email already exists.";
        }
        else {
            $conn->begin_transaction();
            try {
                // Generate IDs
                $usrId = 'USR-' . strtoupper(substr(uniqid(), -6));
                $rdrId = 'RDR-' . strtoupper(substr(uniqid(), -6));
                $hashedPass = password_hash($password, PASSWORD_BCRYPT);

                // Insert into USER_ACCOUNT
                $insUser = $conn->query("INSERT INTO USER_ACCOUNT (Usr_ID, Usr_Name, Usr_Email, Usr_Pass, Usr_Phone, Usr_Type, Usr_Status) 
                                         VALUES ('$usrId', '$name', '$email', '$hashedPass', '$phone', 'rider', 'Active')");

                // Insert into RIDER
                $insRider = $conn->query("INSERT INTO RIDER (Rdr_ID, Rdr_UsrID, Rdr_HubID, Rdr_Name, Rdr_Phone, Rdr_VhcTyp, Rdr_Status) 
                                          VALUES ('$rdrId', '$usrId', '$hubId', '$name', '$phone', '$vehicle', 'Active')");

                if ($insUser && $insRider) {
                    $conn->commit();
                    $_SESSION['success_msg'] = "Rider account created successfully! Please log in below.";
                    header("Location: login.php"); exit();
                } else {
                    throw new Exception("Database insert failed.");
                }
            } catch(Exception $e) {
                $conn->rollback();
                $error = "Failed to create rider account: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Rider | NinjaVan</title>
    <?php include 'register_head.php'; ?>
</head>
<body>

<aside class="brand-panel">
    <div class="brand-panel-bg"></div>
    <div class="brand-panel-grid"></div>

    <a href="/ninjavan/index.php" class="brand-logo">ninja<span>van</span></a>

    <div class="brand-content">
        <h1>Become a<br>Ninja Rider</h1>
        <p>Drive with Ninja Van. Start earning with a flexible schedule, competitive rates, and reliable support.</p>
    </div>

    <!-- Decorative palm SVG -->
    <svg class="position-absolute bottom-0 start-0 w-100"
         style="height:55%; opacity:0.15; pointer-events:none;"
         viewBox="0 0 800 600" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
        <path d="M0 600 Q200 450 400 500 T800 480 L800 600 Z" fill="white" opacity="0.3"/>
        <g fill="white" opacity="0.4">
            <path d="M150 600 L155 400 Q120 380 100 360 Q140 370 158 390 Q160 340 130 310 Q170 320 165 380 Q200 350 230 360 Q200 380 170 395 L165 600 Z"/>
            <path d="M620 600 L625 380 Q580 360 555 335 Q605 345 628 370 Q625 310 590 280 Q640 290 635 365 Q680 335 715 345 Q680 370 640 390 L635 600 Z"/>
        </g>
    </svg>

    <div class="brand-bottom">
        <a href="/ninjavan/index.php#track" class="brand-track-link">
            <i class="bi bi-search"></i> Track a parcel
        </a>
    </div>
</aside>

<section class="form-panel">
<div class="form-inner">

    <h2>Join our Delivery Fleet</h2>
    <p class="subtitle">Already have an account? <a href="login.php">Log in</a></p>

    <?php if($error): ?>
    <div class="nv-error">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">

        <div class="row-fields">
            <div class="nv-field-group">
                <label>First Name *</label>
                <input type="text" name="first_name" class="nv-field"
                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
            </div>
            <div class="nv-field-group">
                <label>Last Name *</label>
                <input type="text" name="last_name" class="nv-field"
                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
            </div>
        </div>

        <div class="nv-field-group">
            <label>Email Address *</label>
            <input type="email" name="email" class="nv-field"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="row-fields">
            <div class="nv-field-group">
                <label>Phone Number *</label>
                <input type="tel" name="phone" class="nv-field" placeholder="09xxxxxxxxx"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
            </div>

            <div class="nv-field-group">
                <label>Vehicle Type *</label>
                <div class="nv-select-wrap">
                    <select name="vehicle_type" class="nv-select" required>
                        <option value="">Select vehicle...</option>
                        <option value="Motorcycle" <?= (($_POST['vehicle_type'] ?? '') === 'Motorcycle') ? 'selected' : '' ?>>Motorcycle</option>
                        <option value="Bicycle" <?= (($_POST['vehicle_type'] ?? '') === 'Bicycle') ? 'selected' : '' ?>>Bicycle</option>
                        <option value="Van / Car" <?= (($_POST['vehicle_type'] ?? '') === 'Van / Car') ? 'selected' : '' ?>>Van / Car</option>
                        <option value="Truck" <?= (($_POST['vehicle_type'] ?? '') === 'Truck') ? 'selected' : '' ?>>Truck</option>
                    </select>
                    <i class="bi bi-chevron-down nv-select-icon"></i>
                </div>
            </div>
        </div>

        <div class="nv-field-group">
            <label>Preferred Hub / Branch *</label>
            <div class="nv-select-wrap">
                <select name="hub_id" class="nv-select" required>
                    <option value="">Select your local branch...</option>
                    <?php while($h = $hubs->fetch_assoc()): ?>
                        <option value="<?= $h['Hub_ID'] ?>" <?= (($_POST['hub_id'] ?? '') === $h['Hub_ID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($h['Hub_Name']) ?> (<?= htmlspecialchars($h['Hub_Area']) ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
                <i class="bi bi-chevron-down nv-select-icon"></i>
            </div>
        </div>

        <div class="row-fields">
            <div class="nv-field-group">
                <label>Password *</label>
                <div class="nv-field-wrap">
                    <input type="password" name="password" class="nv-field" id="pwField" required>
                    <button type="button" class="toggle-pw" onclick="togglePw('pwField', 'eyeIcon1')">
                        <i class="bi bi-eye" id="eyeIcon1"></i>
                    </button>
                </div>
            </div>
            <div class="nv-field-group">
                <label>Confirm Password *</label>
                <div class="nv-field-wrap">
                    <input type="password" name="confirm_password" class="nv-field" id="confirmField" required>
                    <button type="button" class="toggle-pw" onclick="togglePw('confirmField', 'eyeIcon2')">
                        <i class="bi bi-eye" id="eyeIcon2"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4">
            <a href="/ninjavan/index.php" class="back-link">
                <i class="bi bi-arrow-left"></i> Back to home
            </a>
            <button type="submit" name="register_rider" class="btn-register">
                Register <i class="bi bi-check2-circle"></i>
            </button>
        </div>

    </form>

</div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePw(fieldId, iconId){
    const f = document.getElementById(fieldId);
    const i = document.getElementById(iconId);
    f.type  = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>

</body>
</html>
