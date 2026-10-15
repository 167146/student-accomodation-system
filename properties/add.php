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

    <style>
    body {
        font-family: Arial, sans-serif;
        background: #f5f3ff;
        margin: 0;
        padding: 30px 15px;
        color: #25213b;
    }

    .container {
        max-width: 700px;
        margin: auto;
        background: white;
        padding: 28px;
        border-radius: 14px;
        box-shadow: 0 5px 20px #00000010;
    }

    h1 {
        color: #6639b7;
    }

    label {
        display: block;
        margin-top: 16px;
        margin-bottom: 6px;
        font-weight: bold;
    }

    input,
    textarea {
        width: 100%;
        box-sizing: border-box;
        padding: 11px;
        border: 1px solid #d8d2e8;
        border-radius: 7px;
        font: inherit;
    }

    textarea {
        min-height: 90px;
        resize: vertical;
    }

    button {
        margin-top: 22px;
        padding: 12px 18px;
        background: #6639b7;
        color: white;
        border: none;
        border-radius: 7px;
        cursor: pointer;
        font-size: 16px;
    }

    .error {
        color: #b42318;
        background: #fff0f0;
        padding: 10px;
        border-radius: 7px;
    }

    .links {
        margin-top: 20px;
    }

    a {
        color: #6639b7;
        margin-right: 12px;
    }
    </style>
</head>

<body>
    <div class="container">

        <h1>Add a Property</h1>
        <p>List your accommodation on UniMtaa.</p>

        <?php if ($error !== ""): ?>
        <p class="error">
            <?= htmlspecialchars($error) ?>
        </p>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <label for="title">Property Title *</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($_POST["title"] ?? "") ?>" required>

            <label for="description">Description *</label>
            <textarea id="description" name="description"
                required><?= htmlspecialchars($_POST["description"] ?? "") ?></textarea>

            <label for="address">Full Address *</label>
            <input type="text" id="address" name="address" value="<?= htmlspecialchars($_POST["address"] ?? "") ?>"
                required>

            <label for="location_name">Area / Neighbourhood *</label>
            <input type="text" id="location_name" name="location_name" placeholder="e.g. Juja, near JKUAT"
                value="<?= htmlspecialchars($_POST["location_name"] ?? "") ?>" required>

            <label for="price">Monthly Rent (KSh) *</label>
            <input type="number" id="price" name="price" min="1" step="0.01"
                value="<?= htmlspecialchars($_POST["price"] ?? "") ?>" required>

            <label for="amenities">Amenities *</label>
            <textarea id="amenities" name="amenities" placeholder="e.g. Wi-Fi, water, security, parking"
                required><?= htmlspecialchars($_POST["amenities"] ?? "") ?></textarea>

            <label for="latitude">Latitude (optional)</label>
            <input type="number" id="latitude" name="latitude" min="-90" max="90" step="any"
                value="<?= htmlspecialchars($_POST["latitude"] ?? "") ?>">

            <label for="longitude">Longitude (optional)</label>
            <input type="number" id="longitude" name="longitude" min="-180" max="180" step="any"
                value="<?= htmlspecialchars($_POST["longitude"] ?? "") ?>">

            <label for="property_image">Property Photo (optional, max 5 MB)</label>
            <input type="file" id="property_image" name="property_image" accept=".jpg,.jpeg,.png,.webp">

            <button type="submit">Save Property</button>
        </form>

        <div class="links">
            <a href="my-properties.php">My Properties</a>
            <a href="../landlord/landlord.php">Dashboard</a>
        </div>

    </div>
</body>

</html>