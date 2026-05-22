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

try {
    $user = $auth->getUserByEmail($email);
    echo json_encode(['status' => 'taken']);
} catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
    echo json_encode(['status' => 'available']);
}
?>
