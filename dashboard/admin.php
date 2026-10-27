<?php
session_start();
require_once "../config/database.php";

/*
 * Confirmed from your actual database (SHOW CREATE TABLE):
 *  - properties.property_key is the varchar(300) PRIMARY KEY (no
 *    auto-increment ID anywhere in this project).
 *  - properties already has verification_status, verification_notes,
 *    verified_at, plus location_distance_km, location_flag (added by
 *    the verification migration).
 *  - property_photos has property_key, photo_path, is_duplicate.
 */

// Protect the dashboard: admins only.
if (
    empty($_SESSION["email"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../public/admin_login.php");
    exit;
}

$actionMessage = "";

// Handle approve / reject actions from the table below.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $propertyKey = trim($_POST["property_key"] ?? "");
    $action = $_POST["action"] ?? "";
    $notes = trim($_POST["verification_notes"] ?? "");

    if ($propertyKey === "" || !in_array($action, ["approve", "reject"], true)) {

        $actionMessage = "Invalid verification request.";

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
                $actionMessage = "Property approved and published.";

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
                $actionMessage = "Property rejected.";
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $actionMessage = "Could not update this property. Please check your database structure.";
        }
    }
}

// Count properties awaiting verification.
$stmt = $pdo->query(
    "SELECT COUNT(*) FROM properties WHERE verification_status = 'pending'"
);
$pendingCount = (int)$stmt->fetchColumn();

// Count properties flagged for a location mismatch.
$stmt = $pdo->query(
    "SELECT COUNT(*) FROM properties
     WHERE verification_status = 'pending' AND location_flag = 1"
);
$flaggedCount = (int)$stmt->fetchColumn();

// Count all property listings.
$stmt = $pdo->query("SELECT COUNT(*) FROM properties");
$totalCount = (int)$stmt->fetchColumn();

// Get the latest property listings.
$stmt = $pdo->query(
    "SELECT property_key, landlord_email, title,
            location_name, price, availability_status,
            verification_status, location_flag, location_distance_km
     FROM properties
     ORDER BY created_at DESC
     LIMIT 10"
);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pull photos (and duplicate flags) for just these properties in one go.
$photosByProperty = [];
if (!empty($properties)) {
    $keys = array_column($properties, "property_key");
    $placeholders = implode(",", array_fill(0, count($keys), "?"));

    $photoStmt = $pdo->prepare(
        "SELECT property_key, photo_path, is_duplicate
         FROM property_photos
         WHERE property_key IN ($placeholders)
         ORDER BY property_key"
    );
    $photoStmt->execute($keys);

    foreach ($photoStmt->fetchAll(PDO::FETCH_ASSOC) as $photo) {
        $photosByProperty[$photo["property_key"]][] = $photo;
    }
}

