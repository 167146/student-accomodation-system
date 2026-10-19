<?php
session_start();
require_once "../config/database.php";

if (
    !isset($_SESSION["email"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "student"
) {
    header("Location: login.php");
    exit;
}

// Approximate Strathmore University campus coordinates.
$campusLatitude = -1.30981;
$campusLongitude = 36.81449;

$rent = filter_input(INPUT_GET, "max_rent", FILTER_VALIDATE_FLOAT);
$distance = filter_input(INPUT_GET, "max_distance", FILTER_VALIDATE_FLOAT);

$maxRent = ($rent !== false && $rent !== null && $rent > 0)
    ? min($rent, 1000000) : 12000;

$maxDistance = ($distance !== false && $distance !== null && $distance > 0)
    ? min($distance, 100) : 3;

// Preferences: checkboxes are optional filters.
// If checked, the property must satisfy that preference.
$noPets = isset($_GET["no_pets"]);
$needSecurity = isset($_GET["security"]);
$needWifi = isset($_GET["wifi"]);
$needQuiet = isset($_GET["quiet"]);
$needParking = isset($_GET["parking"]);
$needWater = isset($_GET["water"]);
$needFurnished = isset($_GET["furnished"]);

$properties = [];
$errorMessage = "";

try {
    $conditions = [
        "availability_status = 'available'",
        "price <= :max_rent",
        "latitude IS NOT NULL",
        "longitude IS NOT NULL",
        "latitude BETWEEN -90 AND 90",
        "longitude BETWEEN -180 AND 180"
    ];

    $params = [":max_rent" => $maxRent];

    if ($noPets) {
        $conditions[] = "pets_allowed = 0";
    }

    if ($needSecurity) {
        $conditions[] =
            "security_details IS NOT NULL
             AND TRIM(security_details) <> ''";
    }

    if ($needWifi) {
        $conditions[] = "wifi_available = 1";
    }

    if ($needQuiet) {
        $conditions[] = "environment_noise = 'quiet'";
    }

    if ($needParking) {
        $conditions[] = "parking_available = 1";
    }

    if ($needWater) {
        $conditions[] = "water_reliable = 1";
    }

    if ($needFurnished) {
        $conditions[] = "furnished = 1";
    }

    // Haversine formula calculates straight-line distance in kilometres.
    $distanceSql = "
        (6371 * ACOS(
            LEAST(1, GREATEST(-1,
                COS(RADIANS(:campus_lat))
                * COS(RADIANS(latitude))
                * COS(RADIANS(longitude) - RADIANS(:campus_lng))
                + SIN(RADIANS(:campus_lat))
                * SIN(RADIANS(latitude))
            ))
        ))
    ";

    $conditions[] = "$distanceSql <= :max_distance";

    $sql = "SELECT
                property_key,
                landlord_email,
                title,
                description,
                address,
                location_name,
                latitude,
                longitude,
                price,
                amenities,
                availability_status,
                image_path,
                pets_allowed,
                parking_available,
                wifi_available,
                water_reliable,
                security_details,
                furnished,
                environment_noise,
                $distanceSql AS distance_km
            FROM properties
            WHERE " . implode(" AND ", $conditions) . "
            ORDER BY distance_km ASC, price ASC";

    $params[":campus_lat"] = $campusLatitude;
    $params[":campus_lng"] = $campusLongitude;
    $params[":max_distance"] = $maxDistance;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $properties = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Accommodation search failed: " . $e->getMessage());
    $errorMessage =
        "Search is temporarily unavailable. Check the properties table and its columns.";
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Find Your Home | CampusNest</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
    .search-page {
        max-width: 1150px;
        margin: 35px auto;
        padding: 20px;
    }

    .search-panel {
        background: #fff;
        border-radius: 18px;
        padding: 25px;
        box-shadow: 0 8px 28px rgba(60, 30, 90, .10);
        margin-bottom: 28px;
    }

    .search-fields {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 16px;
    }

    .search-panel label {
        display: block;
        font-weight: 600;
        margin-bottom: 7px;
    }

    .search-panel input[type="number"] {
        width: 100%;
        box-sizing: border-box;
        padding: 11px;
        border: 1px solid #ddd3ee;
        border-radius: 9px;
    }

    .preferences {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin: 22px 0;
    }

    .preferences label {
        display: flex;
        align-items: center;
        gap: 7px;
        font-weight: 400;
    }

    .search-button {
        background: #7c3aed;
        color: white;
        padding: 12px 20px;
        border: 0;
        border-radius: 9px;
        cursor: pointer;
    }

    .results-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 22px;
    }

    .property-card {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 6px 24px rgba(60, 30, 90, .10);
    }

    .property-card img {
        width: 100%;
        height: 195px;
        object-fit: cover;
    }

    .property-content {
        padding: 18px;
    }

    .property-price {
        font-size: 1.15rem;
        font-weight: 700;
        color: #7c3aed;
    }

    .property-distance {
        font-size: .9rem;
        color: #62526f;
    }

    .empty-state,
    .error-message {
        padding: 20px;
        background: #fff;
        border-radius: 12px;
    }

    @media (max-width: 600px) {
        .search-page {
            padding: 12px;
        }
    }
    </style>
</head>

<body>
    <main class="search-page">
        <h1>Find Your Ideal Student Home</h1>
        <p>Personalise your search to find accommodation that suits you.</p>

        <form class="search-panel" method="GET">
            <div class="search-fields">
                <div>
                    <label for="max_rent">Maximum monthly rent (KES)</label>
                    <input id="max_rent" name="max_rent" type="number" min="1" max="1000000" value="<?= h($maxRent) ?>"
                        required>
                </div>

                <div>
                    <label for="max_distance">Maximum distance (km)</label>
                    <input id="max_distance" name="max_distance" type="number" min="0.1" max="100" step="0.1"
                        value="<?= h($maxDistance) ?>" required>
                </div>
            </div>

            <h3>My preferences</h3>
            <div class="preferences">
                <label>
                    <input type="checkbox" name="no_pets" value="1" <?= $noPets ? "checked" : "" ?>>
                    No pets allowed
                </label>

                <label>
                    <input type="checkbox" name="security" value="1" <?= $needSecurity ? "checked" : "" ?>>
                    Security details provided
                </label>

                <label>
                    <input type="checkbox" name="wifi" value="1" <?= $needWifi ? "checked" : "" ?>>
                    Wi-Fi
                </label>

                <label>
                    <input type="checkbox" name="quiet" value="1" <?= $needQuiet ? "checked" : "" ?>>
                    Quiet environment
                </label>

                <label>
                    <input type="checkbox" name="parking" value="1" <?= $needParking ? "checked" : "" ?>>
                    Parking
                </label>

                <label>
                    <input type="checkbox" name="water" value="1" <?= $needWater ? "checked" : "" ?>>
                    Reliable water
                </label>

                <label>
                    <input type="checkbox" name="furnished" value="1" <?= $needFurnished ? "checked" : "" ?>>
                    Furnished
                </label>
            </div>

            <button class="search-button" type="submit">
                Search Accommodation
            </button>
        </form>

        <?php if ($errorMessage !== ""): ?>
        <p class="error-message"><?= h($errorMessage) ?></p>

        <?php elseif (!$properties): ?>
        <div class="empty-state">
            <h2>No matching properties found</h2>
            <p>Try increasing your budget or distance, or removing one preference.</p>
        </div>

        <?php else: ?>
        <h2><?= count($properties) ?> matching properties</h2>

        <div class="results-grid">
            <?php foreach ($properties as $property): ?>
            <article class="property-card">
                <?php
                    $image = $property["image_path"] ?? "";
                    $imageFile = "../" . ltrim($image, "/");
                    $hasImage = $image !== "" &&
                        strpos($image, "assets/images/properties/") === 0 &&
                        is_file($imageFile);
                    ?>

                <?php if ($hasImage): ?>
                <img src="../<?= h($image) ?>" alt="<?= h($property["title"]) ?>">
                <?php endif; ?>

                <div class="property-content">
                    <h3><?= h($property["title"]) ?></h3>
                    <p><?= h($property["location_name"]) ?></p>
                    <p><?= h($property["address"]) ?></p>

                    <p class="property-price">
                        KSh <?= number_format((float)$property["price"]) ?>/month
                    </p>

                    <p class="property-distance">
                        <?= number_format((float)$property["distance_km"], 2) ?>
                        km straight-line distance from campus
                    </p>

                    <p><?= nl2br(h($property["description"])) ?></p>
                    <p><strong>Amenities:</strong> <?= h($property["amenities"]) ?></p>
                    <p><strong>Pets:</strong>
                        <?= (int)$property["pets_allowed"] === 1
                                ? "Allowed" : "Not allowed" ?>
                    </p>
                    <p><strong>Security:</strong>
                        <?= h($property["security_details"]) ?></p>

                    <p><strong>Wi-Fi:</strong>
                        <?= (int)$property["wifi_available"] === 1
                                ? "Available" : "Not listed" ?>
                    </p>

                    <p><strong>Environment:</strong>
                        <?= h($property["environment_noise"]) ?></p>

                    <p><strong>Parking:</strong>
                        <?= (int)$property["parking_available"] === 1
                                ? "Available" : "Not listed" ?>
                    </p>

                    <p><strong>Furnished:</strong>
                        <?= (int)$property["furnished"] === 1
                                ? "Yes" : "No" ?>
                    </p>

                    <!-- Link to the property details page can be added
                             after confirming your view.php implementation. -->
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</body>

</html>