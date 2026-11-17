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
 *  - users is keyed by email (name, phone, role, ...).
 */

// Protect the dashboard: admins only.
if (
    empty($_SESSION["email"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../public/admin_login.php");
    exit;
}

// Simple CSRF token for every form on this page.
if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION["csrf"];

// Which tab is open: the listings (default) or user management.
$view = ($_GET["view"] ?? "") === "users" ? "users" : "listings";

$actionMessage = "";
$actionIsError = false;

// Handle approve / reject / delete-user actions.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if (!hash_equals($csrf, $_POST["csrf"] ?? "")) {

        $actionMessage = "Your session expired. Please try again.";
        $actionIsError = true;

    } elseif (in_array($action, ["approve", "reject"], true)) {

        $propertyKey = trim($_POST["property_key"] ?? "");
        $notes = trim($_POST["verification_notes"] ?? "");

        if ($propertyKey === "") {

            $actionMessage = "Invalid verification request.";
            $actionIsError = true;

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
                $actionIsError = true;
            }
        }

    } elseif ($action === "delete_user") {

        $view = "users";
        $targetEmail = trim($_POST["user_email"] ?? "");

        try {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE email = ?");
            $stmt->execute([$targetEmail]);
            $targetRole = $stmt->fetchColumn();

            if ($targetRole === false) {

                $actionMessage = "That user no longer exists.";
                $actionIsError = true;

            } elseif ($targetRole === "admin" || $targetEmail === $_SESSION["email"]) {

                $actionMessage = "Admin accounts cannot be deleted from here.";
                $actionIsError = true;

            } else {

                // Protect the data: a user with listings, bookings or
                // reviews is kept so the records stay intact.
                $linked = 0;
                foreach ([
                    "SELECT COUNT(*) FROM properties WHERE landlord_email = ?",
                    "SELECT COUNT(*) FROM bookings WHERE student_email = ?",
                    "SELECT COUNT(*) FROM reviews WHERE student_email = ?",
                ] as $sql) {
                    $check = $pdo->prepare($sql);
                    $check->execute([$targetEmail]);
                    $linked += (int)$check->fetchColumn();
                }

                if ($linked > 0) {
                    $actionMessage = "This user has $linked linked record(s) (listings, bookings or reviews), so the account was not deleted.";
                    $actionIsError = true;
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
                    $stmt->execute([$targetEmail]);
                    $actionMessage = "User account deleted.";
                }
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $actionMessage = "Could not delete this user.";
            $actionIsError = true;
        }

    } else {

        $actionMessage = "Invalid request.";
        $actionIsError = true;
    }
}

/* ---------- LISTINGS TAB DATA ---------- */

$pendingCount = $flaggedCount = $totalCount = $approvedCount = 0;
$properties = [];
$photosByProperty = [];

if ($view === "listings") {

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

    // Count approved (live) listings.
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM properties WHERE verification_status = 'approved'"
    );
    $approvedCount = (int)$stmt->fetchColumn();

    // Get the latest property listings.
    $stmt = $pdo->query(
        "SELECT property_key, landlord_email, title,
                location_name, price, availability_status,
                verification_status, location_flag, location_distance_km,
                submission_latitude
         FROM properties
         ORDER BY created_at DESC
         LIMIT 10"
    );
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pull photos (and duplicate flags) for just these properties in one go.
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
}

/* ---------- USERS TAB DATA ---------- */

$users = [];
$userTotal = $studentCount = $landlordCount = 0;
$search = trim($_GET["q"] ?? "");
$roleFilter = $_GET["role"] ?? "";
if (!in_array($roleFilter, ["student", "landlord", "admin"], true)) {
    $roleFilter = "";
}

