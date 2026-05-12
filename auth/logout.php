<?php
session_start();
session_destroy();
header("Location: /ninjavan/index.php");
exit();
?>
