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

    $rid = $_SESSION['rider_id'];
    $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : 0;
    $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : 0;

    if ($lat < 4.0 || $lat > 22.0 || $lng < 116.0 || $lng > 128.0) {
        http_response_code(400);
        echo json_encode(['error' => 'coordinates out of range for PH']);
        exit;
    }

    try {
        $db->getReference('users/' . $rid . '/gps')->set([
            'lat' => $lat,
            'lng' => $lng,
            'updated_at' => date('c')
        ]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── GET: return all active riders and their delivery statuses ───────────

$riders = [];

try {
    $usersSnap = $db->getReference('users')->orderByChild('Usr_Type')->equalTo('rider')->getSnapshot();
    $hubsSnap = $db->getReference('hubs')->getSnapshot();
    $ordersSnap = $db->getReference('orders')->getSnapshot();

    $hubs = $hubsSnap->getValue() ?: [];
    $orders = $ordersSnap->getValue() ?: [];

    if ($usersSnap->hasChildren()) {
        foreach ($usersSnap->getValue() as $uid => $u) {
            if (($u['Usr_Status'] ?? '') !== 'Active') continue;

            $hubId = $u['hub_id'] ?? $u['Rdr_HubID'] ?? '';
            $hub = $hubs[$hubId] ?? [];
            $gps = $u['gps'] ?? null;

            $hasGps = false;
            $lat = $hub['Hub_Lat'] ?? null;
            $lng = $hub['Hub_Lng'] ?? null;
            $updatedAt = 'No data';
            $ageMin = 999;

            if ($gps) {
                $hasGps = true;
                $lat = $gps['lat'] ?? $lat;
                $lng = $gps['lng'] ?? $lng;
                $updatedAt = date('M j, g:i A', strtotime($gps['updated_at']));
                $ageMin = floor((time() - strtotime($gps['updated_at'])) / 60);
            }

            $status = 'offline';
            if ($hasGps) {
                $status = $ageMin < 5 ? 'online' : ($ageMin < 30 ? 'idle' : 'offline');
            }

            // Find active parcels for this rider
            $activeCount = 0;
            $sampleOrder = null;

            foreach ($orders as $oid => $o) {
                $ordStatus = $o['Ord_Status'] ?? '';
                $transitStatuses = ['Pickup / Drop-off','Origin Sorting Hub','Main Sorting Hub','Regional Hub','Destination Hub','Out for Delivery'];
                
                if (in_array($ordStatus, $transitStatuses) && isset($o['delivery_attempts'])) {
                    foreach ($o['delivery_attempts'] as $att) {
                        if (($att['Atmp_RdrID'] ?? '') === $uid && ($att['Atmp_Rslt'] ?? '') === 'Pending') {
                            $activeCount++;
                            if (!$sampleOrder) {
                                $sampleOrder = [
                                    'id' => $o['awb']['AWB_TrkNum'] ?? $oid,
                                    'dest' => $o['recipient']['Rcpt_Area'] ?? 'Unknown Area',
                                    'status' => $ordStatus === 'Pickup / Drop-off' ? 'Picking up parcel' : $ordStatus
                                ];
                            }
                        }
                    }
                }
            }

            $riders[] = [
                'id'             => $uid,
                'name'           => $u['Usr_Name'] ?? 'Rider',
                'vehicle'        => $u['Rdr_VhcTyp'] ?? 'Unknown',
                'hub'            => $hub['Hub_Name'] ?? '—',
                'hub_id'         => $hubId,
                'lat'            => $lat !== null ? (float)$lat : null,
                'lng'            => $lng !== null ? (float)$lng : null,
                'has_gps'        => $hasGps,
                'updated_at'     => $updatedAt,
                'status'         => $status,
                'active_parcels' => $activeCount,
                'rcpt_area'      => $sampleOrder['recipient']['Rcpt_Area'] ?? null,
                'delivery_status'=> $sampleOrder['Ord_Status'] ?? null,
                'tracking_num'   => $sampleOrder['awb']['AWB_TrkNum'] ?? ($sampleOrder['Ord_ID'] ?? null)
            ];
        }
    }

    // Sort by name
    usort($riders, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

} catch (Exception $e) {
    // silently fail and return empty list
}

echo json_encode($riders);