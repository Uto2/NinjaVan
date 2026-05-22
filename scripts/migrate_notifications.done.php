<?php
require_once 'config/db.php';

echo "<pre style='font-family:monospace; background:#111; color:#0f0; padding:20px;'>";
echo "=== NinjaVan Notification + GPS Migration ===\n\n";

// ── 1. NOTIFICATION table ────────────────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS NOTIFICATION (
        Notif_ID        INT AUTO_INCREMENT PRIMARY KEY,
        Notif_UsrID     VARCHAR(20) NOT NULL,
        Notif_Title     VARCHAR(120) NOT NULL,
        Notif_Body      VARCHAR(500) NOT NULL,
        Notif_Type      ENUM('order','dispatch','delivery','system','alert') DEFAULT 'system',
        Notif_Icon      VARCHAR(60)  DEFAULT 'bell',
        Notif_Link      VARCHAR(255) DEFAULT NULL,
        Notif_IsRead    TINYINT(1)   DEFAULT 0,
        Notif_CreatedAt DATETIME     DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user  (Notif_UsrID),
        INDEX idx_read  (Notif_IsRead)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo ($conn->errno ? "❌ NOTIFICATION table: ".$conn->error : "✅ NOTIFICATION table ready") . "\n";

// ── 2. RIDER_GPS table ───────────────────────────────────────────────────────
// Note: No FK on GPS_RdrID — avoids charset/collation mismatch with RIDER table.
$conn->query("
    CREATE TABLE IF NOT EXISTS RIDER_GPS (
        GPS_ID        INT AUTO_INCREMENT PRIMARY KEY,
        GPS_RdrID     VARCHAR(20)   NOT NULL COLLATE utf8mb4_general_ci,
        GPS_Lat       DECIMAL(10,7) NOT NULL,
        GPS_Lng       DECIMAL(10,7) NOT NULL,
        GPS_UpdatedAt DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_rider (GPS_RdrID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");
echo ($conn->errno ? "❌ RIDER_GPS table: ".$conn->error : "✅ RIDER_GPS table ready") . "\n\n";

// ── 3. Seed sample GPS positions for seeded riders ───────────────────────────
$riders = $conn->query("SELECT Rdr_ID FROM RIDER LIMIT 5");
$lats = [14.5547, 14.5795, 14.6042, 14.5217, 14.5896];
$lngs = [121.0244, 120.9842, 121.0456, 120.9981, 121.0126];
$i = 0;
while ($r = $riders->fetch_assoc()) {
    $rid = $r['Rdr_ID'];
    $lat = $lats[$i % 5];
    $lng = $lngs[$i % 5];
    $conn->query("INSERT INTO RIDER_GPS (GPS_RdrID, GPS_Lat, GPS_Lng)
                  VALUES ('$rid', $lat, $lng)
                  ON DUPLICATE KEY UPDATE GPS_Lat=$lat, GPS_Lng=$lng, GPS_UpdatedAt=NOW()");
    echo "📍 GPS seeded for rider $rid ($lat, $lng)\n";
    $i++;
}

// ── 4. Seed sample notifications for each test user ──────────────────────────
echo "\n--- Seeding sample notifications ---\n";

// Get all seeded user IDs
$users = $conn->query("SELECT Usr_ID, Usr_Type FROM USER_ACCOUNT WHERE Usr_ID IN ('USR00001','USR00002','USR00003','USR00004','USR00005','USR00006')");

$notifsByRole = [
    'admin' => [
        ['🚨 New Staging Order', 'Order #ORD-001 has been placed and is awaiting dispatch.', 'order', 'bell-fill', '/ninjavan/admin/manage_orders.php'],
        ['👤 New Shipper Registered', 'Maria Santos has completed registration.', 'system', 'people-fill', '/ninjavan/admin/manage_shippers.php'],
        ['📊 Weekly Report Ready', 'Your weekly delivery performance report is available.', 'alert', 'bar-chart-fill', '/ninjavan/admin/reports.php'],
    ],
    'staff' => [
        ['📦 3 Parcels Need Dispatch', 'You have 3 parcels waiting to be assigned to a rider.', 'dispatch', 'send-check-fill', '/ninjavan/staff/dispatch.php'],
        ['🧾 Walk-in Booking Received', 'A new walk-in booking was submitted at your hub.', 'order', 'person-plus-fill', '/ninjavan/staff/hub_inventory.php'],
    ],
    'shipper' => [
        ['✅ Parcel Picked Up', 'Your parcel has been picked up and is In Transit.', 'delivery', 'truck', '/ninjavan/shipper/my_orders.php'],
        ['📬 Delivery Attempted', 'Delivery attempt was made for your parcel. Recipient was unavailable.', 'delivery', 'geo-alt-fill', '/ninjavan/shipper/track_parcel.php'],
        ['🎉 Delivered Successfully', 'Your parcel has been successfully delivered!', 'delivery', 'check-circle-fill', '/ninjavan/shipper/my_orders.php'],
    ],
    'rider' => [
        ['🛵 New Parcel Assigned', 'A new parcel has been dispatched to you. Check your deliveries.', 'dispatch', 'truck', '/ninjavan/rider/my_deliveries.php'],
        ['⚠️ Delivery Attempt Logged', 'Your delivery attempt for TRK-000123 has been recorded.', 'delivery', 'exclamation-triangle-fill', '/ninjavan/rider/history.php'],
    ],
];

// Clear old sample notifications to avoid dupes on re-run
$conn->query("DELETE FROM NOTIFICATION WHERE Notif_Body LIKE '%sample%' OR Notif_CreatedAt < DATE_SUB(NOW(), INTERVAL 1 DAY)");

// Get role per user — use Usr_Type column directly (no separate ADMIN table)
$allUsers = $conn->query("
    SELECT u.Usr_ID, LOWER(u.Usr_Type) AS role
    FROM USER_ACCOUNT u
    WHERE u.Usr_ID IN ('USR00001','USR00002','USR00003','USR00004','USR00005','USR00006')
");

$inserted = 0;
while ($u = $allUsers->fetch_assoc()) {
    $uid  = $u['Usr_ID'];
    $role = $u['role'];
    if (!isset($notifsByRole[$role])) continue;
    foreach ($notifsByRole[$role] as $n) {
        [$title, $body, $type, $icon, $link] = $n;
        $t = $conn->real_escape_string($title);
        $b = $conn->real_escape_string($body);
        $conn->query("INSERT INTO NOTIFICATION (Notif_UsrID, Notif_Title, Notif_Body, Notif_Type, Notif_Icon, Notif_Link)
                      VALUES ('$uid', '$t', '$b', '$type', '$icon', '$link')");
        echo "🔔 Notification seeded for $uid ($role): $title\n";
        $inserted++;
    }
}

echo "\n✅ Done! $inserted notifications inserted.\n";
echo "📍 GPS positions seeded for $i riders.\n";
echo "\n<strong style='color:#ff0;'>⚠️  Rename this file to migrate_notifications.done.php after running.</strong>\n";
echo "</pre>";
?>
