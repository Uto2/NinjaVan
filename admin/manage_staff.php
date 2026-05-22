<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Manage Staff";
$activePage = "staff";

// ---- HANDLE ADD STAFF ----
if(isset($_POST['add_staff'])){
    csrf_verify();
    $firstName = trim($_POST['first_name']);
    $lastName  = trim($_POST['last_name']);
    $name      = $firstName . ' ' . $lastName;
    $email     = trim($_POST['email']);
    $phone     = trim($_POST['phone']);
    $hubId     = trim($_POST['hub_id']);
    $role      = trim($_POST['staff_role']);
    $pass      = $_POST['password'];

    try {
        // 1. Create User in Firebase Auth
        $authProps = [
            'email' => $email,
            'password' => $pass,
            'displayName' => $name,
        ];
        $createdUser = $auth->createUser($authProps);
        $usrId = $createdUser->uid;
        
        // 2. Add to RTDB users node
        $stfId = 'STF-' . strtoupper(substr(uniqid(), -6));
        $db->getReference('users/' . $usrId)->set([
            'Usr_ID' => $usrId,
            'Usr_Name' => $name,
            'Usr_Email' => $email,
            'Usr_Phone' => $phone,
            'Usr_Type' => 'staff',
            'Usr_Status' => 'Active',
            'Stf_ID' => $stfId,
            'hub_id' => $hubId,
            'Stf_Role' => $role
        ]);
        
        $_SESSION['toast_success'] = "Staff member \"$name\" added successfully!";
    } catch (\Kreait\Firebase\Exception\Auth\EmailExists $e) {
        $_SESSION['toast_error'] = "Email already exists.";
    } catch (Exception $e) {
        $_SESSION['toast_error'] = "Failed to add staff: " . $e->getMessage();
    }
    
    header("Location: manage_staff.php"); exit();
}

// ---- HANDLE TOGGLE STATUS (via USER_ACCOUNT only) ----
if(isset($_POST['toggle_status'])){
    csrf_verify();
    $usrId = $_POST['usr_id'];
    $newSt = $_POST['new_status'];
    // Whitelist the only two valid statuses
    if(!in_array($newSt, ['Active','Inactive'])) {
        header("Location: manage_staff.php"); exit();
    }
    try {
        $db->getReference('users/' . $usrId)->update(['Usr_Status' => $newSt]);
        // Also disable/enable in Auth
        if ($newSt === 'Inactive') {
            $auth->disableUser($usrId);
        } else {
            $auth->enableUser($usrId);
        }
        $_SESSION['toast_success'] = "Staff status updated.";
    } catch (Exception $e) {
        $_SESSION['toast_error'] = "Failed to update status.";
    }
    header("Location: manage_staff.php"); exit();
}

// ---- HANDLE DELETE ----
if(isset($_POST['delete_staff'])){
    csrf_verify();
    $usrId = $_POST['usr_id'];

    try {
        $db->getReference('users/' . $usrId)->remove();
        $auth->deleteUser($usrId);
        $_SESSION['toast_success'] = "Staff member removed.";
    } catch (Exception $e) {
        $_SESSION['toast_error'] = "Failed to remove staff.";
    }
    header("Location: manage_staff.php"); exit();
}

// ---- FETCH DATA ----
$search       = trim(strtolower($_GET['q'] ?? ''));
$filterStatus = trim($_GET['status'] ?? '');

$hubsMap = [];
$hubsSnap = $db->getReference('hubs')->getSnapshot();
if ($hubsSnap->hasChildren()) {
    foreach ($hubsSnap->getValue() as $k => $h) {
        $hubsMap[$k] = $h;
    }
}

$staffList = [];
$totalStf = 0;
$activeStf = 0;
$inactiveStf = 0;

