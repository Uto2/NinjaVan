<?php
/**
 * api/rider_positions.php
 * Returns JSON array of all active rider positions for the live map.
 */
require_once '../config/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

// ── Check that RIDER_GPS table exists ─────────────────────────────────────────
$tableCheck = $conn->query("SHOW TABLES LIKE 'RIDER_GPS'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    echo json_encode([]);
    exit;
}

// ── Optional: update a single rider's position via POST ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    if (!isset($_SESSION['rider_id'])) {
        echo json_encode(['error' => 'unauthorized']); exit;
    }

    $rid = $_SESSION['rider_id'];
    $lat = (float)($_POST['lat'] ?? 0);
    $lng = (float)($_POST['lng'] ?? 0);

    if ($lat && $lng) {
        // FIX: Use prepared statement instead of string interpolation
        $stmt = $conn->prepare(
            "INSERT INTO RIDER_GPS (GPS_RdrID, GPS_Lat, GPS_Lng)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE GPS_Lat = ?, GPS_Lng = ?, GPS_UpdatedAt = NOW()"
        );
        $stmt->bind_param("sdddd", $rid, $lat, $lng, $lat, $lng);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['ok' => true]); exit;
}

// ── GET: return all rider positions updated within last 24 hours ───────────────
$res = $conn->query("
    SELECT r.Rdr_ID, r.Rdr_Name, r.Rdr_VhcTyp, r.Rdr_Status,
           h.Hub_Name,
           g.GPS_Lat  AS lat,
           g.GPS_Lng  AS lng,
           DATE_FORMAT(g.GPS_UpdatedAt, '%b %e, %l:%i %p') AS updated_at,
           TIMESTAMPDIFF(MINUTE, g.GPS_UpdatedAt, NOW()) AS age_min,
           (SELECT COUNT(*) FROM DELIVERY_ATTEMPT da
            JOIN SHIPMENT s ON da.Atmp_ShpmID = s.Shpm_ID
            WHERE da.Atmp_RdrID = r.Rdr_ID
              AND s.Shpm_Status IN ('In Transit','Out for Delivery','Pending Pickup')) AS active_parcels
    FROM RIDER r
    JOIN RIDER_GPS g   ON g.GPS_RdrID  = r.Rdr_ID
    LEFT JOIN HUB h    ON h.Hub_ID     = r.Rdr_HubID
    WHERE r.Rdr_Status = 'Active'
      AND g.GPS_UpdatedAt > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY r.Rdr_Name ASC
");

if (!$res) {
    echo json_encode([]);
    exit;
}

$riders = [];
while ($row = $res->fetch_assoc()) {
    $age    = (int)$row['age_min'];
    $status = $age < 5 ? 'online' : ($age < 30 ? 'idle' : 'offline');
    $riders[] = [
        'id'             => $row['Rdr_ID'],
        'name'           => $row['Rdr_Name'],
        'vehicle'        => $row['Rdr_VhcTyp'],
        'hub'            => $row['Hub_Name'] ?? '—',
        'lat'            => (float)$row['lat'],
        'lng'            => (float)$row['lng'],
        'updated_at'     => $row['updated_at'],
        'status'         => $status,
        'active_parcels' => (int)$row['active_parcels'],
    ];
}

echo json_encode($riders);
?>