<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Manage Hubs";
$activePage = "hubs";

// ---- HANDLE ADD HUB ----
if(isset($_POST['add_hub'])){
    csrf_verify();
    $name  = trim($_POST['hub_name']);
    $addr  = trim($_POST['hub_addr']);
    $phone = trim($_POST['hub_phone']);
    $type  = trim($_POST['hub_type']);
    $area  = trim($_POST['hub_area']);

    // Sequential ID generation: HUB00001, HUB00002, etc.
    $res = $conn->query("SELECT Hub_ID FROM HUB ORDER BY Hub_ID DESC LIMIT 1");
    if($res && $res->num_rows > 0) {
        $lastId = $res->fetch_assoc()['Hub_ID'];
        $num = intval(preg_replace('/[^0-9]/', '', $lastId));
        $newId = 'HUB' . str_pad($num + 1, 5, '0', STR_PAD_LEFT);
    } else {
        $newId = 'HUB00001';
    }

    $stmt = $conn->prepare(
        "INSERT INTO HUB (Hub_ID, Hub_Name, Hub_Addr, Hub_Phone, Hub_Type, Hub_Area)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('ssssss', $newId, $name, $addr, $phone, $type, $area);
    $q = $stmt->execute();
    $stmt->close();

    if($q) {
        $_SESSION['toast_success'] = "Hub \"$name\" created successfully!";
    } else {
        $_SESSION['toast_error'] = "Failed to create Hub. Please check data format.";
    }
    header("Location: manage_hubs.php"); exit();
}

// ---- HANDLE EDIT HUB ----
if(isset($_POST['edit_hub'])){
    csrf_verify();
    $id    = trim($_POST['hub_id']);
    $name  = trim($_POST['hub_name']);
    $addr  = trim($_POST['hub_addr']);
    $phone = trim($_POST['hub_phone']);
    $type  = trim($_POST['hub_type']);
    $area  = trim($_POST['hub_area']);

    $stmt = $conn->prepare(
        "UPDATE HUB SET Hub_Name=?, Hub_Addr=?, Hub_Phone=?, Hub_Type=?, Hub_Area=? WHERE Hub_ID=?"
    );
    $stmt->bind_param('ssssss', $name, $addr, $phone, $type, $area, $id);
    $q = $stmt->execute();
    $stmt->close();

    if($q) {
        $_SESSION['toast_success'] = "Hub \"$name\" updated successfully!";
    } else {
        $_SESSION['toast_error'] = "Failed to update hub.";
    }
    header("Location: manage_hubs.php"); exit();
}

// ---- HANDLE DELETE HUB ----
if(isset($_POST['delete_hub'])){
    csrf_verify();
    $id = trim($_POST['hub_id']);

    // Check dependencies — prepared statements
    $chkStaff = $conn->prepare("SELECT COUNT(*) as c FROM STAFF WHERE Stf_HubID = ?");
    $chkStaff->bind_param('s', $id);
    $chkStaff->execute();
    $staffCount = $chkStaff->get_result()->fetch_assoc()['c'];
    $chkStaff->close();

    $chkRider = $conn->prepare("SELECT COUNT(*) as c FROM RIDER WHERE Rdr_HubID = ?");
    $chkRider->bind_param('s', $id);
    $chkRider->execute();
    $riderCount = $chkRider->get_result()->fetch_assoc()['c'];
    $chkRider->close();

    $chkShip = $conn->prepare("SELECT COUNT(*) as c FROM SHIPMENT WHERE Shpm_HubID = ?");
    $chkShip->bind_param('s', $id);
    $chkShip->execute();
    $shipCount = $chkShip->get_result()->fetch_assoc()['c'];
    $chkShip->close();

    if($staffCount > 0 || $riderCount > 0 || $shipCount > 0) {
        $_SESSION['toast_error'] = "Cannot delete hub. It has assigned staff ($staffCount), riders ($riderCount), or shipments ($shipCount).";
    } else {
        $del = $conn->prepare("DELETE FROM HUB WHERE Hub_ID = ?");
        $del->bind_param('s', $id);
        $q = $del->execute();
        $del->close();
        if($q) {
            $_SESSION['toast_success'] = "Hub deleted successfully.";
        } else {
            $_SESSION['toast_error'] = "Failed to delete hub.";
        }
    }
    header("Location: manage_hubs.php"); exit();
}

// ---- FETCH DATA ----
$search = $conn->real_escape_string($_GET['q'] ?? '');
$where = "";
if($search) {
    $where = "WHERE Hub_Name LIKE '%$search%' OR Hub_Addr LIKE '%$search%' OR Hub_Area LIKE '%$search%' OR Hub_Type LIKE '%$search%'";
}

$hubs = $conn->query("
    SELECT h.*, 
           (SELECT COUNT(*) FROM STAFF WHERE Stf_HubID = h.Hub_ID) as staff_count,
           (SELECT COUNT(*) FROM RIDER WHERE Rdr_HubID = h.Hub_ID) as rider_count,
           (SELECT COUNT(*) FROM SHIPMENT WHERE Shpm_HubID = h.Hub_ID) as shipment_count
    FROM HUB h
    $where
    ORDER BY h.Hub_ID ASC
");

// Top statistics
$totalHubs = $conn->query("SELECT COUNT(*) c FROM HUB")->fetch_assoc()['c'];
$distCenters = $conn->query("SELECT COUNT(*) c FROM HUB WHERE Hub_Type='Distribution Center'")->fetch_assoc()['c'];
$sortHubs = $conn->query("SELECT COUNT(*) c FROM HUB WHERE Hub_Type='Sorting Hub'")->fetch_assoc()['c'];

include "../layout/dashboard_layout.php";
?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <h1>Manage Hubs & Branches</h1>
        <p>Create hubs, set operational locations, and monitor hub metrics</p>
    </div>
    <button class="btn-nv" onclick="document.getElementById('addHubModal').style.display='flex'">
        <i class="bi bi-geo-fill"></i> Add New Hub
    </button>
</div>

<!-- STATS -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="bi bi-geo-alt-fill"></i></div>
            <div class="stat-value"><?= $totalHubs ?></div>
            <div class="stat-label">Total Hubs</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="bi bi-box-seam-fill"></i></div>
            <div class="stat-value"><?= $distCenters ?></div>
            <div class="stat-label">Distribution Centers</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card red">
            <div class="stat-icon red"><i class="bi bi-diagram-3-fill"></i></div>
            <div class="stat-value"><?= $sortHubs ?></div>
            <div class="stat-label">Sorting Hubs</div>
        </div>
    </div>
</div>

<!-- FILTER / SEARCH BAR -->
<div class="nv-card mb-4" style="padding: 16px;">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-9 col-sm-8">
            <div class="nv-form-group mb-0" style="position:relative;">
                <input type="text" name="q" class="nv-input" style="padding-left:36px;" 
                       placeholder="Search hubs by name, address, area, type..." value="<?= htmlspecialchars($search) ?>">
                <i class="bi bi-search" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);"></i>
            </div>
        </div>
        <div class="col-md-3 col-sm-4 d-flex gap-2">
            <button type="submit" class="btn-nv w-100 justify-content-center">Search</button>
            <?php if($search): ?>
                <a href="manage_hubs.php" class="btn-nv-ghost justify-content-center" style="white-space:nowrap;">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- HUBS TABLE -->
<div class="nv-card">
    <div class="table-responsive">
        <table class="nv-table">
            <thead>
                <tr>
                    <th>Hub ID</th>
                    <th>Hub Name</th>
                    <th>Type</th>
                    <th>Operational Area</th>
                    <th>Contact Phone</th>
                    <th>Address</th>
                    <th style="text-align:center;">Staff</th>
                    <th style="text-align:center;">Riders</th>
                    <th style="text-align:center;">Active Parcels</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if($hubs && $hubs->num_rows > 0): while($h = $hubs->fetch_assoc()): ?>
                <tr>
                    <td><strong style="color:var(--red); font-family:'Sora',sans-serif;"><?= htmlspecialchars($h['Hub_ID']) ?></strong></td>
                    <td><strong><?= htmlspecialchars($h['Hub_Name']) ?></strong></td>
                    <td>
                        <span class="badge-status <?= strtolower(str_replace(' ', '-', $h['Hub_Type'])) === 'distribution-center' ? 'badge-confirmed' : 'badge-pending' ?>" style="font-size:10px;">
                            <?= htmlspecialchars($h['Hub_Type']) ?>
                        </span>
                    </td>
                    <td><span class="badge-status badge-active"><?= htmlspecialchars($h['Hub_Area']) ?></span></td>
                    <td><?= htmlspecialchars($h['Hub_Phone']) ?: '<span style="color:var(--muted);">—</span>' ?></td>
                    <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($h['Hub_Addr']) ?>">
                        <?= htmlspecialchars($h['Hub_Addr']) ?>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge-status badge-confirmed" style="padding: 2px 8px; border-radius: 4px;"><?= $h['staff_count'] ?></span>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge-status badge-picked" style="padding: 2px 8px; border-radius: 4px;"><?= $h['rider_count'] ?></span>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge-status badge-transit" style="padding: 2px 8px; border-radius: 4px;"><?= $h['shipment_count'] ?></span>
                    </td>
                    <td style="text-align:right;">
                        <div class="d-flex gap-1 justify-content-end">
                            <!-- Edit button -->
                            <button class="btn-icon" title="Edit Hub" onclick="openEditModal(
                                '<?= addslashes($h['Hub_ID']) ?>',
                                '<?= addslashes($h['Hub_Name']) ?>',
                                '<?= addslashes($h['Hub_Addr']) ?>',
                                '<?= addslashes($h['Hub_Phone']) ?>',
                                '<?= addslashes($h['Hub_Type']) ?>',
                                '<?= addslashes($h['Hub_Area']) ?>'
                            )">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <!-- Delete button -->
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this hub? This cannot be undone.')">
                                <input type="hidden" name="hub_id" value="<?= $h['Hub_ID'] ?>">
                                <button type="submit" name="delete_hub" class="btn-icon danger" title="Delete Hub">
                                    <i class="bi bi-trash3-fill"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="bi bi-geo-alt-fill"></i></div>
                            <h4>No hubs found</h4>
                            <p>Try searching with another keyword or add a new hub to the network.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===========================
     ADD HUB MODAL
