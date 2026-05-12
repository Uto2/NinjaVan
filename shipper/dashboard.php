<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'shipper'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "Dashboard";
$activePage = "dashboard";
$shipperId  = $_SESSION['shipper_id'] ?? '';

// ---- STATS ----
$totalOrders = $conn->query("SELECT COUNT(*) c FROM `ORDER` WHERE Ord_ShprID='$shipperId'")->fetch_assoc()['c'];
$activeOrders = $conn->query("SELECT COUNT(*) c FROM `ORDER` WHERE Ord_ShprID='$shipperId' AND Ord_Status NOT IN ('Delivered','RTS')")->fetch_assoc()['c'];
$delivered   = $conn->query("SELECT COUNT(*) c FROM `ORDER` WHERE Ord_ShprID='$shipperId' AND Ord_Status='Delivered'")->fetch_assoc()['c'];
$staging     = $conn->query("SELECT COUNT(*) c FROM `ORDER` WHERE Ord_ShprID='$shipperId' AND Ord_Status='Staging'")->fetch_assoc()['c'];

// ---- RECENT ORDERS ----
$recent = $conn->query("
    SELECT o.*, p.Pcl_Wght, p.Pcl_IsCOD, p.Pcl_CODAmt,
           r.Rcpt_Name, r.Rcpt_Area,
           s.Svc_Name,
           aw.AWB_TrkNum
    FROM `ORDER` o
    JOIN PARCEL p ON o.Ord_PclID = p.Pcl_ID
    JOIN RECIPIENT r ON p.Pcl_RcptID = r.Rcpt_ID
    JOIN SERVICE_TYPE s ON o.Ord_SvcID = s.Svc_ID
    LEFT JOIN AIRWAY_BILL aw ON aw.AWB_OrdID = o.Ord_ID
    WHERE o.Ord_ShprID = '$shipperId'
    ORDER BY o.Ord_CrtdDt DESC
    LIMIT 5
");

include "../layout/dashboard_layout.php";
?>

<!-- STATS ROW -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card red">
            <div class="stat-icon red"><i class="bi bi-boxes"></i></div>
            <div class="stat-value"><?= $totalOrders ?></div>
            <div class="stat-label">Total Orders</div>
            <div class="stat-trend neu"><i class="bi bi-dash"></i> All time</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="bi bi-truck"></i></div>
            <div class="stat-value"><?= $activeOrders ?></div>
            <div class="stat-label">Active Shipments</div>
            <div class="stat-trend up"><i class="bi bi-arrow-up"></i> In progress</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-value"><?= $delivered ?></div>
            <div class="stat-label">Delivered</div>
            <div class="stat-trend up"><i class="bi bi-arrow-up"></i> Completed</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-value"><?= $staging ?></div>
            <div class="stat-label">Staging</div>
            <div class="stat-trend neu"><i class="bi bi-dash"></i> Awaiting</div>
        </div>
    </div>
</div>

<!-- QUICK ACTIONS + RECENT ORDERS -->
<div class="row g-4">

    <!-- QUICK ACTIONS -->
    <div class="col-lg-4">
        <div class="nv-card p-4 h-100">
            <h5 style="font-size:15px; margin-bottom:20px;">Quick Actions</h5>

            <a href="/ninjavan/shipper/book_parcel.php"
               style="display:flex; align-items:center; gap:14px; padding:14px; border-radius:10px; border:1.5px solid var(--border); text-decoration:none; margin-bottom:10px; transition:var(--trans);"
               onmouseover="this.style.borderColor='var(--red)';this.style.background='rgba(232,0,45,0.03)'"
               onmouseout="this.style.borderColor='var(--border)';this.style.background='transparent'">
                <div style="width:40px;height:40px;background:rgba(232,0,45,0.08);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--red);font-size:18px;">
                    <i class="bi bi-plus-circle-fill"></i>
                </div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:var(--ink);">Book a Parcel</div>
                    <div style="font-size:12px;color:var(--muted);">Create new shipment</div>
                </div>
                <i class="bi bi-chevron-right ms-auto" style="color:var(--muted);font-size:13px;"></i>
            </a>

            <a href="/ninjavan/shipper/track_parcel.php"
               style="display:flex; align-items:center; gap:14px; padding:14px; border-radius:10px; border:1.5px solid var(--border); text-decoration:none; margin-bottom:10px; transition:var(--trans);"
               onmouseover="this.style.borderColor='var(--red)';this.style.background='rgba(232,0,45,0.03)'"
               onmouseout="this.style.borderColor='var(--border)';this.style.background='transparent'">
                <div style="width:40px;height:40px;background:var(--blue-soft);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--blue);font-size:18px;">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:var(--ink);">Track Parcel</div>
                    <div style="font-size:12px;color:var(--muted);">Check delivery status</div>
                </div>
                <i class="bi bi-chevron-right ms-auto" style="color:var(--muted);font-size:13px;"></i>
            </a>

            <a href="/ninjavan/shipper/my_orders.php"
               style="display:flex; align-items:center; gap:14px; padding:14px; border-radius:10px; border:1.5px solid var(--border); text-decoration:none; transition:var(--trans);"
               onmouseover="this.style.borderColor='var(--red)';this.style.background='rgba(232,0,45,0.03)'"
               onmouseout="this.style.borderColor='var(--border)';this.style.background='transparent'">
                <div style="width:40px;height:40px;background:var(--green-soft);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--green);font-size:18px;">
                    <i class="bi bi-receipt"></i>
                </div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:var(--ink);">My Orders</div>
                    <div style="font-size:12px;color:var(--muted);">View all shipments</div>
                </div>
                <i class="bi bi-chevron-right ms-auto" style="color:var(--muted);font-size:13px;"></i>
            </a>
        </div>
    </div>

    <!-- RECENT ORDERS -->
    <div class="col-lg-8">
        <div class="nv-card">
            <div style="padding:18px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                <h5 style="font-size:15px; margin:0;">Recent Orders</h5>
                <a href="/ninjavan/shipper/my_orders.php" class="btn-nv-ghost" style="font-size:12px; padding:6px 14px;">View all</a>
            </div>
            <div style="overflow-x:auto;">
                <table class="nv-table">
                    <thead>
                        <tr>
                            <th>Tracking No.</th>
                            <th>Recipient</th>
                            <th>Service</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if($recent->num_rows === 0): ?>
                        <tr><td colspan="4">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="bi bi-box"></i></div>
                                <h4>No orders yet</h4>
                                <p>Book your first parcel to get started</p>
                            </div>
                        </td></tr>
                    <?php else: while($r = $recent->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span style="font-family:'Sora',sans-serif; font-size:13px; font-weight:700; color:var(--red);">
                                    <?= htmlspecialchars($r['AWB_TrkNum'] ?? $r['Ord_ID']) ?>
                                </span>
                            </td>
                            <td style="font-weight:500;"><?= htmlspecialchars($r['Rcpt_Name']) ?></td>
                            <td>
                                <span style="text-transform:capitalize; font-size:13px;">
                                    <?= htmlspecialchars($r['Svc_Name']) ?>
                                </span>
                            </td>
                            <td><?php
                                $s = $r['Ord_Status'];
                                $map = [
                                    'Staging'=>'badge-pending','Pending Pickup'=>'badge-confirmed',
                                    'In Transit'=>'badge-transit','Delivered'=>'badge-delivered',
                                    'RTS'=>'badge-failed',
                                ];
                                $cls = $map[$s] ?? 'badge-pending';
                                echo "<span class='badge-status $cls'>$s</span>";
                            ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include "../layout/dashboard_footer.php"; ?>
