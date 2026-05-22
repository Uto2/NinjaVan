<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['area']='Visayas';
$_GET['hub_id']='HUB00004'; // Davao
$_GET['seed']='RDR28853';
session_start();
$_SESSION['account_id'] = 'test';
ob_start();
require 'api/geocode_area.php';
$output = ob_get_clean();
echo "OUTPUT:\n$output\n";
