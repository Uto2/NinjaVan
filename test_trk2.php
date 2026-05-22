<?php
$_SERVER['REQUEST_METHOD']='GET';
$_GET['trk']='NVPH013B21A7';
session_start();
$_SESSION['account_id']='admin';
$_SESSION['role']='shipper';
$_SESSION['shipper_id']='SHPR00001'; // Assuming the shipper ID is SHPR00001, wait I need the real one.
ob_start();
require 'shipper/track_rider.php';
$out = ob_get_clean();
preg_match('/const HUB\s*=\s*(.*?);/', $out, $m);
print_r($m);
