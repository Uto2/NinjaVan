<?php
/**
 * api/rider_positions.php
 *
 * GET  — Returns JSON array of all active rider positions.
 * POST — Accepts lat/lng from an authenticated rider and upserts their position.
 */

require_once '../config/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

// ── Require a valid session for ALL methods ────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['account_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

// ── POST: rider pushes their GPS position ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['rider_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'unauthorized']);
        exit;
    }

    // Ensure RIDER_GPS table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'RIDER_GPS'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        $conn->query("
            CREATE TABLE RIDER_GPS (
                GPS_RdrID VARCHAR(20) PRIMARY KEY,
                GPS_Lat DECIMAL(10,7) NOT NULL,
                GPS_Lng DECIMAL(10,7) NOT NULL,
                GPS_UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (GPS_RdrID) REFERENCES RIDER(Rdr_ID) ON DELETE CASCADE
            )
        ");
    }

    $rid = $_SESSION['rider_id'];
    $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : 0;
    $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : 0;

    if ($lat < 4.0 || $lat > 22.0 || $lng < 116.0 || $lng > 128.0) {
        http_response_code(400);
        echo json_encode(['error' => 'coordinates out of range for PH']);
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO RIDER_GPS (GPS_RdrID, GPS_Lat, GPS_Lng)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
             GPS_Lat       = VALUES(GPS_Lat),
             GPS_Lng       = VALUES(GPS_Lng),
             GPS_UpdatedAt = NOW()"
    );
    $stmt->bind_param('sdd', $rid, $lat, $lng);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => $ok]);
    exit;
}

// ── GET: return all active riders and their delivery statuses ───────────

// Ensure RIDER_GPS table exists to avoid SQL errors in JOIN
$tableCheck = $conn->query("SHOW TABLES LIKE 'RIDER_GPS'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    $conn->query("
        CREATE TABLE RIDER_GPS (
            GPS_RdrID VARCHAR(20) PRIMARY KEY,
            GPS_Lat DECIMAL(10,7) NOT NULL,
            GPS_Lng DECIMAL(10,7) NOT NULL,
            GPS_UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (GPS_RdrID) REFERENCES RIDER(Rdr_ID) ON DELETE CASCADE
        )
    ");
}

// Ensure Hub_Lat and Hub_Lng exist
$hubCols = $conn->query("SHOW COLUMNS FROM HUB LIKE 'Hub_Lat'");
if($hubCols->num_rows === 0) {
    $conn->query("ALTER TABLE HUB ADD COLUMN Hub_Lat DECIMAL(10,7) NULL, ADD COLUMN Hub_Lng DECIMAL(10,7) NULL");
}

$res = $conn->query("
    SELECT
        r.Rdr_ID, r.Rdr_Name, r.Rdr_VhcTyp, r.Rdr_Status,
        h.Hub_Name,
        h.Hub_ID,
        -- GPS: real if available, hub coords if not (fallback)
        COALESCE(g.GPS_Lat, h.Hub_Lat) AS lat,
        COALESCE(g.GPS_Lng, h.Hub_Lng) AS lng,
        (g.GPS_Lat IS NOT NULL) AS has_gps,
        DATE_FORMAT(g.GPS_UpdatedAt, '%b %e, %l:%i %p') AS updated_at,
        TIMESTAMPDIFF(MINUTE, g.GPS_UpdatedAt, NOW()) AS age_min,
        -- Active parcel details (for route simulation)
        rcpt.Rcpt_Area AS rcpt_area,
        rcpt.Rcpt_Addr AS rcpt_addr,
        active_da.Shpm_Status AS delivery_status,
        aw.AWB_TrkNum AS tracking_num,
        o.Ord_ID AS active_order_id,
        (
            SELECT COUNT(*)
            FROM   DELIVERY_ATTEMPT da2
            JOIN   SHIPMENT s2 ON da2.Atmp_ShpmID = s2.Shpm_ID
            WHERE  da2.Atmp_RdrID = r.Rdr_ID
              AND  s2.Shpm_Status IN ('Pickup / Drop-off','Origin Sorting Hub',
                'Main Sorting Hub','Regional Hub','Destination Hub','Out for Delivery')
              AND  da2.Atmp_Rslt = 'Pending'
        ) AS active_parcels
    FROM RIDER r
    LEFT JOIN RIDER_GPS g ON g.GPS_RdrID = r.Rdr_ID
    LEFT JOIN HUB h ON h.Hub_ID = r.Rdr_HubID
    LEFT JOIN (
        -- Get only the most recent active delivery attempt per rider
        SELECT da.Atmp_RdrID, s.Shpm_Status, s.Shpm_OrdID
        FROM DELIVERY_ATTEMPT da
        JOIN SHIPMENT s ON da.Atmp_ShpmID = s.Shpm_ID
        WHERE s.Shpm_Status IN ('Pickup / Drop-off','Origin Sorting Hub',
            'Main Sorting Hub','Regional Hub','Destination Hub','Out for Delivery')
          AND da.Atmp_Rslt = 'Pending'
        -- Use GROUP BY instead of ORDER BY LIMIT in a subquery join
        AND da.Atmp_ID = (
            SELECT Atmp_ID FROM DELIVERY_ATTEMPT da3
            JOIN SHIPMENT s3 ON da3.Atmp_ShpmID = s3.Shpm_ID
            WHERE da3.Atmp_RdrID = da.Atmp_RdrID
              AND s3.Shpm_Status IN ('Pickup / Drop-off','Origin Sorting Hub',
                'Main Sorting Hub','Regional Hub','Destination Hub','Out for Delivery')
              AND da3.Atmp_Rslt = 'Pending'
            ORDER BY da3.Atmp_ID DESC LIMIT 1
        )
    ) active_da ON active_da.Atmp_RdrID = r.Rdr_ID
    LEFT JOIN `ORDER` o ON o.Ord_ID = active_da.Shpm_OrdID
    LEFT JOIN PARCEL p ON p.Pcl_ID = o.Ord_PclID
    LEFT JOIN RECIPIENT rcpt ON rcpt.Rcpt_ID = p.Pcl_RcptID
    LEFT JOIN AIRWAY_BILL aw ON aw.AWB_OrdID = o.Ord_ID
    WHERE r.Rdr_Status = 'Active'
    ORDER BY r.Rdr_Name ASC
");

if (!$res) {
    echo json_encode([]);
    exit;
}

$riders = [];
while ($row = $res->fetch_assoc()) {
    $age    = $row['age_min'] !== null ? (int)$row['age_min'] : 999;
    
    // online = pinged within last 5 min, idle = within 30 min, offline = older (or no GPS)
    if($row['has_gps']) {
        $status = $age < 5 ? 'online' : ($age < 30 ? 'idle' : 'offline');
    } else {
        $status = 'offline';
    }

    $riders[] = [
        'id'             => $row['Rdr_ID'],
        'name'           => $row['Rdr_Name'],
        'vehicle'        => $row['Rdr_VhcTyp'],
        'hub'            => $row['Hub_Name'] ?? '—',
        'hub_id'         => $row['Hub_ID'],
        'lat'            => $row['lat'] !== null ? (float)$row['lat'] : null,
        'lng'            => $row['lng'] !== null ? (float)$row['lng'] : null,
        'has_gps'        => (bool)$row['has_gps'],
        'updated_at'     => $row['updated_at'] ?? 'No data',
        'status'         => $status,
        'active_parcels' => (int)$row['active_parcels'],
        'rcpt_area'      => $row['rcpt_area'],
        'delivery_status'=> $row['delivery_status'],
        'tracking_num'   => $row['tracking_num']
    ];
}

echo json_encode($riders);