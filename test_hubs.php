<?php
require 'config/db.php';
$r = $conn->query("SELECT * FROM HUB");
while($row = $r->fetch_assoc()){
    print_r($row);
}
