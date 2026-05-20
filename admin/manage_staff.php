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
    $firstName = $conn->real_escape_string(trim($_POST['first_name']));
    $lastName  = $conn->real_escape_string(trim($_POST['last_name']));
    $name      = $firstName . ' ' . $lastName;
    $email     = $conn->real_escape_string(trim($_POST['email']));
    $phone     = $conn->real_escape_string(trim($_POST['phone']));
    $hubId     = $conn->real_escape_string($_POST['hub_id']);
    $role      = $conn->real_escape_string($_POST['staff_role']);
    $pass      = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Check email uniqueness
    $chk = $conn->query("SELECT Usr_ID FROM USER_ACCOUNT WHERE Usr_Email='$email'");
    if($chk && $chk->num_rows > 0){
        $_SESSION['toast_error'] = "Email already exists.";
        header("Location: manage_staff.php"); exit();
    }

    // Create user account (Usr_Type = 'staff')
    $usrId = 'USR-' . strtoupper(substr(uniqid(), -6));
    $conn->query("INSERT INTO USER_ACCOUNT (Usr_ID, Usr_Name, Usr_Email, Usr_Pass, Usr_Phone, Usr_Type, Usr_Status)
                  VALUES ('$usrId','$name','$email','$pass','$phone','staff','Active')");

    // Create STAFF record — only columns that exist: Stf_ID, Stf_UsrID, Stf_HubID, Stf_Role
    $stfId = 'STF-' . strtoupper(substr(uniqid(), -6));
    $conn->query("INSERT INTO STAFF (Stf_ID, Stf_UsrID, Stf_HubID, Stf_Role)
                  VALUES ('$stfId','$usrId','$hubId','$role')");

    $_SESSION['toast_success'] = "Staff member \"$name\" added successfully!";
    header("Location: manage_staff.php"); exit();
}

// ---- HANDLE TOGGLE STATUS (via USER_ACCOUNT only) ----
if(isset($_POST['toggle_status'])){
    $usrId = $conn->real_escape_string($_POST['usr_id']);
    $newSt = $conn->real_escape_string($_POST['new_status']);
    $conn->query("UPDATE USER_ACCOUNT SET Usr_Status='$newSt' WHERE Usr_ID='$usrId'");
    $_SESSION['toast_success'] = "Staff status updated.";
    header("Location: manage_staff.php"); exit();
}

// ---- HANDLE DELETE ----
if(isset($_POST['delete_staff'])){
    $stfId = $conn->real_escape_string($_POST['stf_id']);
    $usrId = $conn->real_escape_string($_POST['usr_id']);
    $conn->query("DELETE FROM STAFF WHERE Stf_ID='$stfId'");
    $conn->query("DELETE FROM USER_ACCOUNT WHERE Usr_ID='$usrId'");
    $_SESSION['toast_success'] = "Staff member removed.";
    header("Location: manage_staff.php"); exit();
}

// ---- FETCH DATA ----
$search       = $conn->real_escape_string($_GET['q'] ?? '');
$filterStatus = $conn->real_escape_string($_GET['status'] ?? '');

$where = "WHERE u.Usr_Type='staff'";
if($search)       $where .= " AND (u.Usr_Name LIKE '%$search%' OR u.Usr_Email LIKE '%$search%' OR s.Stf_Role LIKE '%$search%')";
if($filterStatus) $where .= " AND u.Usr_Status='$filterStatus'";

$staff = $conn->query("
    SELECT s.Stf_ID, s.Stf_Role,
           u.Usr_ID, u.Usr_Name, u.Usr_Email, u.Usr_Phone, u.Usr_Status,
           h.Hub_Name, h.Hub_Area
    FROM STAFF s
    JOIN USER_ACCOUNT u ON s.Stf_UsrID = u.Usr_ID
    LEFT JOIN HUB h ON s.Stf_HubID = h.Hub_ID
    $where
    ORDER BY u.Usr_Status ASC, u.Usr_Name ASC
");

$totalStf    = $conn->query("SELECT COUNT(*) c FROM STAFF")->fetch_assoc()['c'];
$activeStf   = $conn->query("SELECT COUNT(*) c FROM STAFF s JOIN USER_ACCOUNT u ON s.Stf_UsrID=u.Usr_ID WHERE u.Usr_Status='Active'")->fetch_assoc()['c'];
$inactiveStf = $totalStf - $activeStf;

// Hubs for add form
$hubs = $conn->query("SELECT * FROM HUB ORDER BY Hub_Name");

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
        <?php if(!$staff || $staff->num_rows === 0): ?>
            <tr><td colspan="6">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-person-badge"></i></div>
                    <h4>No staff members found</h4>
                    <p>Click "Add Staff" to create the first staff account.</p>
                </div>
            </td></tr>
        <?php else: while($r = $staff->fetch_assoc()): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#1a1a2e,#16213e);display:flex;align-items:center;justify-content:center;color:#fff;font-family:'Sora',sans-serif;font-weight:700;font-size:14px;flex-shrink:0;">
                            <?= strtoupper(substr($r['Usr_Name'],0,1)) ?>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($r['Usr_Name']) ?></div>
                            <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($r['Usr_Email']) ?></div>
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
                    <span class="badge-status <?= $r['Usr_Status']==='Active'?'badge-active':'badge-inactive' ?>">
                        <?= $r['Usr_Status'] ?>
                    </span>
                </td>
                <td>
                    <div style="display:flex;gap:4px;">
                        <!-- Toggle status -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="stf_id" value="<?= $r['Stf_ID'] ?>">
                            <input type="hidden" name="usr_id" value="<?= $r['Usr_ID'] ?>">
                            <input type="hidden" name="new_status" value="<?= $r['Usr_Status']==='Active'?'Inactive':'Active' ?>">
                            <button type="submit" name="toggle_status" class="btn-icon" title="<?= $r['Usr_Status']==='Active'?'Deactivate':'Activate' ?>">
                                <i class="bi bi-<?= $r['Usr_Status']==='Active'?'pause-fill':'play-fill' ?>"></i>
                            </button>
                        </form>
                        <!-- Delete -->
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this staff member? This cannot be undone.')">
                            <input type="hidden" name="stf_id" value="<?= $r['Stf_ID'] ?>">
                            <input type="hidden" name="usr_id" value="<?= $r['Usr_ID'] ?>">
                            <button type="submit" name="delete_staff" class="btn-icon danger" title="Delete">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endwhile; endif; ?>
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
                        <input type="text" name="phone" class="nv-input" placeholder="09XX XXX XXXX">
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
                            <?php if($hubs): while($h = $hubs->fetch_assoc()): ?>
                            <option value="<?= $h['Hub_ID'] ?>"><?= htmlspecialchars($h['Hub_Name']) ?> (<?= htmlspecialchars($h['Hub_Area']) ?>)</option>
                            <?php endwhile; endif; ?>
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
</script>

<?php include "../layout/dashboard_footer.php"; ?>
