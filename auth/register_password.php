<?php
session_start();

if(!isset($_SESSION['reg_step']) || $_SESSION['reg_step'] < 3){
    header("Location: register.php"); exit();
}
if($_SESSION['reg_step'] > 3){
    header("Location: register_profile.php"); exit();
}

$error = "";

if(isset($_POST['step3'])){

    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if(strlen($password) < 8){
        $error = "Password must be at least 8 characters.";
    }
    else if(!preg_match('/[A-Z]/', $password)){
        $error = "Password must contain at least one uppercase letter.";
    }
    else if(!preg_match('/[0-9]/', $password)){
        $error = "Password must contain at least one number.";
    }
    else if($password !== $confirm){
        $error = "Passwords do not match.";
    }
    else {
        $_SESSION['reg_password'] = $password; // hashed at final step
        $_SESSION['reg_step']     = 4;
        header("Location: register_profile.php"); exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password — Step 3 | NinjaVan</title>
    <?php include 'register_head.php'; ?>
</head>
<body>

<?php include 'register_brand.php'; ?>

<section class="form-panel">
<div class="form-inner">

    <?php stepperHtml(3); ?>

    <h2>Set your password</h2>
    <p class="subtitle">
        Choose a strong password for
        <strong style="color:var(--nv-ink);"><?= htmlspecialchars($_SESSION['reg_email']) ?></strong>
    </p>

    <?php if($error): ?>
    <div class="nv-error">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">

        <!-- PASSWORD -->
        <div class="nv-field-group">
            <label>New Password *</label>
            <div class="nv-field-wrap">
                <input type="password" name="password" id="pwField"
                       class="nv-field" oninput="checkStrength(this.value)"
                       autocomplete="new-password" required>
                <button type="button" class="toggle-pw" onclick="togglePw('pwField','eye1')">
                    <i class="bi bi-eye" id="eye1"></i>
                </button>
            </div>
            <!-- Strength bar -->
            <div class="strength-bar">
                <div class="strength-fill" id="strengthFill"></div>
            </div>
            <div class="strength-text" id="strengthText"></div>
        </div>

        <!-- CONFIRM PASSWORD -->
        <div class="nv-field-group">
            <label>Confirm Password *</label>
            <div class="nv-field-wrap">
                <input type="password" name="confirm_password" id="cpwField"
                       class="nv-field" oninput="checkMatch()"
                       autocomplete="new-password" required>
                <button type="button" class="toggle-pw" onclick="togglePw('cpwField','eye2')">
                    <i class="bi bi-eye" id="eye2"></i>
                </button>
            </div>
            <div class="strength-text" id="matchText"></div>
        </div>

        <!-- REQUIREMENTS CHECKLIST -->
        <div style="margin-bottom:24px; background:#fafafa; border:1px solid var(--nv-border); border-radius:10px; padding:14px 16px;">
            <p style="font-size:12px; color:var(--nv-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:10px;">Requirements</p>
            <div id="req-len"  class="req-item"><i class="bi bi-circle me-2"></i>At least 8 characters</div>
            <div id="req-up"   class="req-item"><i class="bi bi-circle me-2"></i>One uppercase letter</div>
            <div id="req-num"  class="req-item"><i class="bi bi-circle me-2"></i>One number</div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-2">
            <a href="register_verify.php" class="back-link">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <button type="submit" name="step3" class="btn-register">
                Next <i class="bi bi-arrow-right"></i>
            </button>
        </div>

    </form>

</div>
</section>

<style>
.req-item {
    font-size: 13px; color: var(--nv-muted);
    margin-bottom: 6px; transition: color 0.2s;
    display: flex; align-items: center;
}
.req-item.met {
    color: #16a34a;
}
.req-item.met .bi { font-size: 0; }
.req-item.met::before {
    content: '';
    display: inline-block;
    width: 16px; height: 16px;
    background: #16a34a;
    border-radius: 50%;
    margin-right: 8px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='white'%3E%3Cpath d='M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z'/%3E%3C/svg%3E");
    background-size: 10px; background-repeat: no-repeat; background-position: center;
    flex-shrink: 0;
}
</style>

<script>
function togglePw(fieldId, iconId){
    const f = document.getElementById(fieldId);
    const i = document.getElementById(iconId);
    f.type  = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

function checkStrength(val){
    const fill = document.getElementById('strengthFill');
    const text = document.getElementById('strengthText');

    const hasLen = val.length >= 8;
    const hasUp  = /[A-Z]/.test(val);
    const hasNum = /[0-9]/.test(val);
    const hasSym = /[^a-zA-Z0-9]/.test(val);

    // Update checklist
    document.getElementById('req-len').className = 'req-item' + (hasLen ? ' met' : '');
    document.getElementById('req-up').className  = 'req-item' + (hasUp  ? ' met' : '');
    document.getElementById('req-num').className = 'req-item' + (hasNum ? ' met' : '');

    const score = [hasLen, hasUp, hasNum, hasSym, val.length >= 12].filter(Boolean).length;

    const levels = [
        { w:'0%',   bg:'transparent', label:'',          color:'' },
        { w:'25%',  bg:'#ef4444',     label:'Weak',       color:'#ef4444' },
        { w:'50%',  bg:'#f97316',     label:'Fair',       color:'#f97316' },
        { w:'75%',  bg:'#eab308',     label:'Good',       color:'#eab308' },
        { w:'90%',  bg:'#22c55e',     label:'Strong',     color:'#22c55e' },
        { w:'100%', bg:'#16a34a',     label:'Very Strong',color:'#16a34a' },
    ];

    const l = levels[Math.min(score, 5)];
    fill.style.width      = l.w;
    fill.style.background = l.bg;
    text.textContent      = l.label;
    text.style.color      = l.color;

    checkMatch();
}

function checkMatch(){
    const pw  = document.getElementById('pwField').value;
    const cpw = document.getElementById('cpwField').value;
    const mt  = document.getElementById('matchText');
    if(!cpw) { mt.textContent = ''; return; }
    if(pw === cpw){
        mt.textContent = '✓ Passwords match';
        mt.style.color = '#16a34a';
    } else {
        mt.textContent = '✗ Passwords do not match';
        mt.style.color = '#ef4444';
    }
}
</script>

</body>
</html>
