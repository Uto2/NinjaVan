<?php
require 'config/db.php';
$res1 = $conn->query('DESCRIBE RIDER');
echo "RIDER: ";
while($r = $res1->fetch_assoc()) echo $r['Field'] . " | ";
echo "\n";

$res2 = $conn->query('DESCRIBE STAFF');
echo "STAFF: ";
while($r = $res2->fetch_assoc()) echo $r['Field'] . " | ";
echo "\n";
?>
