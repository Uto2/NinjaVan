<?php
/**
 * api/notifications.php
 * Handles GET (fetch) and POST (mark-read) notification requests.
 * Requires an active session.
 */
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['account_id'])) {
    echo json_encode(['error' => 'unauthenticated']); exit;
}

// ── Guard: NOTIFICATION table may not exist yet ───────────────────────────────
$tableCheck = $conn->query("SHOW TABLES LIKE 'NOTIFICATION'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    echo json_encode(['unread' => 0, 'notifications' => [], 'error' => 'table_missing']);
    exit;
}

$usrId  = $conn->real_escape_string($_SESSION['account_id']);
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// ── MARK AS READ ──────────────────────────────────────────────────────────────
if ($action === 'mark_read') {
    $id = isset($_POST['id']) ? $conn->real_escape_string($_POST['id']) : null;
    if ($id) {
        $conn->query("UPDATE NOTIFICATION SET Notif_IsRead=1 WHERE Notif_ID='$id' AND Notif_UsrID='$usrId'");
    } else {
        $conn->query("UPDATE NOTIFICATION SET Notif_IsRead=1 WHERE Notif_UsrID='$usrId'");
    }
    echo json_encode(['ok' => true]); exit;
}

// ── LIST NOTIFICATIONS ────────────────────────────────────────────────────────
$res = $conn->query("
    SELECT Notif_ID, Notif_Title, Notif_Body, Notif_Type, Notif_Icon,
           Notif_Link, Notif_IsRead,
           DATE_FORMAT(Notif_CreatedAt, '%b %e, %l:%i %p') AS created_fmt,
           TIMESTAMPDIFF(MINUTE, Notif_CreatedAt, NOW()) AS age_min
    FROM NOTIFICATION
    WHERE Notif_UsrID = '$usrId'
    ORDER BY Notif_CreatedAt DESC
    LIMIT 30
");

if (!$res) {
    echo json_encode(['unread' => 0, 'notifications' => []]);
    exit;
}

$items  = [];
$unread = 0;
while ($row = $res->fetch_assoc()) {
    $age = (int)$row['age_min'];
    if ($age < 1)        $ago = 'Just now';
    elseif ($age < 60)   $ago = "{$age}m ago";
    elseif ($age < 1440) $ago = round($age/60).'h ago';
    else                 $ago = round($age/1440).'d ago';

    $items[] = [
        'id'      => $row['Notif_ID'],
        'title'   => $row['Notif_Title'],
        'body'    => $row['Notif_Body'],
        'type'    => $row['Notif_Type'],
        'icon'    => $row['Notif_Icon'],
        'link'    => $row['Notif_Link'],
        'is_read' => (bool)$row['Notif_IsRead'],
        'ago'     => $ago,
    ];
    if (!$row['Notif_IsRead']) $unread++;
}

echo json_encode(['unread' => $unread, 'notifications' => $items]);
?>
