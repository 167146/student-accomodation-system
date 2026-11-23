<?php
session_start();
require_once "../config/database.php";
require_once "../config/verification_helpers.php";

/*
 * Confirmed from your actual database (SHOW CREATE TABLE):
 *  - ../config/database.php creates a PDO connection called $pdo
 *  - properties.property_key is varchar(300), PRIMARY KEY, with no
 *    default -- this file generates it before inserting.
 *  - property_photos has a composite PRIMARY KEY (property_key, photo_path),
 *    plus photo_hash and is_duplicate (added by the verification migration).
 */

// Only logged-in landlords can add properties.
if (
    empty($_SESSION["email"]) ||
    ($_SESSION["role"] ?? "") !== "landlord"
) {
    header("Location: ../public/landlord_login.php");
    exit;
}

// Simple CSRF token.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Photo limits (used by both PHP and the JavaScript on the page).
$max_photos = 20;
$max_file_size = 5 * 1024 * 1024; // 5 MB per photo

$error = "";
$success = "";

// Default form values (also used to re-fill the form after an error).
$title = $description = $address = $location_name = "";
$price = $amenities = $latitude = $longitude = $security_details = "";
$pets_allowed = $parking_available = $wifi_available = 0;
$water_reliable = $furnished = 0;
$environment_noise = "moderate";
$building_name = $unit_number = $pin_latitude = $pin_longitude = "";

