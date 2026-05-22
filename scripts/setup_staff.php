<?php
require_once "config/db.php";

$email = "staff@ninjavan.com";
$password = "ninja123";
$name = "Main Branch Staff";
$phone = "09123456789";

try {
    // 1. Create User in Firebase Auth
    $authProps = [
        'email' => $email,
        'emailVerified' => false,
        'password' => $password,
        'displayName' => $name,
        'disabled' => false,
    ];
    
    $createdUser = $auth->createUser($authProps);
    $uid = $createdUser->uid;

    // 2. Create a Dummy Hub if it doesn't exist
    $hubId = "HUB-TEST01";
    $db->getReference('hubs/' . $hubId)->set([
        'Hub_ID' => $hubId,
        'Hub_Name' => 'Manila Main Hub',
        'Hub_Area' => 'Metro Manila',
        'Hub_Addr' => '123 Test Street, Manila'
    ]);

    // 3. Create User in Realtime Database
    $db->getReference('users/' . $uid)->set([
        'Usr_ID' => $uid,
        'Usr_Email' => $email,
        'Usr_Name' => $name,
        'Usr_Phone' => $phone,
        'Usr_Type' => 'staff',
        'Usr_Status' => 'Active',
        'Stf_HubID' => $hubId
    ]);

    echo "<h2 style='color:green;'>✅ Staff Account created successfully!</h2>";
    echo "<h3>You can now log in:</h3>";
    echo "<b>Email:</b> $email <br>";
    echo "<b>Password:</b> $password <br>";
    echo "<br><a href='/ninjavan/auth/login.php'>Go to Login</a>";

} catch (Exception $e) {
    // If user already exists, let's just show the credentials
    if(strpos($e->getMessage(), 'email address is already in use') !== false) {
        echo "<h2 style='color:blue;'>✅ Staff Account already exists!</h2>";
        echo "<h3>You can log in:</h3>";
        echo "<b>Email:</b> $email <br>";
        echo "<b>Password:</b> $password <br>";
        echo "<br><a href='/ninjavan/auth/login.php'>Go to Login</a>";
    } else {
        echo "<h2 style='color:red;'>Error creating staff account:</h2>";
        echo $e->getMessage();
    }
}
?>
