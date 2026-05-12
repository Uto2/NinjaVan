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
    $rdrId = $conn->real_escape_string($_POST['rdr_id']);
    $newSt = $conn->real_escape_string($_POST['new_status']);
    $conn->query("UPDATE RIDER SET Rdr_Status='$newSt' WHERE Rdr_ID='$rdrId'");
    $_SESSION['toast_success'] = "Rider status updated!";
    header("Location: manage_riders.php"); exit();
}

// Handle hub reassignment
if(isset($_POST['reassign_hub'])){
    $rdrId = $conn->real_escape_string($_POST['rdr_id']);
    $hubId = $conn->real_escape_string($_POST['hub_id']);
    $conn->query("UPDATE RIDER SET Rdr_HubID='$hubId' WHERE Rdr_ID='$rdrId'");
    $_SESSION['toast_success'] = "Rider hub reassigned!";
    header("Location: manage_riders.php"); exit();
}

$filterStatus = $_GET['status'] ?? '';
$where = $filterStatus ? "WHERE r.Rdr_Status='$filterStatus'" : "";

$riders = $conn->query("
    SELECT r.*, h.Hub_Name, h.Hub_Area,
           (SELECT COUNT(*) FROM DELIVERY_ATTEMPT da WHERE da.Atmp_RdrID = r.Rdr_ID AND da.Atmp_Rslt='Successful') as delivered,
           (SELECT COUNT(*) FROM DELIVERY_ATTEMPT da WHERE da.Atmp_RdrID = r.Rdr_ID AND da.Atmp_Rslt IN('Failed','Unavailable')) as failed,
           (SELECT COUNT(*) FROM DELIVERY_ATTEMPT da WHERE da.Atmp_RdrID = r.Rdr_ID) as total_attempts
    FROM RIDER r
    LEFT JOIN HUB h ON r.Rdr_HubID = h.Hub_ID
    $where
    ORDER BY r.Rdr_Status ASC, r.Rdr_Name ASC
");

$hubs = $conn->query("SELECT * FROM HUB ORDER BY Hub_Name");
$hubOpts = "";
while($h = $hubs->fetch_assoc()) $hubOpts .= "<option value='{$h['Hub_ID']}'>{$h['Hub_Name']} ({$h['Hub_Area']})</option>";

$totalR = $conn->query("SELECT COUNT(*) c FROM RIDER")->fetch_assoc()['c'];
$activeR = $conn->query("SELECT COUNT(*) c FROM RIDER WHERE Rdr_Status='Active'")->fetch_assoc()['c'];
$inactiveR = $totalR - $activeR;

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
        <?php if($riders->num_rows === 0): ?>
            <tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-bicycle"></i></div><h4>No riders found</h4></div></td></tr>
        <?php else: while($r = $riders->fetch_assoc()):
            $rate = $r['total_attempts'] > 0 ? round(($r['delivered']/$r['total_attempts'])*100) : 0;
        ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--ink),var(--ink-3));display:flex;align-items:center;justify-content:center;color:#fff;font-family:'Sora',sans-serif;font-weight:700;font-size:13px;"><?= strtoupper(substr($r['Rdr_Name'],0,1)) ?></div>
                        <div><div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($r['Rdr_Name']) ?></div>
                            <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($r['Rdr_Phone']??'—') ?></div></div>
                    </div>
                </td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['Rdr_VhcTyp']) ?></td>
                <td style="font-size:13px;"><?= $r['Hub_Name'] ? htmlspecialchars($r['Hub_Name']) : '<span style="color:var(--muted);">—</span>' ?></td>
                <td style="font-weight:700;color:var(--green);"><?= $r['delivered'] ?></td>
                <td style="font-weight:700;color:var(--red);"><?= $r['failed'] ?></td>
                <td><div style="display:flex;align-items:center;gap:6px;"><div style="height:5px;width:60px;background:var(--border);border-radius:3px;overflow:hidden;"><div style="height:100%;width:<?= $rate ?>%;background:<?= $rate>=80?'var(--green)':($rate>=50?'var(--amber)':'var(--red)') ?>;border-radius:3px;"></div></div><span style="font-size:12px;font-weight:600;"><?= $rate ?>%</span></div></td>
                <td><span class="badge-status <?= $r['Rdr_Status']==='Active'?'badge-active':'badge-inactive' ?>"><?= $r['Rdr_Status'] ?></span></td>
                <td>
                    <div style="display:flex;gap:4px;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="rdr_id" value="<?= $r['Rdr_ID'] ?>">
                            <input type="hidden" name="new_status" value="<?= $r['Rdr_Status']==='Active'?'Inactive':'Active' ?>">
                            <button type="submit" name="toggle_status" class="btn-icon" title="<?= $r['Rdr_Status']==='Active'?'Deactivate':'Activate' ?>">
                                <i class="bi bi-<?= $r['Rdr_Status']==='Active'?'pause-fill':'play-fill' ?>"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div></div>

<?php include "../layout/dashboard_footer.php"; ?>
