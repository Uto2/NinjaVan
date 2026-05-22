<?php
require 'config/db.php';
$trk = 'NVPH013B21A7';
$stmt = $conn->prepare("
    SELECT o.Ord_ID, o.Ord_Status,
           sh.Shpm_HubID,
           h.Hub_Area, h.Hub_Name, h.Hub_Lat, h.Hub_Lng
    FROM AIRWAY_BILL aw
    JOIN `ORDER` o       ON aw.AWB_OrdID    = o.Ord_ID
    JOIN SHIPPER s       ON o.Ord_ShprID     = s.Shpr_ID
    LEFT JOIN SHIPMENT sh ON sh.Shpm_OrdID   = o.Ord_ID
    LEFT JOIN HUB h       ON h.Hub_ID         = sh.Shpm_HubID
    WHERE aw.AWB_TrkNum = ?
");
$stmt->bind_param('s', $trk);
$stmt->execute();
print_r($stmt->get_result()->fetch_assoc());
