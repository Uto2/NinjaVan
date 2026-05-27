<?php
// ============================================================
//  NinjaVan PH — Forgot Password Page
//  Uses Firebase Auth to send password reset emails
// ============================================================
session_start();
require_once "../config/db.php";

$error = "";
$success = "";

if(isset($_POST['reset'])){
    $email = trim($_POST['email']);

    if(empty($email)){
        $error = "Please enter your email address.";
    } else {
        try {
            $auth->sendPasswordResetLink($email);
            $success = "Password reset link sent! Please check your email inbox.";
        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            // Security best practice: don't reveal if user exists, 
            // but for this specific app we might want to be helpful
            $error = "No account found with that email.";
        } catch (\Exception $e) {
            $error = "Failed to send reset link: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | NinjaVan</title>
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
        
        .form-panel { display:flex; align-items:center; justify-content:center; padding:48px 24px; min-height:100vh; }
        .form-inner { width:100%; max-width:420px; }
        .form-inner h2 { font-family:'Sora',sans-serif; font-size:28px; font-weight:800; color:var(--ink); letter-spacing:-0.02em; }
        .subtitle { color:var(--muted); font-size:14px; margin-top:8px; }

        .nv-field-group { margin-bottom:24px; }
        .nv-field-group label { display:block; font-size:11px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:0.07em; margin-bottom:4px; }
        .nv-field { width:100%; border:none; border-bottom:2px solid var(--border); padding:10px 0; font-size:15px; color:var(--ink); background:transparent; outline:none; font-family:'DM Sans',sans-serif; transition:border-color 0.2s; }
        .nv-field:focus { border-bottom-color:var(--red); }

        .nv-error { background:rgba(232,0,45,0.07); border:1px solid rgba(232,0,45,0.2); border-radius:var(--radius); padding:12px 16px; color:var(--red); font-size:14px; font-weight:500; display:flex; align-items:center; gap:8px; margin-bottom:20px; }
        .nv-success { background:rgba(22,163,74,0.07); border:1px solid rgba(22,163,74,0.2); border-radius:var(--radius); padding:12px 16px; color:#15803d; font-size:14px; font-weight:500; display:flex; align-items:center; gap:8px; margin-bottom:20px; }

        .btn-reset { width:100%; background:var(--ink); color:#fff; border:none; border-radius:50px; padding:14px; font-family:'Sora',sans-serif; font-weight:700; font-size:15px; cursor:pointer; transition:var(--trans); margin-top:10px; }
        .btn-reset:hover { background:var(--red); transform:translateY(-1px); box-shadow:0 8px 24px rgba(232,0,45,0.3); }
        .back-login { text-align:center; margin-top:24px; font-size:13px; color:var(--muted); }
        .back-login a { color:var(--ink); font-weight:600; text-decoration:none; }
        .back-login a:hover { color:var(--red); }
    </style>
</head>
<body>

<aside class="brand-panel">
    <div class="brand-panel-bg"></div>
    <div class="brand-panel-grid"></div>
    <a href="/ninjavan/index.php" class="brand-logo">ninja<span>van</span></a>
    <div class="brand-content">
        <h1>Forgot Password?</h1>
        <p>No worries, we'll help you get back to your account in no time.</p>
    </div>
</aside>

<section class="form-panel">
    <div class="form-inner">

        <header class="mb-5">
            <h2>Reset Password</h2>
            <p class="subtitle">Enter your email address and we'll send you a link to reset your password.</p>
        </header>

        <?php if($error): ?>
        <div class="nv-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if($success): ?>
        <div class="nv-success">
            <i class="bi bi-check-circle-fill"></i>
            <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="nv-field-group">
                <label>Email Address</label>
                <input type="email" name="email" class="nv-field"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       autocomplete="email" required placeholder="e.g. juan@example.com">
            </div>

            <button type="submit" name="reset" class="btn-reset">Send Reset Link</button>
        </form>

        <div class="back-login">
            <a href="/ninjavan/auth/login.php">
                <i class="bi bi-arrow-left me-1"></i>Back to login
            </a>
        </div>

    </div>
</section>

</body>
</html>
