<?php
require_once "config/db.php";
$usersSnap = $db->getReference('users')->getSnapshot();
if ($usersSnap->hasChildren()) {
    foreach ($usersSnap->getValue() as $u) {
        echo "Type: " . ($u['Usr_Type'] ?? 'none') . " | Email: " . ($u['Usr_Email'] ?? 'none') . "\n";
    }
} else {
    echo "No users found in RTDB.\n";
}
