<?php
session_start();
require_once "../config/db.php";

// Clear any leftover registration session
unset($_SESSION['reg_step'], $_SESSION['reg_fname'], $_SESSION['reg_lname'],
      $_SESSION['reg_email'], $_SESSION['reg_phone'], $_SESSION['reg_otp'],
      $_SESSION['reg_otp_time'], $_SESSION['reg_password']);

$error = "";

if(isset($_POST['step1'])){

    $firstName = trim($_POST['first_name']);
    $lastName  = trim($_POST['last_name']);
    $email     = trim($_POST['email']);
    $phone     = trim($_POST['phone']);

    if(empty($firstName) || empty($lastName) || empty($email)){
        $error = "Please fill in all required fields.";
    }
    else if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = "Please enter a valid email address.";
    }
    else {
        try {
            $user = $auth->getUserByEmail($email);
            // If we get here, the user exists
            $error = "An account with that email already exists.";
        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            // User does not exist, safe to proceed
            // Save to session and generate OTP
            $_SESSION['reg_step']     = 2;
            $_SESSION['reg_fname']    = $firstName;
            $_SESSION['reg_lname']    = $lastName;
            $_SESSION['reg_email']    = $email;
            $_SESSION['reg_phone']    = $phone;
            $_SESSION['reg_otp']      = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['reg_otp_time'] = time();

            header("Location: register_verify.php"); exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up — Step 1 | NinjaVan</title>
    <?php include 'register_head.php'; ?>
</head>
<body>

<?php include 'register_brand.php'; ?>

<section class="form-panel">
<div class="form-inner">

    <?php stepperHtml(1); ?>

    <h2>Let's get you started</h2>
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
                <input type="text" name="first_name" id="firstNameInput" class="nv-field" autocomplete="given-name"
                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
            </div>
            <div class="nv-field-group">
                <label>Last Name *</label>
                <input type="text" name="last_name" id="lastNameInput" class="nv-field" autocomplete="family-name"
                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
            </div>
        </div>

        <div class="nv-field-group">
            <label>Email Address *</label>
            <input type="email" name="email" id="emailInput" class="nv-field" autocomplete="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            <div id="emailError" style="color:var(--nv-red); font-size:12px; margin-top:4px; display:none;">Email is already taken</div>
        </div>

        <div class="nv-field-group">
            <label>Phone Number *</label>
            <div class="nv-prefix-wrap" id="phoneWrap">
                <span class="nv-field-prefix">+63</span>
                <input type="tel" name="phone" id="phoneInput" class="nv-field" placeholder="9XX XXX XXXX"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required maxlength="10">
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4">
            <a href="/ninjavan/index.php" class="back-link">
                <i class="bi bi-arrow-left"></i> Back to home
            </a>
            <button type="submit" name="step1" class="btn-register">
                Next <i class="bi bi-arrow-right"></i>
            </button>
        </div>

    </form>

</div>
</section>

<script>
// Name trapping (Letters and spaces only)
function trapName(e) {
    e.target.value = e.target.value.replace(/[^A-Za-z\s]/g, '');
}
document.getElementById('firstNameInput').addEventListener('input', trapName);
document.getElementById('lastNameInput').addEventListener('input', trapName);

// Phone trapping (Numbers only)
document.getElementById('phoneInput').addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/[^0-9]/g, '');
});

// Live Email Checking
let emailTimeout;
const emailInput = document.getElementById('emailInput');
const emailError = document.getElementById('emailError');

emailInput.addEventListener('input', function(e) {
    clearTimeout(emailTimeout);
    emailError.style.display = 'none';
    emailInput.style.borderColor = 'var(--nv-border)';
    
    const email = e.target.value.trim();
    if (email === '' || !email.includes('@')) return;

    emailTimeout = setTimeout(async () => {
        try {
            const res = await fetch(`/ninjavan/api/check_email.php?email=${encodeURIComponent(email)}`);
            const data = await res.json();
            if (data.status === 'taken') {
                emailError.style.display = 'block';
                emailInput.style.borderColor = 'var(--nv-red)';
            }
        } catch(e) {}
    }, 500);
});
</script>

</body>
</html>