// Challenge code: the landlord writes this on paper and includes it in the
// first photo, so old, downloaded or AI-generated pictures cannot be reused.
// It is kept in the session so it cannot be changed from the form.
if (empty($_SESSION["challenge_code"])) {
    $_SESSION["challenge_code"] = generate_challenge_code();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $location_name = trim($_POST["location_name"] ?? "");
    $price = trim($_POST["price"] ?? "");
    $amenities = trim($_POST["amenities"] ?? "");
    $latitude = trim($_POST["latitude"] ?? "");
    $longitude = trim($_POST["longitude"] ?? "");
    $pets_allowed = isset($_POST["pets_allowed"]) ? 1 : 0;
    $parking_available = isset($_POST["parking_available"]) ? 1 : 0;
    $wifi_available = isset($_POST["wifi_available"]) ? 1 : 0;
    $water_reliable = isset($_POST["water_reliable"]) ? 1 : 0;
    $furnished = isset($_POST["furnished"]) ? 1 : 0;
    $security_details = trim($_POST["security_details"] ?? "");
    $environment_noise = $_POST["environment_noise"] ?? "moderate";

    // Proof-of-presence inputs, captured by JavaScript from the
    // browser's Geolocation API just before the form submits.
    // Left blank if the landlord's browser/device didn't provide one.
    $submission_latitude = trim($_POST["submission_latitude"] ?? "");
    $submission_longitude = trim($_POST["submission_longitude"] ?? "");
    $gps_accuracy = trim($_POST["gps_accuracy"] ?? "");

    // The pin the landlord dropped on the map, plus building and unit.
    $pin_latitude = trim($_POST["pin_latitude"] ?? "");
    $pin_longitude = trim($_POST["pin_longitude"] ?? "");
    $building_name = trim($_POST["building_name"] ?? "");
    $unit_number = trim($_POST["unit_number"] ?? "");
    $used_code = $_SESSION["challenge_code"] ?? generate_challenge_code();
    $flag_reasons = [];

    $upload_dir = __DIR__ . "/uploads/";
    $allowed_types = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];
    $uploaded_paths = []; // full server paths (for cleanup)
    $saved_photos = [];   // ["path" => ..., "hash" => ..., "is_duplicate" => ...]
    $photo_files = [];

    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        $error = "Your session expired. Please reload the page and try again.";
    } elseif (
        $title === "" || $description === "" || $address === "" ||
        $location_name === "" || $price === "" || $amenities === ""
    ) {
        $error = "Please fill in all required fields.";
    } elseif (!is_numeric($price) || (float)$price <= 0) {
        $error = "Please enter a valid monthly rent.";
    } elseif (
        ($latitude !== "" && !is_numeric($latitude)) ||
        ($longitude !== "" && !is_numeric($longitude))
    ) {
        $error = "Latitude and longitude must be valid numbers.";
    } elseif (($latitude === "") !== ($longitude === "")) {
        $error = "Enter both latitude and longitude, or leave both blank.";
    } elseif (
        ($latitude !== "" &&
            ((float)$latitude < -90 || (float)$latitude > 90)) ||
        ($longitude !== "" &&
            ((float)$longitude < -180 || (float)$longitude > 180))
    ) {
        $error = "The coordinates are outside the valid range.";
    } elseif (!in_array($environment_noise, ["quiet", "moderate", "busy"], true)) {
        $error = "Please select a valid environment option.";
    } elseif (($building_name === "") !== ($unit_number === "")) {
        $error = "Enter both the building name and the unit number, or leave both blank.";
    } elseif (strlen($building_name) > 150 || strlen($unit_number) > 30) {
        $error = "The building name or unit number is too long.";
    } elseif (
        $pin_latitude === "" || $pin_longitude === "" ||
        !is_numeric($pin_latitude) || !is_numeric($pin_longitude) ||
        (float)$pin_latitude < -90 || (float)$pin_latitude > 90 ||
        (float)$pin_longitude < -180 || (float)$pin_longitude > 180
    ) {
        $error = "Please drop the pin on the map at the property's exact location.";
    } else {

        // --- Proof-of-presence check -----------------------------------
        // 1. The pin must be where the landlord's phone is right now
        //    (within 150 m = fine, up to 1 km = flagged, beyond = refused).
        // 2. The typed address is only a rough sanity check.
        $geocoded = geocode_address($address . ", " . $location_name . ", Kenya");

        $loc = evaluate_location([
            "device_lat" => is_numeric($submission_latitude) ? (float)$submission_latitude : null,
            "device_lng" => is_numeric($submission_longitude) ? (float)$submission_longitude : null,
            "accuracy_m" => is_numeric($gps_accuracy) ? (float)$gps_accuracy : null,
            "pin_lat" => (float)$pin_latitude,
            "pin_lng" => (float)$pin_longitude,
            "geocoded" => $geocoded,
        ]);

        $location_flag = $loc["flag"];
        $location_distance_km = $loc["address_distance_km"];
        $flag_reasons = $loc["reasons"];

        if ($loc["block"]) {
            $error = $loc["block_message"];
        }

        // The pin is the property's map location.
        $latitude = $pin_latitude;
        $longitude = $pin_longitude;

        // --- Same unit listed twice? ------------------------------------
        if ($error === "" && $building_name !== "") {
            try {
                $unitStmt = $pdo->prepare(
                    "SELECT landlord_email FROM properties
                     WHERE LOWER(building_name) = LOWER(?)
                       AND LOWER(unit_number) = LOWER(?)"
                );
                $unitStmt->execute([$building_name, $unit_number]);

                foreach ($unitStmt->fetchAll(PDO::FETCH_COLUMN) as $owner) {
                    if (strcasecmp($owner, $_SESSION["email"]) === 0) {
                        $error = "You have already listed {$building_name}, unit {$unit_number}. "
                            . "Open My Properties and use \"Mark as vacant\" to advertise it again.";
                        break;
                    }
                    $flag_reasons[] = "{$building_name}, unit {$unit_number} is already listed by another landlord.";
                }
            } catch (Throwable $e) {
                error_log("Unit check failed: " . $e->getMessage());
            }
        }

        // --- Validate all photos before saving anything -----------------
        $files = $_FILES["property_images"] ?? null;

        if ($error === "" && $files && isset($files["name"]) && is_array($files["name"])) {
            $file_count = count($files["name"]);

            if ($file_count > $max_photos) {
                $error = "You can upload a maximum of {$max_photos} photos per property.";
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);

                for ($i = 0; $i < $file_count; $i++) {
                    $code = $files["error"][$i] ?? UPLOAD_ERR_NO_FILE;

                    if ($code === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    if ($code !== UPLOAD_ERR_OK) {
                        $error = "One of the photos could not be uploaded. Please try again.";
                        break;
                    }

                    $tmp_name = $files["tmp_name"][$i] ?? "";
                    $size = (int)($files["size"][$i] ?? 0);

                    if (!is_uploaded_file($tmp_name)) {
                        $error = "An uploaded photo could not be verified.";
                        break;
                    }

                    if ($size > $max_file_size) {
                        $error = "Each photo must be 5 MB or smaller.";
                        break;
                    }

                    $mime = $finfo->file($tmp_name);

                    if (!isset($allowed_types[$mime])) {
                        $error = "Only JPG, PNG and WebP photos are allowed.";
                        break;
                    }

                    $photo_files[] = [
                        "tmp_name" => $tmp_name,
                        "extension" => $allowed_types[$mime]
                    ];
                }
            }
        }

        // Load every photo hash already in the system once, with the listing
        // each belongs to, so a new upload can be compared against them.
        // Matches with the SAME landlord's own photos (re-listing a vacant
        // house) or a different unit of the same building are not flagged.
        $known_photos = [];
        if ($error === "" && !empty($photo_files)) {
            try {
                $hashStmt = $pdo->query(
                    "SELECT ph.photo_hash, ph.property_key, p.landlord_email,
                            p.building_name, p.unit_number,
                            p.pin_latitude, p.pin_longitude
                     FROM property_photos ph
                     JOIN properties p ON p.property_key = ph.property_key
                     WHERE ph.photo_hash IS NOT NULL"
                );
                $known_photos = $hashStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                error_log("Could not load existing photo hashes: " . $e->getMessage());
            }
        }

        // Move the validated photos into the uploads folder.
        if ($error === "" && !empty($photo_files)) {
            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                $error = "The upload folder could not be created.";
            } else {
                foreach ($photo_files as $photo) {
                    $new_name = bin2hex(random_bytes(16)) . "." . $photo["extension"];
                    $destination = $upload_dir . $new_name;

                    if (!move_uploaded_file($photo["tmp_name"], $destination)) {
                        $error = "A photo could not be saved. Please try again.";
                        break;
                    }

                    $uploaded_paths[] = $destination;

                    $hash = average_hash($destination);
                    $duplicate = ["is_duplicate" => 0, "matched_property_key" => null];

                    if ($hash !== null) {
                        $duplicate = classify_duplicate($hash, $known_photos, [
                            "landlord_email" => $_SESSION["email"],
                            "building_name" => $building_name,
                            "unit_number" => $unit_number,
                            "pin_lat" => (float)$pin_latitude,
                            "pin_lng" => (float)$pin_longitude,
                        ]);
                    }

                    // Camera data hidden in the photo (make/model, time, GPS).
                    $meta = read_photo_metadata($destination);
                    $exif_distance_m = null;
                    if ($meta["lat"] !== null && $meta["lng"] !== null) {
                        $exif_distance_m = round(haversine_m(
                            (float)$pin_latitude,
                            (float)$pin_longitude,
                            $meta["lat"],
                            $meta["lng"]
                        ), 1);
                    }

                    $saved_photos[] = [
                        "path" => "uploads/" . $new_name,
                        "hash" => $hash,
                        "is_duplicate" => $duplicate["is_duplicate"],
                        "matched_property_key" => $duplicate["matched_property_key"],
                        "has_camera_data" => $meta["has_camera_data"],
                        "exif_taken_at" => $meta["taken_at"],
                        "exif_distance_m" => $exif_distance_m
                    ];
                }
            }
        }

        // Turn the photo checks into reasons for the admin.
        if ($error === "") {
            $flag_reasons = array_merge($flag_reasons, summarise_photo_checks($saved_photos));
            if (!empty($flag_reasons)) {
                $location_flag = max($location_flag, 1);
            }
        }

        // Save the property and its photos in one transaction.
        if ($error === "") {
            try {
                // property_key has no database default -- generate it here.
                $property_key = generate_property_key($_SESSION["email"], $title);

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "INSERT INTO properties
                        (property_key, landlord_email, title, description, address,
                         location_name, price, amenities, latitude, longitude,
                         pets_allowed, parking_available, wifi_available, water_reliable,
                         furnished, security_details, environment_noise,
                         submission_latitude, submission_longitude,
                         location_distance_km, location_flag,
                         building_name, unit_number, pin_latitude, pin_longitude,
                         gps_accuracy_m, pin_distance_m, challenge_code, verification_flags)
                     VALUES
                        (:property_key, :landlord_email, :title, :description, :address,
                         :location_name, :price, :amenities, :latitude, :longitude,
                         :pets_allowed, :parking_available, :wifi_available, :water_reliable,
                         :furnished, :security_details, :environment_noise,
                         :submission_latitude, :submission_longitude,
                         :location_distance_km, :location_flag,
                         :building_name, :unit_number, :pin_latitude, :pin_longitude,
                         :gps_accuracy_m, :pin_distance_m, :challenge_code, :verification_flags)"
                );

                $stmt->execute([
                    ":property_key" => $property_key,
                    ":landlord_email" => $_SESSION["email"],
                    ":title" => $title,
                    ":description" => $description,
                    ":address" => $address,
                    ":location_name" => $location_name,
                    ":price" => (float)$price,
                    ":amenities" => $amenities,
                    ":latitude" => $latitude === "" ? null : (float)$latitude,
                    ":longitude" => $longitude === "" ? null : (float)$longitude,
                    ":pets_allowed" => $pets_allowed,
                    ":parking_available" => $parking_available,
                    ":wifi_available" => $wifi_available,
                    ":water_reliable" => $water_reliable,
                    ":furnished" => $furnished,
                    ":security_details" => $security_details,
                    ":environment_noise" => $environment_noise,
                    ":submission_latitude" => $submission_latitude === "" ? null : (float)$submission_latitude,
                    ":submission_longitude" => $submission_longitude === "" ? null : (float)$submission_longitude,
                    ":location_distance_km" => $location_distance_km,
                    ":location_flag" => $location_flag,
                    ":building_name" => $building_name === "" ? null : $building_name,
                    ":unit_number" => $unit_number === "" ? null : $unit_number,
                    ":pin_latitude" => (float)$pin_latitude,
                    ":pin_longitude" => (float)$pin_longitude,
                    ":gps_accuracy_m" => is_numeric($gps_accuracy) ? (float)$gps_accuracy : null,
                    ":pin_distance_m" => $loc["pin_distance_m"],
                    ":challenge_code" => $used_code,
                    ":verification_flags" => empty($flag_reasons) ? null : implode("\n", $flag_reasons)
                ]);

                if (!empty($saved_photos)) {
                    $img_stmt = $pdo->prepare(
                        "INSERT INTO property_photos
                            (property_key, photo_path, photo_hash, is_duplicate,
                             matched_property_key, has_camera_data, exif_taken_at, exif_distance_m)
                         VALUES
                            (:property_key, :photo_path, :photo_hash, :is_duplicate,
                             :matched_property_key, :has_camera_data, :exif_taken_at, :exif_distance_m)"
                    );

                    foreach ($saved_photos as $photo) {
                        $img_stmt->execute([
                            ":property_key" => $property_key,
                            ":photo_path" => $photo["path"],
                            ":photo_hash" => $photo["hash"],
                            ":is_duplicate" => $photo["is_duplicate"],
                            ":matched_property_key" => $photo["matched_property_key"],
                            ":has_camera_data" => $photo["has_camera_data"],
                            ":exif_taken_at" => $photo["exif_taken_at"],
                            ":exif_distance_m" => $photo["exif_distance_m"]
                        ]);
                    }
                }

                $pdo->commit();
                $success = "Property added successfully. It's now waiting for admin verification.";

                // Clear the form after a successful save.
                $title = $description = $address = $location_name = "";
                $price = $amenities = $latitude = $longitude = $security_details = "";
                $pets_allowed = $parking_available = $wifi_available = 0;
                $water_reliable = $furnished = 0;
                $environment_noise = "moderate";
                $building_name = $unit_number = $pin_latitude = $pin_longitude = "";

                // A fresh code for the next listing.
                $_SESSION["challenge_code"] = generate_challenge_code();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Add property failed: " . $e->getMessage());
                $error = "Something went wrong while saving the property. Please try again.";
            }
        }

        // If anything failed, delete photos that were already moved.
        if ($error !== "") {
            foreach ($uploaded_paths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}

$challenge_code = $_SESSION["challenge_code"];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Property</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <style>
    :root {
        /* Change this path to your own background image. */
        --bg-image: url('../assets/images/background.jpg');

        --primary: #7c3aed;
        --primary-dark: #5b21b6;
        --accent: #a78bfa;
        --accent-dark: #8b5cf6;
        --ink: #1f2937;
        --muted: #6b7280;
        --line: #e5e7eb;
        --soft: #f5f3ff;
        --danger: #b91c1c;
        --danger-bg: #fee2e2;
        --ok: #166534;
        --ok-bg: #dcfce7;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 32px 16px 56px;
        font-family: 'Poppins', Arial, sans-serif;
        color: var(--ink);
        min-height: 100vh;
        background-color: #2e1065;
        background-image:
            linear-gradient(135deg, rgba(109, 40, 217, 0.82), rgba(30, 16, 64, 0.82)),
            var(--bg-image);
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
    }

    .container {
        max-width: 820px;
        margin: 0 auto;
        background: rgba(255, 255, 255, 0.96);
        border-radius: 20px;
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
        overflow: hidden;
    }

    .form-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: #fff;
        padding: 28px 32px;
        border-bottom: 5px solid var(--accent);
    }

    .form-header h1 {
        margin: 0 0 6px;
        font-size: 1.7rem;
    }

    .form-header p {
        margin: 0;
        opacity: 0.9;
        font-size: 0.95rem;
    }

    form {
        padding: 28px 32px 36px;
    }

    fieldset {
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 8px 20px 20px;
        margin: 0 0 22px;
        background: #fff;
    }

    legend {
        padding: 4px 14px;
        font-weight: 600;
        font-size: 0.95rem;
        color: #fff;
        background: var(--primary);
        border-radius: 999px;
    }

    .grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0 18px;
    }

    .full {
        grid-column: 1 / -1;
    }

    label.field-label {
        display: block;
        margin-top: 16px;
        font-weight: 500;
        font-size: 0.88rem;
    }

    .req {
        color: var(--danger);
    }

    .hint {
        display: block;
        margin-top: 4px;
        font-size: 0.78rem;
        color: var(--muted);
    }

    input[type="text"],
    input[type="number"],
    textarea,
    select {
        width: 100%;
        margin-top: 6px;
        padding: 11px 13px;
        font: inherit;
        font-size: 0.93rem;
        color: var(--ink);
        background: #fafafa;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }

    textarea {
        resize: vertical;
    }

    input:focus,
    textarea:focus,
    select:focus {
        outline: none;
        background: #fff;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.18);
    }

    /* Feature toggles as pills */
    .pills {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 16px;
    }

    .pill {
        position: relative;
    }

    .pill input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .pill span {
        display: inline-block;
        padding: 9px 16px;
        font-size: 0.88rem;
        border: 1.5px solid var(--line);
        border-radius: 999px;
        background: #fff;
        cursor: pointer;
        transition: all 0.2s;
        user-select: none;
    }

    .pill span:hover {
        border-color: var(--primary);
    }

    .pill input:checked+span {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }

    .pill input:focus-visible+span {
        box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.28);
    }

    /* Photo upload */
    .dropzone {
        margin-top: 16px;
        padding: 28px 18px;
        text-align: center;
        background: var(--soft);
        border: 2px dashed var(--primary);
        border-radius: 14px;
        cursor: pointer;
        transition: background 0.2s, transform 0.2s;
    }

    .dropzone:hover,
    .dropzone.dragover {
        background: #ede9fe;
        transform: scale(1.01);
    }

    .dropzone .icon {
        font-size: 2rem;
    }

    .dropzone strong {
        color: var(--primary-dark);
    }

    .dropzone small {
        display: block;
        margin-top: 4px;
        color: var(--muted);
    }

    #property_images {
        display: none;
    }

    .photo-count {
        margin-top: 12px;
        font-size: 0.85rem;
        color: var(--muted);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .clear-all {
        background: none;
        border: 0;
        padding: 0;
        color: var(--danger);
        font: inherit;
        font-size: 0.85rem;
        cursor: pointer;
    }

    .previews {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 12px;
        margin-top: 12px;
    }

    .preview {
        position: relative;
        aspect-ratio: 1 / 1;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--line);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
    }

    .preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .preview .remove {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 26px;
        height: 26px;
        border: 0;
        border-radius: 50%;
        background: rgba(17, 24, 39, 0.75);
        color: #fff;
        font-size: 16px;
        line-height: 26px;
        padding: 0;
        cursor: pointer;
    }

    .preview .remove:hover {
        background: var(--danger);
    }

    .preview .cover-tag {
        position: absolute;
        left: 6px;
        bottom: 6px;
        padding: 2px 8px;
        font-size: 0.7rem;
        background: var(--accent);
        color: #1f2937;
        border-radius: 999px;
        font-weight: 600;
    }

    .photo-error {
        margin-top: 10px;
        color: var(--danger);
        font-size: 0.85rem;
        min-height: 1em;
    }

    /* Buttons and messages */
    button.submit {
        width: 100%;
        margin-top: 8px;
        padding: 14px;
        font: inherit;
        font-size: 1rem;
        font-weight: 600;
        color: #fff;
        background: linear-gradient(135deg, var(--accent-dark), var(--primary-dark));
        border: 0;
        border-radius: 12px;
        cursor: pointer;
        box-shadow: 0 8px 18px rgba(91, 33, 182, 0.35);
        transition: transform 0.15s, box-shadow 0.15s;
    }

    button.submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(91, 33, 182, 0.45);
    }

    .message {
        margin: 20px 32px 0;
        padding: 13px 16px;
        border-radius: 10px;
        font-size: 0.92rem;
    }

    .message.error {
        background: var(--danger-bg);
        color: var(--danger);
        border-left: 5px solid var(--danger);
    }

    .message.success {
        background: var(--ok-bg);
        color: var(--ok);
        border-left: 5px solid var(--ok);
    }

    /* Map pin + challenge code */
    #map {
        height: 340px;
        margin-top: 12px;
        border-radius: 14px;
        border: 1.5px solid var(--line);
        z-index: 1;
    }

    .map-bar {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
        margin-top: 14px;
    }

    .map-bar button,
    .camera-btn {
        font: inherit;
        font-size: 0.88rem;
        font-weight: 600;
        padding: 10px 16px;
        border: 1.5px solid var(--primary);
        background: #fff;
        color: var(--primary-dark);
        border-radius: 999px;
        cursor: pointer;
    }

    .map-bar button:hover,
    .camera-btn:hover {
        background: var(--soft);
    }

    #locStatus {
        font-size: 0.85rem;
        color: var(--muted);
    }

    #locStatus.good {
        color: var(--ok);
    }

    #locStatus.bad {
        color: var(--danger);
    }

    .challenge {
        margin-top: 16px;
        padding: 16px 18px;
        border-radius: 14px;
        background: var(--soft);
        border: 1.5px solid var(--accent);
        display: flex;
        gap: 18px;
        align-items: center;
        flex-wrap: wrap;
    }

    .challenge .code {
        font-size: 2.2rem;
        font-weight: 700;
        letter-spacing: 0.3em;
        padding: 4px 18px 4px 24px;
        border-radius: 12px;
        background: #fff;
        color: var(--primary-dark);
        border: 2px dashed var(--primary);
    }

    .challenge p {
        margin: 0;
        flex: 1;
        min-width: 220px;
        font-size: 0.88rem;
    }

    .camera-row {
        margin-top: 14px;
    }

    @media (max-width: 640px) {
        body {
            padding: 16px 10px 40px;
        }

        .grid {
            grid-template-columns: 1fr;
        }

        .form-header,
        form {
            padding-left: 18px;
            padding-right: 18px;
        }

        .message {
            margin-left: 18px;
            margin-right: 18px;
        }
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="form-header">
            <h1>Add a New Property</h1>
            <p>Fill in the details and upload photos so students can find your place.</p>
        </div>

        <?php if ($error !== ""): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="propertyForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
            <input type="hidden" id="submission_latitude" name="submission_latitude" value="">
            <input type="hidden" id="submission_longitude" name="submission_longitude" value="">
            <input type="hidden" id="gps_accuracy" name="gps_accuracy" value="">
            <input type="hidden" id="pin_latitude" name="pin_latitude" value="<?= htmlspecialchars($pin_latitude) ?>">
            <input type="hidden" id="pin_longitude" name="pin_longitude"
                value="<?= htmlspecialchars($pin_longitude) ?>">

            <fieldset>
                <legend>Basic details</legend>

                <label class="field-label" for="title">Title <span class="req">*</span></label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>" required>

                <label class="field-label" for="description">Description <span class="req">*</span></label>
                <textarea id="description" name="description" rows="4"
                    required><?= htmlspecialchars($description) ?></textarea>

                <div class="grid">
                    <div>
                        <label class="field-label" for="address">Address <span class="req">*</span></label>
                        <input type="text" id="address" name="address" value="<?= htmlspecialchars($address) ?>"
                            required>
                        <span class="hint">Used to verify this listing's location. Be as specific as you can.</span>
                    </div>
                    <div>
                        <label class="field-label" for="location_name">Location / Area <span
                                class="req">*</span></label>
                        <input type="text" id="location_name" name="location_name"
                            value="<?= htmlspecialchars($location_name) ?>" required>
                    </div>
                    <div>
                        <label class="field-label" for="price">Monthly rent (KES) <span class="req">*</span></label>
                        <input type="number" id="price" name="price" step="0.01" min="0"
                            value="<?= htmlspecialchars($price) ?>" required>
                    </div>
                    <div>
                        <label class="field-label" for="environment_noise">Environment</label>
                        <select id="environment_noise" name="environment_noise">
                            <?php foreach (["quiet", "moderate", "busy"] as $option): ?>
                            <option value="<?= $option ?>" <?= $environment_noise === $option ? "selected" : "" ?>>
                                <?= ucfirst($option) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="building_name">Building / estate name</label>
                        <input type="text" id="building_name" name="building_name" maxlength="150"
                            value="<?= htmlspecialchars($building_name) ?>">
                        <span class="hint">For apartments and hostels with many similar units.</span>
                    </div>
                    <div>
                        <label class="field-label" for="unit_number">Unit / house number</label>
                        <input type="text" id="unit_number" name="unit_number" maxlength="30"
                            value="<?= htmlspecialchars($unit_number) ?>">
                        <span class="hint">Required if you gave a building name.</span>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Features &amp; amenities</legend>

                <label class="field-label" for="amenities">Amenities <span class="req">*</span></label>
                <textarea id="amenities" name="amenities" rows="3"
                    required><?= htmlspecialchars($amenities) ?></textarea>

                <div class="pills">
                    <label class="pill"><input type="checkbox" name="pets_allowed"
                            <?= $pets_allowed ? "checked" : "" ?>><span>Pets allowed</span></label>
                    <label class="pill"><input type="checkbox" name="parking_available"
                            <?= $parking_available ? "checked" : "" ?>><span>Parking</span></label>
                    <label class="pill"><input type="checkbox" name="wifi_available"
                            <?= $wifi_available ? "checked" : "" ?>><span>Wi-Fi</span></label>
                    <label class="pill"><input type="checkbox" name="water_reliable"
                            <?= $water_reliable ? "checked" : "" ?>><span>Reliable water</span></label>
                    <label class="pill"><input type="checkbox" name="furnished"
                            <?= $furnished ? "checked" : "" ?>><span>Furnished</span></label>
                </div>

                <label class="field-label" for="security_details">Security details</label>
                <textarea id="security_details" name="security_details"
                    rows="3"><?= htmlspecialchars($security_details) ?></textarea>
            </fieldset>

            <fieldset>
                <legend>Pin the property on the map</legend>
                <span class="hint" style="margin-top:14px">
                    You must be at the property when you submit. Allow location access, then drag the pin onto the
                    building's entrance (or tap the map). A pin far from where your phone is will be refused.
                </span>

                <div class="map-bar">
                    <button type="button" id="locateBtn">Use my current location</button>
                    <span id="locStatus">Waiting for your location...</span>
                </div>
                <div id="map"></div>

                <input type="hidden" id="latitude" name="latitude" value="<?= htmlspecialchars($latitude) ?>">
                <input type="hidden" id="longitude" name="longitude" value="<?= htmlspecialchars($longitude) ?>">
            </fieldset>

            <fieldset>
                <legend>Photos</legend>

                <div class="challenge">
                    <div class="code" aria-label="Your verification code"><?= htmlspecialchars($challenge_code) ?></div>
                    <p>
                        <strong>Your verification code.</strong> Write this number on a piece of paper and place it in
                        your <strong>first (cover) photo</strong>, for example next to the door. The admin will look for
                        it.
                        For apartments, also include a photo of the unit's door number and one from its window or
                        balcony.
                    </p>
                </div>

                <div class="camera-row">
                    <button type="button" class="camera-btn" id="cameraBtn">📸 Take a photo now</button>
                    <input type="file" id="camera_input" accept="image/*" capture="environment" hidden>
                    <span class="hint">Photos taken live with your camera are trusted more than downloaded or screenshot
                        images.</span>
                </div>

                <div class="dropzone" id="dropzone">
                    <div class="icon">📷</div>
                    <strong>Click to add photos</strong> or drag them here
                    <small>You can add photos in several rounds. Up to <?= (int)$max_photos ?> photos, JPG, PNG or WebP,
                        5 MB each.</small>
                </div>
                <input type="file" id="property_images" name="property_images[]"
                    accept="image/jpeg,image/png,image/webp" multiple>

                <div class="photo-count">
                    <span id="photoCount">No photos selected</span>
                    <button type="button" class="clear-all" id="clearAll" hidden>Remove all</button>
                </div>
                <div class="photo-error" id="photoError"></div>
                <div class="previews" id="previews"></div>
                <span class="hint">Photos are checked against listings by other landlords, and for camera data, to flag
                    reused, stock or AI-generated images. Your own earlier photos are fine to reuse.</span>
            </fieldset>

            <button type="submit" class="submit">Add Property</button>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script>
    // Proof of presence: the pin must be where the landlord's phone is.
    (function() {
        var form = document.getElementById("propertyForm");
        var statusEl = document.getElementById("locStatus");
        var latField = document.getElementById("submission_latitude");
        var lngField = document.getElementById("submission_longitude");
        var accField = document.getElementById("gps_accuracy");
        var pinLat = document.getElementById("pin_latitude");
        var pinLng = document.getElementById("pin_longitude");
        var mapLat = document.getElementById("latitude");
        var mapLng = document.getElementById("longitude");

        // Default view: Nairobi.
        var start = [-1.2921, 36.8219];
        var hadPin = pinLat.value !== "" && pinLng.value !== "";
        if (hadPin) {
            start = [parseFloat(pinLat.value), parseFloat(pinLng.value)];
        }

        var map = L.map("map").setView(start, hadPin ? 17 : 12);
        L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: "&copy; OpenStreetMap contributors"
        }).addTo(map);

        var marker = null;

        function setPin(lat, lng) {
            if (!marker) {
                marker = L.marker([lat, lng], {
                    draggable: true
                }).addTo(map);
                marker.on("dragend", function() {
                    var p = marker.getLatLng();
                    writePin(p.lat, p.lng);
                });
            } else {
                marker.setLatLng([lat, lng]);
            }
            writePin(lat, lng);
        }

        function writePin(lat, lng) {
            var a = lat.toFixed(7),
                b = lng.toFixed(7);
            pinLat.value = a;
            pinLng.value = b;
            mapLat.value = a;
            mapLng.value = b;
        }

        map.on("click", function(e) {
            setPin(e.latlng.lat, e.latlng.lng);
        });

        if (hadPin) {
            setPin(start[0], start[1]);
        }

        function say(text, cls) {
            statusEl.textContent = text;
            statusEl.className = cls || "";
        }

        function locate() {
            if (!navigator.geolocation) {
                say("Your browser cannot share location. The listing will be flagged for extra checks.", "bad");
                return;
            }
            say("Finding your location...");
            navigator.geolocation.getCurrentPosition(function(pos) {
                latField.value = pos.coords.latitude;
                lngField.value = pos.coords.longitude;
                accField.value = pos.coords.accuracy;
                map.setView([pos.coords.latitude, pos.coords.longitude], 18);
                if (!marker) {
                    setPin(pos.coords.latitude, pos.coords.longitude);
                }
                var acc = Math.round(pos.coords.accuracy);
                if (acc <= 100) {
                    say("Location found (accuracy about " + acc +
                        " m). Now drag the pin onto the building.", "good");
                } else {
                    say("Location found but not precise (about " + acc +
                        " m). Move outside, turn on GPS, then tap the button again.", "bad");
                }
            }, function() {
                say("Location access was denied. The listing will be flagged because your presence cannot be confirmed.",
                    "bad");
            }, {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 0
            });
        }

        document.getElementById("locateBtn").addEventListener("click", locate);
        locate();

        form.addEventListener("submit", function(e) {
            if (pinLat.value === "" || pinLng.value === "") {
                e.preventDefault();
                say("Please drop the pin on the map before submitting.", "bad");
                document.getElementById("map").scrollIntoView({
                    behavior: "smooth",
                    block: "center"
                });
            }
        });
    })();
    </script>

    <script>
    (function() {
        var MAX_PHOTOS = <?= (int)$max_photos ?>;
        var MAX_SIZE = <?= (int)$max_file_size ?>;
        var ALLOWED = ["image/jpeg", "image/png", "image/webp"];

        var input = document.getElementById("property_images");
        var dropzone = document.getElementById("dropzone");
        var previews = document.getElementById("previews");
        var countLabel = document.getElementById("photoCount");
        var errorBox = document.getElementById("photoError");
        var clearAll = document.getElementById("clearAll");

        // Holds every photo chosen so far, across several selections.
        var store = new DataTransfer();
        var urls = [];

        function fileKey(f) {
            return f.name + "|" + f.size + "|" + f.lastModified;
        }

        function addFiles(list) {
            var messages = [];
            var existing = {};
            Array.prototype.forEach.call(store.files, function(f) {
                existing[fileKey(f)] = true;
            });

            Array.prototype.forEach.call(list, function(f) {
                if (ALLOWED.indexOf(f.type) === -1) {
                    messages.push(f.name + " is not a JPG, PNG or WebP image.");
                } else if (f.size > MAX_SIZE) {
                    messages.push(f.name + " is larger than 5 MB.");
                } else if (existing[fileKey(f)]) {
                    return; // already added
                } else if (store.files.length >= MAX_PHOTOS) {
                    if (messages.indexOf("Only " + MAX_PHOTOS + " photos are allowed.") === -1) {
                        messages.push("Only " + MAX_PHOTOS + " photos are allowed.");
                    }
                } else {
                    store.items.add(f);
                    existing[fileKey(f)] = true;
                }
            });

            input.files = store.files;
            errorBox.textContent = messages.join(" ");
            render();
        }

        function removeAt(index) {
            var next = new DataTransfer();
            Array.prototype.forEach.call(store.files, function(f, i) {
                if (i !== index) {
                    next.items.add(f);
                }
            });
            store = next;
            input.files = store.files;
            errorBox.textContent = "";
            render();
        }

        function render() {
            urls.forEach(function(u) {
                URL.revokeObjectURL(u);
            });
            urls = [];
            previews.innerHTML = "";

            Array.prototype.forEach.call(store.files, function(f, i) {
                var url = URL.createObjectURL(f);
                urls.push(url);

                var box = document.createElement("div");
                box.className = "preview";

                var img = document.createElement("img");
                img.src = url;
                img.alt = f.name;
                box.appendChild(img);

                var btn = document.createElement("button");
                btn.type = "button";
                btn.className = "remove";
                btn.setAttribute("aria-label", "Remove " + f.name);
                btn.textContent = "×";
                btn.addEventListener("click", function() {
                    removeAt(i);
                });
                box.appendChild(btn);

                if (i === 0) {
                    var tag = document.createElement("span");
                    tag.className = "cover-tag";
                    tag.textContent = "Cover";
                    box.appendChild(tag);
                }

                previews.appendChild(box);
            });

            var n = store.files.length;
            countLabel.textContent = n === 0 ?
                "No photos selected" :
                n + " of " + MAX_PHOTOS + " photos selected";
            clearAll.hidden = n === 0;
        }

        dropzone.addEventListener("click", function() {
            input.click();
        });

        // Selecting again adds to the list instead of replacing it.
        input.addEventListener("change", function() {
            var chosen = Array.prototype.slice.call(input.files);
            // The browser replaced input.files with only the new picks, so rebuild from store.
            input.files = store.files;
            addFiles(chosen);
        });

        // "Take a photo now": opens the camera on phones and adds to the same list.
        var camBtn = document.getElementById("cameraBtn");
        var camInput = document.getElementById("camera_input");
        camBtn.addEventListener("click", function() {
            camInput.click();
        });
        camInput.addEventListener("change", function() {
            var chosen = Array.prototype.slice.call(camInput.files);
            camInput.value = "";
            addFiles(chosen);
        });

        ["dragenter", "dragover"].forEach(function(evt) {
            dropzone.addEventListener(evt, function(e) {
                e.preventDefault();
                dropzone.classList.add("dragover");
            });
        });

        ["dragleave", "drop"].forEach(function(evt) {
            dropzone.addEventListener(evt, function(e) {
                e.preventDefault();
                dropzone.classList.remove("dragover");
            });
        });

        dropzone.addEventListener("drop", function(e) {
            if (e.dataTransfer && e.dataTransfer.files) {
                addFiles(e.dataTransfer.files);
            }
        });

        clearAll.addEventListener("click", function() {
            store = new DataTransfer();
            input.files = store.files;
            errorBox.textContent = "";
            render();
        });

        render();
    })();
    </script>
</body>

</html>