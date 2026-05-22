<?php
// ============================================================
//  NinjaVan PH — Database Exporter
//  Visit: http://localhost/ninjavan/export_db.php
//  DELETE this file after running it!
// ============================================================
require_once "config/db.php";

$output   = "";
$dbName   = "NinjaVanPH";
$now      = date("Y-m-d H:i:s");

// Header
$output .= "-- ============================================================\n";
$output .= "-- NinjaVan PH — Database Backup\n";
$output .= "-- Generated : $now\n";
$output .= "-- Database  : $dbName\n";
$output .= "-- ============================================================\n\n";
$output .= "SET FOREIGN_KEY_CHECKS=0;\n";
$output .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
$output .= "SET NAMES utf8mb4;\n\n";
$output .= "CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
$output .= "USE `$dbName`;\n\n";

// Get all tables
$tables = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_row()) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {
    $output .= "-- ------------------------------------------------------------\n";
    $output .= "-- Table: `$table`\n";
    $output .= "-- ------------------------------------------------------------\n";
    $output .= "DROP TABLE IF EXISTS `$table`;\n";

    // CREATE TABLE statement
    $createRes = $conn->query("SHOW CREATE TABLE `$table`");
    $createRow = $createRes->fetch_assoc();
    $output .= $createRow['Create Table'] . ";\n\n";

    // Table data
    $dataRes = $conn->query("SELECT * FROM `$table`");
    if ($dataRes && $dataRes->num_rows > 0) {
        // Get column names
        $fieldCount = $dataRes->field_count;
        $fields = [];
        $fieldMeta = $dataRes->fetch_fields();
        foreach ($fieldMeta as $f) $fields[] = "`{$f->name}`";
        $fieldList = implode(", ", $fields);

        $output .= "INSERT INTO `$table` ($fieldList) VALUES\n";
        $rows = [];
        while ($row = $dataRes->fetch_assoc()) {
            $vals = [];
            foreach ($row as $v) {
                if ($v === null) {
                    $vals[] = "NULL";
                } else {
                    $vals[] = "'" . $conn->real_escape_string($v) . "'";
                }
            }
            $rows[] = "  (" . implode(", ", $vals) . ")";
        }
        $output .= implode(",\n", $rows) . ";\n\n";
    } else {
        $output .= "-- (no data)\n\n";
    }
}

$output .= "SET FOREIGN_KEY_CHECKS=1;\n";
$output .= "-- ============================================================\n";
$output .= "-- End of backup\n";
$output .= "-- ============================================================\n";

// Save to file
$filePath = __DIR__ . "/NinjaVanPH_backup.txt";
file_put_contents($filePath, $output);

// Also trigger browser download
header("Content-Type: text/plain");
header("Content-Disposition: attachment; filename=\"NinjaVanPH_backup_" . date("Ymd_His") . ".txt\"");
header("Content-Length: " . strlen($output));
echo $output;
exit;
?>
