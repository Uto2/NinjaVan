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
require_once '../config/Helper.php';
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
    $hubSnap = $db->getReference('hubs/' . $hubId)->getSnapshot();
    if ($hubSnap->exists()) {
        $h = $hubSnap->getValue();
        $lat = isset($h['Hub_Lat']) ? (float)$h['Hub_Lat'] : null;
        $lng = isset($h['Hub_Lng']) ? (float)$h['Hub_Lng'] : null;
        if (!$lat || !$lng) {
            $harea = strtolower($h['Hub_Area'] ?? '');
            if (str_contains($harea, 'cebu') || str_contains($harea, 'visayas')) { $lat=10.3157; $lng=123.8854; }
            elseif (str_contains($harea, 'davao') || str_contains($harea, 'mindanao')) { $lat=7.1907; $lng=125.4553; }
            else { $lat=14.5995; $lng=120.9842; }
        }
        $hubData = ['lat' => $lat, 'lng' => $lng, 'name' => $h['Hub_Name'] ?? 'Hub'];
    }
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
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'header' => "User-Agent: NinjaVan PHP/1.0\r\n"]]);
    
    // Try full address, if fails, try stripping parts (e.g. street name) to get at least the city/barangay
    $addrParts = explode(',', $addr);
    
    while (count($addrParts) > 0 && $destLat === null) {
        $searchQuery = implode(',', $addrParts);
        $nomUrl = "https://nominatim.openstreetmap.org/search?q=" . urlencode(trim($searchQuery)) . "&format=json&limit=1";
        $nomRes = @file_get_contents($nomUrl, false, $ctx);
        
        if ($nomRes) {
            $nomData = json_decode($nomRes, true);
            if (!empty($nomData) && isset($nomData[0]['lat'])) {
                $destLat = (float)$nomData[0]['lat'];
                $destLng = (float)$nomData[0]['lon'];
                $zone    = explode(',', $nomData[0]['display_name'])[0]; // Shorten name
                break;
            }
        }
        
        // Remove the first part (most specific, e.g. street) and try again
        array_shift($addrParts);
    }
}

// B. Fallback if Geocoding Fails
if ($destLat === null) {
    // Check if the recipient area matches the Hub area (Local Delivery)
    $hubAreaStr = strtolower($h['Hub_Area'] ?? '');
    $normArea = strtolower(trim($area));
    
    $isLocal = false;
    if ($hubAreaStr && (stripos($area, $hubAreaStr) !== false || stripos($hubAreaStr, $area) !== false)) {
        $isLocal = true;
    }
    
    // Check if they share the same cluster
    if (!$isLocal) {
        $clusterMap = Helper::getClusterMap();
        
        $hubCluster = '';
        $destCluster = '';
        
        // Find hub cluster
        if (isset($clusterMap[$hubAreaStr])) {
            $hubCluster = $hubAreaStr;
        } else {
            foreach ($clusterMap as $key => $provinces) {
                if (in_array($hubAreaStr, $provinces)) { $hubCluster = $key; break; }
                foreach ($provinces as $prov) {
                    if (stripos($hubAreaStr, $prov) !== false) { $hubCluster = $key; break 2; }
                }
            }
        }
        
        // Find dest cluster
        if (isset($clusterMap[$normArea])) {
            $destCluster = $normArea;
        } else {
            foreach ($clusterMap as $key => $provinces) {
                if (in_array($normArea, $provinces)) { $destCluster = $key; break; }
                foreach ($provinces as $prov) {
                    if (stripos($normArea, $prov) !== false) { $destCluster = $key; break 2; }
                }
            }
        }
        
        if ($hubCluster && $destCluster && $hubCluster === $destCluster) {
            $isLocal = true;
        }
    }
    
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
        $clusterMap = Helper::getClusterMap();
        $AREA_CLUSTERS = [
            'metro manila' => [['lat' => 14.6760, 'lng' => 121.0437, 'zone' => 'Quezon City'], ['lat' => 14.5547, 'lng' => 121.0244, 'zone' => 'Makati']],
            'luzon'        => [['lat' => 14.8527, 'lng' => 120.8166, 'zone' => 'Bulacan'], ['lat' => 15.1785, 'lng' => 120.5960, 'zone' => 'Pampanga']],
            'visayas'      => [['lat' => 10.3157, 'lng' => 123.8854, 'zone' => 'Cebu City'], ['lat' => 10.7202, 'lng' => 122.5621, 'zone' => 'Iloilo']],
            'mindanao'     => [['lat' =>  7.1907, 'lng' => 125.4553, 'zone' => 'Davao City'], ['lat' =>  8.4542, 'lng' => 124.6319, 'zone' => 'Cagayan de Oro']],
        ];
        
        $matchKey = 'metro manila'; // default
        $normArea = strtolower(trim($area));
        
        // 1. Direct match (e.g. "visayas" -> visayas)
        if (isset($AREA_CLUSTERS[$normArea])) {
            $matchKey = $normArea;
        } else {
            // 2. Province map check (e.g. "cebu" -> visayas)
            foreach ($clusterMap as $key => $provinces) {
                if (in_array($normArea, $provinces)) {
                    $matchKey = $key;
                    break;
                }
                // Also check if any province is a substring of the area (e.g. "Cebu City")
                foreach ($provinces as $prov) {
                    if (stripos($normArea, $prov) !== false) {
                        $matchKey = $key;
                        break 2;
                    }
                }
            }
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
