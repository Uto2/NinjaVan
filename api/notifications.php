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

$usrId  = $_SESSION['account_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

$notifRef = $db->getReference('notifications/' . $usrId);

// ── MARK AS READ ──────────────────────────────────────────────────────────────
if ($action === 'mark_read') {
    $id = $_POST['id'] ?? null;
    $snap = $notifRef->getSnapshot();
    if ($snap->hasChildren()) {
        $updates = [];
        foreach ($snap->getValue() as $k => $v) {
            if ($id && $k !== $id) continue;
            $updates[$k . '/Notif_IsRead'] = 1;
        }
        if (!empty($updates)) {
            $notifRef->update($updates);
        }
    }
    echo json_encode(['ok' => true]); exit;
}

// ── LIST NOTIFICATIONS ────────────────────────────────────────────────────────
$snap = $notifRef->getSnapshot();
$items  = [];
$unread = 0;

if ($snap->hasChildren()) {
    $allNotifs = $snap->getValue();
    
    // Sort descending by creation date
    uasort($allNotifs, function($a, $b) {
        return strtotime($b['Notif_CreatedAt'] ?? 0) <=> strtotime($a['Notif_CreatedAt'] ?? 0);
    });
    
    // Limit to 30
    $allNotifs = array_slice($allNotifs, 0, 30);

    foreach ($allNotifs as $id => $row) {
        $created = strtotime($row['Notif_CreatedAt'] ?? 0);
        $age_min = floor((time() - $created) / 60);
        
        $age = (int)$age_min;
        if ($age < 1)        $ago = 'Just now';
        elseif ($age < 60)   $ago = "{$age}m ago";
        elseif ($age < 1440) $ago = round($age/60).'h ago';
        else                 $ago = round($age/1440).'d ago';

        $items[] = [
            'id'      => $id,
            'title'   => $row['Notif_Title'] ?? '',
            'body'    => $row['Notif_Body'] ?? '',
            'type'    => $row['Notif_Type'] ?? '',
            'icon'    => $row['Notif_Icon'] ?? '',
            'link'    => $row['Notif_Link'] ?? '',
            'is_read' => (bool)($row['Notif_IsRead'] ?? false),
            'ago'     => $ago,
        ];
        if (!($row['Notif_IsRead'] ?? false)) $unread++;
    }
}

echo json_encode(['unread' => $unread, 'notifications' => $items]);
?>
