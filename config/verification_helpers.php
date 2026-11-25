<?php
/*
 * Unimtaa verification helpers.
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
const DUPLICATE_HASH_THRESHOLD = 5;

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
    // Building names ("Tsavo Skywalk Apartments") are often missing from
    // OpenStreetMap, so if the full address isn't found, retry with the
    // more general parts only (e.g. "South B, Kenya"). The distance check
    // only needs to be accurate to a few km, so an area match is enough.
    // Maps can't search "500 meters from T-Mall" or "near JKUAT", but they
    // can find "T-Mall" and "JKUAT". Strip the directions, keep the landmark.
    $parts = array_map(function ($part) {
        $part = preg_replace(
            "/^\s*\d+(\.\d+)?\s*(meters?|metres?|m|km|kilometers?|kilometres?)\s*(away\s*)?(from|to)?\s*/i",
            "",
            $part
        );
        $part = preg_replace(
            "/\b(near|opposite|behind|next to|beside|off|close to)\b\s*/i",
            "",
            $part
        );
        return trim($part);
    }, explode(",", $address));

    // Remove empty and repeated parts (e.g. "South B, South B").
    $seen = [];
    $parts = array_values(array_filter($parts, function ($part) use (&$seen) {
        $key = strtolower($part);
        if ($part === "" || isset($seen[$key])) {
            return false;
        }
        $seen[$key] = true;
        return true;
    }));

    $attempts = 0;

    for ($i = 0; $i < count($parts) && $attempts < 4; $i++) {
        $query = implode(", ", array_slice($parts, $i));

        if ($attempts > 0) {
            usleep(1100000); // Nominatim allows 1 request per second.
        }

        $attempts++;
        $result = geocode_single($query);

        if ($result !== null) {
            return $result;
        }
    }

    return null;
}

