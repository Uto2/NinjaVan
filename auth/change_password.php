<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id'])){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Change Password";
$activePage = "password";
$role       = $_SESSION['role'];
$error      = "";
$success    = "";

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    csrf_verify();
    $current = $_POST['current_password'];
    $newPw   = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if(strlen($newPw) < 8){
        $error = "New password must be at least 8 characters.";
    } elseif(!preg_match('/[A-Z]/', $newPw)){
        $error = "New password must contain at least one uppercase letter.";
    } elseif(!preg_match('/[0-9]/', $newPw)){
        $error = "New password must contain at least one number.";
    } elseif($newPw !== $confirm){
        $error = "New passwords do not match.";
    } else {
        try {
            // Verify current password by attempting to sign in
            $userRec = $auth->getUser($_SESSION['account_id']);
            $signInResult = $auth->signInWithEmailAndPassword($userRec->email, $current);
            
            // If successful, change the password
            $auth->changeUserPassword($_SESSION['account_id'], $newPw);
            $success = "Password changed successfully!";
            
        } catch (\Kreait\Firebase\Exception\Auth\InvalidPassword $e) {
            $error = "Current password is incorrect.";
        } catch (Exception $e) {
            $error = "Failed to update password: " . $e->getMessage();
        }
    }
}

include "../layout/dashboard_layout.php";
?>

<div class="page-header">
    <div><h1>Change Password</h1><p>Update your account password</p></div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="nv-card p-4">

            <?php if($error): ?>
            <div class="nv-toast error position-relative mb-4" style="animation:none;bottom:auto;right:auto;transform:none;opacity:1;">
                <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if($success): ?>
            <div class="nv-toast position-relative mb-4" style="animation:none;bottom:auto;right:auto;transform:none;opacity:1;">
                <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>
                <div class="nv-form-group">
                    <label>Current Password *</label>
                    <input type="password" name="current_password" class="nv-input" required>
                </div>

                <div class="nv-form-group">
                    <label>New Password *</label>
                    <input type="password" name="new_password" class="nv-input" required minlength="8">
                    <div style="font-size:11px;color:var(--muted);margin-top:4px;">Min 8 chars, 1 uppercase, 1 number</div>
                </div>

                <div class="nv-form-group">
                    <label>Confirm New Password *</label>
                    <input type="password" name="confirm_password" class="nv-input" required>
                </div>

                <button type="submit" class="btn-nv w-100" style="justify-content:center;padding:12px;">
                    <i class="bi bi-shield-check"></i> Update Password
                </button>
            </form>
        </div>
    </div>
</div>

<?php include "../layout/dashboard_footer.php"; ?>
