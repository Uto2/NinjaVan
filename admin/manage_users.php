<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Manage Users & Roles";
$activePage = "users";
$success    = "";
$error      = "";

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_role'])) {
    csrf_verify();
    $usrId = $_POST['usr_id'];
    $role  = $_POST['role'];
    $hubId = $_POST['hub_id'] ?? '';

    // Whitelist role values
    if(!in_array($role, ['shipper','rider','staff','admin'])) {
        $_SESSION['toast_error'] = 'Invalid role.';
        header('Location: manage_users.php'); exit();
    }

    $conn->begin_transaction();
    try {
        // Update role — prepared statement
        $stmtRole = $conn->prepare("UPDATE USER_ACCOUNT SET Usr_Type = ? WHERE Usr_ID = ?");
        $stmtRole->bind_param('ss', $role, $usrId);
        $stmtRole->execute();
        $stmtRole->close();

        // Fetch user info — prepared statement
        $stmtInfo = $conn->prepare("SELECT Usr_Name, Usr_Phone FROM USER_ACCOUNT WHERE Usr_ID = ?");
        $stmtInfo->bind_param('s', $usrId);
        $stmtInfo->execute();
        $infoRes  = $stmtInfo->get_result();
        $stmtInfo->close();
        $userData = $infoRes->num_rows > 0 ? $infoRes->fetch_assoc() : [];
        $name  = $userData['Usr_Name']  ?? 'New User';
        $phone = $userData['Usr_Phone'] ?? '';

        if($role === 'staff' && $hubId) {
            $chk = $conn->prepare("SELECT Stf_ID FROM STAFF WHERE Stf_UsrID = ?");
            $chk->bind_param('s', $usrId);
            $chk->execute(); $chk->store_result();
            if($chk->num_rows > 0) {
                $chk->close();
                $s = $conn->prepare("UPDATE STAFF SET Stf_HubID = ? WHERE Stf_UsrID = ?");
                $s->bind_param('ss', $hubId, $usrId); $s->execute(); $s->close();
            } else {
                $chk->close();
                $stfId = 'STF' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
                $s = $conn->prepare("INSERT INTO STAFF (Stf_ID, Stf_UsrID, Stf_HubID, Stf_Role) VALUES (?, ?, ?, 'Branch Manager')");
                $s->bind_param('sss', $stfId, $usrId, $hubId); $s->execute(); $s->close();
            }
        }
        elseif ($role === 'rider' && $hubId) {
            $chk = $conn->prepare("SELECT Rdr_ID FROM RIDER WHERE Rdr_UsrID = ?");
            $chk->bind_param('s', $usrId);
            $chk->execute(); $chk->store_result();
            if($chk->num_rows === 0) {
                $chk->close();
                $chk = $conn->prepare("SELECT Rdr_ID FROM RIDER WHERE Rdr_Name = ?");
                $chk->bind_param('s', $name);
                $chk->execute(); $chk->store_result();
            }
            if($chk->num_rows > 0) {
                $chkRes = $chk->get_result(); $chk->close();
                $existRdr = $chkRes->fetch_assoc();
                $s = $conn->prepare("UPDATE RIDER SET Rdr_HubID=?, Rdr_Status='Active', Rdr_UsrID=? WHERE Rdr_ID=?");
                $s->bind_param('sss', $hubId, $usrId, $existRdr['Rdr_ID']); $s->execute(); $s->close();
            } else {
                $chk->close();
                $rdrId   = 'RDR' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
                $vehicle = 'Motorcycle';
                $s = $conn->prepare("INSERT INTO RIDER (Rdr_ID, Rdr_UsrID, Rdr_HubID, Rdr_Name, Rdr_Phone, Rdr_VhcTyp, Rdr_Status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
                $s->bind_param('ssssss', $rdrId, $usrId, $hubId, $name, $phone, $vehicle); $s->execute(); $s->close();
            }
        }

        // If changing away from rider, mark rider inactive
        if($role !== 'rider') {
            $s = $conn->prepare("UPDATE RIDER SET Rdr_Status='Inactive' WHERE Rdr_UsrID = ?");
            $s->bind_param('s', $usrId); $s->execute(); $s->close();
        }

        $conn->commit();
        $_SESSION['toast_success'] = "Role updated successfully!";
        header("Location: manage_users.php"); exit();
    } catch(Exception $e) {
        $conn->rollback();
        $error = "Failed to update role: " . $e->getMessage();
    }
}