function geocode_single(string $address): ?array
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
        "User-Agent: Unimtaa-FinalYearProject/1.0"
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);

    $response = curl_exec($ch);

    if ($response === false) {
        error_log("Geocoding failed for '{$address}': " . curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);

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
 * How far apart (in km) the landlord's map pin and the geocoded address
 * text can be before we flag it. This is only a rough sanity check, because
 * building names are often missing from OpenStreetMap. The real proof of
 * presence is the device-GPS-versus-pin check (see evaluate_location()).
 */
const LOCATION_MISMATCH_THRESHOLD_KM = 3.0;

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


/* =====================================================================
 * VERSION 2: stronger verification
 * ===================================================================== */

/** Pin vs. the landlord's phone: at or below this = fine (metres). */
const PIN_OK_DISTANCE_M = 150;
/** Pin vs. the landlord's phone: above this the submission is refused (metres). */
const PIN_BLOCK_DISTANCE_M = 1000;
/** A GPS reading worse than this is flagged as imprecise (metres). */
const GPS_MAX_ACCURACY_M = 100;
/** Two listings closer than this can be units of the same building (metres). */
const SAME_BUILDING_M = 50;
/** Photos older than this many days are flagged as possibly not recent. */
const PHOTO_MAX_AGE_DAYS = 45;
/** A photo whose own GPS is further than this from the pin is flagged (metres). */
const EXIF_PHOTO_MAX_DISTANCE_M = 300;

function haversine_m(
    float $lat1,
    float $lng1,
    float $lat2,
    float $lng2
): float {
    return haversine_km($lat1, $lng1, $lat2, $lng2) * 1000;
}

/**
 * A random 4-digit code the landlord must write on paper and include in
 * the first photo. An old, downloaded or AI-generated picture cannot
 * contain a code that did not exist when the picture was made.
 */
function generate_challenge_code(): string
{
    return (string) random_int(1000, 9999);
}

/**
 * Compare where the landlord's phone is, where they dropped the pin, and
 * where the typed address geocodes to.
 *
 * $in keys: device_lat, device_lng, accuracy_m (nullable), pin_lat, pin_lng,
 *           geocoded (['lat' =>, 'lng' =>] or null)
 *
 * Returns:
 *   flag     0 = fine, 1 = admin should look, 2 = presence not proven
 *   block    true when the submission must be refused
 *   block_message, reasons[], pin_distance_m, address_distance_km
 */
function evaluate_location(array $in): array
{
    $reasons = [];
    $flag = 0;
    $block = false;
    $blockMessage = "";
    $pinDistance = null;
    $addressKm = null;

    $accuracy = $in["accuracy_m"] ?? null;
    $hasDevice = $in["device_lat"] !== null && $in["device_lng"] !== null;

    if ($hasDevice && $accuracy !== null && $accuracy > PIN_BLOCK_DISTANCE_M) {
        // The phone only knows roughly where it is (typical of laptops),
        // so the distance to the pin would mean nothing.
        $hasDevice = false;
        $reasons[] = "The device location was too imprecise (about "
            . round($accuracy) . " m) to confirm the landlord was at the property.";
        $flag = 2;
    } elseif (!$hasDevice) {
        $reasons[] = "The landlord's device location was not shared, so being at the property could not be confirmed.";
        $flag = 2;
    }

    if ($hasDevice) {
        $pinDistance = round(haversine_m(
            (float)$in["device_lat"],
            (float)$in["device_lng"],
            (float)$in["pin_lat"],
            (float)$in["pin_lng"]
        ), 1);

        if ($pinDistance > PIN_BLOCK_DISTANCE_M) {
            $block = true;
            $blockMessage = "Your pin is about " . round($pinDistance)
                . " m from where your device is right now. To protect students from fake listings, "
                . "you must be at the property when you submit it. Move the pin onto the property, "
                . "or go to the property and try again.";
        } elseif ($pinDistance > PIN_OK_DISTANCE_M) {
            $flag = max($flag, 1);
            $reasons[] = "The pin is " . round($pinDistance)
                . " m from the landlord's device (automatic limit: " . PIN_OK_DISTANCE_M . " m).";
        }

        if ($accuracy !== null && $accuracy > GPS_MAX_ACCURACY_M) {
            $flag = max($flag, 1);
            $reasons[] = "The GPS reading was imprecise (about " . round($accuracy) . " m).";
        }
    }

    $geo = $in["geocoded"] ?? null;
    if ($geo === null) {
        $flag = max($flag, 1);
        $reasons[] = "The typed address could not be found on the map.";
    } else {
        $addressKm = round(haversine_km(
            (float)$in["pin_lat"],
            (float)$in["pin_lng"],
            (float)$geo["lat"],
            (float)$geo["lng"]
        ), 2);

        if ($addressKm > LOCATION_MISMATCH_THRESHOLD_KM) {
            $flag = max($flag, 1);
            $reasons[] = "The typed address is about " . $addressKm . " km from the pin.";
        }
    }

    return [
        "flag" => $flag,
        "block" => $block,
        "block_message" => $blockMessage,
        "reasons" => $reasons,
        "pin_distance_m" => $pinDistance,
        "address_distance_km" => $addressKm,
    ];
}

function normalise_label(?string $text): string
{
    return strtolower(trim(preg_replace("/\s+/", " ", (string)$text)));
}

/**
 * Decide whether a new photo is a suspicious duplicate.
 *
 * $known: rows with photo_hash, property_key, landlord_email, building_name,
 *         unit_number, pin_latitude, pin_longitude
 * $ctx:   landlord_email, building_name, unit_number, pin_lat, pin_lng
 *
 * A close match is NOT a problem when:
 *   - it is the same landlord's own earlier photo (re-listing a vacant house), or
 *   - it is a different unit of the same building within SAME_BUILDING_M
 *     (look-alike interiors, e.g. student apartment blocks).
 */
function classify_duplicate(string $hash, array $known, array $ctx): array
{
    foreach ($known as $row) {
        if (empty($row["photo_hash"])) {
            continue;
        }

        if (hamming_distance($hash, $row["photo_hash"]) > DUPLICATE_HASH_THRESHOLD) {
            continue;
        }

        // Same landlord: their own photo again.
        if (
            normalise_label($row["landlord_email"] ?? "") ===
            normalise_label($ctx["landlord_email"] ?? "")
        ) {
            continue;
        }

        // Different unit in the same building.
        $buildingA = normalise_label($ctx["building_name"] ?? "");
        $buildingB = normalise_label($row["building_name"] ?? "");
        $unitA = normalise_label($ctx["unit_number"] ?? "");
        $unitB = normalise_label($row["unit_number"] ?? "");

        if (
            $buildingA !== "" && $buildingA === $buildingB &&
            $unitA !== "" && $unitB !== "" && $unitA !== $unitB
        ) {
            $close = true;
            if (
                $row["pin_latitude"] !== null && $row["pin_longitude"] !== null &&
                $ctx["pin_lat"] !== null && $ctx["pin_lng"] !== null
            ) {
                $close = haversine_m(
                    (float)$ctx["pin_lat"],
                    (float)$ctx["pin_lng"],
                    (float)$row["pin_latitude"],
                    (float)$row["pin_longitude"]
                ) <= SAME_BUILDING_M;
            }

            if ($close) {
                continue;
            }
        }

        return [
            "is_duplicate" => 1,
            "matched_property_key" => $row["property_key"],
        ];
    }

    return ["is_duplicate" => 0, "matched_property_key" => null];
}

/** "12/1" or 12 -> float. */
function exif_rational($value): float
{
    if (is_string($value) && strpos($value, "/") !== false) {
        [$n, $d] = explode("/", $value, 2);
        return (float)$d != 0 ? (float)$n / (float)$d : 0.0;
    }
    return (float)$value;
}

function exif_gps_to_decimal(array $parts, string $ref): ?float
{
    if (count($parts) < 3) {
        return null;
    }

    $value = exif_rational($parts[0])
        + exif_rational($parts[1]) / 60
        + exif_rational($parts[2]) / 3600;

    return in_array(strtoupper($ref), ["S", "W"], true) ? -$value : $value;
}

/**
 * Read the camera data hidden inside a photo (EXIF).
 * Photos taken with a phone camera normally carry the camera make/model and
 * the time they were taken, and sometimes GPS. Screenshots, downloaded
 * pictures and AI-generated images usually carry none of it. This is a
 * signal for the admin, not proof: EXIF can be edited.
 *
 * Returns has_camera_data (0/1), taken_at (Y-m-d H:i:s or null), lat, lng.
 */
function read_photo_metadata(string $path): array
{
    $out = [
        "has_camera_data" => 0,
        "taken_at" => null,
        "lat" => null,
        "lng" => null,
    ];

    if (!function_exists("exif_read_data")) {
        return $out;
    }

    $exif = @exif_read_data($path, "ANY_TAG", true);
    if (!is_array($exif)) {
        return $out;
    }

    $ifd0 = $exif["IFD0"] ?? [];
    $sub = $exif["EXIF"] ?? [];
    $gps = $exif["GPS"] ?? [];

    if (!empty($ifd0["Make"]) || !empty($ifd0["Model"])) {
        $out["has_camera_data"] = 1;
    }

    $taken = $sub["DateTimeOriginal"] ?? ($ifd0["DateTime"] ?? null);
    if (is_string($taken)) {
        $dt = DateTime::createFromFormat("Y:m:d H:i:s", $taken);
        if ($dt !== false) {
            $out["taken_at"] = $dt->format("Y-m-d H:i:s");
        }
    }

    if (
        !empty($gps["GPSLatitude"]) && !empty($gps["GPSLongitude"]) &&
        !empty($gps["GPSLatitudeRef"]) && !empty($gps["GPSLongitudeRef"])
    ) {
        $out["lat"] = exif_gps_to_decimal($gps["GPSLatitude"], $gps["GPSLatitudeRef"]);
        $out["lng"] = exif_gps_to_decimal($gps["GPSLongitude"], $gps["GPSLongitudeRef"]);
    }

    return $out;
}

/**
 * Turn the per-photo results into plain-English reasons for the admin.
 * Each photo: has_camera_data, exif_taken_at, exif_distance_m, is_duplicate.
 */


/**
 * Summarise photo checks for the administrator.
 * Missing camera metadata is not proof that a photo is AI-generated.
 */
function summarise_photo_checks(array $photos = [], ?DateTime $now = null): array
{
    $now = $now ?? new DateTime("now");
    $reasons = [];

    // Make sure the supplied value is an array.
    $photos = is_array($photos) ? $photos : [];

    $total = count($photos);

    if ($total === 0) {
        return ["No photos were uploaded."];
    }

    $old = 0;
    $far = 0;
    $duplicates = 0;

    foreach ($photos as $photo) {
        if (!is_array($photo)) {
            continue;
        }

        // Check whether the photo date is older than the allowed period.
        $takenAt = $photo["exif_taken_at"] ?? $photo["taken_at"] ?? null;

        if (!empty($takenAt)) {
            try {
                $taken = new DateTime($takenAt);

                if ($taken < (clone $now)->modify("-" . PHOTO_MAX_AGE_DAYS . " days")) {
                    $old++;
                }
            } catch (Exception $e) {
                // Ignore invalid photo dates.
            }
        }

        // Check whether photo GPS is far from the property's pinned location.
        $distance = $photo["exif_distance_m"] ?? null;

        if ($distance !== null && is_numeric($distance)
            && (float)$distance > EXIF_PHOTO_MAX_DISTANCE_M) {
            $far++;
        }

        // Check for possible duplicate photographs.
        if (!empty($photo["is_duplicate"])) {
            $duplicates++;
        }
    }

    if ($old > 0) {
        $reasons[] = $old . " photo" . ($old === 1 ? " was" : "s were")
            . " taken more than " . PHOTO_MAX_AGE_DAYS . " days ago.";
    }

    if ($far > 0) {
        $reasons[] = $far . " photo" . ($far === 1 ? " was" : "s were")
            . " taken more than " . EXIF_PHOTO_MAX_DISTANCE_M
            . " metres from the pinned location.";
    }

    if ($duplicates > 0) {
        $reasons[] = $duplicates . " photo"
            . ($duplicates === 1 ? " closely matches a photo" : "s closely match photos")
            . " in another landlord's listing.";
    }

    return $reasons;
}