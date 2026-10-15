<?php
session_start();

require_once "../config/database.php";

if (
    empty($_SESSION["email"]) ||
    ($_SESSION["role"] ?? "") !== "landlord"
) {
    header("Location: ../public/landlord_login.php");
    exit;
}

$stmt = $pdo->prepare(
    "SELECT property_key, title, location_name, price,
            availability_status, image_path, created_at
     FROM properties
     WHERE landlord_email = ?
     ORDER BY created_at DESC"
);

$stmt->execute([$_SESSION["email"]]);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Properties | UniMtaa</title>

    <style>
    body {
        font-family: Arial, sans-serif;
        background: #f5f3ff;
        padding: 25px;
        color: #25213b;
    }

    .container {
        max-width: 1000px;
        margin: auto;
    }

    .property {
        background: white;
        padding: 18px;
        margin: 18px 0;
        border-radius: 12px;
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        box-shadow: 0 4px 15px #0000000d;
    }

    .property img {
        width: 220px;
        max-width: 100%;
        height: 160px;
        object-fit: cover;
        border-radius: 8px;
    }

    .details {
        flex: 1;
        min-width: 200px;
    }

    .status {
        display: inline-block;
        background: #eee7ff;
        color: #6639b7;
        padding: 6px 10px;
        border-radius: 20px;
        font-size: 13px;
    }

    .button {
        display: inline-block;
        background: #6639b7;
        color: white;
        text-decoration: none;
        padding: 10px 14px;
        border-radius: 7px;
    }

    .empty {
        background: white;
        padding: 25px;
        border-radius: 10px;
    }
    </style>
</head>

<body>
    <div class="container">

        <h1>My Properties</h1>
        <p>Manage the accommodation listings you have submitted to UniMtaa.</p>

        <?php if (isset($_GET["added"])): ?>
        <p>Property submitted successfully. It is awaiting review.</p>
        <?php endif; ?>

        <p>
            <a class="button" href="add.php">+ Add Property</a>
            <a href="../landlord/landlord.php">Back to Dashboard</a>
        </p>

        <?php if (empty($properties)): ?>

        <div class="empty">
            <h3>You haven't added any properties yet.</h3>
            <p>Start by adding your first accommodation listing.</p>
            <a class="button" href="add.php">Add Your First Property</a>
        </div>

        <?php else: ?>

        <?php foreach ($properties as $property): ?>

        <div class="property">

            <?php
                $imageUrl = "";

                if (!empty($property["image_path"])) {
                    $imageUrl = $property["image_path"];
                }
                ?>

            <?php if ($imageUrl !== ""): ?>
            <img src="<?= htmlspecialchars($imageUrl) ?>" alt="Property photo">
            <?php endif; ?>

            <div class="details">

                <h2>
                    <?= htmlspecialchars($property["title"]) ?>
                </h2>

                <p>
                    <strong>Location:</strong>
                    <?= htmlspecialchars($property["location_name"]) ?>
                </p>

                <p>
                    <strong>Monthly Rent:</strong>
                    KSh <?= number_format((float)$property["price"], 2) ?>
                </p>

                <p>
                    <strong>Status:</strong>
                    <span class="status">
                        <?= htmlspecialchars(
                                ucfirst(str_replace(
                                    "_", " ",
                                    $property["availability_status"]
                                ))
                            ) ?>
                    </span>
                </p>

                <p>
                    <strong>Submitted:</strong>
                    <?= htmlspecialchars($property["created_at"]) ?>
                </p>

            </div>
        </div>

        <?php endforeach; ?>

        <?php endif; ?>

    </div>
</body>

</html>