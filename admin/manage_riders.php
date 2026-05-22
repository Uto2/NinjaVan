<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Manage Riders";
$activePage = "riders";

// Handle status toggle
if(isset($_POST['toggle_status'])){
    csrf_verify();
    $rdrId = $_POST['rdr_id'];
    $newSt = $_POST['new_status'];
    // Whitelist valid statuses to prevent injection through enum bypass
    if(!in_array($newSt, ['Active','Inactive'])) {
        header("Location: manage_riders.php"); exit();
    }
    try {
        $db->getReference('users/' . $rdrId)->update(['Usr_Status' => $newSt]);
        // Optional: disable in Auth as well
        if ($newSt === 'Inactive') {
            $auth->disableUser($rdrId);
        } else {
            $auth->enableUser($rdrId);
        }
        $_SESSION['toast_success'] = "Rider status updated!";
    } catch (Exception $e) {
        $_SESSION['toast_error'] = "Failed to update status.";
    }
    header("Location: manage_riders.php"); exit();
}

$filterStatus = $_GET['status'] ?? '';
if(!in_array($filterStatus, ['', 'Active', 'Inactive'])) $filterStatus = '';

$hubsMap = [];
$hubsSnap = $db->getReference('hubs')->getSnapshot();
if ($hubsSnap->hasChildren()) {
    foreach ($hubsSnap->getValue() as $k => $h) {
        $hubsMap[$k] = $h;
    }
}

$riderAttempts = [];
$ordersSnap = $db->getReference('orders')->getSnapshot();
if ($ordersSnap->hasChildren()) {
    foreach ($ordersSnap->getValue() as $o) {
        if (isset($o['delivery_attempts'])) {
            foreach ($o['delivery_attempts'] as $att) {
                $rid = $att['Atmp_RdrID'] ?? '';
                if ($rid) {
                    if (!isset($riderAttempts[$rid])) {
                        $riderAttempts[$rid] = ['delivered' => 0, 'failed' => 0, 'total' => 0];
                    }
                    $riderAttempts[$rid]['total']++;
                    $res = $att['Atmp_Rslt'] ?? '';
                    if ($res === 'Successful') $riderAttempts[$rid]['delivered']++;
                    elseif ($res === 'Failed' || $res === 'Unavailable') $riderAttempts[$rid]['failed']++;
                }
            }
        }
    }
}

$ridersList = [];
$totalR = 0;
$activeR = 0;
$inactiveR = 0;

$usersSnap = $db->getReference('users')->getSnapshot();
if ($usersSnap->hasChildren()) {
    foreach ($usersSnap->getValue() as $u) {
        if (($u['Usr_Type'] ?? '') === 'rider') {
            $totalR++;
            $status = $u['Usr_Status'] ?? 'Active';
            if ($status === 'Active') $activeR++;
            else $inactiveR++;

            if ($filterStatus && $status !== $filterStatus) continue;

            $hubId = $u['hub_id'] ?? '';
            $u['Hub_Name'] = $hubsMap[$hubId]['Hub_Name'] ?? '';
            $u['delivered'] = $riderAttempts[$u['Usr_ID']]['delivered'] ?? 0;
            $u['failed'] = $riderAttempts[$u['Usr_ID']]['failed'] ?? 0;
            $u['total_attempts'] = $riderAttempts[$u['Usr_ID']]['total'] ?? 0;
            
            $ridersList[] = $u;
        }
    }
    
    usort($ridersList, function($a, $b) {
        $sa = $a['Usr_Status'] ?? '';
        $sb = $b['Usr_Status'] ?? '';
        if ($sa !== $sb) return strcmp($sa, $sb);
        return strcmp($a['Usr_Name'] ?? '', $b['Usr_Name'] ?? '');
    });
}

include "../layout/dashboard_layout.php";
?>

