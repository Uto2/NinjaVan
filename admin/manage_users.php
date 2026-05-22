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

    try {
        $update = ['Usr_Type' => $role];
        
        if ($role === 'staff' || $role === 'rider') {
            if ($hubId) {
                $update['hub_id'] = $hubId;
                if ($role === 'staff') $update['Stf_HubID'] = $hubId;
                if ($role === 'rider') $update['Rdr_HubID'] = $hubId;
            }
        } else {
            $update['hub_id'] = null;
            $update['Stf_HubID'] = null;
            $update['Rdr_HubID'] = null;
        }

        $db->getReference('users/' . $usrId)->update($update);
        
        $_SESSION['toast_success'] = "Role updated successfully!";
        header("Location: manage_users.php"); exit();
    } catch(Exception $e) {
        $error = "Failed to update role: " . $e->getMessage();
    }
}

// ---- FILTER + SEARCH ----
$filterRole = trim(strtolower($_GET['role'] ?? ''));
$search = trim(strtolower($_GET['q'] ?? ''));

$hubsMap = [];
$hubOptions = "";
$hubsSnap = $db->getReference('hubs')->getSnapshot();
if ($hubsSnap->hasChildren()) {
    foreach ($hubsSnap->getValue() as $k => $h) {
        $hubsMap[$k] = $h;
        $name = htmlspecialchars($h['Hub_Name'] ?? '');
        $area = htmlspecialchars($h['Hub_Area'] ?? '');
        $id = htmlspecialchars($h['Hub_ID'] ?? '');
        $hubOptions .= "<option value='{$id}'>{$name} ({$area})</option>";
    }
}

$usersList = [];
$countAll = 0;
$countShipper = 0;
$countRider = 0;
$countStaff = 0;
$countAdmin = 0;

$usersSnap = $db->getReference('users')->getSnapshot();
if ($usersSnap->hasChildren()) {
    foreach ($usersSnap->getValue() as $u) {
        $countAll++;
        $type = strtolower($u['Usr_Type'] ?? '');
        if ($type === 'shipper') $countShipper++;
        if ($type === 'rider') $countRider++;
        if ($type === 'staff') $countStaff++;
        if ($type === 'admin') $countAdmin++;

        if ($filterRole && $type !== $filterRole) continue;

        if ($search) {
            $match = str_contains(strtolower($u['Usr_Name'] ?? ''), $search) ||
                     str_contains(strtolower($u['Usr_Email'] ?? ''), $search);
            if (!$match) continue;
        }

        $hubId = $u['hub_id'] ?? '';
        if ($hubId && isset($hubsMap[$hubId])) {
            $u['HubName'] = $hubsMap[$hubId]['Hub_Name'] ?? '';
            $u['HubArea'] = $hubsMap[$hubId]['Hub_Area'] ?? '';
        } else {
            $u['HubName'] = '';
            $u['HubArea'] = '';
        }

        $usersList[] = $u;
    }
    
    usort($usersList, function($a, $b) {
        $ta = $a['Usr_Type'] ?? '';
        $tb = $b['Usr_Type'] ?? '';
        if ($ta !== $tb) return strcmp($ta, $tb);
        return strtotime($b['Usr_DateReg'] ?? 0) <=> strtotime($a['Usr_DateReg'] ?? 0);
    });
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
        <?php if(empty($usersList)): ?>
            <tr><td colspan="6">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-people"></i></div>
                    <h4>No users found</h4>
                    <p><?= $search ? "No results for \"$search\"" : 'No accounts in this role.' ?></p>
                </div>
            </td></tr>
        <?php else: foreach($usersList as $u):
            $type = strtolower($u['Usr_Type'] ?? '');
            $roleColors = [
                'shipper' => ['role-shipper', 'bi-box-seam-fill',      'Shipper'],
                'rider'   => ['role-rider',   'bi-bicycle',            'Rider'],
                'staff'   => ['role-staff',   'bi-person-badge-fill',  'Staff'],
                'admin'   => ['role-admin',   'bi-shield-fill',        'Admin'],
            ];
            [$badgeCls, $badgeIcon, $badgeLabel] = $roleColors[$type] ?? ['role-shipper','bi-person','Unknown'];
            $hubDisplay = '';
            if(in_array($type, ['staff', 'rider']) && ($u['HubName'] ?? '')) {
                $hubDisplay = htmlspecialchars($u['HubName']) . ' <span style="color:var(--muted-2)">(' . htmlspecialchars($u['HubArea']) . ')</span>';
            }
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
                            <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($u['Usr_Email'] ?? '') ?></div>
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
                    <span class="badge-status <?= ($u['Usr_Status']??'')==='Active'?'badge-active':'badge-failed' ?>">
                        <?= $u['Usr_Status'] ?? '' ?>
                    </span>
                </td>
                <!-- Joined -->
                <td style="font-size:12px;color:var(--muted);white-space:nowrap;">
                    <?= date('M d, Y', strtotime($u['Usr_DateReg'] ?? 'now')) ?>
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
        <?php endforeach; endif; ?>
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
