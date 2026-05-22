<?php
/**
 * api/geocode_area.php
 *
 * Returns a simulated destination coordinate for a given Rcpt_Area string
 * and hub origin. Uses OSRM public API to generate real-world route waypoints.
 * Falls back to bezier curve waypoints if OSRM is unreachable.
 *
 * GET params:
 *   area   = Rcpt_Area value (Metro Manila / Luzon / Visayas / Mindanao)
 *   hub_id = hub ID from DB
 *   seed   = order ID or AWB
 */

require_once '../config/db.php';
header('Content-Type: application/json');
header('Cache-Control: max-age=3600');

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['account_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$area  = trim($_GET['area'] ?? 'Metro Manila');
$hubId = trim($_GET['hub_id'] ?? '');
$seed  = trim($_GET['seed'] ?? 'default');

// 1. Get Hub coordinates
$hubData = ['lat' => 14.5995, 'lng' => 120.9842, 'name' => 'Manila Hub']; // Default fallback
if ($hubId) {
    $stmt = $conn->prepare("SELECT Hub_Name, Hub_Lat, Hub_Lng, Hub_Area FROM HUB WHERE Hub_ID = ?");
    $stmt->bind_param('s', $hubId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($h = $res->fetch_assoc()) {
        $lat = $h['Hub_Lat'] ? (float)$h['Hub_Lat'] : null;
        $lng = $h['Hub_Lng'] ? (float)$h['Hub_Lng'] : null;
        if (!$lat || !$lng) {
            $harea = strtolower($h['Hub_Area'] ?? '');
            if (str_contains($harea, 'cebu') || str_contains($harea, 'visayas')) { $lat=10.3157; $lng=123.8854; }
            elseif (str_contains($harea, 'davao') || str_contains($harea, 'mindanao')) { $lat=7.1907; $lng=125.4553; }
            else { $lat=14.5995; $lng=120.9842; }
        }
        $hubData = ['lat' => $lat, 'lng' => $lng, 'name' => $h['Hub_Name']];
    }
    $stmt->close();
}
$addr  = trim($_GET['addr'] ?? '');

// 2. Destination Generation
$destLat = null;
$destLng = null;
$zone    = 'Unknown Address';
$areaKey = $area;
$seedInt = abs(crc32($seed));

// A. Attempt Real Address Geocoding (Nominatim)
if ($addr) {
    $ctx = stream_context_create(['http' => ['timeout' => 2, 'header' => "User-Agent: NinjaVan PHP/1.0\r\n"]]);
    $nomUrl = "https://nominatim.openstreetmap.org/search?q=" . urlencode($addr) . "&format=json&limit=1";
    $nomRes = @file_get_contents($nomUrl, false, $ctx);
    if ($nomRes) {
        $nomData = json_decode($nomRes, true);
        if (!empty($nomData) && isset($nomData[0]['lat'])) {
            $destLat = (float)$nomData[0]['lat'];
            $destLng = (float)$nomData[0]['lon'];
            $zone    = explode(',', $nomData[0]['display_name'])[0]; // Shorten name
        }
    }
}

// B. Fallback if Geocoding Fails
if ($destLat === null) {
    // Check if the recipient area matches the Hub area (Local Delivery)
    $hubAreaStr = strtolower($h['Hub_Area'] ?? '');
    $isLocal = ($hubAreaStr && (stripos($area, $hubAreaStr) !== false || stripos($hubAreaStr, $area) !== false));
    
    // Default to local if no area provided
    if (!$area || $area === 'Metro Manila') $isLocal = true;
    
    if ($isLocal) {
        // Local Delivery: 3-5 km radius from Hub
        $angle = ($seedInt % 360) * (pi() / 180);
        $distanceDeg = 0.02 + (($seedInt % 30) * 0.001);
        $destLat = $hubData['lat'] + ($distanceDeg * cos($angle));
        $destLng = $hubData['lng'] + ($distanceDeg * sin($angle));
        $zone    = 'Local Address ' . ($seedInt % 999);
    } else {
        // Regional/Cross-Island Transport: Fallback to Area Clusters
        $AREA_CLUSTERS = [
            'Metro Manila' => [['lat' => 14.6760, 'lng' => 121.0437, 'zone' => 'Quezon City'], ['lat' => 14.5547, 'lng' => 121.0244, 'zone' => 'Makati']],
            'Luzon'        => [['lat' => 14.8527, 'lng' => 120.8166, 'zone' => 'Bulacan'], ['lat' => 15.1785, 'lng' => 120.5960, 'zone' => 'Pampanga']],
            'Visayas'      => [['lat' => 10.3157, 'lng' => 123.8854, 'zone' => 'Cebu City'], ['lat' => 10.7202, 'lng' => 122.5621, 'zone' => 'Iloilo']],
            'Mindanao'     => [['lat' =>  7.1907, 'lng' => 125.4553, 'zone' => 'Davao City'], ['lat' =>  8.4542, 'lng' => 124.6319, 'zone' => 'Cagayan de Oro']],
        ];
        $matchKey = 'Metro Manila';
        foreach (array_keys($AREA_CLUSTERS) as $k) {
            if (stripos($area, $k) !== false) { $matchKey = $k; break; }
        }
        $cluster = $AREA_CLUSTERS[$matchKey];
        $dest    = $cluster[$seedInt % count($cluster)];
        
        $destLat = $dest['lat'] + ((($seedInt % 100) - 50) * 0.0005);
        $destLng = $dest['lng'] + (((intdiv($seedInt, 100) % 100) - 50) * 0.0005);
        $zone    = $dest['zone'] . ' Area';
    }
}

// 3. Fallback routing generator
function generateWaypointsFallback(float $lat1, float $lng1, float $lat2, float $lng2, int $seed, int $steps = 20): array {
    $waypoints = [];
    for ($i = 0; $i <= $steps; $i++) {
        $t = $i / $steps;
        // Linear interpolation for a straight line
        $lat = $lat1 + ($lat2 - $lat1) * $t;
        $lng = $lng1 + ($lng2 - $lng1) * $t;
        
        // Very tiny noise for drone wobble
        $noise = sin($i * 7.3 + $seed) * 0.0004;
        $waypoints[] = [$lat + $noise, $lng + $noise];
    }
    return $waypoints;
}

// 4. Generate Path (using 100% offline curved bezier fallback)
$waypoints = generateWaypointsFallback($hubData['lat'], $hubData['lng'], $destLat, $destLng, $seedInt);

echo json_encode([
    'lat'      => $destLat,
    'lng'      => $destLng,
    'zone'     => $zone,
    'area'     => $areaKey,
    'hub_lat'  => $hubData['lat'],
    'hub_lng'  => $hubData['lng'],
    'hub_name' => $hubData['name'],
    'waypoints'=> $waypoints,
]);
