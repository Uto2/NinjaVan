<?php
session_start();
require_once "../config/db.php";

if(!isset($_SESSION['account_id']) || $_SESSION['role'] !== 'shipper'){
    header("Location: /ninjavan/auth/login.php"); exit();
}

$title      = "My Orders";
$activePage = "orders";
$shipperId  = $_SESSION['shipper_id'];

$statusFilter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$whereClause = "WHERE o.Ord_ShprID = '$shipperId'";
if($statusFilter) {
    $whereClause .= " AND o.Ord_Status = '$statusFilter'";
}

$orders = $conn->query("
    SELECT o.*, p.Pcl_Wght, p.Pcl_DeclVal, p.Pcl_IsCOD, p.Pcl_CODAmt,
           r.Rcpt_Name, r.Rcpt_Addr, r.Rcpt_Area, r.Rcpt_Phone,
           s.Svc_Name,
           aw.AWB_TrkNum,
           f.Fee_Total
    FROM `ORDER` o
    JOIN PARCEL p ON o.Ord_PclID = p.Pcl_ID
    JOIN RECIPIENT r ON p.Pcl_RcptID = r.Rcpt_ID
    JOIN SERVICE_TYPE s ON o.Ord_SvcID = s.Svc_ID
    LEFT JOIN AIRWAY_BILL aw ON aw.AWB_OrdID = o.Ord_ID
    LEFT JOIN SHIPPING_FEE f ON f.Fee_OrdID = o.Ord_ID
    $whereClause
    ORDER BY o.Ord_CrtdDt DESC
");

// Stats for tabs
$statAll = $conn->query("SELECT COUNT(*) c FROM `ORDER` WHERE Ord_ShprID='$shipperId'")->fetch_assoc()['c'];
$statPending = $conn->query("SELECT COUNT(*) c FROM `ORDER` WHERE Ord_ShprID='$shipperId' AND Ord_Status IN ('Staging','Pending Pickup')")->fetch_assoc()['c'];
$statTransit = $conn->query("SELECT COUNT(*) c FROM `ORDER` WHERE Ord_ShprID='$shipperId' AND Ord_Status = 'In Transit'")->fetch_assoc()['c'];
$statDone = $conn->query("SELECT COUNT(*) c FROM `ORDER` WHERE Ord_ShprID='$shipperId' AND Ord_Status = 'Delivered'")->fetch_assoc()['c'];

include "../layout/dashboard_layout.php";
?>

<div class="page-header">
    <div>
        <h1>My Orders</h1>
        <p>Manage and track all your shipments</p>
    </div>
    <a href="/ninjavan/shipper/book_parcel.php" class="btn-nv">
        <i class="bi bi-plus-circle-fill"></i> Book New Parcel
    </a>
</div>

<!-- Tabs -->
<div style="display:flex; gap:20px; border-bottom:1px solid var(--border); margin-bottom:24px; overflow-x:auto;">
    <a href="?status=" style="text-decoration:none; padding:10px 4px; font-weight:600; font-size:14px; border-bottom:2px solid <?= $statusFilter==='' ? 'var(--red)' : 'transparent' ?>; color:<?= $statusFilter==='' ? 'var(--red)' : 'var(--muted)' ?>;">
        All Orders <span style="background:var(--surface); padding:2px 8px; border-radius:20px; font-size:11px; margin-left:4px;"><?= $statAll ?></span>
    </a>
    <a href="?status=Staging" style="text-decoration:none; padding:10px 4px; font-weight:600; font-size:14px; border-bottom:2px solid <?= $statusFilter==='Staging' ? 'var(--red)' : 'transparent' ?>; color:<?= $statusFilter==='Staging' ? 'var(--red)' : 'var(--muted)' ?>;">
        Pending <span style="background:var(--surface); padding:2px 8px; border-radius:20px; font-size:11px; margin-left:4px;"><?= $statPending ?></span>
    </a>
    <a href="?status=In Transit" style="text-decoration:none; padding:10px 4px; font-weight:600; font-size:14px; border-bottom:2px solid <?= $statusFilter==='In Transit' ? 'var(--red)' : 'transparent' ?>; color:<?= $statusFilter==='In Transit' ? 'var(--red)' : 'var(--muted)' ?>;">
        In Transit <span style="background:var(--surface); padding:2px 8px; border-radius:20px; font-size:11px; margin-left:4px;"><?= $statTransit ?></span>
    </a>
    <a href="?status=Delivered" style="text-decoration:none; padding:10px 4px; font-weight:600; font-size:14px; border-bottom:2px solid <?= $statusFilter==='Delivered' ? 'var(--red)' : 'transparent' ?>; color:<?= $statusFilter==='Delivered' ? 'var(--red)' : 'var(--muted)' ?>;">
        Delivered <span style="background:var(--surface); padding:2px 8px; border-radius:20px; font-size:11px; margin-left:4px;"><?= $statDone ?></span>
    </a>
</div>

<!-- Table List -->
<div class="row g-4">
    <?php if($orders->num_rows === 0): ?>
    <div class="col-12">
        <div class="nv-card p-5 text-center">
            <div class="empty-state-icon"><i class="bi bi-box2"></i></div>
            <h4 style="font-family:'Sora',sans-serif; font-weight:700;">No orders found</h4>
            <p style="color:var(--muted); font-size:14px;">Try changing the filter or book a new parcel.</p>
        </div>
    </div>
    <?php else: while($o = $orders->fetch_assoc()): 
        $s = $o['Ord_Status'];
        $map = [
            'Staging'=>'badge-pending','Pending Pickup'=>'badge-confirmed',
            'In Transit'=>'badge-transit','Out for Delivery'=>'badge-delivery',
            'Delivered'=>'badge-delivered','RTS'=>'badge-failed'
        ];
        $cls = $map[$s] ?? 'badge-pending';
    ?>
    <div class="col-12">
        <div class="nv-card" style="display:flex; flex-direction:column; padding:0;">
            <!-- Header -->
            <div style="padding:16px 24px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.01);">
                <div style="display:flex; align-items:center; gap:16px;">
                    <span style="font-family:'Sora',sans-serif; font-size:16px; font-weight:800; color:var(--red);">
                        <?= htmlspecialchars($o['AWB_TrkNum'] ?? $o['Ord_ID']) ?>
                    </span>
                    <span style="color:var(--muted); font-size:12px;"><i class="bi bi-calendar3"></i> <?= date('M d, Y', strtotime($o['Ord_CrtdDt'])) ?></span>
                </div>
                <div>
                    <span class="badge-status <?= $cls ?>"><?= $s ?></span>
                </div>
            </div>
            
            <!-- Body -->
            <div style="padding:24px; display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:24px;">
                <div>
                    <div style="font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Recipient</div>
                    <div style="font-weight:600; color:var(--ink); font-size:14px;"><?= htmlspecialchars($o['Rcpt_Name']) ?></div>
                    <div style="color:var(--muted); font-size:13px; margin-top:2px;"><i class="bi bi-telephone"></i> <?= htmlspecialchars($o['Rcpt_Phone']) ?></div>
                    <div style="color:var(--muted); font-size:13px; margin-top:2px;"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($o['Rcpt_Area']) ?></div>
                </div>
                
                <div>
                    <div style="font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Parcel Details</div>
                    <div style="color:var(--ink); font-size:13px; margin-bottom:4px;"><span style="color:var(--muted);">Service:</span> <?= htmlspecialchars($o['Svc_Name']) ?></div>
                    <div style="color:var(--ink); font-size:13px; margin-bottom:4px;"><span style="color:var(--muted);">Weight:</span> <?= htmlspecialchars($o['Pcl_Wght']) ?> kg</div>
                    <div style="color:var(--ink); font-size:13px;"><span style="color:var(--muted);">Declared Value:</span> ₱<?= number_format($o['Pcl_DeclVal'], 2) ?></div>
                </div>

                <div>
                    <div style="font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Payment & COD</div>
                    <?php if($o['Pcl_IsCOD'] === 'Yes'): ?>
                        <div style="display:inline-block; background:var(--amber-soft); color:var(--amber); font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; margin-bottom:6px;">COD ENABLED</div>
                        <div style="color:var(--ink); font-size:13px; font-weight:600;"><span style="color:var(--muted); font-weight:400;">To Collect:</span> ₱<?= number_format($o['Pcl_CODAmt'], 2) ?></div>
                    <?php else: ?>
                        <div style="display:inline-block; background:var(--surface); color:var(--muted); font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; margin-bottom:6px;">NON-COD</div>
                    <?php endif; ?>
                    <div style="color:var(--ink); font-size:13px; margin-top:6px;"><span style="color:var(--muted);">Shipping Fee:</span> ₱<?= number_format($o['Fee_Total']??0, 2) ?></div>
                </div>

                <div style="display:flex; flex-direction:column; gap:8px; justify-content:center;">
                    <a href="/ninjavan/shipper/track_parcel.php?trk=<?= urlencode($o['AWB_TrkNum']) ?>" class="btn-nv w-100" style="justify-content:center;">
                        <i class="bi bi-geo-alt-fill"></i> Track Parcel
                    </a>
                    
                    <?php if(in_array($s, ['Delivered', 'Failed'])): ?>
                    <a href="#" class="btn-nv-ghost w-100 text-danger" style="justify-content:center; border-color:var(--border);">
                        <i class="bi bi-shield-exclamation"></i> File a Claim
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; endif; ?>
</div>

<?php include "../layout/dashboard_footer.php"; ?>
