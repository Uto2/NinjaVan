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

    // Get current hash — prepared statements
    if($role === 'admin'){
        $stmt = $conn->prepare("SELECT Adm_Pass FROM ADMIN_ACCOUNT WHERE Adm_ID = ?");
        $stmt->bind_param('s', $_SESSION['account_id']);
        $stmt->execute();
        $hash = $stmt->get_result()->fetch_assoc()['Adm_Pass'];
        $stmt->close();
    } else {
        $stmt = $conn->prepare("SELECT Usr_Pass FROM USER_ACCOUNT WHERE Usr_ID = ?");
        $stmt->bind_param('s', $_SESSION['account_id']);
        $stmt->execute();
        $hash = $stmt->get_result()->fetch_assoc()['Usr_Pass'];
        $stmt->close();
    }

    if(!password_verify($current, $hash)){
        $error = "Current password is incorrect.";
    } elseif(strlen($newPw) < 8){
        $error = "New password must be at least 8 characters.";
    } elseif(!preg_match('/[A-Z]/', $newPw)){
        $error = "New password must contain at least one uppercase letter.";
    } elseif(!preg_match('/[0-9]/', $newPw)){
        $error = "New password must contain at least one number.";
    } elseif($newPw !== $confirm){
        $error = "New passwords do not match.";
    } else {
        $newHash = password_hash($newPw, PASSWORD_DEFAULT);
        if($role === 'admin'){
            $stmt = $conn->prepare("UPDATE ADMIN_ACCOUNT SET Adm_Pass=? WHERE Adm_ID=?");
            $stmt->bind_param("ss", $newHash, $_SESSION['account_id']);
        } else {
            $stmt = $conn->prepare("UPDATE USER_ACCOUNT SET Usr_Pass=? WHERE Usr_ID=?");
            $stmt->bind_param("ss", $newHash, $_SESSION['account_id']);
        }
        $stmt->execute();
        $success = "Password changed successfully!";
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
