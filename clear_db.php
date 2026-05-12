<?php
require 'config/db.php';

echo "Clearing database...\n";

$conn->query('SET FOREIGN_KEY_CHECKS = 0');

// Clear all transactions
$conn->query('TRUNCATE TABLE DELIVERY_ATTEMPT');
$conn->query('TRUNCATE TABLE SHIPMENT');
$conn->query('TRUNCATE TABLE SHIPPING_FEE');
$conn->query('TRUNCATE TABLE AIRWAY_BILL');
$conn->query('TRUNCATE TABLE `ORDER`');
$conn->query('TRUNCATE TABLE PARCEL');
$conn->query('TRUNCATE TABLE RECIPIENT');

// Clear all non-seeded users and profiles
$conn->query("DELETE FROM USER_ACCOUNT WHERE Usr_ID NOT IN ('USR00001','USR00002','USR00003','USR00004')");
$conn->query("DELETE FROM STAFF WHERE Stf_UsrID NOT IN ('USR00001','USR00002','USR00003','USR00004')");
$conn->query("DELETE FROM SHIPPER WHERE Shpr_UsrID NOT IN ('USR00001','USR00002','USR00003','USR00004')");
// Rider uses Usr_Name, we delete riders whose name isn't Juan, Maria, Ryo, Carlo
$conn->query("DELETE FROM RIDER WHERE Rdr_Name NOT IN ('Ryo Yamada', 'Carlo Mendoza')");

$conn->query('SET FOREIGN_KEY_CHECKS = 1');

echo "Database successfully cleared and reset to factory defaults! All test accounts and test transactions have been wiped.\n";
?>
