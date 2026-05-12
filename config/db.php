<?php

$conn = new mysqli("localhost", "root", "nigger", "NinjaVanPH");

if($conn->connect_error){
    die("
        <div style='font-family:sans-serif;padding:40px;text-align:center;'>
            <h2 style='color:#e8002d;'>⚠ Database Connection Failed</h2>
            <p>Could not connect to <strong>NinjaVanPH</strong>.</p>
            <p style='color:#888;'>Make sure XAMPP MySQL is running and you imported <code>ninjavan_new.sql</code></p>
            <p style='color:#aaa;font-size:12px;'>" . $conn->connect_error . "</p>
        </div>
    ");
}

$conn->set_charset("utf8mb4");
?>