<?php
/*
 * CampusNest verification helpers.
 *
 * Save this file as: config/verification_helpers.php
 * and require it wherever it's needed, e.g.:
 *     require_once __DIR__ . "/../config/verification_helpers.php";
 */

/**
 * Perceptual "average hash" (aHash) of an image.
 * Two visually similar images (even resized, recompressed, or
 * lightly cropped) produce hashes that are close in Hamming distance,
 * even though their raw file bytes are completely different.
 *
 * Returns a 16-character hex string (64 bits), or null if the image
 * could not be read.
 */
function average_hash(string $imagePath): ?string
{
    $raw = @file_get_contents($imagePath);
    if ($raw === false) {
        return null;
    }

    $img = @imagecreatefromstring($raw);
    if ($img === false) {
        return null;
    }

    $small = imagecreatetruecolor(8, 8);
    imagecopyresampled(
        $small,
        $img,
        0, 0, 0, 0,
        8, 8,
        imagesx($img),
        imagesy($img)
    );

    $gray = [];
    $sum = 0;

    for ($y = 0; $y < 8; $y++) {
        for ($x = 0; $x < 8; $x++) {
            $rgb = imagecolorat($small, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $value = (int) round(($r + $g + $b) / 3);

            $gray[] = $value;
            $sum += $value;
        }
    }

    imagedestroy($img);
    imagedestroy($small);

    $average = $sum / 64;
    $bits = "";

    foreach ($gray as $value) {
        $bits .= ($value >= $average) ? "1" : "0";
    }

    // Convert the 64-bit string to hex 8 bits (1 byte) at a time.
    // base_convert() loses precision on numbers this large, so we
    // avoid it by converting one byte at a time instead.
    $hex = "";
    for ($i = 0; $i < 64; $i += 8) {
        $byte = substr($bits, $i, 8);
        $hex .= str_pad(dechex(bindec($byte)), 2, "0", STR_PAD_LEFT);
    }

    return $hex;
}

/**
 * Hamming distance between two 64-bit hex hashes:
 * how many of the 64 bits differ. 0 = identical hash.
 * In practice, a distance of roughly 8 or less usually means the
 * two images are near-duplicates (same photo, resized/recompressed/
 * lightly edited), not just "similar in style."
 */
function hamming_distance(string $hashA, string $hashB): int
{
    if (strlen($hashA) !== 16 || strlen($hashB) !== 16) {
        return 64;
    }

    $distance = 0;

    for ($i = 0; $i < 16; $i += 2) {
        $byteA = hexdec(substr($hashA, $i, 2));
        $byteB = hexdec(substr($hashB, $i, 2));
        $distance += substr_count(decbin($byteA ^ $byteB), "1");
    }

    return $distance;
}

/**
 * How close two hashes need to be before we treat the photo as a
 * likely duplicate or reused/stock image.
 */
const DUPLICATE_HASH_THRESHOLD = 8;

/**
 * Geocode a free-text address to latitude/longitude using
 * OpenStreetMap's free Nominatim service (no API key needed).
 *
 * Note: Nominatim's usage policy allows at most 1 request/second
 * and requires a descriptive User-Agent — fine for a student
 * project's traffic, but do not remove the User-Agent header or
 * call this in a tight loop.
 *
 * Returns ['lat' => float, 'lng' => float] or null if the address
 * could not be geocoded (bad address, or no internet reachable from
 * the server).
 */
function geocode_address(string $address): ?array
{
    $url = "https://nominatim.openstreetmap.org/search?" . http_build_query([
        "q" => $address,
        "format" => "json",
        "limit" => 1,
        "countrycodes" => "ke",
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: CampusNest-FinalYearProject/1.0"
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);

    if (empty($data[0]["lat"]) || empty($data[0]["lon"])) {
        return null;
    }

    return [
        "lat" => (float) $data[0]["lat"],
        "lng" => (float) $data[0]["lon"],
    ];
}

/**
 * Great-circle distance between two lat/lng points, in kilometres.
 */
function haversine_km(
    float $lat1,
    float $lng1,
    float $lat2,
    float $lng2
): float {
    $earthRadiusKm = 6371;

    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadiusKm * $c;
}

/**
 * How far apart (in km) the submitted GPS and the geocoded address
 * can be before we flag it for admin review.
 */
const LOCATION_MISMATCH_THRESHOLD_KM = 5.0;

/**
 * Generate a natural, readable primary key for a new property --
 * properties.property_key has no database default or auto-increment,
 * so the application must supply one.
 *
 * Built from the landlord's email and the property title (so it stays
 * meaningful, not an opaque ID) plus a short random suffix (so two
 * listings with the same email/title never collide). Always fits in
 * the property_key varchar(300) column.
 */
function generate_property_key(string $landlordEmail, string $title): string
{
    $base = strtolower(trim($landlordEmail) . "-" . trim($title));
    $base = preg_replace("/[^a-z0-9]+/", "-", $base);
    $base = trim($base, "-");
    $base = substr($base, 0, 240);

    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);

    return $base . "-" . $suffix;
}