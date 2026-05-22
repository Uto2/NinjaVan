<?php
require_once "config/db.php";
$usersSnap = $db->getReference('users')->getSnapshot();
if ($usersSnap->hasChildren()) {
    foreach ($usersSnap->getValue() as $u) {
        if (($u['Usr_Type'] ?? '') === 'admin') {
            echo "Admin found: " . ($u['Usr_Email'] ?? 'No email') . "\n";
        }
    }
} else {
    echo "No users found in RTDB.\n";
}