<div class="page-header">
    <div><h1>Manage Riders</h1><p>View all riders, their hub assignments, and delivery performance</p></div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card blue"><div class="stat-icon blue"><i class="bi bi-bicycle"></i></div>
            <div class="stat-value"><?= $totalR ?></div><div class="stat-label">Total Riders</div></div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card green"><div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-value"><?= $activeR ?></div><div class="stat-label">Active</div></div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card red"><div class="stat-icon red"><i class="bi bi-pause-circle-fill"></i></div>
            <div class="stat-value"><?= $inactiveR ?></div><div class="stat-label">Inactive</div></div>
    </div>
</div>

<!-- Filter -->
<div style="display:flex;gap:6px;margin-bottom:24px;">
    <?php foreach(['' => 'All', 'Active' => 'Active', 'Inactive' => 'Inactive'] as $k=>$v): ?>
    <a href="?status=<?= $k ?>" style="padding:7px 16px;border-radius:50px;font-size:12px;font-weight:600;text-decoration:none;<?= $filterStatus===$k?'background:var(--ink);color:#fff;':'background:var(--surface-2);color:var(--muted);border:1.5px solid var(--border);' ?>"><?= $v ?></a>
    <?php endforeach; ?>
</div>

<div class="nv-card"><div style="overflow-x:auto;">
    <table class="nv-table">
        <thead><tr><th>Rider</th><th>Vehicle</th><th>Hub</th><th>Delivered</th><th>Failed</th><th>Rate</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if(empty($ridersList)): ?>
            <tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-bicycle"></i></div><h4>No riders found</h4></div></td></tr>
        <?php else: foreach($ridersList as $r):
            $rate = $r['total_attempts'] > 0 ? round(($r['delivered']/$r['total_attempts'])*100) : 0;
        ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--ink),var(--ink-3));display:flex;align-items:center;justify-content:center;color:#fff;font-family:'Sora',sans-serif;font-weight:700;font-size:13px;"><?= strtoupper(substr($r['Usr_Name']??'R',0,1)) ?></div>
                        <div><div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($r['Usr_Name']??'') ?></div>
                            <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($r['Usr_Phone']??'—') ?></div></div>
                    </div>
                </td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['Rdr_VhcTyp']??'—') ?></td>
                <td style="font-size:13px;"><?= ($r['Hub_Name']??'') ? htmlspecialchars($r['Hub_Name']) : '<span style="color:var(--muted);">—</span>' ?></td>
                <td style="font-weight:700;color:var(--green);"><?= $r['delivered'] ?></td>
                <td style="font-weight:700;color:var(--red);"><?= $r['failed'] ?></td>
                <td><div style="display:flex;align-items:center;gap:6px;"><div style="height:5px;width:60px;background:var(--border);border-radius:3px;overflow:hidden;"><div style="height:100%;width:<?= $rate ?>%;background:<?= $rate>=80?'var(--green)':($rate>=50?'var(--amber)':'var(--red)') ?>;border-radius:3px;"></div></div><span style="font-size:12px;font-weight:600;"><?= $rate ?>%</span></div></td>
                <td><span class="badge-status <?= ($r['Usr_Status']??'')==='Active'?'badge-active':'badge-inactive' ?>"><?= $r['Usr_Status']??'' ?></span></td>
                <td>
                    <div style="display:flex;gap:4px;">
                        <form method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="rdr_id" value="<?= $r['Usr_ID'] ?>">
                            <input type="hidden" name="new_status" value="<?= ($r['Usr_Status']??'')==='Active'?'Inactive':'Active' ?>">
                            <button type="submit" name="toggle_status" class="btn-icon" title="<?= ($r['Usr_Status']??'')==='Active'?'Deactivate':'Activate' ?>">
                                <i class="bi bi-<?= ($r['Usr_Status']??'')==='Active'?'pause-fill':'play-fill' ?>"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div></div>

<?php include "../layout/dashboard_footer.php"; ?>
