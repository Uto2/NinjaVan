<?php
// ============================================================
//  NinjaVan PH — Login Page
//  Tables used: USER_ACCOUNT, ADMIN_ACCOUNT, SHIPPER, RIDER
//  Roles: admin → admin/dashboard.php
//         shipper → shipper/dashboard.php
//         rider → rider/dashboard.php
// ============================================================
session_start();
require_once "../config/db.php";

$error = "";

if(isset($_POST['login'])){

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        $signInResult = $auth->signInWithEmailAndPassword($email, $password);
        $uid = $signInResult->firebaseUserId();
        
        // Get user details from Realtime Database
        $userSnapshot = $db->getReference('users/' . $uid)->getSnapshot();
        if ($userSnapshot->exists()) {
            $user = $userSnapshot->getValue();
            
            if (($user['Usr_Status'] ?? '') === 'Suspended') {
                $error = "Your account has been suspended. Please contact support.";
            } else if (($user['Usr_Status'] ?? '') === 'Pending') {
                $error = "Your account is pending approval.";
            } else {
                $_SESSION['account_id']   = $uid;
                $_SESSION['email']        = $user['Usr_Email'] ?? '';
                $_SESSION['display_name'] = $user['Usr_Name'] ?? '';
                
                $role = strtolower($user['Usr_Type'] ?? '');
                $_SESSION['role'] = $role;
                
                if ($role === 'shipper') {
                    $_SESSION['shipper_id'] = $user['Shpr_ID'] ?? $uid;
                    header("Location: /ninjavan/shipper/dashboard.php"); exit();
                } elseif ($role === 'staff') {
                    $_SESSION['staff_id'] = $user['Stf_ID'] ?? $uid;
                    $_SESSION['hub_id']   = $user['hub_id'] ?? $user['Stf_HubID'] ?? '';
                    header("Location: /ninjavan/staff/dashboard.php"); exit();
                } elseif ($role === 'rider') {
                    $_SESSION['rider_id'] = $user['Usr_ID'] ?? $uid;
                    $_SESSION['hub_id']   = $user['hub_id'] ?? $user['Rdr_HubID'] ?? '';
                    header("Location: /ninjavan/rider/dashboard.php"); exit();
                } elseif ($role === 'admin') {
                    header("Location: /ninjavan/admin/dashboard.php"); exit();
                }
                
                $error = "Account profile missing or invalid role. Please contact admin.";
            }
        } else {
            // Check if it's an admin in a separate admins collection just in case
            $adminSnapshot = $db->getReference('admins/' . $uid)->getSnapshot();
            if ($adminSnapshot->exists()) {
                $admin = $adminSnapshot->getValue();
                $_SESSION['account_id']   = $uid;
                $_SESSION['role']         = 'admin';
                $_SESSION['email']        = $admin['Adm_Email'] ?? '';
                $_SESSION['display_name'] = $admin['Adm_Name'] ?? '';
                header("Location: /ninjavan/admin/dashboard.php"); exit();
            } else {
                $error = "User document not found in database.";
            }
        }
    } catch (\Kreait\Firebase\Exception\Auth\InvalidPassword $e) {
        $error = "Incorrect password.";
    } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
        $error = "No account found with that email.";
    } catch (\Exception $e) {
        // Catch all other errors including invalid credentials in newer SDKs
        $error = "Login failed: Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In | NinjaVan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --red:      #e8002d;
            --red-dark: #b80024;
            --ink:      #0d0d0d;
            --border:   #e5e7eb;
            --muted:    #6b7280;
            --radius:   12px;
            --trans:    all 0.28s cubic-bezier(0.4,0,0.2,1);
        }
        *, *::before, *::after { box-sizing:border-box; margin:0; }
        body {
            font-family:'DM Sans',sans-serif; background:#fff;
            min-height:100vh; display:grid; grid-template-columns:1fr 1fr;
        }
        @media(max-width:991px){ body{grid-template-columns:1fr} .brand-panel{display:none!important} }

        .brand-panel {
            background:var(--ink); position:relative; overflow:hidden;
            display:flex; flex-direction:column; padding:48px; min-height:100vh;
        }
        .brand-panel-bg {
            position:absolute; inset:0;
            background: radial-gradient(ellipse 80% 60% at 60% 80%, rgba(232,0,45,0.25) 0%, transparent 70%),
                        radial-gradient(ellipse 50% 40% at 20% 20%, rgba(232,0,45,0.1) 0%, transparent 60%);
        }
        .brand-panel-grid {
            position:absolute; inset:0;
            background-image: linear-gradient(rgba(255,255,255,0.025) 1px,transparent 1px),
                              linear-gradient(90deg,rgba(255,255,255,0.025) 1px,transparent 1px);
            background-size:36px 36px;
        }
        .brand-logo { font-family:'Sora',sans-serif; font-size:24px; font-weight:800; color:#fff; text-decoration:none; letter-spacing:-0.04em; position:relative; z-index:2; }
        .brand-logo span { color:var(--red); }
        .brand-content { position:relative; z-index:2; margin-top:80px; }
        .brand-content h1 { font-family:'Sora',sans-serif; font-size:clamp(36px,4vw,52px); font-weight:800; color:#fff; letter-spacing:-0.03em; line-height:1.1; margin-bottom:16px; }
        .brand-content p { color:rgba(255,255,255,0.65); font-size:16px; line-height:1.7; max-width:340px; }
        .brand-bottom { position:relative; z-index:2; margin-top:auto; }
        .brand-track-link { display:inline-flex; align-items:center; gap:8px; color:rgba(255,255,255,0.5); font-size:13px; text-decoration:none; border:1px solid rgba(255,255,255,0.12); border-radius:50px; padding:10px 18px; transition:var(--trans); }
        .brand-track-link:hover { color:var(--red); border-color:rgba(232,0,45,0.4); }

        .form-panel { display:flex; align-items:center; justify-content:center; padding:48px 24px; min-height:100vh; }
        .form-inner { width:100%; max-width:420px; }
        .form-inner h2 { font-family:'Sora',sans-serif; font-size:28px; font-weight:800; color:var(--ink); letter-spacing:-0.02em; }
        .subtitle { color:var(--muted); font-size:14px; margin-top:8px; }
        .subtitle a { color:var(--red); font-weight:600; text-decoration:none; }
        .subtitle a:hover { text-decoration:underline; }

        .nv-field-group { margin-bottom:24px; }
        .nv-field-group label { display:block; font-size:11px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:0.07em; margin-bottom:4px; }
        .nv-field { width:100%; border:none; border-bottom:2px solid var(--border); padding:10px 0; font-size:15px; color:var(--ink); background:transparent; outline:none; font-family:'DM Sans',sans-serif; transition:border-color 0.2s; }
        .nv-field:focus { border-bottom-color:var(--red); }
        .nv-field-wrap { position:relative; }
        .nv-field-wrap .nv-field { padding-right:36px; }
        .toggle-pw { position:absolute; right:0; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--muted); cursor:pointer; font-size:18px; padding:4px; transition:color 0.2s; }
        .toggle-pw:hover { color:var(--ink); }

        .nv-error { background:rgba(232,0,45,0.07); border:1px solid rgba(232,0,45,0.2); border-radius:var(--radius); padding:12px 16px; color:var(--red); font-size:14px; font-weight:500; display:flex; align-items:center; gap:8px; margin-bottom:20px; }
        .nv-success { background:rgba(22,163,74,0.07); border:1px solid rgba(22,163,74,0.2); border-radius:var(--radius); padding:12px 16px; color:#15803d; font-size:14px; font-weight:500; display:flex; align-items:center; gap:8px; margin-bottom:20px; }

        .forgot-link { display:block; text-align:right; color:var(--muted); font-size:13px; font-weight:600; text-decoration:none; margin-bottom:28px; transition:color 0.2s; }
        .forgot-link:hover { color:var(--red); }
        .btn-login { width:100%; background:var(--ink); color:#fff; border:none; border-radius:50px; padding:14px; font-family:'Sora',sans-serif; font-weight:700; font-size:15px; cursor:pointer; transition:var(--trans); }
        .btn-login:hover { background:var(--red); transform:translateY(-1px); box-shadow:0 8px 24px rgba(232,0,45,0.3); }
        .back-home { text-align:center; margin-top:24px; font-size:13px; color:var(--muted); }
        .back-home a { color:var(--ink); font-weight:600; text-decoration:none; }
        .back-home a:hover { color:var(--red); }
    </style>
</head>
<body>

<aside class="brand-panel">
    <div class="brand-panel-bg"></div>
    <div class="brand-panel-grid"></div>
    <a href="/ninjavan/index.php" class="brand-logo">ninja<span>van</span></a>
    <div class="brand-content">
        <h1>Ninja Van</h1>
        <p>The leading courier in the Philippines — delivering smarter, faster, further.</p>
    </div>
    <svg class="position-absolute bottom-0 start-0 w-100" style="height:55%;opacity:0.15;pointer-events:none;" viewBox="0 0 800 600" preserveAspectRatio="xMidYMax slice">
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

        <header class="mb-5">
            <h2>Glad to see you back, Ninja!</h2>
            <p class="subtitle">
                Don't have an account?
                <a href="/ninjavan/auth/register.php">Sign up here!</a>
            </p>
        </header>

        <?php if(isset($_SESSION['success_msg'])): ?>
        <div class="nv-success">
            <i class="bi bi-check-circle-fill"></i>
            <?= htmlspecialchars($_SESSION['success_msg']) ?>
        </div>
        <?php unset($_SESSION['success_msg']); endif; ?>

        <?php if($error): ?>
        <div class="nv-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="nv-field-group">
                <label>Email Address</label>
                <input type="email" name="email" class="nv-field"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       autocomplete="email" required>
            </div>

            <div class="nv-field-group">
                <label>Password</label>
                <div class="nv-field-wrap">
                    <input type="password" name="password" class="nv-field"
                           id="pwField" autocomplete="current-password" required>
                    <button type="button" class="toggle-pw" onclick="togglePw()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <a href="#" class="forgot-link">Forgot your password?</a>

            <button type="submit" name="login" class="btn-login">Login</button>
        </form>

        <div class="back-home">
            <a href="/ninjavan/index.php">
                <i class="bi bi-arrow-left me-1"></i>Back to home
            </a>
        </div>

    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePw(){
    const f = document.getElementById('pwField');
    const i = document.getElementById('eyeIcon');
    f.type  = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
</body>
</html>