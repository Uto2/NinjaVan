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
    $type  = trim($_POST['hub_type']);
    $phone = trim($_POST['hub_phone']);

    $street   = trim($_POST['hub_street'] ?? '');
    $barangay = trim($_POST['hub_barangay'] ?? '');
    $city     = trim($_POST['hub_city'] ?? '');
    $province = trim($_POST['hub_province'] ?? '');
    $addr     = "$street, $barangay, $city, $province";
    $area     = $province; 

    // Manual Lat/Lng
    $lat = !empty($_POST['hub_lat']) ? (float)$_POST['hub_lat'] : null;
    $lng = !empty($_POST['hub_lng']) ? (float)$_POST['hub_lng'] : null;

    // Auto-Geocode if manual is missing
    if (!$lat || !$lng) {
        $searchQueries = [
            "$street, $barangay, $city, $province, Philippines",
            "$barangay, $city, $province, Philippines",
            "$city, $province, Philippines",
            "$province, Philippines"
        ];
        
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'header' => "User-Agent: NinjaVan PHP/1.0\r\n"]]);
        foreach ($searchQueries as $sq) {
            // Clean up double commas or leading/trailing commas
            $sq = preg_replace('/, ,/', ', ', $sq);
            $sq = trim($sq, ', ');
            
            $nomUrl = "https://nominatim.openstreetmap.org/search?q=" . urlencode($sq) . "&format=json&limit=1";
            $nomRes = @file_get_contents($nomUrl, false, $ctx);
            if ($nomRes) {
                $nomData = json_decode($nomRes, true);
                if (!empty($nomData) && isset($nomData[0]['lat'])) {
                    $lat = (float)$nomData[0]['lat'];
                    $lng = (float)$nomData[0]['lon'];
                    break;
                }
            }
            // Small sleep to avoid rate limiting if we have to try multiple queries
            if (count($searchQueries) > 1) usleep(200000);
        }
    }

    // Sequential ID generation
    $hubsSnap = $db->getReference('hubs')->getSnapshot();
    $maxIdNum = 0;
    if ($hubsSnap->hasChildren()) {
        foreach ($hubsSnap->getValue() as $k => $h) {
            $num = intval(preg_replace('/[^0-9]/', '', $k));
            if ($num > $maxIdNum) $maxIdNum = $num;
        }
    }
    $newId = 'HUB' . str_pad($maxIdNum + 1, 5, '0', STR_PAD_LEFT);

    try {
        $hubData = [
            'Hub_ID' => $newId,
            'Hub_Name' => $name,
            'Hub_Addr' => $addr,
            'Hub_Phone' => $phone,
            'Hub_Type' => $type,
            'Hub_Area' => $area
        ];
        if ($lat && $lng) {
            $hubData['Hub_Lat'] = $lat;
            $hubData['Hub_Lng'] = $lng;
        }
        $db->getReference('hubs/' . $newId)->set($hubData);
        $_SESSION['toast_success'] = "Hub \"$name\" created successfully!";
    } catch (Exception $e) {
        $_SESSION['toast_error'] = "Failed to create Hub. " . $e->getMessage();
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
    
    // Manual Lat/Lng
    $lat = !empty($_POST['hub_lat']) ? (float)$_POST['hub_lat'] : null;
    $lng = !empty($_POST['hub_lng']) ? (float)$_POST['hub_lng'] : null;

    // Auto-Geocode if manual is missing OR if address changed and manual is empty
    if (!$lat || !$lng) {
        $searchQueries = [
            $addr . ", Philippines",
        ];
        
        // Try to extract parts if it's a comma-separated address
        $parts = explode(',', $addr);
        if (count($parts) > 1) {
            $provPart = trim(end($parts));
            
            // 2. Try Brgy, City, Province
            if (count($parts) > 3) {
                $brgyPart = trim($parts[count($parts)-3]);
                $cityPart = trim($parts[count($parts)-2]);
                $searchQueries[] = "$brgyPart, $cityPart, $provPart, Philippines";
            }
            // 3. Try City, Province
            if (count($parts) > 2) {
                $cityPart = trim($parts[count($parts)-2]);
                $searchQueries[] = "$cityPart, $provPart, Philippines";
            }
            // 4. Try Province alone
            $searchQueries[] = "$provPart, Philippines";
        }

        $ctx = stream_context_create(['http' => ['timeout' => 5, 'header' => "User-Agent: NinjaVan PHP/1.0\r\n"]]);
        foreach ($searchQueries as $sq) {
            $sq = preg_replace('/, ,/', ', ', $sq);
            $sq = trim($sq, ', ');
            $nomUrl = "https://nominatim.openstreetmap.org/search?q=" . urlencode($sq) . "&format=json&limit=1";
            $nomRes = @file_get_contents($nomUrl, false, $ctx);
            if ($nomRes) {
                $nomData = json_decode($nomRes, true);
                if (!empty($nomData) && isset($nomData[0]['lat'])) {
                    $lat = (float)$nomData[0]['lat'];
                    $lng = (float)$nomData[0]['lon'];
                    break;
                }
            }
            usleep(200000);
        }
    }

    try {
        $updateData = [
            'Hub_Name' => $name,
            'Hub_Addr' => $addr,
            'Hub_Phone' => $phone,
            'Hub_Type' => $type,
            'Hub_Area' => $area
        ];
        if ($lat && $lng) {
            $updateData['Hub_Lat'] = $lat;
            $updateData['Hub_Lng'] = $lng;
        }
        $db->getReference('hubs/' . $id)->update($updateData);
        $_SESSION['toast_success'] = "Hub \"$name\" updated successfully!";
    } catch (Exception $e) {
        $_SESSION['toast_error'] = "Failed to update hub. " . $e->getMessage();
    }
    header("Location: manage_hubs.php"); exit();
}