if ($view === "users") {

    $userTotal = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $studentCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $landlordCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'landlord'")->fetchColumn();

    $where = [];
    $params = [];

    if ($search !== "") {
        $where[] = "(name LIKE ? OR email LIKE ?)";
        $like = "%" . $search . "%";
        $params[] = $like;
        $params[] = $like;
    }

    if ($roleFilter !== "") {
        $where[] = "role = ?";
        $params[] = $roleFilter;
    }

    $whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

    // Newest first when the table has created_at; fall back if it doesn't.
    try {
        $stmt = $pdo->prepare(
            "SELECT email, name, phone, role, created_at
             FROM users" . $whereSql . " ORDER BY created_at DESC LIMIT 200"
        );
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare(
            "SELECT email, name, role FROM users" . $whereSql . " ORDER BY name LIMIT 200"
        );
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">

    <style>
    :root {
        --burgundy: #800020;
        --burgundy-dark: #4A0E1F;
        --burgundy-deep: #2B0612;
        --rose: #C45A6A;
        --paper: #FBF6F7;
        --card: #FFFFFF;
        --border: #EBD9DE;
        --text: #2A1418;
        --muted: #6E5358;
        --green: #166534;
        --green-soft: #DCFCE7;
        --amber: #92400E;
        --amber-soft: #FEF3C7;
        --red: #8A1C1C;
        --red-soft: #FDECEC;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: 'Inter', "Segoe UI", system-ui, sans-serif;
        background: var(--paper);
        color: var(--text);
        min-height: 100vh;
    }

    h1,
    h2,
    h3 {
        font-family: 'Poppins', sans-serif;
    }

    a {
        text-decoration: none;
    }

    /* ---------- TOP BAR ---------- */

    .topbar {
        position: sticky;
        top: 0;
        z-index: 20;
        background: rgba(43, 6, 18, .94);
        backdrop-filter: blur(8px);
        color: #fff;
        padding: 0 5%;
        height: 68px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        box-shadow: 0 4px 18px rgba(43, 6, 18, .3);
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .brand-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--rose), var(--burgundy));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .brand-name {
        font-family: 'Poppins', sans-serif;
        font-size: 19px;
        font-weight: 700;
        line-height: 1.1;
    }

    .brand-sub {
        font-size: 10.5px;
        opacity: .7;
        letter-spacing: 1.2px;
        text-transform: uppercase;
    }

    .nav {
        display: flex;
        gap: 6px;
    }

    .nav a {
        color: rgba(255, 255, 255, .8);
        font-size: 14px;
        font-weight: 500;
        padding: 9px 16px;
        border-radius: 10px;
        transition: background .2s, color .2s;
    }

    .nav a:hover {
        background: rgba(255, 255, 255, .1);
        color: #fff;
    }

    .nav a.active {
        background: rgba(255, 255, 255, .16);
        color: #fff;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 14px;
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
        font-size: 13.5px;
        font-weight: 600;
        padding: 9px 16px;
        border: 1px solid rgba(255, 255, 255, .35);
        border-radius: 10px;
        transition: background .2s;
    }

    .logout:hover {
        background: rgba(255, 255, 255, .14);
    }

    /* ---------- HERO ---------- */

    .hero {
        background:
            radial-gradient(circle at 88% 0%, rgba(196, 90, 106, .45), transparent 55%),
            linear-gradient(120deg, var(--burgundy-deep), var(--burgundy-dark) 55%, var(--burgundy));
        color: #fff;
        padding: 46px 5% 96px;
    }

    .hero-inner {
        max-width: 1150px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        flex-wrap: wrap;
    }

    .hero h1 {
        font-size: clamp(26px, 4vw, 34px);
        margin-bottom: 8px;
    }

    .hero p {
        color: rgba(255, 255, 255, .8);
        font-size: 15px;
        max-width: 520px;
    }

    .hero-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .hero-button {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: #fff;
        color: var(--burgundy);
        font-weight: 600;
        font-size: 15px;
        padding: 14px 24px;
        border-radius: 14px;
        box-shadow: 0 12px 26px rgba(0, 0, 0, .25);
        transition: transform .15s, box-shadow .15s;
    }

    .hero-button.ghost {
        background: rgba(255, 255, 255, .12);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, .35);
        box-shadow: none;
    }

    .hero-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 32px rgba(0, 0, 0, .3);
    }

    /* ---------- CONTENT ---------- */

    .container {
        max-width: 1150px;
        margin: -58px auto 0;
        padding: 0 5% 70px;
    }

    .flash {
        background: var(--green-soft);
        color: var(--green);
        border-radius: 12px;
        padding: 13px 18px;
        font-size: 14px;
        margin-bottom: 20px;
        box-shadow: 0 8px 20px rgba(128, 0, 32, .08);
    }

    .flash.error {
        background: var(--red-soft);
        color: var(--red);
    }

    /* ---------- STAT CARDS ---------- */

    .stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 18px;
        margin-bottom: 40px;
    }

    .card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 22px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 12px 28px rgba(128, 0, 32, .09);
        transition: transform .15s, box-shadow .15s;
    }

    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 16px 34px rgba(128, 0, 32, .14);
    }

    .card-icon {
        width: 54px;
        height: 54px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
    }

    .card-icon.burgundy {
        background: #F9EEF1;
    }

    .card-icon.amber {
        background: var(--amber-soft);
    }

    .card-icon.red {
        background: var(--red-soft);
    }

    .card-icon.green {
        background: var(--green-soft);
    }

    .card h3 {
        font-family: 'Inter', sans-serif;
        font-size: 12px;
        font-weight: 600;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: .6px;
        margin-bottom: 4px;
    }

    .card h2 {
        font-size: 32px;
        color: var(--burgundy);
        line-height: 1;
    }

    /* ---------- SECTION HEAD ---------- */

    .section-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        margin-bottom: 16px;
    }

    .section-head h2 {
        font-size: 21px;
        color: var(--burgundy-dark);
    }

    .section-head span {
        font-size: 13px;
        color: var(--muted);
    }

    /* ---------- LISTING CARDS ---------- */

    .listing {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 20px;
        margin-bottom: 18px;
        display: grid;
        grid-template-columns: 230px 1fr 300px;
        overflow: hidden;
        box-shadow: 0 10px 26px rgba(128, 0, 32, .07);
        transition: box-shadow .15s;
    }

    .listing:hover {
        box-shadow: 0 16px 34px rgba(128, 0, 32, .12);
    }

    .cover {
        position: relative;
        background: linear-gradient(135deg, #F9EEF1, #EBD9DE);
        display: grid;
        place-items: center;
        font-size: 38px;
        min-height: 190px;
    }

    .cover img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .cover .count {
        position: absolute;
        left: 12px;
        bottom: 12px;
        background: rgba(43, 6, 18, .78);
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
    }

    .info {
        padding: 20px 22px;
        min-width: 0;
    }

    .info h3 {
        font-size: 18px;
        color: var(--burgundy-dark);
        margin-bottom: 4px;
    }

    .meta {
        color: var(--muted);
        font-size: 13.5px;
        line-height: 1.6;
    }

    .price {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 17px;
        margin: 10px 0 12px;
    }

    .price small {
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        font-size: 12px;
        color: var(--muted);
    }

    .badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

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

    .status-under_review,
    .status-pending {
        background: var(--amber-soft);
        color: var(--amber);
    }

    .status-available,
    .status-approved {
        background: var(--green-soft);
        color: var(--green);
    }

    .status-booked,
    .status-unavailable,
    .status-rejected {
        background: var(--red-soft);
        color: var(--red);
    }

    .flag-warning {
        display: inline-block;
        margin-top: 12px;
        padding: 6px 12px;
        border-radius: 10px;
        font-size: 12.5px;
        font-weight: 600;
        background: var(--red-soft);
        color: var(--red);
    }

    /* ---------- PHOTOS ---------- */

    details.photo-details {
        margin-top: 14px;
    }

    details.photo-details summary {
        cursor: pointer;
        font-size: 13px;
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
        width: 80px;
        height: 64px;
        border-radius: 9px;
        overflow: hidden;
        border: 1px solid var(--border);
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
        background: rgba(138, 28, 28, .92);
        color: #fff;
        font-size: 8.5px;
        font-weight: 700;
        text-align: center;
        padding: 2px 0;
    }

    /* ---------- DECISION PANEL ---------- */

    .decision {
        background: #FDF9FA;
        border-left: 1px solid var(--border);
        padding: 20px 22px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .decision .label {
        font-size: 12px;
        font-weight: 600;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: .6px;
        margin-bottom: 8px;
    }

    .decision textarea {
        width: 100%;
        min-height: 70px;
        padding: 10px 12px;
        font: inherit;
        font-size: 13px;
        border: 1.5px solid var(--border);
        border-radius: 10px;
        resize: vertical;
        background: #fff;
    }

    .decision textarea:focus {
        outline: none;
        border-color: var(--rose);
    }

    .verify-actions {
        display: flex;
        gap: 8px;
        margin-top: 10px;
    }

    .btn-small {
        flex: 1;
        padding: 10px 14px;
        border: 0;
        border-radius: 10px;
        font: inherit;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: transform .12s;
    }

    .btn-small:hover {
        transform: translateY(-1px);
    }

    .btn-approve {
        background: linear-gradient(135deg, #16A34A, var(--green));
        color: #fff;
    }

    .btn-reject {
        background: var(--red-soft);
        color: var(--red);
    }

    .reviewed {
        font-size: 13.5px;
        color: var(--muted);
        line-height: 1.5;
    }

    /* ---------- USER MANAGEMENT ---------- */

    .filter-bar {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 10px;
        margin-bottom: 18px;
        box-shadow: 0 8px 22px rgba(128, 0, 32, .06);
    }

    .filter-bar input,
    .filter-bar select {
        font: inherit;
        font-size: 14px;
        padding: 10px 12px;
        border: 1.5px solid var(--border);
        border-radius: 10px;
        background: #fff;
        color: var(--text);
    }

    .filter-bar input {
        flex: 1;
        min-width: 200px;
    }

    .filter-bar input:focus,
    .filter-bar select:focus {
        outline: none;
        border-color: var(--rose);
    }

    .filter-bar button {
        border: 0;
        cursor: pointer;
        font: inherit;
        font-weight: 600;
        font-size: 14px;
        color: #fff;
        padding: 10px 22px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--burgundy), var(--burgundy-dark));
    }

    .table-wrap {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 18px;
        overflow-x: auto;
        box-shadow: 0 10px 26px rgba(128, 0, 32, .07);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 720px;
    }

    th,
    td {
        padding: 14px 18px;
        text-align: left;
        font-size: 14px;
        vertical-align: middle;
    }

    th {
        background: linear-gradient(135deg, var(--burgundy), var(--burgundy-dark));
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: .7px;
        text-transform: uppercase;
    }

    td {
        border-bottom: 1px solid #F3E6E9;
    }

    tbody tr:last-child td {
        border-bottom: none;
    }

    tbody tr:hover {
        background: #FDF7F9;
    }

    .user-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--rose), var(--burgundy));
        color: #fff;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .user-name {
        font-weight: 600;
        color: var(--burgundy-dark);
    }

    .muted {
        color: var(--muted);
        font-size: 13px;
    }

    .role {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
    }

    .role-student {
        background: #F9EEF1;
        color: var(--burgundy);
    }

    .role-landlord {
        background: var(--amber-soft);
        color: var(--amber);
    }

    .role-admin {
        background: var(--burgundy-dark);
        color: #fff;
    }

    .btn-delete {
        border: 0;
        cursor: pointer;
        font: inherit;
        font-size: 12.5px;
        font-weight: 600;
        padding: 8px 14px;
        border-radius: 9px;
        background: var(--red-soft);
        color: var(--red);
        transition: background .15s;
    }

    .btn-delete:hover {
        background: #F9D2D2;
    }

    .protected {
        font-size: 12.5px;
        color: var(--muted);
    }

    /* ---------- EMPTY STATE ---------- */

    .empty {
        background: var(--card);
        border: 1px dashed var(--border);
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

    @media (max-width: 980px) {
        .listing {
            grid-template-columns: 1fr;
        }

        .cover {
            min-height: 200px;
        }

        .decision {
            border-left: none;
            border-top: 1px solid var(--border);
        }
    }

    @media (max-width: 760px) {
        .nav {
            display: none;
        }

        .admin-chip .name {
            display: none;
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

        <nav class="nav">
            <a class="<?= $view === "listings" ? "active" : "" ?>" href="admin.php">Dashboard</a>
            <a class="<?= $view === "users" ? "active" : "" ?>" href="admin.php?view=users">Manage Users</a>
            <a href="bi.php">Business Intelligence</a>
        </nav>

        <div class="topbar-right">
            <div class="admin-chip">
                <div class="avatar"><?= htmlspecialchars($initial) ?></div>
                <span class="name"><?= htmlspecialchars($adminName) ?></span>
            </div>

            <a class="logout" href="../public/logout.php">Log Out</a>
        </div>

    </header>

    <!-- HERO -->
    <section class="hero">
        <div class="hero-inner">
            <div>
                <?php if ($view === "users"): ?>
                <h1>Manage Users</h1>
                <p>See every student, landlord and admin account on the platform, and remove accounts that are no longer
                    needed.</p>
                <?php else: ?>
                <h1>Welcome, <?= htmlspecialchars($adminName) ?>!</h1>
                <p>Review new submissions, check the verification flags and see how the platform is performing.</p>
                <?php endif; ?>
            </div>

            <div class="hero-actions">
                <?php if ($view === "users"): ?>
                <a class="hero-button ghost" href="admin.php">&larr; Listings</a>
                <?php else: ?>
                <a class="hero-button ghost" href="admin.php?view=users">👥 Manage Users</a>
                <?php endif; ?>
                <a class="hero-button" href="bi.php">📊 Business Intelligence</a>
            </div>
        </div>
    </section>

    <div class="container">

        <?php if ($actionMessage !== ""): ?>
        <div class="flash <?= $actionIsError ? "error" : "" ?>"><?= htmlspecialchars($actionMessage) ?></div>
        <?php endif; ?>

        <?php if ($view === "users"): ?>

        <!-- ================= USERS TAB ================= -->

        <div class="stats">

            <div class="card">
                <div class="card-icon burgundy">👥</div>
                <div>
                    <h3>Total Users</h3>
                    <h2><?= $userTotal ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon green">🎓</div>
                <div>
                    <h3>Students</h3>
                    <h2><?= $studentCount ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon amber">🏢</div>
                <div>
                    <h3>Landlords</h3>
                    <h2><?= $landlordCount ?></h2>
                </div>
            </div>

        </div>

        <div class="section-head">
            <h2>All Users</h2>
            <span><?= count($users) ?> shown</span>
        </div>

        <form method="GET" class="filter-bar">
            <input type="hidden" name="view" value="users">
            <input type="text" name="q" placeholder="Search by name or email" value="<?= htmlspecialchars($search) ?>">
            <select name="role">
                <option value="">All roles</option>
                <option value="student" <?= $roleFilter === "student" ? "selected" : "" ?>>Students</option>
                <option value="landlord" <?= $roleFilter === "landlord" ? "selected" : "" ?>>Landlords</option>
                <option value="admin" <?= $roleFilter === "admin" ? "selected" : "" ?>>Admins</option>
            </select>
            <button type="submit">Filter</button>
        </form>

        <?php if (empty($users)): ?>

        <div class="empty">
            <div class="big">🔍</div>
            No users match your search.
        </div>

        <?php else: ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $userRole = preg_replace("/[^a-z]/", "", strtolower($user["role"] ?? ""));
                        $canDelete = $userRole !== "admin" && $user["email"] !== $_SESSION["email"];
                    ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar">
                                    <?= htmlspecialchars(strtoupper(substr($user["name"] ?: $user["email"], 0, 1))) ?>
                                </div>
                                <div>
                                    <div class="user-name"><?= htmlspecialchars($user["name"] ?? "") ?></div>
                                    <div class="muted"><?= htmlspecialchars($user["email"]) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="muted"><?= htmlspecialchars($user["phone"] ?? "—") ?></td>
                        <td><span
                                class="role role-<?= htmlspecialchars($userRole) ?>"><?= htmlspecialchars(ucfirst($userRole)) ?></span>
                        </td>
                        <td class="muted">
                            <?= !empty($user["created_at"]) ? htmlspecialchars(date("j M Y", strtotime($user["created_at"]))) : "—" ?>
                        </td>
                        <td>
                            <?php if ($canDelete): ?>
                            <form method="POST" action="admin.php?view=users"
                                onsubmit="return confirm('Delete this account? This cannot be undone.');">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_email" value="<?= htmlspecialchars($user["email"]) ?>">
                                <button type="submit" class="btn-delete">Delete</button>
                            </form>
                            <?php else: ?>
                            <span class="protected">Protected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>

        <?php else: ?>

        <!-- ================= LISTINGS TAB ================= -->

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

            <div class="card">
                <div class="card-icon green">✅</div>
                <div>
                    <h3>Approved &amp; Live</h3>
                    <h2><?= $approvedCount ?></h2>
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

        <?php foreach ($properties as $property):
            $photos = $photosByProperty[$property["property_key"]] ?? [];
            $duplicateCount = count(array_filter($photos, fn($p) => (int)$p["is_duplicate"] === 1));
            $verification = $property["verification_status"] ?? "pending";
        ?>
        <article class="listing">

            <!-- Cover photo -->
            <div class="cover">
                <?php if (!empty($photos)): ?>
                <img src="../properties/<?= htmlspecialchars($photos[0]["photo_path"]) ?>" alt="">
                <span class="count"><?= count($photos) ?> photo<?= count($photos) === 1 ? "" : "s" ?></span>
                <?php else: ?>
                🏠
                <?php endif; ?>
            </div>

            <!-- Details -->
            <div class="info">
                <h3><?= htmlspecialchars($property["title"]) ?></h3>
                <div class="meta">
                    📍 <?= htmlspecialchars($property["location_name"]) ?><br>
                    ✉ <?= htmlspecialchars($property["landlord_email"]) ?>
                </div>

                <div class="price">
                    KSh <?= number_format((float)$property["price"], 2) ?>
                    <small>/ month</small>
                </div>

                <div class="badges">
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

                    <span class="status status-<?= htmlspecialchars(strtolower($verification)) ?>">
                        <?= htmlspecialchars(ucfirst($verification)) ?>
                    </span>
                </div>

                <?php if ((int)($property["location_flag"] ?? 0) === 1): ?>
                <div class="flag-warning">
                    <?php if ($property["location_distance_km"] !== null): ?>
                    ⚠ Location mismatch (<?= htmlspecialchars((string)$property["location_distance_km"]) ?>
                    km apart)
                    <?php elseif ($property["submission_latitude"] === null): ?>
                    ⚠ Not checked: landlord's location wasn't shared
                    <?php else: ?>
                    ⚠ Not checked: address wasn't found on the map
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($photos)): ?>
                <details class="photo-details">
                    <summary>
                        View all <?= count($photos) ?> photo<?= count($photos) === 1 ? "" : "s" ?>
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
            </div>

            <!-- Decision -->
            <div class="decision">
                <?php if ($verification === "pending"): ?>
                <form method="POST" class="verify-form">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="property_key" value="<?= htmlspecialchars($property["property_key"]) ?>">
                    <div class="label">Your decision</div>
                    <textarea name="verification_notes" placeholder="Notes for the landlord (optional)"></textarea>
                    <div class="verify-actions">
                        <button type="submit" name="action" value="approve"
                            class="btn-small btn-approve">Approve</button>
                        <button type="submit" name="action" value="reject" class="btn-small btn-reject">Reject</button>
                    </div>
                </form>
                <?php elseif ($verification === "approved"): ?>
                <div class="label">Decision</div>
                <div class="reviewed">✅ Approved. This listing is visible to students.</div>
                <?php else: ?>
                <div class="label">Decision</div>
                <div class="reviewed">⛔ Rejected. The landlord can see your note.</div>
                <?php endif; ?>
            </div>

        </article>
        <?php endforeach; ?>

        <?php endif; ?>

        <?php endif; ?>

    </div>

</body>

</html>