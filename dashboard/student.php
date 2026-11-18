<?php
session_start();
require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| UniMtaa STUDENT DASHBOARD
|--------------------------------------------------------------------------
| Uses existing database fields only. No ID columns are added.
*/

if (
    !isset($_SESSION["user_email"]) ||
    ($_SESSION["role"] ?? "") !== "student"
) {
    header("Location: ../public/login.php");
    exit;
}

$studentEmail = $_SESSION["user_email"];
$studentName = $_SESSION["name"] ?? "Student";
$error = "";
$success = "";

if (empty($_SESSION["unimtaa_csrf"])) {
    $_SESSION["unimtaa_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["unimtaa_csrf"];

function h($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function redirectDashboard($section = "discover") {
    header("Location: student.php?section=" . urlencode($section));
    exit;
}

/*
|--------------------------------------------------------------------------
| HANDLE FORM ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submittedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $submittedToken)) {
        $error = "Your session has expired. Refresh the page and try again.";
    } else {
        $action = $_POST["action"] ?? "";

        try {
            /*
            | Request a booking
            */
            if ($action === "book") {
                $propertyKey = trim($_POST["property_key"] ?? "");

                if ($propertyKey === "") {
                    throw new Exception("Invalid property selection.");
                }

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "SELECT property_key
                     FROM properties
                     WHERE property_key = ?
                       AND availability_status = 'available'
                     FOR UPDATE"
                );
                $stmt->execute([$propertyKey]);

                if (!$stmt->fetch()) {
                    throw new Exception(
                        "This property is no longer available for booking."
                    );
                }

                $stmt = $pdo->prepare(
                    "SELECT status
                     FROM bookings
                     WHERE property_key = ?
                       AND student_email = ?
                     LIMIT 1"
                );
                $stmt->execute([$propertyKey, $studentEmail]);
                $existingBooking = $stmt->fetch();

                if ($existingBooking) {
                    if (in_array(
                        $existingBooking["status"],
                        ["pending", "accepted"],
                        true
                    )) {
                        throw new Exception(
                            "You already have an active booking request for this property."
                        );
                    }

                    if ($existingBooking["status"] === "completed") {
                        throw new Exception(
                            "You have already completed a booking for this property."
                        );
                    }

                    $stmt = $pdo->prepare(
                        "UPDATE bookings
                         SET status = 'pending',
                             booking_date = CURRENT_TIMESTAMP
                         WHERE property_key = ?
                           AND student_email = ?"
                    );
                    $stmt->execute([$propertyKey, $studentEmail]);
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO bookings
                         (property_key, student_email, status)
                         VALUES (?, ?, 'pending')"
                    );
                    $stmt->execute([$propertyKey, $studentEmail]);
                }

                $pdo->commit();
                redirectDashboard("bookings");
            }

            /*
            | Cancel a pending booking request
            */
            elseif ($action === "cancel_booking") {
                $propertyKey = trim($_POST["property_key"] ?? "");

                $stmt = $pdo->prepare(
                    "UPDATE bookings
                     SET status = 'cancelled'
                     WHERE property_key = ?
                       AND student_email = ?
                       AND status = 'pending'"
                );
                $stmt->execute([$propertyKey, $studentEmail]);

                if ($stmt->rowCount() > 0) {
                    redirectDashboard("bookings");
                }

                throw new Exception(
                    "Only pending booking requests can be cancelled here."
                );
            }

            /*
            | Submit a review after a completed booking
            */
            elseif ($action === "review") {
                $propertyKey = trim($_POST["property_key"] ?? "");
                $rating = filter_var(
                    $_POST["rating"] ?? null,
                    FILTER_VALIDATE_INT
                );
                $comment = trim($_POST["comment"] ?? "");

                if (
                    $propertyKey === "" ||
                    $rating === false ||
                    $rating === null ||
                    $rating < 1 ||
                    $rating > 5
                ) {
                    throw new Exception(
                        "Please select a rating from 1 to 5."
                    );
                }

                $stmt = $pdo->prepare(
                    "SELECT status
                     FROM bookings
                     WHERE property_key = ?
                       AND student_email = ?
                     LIMIT 1"
                );
                $stmt->execute([$propertyKey, $studentEmail]);
                $booking = $stmt->fetch();

                if (!$booking || $booking["status"] !== "completed") {
                    throw new Exception(
                        "You can review a property after your booking is marked completed."
                    );
                }

                $stmt = $pdo->prepare(
                    "SELECT property_key
                     FROM reviews
                     WHERE property_key = ?
                       AND student_email = ?
                     LIMIT 1"
                );
                $stmt->execute([$propertyKey, $studentEmail]);

                if ($stmt->fetch()) {
                    throw new Exception(
                        "You have already reviewed this property."
                    );
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO reviews
                     (property_key, student_email, rating, comment)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([
                    $propertyKey,
                    $studentEmail,
                    $rating,
                    $comment
                ]);

                redirectDashboard("reviews");
            }

            /*
            | Send a message to the landlord
            */
            elseif ($action === "message") {
                $propertyKey = trim($_POST["property_key"] ?? "");
                $message = trim($_POST["message"] ?? "");

                if ($message === "" || strlen($message) > 5000) {
                    throw new Exception(
                        "Enter a message of up to 5,000 characters."
                    );
                }

                $stmt = $pdo->prepare(
                    "SELECT landlord_email
                     FROM properties
                     WHERE property_key = ?
                     LIMIT 1"
                );
                $stmt->execute([$propertyKey]);
                $property = $stmt->fetch();

                if (!$property) {
                    throw new Exception("Property not found.");
                }

                if ($property["landlord_email"] === $studentEmail) {
                    throw new Exception(
                        "You cannot message yourself about your own property."
                    );
                }

                $messageKey = bin2hex(random_bytes(32));

                $stmt = $pdo->prepare(
                    "INSERT INTO messages
                     (message_key, sender_email, receiver_email,
                      property_key, message, is_read)
                     VALUES (?, ?, ?, ?, ?, 0)"
                );
                $stmt->execute([
                    $messageKey,
                    $studentEmail,
                    $property["landlord_email"],
                    $propertyKey,
                    $message
                ]);

                redirectDashboard("messages");
            }

            /*
            | Update account details
            */
            elseif ($action === "profile") {
                $name = trim($_POST["name"] ?? "");
                $phone = trim($_POST["phone"] ?? "");

                if ($name === "") {
                    throw new Exception("Your name cannot be empty.");
                }

                if (strlen($name) > 100 || strlen($phone) > 20) {
                    throw new Exception(
                        "Please check the length of your name and phone number."
                    );
                }

                $stmt = $pdo->prepare(
                    "UPDATE users
                     SET name = ?, phone = ?
                     WHERE email = ? AND role = 'student'"
                );
                $stmt->execute([$name, $phone, $studentEmail]);

                $_SESSION["name"] = $name;
                redirectDashboard("profile");
            }

            else {
                throw new Exception("Unknown action.");
            }

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof PDOException) {
                error_log($e->getMessage());
                $error = "The action could not be completed. Please check your information and try again.";
            } else {
                $error = $e->getMessage();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| LOAD ACCOUNT AND DASHBOARD DATA
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->prepare(
        "SELECT email, name, phone
         FROM users
         WHERE email = ? AND role = 'student'
         LIMIT 1"
    );
    $stmt->execute([$studentEmail]);
    $student = $stmt->fetch();

    if (!$student) {
        session_unset();
        session_destroy();
        header("Location: ../public/login.php");
        exit;
    }

    $studentName = $student["name"];

    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM properties
         WHERE availability_status = 'available'"
    );
    $availableCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM bookings
         WHERE student_email = ?
           AND status IN ('pending', 'accepted')"
    );
    $stmt->execute([$studentEmail]);
    $activeBookings = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM reviews
         WHERE student_email = ?"
    );
    $stmt->execute([$studentEmail]);
    $reviewCount = (int)$stmt->fetchColumn();

    /*
    | Search and filter available properties
    */
    $search = trim($_GET["search"] ?? "");
    $location = trim($_GET["location"] ?? "");
    $maxPrice = trim($_GET["max_price"] ?? "");

    $sql = "
        SELECT p.*,
               (SELECT ph.photo_path
                FROM property_photos ph
                WHERE ph.property_key = p.property_key
                ORDER BY ph.uploaded_at ASC
                LIMIT 1) AS cover_photo,
               (SELECT ROUND(AVG(r.rating), 1)
                FROM reviews r
                WHERE r.property_key = p.property_key) AS average_rating,
               (SELECT COUNT(*)
                FROM reviews r
                WHERE r.property_key = p.property_key) AS review_total
        FROM properties p
        WHERE p.availability_status = 'available'
    ";

    $params = [];

    if ($search !== "") {
        $sql .= " AND (
            p.title LIKE ?
            OR p.description LIKE ?
            OR p.address LIKE ?
            OR p.location_name LIKE ?
            OR p.amenities LIKE ?
        )";

        $term = "%" . $search . "%";
        array_push($params, $term, $term, $term, $term, $term);
    }

    if ($location !== "") {
        $sql .= " AND (
            p.location_name LIKE ?
            OR p.address LIKE ?
        )";

        $term = "%" . $location . "%";
        array_push($params, $term, $term);
    }

    if ($maxPrice !== "") {
        if (!is_numeric($maxPrice) || (float)$maxPrice < 0) {
            throw new Exception("Enter a valid maximum price.");
        }

        $sql .= " AND p.price <= ?";
        $params[] = (float)$maxPrice;
    }

    $sql .= " ORDER BY p.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $properties = $stmt->fetchAll();

    /*
    | Student's booking history
    */
    $stmt = $pdo->prepare(
        "SELECT b.property_key, b.booking_date, b.status,
                p.title, p.address, p.location_name, p.price,
                p.landlord_email, p.availability_status
         FROM bookings b
         INNER JOIN properties p
             ON p.property_key = b.property_key
         WHERE b.student_email = ?
         ORDER BY b.booking_date DESC"
    );
    $stmt->execute([$studentEmail]);
    $bookings = $stmt->fetchAll();

    /*
    | Student's reviews
    */
    $stmt = $pdo->prepare(
        "SELECT r.property_key, r.rating, r.comment, r.created_at,
                p.title
         FROM reviews r
         INNER JOIN properties p
             ON p.property_key = r.property_key
         WHERE r.student_email = ?
         ORDER BY r.created_at DESC"
    );
    $stmt->execute([$studentEmail]);
    $reviews = $stmt->fetchAll();

    /*
    | Conversations and messages
    */
    $stmt = $pdo->prepare(
        "SELECT m.*,
                p.title AS property_title,
                sender.name AS sender_name,
                receiver.name AS receiver_name
         FROM messages m
         LEFT JOIN properties p
             ON p.property_key = m.property_key
         LEFT JOIN users sender
             ON sender.email = m.sender_email
         LEFT JOIN users receiver
             ON receiver.email = m.receiver_email
         WHERE m.sender_email = ? OR m.receiver_email = ?
         ORDER BY m.created_at DESC"
    );
    $stmt->execute([$studentEmail, $studentEmail]);
    $messages = $stmt->fetchAll();

    /*
    | Mark incoming messages as read
    */
    $stmt = $pdo->prepare(
        "UPDATE messages
         SET is_read = 1
         WHERE receiver_email = ? AND is_read = 0"
    );
    $stmt->execute([$studentEmail]);

} catch (Throwable $e) {
    error_log($e->getMessage());
    $error = $error ?: "Some dashboard information could not be loaded. Please check your database tables.";
    $properties = $properties ?? [];
    $bookings = $bookings ?? [];
    $reviews = $reviews ?? [];
    $messages = $messages ?? [];
    $availableCount = $availableCount ?? 0;
    $activeBookings = $activeBookings ?? 0;
    $reviewCount = $reviewCount ?? 0;
    $student = $student ?? [
        "email" => $studentEmail,
        "name" => $studentName,
        "phone" => ""
    ];
}