=========================== -->
<div id="addHubModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.15);width:100%;max-width:520px;overflow:hidden;">
        <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h5 style="margin:0;font-size:16px;">Add New Hub / Branch</h5>
                <p style="margin:4px 0 0;font-size:12px;color:var(--muted);">Expands the delivery network node</p>
            </div>
            <button onclick="document.getElementById('addHubModal').style.display='none'"
                    style="border:none;background:none;font-size:20px;cursor:pointer;color:var(--muted);line-height:1;">×</button>
        </div>

        <form method="POST" style="padding:24px;">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-12">
                    <div class="nv-form-group">
                        <label>Hub Name *</label>
                        <input type="text" name="hub_name" class="nv-input" placeholder="e.g. Cebu Main Sorting Hub" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Hub Type *</label>
                        <select name="hub_type" class="nv-input" required>
                            <option value="Distribution Center">Distribution Center</option>
                            <option value="Sorting Hub">Sorting Hub</option>
                            <option value="Pickup Point">Pickup Point</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Operational Area *</label>
                        <select name="hub_area" class="nv-input" required>
                            <option value="Metro Manila">Metro Manila</option>
                            <option value="Luzon">Luzon</option>
                            <option value="Visayas">Visayas</option>
                            <option value="Mindanao">Mindanao</option>
                        </select>
                    </div>
                </div>

                <div class="col-12">
                    <div class="nv-form-group">
                        <label>Contact Phone Number *</label>
                        <input type="text" name="hub_phone" class="nv-input" placeholder="e.g. 09171234567" required>
                    </div>
                </div>

                <div class="col-12">
                    <div class="nv-form-group">
                        <label>Complete Address *</label>
                        <textarea name="hub_addr" class="nv-input" rows="2" placeholder="e.g. M.J. Cuenco Ave, Cebu City" required></textarea>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
                <button type="button" class="btn-nv-ghost"
                        onclick="document.getElementById('addHubModal').style.display='none'">
                    Cancel
                </button>
                <button type="submit" name="add_hub" class="btn-nv">
                    <i class="bi bi-plus-circle-fill"></i> Add Hub
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===========================
     EDIT HUB MODAL
