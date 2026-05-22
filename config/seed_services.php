<?php
/**
 * config/seed_services.php
 *
 * Idempotent SERVICE_TYPE seed — inserts the five default service types
 * only if the table is empty. Include this file wherever you need to
 * guarantee SERVICE_TYPE data exists (e.g. book_parcel.php, book_walkin.php).
 *
 * Usage:
 *   $cnt = $conn->query("SELECT COUNT(*) c FROM SERVICE_TYPE")->fetch_assoc()['c'];
 *   if($cnt == 0) require_once __DIR__ . '/seed_services.php';
 *
 * $conn must already be initialised (via require_once config/db.php).
 */

if(!isset($conn)) {
    require_once __DIR__ . '/db.php';
}

$conn->query("
    INSERT IGNORE INTO SERVICE_TYPE (Svc_ID, Svc_Name, Svc_MaxWght, Svc_BaseRte, Svc_LeadTm) VALUES
        ('SVC00001', 'Standard Delivery',  20,  85, '3-5 Days'),
        ('SVC00002', 'Express Delivery',   20, 120, '1-2 Days'),
        ('SVC00003', 'Same-Day Delivery',   5, 150, 'Same Day'),
        ('SVC00004', 'Next-Day Delivery',  20, 100, 'Before 6PM'),
        ('SVC00005', 'COD Standard',       20,  85, '3-5 Days')
");
