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
    } else {

        // --- Proof-of-presence check -----------------------------------
        // Compare the landlord's device location (captured by the browser
        // at submission time) against the geocoded listing address. This
        // never blocks submission -- it only flags the listing for admin
        // review, since GPS can be imprecise or permission can be denied.
        $location_flag = 1;
        $location_distance_km = null;

        $geocoded = geocode_address($address . ", " . $location_name . ", Kenya");

        if (
            $geocoded !== null &&
            $submission_latitude !== "" &&
            $submission_longitude !== "" &&
            is_numeric($submission_latitude) &&
            is_numeric($submission_longitude)
        ) {
            $location_distance_km = round(haversine_km(
                (float)$submission_latitude,
                (float)$submission_longitude,
                $geocoded["lat"],
                $geocoded["lng"]
            ), 2);

            $location_flag = $location_distance_km > LOCATION_MISMATCH_THRESHOLD_KM ? 1 : 0;
        }

        // --- Validate all photos before saving anything -----------------
        $files = $_FILES["property_images"] ?? null;

        if ($files && isset($files["name"]) && is_array($files["name"])) {
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

        // Load every photo hash already in the system once, so each new
        // upload can be compared against it (and against earlier photos
        // in this same batch) for near-duplicates or reused/stock photos.
        $known_hashes = [];
        if ($error === "" && !empty($photo_files)) {
            try {
                $hashStmt = $pdo->query(
                    "SELECT photo_hash FROM property_photos WHERE photo_hash IS NOT NULL"
                );
                $known_hashes = $hashStmt->fetchAll(PDO::FETCH_COLUMN);
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
                    $is_duplicate = 0;

                    if ($hash !== null) {
                        foreach ($known_hashes as $existing_hash) {
                            if (hamming_distance($hash, $existing_hash) <= DUPLICATE_HASH_THRESHOLD) {
                                $is_duplicate = 1;
                                break;
                            }
                        }
                        // So a second copy of the same photo later in this
                        // same batch also gets caught.
                        $known_hashes[] = $hash;
                    }

                    $saved_photos[] = [
                        "path" => "uploads/" . $new_name,
                        "hash" => $hash,
                        "is_duplicate" => $is_duplicate
                    ];
                }
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
                         location_distance_km, location_flag)
                     VALUES
                        (:property_key, :landlord_email, :title, :description, :address,
                         :location_name, :price, :amenities, :latitude, :longitude,
                         :pets_allowed, :parking_available, :wifi_available, :water_reliable,
                         :furnished, :security_details, :environment_noise,
                         :submission_latitude, :submission_longitude,
                         :location_distance_km, :location_flag)"
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
                    ":location_flag" => $location_flag
                ]);

                if (!empty($saved_photos)) {
                    $img_stmt = $pdo->prepare(
                        "INSERT INTO property_photos
                            (property_key, photo_path, photo_hash, is_duplicate)
                         VALUES
                            (:property_key, :photo_path, :photo_hash, :is_duplicate)"
                    );

                    foreach ($saved_photos as $photo) {
                        $img_stmt->execute([
                            ":property_key" => $property_key,
                            ":photo_path" => $photo["path"],
                            ":photo_hash" => $photo["hash"],
                            ":is_duplicate" => $photo["is_duplicate"]
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
                <legend>Map location (optional)</legend>
                <div class="grid">
                    <div>
                        <label class="field-label" for="latitude">Latitude</label>
                        <input type="text" id="latitude" name="latitude" value="<?= htmlspecialchars($latitude) ?>">
                    </div>
                    <div>
                        <label class="field-label" for="longitude">Longitude</label>
                        <input type="text" id="longitude" name="longitude" value="<?= htmlspecialchars($longitude) ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Photos</legend>

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
                <span class="hint">Photos are checked against listings already in the system to flag likely reused or
                    stock images.</span>
            </fieldset>

            <button type="submit" class="submit">Add Property</button>
        </form>
    </div>

    <script>
    // Proof-of-presence: ask the browser for the landlord's current
    // location so it can be compared, server-side, against the
    // geocoded listing address. This never blocks submission -- if
    // permission is denied or unavailable, the form still submits,
    // and the listing is simply flagged for the admin to double-check.
    (function() {
        var latField = document.getElementById("submission_latitude");
        var lngField = document.getElementById("submission_longitude");
        var form = document.getElementById("propertyForm");
        var locationSettled = false;

        function markReady() {
            locationSettled = true;
        }

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                latField.value = position.coords.latitude;
                lngField.value = position.coords.longitude;
                markReady();
            }, markReady, {
                timeout: 8000,
                maximumAge: 60000
            });
        } else {
            markReady();
        }

        form.addEventListener("submit", function(e) {
            if (!locationSettled) {
                e.preventDefault();
                setTimeout(function() {
                    if (typeof form.requestSubmit === "function") {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }, 1500);
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