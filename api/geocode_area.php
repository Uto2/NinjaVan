<?php
/**
 * api/geocode_area.php
 *
 * Returns a simulated destination coordinate for a given Rcpt_Area string
 * and optional hub origin. Used by the live map and shipper tracker to draw
 * a route line when no real geocoded address is available.
 *
 * GET params:
 *   area  = Rcpt_Area value (Metro Manila / Luzon / Visayas / Mindanao)
 *   hub   = hub area key    (Manila / Cebu / Davao)
 *   seed  = order ID or AWB — used to make the offset repeatable per parcel
 *           so the destination pin doesn't jump on every page load
 *
 * Returns JSON:
 *   { lat, lng, label, hub_lat, hub_lng, hub_name, waypoints: [[lat,lng],...] }
 */

require_once '../config/db.php';
header('Content-Type: application/json');
header('Cache-Control: max-age=3600'); // cache for 1 hour — coords don't change per parcel

$area = trim($_GET['area'] ?? 'Metro Manila');
$hub  = trim($_GET['hub']  ?? 'Manila');
$seed = trim($_GET['seed'] ?? 'default');

// ── Hub coordinates ────────────────────────────────────────────────────────────
$HUBS = [
    'Cebu'   => ['lat' => 10.3157, 'lng' => 123.8854, 'name' => 'Cebu Hub'],
    'Manila' => ['lat' => 14.5995, 'lng' => 120.9842, 'name' => 'Manila Hub'],
    'Davao'  => ['lat' =>  7.1907, 'lng' => 125.4553, 'name' => 'Davao Hub'],
];

// Match hub key (fuzzy)
$hubKey = 'Manila';
foreach (array_keys($HUBS) as $k) {
    if (stripos($hub, $k) !== false) { $hubKey = $k; break; }
}
$hubData = $HUBS[$hubKey];

// ── Area destination clusters ─────────────────────────────────────────────────
// Each area has a cluster of realistic delivery zone coordinates.
// The seed is used to deterministically pick one offset per parcel.
$AREA_CLUSTERS = [
    'Metro Manila' => [
        // Major delivery zones within Metro Manila
        ['lat' => 14.6760, 'lng' => 121.0437, 'zone' => 'Quezon City'],
        ['lat' => 14.5547, 'lng' => 121.0244, 'zone' => 'Makati'],
        ['lat' => 14.5794, 'lng' => 121.0359, 'zone' => 'Mandaluyong'],
        ['lat' => 14.6091, 'lng' => 121.0223, 'zone' => 'San Juan'],
        ['lat' => 14.5243, 'lng' => 121.0792, 'zone' => 'Pasig'],
        ['lat' => 14.4793, 'lng' => 121.0198, 'zone' => 'Taguig'],
        ['lat' => 14.6507, 'lng' => 120.9833, 'zone' => 'Caloocan'],
        ['lat' => 14.5184, 'lng' => 120.9834, 'zone' => 'Parañaque'],
    ],
    'Luzon' => [
        ['lat' => 14.8527, 'lng' => 120.8166, 'zone' => 'Bulacan'],
        ['lat' => 15.1785, 'lng' => 120.5960, 'zone' => 'Pampanga'],
        ['lat' => 16.4023, 'lng' => 120.5960, 'zone' => 'Baguio'],
        ['lat' => 13.6218, 'lng' => 123.1948, 'zone' => 'Naga'],
        ['lat' => 14.0883, 'lng' => 121.1319, 'zone' => 'Laguna'],
        ['lat' => 14.1007, 'lng' => 120.8353, 'zone' => 'Cavite'],
    ],
    'Visayas' => [
        ['lat' => 10.3157, 'lng' => 123.8854, 'zone' => 'Cebu City'],
        ['lat' => 10.7202, 'lng' => 122.5621, 'zone' => 'Iloilo'],
        ['lat' => 11.2543, 'lng' => 125.0000, 'zone' => 'Tacloban'],
        ['lat' =>  9.3068, 'lng' => 123.3054, 'zone' => 'Dumaguete'],
        ['lat' => 10.6713, 'lng' => 122.9511, 'zone' => 'Bacolod'],
    ],
    'Mindanao' => [
        ['lat' =>  7.1907, 'lng' => 125.4553, 'zone' => 'Davao'],
        ['lat' =>  8.4542, 'lng' => 124.6319, 'zone' => 'Cagayan de Oro'],
        ['lat' =>  7.8731, 'lng' => 123.5050, 'zone' => 'Zamboanga'],
        ['lat' =>  6.9214, 'lng' => 122.0790, 'zone' => 'Jolo'],
        ['lat' =>  7.9986, 'lng' => 125.1592, 'zone' => 'Butuan'],
    ],
];

