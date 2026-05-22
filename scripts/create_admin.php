<?php
require_once "config/db.php";

$email = "admin@ninjavan.com";
$password = "password123";
$name = "System Admin";

try {
    // Check if user already exists in Auth
    try {
        $createdUser = $auth->getUserByEmail($email);
        $usrId = $createdUser->uid;
        echo "User already exists in Auth with UID: $usrId\n";
    } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
        // Create User in Firebase Auth
        $authProps = [
            'email' => $email,
            'password' => $password,
            'displayName' => $name,
        ];
        $createdUser = $auth->createUser($authProps);
        $usrId = $createdUser->uid;
        echo "Created Auth User with UID: $usrId\n";
    }

    // Add to RTDB users node
    $db->getReference('users/' . $usrId)->set([
        'Usr_ID' => $usrId,
        'Usr_Name' => $name,
        'Usr_Email' => $email,
        'Usr_Type' => 'admin',
        'Usr_Status' => 'Active',
        'Usr_DateReg' => date('Y-m-d H:i:s')
    ]);
    
    echo "Admin account setup successful in RTDB.\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
} catch (Exception $e) {
    echo "Failed to add admin: " . $e->getMessage() . "\n";
}
