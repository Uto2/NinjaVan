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
    try {
        $db->getReference('users/' . $usrId)->update(['Usr_Status' => $newSt]);
        if ($newSt === 'Suspended') {
            $auth->disableUser($usrId);
        } else {
            $auth->enableUser($usrId);
        }
        $_SESSION['toast_success'] = "Shipper status updated!";
    } catch (Exception $e) {
        $_SESSION['toast_error'] = "Failed to update status.";
    }
    header("Location: manage_shippers.php"); exit();
}

$search = trim(strtolower($_GET['q'] ?? ''));

$shipperOrders = [];
$ordersSnap = $db->getReference('orders')->getSnapshot();
if ($ordersSnap->hasChildren()) {
    foreach ($ordersSnap->getValue() as $o) {
        $shid = $o['Ord_ShprID'] ?? '';
        if ($shid) {
            if (!isset($shipperOrders[$shid])) {
                $shipperOrders[$shid] = ['total' => 0, 'delivered' => 0];
            }
            $shipperOrders[$shid]['total']++;
            if (($o['Ord_Status'] ?? '') === 'Delivered') {
                $shipperOrders[$shid]['delivered']++;
            }
        }
    }
}

$shippersList = [];
$totalS = 0;
$activeS = 0;

$usersSnap = $db->getReference('users')->getSnapshot();
if ($usersSnap->hasChildren()) {
    foreach ($usersSnap->getValue() as $u) {
        if (($u['Usr_Type'] ?? '') === 'shipper') {
            $totalS++;
            if (($u['Usr_Status'] ?? 'Active') === 'Active') $activeS++;
            
            if ($search) {
                $match = str_contains(strtolower($u['Usr_Name'] ?? ''), $search) ||
                         str_contains(strtolower($u['Usr_Email'] ?? ''), $search);
                if (!$match) continue;
            }
            
            $uid = $u['Usr_ID'] ?? '';
            $u['total_orders'] = $shipperOrders[$uid]['total'] ?? 0;
            $u['delivered'] = $shipperOrders[$uid]['delivered'] ?? 0;
            
            $shippersList[] = $u;
        }
    }
    
    usort($shippersList, function($a, $b) {
        return strtotime($b['Usr_DateReg'] ?? 0) <=> strtotime($a['Usr_DateReg'] ?? 0);
    });
}

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
        <?php if(empty($shippersList)): ?>
            <tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-people"></i></div><h4>No shippers found</h4></div></td></tr>
        <?php else: foreach($shippersList as $r): ?>
            <tr>
                <td><div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($r['Usr_Name']??'') ?></div><div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($r['Usr_Email']??'') ?></div></td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['Shpr_BizName']??'—') ?></td>
                <td style="font-size:12px;color:var(--muted);max-width:200px;white-space:normal;"><?= htmlspecialchars($r['Shpr_PickAddr']??'—') ?></td>
                <td style="font-weight:700;"><?= $r['total_orders'] ?></td>
                <td style="font-weight:700;color:var(--green);"><?= $r['delivered'] ?></td>
                <td><span class="badge-status <?= ($r['Usr_Status']??'')==='Active'?'badge-active':'badge-failed' ?>"><?= $r['Usr_Status']??'' ?></span></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="usr_id" value="<?= $r['Usr_ID'] ?>">
                        <input type="hidden" name="new_status" value="<?= ($r['Usr_Status']??'')==='Active'?'Suspended':'Active' ?>">
                        <button type="submit" name="toggle_status" class="btn-icon <?= ($r['Usr_Status']??'')==='Active'?'danger':'' ?>" title="<?= ($r['Usr_Status']??'')==='Active'?'Suspend':'Activate' ?>">
                            <i class="bi bi-<?= ($r['Usr_Status']??'')==='Active'?'pause-fill':'play-fill' ?>"></i>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div></div>

<?php include "../layout/dashboard_footer.php"; ?>