// Normalise area
$areaKey = 'Metro Manila';
foreach (array_keys($AREA_CLUSTERS) as $k) {
    if (stripos($area, $k) !== false || stripos($k, $area) !== false) {
        $areaKey = $k; break;
    }
}
$cluster = $AREA_CLUSTERS[$areaKey];

// Pick cluster index deterministically from seed
$seedInt = abs(crc32($seed));
$idx     = $seedInt % count($cluster);
$dest    = $cluster[$idx];

// Add a small random jitter (repeatable per seed) so two parcels to same zone
// don't land on the exact same pixel on the map
$jitterLat = (($seedInt % 100) - 50) * 0.0005;
$jitterLng = ((intdiv($seedInt, 100) % 100) - 50) * 0.0005;

$destLat = $dest['lat'] + $jitterLat;
$destLng = $dest['lng'] + $jitterLng;

// ── Generate waypoints (hub → destination) ────────────────────────────────────
// Creates a realistic curved path with 12 intermediate points.
// Uses a cubic bezier-style interpolation with perpendicular curve offset.
function generateWaypoints(float $lat1, float $lng1, float $lat2, float $lng2, int $seed, int $steps = 12): array {
    // Control point: midpoint shifted perpendicularly
    $midLat = ($lat1 + $lat2) / 2;
    $midLng = ($lng1 + $lng2) / 2;

    // Perpendicular offset (varies by seed for realism)
    $dx = $lat2 - $lat1;
    $dy = $lng2 - $lng1;
    $len = sqrt($dx * $dx + $dy * $dy) ?: 0.01;

    $curveFactor = 0.25 + (($seed % 30) / 100); // 0.25–0.55
    $sign        = ($seed % 2 === 0) ? 1 : -1;

    $cpLat = $midLat + $sign * $curveFactor * (-$dy / $len);
    $cpLng = $midLng + $sign * $curveFactor * ($dx  / $len);

    $waypoints = [];
    for ($i = 0; $i <= $steps; $i++) {
        $t = $i / $steps;
        // Quadratic bezier: B(t) = (1-t)²P0 + 2(1-t)tP1 + t²P2
        $lat = (1 - $t) * (1 - $t) * $lat1 + 2 * (1 - $t) * $t * $cpLat + $t * $t * $lat2;
        $lng = (1 - $t) * (1 - $t) * $lng1 + 2 * (1 - $t) * $t * $cpLng + $t * $t * $lng2;

        // Add tiny noise for road-like jitter (repeatable per waypoint index)
        $noise = sin($i * 7.3 + $seed) * 0.0008;
        $waypoints[] = [$lat + $noise * 0.6, $lng + $noise];
    }
    return $waypoints;
}

$waypoints = generateWaypoints(
    $hubData['lat'], $hubData['lng'],
    $destLat, $destLng,
    $seedInt
);

echo json_encode([
    'lat'      => $destLat,
    'lng'      => $destLng,
    'zone'     => $dest['zone'],
    'area'     => $areaKey,
    'hub_lat'  => $hubData['lat'],
    'hub_lng'  => $hubData['lng'],
    'hub_name' => $hubData['name'],
    'waypoints'=> $waypoints,
]);
