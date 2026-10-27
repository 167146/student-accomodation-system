<?php
session_start();

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/verification_helpers.php";

requireRole("admin");

$message = "";

/*
 * Handle approve / reject actions.
 * Properties are identified by their natural key, property_key —
 * no surrogate ID column is used anywhere in this project.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $propertyKey = trim($_POST["property_key"] ?? "");
    $action = $_POST["action"] ?? "";
    $notes = trim($_POST["verification_notes"] ?? "");

    if ($propertyKey === "" || !in_array($action, ["approve", "reject"], true)) {

        $message = "Invalid verification request.";

    } else {

        try {
            if ($action === "approve") {
                $stmt = $pdo->prepare(
                    "UPDATE properties
                     SET verification_status = 'approved',
                         verification_notes = ?,
                         verified_at = NOW(),
                         availability_status = 'available'
                     WHERE property_key = ?"
                );
                $stmt->execute([$notes, $propertyKey]);
                $message = "Property approved and published.";

            } else {
                $stmt = $pdo->prepare(
                    "UPDATE properties
                     SET verification_status = 'rejected',
                         verification_notes = ?,
                         verified_at = NOW(),
                         availability_status = 'unavailable'
                     WHERE property_key = ?"
                );
                $stmt->execute([$notes, $propertyKey]);
                $message = "Property rejected.";
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = "Could not update this property. Please check your database structure.";
        }
    }
}

/*
 * Load every property still pending verification, along with its
 * photos, so the admin can review everything in one place.
 */
$pendingProperties = [];

