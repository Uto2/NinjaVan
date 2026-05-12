<?php
session_start();

// Guard — must have completed step 1
if(!isset($_SESSION['reg_step']) || $_SESSION['reg_step'] < 2){
    header("Location: register.php"); exit();
}
if($_SESSION['reg_step'] > 2){
    header("Location: register_password.php"); exit();
}

$error  = "";
$resent = false;

// Resend OTP
if(isset($_GET['resend'])){
    $_SESSION['reg_otp']      = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['reg_otp_time'] = time();
    $resent = true;
}

// Verify OTP
if(isset($_POST['step2'])){

    $entered = trim(
        ($_POST['d1'] ?? '') .
        ($_POST['d2'] ?? '') .
        ($_POST['d3'] ?? '') .
        ($_POST['d4'] ?? '') .
        ($_POST['d5'] ?? '') .
        ($_POST['d6'] ?? '')
    );

    // OTP expires in 10 minutes
    $expired = (time() - ($_SESSION['reg_otp_time'] ?? 0)) > 600;

    if($expired){
        $error = "Your OTP has expired. Please request a new one.";
    }
    else if($entered !== $_SESSION['reg_otp']){
        $error = "Incorrect OTP. Please try again.";
    }
    else {
        $_SESSION['reg_step'] = 3;
        header("Location: register_password.php"); exit();
    }
}

$otp   = $_SESSION['reg_otp'];
$email = $_SESSION['reg_email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email — Step 2 | NinjaVan</title>
    <?php include 'register_head.php'; ?>
</head>
<body>

<?php include 'register_brand.php'; ?>

<section class="form-panel">
<div class="form-inner">

    <?php stepperHtml(2); ?>

    <h2>Verify your email</h2>
    <p class="subtitle">
        We sent a 6-digit code to
        <strong style="color:var(--nv-ink);"><?= htmlspecialchars($email) ?></strong>
    </p>

    <?php if($error): ?>
    <div class="nv-error">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if($resent): ?>
    <div class="nv-info">
        <i class="bi bi-info-circle-fill mt-1"></i>
        <span>A new OTP has been generated. Check the demo box below.</span>
    </div>
    <?php endif; ?>

    <!-- DEMO OTP DISPLAY BOX -->
    <div class="otp-demo-box">
        <div class="label">⚡ Demo Mode — Your OTP</div>
        <div class="code">
            <?php
            // Show first 3 digits white, last 3 red for style
            echo substr($otp, 0, 3) . '<span>' . substr($otp, 3) . '</span>';
            ?>
        </div>
        <div style="color:rgba(255,255,255,0.35); font-size:11px; margin-top:6px;">
            In production this would be sent to your email
        </div>
    </div>

    <!-- OTP INPUT FORM -->
    <form method="POST" action="register_verify.php" id="otpForm">

        <div class="otp-group">
            <?php for($i = 1; $i <= 6; $i++): ?>
            <input type="text" name="d<?= $i ?>" id="d<?= $i ?>"
                   class="otp-box" maxlength="1"
                   inputmode="numeric" pattern="[0-9]"
                   autocomplete="off" required>
            <?php endfor; ?>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-2">
            <a href="register.php" class="back-link">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <button type="submit" name="step2" class="btn-register" id="verifyBtn" disabled>
                Verify <i class="bi bi-shield-check ms-1"></i>
            </button>
        </div>

    </form>

    <div style="text-align:center; margin-top:24px; font-size:13px; color:var(--nv-muted);">
        Didn't get it?
        <a href="?resend=1" style="color:var(--nv-red); font-weight:600; text-decoration:none;">
            Resend OTP
        </a>
        &nbsp;·&nbsp;
        <span id="timerText">Expires in <strong id="countdown">10:00</strong></span>
    </div>

</div>
</section>

<script>
// ===== OTP BOX AUTO-ADVANCE =====
const boxes = document.querySelectorAll('.otp-box');
const btn   = document.getElementById('verifyBtn');

boxes.forEach((box, idx) => {

    box.addEventListener('input', () => {
        // Only allow digits
        box.value = box.value.replace(/\D/g, '').slice(-1);
        if(box.value) {
            box.classList.add('filled');
            if(idx < boxes.length - 1) boxes[idx + 1].focus();
        } else {
            box.classList.remove('filled');
        }
        checkAllFilled();
    });

    box.addEventListener('keydown', (e) => {
        if(e.key === 'Backspace' && !box.value && idx > 0){
            boxes[idx - 1].focus();
            boxes[idx - 1].value = '';
            boxes[idx - 1].classList.remove('filled');
            checkAllFilled();
        }
    });

    // Handle paste on first box
    box.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData)
                        .getData('text').replace(/\D/g, '').slice(0, 6);
        [...pasted].forEach((ch, i) => {
            if(boxes[i]){
                boxes[i].value = ch;
                boxes[i].classList.add('filled');
            }
        });
        boxes[Math.min(pasted.length, 5)].focus();
        checkAllFilled();
    });
});

function checkAllFilled(){
    const allFilled = [...boxes].every(b => b.value.length === 1);
    btn.disabled = !allFilled;
}

// ===== COUNTDOWN TIMER (10 min) =====
const startTime = <?= $_SESSION['reg_otp_time'] ?>;
const expires   = startTime + 600;

function updateTimer(){
    const remaining = expires - Math.floor(Date.now() / 1000);
    if(remaining <= 0){
        document.getElementById('timerText').innerHTML =
            '<span style="color:var(--nv-red); font-weight:600;">OTP expired</span>';
        return;
    }
    const m = String(Math.floor(remaining / 60)).padStart(2, '0');
    const s = String(remaining % 60).padStart(2, '0');
    document.getElementById('countdown').textContent = m + ':' + s;
    setTimeout(updateTimer, 1000);
}
updateTimer();

// Auto-fill OTP from demo box on click (nice UX touch)
document.querySelector('.otp-demo-box').style.cursor = 'pointer';
document.querySelector('.otp-demo-box').addEventListener('click', () => {
    const code = '<?= $otp ?>';
    [...code].forEach((ch, i) => {
        boxes[i].value = ch;
        boxes[i].classList.add('filled');
    });
    checkAllFilled();
    btn.focus();
});
// Tooltip hint
document.querySelector('.otp-demo-box').title = 'Click to auto-fill the OTP';
</script>

</body>
</html>
