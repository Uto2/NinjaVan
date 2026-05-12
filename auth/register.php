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
        $check = $conn->prepare("SELECT Usr_ID FROM USER_ACCOUNT WHERE Usr_Email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if($check->num_rows > 0){
            $error = "An account with that email already exists.";
        }
        else {
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
                <input type="text" name="first_name" class="nv-field" autocomplete="given-name"
                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
            </div>
            <div class="nv-field-group">
                <label>Last Name *</label>
                <input type="text" name="last_name" class="nv-field" autocomplete="family-name"
                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
            </div>
        </div>

        <div class="nv-field-group">
            <label>Email Address *</label>
            <input type="email" name="email" class="nv-field" autocomplete="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="nv-field-group">
            <label>Phone Number</label>
            <input type="tel" name="phone" class="nv-field" placeholder="+63 9XX XXX XXXX"
                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
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

</body>
</html>