$usersSnap = $db->getReference('users')->getSnapshot();
if ($usersSnap->hasChildren()) {
    foreach ($usersSnap->getValue() as $u) {
        if (($u['Usr_Type'] ?? '') === 'staff') {
            $totalStf++;
            $status = $u['Usr_Status'] ?? 'Active';
            if ($status === 'Active') $activeStf++;
            else $inactiveStf++;

            if ($filterStatus && $status !== $filterStatus) continue;
            if ($search) {
                $match = str_contains(strtolower($u['Usr_Name'] ?? ''), $search) ||
                         str_contains(strtolower($u['Usr_Email'] ?? ''), $search) ||
                         str_contains(strtolower($u['Stf_Role'] ?? ''), $search);
                if (!$match) continue;
            }

            // Bind hub info
            $hubId = $u['hub_id'] ?? '';
            $u['Hub_Name'] = $hubsMap[$hubId]['Hub_Name'] ?? '';
            $u['Hub_Area'] = $hubsMap[$hubId]['Hub_Area'] ?? '';

            $staffList[] = $u;
        }
    }
    
    usort($staffList, function($a, $b) {
        $sa = $a['Usr_Status'] ?? '';
        $sb = $b['Usr_Status'] ?? '';
        if ($sa !== $sb) return strcmp($sa, $sb); // Active before Inactive
        return strcmp($a['Usr_Name'] ?? '', $b['Usr_Name'] ?? '');
    });
}

include "../layout/dashboard_layout.php";
?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <h1>Manage Staff</h1>
        <p>View and manage hub staff accounts, roles, and hub assignments</p>
    </div>
    <button class="btn-nv" onclick="document.getElementById('addStaffModal').style.display='flex'">
        <i class="bi bi-person-plus-fill"></i> Add Staff
    </button>
</div>

<!-- STATS -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="bi bi-person-badge-fill"></i></div>
            <div class="stat-value"><?= $totalStf ?></div>
            <div class="stat-label">Total Staff</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-value"><?= $activeStf ?></div>
            <div class="stat-label">Active</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card red">
            <div class="stat-icon red"><i class="bi bi-pause-circle-fill"></i></div>
            <div class="stat-value"><?= $inactiveStf ?></div>
            <div class="stat-label">Inactive</div>
        </div>
    </div>
</div>

<!-- SEARCH + FILTER -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
    <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px;">
        <?php if($filterStatus): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>"><?php endif; ?>
        <div style="position:relative;flex:1;">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);"></i>
            <input type="text" name="q" class="nv-input" style="padding-left:36px;" placeholder="Search name, email or role..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn-nv">Search</button>
    </form>
    <div style="display:flex;gap:6px;">
        <?php foreach(['' => 'All', 'Active' => 'Active', 'Inactive' => 'Inactive'] as $k => $v): ?>
        <a href="?status=<?= $k ?><?= $search ? '&q='.urlencode($search) : '' ?>"
           style="padding:7px 16px;border-radius:50px;font-size:12px;font-weight:600;text-decoration:none;<?= $filterStatus===$k?'background:var(--ink);color:#fff;':'background:var(--surface-2);color:var(--muted);border:1.5px solid var(--border);' ?>">
            <?= $v ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- TABLE -->
<div class="nv-card"><div style="overflow-x:auto;">
    <table class="nv-table">
        <thead><tr>
            <th>Staff Member</th>
            <th>Role</th>
            <th>Hub</th>
            <th>Phone</th>
            <th>Status</th>
            <th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if(empty($staffList)): ?>
            <tr><td colspan="6">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-person-badge"></i></div>
                    <h4>No staff members found</h4>
                    <p>Click "Add Staff" to create the first staff account.</p>
                </div>
            </td></tr>
        <?php else: foreach($staffList as $r): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#1a1a2e,#16213e);display:flex;align-items:center;justify-content:center;color:#fff;font-family:'Sora',sans-serif;font-weight:700;font-size:14px;flex-shrink:0;">
                            <?= strtoupper(substr($r['Usr_Name'] ?? 'S',0,1)) ?>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($r['Usr_Name'] ?? '') ?></div>
                            <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($r['Usr_Email'] ?? '') ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;background:rgba(59,130,246,0.08);color:#1d4ed8;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;">
                        <i class="bi bi-person-badge"></i> <?= htmlspecialchars($r['Stf_Role'] ?? '—') ?>
                    </span>
                </td>
                <td style="font-size:13px;">
                    <?= $r['Hub_Name'] ? htmlspecialchars($r['Hub_Name']) . '<br><span style="font-size:11px;color:var(--muted);">'.htmlspecialchars($r['Hub_Area']).'</span>' : '<span style="color:var(--muted);">—</span>' ?>
                </td>
                <td style="font-size:13px;color:var(--muted);"><?= htmlspecialchars($r['Usr_Phone'] ?? '—') ?></td>
                <td>
                    <span class="badge-status <?= ($r['Usr_Status']??'')==='Active'?'badge-active':'badge-inactive' ?>">
                        <?= $r['Usr_Status'] ?? '' ?>
                    </span>
                </td>
                <td>
                    <div style="display:flex;gap:4px;">
                        <!-- Toggle status -->
                        <form method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="usr_id" value="<?= $r['Usr_ID'] ?>">
                            <input type="hidden" name="new_status" value="<?= ($r['Usr_Status']??'')==='Active'?'Inactive':'Active' ?>">
                            <button type="submit" name="toggle_status" class="btn-icon" title="<?= ($r['Usr_Status']??'')==='Active'?'Deactivate':'Activate' ?>">
                                <i class="bi bi-<?= ($r['Usr_Status']??'')==='Active'?'pause-fill':'play-fill' ?>"></i>
                            </button>
                        </form>
                        <!-- Delete -->
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this staff member? This cannot be undone.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="usr_id" value="<?= $r['Usr_ID'] ?>">
                            <button type="submit" name="delete_staff" class="btn-icon danger" title="Delete">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div></div>