// ---- HANDLE DELETE HUB ----
if(isset($_POST['delete_hub'])){
    csrf_verify();
    $id = trim($_POST['hub_id']);

    $staffCount = 0;
    $riderCount = 0;
    $shipCount = 0;

    $usersSnap = $db->getReference('users')->getSnapshot();
    if ($usersSnap->hasChildren()) {
        foreach ($usersSnap->getValue() as $u) {
            $t = $u['Usr_Type'] ?? '';
            $hub = $u['hub_id'] ?? ''; // generic hub_id field from migration
            if ($hub === $id) {
                if ($t === 'staff') $staffCount++;
                if ($t === 'rider') $riderCount++;
            }
        }
    }

    $ordersSnap = $db->getReference('orders')->getSnapshot();
    if ($ordersSnap->hasChildren()) {
        foreach ($ordersSnap->getValue() as $o) {
            // Check if hub is used in tracking steps
            if (isset($o['tracking'])) {
                foreach ($o['tracking'] as $trk) {
                    if (($trk['Trk_HubID'] ?? '') === $id) $shipCount++;
                }
            }
        }
    }

    if($staffCount > 0 || $riderCount > 0 || $shipCount > 0) {
        $_SESSION['toast_error'] = "Cannot delete hub. It has assigned staff ($staffCount), riders ($riderCount), or shipments ($shipCount).";
    } else {
        try {
            $db->getReference('hubs/' . $id)->remove();
            $_SESSION['toast_success'] = "Hub deleted successfully.";
        } catch (Exception $e) {
            $_SESSION['toast_error'] = "Failed to delete hub. " . $e->getMessage();
        }
    }
    header("Location: manage_hubs.php"); exit();
}

// ---- FETCH DATA ----
$search = trim($_GET['q'] ?? '');

$totalHubs = 0;
$distCenters = 0;
$sortHubs = 0;

$hubsList = [];

// Precompute staff and riders per hub
$hubStaff = [];
$hubRiders = [];
$usersSnap = $db->getReference('users')->getSnapshot();
if ($usersSnap->hasChildren()) {
    foreach ($usersSnap->getValue() as $u) {
        $t = $u['Usr_Type'] ?? '';
        $hub = $u['hub_id'] ?? '';
        if ($hub) {
            if ($t === 'staff') $hubStaff[$hub] = ($hubStaff[$hub] ?? 0) + 1;
            if ($t === 'rider') $hubRiders[$hub] = ($hubRiders[$hub] ?? 0) + 1;
        }
    }
}

// Precompute active parcels per hub
$hubParcels = [];
$ordersSnap = $db->getReference('orders')->getSnapshot();
if ($ordersSnap->hasChildren()) {
    foreach ($ordersSnap->getValue() as $o) {
        $status = $o['Ord_Status'] ?? '';
        // Approximate active parcel location based on last tracking
        if (!in_array($status, ['Delivered', 'RTS', 'Order Created']) && isset($o['tracking'])) {
            $lastTrk = end($o['tracking']);
            $hub = $lastTrk['Trk_HubID'] ?? '';
            if ($hub) $hubParcels[$hub] = ($hubParcels[$hub] ?? 0) + 1;
        }
    }
}

