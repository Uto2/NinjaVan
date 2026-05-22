<?php
class Helper {
    public static function getClusterMap() {
        return [
            'metro manila' => ['manila', 'quezon city', 'makati', 'pasig', 'taguig', 'caloocan', 'muntinlupa'],
            'luzon' => ['bulacan', 'pampanga', 'cavite', 'laguna', 'batangas', 'rizal'],
            'visayas' => ['cebu', 'iloilo', 'bohol', 'leyte', 'negros'],
            'mindanao' => ['davao', 'cagayan de oro', 'zamboanga', 'gensan']
        ];
    }

    public static function getBadgeClass($status) {
        $map = [
            'Order Created'      => 'badge-pending',
            'Pickup / Drop-off'  => 'badge-confirmed',
            'Origin Sorting Hub' => 'badge-transit',
            'Main Sorting Hub'   => 'badge-transit',
            'Regional Hub'       => 'badge-transit',
            'Destination Hub'    => 'badge-transit',
            'Out for Delivery'   => 'badge-delivery',
            'Delivered'          => 'badge-delivered',
            'RTS'                => 'badge-failed'
        ];
        return $map[$status] ?? 'badge-pending';
    }

    public static function generateId($prefix) {
        return $prefix . strtoupper(substr(uniqid(), -6));
    }

    public static function isAreaMatch($hubArea, $rcptArea, $pickAddr = '', $status = '') {
        $hubAreaStr = strtolower(trim($hubArea ?? ''));
        $rcptAreaStr = strtolower(trim($rcptArea ?? ''));
        
        if (!$hubAreaStr || !$rcptAreaStr) return true;
        if (stripos($rcptAreaStr, $hubAreaStr) !== false || stripos($hubAreaStr, $rcptAreaStr) !== false) return true;
        
        $clusterMap = self::getClusterMap();
        
        if (isset($clusterMap[$rcptAreaStr]) && in_array($hubAreaStr, $clusterMap[$rcptAreaStr])) return true;
        if (isset($clusterMap[$hubAreaStr]) && in_array($rcptAreaStr, $clusterMap[$hubAreaStr])) return true;
        
        if ($status === 'Order Created' || $status === 'Pickup / Drop-off') {
            $pickAddrStr = strtolower(trim($pickAddr ?? ''));
            if ($pickAddrStr && stripos($pickAddrStr, $hubAreaStr) !== false) return true;
        }
        
        return false;
    }
}