$section = $_GET["section"] ?? "discover";
$allowedSections = [
    "discover", "bookings", "reviews", "messages", "profile"
];

if (!in_array($section, $allowedSections, true)) {
    $section = "discover";
}

function statusClass($status) {
    return match ($status) {
        "accepted", "completed", "available" => "good",
        "pending", "under_review" => "waiting",
        "rejected", "cancelled", "unavailable" => "bad",
        default => "waiting"
    };
}
?>
<?php
/*
|--------------------------------------------------------------------------
| VIEW HELPERS (display only, no data changes)
|--------------------------------------------------------------------------
*/
$imageBase = "../properties/";
$search   = $search   ?? "";
$location = $location ?? "";
$maxPrice = $maxPrice ?? "";
$filtersOn = ($search !== "" || $location !== "" || $maxPrice !== "");

$hour = (int)(new DateTime("now", new DateTimeZone("Africa/Nairobi")))->format("G");
$greeting = $hour < 12 ? "Good morning" : ($hour < 17 ? "Good afternoon" : "Good evening");
$firstName = explode(" ", trim($studentName))[0];

$icons = [
    "discover" => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
    "bookings" => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/>',
    "reviews"  => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
    "messages" => '<path d="M4 5h16v11H9l-5 4z"/>',
    "profile"  => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>',
    "gallery"  => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
    "logout"   => '<path d="M9 4H5v16h4"/><path d="M16 8l4 4-4 4M20 12H9"/>',
    "pin"      => '<path d="M12 21s7-6.2 7-11a7 7 0 10-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
    "search"   => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
    "house"    => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
    "chat"     => '<path d="M4 5h16v11H9l-5 4z"/>',
    "check"    => '<path d="M5 12l5 5 9-10"/>',
];
function ico($name, $size = 20) {
    global $icons;
    return '<svg class="ico" width="' . (int)$size . '" height="' . (int)$size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($icons[$name] ?? "") . '</svg>';
}