$hubsSnap = $db->getReference('hubs')->getSnapshot();
if ($hubsSnap->hasChildren()) {
    foreach ($hubsSnap->getValue() as $h) {
        $totalHubs++;
        if (($h['Hub_Type'] ?? '') === 'Distribution Center') $distCenters++;
        if (($h['Hub_Type'] ?? '') === 'Sorting Hub') $sortHubs++;

        if ($search) {
            $s = strtolower($search);
            $match = str_contains(strtolower($h['Hub_Name'] ?? ''), $s) ||
                     str_contains(strtolower($h['Hub_Addr'] ?? ''), $s) ||
                     str_contains(strtolower($h['Hub_Area'] ?? ''), $s) ||
                     str_contains(strtolower($h['Hub_Type'] ?? ''), $s);
            if (!$match) continue;
        }
        
        $id = $h['Hub_ID'] ?? '';
        $h['staff_count'] = $hubStaff[$id] ?? 0;
        $h['rider_count'] = $hubRiders[$id] ?? 0;
        $h['shipment_count'] = $hubParcels[$id] ?? 0;
        $hubsList[] = $h;
    }
}

// Philippine provinces list
$provinces = [
    "Metro Manila",
    "Abra","Agusan del Norte","Agusan del Sur","Aklan","Albay",
    "Antique","Apayao","Aurora","Basilan","Bataan","Batanes",
    "Batangas","Benguet","Biliran","Bohol","Bukidnon","Bulacan",
    "Cagayan","Camarines Norte","Camarines Sur","Camiguin","Capiz",
    "Catanduanes","Cavite","Cebu","Compostela Valley","Cotabato",
    "Davao de Oro","Davao del Norte","Davao del Sur","Davao Occidental",
    "Davao Oriental","Dinagat Islands","Eastern Samar","Guimaras",
    "Ifugao","Ilocos Norte","Ilocos Sur","Iloilo","Isabela","Kalinga",
    "La Union","Laguna","Lanao del Norte","Lanao del Sur","Leyte",
    "Maguindanao","Marinduque","Masbate","Misamis Occidental",
    "Misamis Oriental","Mountain Province","Negros Occidental",
    "Negros Oriental","Northern Samar","Nueva Ecija","Nueva Vizcaya",
    "Occidental Mindoro","Oriental Mindoro","Palawan","Pampanga",
    "Pangasinan","Quezon","Quirino","Rizal","Romblon","Samar",
    "Sarangani","Siquijor","Sorsogon","South Cotabato","Southern Leyte",
    "Sultan Kudarat","Sulu","Surigao del Norte","Surigao del Sur",
    "Tarlac","Tawi-Tawi","Zambales","Zamboanga del Norte",
    "Zamboanga del Sur","Zamboanga Sibugay",
];

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
                    <th>GPS (Lat, Lng)</th>
                    <th style="text-align:center;">Staff</th>
                    <th style="text-align:center;">Riders</th>
                    <th style="text-align:center;">Active Parcels</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($hubsList)): ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="bi bi-geo-alt-fill"></i></div>
                            <h4>No hubs found</h4>
                            <p>Try searching with another keyword or add a new hub to the network.</p>
                        </div>
                    </td>
                </tr>
            <?php else: foreach($hubsList as $h): ?>
                <tr>
                    <td><strong style="color:var(--red); font-family:'Sora',sans-serif;"><?= htmlspecialchars($h['Hub_ID'] ?? '') ?></strong></td>
                    <td><strong><?= htmlspecialchars($h['Hub_Name'] ?? '') ?></strong></td>
                    <td>
                        <span class="badge-status <?= strtolower(str_replace(' ', '-', $h['Hub_Type'] ?? '')) === 'distribution-center' ? 'badge-confirmed' : 'badge-pending' ?>" style="font-size:10px;">
                            <?= htmlspecialchars($h['Hub_Type'] ?? '') ?>
                        </span>
                    </td>
                    <td><span class="badge-status badge-active"><?= htmlspecialchars($h['Hub_Area'] ?? '') ?></span></td>
                    <td><?= htmlspecialchars($h['Hub_Phone'] ?? '') ?: '<span style="color:var(--muted);">—</span>' ?></td>
                    <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($h['Hub_Addr'] ?? '') ?>">
                        <?= htmlspecialchars($h['Hub_Addr'] ?? '') ?>
                    </td>
                    <td style="font-size:11px; color:var(--muted);">
                        <?php if(isset($h['Hub_Lat']) && isset($h['Hub_Lng'])): ?>
                            <?= number_format($h['Hub_Lat'], 4) ?>, <?= number_format($h['Hub_Lng'], 4) ?>
                        <?php else: ?>
                            <span style="color:var(--amber);"><i class="bi bi-exclamation-triangle"></i> No GPS</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge-status badge-confirmed" style="padding: 2px 8px; border-radius: 4px;"><?= $h['staff_count'] ?? 0 ?></span>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge-status badge-picked" style="padding: 2px 8px; border-radius: 4px;"><?= $h['rider_count'] ?? 0 ?></span>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge-status badge-transit" style="padding: 2px 8px; border-radius: 4px;"><?= $h['shipment_count'] ?? 0 ?></span>
                    </td>
                    <td style="text-align:right;">
                        <div class="d-flex gap-1 justify-content-end">
                            <!-- Edit button -->
                            <button class="btn-icon" title="Edit Hub" onclick="openEditModal(
                                '<?= addslashes($h['Hub_ID'] ?? '') ?>',
                                '<?= addslashes($h['Hub_Name'] ?? '') ?>',
                                '<?= addslashes($h['Hub_Addr'] ?? '') ?>',
                                '<?= addslashes($h['Hub_Phone'] ?? '') ?>',
                                '<?= addslashes($h['Hub_Type'] ?? '') ?>',
                                '<?= addslashes($h['Hub_Area'] ?? '') ?>',
                                '<?= addslashes($h['Hub_Lat'] ?? '') ?>',
                                '<?= addslashes($h['Hub_Lng'] ?? '') ?>'
                            )">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <!-- Delete button -->
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this hub? This cannot be undone.')">
                                <input type="hidden" name="hub_id" value="<?= $h['Hub_ID'] ?? '' ?>">
                                <button type="submit" name="delete_hub" class="btn-icon danger" title="Delete Hub">
                                    <i class="bi bi-trash3-fill"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
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
                        <input type="tel" name="hub_phone" id="addHubPhone" class="nv-input" placeholder="e.g. 09171234567" required maxlength="11" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="nv-form-group">
                        <label>Province *</label>
                        <select name="hub_province" id="addProvince" class="nv-input" required>
                            <option value="">Select province</option>
                            <?php foreach($provinces as $prov): ?>
                            <option value="<?= $prov ?>"><?= $prov ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="nv-form-group">
                        <label>City / Municipality *</label>
                        <select name="hub_city" id="addCity" class="nv-input" required>
                            <option value="">Select city/municipality</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="nv-form-group">
                        <label>Barangay *</label>
                        <select name="hub_barangay" id="addBarangay" class="nv-input" required>
                            <option value="">Select barangay</option>
                        </select>
                    </div>
                </div>

                <div class="col-12">
                    <button type="button" class="btn-nv-ghost w-100" style="font-size:12px; padding:8px;" onclick="toggleAdvanced('add')">
                        <i class="bi bi-gear"></i> <span id="add_toggle_text">Show Advanced Location Settings</span>
                    </button>
                </div>

                <div id="add_advanced_location" style="display:none; width:100%;">
                    <div class="row g-3 px-3">
                        <div class="col-md-5">
                            <div class="nv-form-group">
                                <label>Latitude</label>
                                <input type="number" step="any" name="hub_lat" id="add_hub_lat" class="nv-input" placeholder="e.g. 14.5995">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="nv-form-group">
                                <label>Longitude</label>
                                <input type="number" step="any" name="hub_lng" id="add_hub_lng" class="nv-input" placeholder="e.g. 120.9842">
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn-nv w-100" style="padding:10px;" title="Auto-locate from address" onclick="autoLocate('add')">
                                <i class="bi bi-geo-alt"></i>
                            </button>
                        </div>
                        <div class="col-12">
                            <p style="font-size:11px; color:var(--muted); margin:0;"><i class="bi bi-info-circle"></i> Use the blue button to automatically find coordinates based on the address above.</p>
                        </div>
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
                        <input type="tel" name="hub_phone" id="edit_hub_phone" class="nv-input" required maxlength="11" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                </div>

                <div class="col-12">
                    <div class="nv-form-group">
                        <label>Complete Address *</label>
                        <textarea name="hub_addr" id="edit_hub_addr" class="nv-input" rows="2" required></textarea>
                    </div>
                </div>

                <div class="col-12">
                    <button type="button" class="btn-nv-ghost w-100" style="font-size:12px; padding:8px;" onclick="toggleAdvanced('edit')">
                        <i class="bi bi-gear"></i> <span id="edit_toggle_text">Show Advanced Location Settings</span>
                    </button>
                </div>

                <div id="edit_advanced_location" style="display:none; width:100%;">
                    <div class="row g-3 px-3">
                        <div class="col-md-5">
                            <div class="nv-form-group">
                                <label>Latitude</label>
                                <input type="number" step="any" name="hub_lat" id="edit_hub_lat" class="nv-input">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="nv-form-group">
                                <label>Longitude</label>
                                <input type="number" step="any" name="hub_lng" id="edit_hub_lng" class="nv-input">
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn-nv w-100" style="padding:10px;" title="Auto-locate from address" onclick="autoLocate('edit')">
                                <i class="bi bi-geo-alt"></i>
                            </button>
                        </div>
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
function toggleAdvanced(prefix) {
    const el = document.getElementById(prefix + '_advanced_location');
    const txt = document.getElementById(prefix + '_toggle_text');
    if (el.style.display === 'none') {
        el.style.display = 'block';
        txt.textContent = 'Hide Advanced Location Settings';
    } else {
        el.style.display = 'none';
        txt.textContent = 'Show Advanced Location Settings';
    }
}