=========================== -->
<div id="editHubModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.15);width:100%;max-width:520px;overflow:hidden;">
        <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h5 style="margin:0;font-size:16px;">Edit Hub Details</h5>
                <p style="margin:4px 0 0;font-size:12px;color:var(--muted);">Update operational node properties</p>
            </div>
            <button onclick="document.getElementById('editHubModal').style.display='none'"
                    style="border:none;background:none;font-size:20px;cursor:pointer;color:var(--muted);line-height:1;">×</button>
        </div>

        <form method="POST" style="padding:24px;">
            <?= csrf_field() ?>
            <input type="hidden" name="hub_id" id="edit_hub_id">
            
            <div class="row g-3">
                <div class="col-12">
                    <div class="nv-form-group">
                        <label>Hub Name *</label>
                        <input type="text" name="hub_name" id="edit_hub_name" class="nv-input" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Hub Type *</label>
                        <select name="hub_type" id="edit_hub_type" class="nv-input" required>
                            <option value="Distribution Center">Distribution Center</option>
                            <option value="Sorting Hub">Sorting Hub</option>
                            <option value="Pickup Point">Pickup Point</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="nv-form-group">
                        <label>Operational Area *</label>
                        <select name="hub_area" id="edit_hub_area" class="nv-input" required>
                            <option value="Metro Manila">Metro Manila</option>
                            <option value="Luzon">Luzon</option>
                            <option value="Visayas">Visayas</option>
                            <option value="Mindanao">Mindanao</option>
                        </select>
                    </div>
                </div>

                <div class="col-12">
                    <div class="nv-form-group">
                        <label>Contact Phone Number *</label>
                        <input type="text" name="hub_phone" id="edit_hub_phone" class="nv-input" required>
                    </div>
                </div>

                <div class="col-12">
                    <div class="nv-form-group">
                        <label>Complete Address *</label>
                        <textarea name="hub_addr" id="edit_hub_addr" class="nv-input" rows="2" required></textarea>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
                <button type="button" class="btn-nv-ghost"
                        onclick="document.getElementById('editHubModal').style.display='none'">
                    Cancel
                </button>
                <button type="submit" name="edit_hub" class="btn-nv">
                    <i class="bi bi-save-fill"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SCRIPTS -->
<script>
// Close modals on backdrop click
document.getElementById('addHubModal').addEventListener('click', function(e){
    if(e.target === this) this.style.display = 'none';
});
document.getElementById('editHubModal').addEventListener('click', function(e){
    if(e.target === this) this.style.display = 'none';
});

function openEditModal(id, name, addr, phone, type, area) {
    document.getElementById('edit_hub_id').value = id;
    document.getElementById('edit_hub_name').value = name;
    document.getElementById('edit_hub_addr').value = addr;
    document.getElementById('edit_hub_phone').value = phone;
    document.getElementById('edit_hub_type').value = type;
    document.getElementById('edit_hub_area').value = area;
    document.getElementById('editHubModal').style.display = 'flex';
}
</script>

<?php include "../layout/dashboard_footer.php"; ?>