$titles = [
    "discover" => ["Discover accommodation", "Verified homes near campus, ready for you to explore."],
    "bookings" => ["My bookings", "Track every request from first click to move-in."],
    "reviews"  => ["My reviews", "What you told other students about the places you stayed."],
    "messages" => ["Messages", "Conversations with landlords about their properties."],
    "profile"  => ["My profile", "Keep your contact details current."],
];
$navItems = [
    "discover" => "Discover",
    "bookings" => "Bookings",
    "reviews"  => "Reviews",
    "messages" => "Messages",
    "profile"  => "Profile",
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | UniMtaa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
    :root {
        --p1: #2E1065;
        --p2: #4C1D95;
        --p3: #6D28D9;
        --p4: #8B5CF6;
        --p5: #C4B5FD;
        --p6: #EDE9FE;
        --p7: #F7F4FF;
        --ink: #1F1236;
        --muted: #675E80;
        --line: #E6DEF5;
        --bg: #FAF8FF;
        --card: #FFFFFF;
        --good-bg: #DCFCE7;
        --good: #166534;
        --wait-bg: #FEF3C7;
        --wait: #92400E;
        --bad-bg: #FEE2E2;
        --bad: #991B1B;
        --radius: 20px;
        --shadow: 0 1px 2px rgba(46, 16, 101, .06), 0 10px 30px -12px rgba(46, 16, 101, .18);
        --side: 268px;
    }

    *,
    *::before,
    *::after {
        box-sizing: border-box;
    }

    html {
        scroll-behavior: smooth;
    }

    body {
        margin: 0;
        background: var(--bg);
        color: var(--ink);
        font-family: "Plus Jakarta Sans", Inter, "Segoe UI", system-ui, Arial, sans-serif;
        font-size: 15px;
        line-height: 1.5;
        -webkit-font-smoothing: antialiased;
    }

    a {
        color: inherit;
        text-decoration: none;
    }

    button,
    input,
    textarea,
    select {
        font: inherit;
        color: inherit;
    }

    :focus-visible {
        outline: 3px solid var(--p4);
        outline-offset: 2px;
    }

    .sr {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
    }

    .skip {
        position: absolute;
        left: 12px;
        top: -60px;
        background: var(--p3);
        color: #fff;
        padding: 10px 16px;
        border-radius: 10px;
        z-index: 100;
        font-weight: 700;
    }

    .skip:focus {
        top: 12px;
    }

    .ico {
        flex: none;
    }

    /* ---------- layout ---------- */
    .app {
        display: grid;
        grid-template-columns: var(--side) minmax(0, 1fr);
        min-height: 100vh;
    }

    .side {
        position: sticky;
        top: 0;
        height: 100vh;
        display: flex;
        flex-direction: column;
        padding: 26px 18px 20px;
        color: #fff;
        background:
            radial-gradient(120% 60% at 0% 0%, rgba(139, 92, 246, .45), transparent 60%),
            linear-gradient(180deg, var(--p2), var(--p1));
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 0 8px 26px;
    }

    .brand-mark {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, var(--p4), var(--p3));
        box-shadow: 0 8px 20px -6px rgba(0, 0, 0, .45), inset 0 1px 0 rgba(255, 255, 255, .3);
    }

    .brand-name {
        font-weight: 800;
        font-size: 20px;
        letter-spacing: -.01em;
        line-height: 1.1;
    }

    .brand-sub {
        display: block;
        font-size: 11px;
        color: var(--p5);
        margin-top: 3px;
        font-weight: 500;
    }

    .nav-label {
        font-size: 10.5px;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: var(--p5);
        opacity: .8;
        padding: 6px 12px 10px;
    }

    .nav {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .nav a {
        position: relative;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border-radius: 14px;
        color: #E7DEFF;
        font-weight: 600;
        font-size: 14px;
        transition: background .15s, color .15s;
    }

    .nav a:hover {
        background: rgba(255, 255, 255, .09);
        color: #fff;
    }

    .nav a.active {
        background: #fff;
        color: var(--p2);
        box-shadow: 0 10px 24px -10px rgba(0, 0, 0, .5);
    }

    .nav a .dot {
        margin-left: auto;
        min-width: 22px;
        height: 22px;
        padding: 0 7px;
        border-radius: 99px;
        background: var(--p4);
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        display: grid;
        place-items: center;
    }

    .nav a.active .dot {
        background: var(--p6);
        color: var(--p3);
    }

    .side-foot {
        margin-top: auto;
        padding-top: 18px;
    }

    .me {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px;
        border-radius: 18px;
        background: rgba(255, 255, 255, .08);
        border: 1px solid rgba(255, 255, 255, .12);
    }

    .avatar {
        width: 42px;
        height: 42px;
        flex: none;
        border-radius: 50%;
        display: grid;
        place-items: center;
        font-weight: 800;
        color: var(--p1);
        background: linear-gradient(135deg, var(--p5), #fff);
    }

    .me strong {
        display: block;
        font-size: 14px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 140px;
    }

    .me small {
        color: var(--p5);
        font-size: 12px;
    }

    .logout {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 10px;
        padding: 11px;
        border-radius: 14px;
        font-weight: 700;
        font-size: 13px;
        color: #E7DEFF;
        border: 1px solid rgba(255, 255, 255, .18);
        transition: background .15s;
    }

    .logout:hover {
        background: rgba(255, 255, 255, .1);
        color: #fff;
    }

    .main {
        min-width: 0;
    }

    .mobile-top {
        display: none;
    }

    .wrap {
        max-width: 1240px;
        margin: 0 auto;
        padding: 28px 34px 60px;
    }

    /* ---------- hero ---------- */
    .hero {
        position: relative;
        overflow: hidden;
        border-radius: 28px;
        color: #fff;
        background:
            radial-gradient(70% 120% at 85% 0%, rgba(196, 181, 253, .55), transparent 60%),
            linear-gradient(120deg, var(--p1) 0%, var(--p2) 45%, var(--p3) 100%);
        min-height: 300px;
        isolation: isolate;
    }

    .hero-sky {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 170px;
        z-index: -1;
    }

    .hero-sky svg {
        width: 100%;
        height: 100%;
        display: block;
    }

    .hero-body {
        padding: 44px 44px 120px;
        max-width: 700px;
    }

    .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 12.5px;
        font-weight: 700;
        letter-spacing: .06em;
        padding: 6px 12px;
        border-radius: 99px;
        background: rgba(255, 255, 255, .14);
        border: 1px solid rgba(255, 255, 255, .22);
        margin: 0 0 16px;
    }

    .hero h1 {
        margin: 0 0 12px;
        font-size: clamp(28px, 3.6vw, 42px);
        line-height: 1.1;
        letter-spacing: -.025em;
        font-weight: 800;
    }

    .hero h1 em {
        font-style: normal;
        color: var(--p5);
    }

    .hero p.lead {
        margin: 0;
        color: #E9E0FF;
        max-width: 520px;
        font-size: 15.5px;
    }

    /* ---------- finder ---------- */
    .finder {
        position: relative;
        z-index: 2;
        margin: -52px 26px 0;
        display: grid;
        grid-template-columns: 1.6fr 1.2fr 1fr auto;
        gap: 6px;
        align-items: stretch;
        padding: 10px;
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 22px;
        box-shadow: 0 24px 50px -22px rgba(46, 16, 101, .45);
    }

    .fld {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 16px;
        border-radius: 14px;
        transition: background .15s;
    }

    .fld:focus-within {
        background: var(--p7);
    }

    .fld+.fld {
        border-left: 1px solid var(--line);
        border-radius: 0 14px 14px 0;
    }

    .fld .ico {
        color: var(--p3);
    }

    .fld label {
        display: block;
        font-size: 10.5px;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .fld input {
        width: 100%;
        border: 0;
        outline: 0;
        background: transparent;
        padding: 2px 0 0;
        font-size: 14.5px;
        font-weight: 600;
    }

    .fld input::placeholder {
        color: #A59DBE;
        font-weight: 500;
    }

    .finder-actions {
        display: flex;
        gap: 6px;
        align-items: center;
        padding-left: 4px;
    }

    /* ---------- buttons ---------- */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: 0;
        cursor: pointer;
        font-weight: 700;
        font-size: 13.5px;
        padding: 12px 18px;
        border-radius: 14px;
        transition: transform .15s, box-shadow .15s, background .15s;
        white-space: nowrap;
    }

    .btn:hover {
        transform: translateY(-1px);
    }

    .btn-primary {
        background: var(--p3);
        color: #fff;
        box-shadow: 0 10px 22px -10px rgba(109, 40, 217, .8);
    }

    .btn-primary:hover {
        background: #5B21B6;
    }

    .btn-soft {
        background: var(--p6);
        color: var(--p2);
    }

    .btn-soft:hover {
        background: #DDD3FB;
    }

    .btn-ghost {
        background: #fff;
        color: var(--ink);
        border: 1px solid var(--line);
    }

    .btn-ghost:hover {
        background: var(--p7);
    }

    .btn-danger {
        background: var(--bad-bg);
        color: var(--bad);
    }

    .btn-sm {
        padding: 9px 13px;
        font-size: 12.5px;
        border-radius: 11px;
    }

    /* ---------- stats ---------- */
    .stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin: 26px 0 8px;
    }

    .stat {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 18px 20px;
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
    }

    .stat-ico {
        width: 48px;
        height: 48px;
        border-radius: 15px;
        display: grid;
        place-items: center;
        background: var(--p6);
        color: var(--p3);
    }

    .stat b {
        display: block;
        font-size: 28px;
        line-height: 1;
        font-weight: 800;
        letter-spacing: -.02em;
        color: var(--p2);
    }

    .stat span {
        font-size: 12.5px;
        color: var(--muted);
        font-weight: 600;
    }

    /* ---------- headings ---------- */
    .page-head {
        margin: 4px 0 24px;
    }

    .page-head h1 {
        margin: 0;
        font-size: clamp(24px, 3vw, 32px);
        letter-spacing: -.02em;
        font-weight: 800;
        color: var(--p1);
    }

    .page-head p {
        margin: 6px 0 0;
        color: var(--muted);
    }

    .sec-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin: 30px 0 18px;
    }

    .sec-head h2 {
        margin: 0;
        font-size: 22px;
        letter-spacing: -.015em;
        font-weight: 800;
        color: var(--p1);
    }

    .sec-head p {
        margin: 4px 0 0;
        color: var(--muted);
        font-size: 13.5px;
    }

    /* ---------- property cards ---------- */
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 22px;
    }

    .card {
        display: flex;
        flex-direction: column;
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 24px;
        overflow: hidden;
        box-shadow: var(--shadow);
        transition: transform .2s, box-shadow .2s;
    }

    .card:hover {
        transform: translateY(-4px);
        box-shadow: 0 2px 4px rgba(46, 16, 101, .06), 0 24px 40px -16px rgba(46, 16, 101, .3);
    }

    .photo {
        position: relative;
        aspect-ratio: 4 / 3;
        overflow: hidden;
        background: linear-gradient(135deg, var(--p6), var(--p5));
    }

    .photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform .5s;
    }

    .card:hover .photo img {
        transform: scale(1.05);
    }

    .ph-fallback {
        position: absolute;
        inset: 0;
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        color: var(--p3);
        font-size: 12px;
        font-weight: 700;
    }

    .photo.nophoto .ph-fallback {
        display: flex;
    }

    .photo.nophoto img {
        display: none;
    }

    .photo::after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 45%;
        background: linear-gradient(to top, rgba(31, 18, 54, .55), transparent);
        pointer-events: none;
    }

    .photo.nophoto::after {
        display: none;
    }

    .pill {
        position: absolute;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 11px;
        border-radius: 99px;
        font-size: 11.5px;
        font-weight: 800;
        backdrop-filter: blur(6px);
    }

    .pill.avail {
        top: 14px;
        left: 14px;
        background: rgba(255, 255, 255, .92);
        color: var(--good);
    }

    .pill.avail::before {
        content: "";
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #22C55E;
    }

    .pill.price {
        left: 14px;
        bottom: 14px;
        background: var(--p3);
        color: #fff;
        font-size: 14px;
        padding: 7px 13px;
    }

    .pill.price small {
        font-weight: 600;
        opacity: .8;
        font-size: 11px;
    }

    .pill.rate {
        right: 14px;
        bottom: 14px;
        background: rgba(255, 255, 255, .94);
        color: var(--p1);
    }

    .pill.rate svg {
        color: var(--p3);
        fill: var(--p4);
    }

    .card-body {
        display: flex;
        flex-direction: column;
        flex: 1;
        padding: 18px 20px 20px;
    }

    .card-body h3 {
        margin: 0 0 6px;
        font-size: 18px;
        line-height: 1.25;
        letter-spacing: -.01em;
        font-weight: 800;
    }

    .loc {
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 0 0 10px;
        color: var(--muted);
        font-size: 13px;
        font-weight: 500;
    }

    .loc .ico {
        color: var(--p3);
    }

    .desc {
        margin: 0 0 12px;
        color: var(--muted);
        font-size: 13.5px;
    }

    .tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin: 0 0 16px;
        padding: 0;
        list-style: none;
    }

    .tags li {
        padding: 5px 10px;
        border-radius: 99px;
        background: var(--p7);
        border: 1px solid var(--line);
        color: var(--p2);
        font-size: 11.5px;
        font-weight: 700;
    }

    .actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: auto;
    }

    .actions {
        flex-direction: column;
    }

    .actions form {
        display: block;
    }

    .actions .btn {
        width: 100%;
    }

    .msgbox {
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px dashed var(--line);
    }

    .msgbox[hidden] {
        display: none;
    }

    /* ---------- forms ---------- */
    .field label,
    .field legend {
        display: block;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 7px;
        color: var(--p2);
    }

    .field input,
    .field textarea,
    .field select {
        width: 100%;
        padding: 12px 14px;
        border: 1.5px solid var(--line);
        border-radius: 13px;
        background: #fff;
        outline: 0;
        transition: border-color .15s, box-shadow .15s;
    }

    .field input:focus,
    .field textarea:focus,
    .field select:focus {
        border-color: var(--p4);
        box-shadow: 0 0 0 4px rgba(139, 92, 246, .16);
    }

    .field input:disabled {
        background: var(--p7);
        color: var(--muted);
    }

    .field textarea {
        min-height: 92px;
        resize: vertical;
    }

    .field small {
        display: block;
        margin-top: 6px;
        color: var(--muted);
        font-size: 12px;
    }

    .stars {
        display: inline-flex;
        flex-direction: row-reverse;
        gap: 2px;
        border: 0;
        padding: 0;
        margin: 0;
    }

    .lbl {
        display: block;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 7px;
        color: var(--p2);
    }

    .stars {
        min-width: 0;
    }

    .stars input {
        position: absolute;
        opacity: 0;
        width: 1px;
        height: 1px;
    }

    .stars label {
        margin: 0;
        font-size: 30px;
        line-height: 1;
        cursor: pointer;
        color: #D8CEEE;
        transition: color .12s, transform .12s;
    }

    .stars label:hover {
        transform: scale(1.12);
    }

    .stars input:checked~label,
    .stars label:hover,
    .stars label:hover~label {
        color: var(--p3);
    }

    .stars input:focus-visible+label {
        outline: 3px solid var(--p4);
        border-radius: 6px;
    }

    /* ---------- bookings ---------- */
    .stack {
        display: grid;
        gap: 16px;
    }

    .row-card {
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: var(--radius);
        padding: 22px;
        box-shadow: var(--shadow);
    }

    .row-top {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        align-items: flex-start;
    }

    .row-top h3 {
        margin: 0 0 6px;
        font-size: 17px;
        font-weight: 800;
    }

    .meta {
        color: var(--muted);
        font-size: 13px;
        margin: 2px 0;
    }

    .amount {
        margin: 10px 0 0;
        font-size: 20px;
        font-weight: 800;
        color: var(--p3);
        letter-spacing: -.01em;
    }

    .badge {
        display: inline-flex;
        padding: 6px 12px;
        border-radius: 99px;
        font-size: 11.5px;
        font-weight: 800;
        text-transform: capitalize;
        white-space: nowrap;
    }

    .badge.good {
        background: var(--good-bg);
        color: var(--good);
    }

    .badge.waiting {
        background: var(--wait-bg);
        color: var(--wait);
    }

    .badge.bad {
        background: var(--bad-bg);
        color: var(--bad);
    }

    .track {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        list-style: none;
        margin: 20px 0 4px;
        padding: 0;
    }

    .track li {
        position: relative;
        text-align: center;
        font-size: 12px;
        font-weight: 700;
        color: #A59DBE;
        padding-top: 34px;
    }

    .track li::before {
        content: "";
        position: absolute;
        top: 8px;
        left: 50%;
        transform: translateX(-50%);
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #fff;
        border: 3px solid var(--line);
        z-index: 1;
    }

    .track li::after {
        content: "";
        position: absolute;
        top: 16px;
        left: -50%;
        width: 100%;
        height: 3px;
        background: var(--line);
    }

    .track li:first-child::after {
        display: none;
    }

    .track li.done {
        color: var(--p2);
    }

    .track li.done::before {
        background: var(--p3);
        border-color: var(--p3);
    }

    .track li.done::after {
        background: var(--p3);
    }

    .track li.now::before {
        box-shadow: 0 0 0 6px rgba(139, 92, 246, .25);
    }

    .stopped {
        margin: 16px 0 0;
        padding: 10px 14px;
        border-radius: 12px;
        background: var(--bad-bg);
        color: var(--bad);
        font-size: 13px;
        font-weight: 600;
    }

    .row-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 16px;
    }

    .review-form {
        margin-top: 18px;
        padding: 18px;
        border-radius: 16px;
        background: var(--p7);
        border: 1px solid var(--line);
    }

    .review-form .field+.field {
        margin-top: 14px;
    }

    .note-ok {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 16px 0 0;
        color: var(--good);
        font-weight: 700;
        font-size: 13px;
    }

    /* ---------- reviews ---------- */
    .rv-stars {
        color: var(--p3);
        letter-spacing: 3px;
        font-size: 18px;
        margin: 6px 0;
    }

    .rv-stars .off {
        color: #D8CEEE;
    }

    .quote {
        margin: 12px 0 0;
        padding: 12px 16px;
        border-left: 4px solid var(--p5);
        background: var(--p7);
        border-radius: 0 12px 12px 0;
        color: var(--ink);
        font-size: 14px;
    }

    /* ---------- messages ---------- */
    .thread {
        display: grid;
        gap: 14px;
    }

    .msg {
        display: flex;
    }

    .msg.mine {
        justify-content: flex-end;
    }

    .bubble {
        max-width: min(640px, 92%);
        padding: 16px 18px;
        border-radius: 20px 20px 20px 6px;
        background: #fff;
        border: 1px solid var(--line);
        box-shadow: var(--shadow);
    }

    .msg.mine .bubble {
        border-radius: 20px 20px 6px 20px;
        background: linear-gradient(135deg, var(--p3), var(--p2));
        border-color: transparent;
        color: #fff;
    }

    .bubble h3 {
        margin: 0 0 2px;
        font-size: 14.5px;
        font-weight: 800;
    }

    .bubble .who {
        font-size: 12px;
        color: var(--muted);
        margin-bottom: 8px;
    }

    .msg.mine .who {
        color: var(--p5);
    }

    .bubble p {
        margin: 0;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    .bubble time {
        display: block;
        margin-top: 8px;
        font-size: 11.5px;
        color: var(--muted);
    }

    .msg.mine time {
        color: var(--p5);
    }

    .reply {
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px dashed var(--line);
    }

    /* ---------- profile ---------- */
    .profile {
        display: grid;
        grid-template-columns: 300px minmax(0, 1fr);
        gap: 20px;
        align-items: start;
    }

    .id-card {
        text-align: center;
        padding: 30px 22px;
        border-radius: 26px;
        color: #fff;
        background:
            radial-gradient(90% 70% at 50% 0%, rgba(196, 181, 253, .4), transparent 70%),
            linear-gradient(160deg, var(--p3), var(--p1));
        box-shadow: var(--shadow);
    }

    .id-card .avatar {
        width: 92px;
        height: 92px;
        margin: 0 auto 14px;
        font-size: 36px;
        border: 4px solid rgba(255, 255, 255, .35);
    }

    .id-card h2 {
        margin: 0;
        font-size: 20px;
    }

    .id-card p {
        margin: 4px 0 0;
        color: var(--p5);
        font-size: 13px;
        overflow-wrap: anywhere;
    }

    .id-card .role {
        display: inline-block;
        margin-top: 14px;
        padding: 5px 14px;
        border-radius: 99px;
        background: rgba(255, 255, 255, .16);
        font-size: 12px;
        font-weight: 800;
    }

    .panel {
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 24px;
        padding: 26px;
        box-shadow: var(--shadow);
    }

    .panel+.panel {
        margin-top: 18px;
    }

    .panel h2 {
        margin: 0 0 16px;
        font-size: 18px;
        font-weight: 800;
        color: var(--p1);
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .safety {
        display: flex;
        gap: 14px;
        align-items: flex-start;
    }

    .safety .stat-ico {
        flex: none;
    }

    .safety p {
        margin: 0 0 14px;
        color: var(--muted);
        font-size: 13.5px;
    }

    /* ---------- misc ---------- */
    .alert {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        padding: 14px 18px;
        border-radius: 16px;
        margin-bottom: 20px;
        font-weight: 600;
        font-size: 14px;
    }

    .alert-error {
        background: var(--bad-bg);
        color: var(--bad);
        border: 1px solid #FCA5A5;
    }

    .alert-success {
        background: var(--good-bg);
        color: var(--good);
        border: 1px solid #86EFAC;
    }

    .empty {
        text-align: center;
        padding: 48px 24px;
        background: #fff;
        border: 2px dashed var(--p5);
        border-radius: 26px;
    }

    .empty .stat-ico {
        width: 64px;
        height: 64px;
        border-radius: 20px;
        margin: 0 auto 14px;
    }

    .empty h3 {
        margin: 0 0 6px;
        font-size: 19px;
        color: var(--p1);
    }

    .empty p {
        margin: 0 auto 18px;
        max-width: 420px;
        color: var(--muted);
        font-size: 14px;
    }

    .foot {
        margin-top: 40px;
        text-align: center;
        color: var(--muted);
        font-size: 12.5px;
    }

    /* ---------- responsive ---------- */
    @media (max-width: 1100px) {
        .finder {
            grid-template-columns: 1fr 1fr;
        }

        .fld+.fld {
            border-left: 0;
            border-radius: 14px;
        }

        .finder-actions {
            grid-column: 1 / -1;
            justify-content: flex-end;
        }

        .profile {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 900px) {
        .app {
            display: block;
        }

        .side {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            top: auto;
            height: auto;
            z-index: 50;
            padding: 8px 8px calc(8px + env(safe-area-inset-bottom));
            background: var(--p1);
            border-top: 1px solid rgba(255, 255, 255, .12);
        }

        .brand,
        .nav-label,
        .side-foot {
            display: none;
        }

        .nav {
            flex-direction: row;
            gap: 2px;
            justify-content: space-between;
        }

        .nav a {
            flex: 1;
            flex-direction: column;
            gap: 3px;
            padding: 8px 2px;
            font-size: 10.5px;
            text-align: center;
            border-radius: 12px;
        }

        .nav a .dot {
            display: none;
        }

        .mobile-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            background: #fff;
            border-bottom: 1px solid var(--line);
            position: sticky;
            top: 0;
            z-index: 40;
        }

        .mobile-top .brand-mark {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            color: #fff;
        }

        .mobile-top a.b {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 18px;
            color: var(--p1);
        }

        .wrap {
            padding: 18px 16px 110px;
        }

        .hero-body {
            padding: 30px 24px 110px;
        }

        .finder {
            margin: -52px 8px 0;
            grid-template-columns: 1fr;
        }

        .finder-actions .btn {
            flex: 1;
        }

        .stats {
            grid-template-columns: 1fr;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .row-top {
            flex-direction: column;
        }

        .grid {
            grid-template-columns: 1fr;
        }
    }

    @media (prefers-reduced-motion: reduce) {

        *,
        *::before,
        *::after {
            transition: none !important;
            animation: none !important;
            scroll-behavior: auto !important;
        }

        .card:hover,
        .btn:hover {
            transform: none;
        }
    }
    </style>
</head>

<body>
    <a class="skip" href="#main">Skip to main content</a>

    <div class="app">

        <aside class="side" aria-label="Student navigation">
            <a class="brand" href="student.php">
                <span class="brand-mark"><?= ico("house", 24) ?></span>
                <span>
                    <span class="brand-name">UniMtaa</span>
                    <span class="brand-sub">Student Accommodation</span>
                </span>
            </a>

            <div class="nav-label">Student workspace</div>
            <nav class="nav" aria-label="Sections">
                <?php foreach ($navItems as $key => $label): ?>
                <a href="student.php?section=<?= h($key) ?>" class="<?= $section === $key ? 'active' : '' ?>"
                    <?= $section === $key ? 'aria-current="page"' : '' ?>>
                    <?= ico($key) ?>
                    <?= h($label) ?>
                    <?php if ($key === "bookings" && $activeBookings > 0): ?>
                    <span class="dot" aria-label="<?= (int)$activeBookings ?> active"><?= (int)$activeBookings ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
                <a href="../properties/browse.php">
                    <?= ico("gallery") ?>
                    Gallery
                </a>
            </nav>

            <div class="side-foot">
                <div class="me">
                    <div class="avatar" aria-hidden="true"><?= h(strtoupper(substr($studentName, 0, 1))) ?></div>
                    <div>
                        <strong><?= h($studentName) ?></strong>
                        <small>Student account</small>
                    </div>
                </div>
                <a class="logout" href="../public/logout.php"><?= ico("logout", 18) ?> Log out</a>
            </div>
        </aside>

        <main class="main" id="main">

            <div class="mobile-top">
                <a class="b" href="student.php">
                    <span class="brand-mark"
                        style="background:linear-gradient(135deg,var(--p4),var(--p3))"><?= ico("house", 20) ?></span>
                    UniMtaa
                </a>
                <a class="btn btn-soft btn-sm" href="../public/logout.php"><?= ico("logout", 16) ?> Log out</a>
            </div>

            <div class="wrap">

                <?php if ($error !== ""): ?>
                <div class="alert alert-error" role="alert"><?= h($error) ?></div>
                <?php endif; ?>

                <?php if ($success !== ""): ?>
                <div class="alert alert-success" role="status"><?= h($success) ?></div>
                <?php endif; ?>

                <?php if ($section === "discover"): ?>

                <section class="hero" aria-labelledby="hello">
                    <div class="hero-sky"><svg viewBox="0 0 1200 170" preserveAspectRatio="xMidYMax slice"
                            aria-hidden="true" focusable="false">
                            <rect x="0" y="106" width="64" height="64" fill="#2E1065" opacity=".55" />
                            <rect x="22" y="116" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="36" y="116" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="50" y="116" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="8" y="132" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="36" y="132" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="8" y="148" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="22" y="148" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="68" y="51" width="90" height="119" fill="#2E1065" opacity=".55" />
                            <rect x="104" y="61" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="118" y="61" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="104" y="77" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="132" y="77" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="90" y="109" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="132" y="109" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="146" y="109" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="104" y="125" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="118" y="125" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="132" y="125" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="146" y="125" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="104" y="141" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="146" y="141" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="76" y="157" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="172" y="118" width="40" height="52" fill="#2E1065" opacity=".55" />
                            <rect x="194" y="128" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="180" y="160" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="220" y="66" width="40" height="104" fill="#2E1065" opacity=".55" />
                            <rect x="228" y="76" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="242" y="76" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="228" y="92" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="242" y="92" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="242" y="124" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="268" y="80" width="78" height="90" fill="#2E1065" opacity=".55" />
                            <rect x="304" y="90" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="318" y="90" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="276" y="106" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="290" y="106" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="304" y="106" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="276" y="122" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="276" y="138" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="332" y="138" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="290" y="154" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="318" y="154" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="332" y="154" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="360" y="57" width="52" height="113" fill="#2E1065" opacity=".55" />
                            <rect x="368" y="67" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="396" y="67" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="382" y="83" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="396" y="99" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="368" y="131" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="416" y="79" width="90" height="91" fill="#2E1065" opacity=".55" />
                            <rect x="424" y="89" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="466" y="89" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="424" y="105" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="452" y="105" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="494" y="105" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="466" y="121" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="424" y="137" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="424" y="153" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="514" y="97" width="40" height="73" fill="#2E1065" opacity=".55" />
                            <rect x="522" y="107" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="536" y="107" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="536" y="139" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="522" y="155" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="536" y="155" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="558" y="25" width="78" height="145" fill="#2E1065" opacity=".55" />
                            <rect x="580" y="35" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="622" y="35" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="622" y="51" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="566" y="67" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="608" y="67" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="580" y="83" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="608" y="83" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="566" y="115" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="580" y="115" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="594" y="115" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="608" y="115" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="566" y="131" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="580" y="131" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="594" y="147" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="622" y="147" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="640" y="125" width="90" height="45" fill="#2E1065" opacity=".55" />
                            <rect x="662" y="135" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="718" y="135" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="704" y="151" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="718" y="151" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="744" y="54" width="78" height="116" fill="#2E1065" opacity=".55" />
                            <rect x="752" y="64" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="794" y="96" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="752" y="112" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="766" y="112" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="794" y="112" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="808" y="112" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="752" y="128" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="794" y="128" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="752" y="144" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="794" y="144" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="794" y="160" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="830" y="72" width="64" height="98" fill="#2E1065" opacity=".55" />
                            <rect x="838" y="82" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="852" y="82" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="866" y="82" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="880" y="98" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="838" y="114" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="866" y="114" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="880" y="114" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="838" y="130" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="902" y="114" width="64" height="56" fill="#2E1065" opacity=".55" />
                            <rect x="910" y="124" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="924" y="124" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="938" y="124" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="924" y="140" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="938" y="140" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="910" y="156" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="970" y="35" width="90" height="135" fill="#2E1065" opacity=".55" />
                            <rect x="978" y="45" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="1006" y="45" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="1020" y="45" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="978" y="61" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="1006" y="61" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="1048" y="61" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="1006" y="77" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="1020" y="77" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="1034" y="77" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="978" y="109" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="1006" y="109" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="1048" y="109" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="992" y="125" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="1006" y="125" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="978" y="141" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="1006" y="141" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="1020" y="141" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="1034" y="141" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="1006" y="157" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="1020" y="157" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="1034" y="157" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="1048" y="157" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="1074" y="94" width="52" height="76" fill="#2E1065" opacity=".55" />
                            <rect x="1096" y="104" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                            <rect x="1082" y="120" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="1096" y="120" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                            <rect x="1110" y="120" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="1082" y="136" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="1082" y="152" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="1134" y="106" width="78" height="64" fill="#2E1065" opacity=".55" />
                            <rect x="1142" y="116" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="1170" y="116" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="1170" y="132" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                            <rect x="1156" y="148" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                        </svg></div>
                    <div class="hero-body">
                        <p class="eyebrow"><?= h($greeting) ?></p>
                        <h1 id="hello">Hello, <?= h($firstName) ?>. Let's find the place that feels like <em>yours.</em>
                        </h1>
                        <p class="lead">
                            Explore available accommodation, compare your options, and contact landlords directly.
                        </p>
                    </div>
                </section>

                <form class="finder" method="GET" action="student.php#available-homes" role="search">
                    <input type="hidden" name="section" value="discover">

                    <div class="fld">
                        <?= ico("search") ?>
                        <div style="flex:1">
                            <label for="f-search">Search</label>
                            <input id="f-search" type="text" name="search" placeholder="Bedsitter, Wi-Fi, furnished"
                                value="<?= h($search) ?>">
                        </div>
                    </div>

                    <div class="fld">
                        <?= ico("pin") ?>
                        <div style="flex:1">
                            <label for="f-loc">Location</label>
                            <input id="f-loc" type="text" name="location" placeholder="e.g. Madaraka"
                                value="<?= h($location) ?>">
                        </div>
                    </div>

                    <div class="fld">
                        <?= ico("bookings") ?>
                        <div style="flex:1">
                            <label for="f-max">Max rent (KSh)</label>
                            <input id="f-max" type="number" name="max_price" min="0" step="0.01" placeholder="15000"
                                value="<?= h($maxPrice) ?>">
                        </div>
                    </div>

                    <div class="finder-actions">
                        <button class="btn btn-primary" type="submit">Search</button>
                        <a class="btn btn-ghost" href="student.php?section=discover">Reset</a>
                    </div>
                </form>

                <div class="stats">
                    <div class="stat">
                        <div class="stat-ico"><?= ico("house", 24) ?></div>
                        <div><b><?= (int)$availableCount ?></b><span>Available properties</span></div>
                    </div>
                    <div class="stat">
                        <div class="stat-ico"><?= ico("bookings", 24) ?></div>
                        <div><b><?= (int)$activeBookings ?></b><span>Active bookings</span></div>
                    </div>
                    <div class="stat">
                        <div class="stat-ico"><?= ico("reviews", 24) ?></div>
                        <div><b><?= (int)$reviewCount ?></b><span>Reviews submitted</span></div>
                    </div>
                </div>

                <div class="sec-head" id="available-homes">
                    <div>
                        <h2><?= $filtersOn ? "Matching homes" : "Find your next home" ?></h2>
                        <p>
                            <?php if ($filtersOn): ?>
                            <?= count($properties) ?> result(s) for your search.
                            <?php else: ?>
                            Search by property, location, amenities or budget.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php if (!$properties): ?>
                <div class="empty">
                    <div class="stat-ico"><?= ico("house", 30) ?></div>
                    <h3>No available properties found</h3>
                    <p>Try another search or check again later. Properties under review or marked unavailable are not
                        shown here.</p>
                    <?php if ($filtersOn): ?>
                    <a class="btn btn-primary" href="student.php?section=discover">Clear search</a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="grid">
                    <?php foreach ($properties as $property):
                        $cover = $property["cover_photo"] ?? "";
                        if ($cover === "" || $cover === null) {
                            $cover = $property["image_path"] ?? "";
                        }
                        $amen = array_filter(array_map("trim", explode(",", (string)($property["amenities"] ?? ""))));
                        $amen = array_slice($amen, 0, 4);
                        $mid = "message-" . $property["property_key"];
                    ?>
                    <article class="card">
                        <div class="photo<?= $cover ? '' : ' nophoto' ?>">
                            <?php if ($cover): ?>
                            <img src="<?= h($imageBase . $cover) ?>" loading="lazy"
                                alt="Photo of <?= h($property["title"]) ?>"
                                onerror="this.parentNode.classList.add('nophoto')">
                            <?php endif; ?>
                            <div class="ph-fallback"><?= ico("house", 34) ?>No photo yet</div>

                            <span class="pill avail">Available</span>
                            <span class="pill price">KSh <?= number_format((float)$property["price"]) ?> <small>/
                                    rent</small></span>
                            <span class="pill rate">
                                <?= ico("reviews", 14) ?>
                                <?php if ($property["average_rating"] !== null): ?>
                                <?= h($property["average_rating"]) ?> (<?= (int)$property["review_total"] ?>)
                                <?php else: ?>
                                New
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="card-body">
                            <h3><?= h($property["title"]) ?></h3>
                            <p class="loc"><?= ico("pin", 16) ?>
                                <?= h($property["location_name"] ?: $property["address"]) ?></p>

                            <p class="desc">
                                <?= h(mb_strimwidth((string)$property["description"], 0, 130, "...")) ?>
                            </p>

                            <?php if ($amen): ?>
                            <ul class="tags" aria-label="Amenities">
                                <?php foreach ($amen as $a): ?>
                                <li><?= h($a) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>

                            <div class="actions">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="action" value="book">
                                    <input type="hidden" name="property_key"
                                        value="<?= h($property["property_key"]) ?>">
                                    <button type="submit" class="btn btn-primary"
                                        onclick="return confirm('Send a booking request for this property?')">
                                        Request booking
                                    </button>
                                </form>

                                <button type="button" class="btn btn-soft" aria-expanded="false"
                                    aria-controls="<?= h($mid) ?>" onclick="toggleMsg('<?= h($mid) ?>', this)">
                                    <?= ico("chat", 16) ?> Contact landlord
                                </button>
                            </div>

                            <form id="<?= h($mid) ?>" method="POST" class="msgbox" hidden>
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="action" value="message">
                                <input type="hidden" name="property_key" value="<?= h($property["property_key"]) ?>">

                                <div class="field">
                                    <label for="t-<?= h($mid) ?>">Message the landlord</label>
                                    <textarea id="t-<?= h($mid) ?>" name="message" required maxlength="5000"
                                        placeholder="Ask about availability, amenities or viewing..."></textarea>
                                </div>

                                <button class="btn btn-primary btn-sm" type="submit" style="margin-top:10px">
                                    Send message
                                </button>
                            </form>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php elseif ($section === "bookings"): ?>

                <div class="page-head">
                    <h1><?= h($titles["bookings"][0]) ?></h1>
                    <p>Track requests and see the latest status of each booking.</p>
                </div>

                <?php if (!$bookings): ?>
                <div class="empty">
                    <div class="stat-ico"><?= ico("bookings", 30) ?></div>
                    <h3>No bookings yet</h3>
                    <p>When you request accommodation, it will appear here.</p>
                    <a class="btn btn-primary" href="student.php?section=discover">Browse homes</a>
                </div>
                <?php else: ?>
                <div class="stack">
                    <?php $flow = ["pending" => 1, "accepted" => 2, "completed" => 3]; ?>
                    <?php foreach ($bookings as $bi => $booking):
                        $stage = $flow[$booking["status"]] ?? 0;
                    ?>
                    <article class="row-card">
                        <div class="row-top">
                            <div>
                                <h3><?= h($booking["title"]) ?></h3>
                                <p class="meta"><?= ico("pin", 14) ?>
                                    <?= h($booking["location_name"] ?: $booking["address"]) ?></p>
                                <p class="meta">Requested
                                    <?= h(date("d M Y, g:i a", strtotime($booking["booking_date"]))) ?></p>
                                <p class="amount">KSh <?= number_format((float)$booking["price"], 2) ?></p>
                            </div>
                            <span
                                class="badge <?= statusClass($booking["status"]) ?>"><?= h($booking["status"]) ?></span>
                        </div>

                        <?php if ($stage > 0): ?>
                        <ol class="track" aria-label="Booking progress">
                            <?php foreach (["Requested", "Accepted", "Completed"] as $si => $sl):
                                $n = $si + 1;
                                $cls = $n <= $stage ? "done" : "";
                                if ($n === $stage) $cls .= " now";
                            ?>
                            <li class="<?= trim($cls) ?>"><?= h($sl) ?></li>
                            <?php endforeach; ?>
                        </ol>
                        <?php else: ?>
                        <p class="stopped">This request is <?= h($booking["status"]) ?>.</p>
                        <?php endif; ?>

                        <div class="row-actions">
                            <?php if ($booking["status"] === "pending"): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="action" value="cancel_booking">
                                <input type="hidden" name="property_key" value="<?= h($booking["property_key"]) ?>">
                                <button class="btn btn-danger btn-sm" type="submit"
                                    onclick="return confirm('Cancel this pending booking request?')">
                                    Cancel request
                                </button>
                            </form>
                            <?php endif; ?>

                            <a class="btn btn-soft btn-sm" href="student.php?section=discover">Browse other
                                properties</a>
                        </div>

                        <?php if ($booking["status"] === "completed"): ?>
                        <?php
                            $alreadyReviewed = false;
                            foreach ($reviews as $review) {
                                if ($review["property_key"] === $booking["property_key"]) {
                                    $alreadyReviewed = true;
                                    break;
                                }
                            }
                        ?>
                        <?php if (!$alreadyReviewed): ?>
                        <form method="POST" class="review-form">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                            <input type="hidden" name="action" value="review">
                            <input type="hidden" name="property_key" value="<?= h($booking["property_key"]) ?>">

                            <div class="field">
                                <span class="lbl" id="rl-<?= (int)$bi ?>">Your rating</span>
                                <fieldset class="stars" aria-labelledby="rl-<?= (int)$bi ?>">
                                    <?php foreach ([5, 4, 3, 2, 1] as $r): ?>
                                    <input type="radio" name="rating" id="rt-<?= (int)$bi ?>-<?= $r ?>"
                                        value="<?= $r ?>" required>
                                    <label for="rt-<?= (int)$bi ?>-<?= $r ?>"
                                        title="<?= $r ?> star<?= $r > 1 ? 's' : '' ?>">
                                        <span class="sr"><?= $r ?> star<?= $r > 1 ? 's' : '' ?></span>&#9733;
                                    </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            </div>

                            <div class="field" style="margin-top:14px">
                                <label for="cm-<?= (int)$bi ?>">Review</label>
                                <textarea id="cm-<?= (int)$bi ?>" name="comment" maxlength="3000"
                                    placeholder="Share your experience..."></textarea>
                            </div>

                            <button class="btn btn-primary btn-sm" type="submit" style="margin-top:12px">
                                Submit review
                            </button>
                        </form>
                        <?php else: ?>
                        <p class="note-ok"><?= ico("check", 18) ?> You have reviewed this property.</p>
                        <?php endif; ?>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php elseif ($section === "reviews"): ?>

                <div class="page-head">
                    <h1><?= h($titles["reviews"][0]) ?></h1>
                    <p>Reviews you have submitted for completed accommodation bookings.</p>
                </div>

                <?php if (!$reviews): ?>
                <div class="empty">
                    <div class="stat-ico"><?= ico("reviews", 30) ?></div>
                    <h3>No reviews yet</h3>
                    <p>After a landlord marks a booking completed, you can submit a review from My Bookings.</p>
                    <a class="btn btn-primary" href="student.php?section=bookings">Go to my bookings</a>
                </div>
                <?php else: ?>
                <div class="stack">
                    <?php foreach ($reviews as $review): $rt = max(0, min(5, (int)$review["rating"])); ?>
                    <article class="row-card">
                        <div class="row-top">
                            <div>
                                <h3><?= h($review["title"]) ?></h3>
                                <div class="rv-stars" role="img" aria-label="<?= $rt ?> out of 5 stars">
                                    <?= str_repeat("★", $rt) ?><span class="off"><?= str_repeat("★", 5 - $rt) ?></span>
                                </div>
                            </div>
                            <span class="meta"><?= h(date("d M Y", strtotime($review["created_at"]))) ?></span>
                        </div>
                        <?php if (trim((string)$review["comment"]) !== ""): ?>
                        <p class="quote"><?= nl2br(h($review["comment"])) ?></p>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php elseif ($section === "messages"): ?>

                <div class="page-head">
                    <h1><?= h($titles["messages"][0]) ?></h1>
                    <p>Messages sent to or received from accommodation providers.</p>
                </div>

                <?php if (!$messages): ?>
                <div class="empty">
                    <div class="stat-ico"><?= ico("messages", 30) ?></div>
                    <h3>No messages yet</h3>
                    <p>Open a property listing and choose Contact landlord to start a conversation.</p>
                    <a class="btn btn-primary" href="student.php?section=discover">Find a landlord</a>
                </div>
                <?php else: ?>
                <div class="thread">
                    <?php foreach ($messages as $mi => $message): $mine = $message["sender_email"] === $studentEmail; ?>
                    <div class="msg <?= $mine ? 'mine' : '' ?>">
                        <article class="bubble">
                            <h3><?= h($message["property_title"] ?: "Accommodation enquiry") ?></h3>
                            <div class="who">
                                From: <?= h($message["sender_name"] ?: $message["sender_email"]) ?>
                                · To: <?= h($message["receiver_name"] ?: $message["receiver_email"]) ?>
                            </div>
                            <p><?= h($message["message"]) ?></p>
                            <time><?= h(date("d M Y, g:i a", strtotime($message["created_at"]))) ?></time>

                            <?php if (!$mine && $message["property_key"]): ?>
                            <form method="POST" class="reply">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="action" value="message">
                                <input type="hidden" name="property_key" value="<?= h($message["property_key"]) ?>">
                                <div class="field">
                                    <label for="rp-<?= (int)$mi ?>">Reply to this conversation</label>
                                    <textarea id="rp-<?= (int)$mi ?>" name="message" maxlength="5000" required
                                        placeholder="Write your reply..."></textarea>
                                </div>
                                <button class="btn btn-primary btn-sm" type="submit" style="margin-top:10px">
                                    Send reply
                                </button>
                            </form>
                            <?php endif; ?>
                        </article>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php elseif ($section === "profile"): ?>

                <div class="page-head">
                    <h1><?= h($titles["profile"][0]) ?></h1>
                    <p>Keep your contact information up to date.</p>
                </div>

                <div class="profile">
                    <aside class="id-card">
                        <div class="avatar" aria-hidden="true"><?= h(strtoupper(substr($student["name"], 0, 1))) ?>
                        </div>
                        <h2><?= h($student["name"]) ?></h2>
                        <p><?= h($student["email"]) ?></p>
                        <span class="role">Student</span>
                    </aside>

                    <div>
                        <section class="panel">
                            <h2>Account information</h2>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="action" value="profile">

                                <div class="form-grid">
                                    <div class="field">
                                        <label for="pf-name">Full name</label>
                                        <input id="pf-name" type="text" name="name" maxlength="100"
                                            value="<?= h($student["name"]) ?>" required>
                                    </div>
                                    <div class="field">
                                        <label for="pf-email">Email address</label>
                                        <input id="pf-email" type="email" value="<?= h($student["email"]) ?>" disabled>
                                        <small>Your email is your account's unique identifier.</small>
                                    </div>
                                    <div class="field">
                                        <label for="pf-phone">Phone number</label>
                                        <input id="pf-phone" type="tel" name="phone" maxlength="20"
                                            value="<?= h($student["phone"]) ?>">
                                    </div>
                                    <div class="field">
                                        <label for="pf-role">Account role</label>
                                        <input id="pf-role" type="text" value="Student" disabled>
                                    </div>
                                </div>

                                <button class="btn btn-primary" type="submit" style="margin-top:20px">Save
                                    changes</button>
                            </form>
                        </section>

                        <section class="panel">
                            <div class="safety">
                                <div class="stat-ico"><?= ico("check", 22) ?></div>
                                <div>
                                    <h2>Account safety</h2>
                                    <p>
                                        Never share your password with a landlord. Contact accommodation providers
                                        through UniMtaa and verify listing information before making housing decisions.
                                    </p>
                                    <a class="btn btn-danger" href="../public/logout.php">Log out</a>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <?php endif; ?>

                <div class="foot">UniMtaa · Helping students discover accommodation with greater confidence.</div>
            </div>
        </main>
    </div>

    <script>
    function toggleMsg(id, btn) {
        var box = document.getElementById(id);
        if (!box) return;
        var open = box.hasAttribute("hidden");
        if (open) {
            box.removeAttribute("hidden");
            var t = box.querySelector("textarea");
            if (t) t.focus();
        } else {
            box.setAttribute("hidden", "");
        }
        btn.setAttribute("aria-expanded", open ? "true" : "false");
    }
    </script>
</body>

</html>