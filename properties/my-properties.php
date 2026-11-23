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

/*
 * "Mark as occupied / vacant": lets a landlord advertise the same house
 * again when it becomes vacant, without creating a new listing (so there
 * is no duplicate-photo flag). Only verified listings can be toggled.
 */
if (empty($_SESSION["mp_csrf"])) {
    $_SESSION["mp_csrf"] = bin2hex(random_bytes(32));
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "toggle_availability" &&
    hash_equals($_SESSION["mp_csrf"], $_POST["csrf_token"] ?? "")
) {
    $toggleStmt = $pdo->prepare(
        "SELECT availability_status, verification_status
         FROM properties
         WHERE property_key = ? AND landlord_email = ?
         LIMIT 1"
    );
    $toggleStmt->execute([$_POST["property_key"] ?? "", $_SESSION["email"]]);
    $current = $toggleStmt->fetch(PDO::FETCH_ASSOC);

    if ($current && $current["verification_status"] === "approved") {
        $newStatus = $current["availability_status"] === "available"
            ? "unavailable"
            : "available";

        $update = $pdo->prepare(
            "UPDATE properties
             SET availability_status = ?
             WHERE property_key = ? AND landlord_email = ?"
        );
        $update->execute([$newStatus, $_POST["property_key"], $_SESSION["email"]]);

        header("Location: my-properties.php?status=" . $newStatus);
        exit;
    }

    header("Location: my-properties.php");
    exit;
}

/*
 * Same query as before, plus the verification fields and each listing's
 * first uploaded photo (from property_photos) to use as its cover image.
 * image_path is kept as a fallback for older listings.
 */
$stmt = $pdo->prepare(
    "SELECT p.property_key, p.title, p.location_name, p.price,
            p.availability_status, p.image_path, p.created_at,
            p.verification_status, p.verification_notes,
            (SELECT ph.photo_path
             FROM property_photos ph
             WHERE ph.property_key = p.property_key
             ORDER BY ph.uploaded_at ASC
             LIMIT 1) AS cover_photo,
            (SELECT COUNT(*)
             FROM property_photos ph2
             WHERE ph2.property_key = p.property_key) AS photo_count
     FROM properties p
     WHERE p.landlord_email = ?
     ORDER BY p.created_at DESC"
);