// ---- FILTER + SEARCH ----
$filterRole   = $conn->real_escape_string($_GET['role'] ?? '');
$search       = $conn->real_escape_string($_GET['q']    ?? '');

$where = "WHERE 1=1";
if($filterRole) $where .= " AND u.Usr_Type = '$filterRole'";
if($search)     $where .= " AND (u.Usr_Name LIKE '%$search%' OR u.Usr_Email LIKE '%$search%')";

// Fetch all users with their hub info
$users = $conn->query("
    SELECT u.*,
           s.Stf_HubID, h1.Hub_Name AS StfHubName, h1.Hub_Area AS StfHubArea,
           r.Rdr_HubID, h2.Hub_Name AS RdrHubName, h2.Hub_Area AS RdrHubArea
    FROM USER_ACCOUNT u
    LEFT JOIN STAFF s  ON u.Usr_ID = s.Stf_UsrID
    LEFT JOIN HUB h1   ON s.Stf_HubID  = h1.Hub_ID
    LEFT JOIN RIDER r  ON (r.Rdr_UsrID = u.Usr_ID OR (r.Rdr_UsrID IS NULL AND r.Rdr_Name = u.Usr_Name))
    LEFT JOIN HUB h2   ON r.Rdr_HubID  = h2.Hub_ID
    $where
    ORDER BY u.Usr_Type ASC, u.Usr_DateReg DESC
");

// Role counts for tab badges
$countAll     = $conn->query("SELECT COUNT(*) c FROM USER_ACCOUNT")->fetch_assoc()['c'];
$countShipper = $conn->query("SELECT COUNT(*) c FROM USER_ACCOUNT WHERE Usr_Type='shipper'")->fetch_assoc()['c'];
$countRider   = $conn->query("SELECT COUNT(*) c FROM USER_ACCOUNT WHERE Usr_Type='rider'")->fetch_assoc()['c'];
$countStaff   = $conn->query("SELECT COUNT(*) c FROM USER_ACCOUNT WHERE Usr_Type='staff'")->fetch_assoc()['c'];
$countAdmin   = $conn->query("SELECT COUNT(*) c FROM USER_ACCOUNT WHERE Usr_Type='admin'")->fetch_assoc()['c'];

// Fetch Hubs for the dropdown
$hubs = $conn->query("SELECT * FROM HUB ORDER BY Hub_Name ASC");
$hubOptions = "";
while($h = $hubs->fetch_assoc()) {
    $hubOptions .= "<option value='{$h['Hub_ID']}'>{$h['Hub_Name']} ({$h['Hub_Area']})</option>";
}

include "../layout/dashboard_layout.php";
?>

<style>
/* Role badge colours unique per role */
.role-shipper { background:rgba(59,130,246,0.1);  color:#1d4ed8; }
.role-rider   { background:rgba(0,179,125,0.1);   color:#15803d; }
.role-staff   { background:rgba(6,182,212,0.1);   color:#0891b2; }
.role-admin   { background:rgba(232,0,45,0.1);    color:#b80024; }

/* Filter tab buttons */
.role-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    color: var(--muted);
    background: var(--surface-2);
    border: 1.5px solid var(--border);
    transition: var(--trans);
    white-space: nowrap;
}
.role-tab:hover { border-color: var(--red); color: var(--red); }
.role-tab.active { background: var(--ink); color: #fff; border-color: var(--ink); }
.role-tab .tab-count {
    background: rgba(255,255,255,0.2);
    border-radius: 20px;
    padding: 1px 7px;
    font-size: 10px;
    font-weight: 700;
}
.role-tab.active .tab-count { background: rgba(255,255,255,0.25); }
.role-tab:not(.active) .tab-count { background: var(--border); color: var(--muted); }
</style>

<div class="page-header">
    <div>
        <h1>Manage Users</h1>
        <p>Assign roles to registered accounts and allocate them to branch areas</p>
    </div>
</div>

<?php if($error): ?>
<div class="nv-toast error position-relative mb-4" style="animation:none; bottom:auto; right:auto; transform:none; opacity:1;">
    <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- ROLE FILTER TABS + SEARCH -->
<div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:20px;">

    <!-- Role tabs -->
    <div style="display:flex; flex-wrap:wrap; gap:6px; flex:1; min-width:200px;">
        <?php
        $tabs = [
            ''        => ['All Users',  $countAll,     'bi-people-fill'],
            'shipper' => ['Shippers',   $countShipper, 'bi-box-seam-fill'],
            'rider'   => ['Riders',     $countRider,   'bi-bicycle'],
            'staff'   => ['Staff',      $countStaff,   'bi-person-badge-fill'],
            'admin'   => ['Admins',     $countAdmin,   'bi-shield-fill'],
        ];
        foreach($tabs as $key => [$label, $cnt, $icon]):
            $isActive = $filterRole === $key;
            $qs = http_build_query(array_filter(['role' => $key, 'q' => $search]));
        ?>
        <a href="?<?= $qs ?>" class="role-tab <?= $isActive ? 'active' : '' ?>">
            <i class="bi <?= $icon ?>"></i>
            <?= $label ?>
            <span class="tab-count"><?= $cnt ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Search bar -->
    <form method="GET" style="display:flex; gap:6px; min-width:240px;">
        <?php if($filterRole): ?><input type="hidden" name="role" value="<?= htmlspecialchars($filterRole) ?>"><?php endif; ?>
        <div style="position:relative; flex:1;">
            <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;"></i>
            <input type="text" name="q" class="nv-input" style="padding-left:32px; height:36px; font-size:13px;"
                   placeholder="Search name or email..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn-nv" style="padding:7px 14px; font-size:12px;">Search</button>
        <?php if($search): ?>
        <a href="?role=<?= urlencode($filterRole) ?>" class="btn-nv-ghost" style="padding:7px 14px; font-size:12px;">Clear</a>
        <?php endif; ?>
    </form>

</div>

<!-- USER TABLE -->
<div class="nv-card"><div style="overflow-x:auto;">
    <table class="nv-table">
        <thead>
            <tr>
                <th>User</th>
                <th>
                    <!-- Clickable Role column header for cycling sort -->
                    <?php
                    $nextRole = '';
                    if($filterRole === '')        $nextRole = 'shipper';
                    elseif($filterRole==='shipper') $nextRole = 'rider';
                    elseif($filterRole==='rider')   $nextRole = 'staff';
                    elseif($filterRole==='staff')   $nextRole = 'admin';
                    else                           $nextRole = '';
                    $nextQs = http_build_query(array_filter(['role'=>$nextRole,'q'=>$search]));
                    ?>
                    <a href="?<?= $nextQs ?>" style="display:inline-flex;align-items:center;gap:5px;color:var(--muted);text-decoration:none;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;transition:color 0.2s;"
                       title="Click to cycle through roles">
                        Current Role
                        <i class="bi bi-arrow-down-up" style="font-size:10px;"></i>
                    </a>
                </th>
                <th>Hub / Area</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Change Role</th>
            </tr>
        </thead>
        <tbody>
        <?php if(!$users || $users->num_rows === 0): ?>
            <tr><td colspan="6">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-people"></i></div>
                    <h4>No users found</h4>
                    <p><?= $search ? "No results for \"$search\"" : 'No accounts in this role.' ?></p>
                </div>
            </td></tr>
        <?php else: while($u = $users->fetch_assoc()):
            $type = strtolower($u['Usr_Type']);
            $roleColors = [
                'shipper' => ['role-shipper', 'bi-box-seam-fill',      'Shipper'],
                'rider'   => ['role-rider',   'bi-bicycle',            'Rider'],
                'staff'   => ['role-staff',   'bi-person-badge-fill',  'Staff'],
                'admin'   => ['role-admin',   'bi-shield-fill',        'Admin'],
            ];
            [$badgeCls, $badgeIcon, $badgeLabel] = $roleColors[$type] ?? ['role-shipper','bi-person','Unknown'];
            $hubDisplay = '';
            if($type === 'staff' && $u['StfHubName'])      $hubDisplay = $u['StfHubName'] . ' <span style="color:var(--muted-2)">(' . $u['StfHubArea'] . ')</span>';
            elseif($type === 'rider' && $u['RdrHubName']) $hubDisplay = $u['RdrHubName'] . ' <span style="color:var(--muted-2)">(' . $u['RdrHubArea'] . ')</span>';
        ?>
            <tr>
                <!-- User -->
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--ink),var(--ink-3));display:flex;align-items:center;justify-content:center;color:#fff;font-family:'Sora',sans-serif;font-weight:700;font-size:13px;flex-shrink:0;">
                            <?= strtoupper(substr($u['Usr_Name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($u['Usr_Name'] ?? '—') ?></div>
                            <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($u['Usr_Email']) ?></div>
                        </div>
                    </div>
                </td>
                <!-- Role badge -->
                <td>
                    <span class="badge-status <?= $badgeCls ?>" style="font-size:11px;">
                        <i class="bi <?= $badgeIcon ?>" style="font-size:10px;"></i>
                        <?= $badgeLabel ?>
                    </span>
                </td>
                <!-- Hub -->
                <td style="font-size:12px;">
                    <?php if($hubDisplay): ?>
                        <i class="bi bi-geo-alt" style="color:var(--muted);margin-right:3px;"></i><?= $hubDisplay ?>
                    <?php else: ?>
                        <span style="color:var(--muted-2);">—</span>
                    <?php endif; ?>
                </td>
                <!-- Status -->
                <td>
                    <span class="badge-status <?= $u['Usr_Status']==='Active'?'badge-active':'badge-failed' ?>">
                        <?= $u['Usr_Status'] ?>
                    </span>
                </td>
                <!-- Joined -->
                <td style="font-size:12px;color:var(--muted);white-space:nowrap;">
                    <?= date('M d, Y', strtotime($u['Usr_DateReg'])) ?>
                </td>
                <!-- Change Role -->
                <td style="width:340px;">
                    <form method="POST" style="display:flex;gap:6px;align-items:center;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="usr_id" value="<?= $u['Usr_ID'] ?>">
                        <select name="role" class="nv-input" style="padding:5px 8px;font-size:12px;width:110px;"
                                onchange="toggleHub(this,'hub_<?= $u['Usr_ID'] ?>')" required>
                            <option value="shipper" <?= $type==='shipper'?'selected':'' ?>>Shipper</option>
                            <option value="rider"   <?= $type==='rider'  ?'selected':'' ?>>Rider</option>
                            <option value="staff"   <?= $type==='staff'  ?'selected':'' ?>>Staff</option>
                            <option value="admin"   <?= $type==='admin'  ?'selected':'' ?>>Admin</option>
                        </select>
                        <select name="hub_id" id="hub_<?= $u['Usr_ID'] ?>" class="nv-input"
                                style="padding:5px 8px;font-size:12px;<?= in_array($type,['staff','rider'])?'':'display:none;' ?>">
                            <option value="">Select hub...</option>
                            <?= $hubOptions ?>
                        </select>
                        <button type="submit" name="assign_role" class="btn-nv" style="padding:5px 12px;font-size:12px;white-space:nowrap;">
                            Save
                        </button>
                    </form>
                </td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div></div>


<script>
function toggleHub(roleSelect, hubSelectId) {
    var hubSelect = document.getElementById(hubSelectId);
    if(roleSelect.value === 'staff' || roleSelect.value === 'rider') {
        hubSelect.style.display = 'block';
        hubSelect.required = true;
    } else {
        hubSelect.style.display = 'none';
        hubSelect.required = false;
        hubSelect.value = '';
    }
}
</script>

<?php include "../layout/dashboard_footer.php"; ?>