try {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM properties
         WHERE verification_status = 'pending'
         ORDER BY created_at ASC"
    );
    $stmt->execute();
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $photoStmt = $pdo->prepare(
        "SELECT photo_path, photo_hash, is_duplicate
         FROM property_photos
         WHERE property_key = ?
         ORDER BY uploaded_at ASC"
    );

    foreach ($properties as $property) {
        $photoStmt->execute([$property["property_key"]]);
        $property["photos"] = $photoStmt->fetchAll(PDO::FETCH_ASSOC);
        $pendingProperties[] = $property;
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    $message = "Could not load pending properties. Please check your database structure.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Queue | CampusNest</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap"
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
        padding: 32px 24px 60px;
    }

    h1,
    h2,
    h3,
    strong {
        font-family: 'Poppins', sans-serif;
    }

    .page-head {
        max-width: 960px;
        margin: 0 auto 24px;
    }

    .page-head h1 {
        font-size: 24px;
        margin: 0 0 4px;
    }

    .page-head p {
        color: var(--ink-soft);
        font-size: 14px;
        margin: 0;
    }

    .page-head a {
        color: var(--violet);
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
    }

    .flash {
        max-width: 960px;
        margin: 0 auto 20px;
        background: var(--green-soft);
        color: #0B6B4C;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 13.5px;
    }

    .queue {
        max-width: 960px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 22px;
    }

    .empty-state {
        background: var(--card);
        border: 1px solid var(--hairline);
        border-radius: 16px;
        padding: 40px;
        text-align: center;
        color: var(--ink-soft);
    }

    .property-card {
        background: var(--card);
        border: 1px solid var(--hairline);
        border-radius: 18px;
        padding: 24px 26px;
        box-shadow: 0 10px 24px rgba(76, 29, 149, .05);
    }

    .property-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 14px;
    }

    .property-head h2 {
        font-size: 18px;
        margin: 0 0 4px;
        color: var(--violet-dark);
    }

    .property-head .meta {
        color: var(--ink-soft);
        font-size: 13px;
    }

    .badge {
        display: inline-block;
        padding: 5px 11px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }

    .badge-flag {
        background: var(--red-soft);
        color: var(--red);
    }

    .badge-ok {
        background: var(--green-soft);
        color: #0B6B4C;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px 24px;
        font-size: 13.5px;
        margin-bottom: 16px;
    }

    .detail-grid div strong {
        display: block;
        color: var(--violet-dark);
        font-size: 12px;
        margin-bottom: 2px;
    }

    .detail-grid div p {
        margin: 0;
        color: var(--ink-soft);
    }

    .location-flag-note {
        background: var(--red-soft);
        color: var(--red);
        padding: 10px 14px;
        border-radius: 10px;
        font-size: 13px;
        margin-bottom: 16px;
    }

    .photo-strip {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .photo-thumb {
        position: relative;
        width: 110px;
        height: 90px;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid var(--hairline);
    }

    .photo-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .photo-thumb .dup-tag {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(179, 55, 44, .9);
        color: #fff;
        font-size: 10.5px;
        font-weight: 700;
        text-align: center;
        padding: 3px 0;
    }

    form.verify-form {
        border-top: 1px solid var(--hairline);
        padding-top: 16px;
    }

    textarea.notes {
        width: 100%;
        min-height: 60px;
        padding: 10px 12px;
        border: 1.5px solid var(--hairline);
        border-radius: 10px;
        font: inherit;
        font-size: 13.5px;
        resize: vertical;
        margin-bottom: 12px;
    }

    .action-row {
        display: flex;
        gap: 10px;
    }

    button {
        padding: 10px 18px;
        border-radius: 9px;
        border: none;
        font-weight: 600;
        font-size: 13.5px;
        cursor: pointer;
    }

    .btn-approve {
        background: linear-gradient(135deg, var(--green), #0B6B4C);
        color: #fff;
    }

    .btn-reject {
        background: var(--red-soft);
        color: var(--red);
    }
    </style>
</head>

<body>

    <div class="page-head">
        <a href="../dashboard/admin.php">&larr; Back to dashboard</a>
        <h1>Verification queue</h1>
        <p>Every property below is waiting for admin review before it appears to students.</p>
    </div>

    <?php if ($message !== ""): ?>
    <div class="flash"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></div>
    <?php endif; ?>

    <div class="queue">

        <?php if (empty($pendingProperties)): ?>
        <div class="empty-state">Nothing pending right now — new submissions will show up here.</div>
        <?php endif; ?>

        <?php foreach ($pendingProperties as $property): ?>
        <div class="property-card">

            <div class="property-head">
                <div>
                    <h2><?= htmlspecialchars($property["title"] ?? "", ENT_QUOTES, "UTF-8") ?></h2>
                    <div class="meta">
                        Submitted by <?= htmlspecialchars($property["landlord_email"] ?? "", ENT_QUOTES, "UTF-8") ?>
                    </div>
                </div>

                <?php if ((int) ($property["location_flag"] ?? 0) === 1): ?>
                <span class="badge badge-flag">⚠ Location mismatch</span>
                <?php else: ?>
                <span class="badge badge-ok">Location OK</span>
                <?php endif; ?>
            </div>

            <div class="detail-grid">
                <div>
                    <strong>Address</strong>
                    <p><?= htmlspecialchars($property["address"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
                </div>
                <div>
                    <strong>Area</strong>
                    <p><?= htmlspecialchars($property["location_name"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
                </div>
                <div>
                    <strong>Monthly rent</strong>
                    <p>KSh <?= htmlspecialchars((string) ($property["price"] ?? ""), ENT_QUOTES, "UTF-8") ?></p>
                </div>
                <div>
                    <strong>Distance (submitted GPS vs. address)</strong>
                    <p>
                        <?= $property["location_distance_km"] !== null
                            ? htmlspecialchars((string) $property["location_distance_km"], ENT_QUOTES, "UTF-8") . " km"
                            : "Not available" ?>
                    </p>
                </div>
            </div>

            <?php if ((int) ($property["location_flag"] ?? 0) === 1): ?>
            <div class="location-flag-note">
                The landlord's device location at submission did not closely match this address's geocoded location (or
                location data wasn't available). This doesn't necessarily mean the listing is fake — double-check the
                address spelling and ask the landlord if needed.
            </div>
            <?php endif; ?>

            <div class="photo-strip">
                <?php foreach ($property["photos"] as $photo): ?>
                <div class="photo-thumb">
                    <img src="../properties/<?= htmlspecialchars($photo["photo_path"], ENT_QUOTES, "UTF-8") ?>"
                        alt="Property photo">
                    <?php if ((int) $photo["is_duplicate"] === 1): ?>
                    <div class="dup-tag">Possible duplicate</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <?php if (empty($property["photos"])): ?>
                <p style="color:var(--ink-soft); font-size:13px;">No photos were uploaded.</p>
                <?php endif; ?>
            </div>

            <form method="POST" class="verify-form">
                <input type="hidden" name="property_key"
                    value="<?= htmlspecialchars($property["property_key"], ENT_QUOTES, "UTF-8") ?>">

                <textarea name="verification_notes" class="notes"
                    placeholder="Notes for this decision (optional, visible to you only)"></textarea>

                <div class="action-row">
                    <button type="submit" name="action" value="approve" class="btn-approve">Approve &amp;
                        publish</button>
                    <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
                </div>
            </form>

        </div>
        <?php endforeach; ?>

    </div>

</body>

</html>