$stmt->execute([$_SESSION["email"]]);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Properties | CampusNest</title>

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
        --violet-dark: #2E1065;
        --lilac: #B794F4;
        --paper: #F8F6FC;
        --card: #FFFFFF;
        --hairline: #E9E1F2;
        --green: #0E9F6E;
        --green-soft: #E3F6EF;
        --amber: #C08A1E;
        --amber-soft: #FCF3DF;
        --red: #B3372C;
        --red-soft: #FBE7E7;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: 'Inter', sans-serif;
        background: var(--paper);
        color: var(--ink);
    }

    h1,
    h2,
    h3 {
        font-family: 'Poppins', sans-serif;
    }

    /* ---------- Header ---------- */

    .page-header {
        background:
            radial-gradient(circle at 85% 0%, rgba(183, 148, 244, .35), transparent 55%),
            linear-gradient(120deg, var(--violet-dark), var(--violet) 70%, #8B5CF6);
        color: #fff;
        padding: 44px 24px 70px;
    }

    .header-inner,
    .container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .page-header h1 {
        margin: 0 0 8px;
        font-size: clamp(26px, 4vw, 34px);
        font-weight: 700;
    }

    .page-header p {
        margin: 0;
        color: rgba(255, 255, 255, .85);
        font-size: 15px;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 18px;
        flex-wrap: wrap;
        margin-top: 24px;
    }

    .button {
        display: inline-block;
        background: #fff;
        color: var(--violet-deep);
        text-decoration: none;
        padding: 11px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        box-shadow: 0 8px 18px rgba(0, 0, 0, .18);
        transition: transform .15s;
    }

    .button:hover {
        transform: translateY(-2px);
    }

    .back-link {
        color: rgba(255, 255, 255, .9);
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    /* ---------- Content ---------- */

    .container {
        padding: 0 24px 60px;
        margin-top: -36px;
    }

    .notice {
        background: var(--green-soft);
        color: #0B6B4C;
        border-radius: 12px;
        padding: 14px 18px;
        font-size: 14px;
        margin-bottom: 18px;
        box-shadow: 0 8px 20px rgba(76, 29, 149, .08);
    }

    .property {
        background: var(--card);
        border: 1px solid var(--hairline);
        border-radius: 18px;
        margin-bottom: 20px;
        display: flex;
        overflow: hidden;
        box-shadow: 0 10px 26px rgba(76, 29, 149, .07);
        transition: box-shadow .15s, transform .15s;
    }

    .property:hover {
        box-shadow: 0 16px 32px rgba(76, 29, 149, .12);
        transform: translateY(-2px);
    }

    .cover {
        position: relative;
        width: 260px;
        flex-shrink: 0;
        background: linear-gradient(135deg, #F3E8FF, #E9E1F2);
        display: grid;
        place-items: center;
        font-size: 38px;
    }

    .cover img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .photo-count {
        position: absolute;
        left: 12px;
        bottom: 12px;
        background: rgba(30, 16, 51, .75);
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
    }

    .details {
        flex: 1;
        min-width: 0;
        padding: 22px 26px;
    }

    .details-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        flex-wrap: wrap;
    }

    .details h2 {
        margin: 0 0 4px;
        font-size: 20px;
        color: var(--violet-dark);
    }

    .location {
        color: var(--ink-soft);
        font-size: 14px;
    }

    .price {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 20px;
        color: var(--ink);
        white-space: nowrap;
    }

    .price small {
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        font-size: 12.5px;
        color: var(--ink-soft);
    }

    .badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin: 16px 0 12px;
    }

    .status {
        display: inline-block;
        background: #F3E8FF;
        color: var(--violet-deep);
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12.5px;
        font-weight: 600;
    }

    .status.available {
        background: var(--green-soft);
        color: #0B6B4C;
    }

    .status.unavailable,
    .status.rejected {
        background: var(--red-soft);
        color: var(--red);
    }

    .status.pending,
    .status.under_review {
        background: var(--amber-soft);
        color: #8A6314;
    }

    .status.approved {
        background: var(--green-soft);
        color: #0B6B4C;
    }

    .verify-note {
        font-size: 13px;
        color: var(--ink-soft);
        margin: 0 0 12px;
    }

    .reject-note {
        background: var(--red-soft);
        color: var(--red);
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 13px;
        margin: 0 0 12px;
    }

    .submitted {
        font-size: 12.5px;
        color: var(--ink-soft);
    }

    /* ---------- Empty state ---------- */

    .empty {
        background: var(--card);
        border: 1px dashed var(--lilac);
        border-radius: 18px;
        padding: 48px 24px;
        text-align: center;
        box-shadow: 0 10px 26px rgba(76, 29, 149, .06);
    }

    .empty .big {
        font-size: 42px;
        margin-bottom: 8px;
    }

    .empty h3 {
        margin: 0 0 6px;
        color: var(--violet-dark);
    }

    .empty p {
        margin: 0 0 20px;
        color: var(--ink-soft);
    }

    .empty .button {
        background: linear-gradient(135deg, var(--violet), var(--violet-deep));
        color: #fff;
    }

    .vacancy-form {
        margin-top: 14px;
    }

    .vacancy-btn {
        font: inherit;
        font-size: 13px;
        font-weight: 600;
        padding: 9px 16px;
        border-radius: 10px;
        border: 1.5px solid var(--violet);
        background: #fff;
        color: var(--violet-deep);
        cursor: pointer;
    }

    .vacancy-btn:hover {
        background: #F3E8FF;
    }

    .vacancy-btn.go {
        background: var(--violet);
        color: #fff;
    }

    @media (max-width: 680px) {
        .property {
            flex-direction: column;
        }

        .cover {
            width: 100%;
            height: 190px;
        }

        .details {
            padding: 18px;
        }
    }
    </style>
</head>

<body>

    <header class="page-header">
        <div class="header-inner">
            <h1>My Properties</h1>
            <p>Manage the accommodation listings you have submitted to CampusNest.</p>

            <div class="header-actions">
                <a class="button" href="add.php">+ Add Property</a>
                <a class="back-link" href="../dashboard/landlord.php">&larr; Back to Dashboard</a>
            </div>
        </div>
    </header>

    <div class="container">

        <?php if (($_GET["status"] ?? "") === "available"): ?>
        <div class="notice">Marked as vacant. Students can see this listing again.</div>
        <?php elseif (($_GET["status"] ?? "") === "unavailable"): ?>
        <div class="notice">Marked as occupied. It is hidden from students until you mark it vacant.</div>
        <?php endif; ?>

        <?php if (isset($_GET["added"])): ?>
        <div class="notice">Property submitted successfully. It is awaiting review.</div>
        <?php endif; ?>

        <?php if (empty($properties)): ?>

        <div class="empty">
            <div class="big">🏠</div>
            <h3>You haven't added any properties yet.</h3>
            <p>Start by adding your first accommodation listing.</p>
            <a class="button" href="add.php">Add Your First Property</a>
        </div>

        <?php else: ?>

        <?php foreach ($properties as $property): ?>

        <?php
            // Prefer the first uploaded photo; fall back to the old image_path.
            $imageUrl = "";

            if (!empty($property["cover_photo"])) {
                $imageUrl = $property["cover_photo"];
            } elseif (!empty($property["image_path"])) {
                $imageUrl = $property["image_path"];
            }

            $verification = $property["verification_status"] ?? "pending";
            $availability = $property["availability_status"] ?? "under_review";
            $photoCount = (int)($property["photo_count"] ?? 0);
        ?>

        <div class="property">

            <div class="cover">
                <?php if ($imageUrl !== ""): ?>
                <img src="<?= htmlspecialchars($imageUrl) ?>" alt="Property photo">
                <?php else: ?>
                🏠
                <?php endif; ?>

                <?php if ($photoCount > 0): ?>
                <span class="photo-count"><?= $photoCount ?> photo<?= $photoCount === 1 ? "" : "s" ?></span>
                <?php endif; ?>
            </div>

            <div class="details">

                <div class="details-top">
                    <div>
                        <h2><?= htmlspecialchars($property["title"]) ?></h2>
                        <div class="location">📍 <?= htmlspecialchars($property["location_name"]) ?></div>
                    </div>

                    <div class="price">
                        KSh <?= number_format((float)$property["price"], 2) ?>
                        <small>/ month</small>
                    </div>
                </div>

                <div class="badges">
                    <span
                        class="status <?= htmlspecialchars(preg_replace("/[^a-z_]/", "", strtolower($availability))) ?>">
                        <?= htmlspecialchars(
                            ucfirst(str_replace(
                                "_", " ",
                                $availability
                            ))
                        ) ?>
                    </span>

                    <span
                        class="status <?= htmlspecialchars(preg_replace("/[^a-z_]/", "", strtolower($verification))) ?>">
                        <?= $verification === "approved" ? "✓ Verified" : htmlspecialchars(ucfirst($verification)) ?>
                    </span>
                </div>

                <?php if ($verification === "pending"): ?>
                <p class="verify-note">Waiting for an admin to verify this listing before students can see it.</p>
                <?php elseif ($verification === "approved"): ?>
                <p class="verify-note">This listing is verified and visible to students.</p>
                <?php elseif ($verification === "rejected"): ?>
                <div class="reject-note">
                    <strong>This listing was not approved.</strong>
                    <?php if (!empty($property["verification_notes"])): ?>
                    <br>Admin note: <?= htmlspecialchars($property["verification_notes"]) ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="submitted">
                    Submitted <?= htmlspecialchars(date("j M Y, g:i A", strtotime($property["created_at"]))) ?>
                </div>

                <?php if ($verification === "approved"): ?>
                <form method="POST" class="vacancy-form">
                    <input type="hidden" name="action" value="toggle_availability">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["mp_csrf"]) ?>">
                    <input type="hidden" name="property_key" value="<?= htmlspecialchars($property["property_key"]) ?>">
                    <?php if ($availability === "available"): ?>
                    <button type="submit" class="vacancy-btn">Mark as occupied</button>
                    <?php else: ?>
                    <button type="submit" class="vacancy-btn go">Mark as vacant (advertise again)</button>
                    <?php endif; ?>
                </form>
                <?php endif; ?>

            </div>
        </div>

        <?php endforeach; ?>

        <?php endif; ?>

    </div>
</body>

</html>