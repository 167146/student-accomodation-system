<?php
session_start();
require_once "../config/database.php";

// Protect the dashboard: admins only.
if (
    empty($_SESSION["email"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../public/admin_login.php");
    exit;
}

// Count properties awaiting review.
$stmt = $pdo->query(
    "SELECT COUNT(*) FROM properties
     WHERE availability_status = 'under_review'"
);
$pendingCount = (int)$stmt->fetchColumn();

// Count all property listings.
$stmt = $pdo->query("SELECT COUNT(*) FROM properties");
$totalCount = (int)$stmt->fetchColumn();

// Get the latest property listings.
$stmt = $pdo->query(
    "SELECT property_key, landlord_email, title,
            location_name, price, availability_status
     FROM properties
     ORDER BY created_at DESC
     LIMIT 10"
);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        min-width: 720px;
    }

    th,
    td {
        padding: 15px 18px;
        text-align: left;
        font-size: 14px;
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
                    <h3>Awaiting Review</h3>
                    <h2><?= $pendingCount ?></h2>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($properties as $property): ?>
                    <tr>
                        <td class="prop-title"><?= htmlspecialchars($property["title"]) ?></td>
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
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>

    </div>

</body>

</html>