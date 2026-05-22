<?php
require_once "config/db.php";

echo "Starting migration...\n";

// Create STAFF table
$sql = "
CREATE TABLE IF NOT EXISTS STAFF (
    Stf_ID VARCHAR(20) PRIMARY KEY,
    Stf_UsrID VARCHAR(20) NOT NULL,
    Stf_HubID VARCHAR(20) NOT NULL,
    Stf_Role VARCHAR(50) DEFAULT 'Branch Staff',
    FOREIGN KEY (Stf_UsrID) REFERENCES USER_ACCOUNT(Usr_ID) ON DELETE CASCADE,
    FOREIGN KEY (Stf_HubID) REFERENCES HUB(Hub_ID) ON DELETE CASCADE
);
";

if ($conn->query($sql) === TRUE) {
    echo "STAFF table created successfully.\n";
} else {
    echo "Error creating STAFF table: " . $conn->error . "\n";
}

// Make sure we have at least one HUB for testing
$hubCheck = $conn->query("SELECT * FROM HUB WHERE Hub_ID = 'HUB00001'");
if ($hubCheck->num_rows == 0) {
    $conn->query("INSERT INTO HUB (Hub_ID, Hub_Name, Hub_Addr, Hub_Phone, Hub_Type, Hub_Area) 
                  VALUES ('HUB00001', 'Makati Main Hub', 'Ayala Ave, Makati', '09171234567', 'Distribution Center', 'Metro Manila')");
    echo "Inserted test Hub (HUB00001).\n";
}

// Insert a test STAFF user into USER_ACCOUNT
$userCheck = $conn->query("SELECT * FROM USER_ACCOUNT WHERE Usr_ID = 'USR00005'");
if ($userCheck->num_rows == 0) {
    $hash = password_hash('ninja123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO USER_ACCOUNT (Usr_ID, Usr_Email, Usr_Pass, Usr_Name, Usr_Phone, Usr_Type, Usr_Status) 
                  VALUES ('USR00005', 'staff@ninjavan.ph', '$hash', 'Sarah Staff', '09171112222', 'Individual', 'Active')");
    echo "Inserted test User Account (USR00005).\n";
} else {
    // update password just in case
    $hash = password_hash('ninja123', PASSWORD_DEFAULT);
    $conn->query("UPDATE USER_ACCOUNT SET Usr_Pass='$hash' WHERE Usr_ID='USR00005'");
}

// Link user to STAFF
$staffCheck = $conn->query("SELECT * FROM STAFF WHERE Stf_ID = 'STF00001'");
if ($staffCheck->num_rows == 0) {
    $conn->query("INSERT INTO STAFF (Stf_ID, Stf_UsrID, Stf_HubID, Stf_Role) 
                  VALUES ('STF00001', 'USR00005', 'HUB00001', 'Branch Manager')");
    echo "Inserted test Staff record (STF00001).\n";
}

echo "Migration complete!\n";
?>
