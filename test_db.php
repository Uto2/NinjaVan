<?php
require 'config/db.php';
$r=$conn->query("SELECT Ord_Status FROM `ORDER` GROUP BY Ord_Status");
while($row=$r->fetch_assoc()) { echo $row['Ord_Status'] . "\n"; }
