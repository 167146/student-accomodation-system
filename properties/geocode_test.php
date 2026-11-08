<?php
/*
 * Temporary diagnostic page. Save as properties/geocode_test.php,
 * open http://localhost/student-accomodation-system/properties/geocode_test.php
 * and DELETE it when you're done.
 */
require_once __DIR__ . "/../config/verification_helpers.php";

header("Content-Type: text/plain");

echo "cURL extension loaded: " . (function_exists("curl_init") ? "yes" : "NO - enable extension=curl in php.ini") . "\n";
echo "GD extension loaded:   " . (function_exists("imagecreatefromstring") ? "yes" : "NO") . "\n\n";

$ch = curl_init("https://nominatim.openstreetmap.org/search?q=Nairobi&format=json&limit=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["User-Agent: CampusNest-FinalYearProject/1.0"]);
curl_setopt($ch, CURLOPT_TIMEOUT, 8);
$response = curl_exec($ch);

if ($response === false) {
    echo "Raw request FAILED.\ncURL error: " . curl_error($ch) . "\n";
    echo "(If this mentions SSL certificate, see the fix steps I sent.)\n";
} else {
    echo "Raw request OK (HTTP " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . ").\n";
}
curl_close($ch);

echo "\nFull test address:\n";
var_dump(geocode_address("Tsavo Skywalk Apartments, South B, Kenya"));