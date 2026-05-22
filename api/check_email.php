<?php
require_once "../config/db.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['error' => 'Invalid method']);
    exit;
}

$email = trim($_GET['email'] ?? '');
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'invalid']);
    exit;
}

$stmt = $conn->prepare("SELECT Usr_ID FROM USER_ACCOUNT WHERE Usr_Email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(['status' => 'taken']);
} else {
    echo json_encode(['status' => 'available']);
}
$stmt->close();
?>
