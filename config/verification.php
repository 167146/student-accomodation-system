<?php
function haversine_m($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000;
    $p1 = deg2rad($lat1); $p2 = deg2rad($lat2);
    $dp = deg2rad($lat2 - $lat1);
    $dl = deg2rad($lon2 - $lon1);
    $a = sin($dp/2)**2 + cos($p1)*cos($p2)*sin($dl/2)**2;
    return 2 * $R * asin(sqrt($a));
}

/**
 * Returns ['ok'=>bool, 'distance_m'=>float, 'action'=>'accept|review|reject', 'reasons'=>[]]
 */
function check_location($pin_lat, $pin_lon, $gps_lat, $gps_lon, $accuracy_m = null) {
    $reasons = [];
    $dist = haversine_m($pin_lat, $pin_lon, $gps_lat, $gps_lon);

    if ($accuracy_m !== null && $accuracy_m > 100) {
        $reasons[] = "gps_accuracy_poor:{$accuracy_m}m";
    }

    if ($dist <= 150) {
        $action = 'accept';
    } elseif ($dist <= 1000) {
        $action = 'review';
        $reasons[] = "distance_review:{$dist}m";
    } else {
        $action = 'reject';
        $reasons[] = "distance_reject:{$dist}m";
    }

    return [
        'ok'          => $action !== 'reject',
        'distance_m'  => round($dist, 1),
        'action'      => $action,
        'reasons'     => $reasons,
    ];
}

/**
 * Photo EXIF GPS vs pin. AI images / downloads usually have no GPS EXIF.
 */
function check_photo_exif($image_path, $pin_lat, $pin_lon, $max_m = 200) {
    $exif = @exif_read_data($image_path);
    if (!$exif || empty($exif['GPSLatitude']) || empty($exif['GPSLongitude'])) {
        return ['ok' => false, 'reason' => 'no_gps_exif'];
    }
    $lat = gps_to_decimal($exif['GPSLatitude'], $exif['GPSLatitudeRef'] ?? 'N');
    $lon = gps_to_decimal($exif['GPSLongitude'], $exif['GPSLongitudeRef'] ?? 'E');
    $d = haversine_m($pin_lat, $pin_lon, $lat, $lon);
    return $d <= $max_m
        ? ['ok' => true,  'distance_m' => round($d,1)]
        : ['ok' => false, 'reason' => "exif_far:{$d}m"];
}

function gps_to_decimal($coord, $ref) {
    $parts = array_map(function ($p) {
        if (is_string($p) && strpos($p, '/') !== false) {
            [$n, $d] = explode('/', $p);
            return $d == 0 ? 0 : $n / $d;
        }
        return (float)$p;
    }, $coord);
    $dec = $parts[0] + ($parts[1] ?? 0)/60 + ($parts[2] ?? 0)/3600;
    if (in_array($ref, ['S', 'W'])) $dec = -$dec;
    return $dec;
}