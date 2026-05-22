<?php
// ============================================================
//  NinjaVan PH — Migration: Add Rdr_UsrID to RIDER table
//  Run once: http://localhost/ninjavan/migrate_rider_fk.php
//  DELETE this file after running!
// ============================================================
require_once "config/db.php";

$results = [];

// 1. Check if column already exists
$check = $conn->query("SHOW COLUMNS FROM RIDER LIKE 'Rdr_UsrID'");
if($check->num_rows > 0) {
    $results[] = "Column Rdr_UsrID already exists. Skipping ALTER.";
} else {
    // Add the column
    $conn->query("ALTER TABLE RIDER ADD COLUMN Rdr_UsrID VARCHAR(20) NULL AFTER Rdr_ID");
    $results[] = "✅ Added Rdr_UsrID column to RIDER table.";
}

// 2. Backfill: match existing riders to users by name
$riders = $conn->query("SELECT Rdr_ID, Rdr_Name FROM RIDER WHERE Rdr_UsrID IS NULL OR Rdr_UsrID = ''");
$updated = 0;
while($r = $riders->fetch_assoc()) {
    $name = $conn->real_escape_string($r['Rdr_Name']);
    $user = $conn->query("SELECT Usr_ID FROM USER_ACCOUNT WHERE Usr_Name = '$name' AND Usr_Type = 'rider' LIMIT 1");
    if($user && $user->num_rows > 0) {
        $usrId = $user->fetch_assoc()['Usr_ID'];
        $conn->query("UPDATE RIDER SET Rdr_UsrID = '$usrId' WHERE Rdr_ID = '{$r['Rdr_ID']}'");
        $updated++;
    }
}
$results[] = "✅ Backfilled $updated rider(s) with Usr_ID.";
?>
<!DOCTYPE html><html><head><title>Migration</title>
<style>body{font-family:sans-serif;max-width:640px;margin:60px auto;padding:0 20px}.box{background:#f9f9f9;border:1px solid #e5e7eb;border-radius:12px;padding:32px}h2{color:#e8002d}li{margin-bottom:8px;font-size:14px}.warn{background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:14px;margin-top:20px;font-size:13px;color:#856404}</style>
</head><body><div class="box">
<h2>RIDER FK Migration</h2>
<ul>
<?php foreach($results as $r): ?>
<li><?= $r ?></li>
<?php endforeach; ?>
</ul>
<div class="warn">⚠️ <strong>Delete this file after running!</strong></div>
<p style="margin-top:20px"><a href="/ninjavan/auth/login.php" style="background:#e8002d;color:#fff;padding:10px 24px;border-radius:50px;text-decoration:none;font-weight:700">Go to Login →</a></p>
</div></body></html>
