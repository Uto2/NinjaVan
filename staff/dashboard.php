<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'staff'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Branch Dashboard";
$activePage = "dashboard";
$hubId      = $_SESSION['hub_id'];

// Get Hub info
$hubRes = $conn->query("SELECT * FROM HUB WHERE Hub_ID = '$hubId'");
$hub = ($hubRes && $hubRes->num_rows > 0) ? $hubRes->fetch_assoc() : ['Hub_Name'=>'Unknown Hub','Hub_Addr'=>'—','Hub_Area'=>''];

// ---- STATS (specific to this Hub) ----
// Parcels currently at this hub waiting to be assigned/processed
// Using ORDER status 'Pickup / Drop-off' or SHIPMENT pointing to this hub
$pendingDispatch = $conn->query("
    SELECT COUNT(*) c FROM `ORDER` o 
    WHERE o.Ord_Status = 'Pickup / Drop-off'
")->fetch_assoc()['c'];

$activeRiders = $conn->query("
    SELECT COUNT(*) c FROM RIDER WHERE Rdr_HubID = '$hubId' AND Rdr_Status = 'Active'
")->fetch_assoc()['c'];

// Hub Inventory (Parcels physically here based on shipment history - simplistic for now)
$hubInventory = $conn->query("
    SELECT COUNT(*) c FROM SHIPMENT WHERE Shpm_HubID = '$hubId' AND Shpm_Status NOT IN ('Delivered', 'RTS')
")->fetch_assoc()['c'];

// ---- RECENT WALK-IN BOOKINGS ----
// Orders booked by this specific staff (assuming we link staff ID to order if booked via walk-in, but for now just general orders)
$recentOrders = $conn->query("
    SELECT o.*, p.Pcl_Wght, r.Rcpt_Name, r.Rcpt_Area, aw.AWB_TrkNum 
    FROM `ORDER` o
    JOIN PARCEL p ON o.Ord_PclID = p.Pcl_ID
    JOIN RECIPIENT r ON p.Pcl_RcptID = r.Rcpt_ID
    LEFT JOIN AIRWAY_BILL aw ON aw.AWB_OrdID = o.Ord_ID
    ORDER BY o.Ord_CrtdDt DESC
    LIMIT 5
");

include "../layout/dashboard_layout.php";
?>

<div class="page-header">
    <div>
        <h1><?= htmlspecialchars($hub['Hub_Name']) ?></h1>
        <p><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($hub['Hub_Addr']) ?> — Branch Manager Dashboard</p>
    </div>
</div>

<!-- STATS ROW -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="bi bi-box-seam-fill"></i></div>
            <div class="stat-value"><?= $pendingDispatch ?></div>
            <div class="stat-label">Pending Dispatch</div>
            <div class="stat-trend neu"><i class="bi bi-dash"></i> Needs Rider Assignment</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="bi bi-bicycle"></i></div>
            <div class="stat-value"><?= $activeRiders ?></div>
            <div class="stat-label">Active Riders</div>
            <div class="stat-trend up"><i class="bi bi-arrow-up"></i> Currently on duty</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="bi bi-house-door-fill"></i></div>
            <div class="stat-value"><?= $hubInventory ?></div>
            <div class="stat-label">Hub Inventory</div>
            <div class="stat-trend neu"><i class="bi bi-dash"></i> Parcels at branch</div>
        </div>
    </div>
</div>

<!-- QUICK ACTIONS -->
<div class="row g-4 mb-4">
    <div class="col-lg-12">
        <div class="nv-card p-4">
            <h5 style="font-size:15px; margin-bottom:20px;">Branch Operations</h5>
            <div class="d-flex flex-wrap gap-3">
                <a href="/ninjavan/staff/book_walkin.php" class="btn-nv">
                    <i class="bi bi-person-plus-fill"></i> New Walk-in Booking
                </a>
                <a href="/ninjavan/staff/dispatch.php" class="btn-nv" style="background:var(--blue);">
                    <i class="bi bi-send-check-fill"></i> Dispatch Parcels
                </a>
                <a href="/ninjavan/staff/hub_inventory.php" class="btn-nv-ghost">
                    <i class="bi bi-box-seam"></i> View Inventory
                </a>
            </div>
        </div>
    </div>
</div>

<!-- RECENT ORDERS TABLE -->
<div class="nv-card">
    <div style="padding:18px 22px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h5 style="font-size:15px; margin:0 0 2px;">Recent Bookings in System</h5>
        </div>
    </div>
    <div style="overflow-x:auto;">
        <table class="nv-table">
            <thead>
                <tr>
                    <th>Tracking No.</th>
                    <th>Recipient</th>
                    <th>Area</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if($recentOrders->num_rows === 0): ?>
                <tr><td colspan="4">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-box"></i></div>
                        <h4>No recent bookings</h4>
                    </div>
                </td></tr>
            <?php else: while($r = $recentOrders->fetch_assoc()):
                $s   = $r['Ord_Status'];
                $map = [
                    'Order Created'=>'badge-pending','Pickup / Drop-off'=>'badge-confirmed',
                    'Origin Sorting Hub'=>'badge-transit','Main Sorting Hub'=>'badge-transit','Regional Hub'=>'badge-transit','Destination Hub'=>'badge-transit','Delivered'=>'badge-delivered',
                    'RTS'=>'badge-failed',
                ];
                $cls = $map[$s] ?? 'badge-pending';
            ?>
                <tr>
                    <td><span style="font-family:'Sora',sans-serif;font-size:13px;font-weight:700;color:var(--red);"><?= htmlspecialchars($r['AWB_TrkNum'] ?? $r['Ord_ID']) ?></span></td>
                    <td style="font-weight:500;"><?= htmlspecialchars($r['Rcpt_Name']) ?></td>
                    <td style="font-size:13px;color:var(--muted);"><?= htmlspecialchars($r['Rcpt_Area'] ?? '—') ?></td>
                    <td><span class="badge-status <?= $cls ?>"><?= $s ?></span></td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "../layout/dashboard_footer.php"; ?>
