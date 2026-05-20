<?php
/**
 * migrate_hub_gps.php
 *
 * Run once: http://localhost/ninjavan/migrate_hub_gps.php
 *
 * What this does:
 *   1. Creates the RIDER_GPS table if it doesn't exist.
 *   2. Adds Hub_Lat and Hub_Lng columns to the HUB table if missing.
 *   3. Seeds the three main branch hub coordinates (Cebu, Manila, Davao).
 *
 * DELETE or move this file after running it.
 */

require_once 'config/db.php';

// Only allow admin or local access
if (session_status() === PHP_SESSION_NONE) session_start();
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']);
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if (!$isLocal && !$isAdmin) {
    http_response_code(403);
    die("<h2 style='color:red;font-family:sans-serif;padding:40px;'>403 — Access denied.<br><small>Run this from localhost or log in as admin first.</small></h2>");
}

$log = [];
$errors = [];

function run($conn, $sql, &$log, &$errors, $label) {
    if ($conn->query($sql)) {
        $log[] = "✅ $label";
    } else {
        $errors[] = "❌ $label — " . $conn->error;
    }
}

// ─── 1. Create RIDER_GPS table ────────────────────────────────────────────────
run($conn, "
    CREATE TABLE IF NOT EXISTS RIDER_GPS (
        GPS_RdrID     VARCHAR(20)    NOT NULL,
        GPS_Lat       DECIMAL(10,7)  NOT NULL,
        GPS_Lng       DECIMAL(10,7)  NOT NULL,
        GPS_UpdatedAt TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (GPS_RdrID),
        CONSTRAINT fk_gps_rider FOREIGN KEY (GPS_RdrID)
            REFERENCES RIDER(Rdr_ID) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", $log, $errors, "Created RIDER_GPS table");

// ─── 2. Add Hub_Lat column to HUB ────────────────────────────────────────────
$colCheck = $conn->query("SHOW COLUMNS FROM HUB LIKE 'Hub_Lat'");
if ($colCheck && $colCheck->num_rows === 0) {
    run($conn, "ALTER TABLE HUB ADD COLUMN Hub_Lat DECIMAL(10,7) NULL AFTER Hub_Area",
        $log, $errors, "Added Hub_Lat column to HUB");
    run($conn, "ALTER TABLE HUB ADD COLUMN Hub_Lng DECIMAL(10,7) NULL AFTER Hub_Lat",
        $log, $errors, "Added Hub_Lng column to HUB");
} else {
    $log[] = "ℹ️  Hub_Lat / Hub_Lng columns already exist — skipped";
}

// ─── 3. Seed hub coordinates ──────────────────────────────────────────────────
// Match by Hub_Area name — update safely even if run twice
$hubs = [
    ['Cebu',   10.3157, 123.8854],
    ['Manila', 14.5995, 120.9842],
    ['Davao',   7.1907, 125.4553],
];

foreach ($hubs as [$area, $lat, $lng]) {
    $stmt = $conn->prepare(
        "UPDATE HUB SET Hub_Lat = ?, Hub_Lng = ? WHERE Hub_Area LIKE ?"
    );
    $like = "%$area%";
    $stmt->bind_param('dds', $lat, $lng, $like);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        $log[] = "✅ Seeded coordinates for $area hub ($lat, $lng)";
    } else {
        $errors[] = "⚠️  No HUB row found matching area '$area' — add it manually";
    }
}

// ─── Output ───────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hub GPS Migration</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 700px; margin: 60px auto; padding: 0 20px; }
        h1   { color: #e8002d; font-size: 22px; margin-bottom: 4px; }
        p    { color: #666; font-size: 14px; margin-bottom: 24px; }
        .log { background: #f8f7f5; border: 1px solid #e8e6e1; border-radius: 10px; padding: 20px 24px; }
        .log div { font-size: 14px; padding: 5px 0; border-bottom: 1px solid #eee; }
        .log div:last-child { border-bottom: none; }
        .warn { background: #fff3cd; border: 1px solid #ffc107; border-radius: 10px; padding: 16px 20px; margin-top: 20px; font-size: 13px; color: #856404; }
        .done { background: #d1fae5; border: 1px solid #6ee7b7; border-radius: 10px; padding: 16px 20px; margin-top: 20px; font-size: 14px; color: #065f46; font-weight: 600; }
    </style>
</head>
<body>
    <h1>🗺️ Hub GPS Migration</h1>
    <p>Setting up RIDER_GPS table and adding branch coordinates to HUB table.</p>

    <div class="log">
        <?php foreach ($log    as $l) echo "<div>$l</div>"; ?>
        <?php foreach ($errors as $e) echo "<div style='color:#dc2626;'>$e</div>"; ?>
    </div>

    <?php if (empty($errors)): ?>
    <div class="done">
        ✅ Migration complete! The map will now show hub pins for Cebu, Manila, and Davao.<br>
        Riders can now tap "Share my location" to push real GPS data.
    </div>
    <?php else: ?>
    <div class="warn">
        ⚠️ Some steps had warnings — check above. The system will still work; hub coordinate seeding may need manual SQL if your Hub_Area values differ.
    </div>
    <?php endif; ?>

    <div class="warn" style="margin-top:12px;">
        🔒 <strong>Security reminder:</strong> Delete or rename this file after running it.<br>
        <code>C:/xampp/htdocs/uto2-ninjavan/migrate_hub_gps.php</code>
    </div>
</body>
</html>