<!-- ===========================
     ADD STAFF MODAL
=========================== -->
<div id="addStaffModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.15);width:100%;max-width:520px;overflow:hidden;">

        <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h5 style="margin:0;font-size:16px;">Add New Staff Member</h5>
                <p style="margin:4px 0 0;font-size:12px;color:var(--muted);">Creates a login account + staff record</p>
            </div>
            <button onclick="document.getElementById('addStaffModal').style.display='none'"
                    style="border:none;background:none;font-size:20px;cursor:pointer;color:var(--muted);line-height:1;">×</button>
        </div>

        <form method="POST" style="padding:24px;">
            <?= csrf_field() ?>
            <div class="row g-3">

                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" class="nv-input" placeholder="e.g. Maria" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" class="nv-input" placeholder="e.g. Santos" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="nv-input" placeholder="staff@ninjavan.com" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" id="phoneInputStaff" class="nv-input" placeholder="09XX XXX XXXX">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Staff Role</label>
                        <select name="staff_role" class="nv-input" required>
                            <option value="">Select role...</option>
                            <option value="Hub Sorter">Hub Sorter</option>
                            <option value="Hub Cashier">Hub Cashier</option>
                            <option value="Hub Supervisor">Hub Supervisor</option>
                            <option value="Dispatcher">Dispatcher</option>
                            <option value="Walk-in Agent">Walk-in Agent</option>
                            <option value="Operations Manager">Operations Manager</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Assigned Hub</label>
                        <select name="hub_id" class="nv-input" required>
                            <option value="">Select hub...</option>
                            <?php if(!empty($hubsMap)): foreach($hubsMap as $h): ?>
                            <option value="<?= $h['Hub_ID'] ?>"><?= htmlspecialchars($h['Hub_Name']??'') ?> (<?= htmlspecialchars($h['Hub_Area']??'') ?>)</option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                </div>

                <div class="col-12">
                    <div class="nv-form-group">
                        <label>Temporary Password</label>
                        <input type="password" name="password" class="nv-input" placeholder="Min. 8 characters" minlength="8" required>
                    </div>
                </div>

            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
                <button type="button" class="btn-nv-ghost"
                        onclick="document.getElementById('addStaffModal').style.display='none'">
                    Cancel
                </button>
                <button type="submit" name="add_staff" class="btn-nv">
                    <i class="bi bi-person-plus-fill"></i> Create Staff
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Close modal on backdrop click -->
<script>
document.getElementById('addStaffModal').addEventListener('click', function(e){
    if(e.target === this) this.style.display = 'none';
});

// Phone trapping
const pStaff = document.getElementById('phoneInputStaff');
if(pStaff) {
    pStaff.addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/[^0-9]/g, '');
    });
}

// Password match trapping
const pw1S = document.getElementById('pwStaff');
const pw2S = document.getElementById('confirmStaff');
if(pw1S && pw2S) {
    pw2S.addEventListener('input', () => {
        if(pw2S.value === '') { pw2S.style.borderColor = 'var(--nv-border)'; return; }
        if(pw2S.value === pw1S.value) { pw2S.style.borderColor = '#10b981'; } 
        else { pw2S.style.borderColor = 'var(--nv-red)'; }
    });
}
</script>

<?php include "../layout/dashboard_footer.php"; ?>