// Display helpers (styling only).
$adminName = $_SESSION["name"] ?? "Admin";
$initial = strtoupper(substr($adminName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | UniMtaa</title>

    <style>
    :root {
        --burgundy: #800020;
        --burgundy-dark: #4A0E1F;
        --burgundy-deep: #2B0612;
        --burgundy-light: #F9EEF1;
        --burgundy-border: #E8C9D1;
        --text: #1F1A1C;
        --muted: #6B6266;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
        background: var(--burgundy-light);
        color: var(--text);
        min-height: 100vh;
    }

    /* ---------- TOP BAR ---------- */

    .topbar {
        background: linear-gradient(135deg, var(--burgundy) 0%, var(--burgundy-dark) 60%, var(--burgundy-deep) 100%);
        color: #fff;
        padding: 0 5%;
        height: 68px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 4px 18px rgba(43, 6, 18, 0.25);
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .brand-icon {
        width: 40px;
        height: 40px;
        border-radius: 11px;
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.25);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .brand-name {
        font-size: 19px;
        font-weight: 700;
        line-height: 1.1;
    }

    .brand-sub {
        font-size: 11px;
        opacity: 0.7;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .admin-chip {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
    }

    .avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #fff;
        color: var(--burgundy);
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .logout {
        color: #fff;
        text-decoration: none;
        font-size: 13.5px;
        font-weight: 600;
        padding: 9px 16px;
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 10px;
        transition: background 0.2s;
    }

    .logout:hover {
        background: rgba(255, 255, 255, 0.15);
    }

    /* ---------- CONTENT ---------- */

    .container {
        max-width: 1150px;
        margin: 0 auto;
        padding: 36px 5% 60px;
    }

    .welcome {
        margin-bottom: 28px;
    }

    .welcome h1 {
        font-size: 28px;
        color: var(--burgundy-dark);
        margin-bottom: 6px;
    }

    .welcome p {
        color: var(--muted);
        font-size: 15px;
    }

    .flash {
        background: #DCFCE7;
        color: #166534;
        border-radius: 12px;
        padding: 13px 18px;
        font-size: 14px;
        margin-bottom: 24px;
    }

    /* ---------- STAT CARDS ---------- */

    .stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 18px;
        margin-bottom: 36px;
    }

    .card {
        background: #fff;
        border: 1px solid var(--burgundy-border);
        border-radius: 18px;
        padding: 24px;
        display: flex;
        align-items: center;
        gap: 18px;
        box-shadow: 0 10px 28px rgba(128, 0, 32, 0.07);
        transition: transform 0.15s, box-shadow 0.15s;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 32px rgba(128, 0, 32, 0.12);
    }

    .card-icon {
        width: 54px;
        height: 54px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
    }

    .card-icon.burgundy {
        background: var(--burgundy-light);
        border: 1px solid var(--burgundy-border);
    }

    .card-icon.amber {
        background: #FEF3C7;
        border: 1px solid #FCE08A;
    }

    .card-icon.red {
        background: #FDECEC;
        border: 1px solid #F5C6C6;
    }

    .card h3 {
        font-size: 13px;
        font-weight: 600;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.6px;
        margin-bottom: 4px;
    }

    .card h2 {
        font-size: 34px;
        color: var(--burgundy);
        line-height: 1;
    }

    /* ---------- TABLE ---------- */

    .section-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        margin-bottom: 14px;
    }

    .section-head h2 {
        font-size: 20px;
        color: var(--burgundy-dark);
    }

    .section-head span {
        font-size: 13px;
        color: var(--muted);
    }

    .table-wrapper {
        background: #fff;
        border: 1px solid var(--burgundy-border);
        border-radius: 18px;
        overflow-x: auto;
        box-shadow: 0 10px 28px rgba(128, 0, 32, 0.07);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 820px;
    }

    th,
    td {
        padding: 15px 18px;
        text-align: left;
        font-size: 14px;
        vertical-align: top;
    }

    th {
        background: linear-gradient(135deg, var(--burgundy) 0%, var(--burgundy-dark) 100%);
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.7px;
        text-transform: uppercase;
    }

    td {
        border-bottom: 1px solid #F1E3E7;
    }

    tbody tr:last-child td {
        border-bottom: none;
    }

    tbody tr:hover {
        background: #FDF7F9;
    }

    .prop-title {
        font-weight: 600;
        color: var(--burgundy-dark);
    }

    .muted {
        color: var(--muted);
    }

    .price {
        font-weight: 600;
        white-space: nowrap;
    }

    /* ---------- STATUS BADGES ---------- */

    .status {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
        background: #EEE9EB;
        color: #4B4348;
    }

    .status-under_review {
        background: #FEF3C7;
        color: #92400E;
    }

    .status-available {
        background: #DCFCE7;
        color: #166534;
    }

    .status-booked,
    .status-unavailable,
    .status-rejected {
        background: #FDECEC;
        color: #8A1C1C;
    }

    .status-pending {
        background: #FEF3C7;
        color: #92400E;
    }

    .status-approved {
        background: #DCFCE7;
        color: #166534;
    }

    .flag-warning {
        display: inline-block;
        margin-top: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 600;
        background: #FDECEC;
        color: #8A1C1C;
    }

    /* ---------- PHOTOS ---------- */

    details.photo-details summary {
        cursor: pointer;
        font-size: 12.5px;
        color: var(--burgundy);
        font-weight: 600;
    }

    .photo-strip {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 10px;
    }

    .photo-thumb {
        position: relative;
        width: 72px;
        height: 60px;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid var(--burgundy-border);
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
        background: rgba(138, 28, 28, 0.9);
        color: #fff;
        font-size: 8.5px;
        font-weight: 700;
        text-align: center;
        padding: 2px 0;
    }

    /* ---------- VERIFY FORM ---------- */

    form.verify-form {
        margin-top: 10px;
    }

    .verify-form textarea {
        width: 100%;
        min-height: 44px;
        margin-top: 8px;
        padding: 8px 10px;
        font: inherit;
        font-size: 12.5px;
        border: 1.5px solid var(--burgundy-border);
        border-radius: 8px;
        resize: vertical;
    }

    .verify-actions {
        display: flex;
        gap: 8px;
        margin-top: 8px;
    }

    .btn-small {
        padding: 7px 14px;
        border: 0;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-approve {
        background: #166534;
        color: #fff;
    }

    .btn-reject {
        background: #FDECEC;
        color: #8A1C1C;
    }

    /* ---------- EMPTY STATE ---------- */

    .empty {
        background: #fff;
        border: 1px dashed var(--burgundy-border);
        border-radius: 18px;
        padding: 48px 20px;
        text-align: center;
        color: var(--muted);
    }

    .empty .big {
        font-size: 38px;
        margin-bottom: 10px;
    }

    /* ---------- RESPONSIVE ---------- */

    @media (max-width: 640px) {
        .admin-chip .name {
            display: none;
        }

        .welcome h1 {
            font-size: 23px;
        }
    }
    </style>
</head>

<body>

    <!-- TOP BAR -->
    <header class="topbar">

        <div class="brand">
            <div class="brand-icon">🏠</div>
            <div>
                <div class="brand-name">UniMtaa</div>
                <div class="brand-sub">Admin Portal</div>
            </div>
        </div>

        <div class="topbar-right">
            <div class="admin-chip">
                <div class="avatar"><?= htmlspecialchars($initial) ?></div>
                <span class="name"><?= htmlspecialchars($adminName) ?></span>
            </div>

            <a class="logout" href="../public/logout.php">Log Out</a>
        </div>

    </header>


    <div class="container">

        <!-- WELCOME -->
        <div class="welcome">
            <h1>Welcome, <?= htmlspecialchars($adminName) ?>!</h1>
            <p>You can monitor property listings and review submissions here.</p>
        </div>

        <?php if ($actionMessage !== ""): ?>
        <div class="flash"><?= htmlspecialchars($actionMessage) ?></div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats">

            <div class="card">
                <div class="card-icon burgundy">🏢</div>
                <div>
                    <h3>Total Properties</h3>
                    <h2><?= $totalCount ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon amber">⏳</div>
                <div>
                    <h3>Awaiting Verification</h3>
                    <h2><?= $pendingCount ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon red">⚠️</div>
                <div>
                    <h3>Flagged: Location Mismatch</h3>
                    <h2><?= $flaggedCount ?></h2>
                </div>
            </div>

        </div>


        <!-- RECENT LISTINGS -->
        <div class="section-head">
            <h2>Recent Property Listings</h2>
            <span>Latest 10 submissions</span>
        </div>

        <?php if (empty($properties)): ?>

        <div class="empty">
            <div class="big">📭</div>
            No property listings have been submitted yet.
        </div>

        <?php else: ?>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Property</th>
                        <th>Landlord Email</th>
                        <th>Location</th>
                        <th>Monthly Rent</th>
                        <th>Status</th>
                        <th>Verification</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($properties as $property):
                        $photos = $photosByProperty[$property["property_key"]] ?? [];
                        $duplicateCount = count(array_filter($photos, fn($p) => (int)$p["is_duplicate"] === 1));
                    ?>
                    <tr>
                        <td class="prop-title">
                            <?= htmlspecialchars($property["title"]) ?>

                            <?php if (!empty($photos)): ?>
                            <details class="photo-details">
                                <summary>
                                    <?= count($photos) ?> photo<?= count($photos) === 1 ? "" : "s" ?>
                                    <?= $duplicateCount > 0 ? " · ⚠ {$duplicateCount} possible duplicate" . ($duplicateCount === 1 ? "" : "s") : "" ?>
                                </summary>
                                <div class="photo-strip">
                                    <?php foreach ($photos as $photo): ?>
                                    <div class="photo-thumb">
                                        <img src="../properties/<?= htmlspecialchars($photo["photo_path"]) ?>" alt="">
                                        <?php if ((int)$photo["is_duplicate"] === 1): ?>
                                        <div class="dup-tag">Duplicate</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                            <?php endif; ?>
                        </td>
                        <td class="muted"><?= htmlspecialchars($property["landlord_email"]) ?></td>
                        <td><?= htmlspecialchars($property["location_name"]) ?></td>
                        <td class="price">KSh <?= number_format((float)$property["price"], 2) ?></td>
                        <td>
                            <span class="status status-<?= htmlspecialchars(
                                preg_replace("/[^a-z_]/", "", strtolower($property["availability_status"]))
                            ) ?>">
                                <?= htmlspecialchars(
                                    ucfirst(str_replace(
                                        "_", " ",
                                        $property["availability_status"]
                                    ))
                                ) ?>
                            </span>
                        </td>
                        <td style="min-width:220px;">
                            <span
                                class="status status-<?= htmlspecialchars(strtolower($property["verification_status"] ?? "pending")) ?>">
                                <?= htmlspecialchars(ucfirst($property["verification_status"] ?? "pending")) ?>
                            </span>

                            <?php if ((int)($property["location_flag"] ?? 0) === 1): ?>
                            <div class="flag-warning">
                                ⚠ Location mismatch
                                <?= $property["location_distance_km"] !== null
                                    ? " (" . htmlspecialchars((string)$property["location_distance_km"]) . " km)"
                                    : "" ?>
                            </div>
                            <?php endif; ?>

                            <?php if (($property["verification_status"] ?? "pending") === "pending"): ?>
                            <form method="POST" class="verify-form">
                                <input type="hidden" name="property_key"
                                    value="<?= htmlspecialchars($property["property_key"]) ?>">
                                <textarea name="verification_notes" placeholder="Notes (optional)"></textarea>
                                <div class="verify-actions">
                                    <button type="submit" name="action" value="approve"
                                        class="btn-small btn-approve">Approve</button>
                                    <button type="submit" name="action" value="reject"
                                        class="btn-small btn-reject">Reject</button>
                                </div>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>

    </div>

</body>

</html>