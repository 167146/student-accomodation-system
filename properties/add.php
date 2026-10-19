<?php
session_start();

require_once "../config/database.php";

// Only logged-in landlords can add properties.
if (
    empty($_SESSION["email"]) ||
    ($_SESSION["role"] ?? "") !== "landlord"
) {
    header("Location: ../public/landlord_login.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $location_name = trim($_POST["location_name"] ?? "");
    $price = $_POST["price"] ?? "";
    $amenities = trim($_POST["amenities"] ?? "");

    $latitude = $_POST["latitude"] ?? "";
    $longitude = $_POST["longitude"] ?? "";

    if (
        $title === "" ||
        $description === "" ||
        $address === "" ||
        $location_name === "" ||
        $price === "" ||
        $amenities === ""
    ) {
        $error = "Please fill in all required fields.";

    } elseif (!is_numeric($price) || (float)$price <= 0) {
        $error = "Please enter a valid monthly rent.";

    } elseif (
        ($latitude !== "" && !is_numeric($latitude)) ||
        ($longitude !== "" && !is_numeric($longitude))
    ) {
        $error = "Latitude and longitude must be valid numbers.";

    } elseif (
        ($latitude === "") !== ($longitude === "")
    ) {
        $error = "Enter both latitude and longitude, or leave both blank.";

    } elseif (
        ($latitude !== "" &&
            ((float)$latitude < -90 || (float)$latitude > 90)) ||
        ($longitude !== "" &&
            ((float)$longitude < -180 || (float)$longitude > 180))
    ) {
        $error = "The coordinates are outside the valid range.";

    } else {
        $image_path = null;
        $upload_dir = __DIR__ . "/uploads/";

        // Create the upload folder if it does not exist.
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // A property photo is optional.
        if (
            isset($_FILES["property_image"]) &&
            $_FILES["property_image"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {
            $file = $_FILES["property_image"];

            if ($file["error"] !== UPLOAD_ERR_OK) {
                $error = "The photo could not be uploaded.";

            } elseif ($file["size"] > 5 * 1024 * 1024) {
                $error = "The photo must be 5 MB or smaller.";

            } else {
                $image_info = getimagesize($file["tmp_name"]);

                $allowed_types = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                $mime = $image_info["mime"] ?? "";

                if (
                    $image_info === false ||
                    !isset($allowed_types[$mime])
                ) {
                    $error = "Upload a JPG, PNG or WEBP image.";

                } else {
                    $filename = bin2hex(random_bytes(16))
                        . "." . $allowed_types[$mime];

                    if (
                        move_uploaded_file(
                            $file["tmp_name"],
                            $upload_dir . $filename
                        )
                    ) {
                        $image_path = "uploads/" . $filename;
                    } else {
                        $error = "Unable to save the property photo.";
                    }
                }
            }
        }

        if ($error === "") {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO properties
                    (
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
                        image_path
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'under_review', ?)"
                );

                $stmt->execute([
                    $_SESSION["email"],
                    $title,
                    $description,
                    $address,
                    $location_name,
                    $latitude === "" ? null : $latitude,
                    $longitude === "" ? null : $longitude,
                    $price,
                    $amenities,
                    $image_path
                ]);

                header("Location: my-properties.php?added=1");
                exit;

            } catch (PDOException $e) {
                // Remove the uploaded image if the database insert fails.
                if ($image_path !== null &&
                    is_file(__DIR__ . "/" . $image_path)) {
                    unlink(__DIR__ . "/" . $image_path);
                }

                $error = "Could not save the property. Please check your database table structure.";
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
    <title>Add Property | UniMtaa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">

    <style>
    :root {
        --ink: #1E1033;
        --ink-soft: #5B4E78;
        --violet: #7C3AED;
        --violet-deep: #4C1D95;
        --lilac: #B794F4;
        --paper: #F8F6FC;
        --card: #FFFFFF;
        --hairline: #E8E1F7;
        --red: #B3372C;
        --red-soft: #FBE7E7;
    }

    * {
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        margin: 0;
        padding: 40px 16px 60px;
        color: var(--ink);

        background-image:
            linear-gradient(rgba(30, 16, 51, .45), rgba(76, 29, 149, .4)),
            url('../assets/images/background.jpg');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: fixed;
    }

    h1,
    h3 {
        font-family: 'Poppins', sans-serif;
    }

    .container {
        max-width: 680px;
        margin: 0 auto;
        background: var(--card);
        border: 1px solid var(--hairline);
        border-radius: 18px;
        padding: 0 0 32px;
        box-shadow: 0 20px 40px rgba(76, 29, 149, .08);
        overflow: hidden;
    }

    .header {
        background: linear-gradient(135deg, var(--violet), var(--violet-deep));
        padding: 30px 32px;
        color: #fff;
    }

    .header h1 {
        margin: 0 0 4px;
        font-size: 22px;
        font-weight: 700;
    }

    .header p {
        margin: 0;
        font-size: 13.5px;
        color: #E6DAFB;
    }

    form {
        padding: 28px 32px 0;
    }

    .section-label {
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--violet);
        margin: 28px 0 14px;
    }

    .section-label:first-child {
        margin-top: 0;
    }

    label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
        font-size: 13.5px;
        color: var(--ink);
    }

    .field {
        margin-bottom: 18px;
    }

    .req {
        color: var(--violet);
        font-weight: 700;
    }

    input[type=text],
    input[type=number],
    textarea,
    select {
        width: 100%;
        padding: 11px 13px;
        border: 1.5px solid var(--hairline);
        border-radius: 10px;
        font: inherit;
        font-size: 14px;
        background: #fff;
        color: var(--ink);
        transition: border-color .15s;
    }

    input:focus,
    textarea:focus,
    select:focus {
        outline: none;
        border-color: var(--violet);
    }

    textarea {
        min-height: 90px;
        resize: vertical;
    }

    .row2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    @media (max-width:520px) {
        .row2 {
            grid-template-columns: 1fr;
        }
    }

    .file-field {
        border: 1.5px dashed var(--hairline);
        border-radius: 10px;
        padding: 14px;
        font-size: 13px;
        color: var(--ink-soft);
        background: var(--paper);
    }

    .file-field input {
        border: none;
        padding: 0;
        background: none;
        font-size: 13px;
    }

    .checks {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 18px;
    }

    @media (max-width:520px) {
        .checks {
            grid-template-columns: 1fr;
        }
    }

    .check {
        display: flex;
        align-items: center;
        gap: 9px;
        border: 1.5px solid var(--hairline);
        border-radius: 10px;
        padding: 11px 13px;
        font-size: 13.5px;
        font-weight: 500;
        cursor: pointer;
        background: #fff;
    }

    .check:has(input:checked) {
        border-color: var(--violet);
        background: #F4EEFD;
    }

    .check input {
        width: 16px;
        height: 16px;
        accent-color: var(--violet);
        margin: 0;
    }

    button {
        width: 100%;
        margin-top: 26px;
        padding: 14px;
        background: linear-gradient(135deg, var(--violet), var(--violet-deep));
        color: #fff;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-size: 15px;
        font-weight: 600;
        font-family: 'Inter', sans-serif;
        box-shadow: 0 8px 18px rgba(124, 58, 237, .3);
    }

    .error {
        margin: 24px 32px 0;
        color: var(--red);
        background: var(--red-soft);
        padding: 12px 14px;
        border-radius: 10px;
        font-size: 13.5px;
    }

    .links {
        display: flex;
        gap: 18px;
        padding: 22px 32px 0;
        font-size: 13.5px;
    }

    .links a {
        color: var(--violet);
        text-decoration: none;
        font-weight: 600;
    }

    .links a:hover {
        text-decoration: underline;
    }
    </style>
</head>

<body>
    <div class="container">

        <div class="header">
            <h1>Add a Property</h1>
            <p>List your accommodation on UniMtaa.</p>
        </div>

        <?php if ($error !== ""): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="section-label">Property details</div>

            <div class="field">
                <label for="title">Property Title <span class="req">*</span></label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($_POST["title"] ?? "") ?>"
                    required>
            </div>

            <div class="field">
                <label for="description">Description <span class="req">*</span></label>
                <textarea id="description" name="description"
                    required><?= htmlspecialchars($_POST["description"] ?? "") ?></textarea>
            </div>

            <div class="field">
                <label for="address">Full Address <span class="req">*</span></label>
                <input type="text" id="address" name="address" value="<?= htmlspecialchars($_POST["address"] ?? "") ?>"
                    required>
            </div>

            <div class="field">
                <label for="location_name">Area / Neighbourhood <span class="req">*</span></label>
                <input type="text" id="location_name" name="location_name" placeholder="e.g. Juja, near JKUAT"
                    value="<?= htmlspecialchars($_POST["location_name"] ?? "") ?>" required>
            </div>

            <div class="field">
                <label for="price">Monthly Rent (KSh) <span class="req">*</span></label>
                <input type="number" id="price" name="price" min="1" step="0.01"
                    value="<?= htmlspecialchars($_POST["price"] ?? "") ?>" required>
            </div>

            <div class="field">
                <label for="amenities">Amenities <span class="req">*</span></label>
                <textarea id="amenities" name="amenities" placeholder="e.g. Wi-Fi, water, security, parking"
                    required><?= htmlspecialchars($_POST["amenities"] ?? "") ?></textarea>
            </div>

            <div class="section-label">Location coordinates (optional)</div>

            <div class="row2">
                <div class="field">
                    <label for="latitude">Latitude</label>
                    <input type="number" id="latitude" name="latitude" min="-90" max="90" step="any"
                        value="<?= htmlspecialchars($_POST["latitude"] ?? "") ?>">
                </div>
                <div class="field">
                    <label for="longitude">Longitude</label>
                    <input type="number" id="longitude" name="longitude" min="-180" max="180" step="any"
                        value="<?= htmlspecialchars($_POST["longitude"] ?? "") ?>">
                </div>
            </div>

            <div class="section-label">Photo</div>

            <div class="field">
                <label for="property_image">Property Photo (optional, max 5 MB)</label>
                <div class="file-field">
                    <input type="file" id="property_image" name="property_image" accept=".jpg,.jpeg,.png,.webp">
                </div>
            </div>

            <div class="section-label">Facilities &amp; environment</div>

            <div class="checks">
                <label class="check"><input type="checkbox" name="pets_allowed" value="1"> Pets are allowed</label>
                <label class="check"><input type="checkbox" name="parking_available" value="1"> Parking is
                    available</label>
                <label class="check"><input type="checkbox" name="wifi_available" value="1"> Wi-Fi is available</label>
                <label class="check"><input type="checkbox" name="water_reliable" value="1"> Reliable water
                    supply</label>
                <label class="check"><input type="checkbox" name="furnished" value="1"> Furnished accommodation</label>
            </div>

            <div class="field">
                <label for="security_details">Security details</label>
                <textarea id="security_details" name="security_details"
                    placeholder="Describe security arrangements"></textarea>
            </div>

            <div class="field">
                <label for="environment_noise">Environment</label>
                <select id="environment_noise" name="environment_noise">
                    <option value="quiet">Quiet</option>
                    <option value="moderate" selected>Moderate</option>
                    <option value="busy">Busy</option>
                </select>
            </div>

            <button type="submit">Save Property</button>

        </form>

        <div class="links">
            <a href="my-properties.php">My Properties</a>
            <a href="../landlord/landlord.php">Dashboard</a>
        </div>

    </div>
</body>

</html>