<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Manage Shippers";
$activePage = "shippers";

// Handle suspend/activate
if(isset($_POST['toggle_status'])){
    csrf_verify();
    $usrId = $_POST['usr_id'];
    $newSt = $_POST['new_status'];
    if(!in_array($newSt, ['Active','Suspended'])) {
        header("Location: manage_shippers.php"); exit();
    }
    $stmt = $conn->prepare("UPDATE USER_ACCOUNT SET Usr_Status = ? WHERE Usr_ID = ?");
    $stmt->bind_param('ss', $newSt, $usrId);
    $stmt->execute();
    $stmt->close();
    $_SESSION['toast_success'] = "Shipper status updated!";
    header("Location: manage_shippers.php"); exit();
}

$search = $conn->real_escape_string($_GET['q'] ?? '');
$where = "WHERE u.Usr_Type='shipper'";
if($search) $where .= " AND (u.Usr_Name LIKE '%$search%' OR u.Usr_Email LIKE '%$search%')";

$shippers = $conn->query("
    SELECT u.*, sh.Shpr_ID, sh.Shpr_BizName, sh.Shpr_PickAddr,
           (SELECT COUNT(*) FROM `ORDER` o WHERE o.Ord_ShprID=sh.Shpr_ID) as total_orders,
           (SELECT COUNT(*) FROM `ORDER` o WHERE o.Ord_ShprID=sh.Shpr_ID AND o.Ord_Status='Delivered') as delivered
    FROM USER_ACCOUNT u
    LEFT JOIN SHIPPER sh ON sh.Shpr_UsrID = u.Usr_ID
    $where
    ORDER BY u.Usr_DateReg DESC
");

$totalS = $conn->query("SELECT COUNT(*) c FROM USER_ACCOUNT WHERE Usr_Type='shipper'")->fetch_assoc()['c'];
$activeS = $conn->query("SELECT COUNT(*) c FROM USER_ACCOUNT WHERE Usr_Type='shipper' AND Usr_Status='Active'")->fetch_assoc()['c'];

include "../layout/dashboard_layout.php";
?>

<div class="page-header">
    <div><h1>Manage Shippers</h1><p>View all registered shippers and their activity</p></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6">
        <div class="stat-card blue"><div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
            <div class="stat-value"><?= $totalS ?></div><div class="stat-label">Total Shippers</div></div>
    </div>
    <div class="col-sm-6">
        <div class="stat-card green"><div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-value"><?= $activeS ?></div><div class="stat-label">Active</div></div>
    </div>
</div>

<div style="display:flex;gap:8px;margin-bottom:20px;">
    <form method="GET" style="display:flex;gap:8px;flex:1;">
        <div style="position:relative;flex:1;">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);"></i>
            <input type="text" name="q" class="nv-input" style="padding-left:36px;" placeholder="Search shipper..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn-nv">Search</button>
    </form>
</div>

<div class="nv-card"><div style="overflow-x:auto;">
    <table class="nv-table">
        <thead><tr><th>Shipper</th><th>Business</th><th>Pickup Address</th><th>Orders</th><th>Delivered</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if($shippers->num_rows === 0): ?>
            <tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-people"></i></div><h4>No shippers found</h4></div></td></tr>
        <?php else: while($r = $shippers->fetch_assoc()): ?>
            <tr>
                <td><div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($r['Usr_Name']) ?></div><div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($r['Usr_Email']) ?></div></td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['Shpr_BizName']??'—') ?></td>
                <td style="font-size:12px;color:var(--muted);max-width:200px;white-space:normal;"><?= htmlspecialchars($r['Shpr_PickAddr']??'—') ?></td>
                <td style="font-weight:700;"><?= $r['total_orders'] ?></td>
                <td style="font-weight:700;color:var(--green);"><?= $r['delivered'] ?></td>
                <td><span class="badge-status <?= $r['Usr_Status']==='Active'?'badge-active':'badge-failed' ?>"><?= $r['Usr_Status'] ?></span></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="usr_id" value="<?= $r['Usr_ID'] ?>">
                        <input type="hidden" name="new_status" value="<?= $r['Usr_Status']==='Active'?'Suspended':'Active' ?>">
                        <button type="submit" name="toggle_status" class="btn-icon <?= $r['Usr_Status']==='Active'?'danger':'' ?>" title="<?= $r['Usr_Status']==='Active'?'Suspend':'Activate' ?>">
                            <i class="bi bi-<?= $r['Usr_Status']==='Active'?'pause-fill':'play-fill' ?>"></i>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div></div>

<?php include "../layout/dashboard_footer.php"; ?>