async function autoLocate(prefix) {
    let query = '';
    if (prefix === 'add') {
        const street = document.getElementsByName('hub_street')[0].value;
        const brgy = document.getElementById('addBarangay').value;
        const city = document.getElementById('addCity').value;
        const prov = document.getElementById('addProvince').value;
        if (!city || !prov) { alert('Please select at least Province and City first.'); return; }
        query = `${street}, ${brgy}, ${city}, ${prov}, Philippines`;
    } else {
        query = document.getElementById('edit_hub_addr').value + ', Philippines';
    }

    const btn = event.currentTarget;
    const oldHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    btn.disabled = true;

    try {
        const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&limit=1`;
        const res = await fetch(url, { headers: { 'User-Agent': 'NinjaVan PHP/1.0' } });
        const data = await res.json();

        if (data && data.length > 0) {
            document.getElementById(prefix + '_hub_lat').value = data[0].lat;
            document.getElementById(prefix + '_hub_lng').value = data[0].lon;
            
            // If it was hidden, show it to show success
            if (document.getElementById(prefix + '_advanced_location').style.display === 'none') {
                toggleAdvanced(prefix);
            }
        } else {
            alert('Could not find precise location for this address. Try making it simpler (e.g. just Barangay and City).');
        }
    } catch (e) {
        console.error(e);
        alert('Geocoding service error. Please try again later.');
    } finally {
        btn.innerHTML = oldHtml;
        btn.disabled = false;
    }
}
// Phone trapping
document.getElementById('addHubPhone').addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/[^0-9]/g, '');
});
document.getElementById('edit_hub_phone').addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/[^0-9]/g, '');
});

// Location Dropdowns Logic
const phLocations = {
    "Metro Manila": {
        "Makati City": ["Bel-Air", "San Lorenzo", "Urdaneta", "Guadalupe Nuevo", "Pembo", "Rizal", "Poblacion", "Tejeros", "Pio del Pilar"],
        "Quezon City": ["Commonwealth", "Batasan Hills", "San Jose", "Bagong Silangan", "Payatas", "Socorro", "Novaliches", "Diliman"],
        "Taguig City": ["Fort Bonifacio", "Central Signal Village", "Western Bicutan", "Ususan", "Tuktukan", "Pinagsama"],
        "Manila": ["Ermita", "Malate", "Intramuros", "Binondo", "Quiapo", "Sampaloc", "Tondo", "Paco", "Santa Cruz"],
        "Pasig City": ["San Antonio", "Kapitolyo", "Caniogan", "Manggahan", "Rosario", "Bambang"]
    },
    "Cebu": {
        "Cebu City": ["Lahug", "Mabolo", "Guadalupe", "Talamban", "Apas", "Capitol Site", "Banilad", "Pardo", "Tisa"],
        "Mandaue City": ["Tipolo", "Banilad", "Bakilid", "Subangdaku", "Centro", "Looc", "Cabancalan", "Maguikay"],
        "Lapu-Lapu City": ["Maribago", "Mactan", "Pajo", "Basak", "Gun-ob", "Babag", "Punta Engaño"],
        "Consolacion": ["Tugbongan", "Nangka", "Pitogo", "Tayud", "Poblacion Occidental", "Poblacion Oriental"]
    },
    "Davao del Sur": {
        "Davao City": ["Buhangin", "Talomo", "Agdao", "Toril", "Bunawan", "Poblacion", "Matina Crossing", "Ma-a"]
    },
    "Bulacan": {
        "Malolos": ["Sumapang Bata", "Dakila", "San Vicente", "Mojon", "Catmon", "Bagasbas"],
        "Meycauayan": ["Calvario", "Banga", "Saluysoy", "Libtong", "Bancal"]
    },
    "Cavite": {
        "Imus": ["Anabu I-A", "Bucandala I", "Malagasang I-A", "Poblacion", "Toclong"],
        "Dasmariñas": ["Salawag", "Langkaan I", "Sampaloc I", "Paliparan III", "San Jose"]
    },
    "Laguna": {
        "Calamba": ["Canlubang", "Real", "Pansol", "Bucal", "Halang", "Parian"],
        "Santa Rosa": ["Balibago", "Don Jose", "Macabling", "Tagapo", "Market Area", "Sinalhan"]
    },
    "Pangasinan": {
        "Dagupan": ["Tapuac", "Poblacion Oeste", "Puelay", "Caranglaan", "Bonuan Gueset"],
        "Urdaneta": ["Poblacion", "Nancayasan", "Pinmaludpod", "San Vicente"]
    },
    "Pampanga": {
        "San Fernando": ["Dolores", "Sindalan", "Calulut", "San Agustin", "San Jose"],
        "Angeles City": ["Balibago", "Malabanias", "Pandan", "Sto. Cristo", "Cutcut"]
    },
    "Iloilo": {
        "Iloilo City": ["Mandurriao", "Molo", "Jaro", "La Paz", "Arevalo", "City Proper"]
    },
    "Leyte": {
        "Tacloban": ["Poblacion", "San Jose", "Abucay", "Marasbaras", "Utap"]
    },
    "Misamis Oriental": {
        "Cagayan de Oro": ["Carmen", "Balulang", "Kauswagan", "Nazareth", "Macasandig", "Lapasan"]
    },
    "Zamboanga del Sur": {
        "Zamboanga City": ["Pasonanca", "Tetuan", "Guiwan", "Santa Maria", "Poblacion"]
    }
};

function setupPhAddressDropdowns(provinceSelectId, citySelectId, barangaySelectId) {
    const provSel = document.getElementById(provinceSelectId);
    const citySel = document.getElementById(citySelectId);
    const brgySel = document.getElementById(barangaySelectId);

    function getCitiesForProvince(prov) {
        if (phLocations[prov]) {
            return Object.keys(phLocations[prov]);
        }
        return [
            prov + " Capital City",
            prov + " Centro",
            "North " + prov,
            "South " + prov,
            "East " + prov,
            "West " + prov
        ];
    }

    function getBarangaysForCity(prov, city) {
        if (phLocations[prov] && phLocations[prov][city]) {
            return phLocations[prov][city];
        }
        return [
            "Poblacion",
            "San Jose",
            "San Antonio",
            "Santa Maria",
            "San Roque",
            "Santo Rosario",
            "Barangay I",
            "Barangay II",
            "Barangay III",
            "Bagong Pag-asa"
        ];
    }

    provSel.addEventListener('change', function() {
        const prov = this.value;
        citySel.innerHTML = '<option value="">Select city/municipality</option>';
        brgySel.innerHTML = '<option value="">Select barangay</option>';
        if(!prov) return;

        const cities = getCitiesForProvince(prov);
        cities.forEach(function(c) {
            const opt = document.createElement('option');
            opt.value = c;
            opt.textContent = c;
            citySel.appendChild(opt);
        });
    });

    citySel.addEventListener('change', function() {
        const prov = provSel.value;
        const city = this.value;
        brgySel.innerHTML = '<option value="">Select barangay</option>';
        if(!city) return;

        const brgys = getBarangaysForCity(prov, city);
        brgys.forEach(function(b) {
            const opt = document.createElement('option');
            opt.value = b;
            opt.textContent = b;
            brgySel.appendChild(opt);
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    setupPhAddressDropdowns('addProvince', 'addCity', 'addBarangay');
});
</script>

<?php include "../layout/dashboard_footer.php"; ?>
