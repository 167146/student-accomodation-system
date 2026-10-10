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

if (empty($_SESSION["campusnest_csrf"])) {
    $_SESSION["campusnest_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["campusnest_csrf"];

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
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | UniMtaa</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
    :root {
        --purple: #6d28d9;
        --purple-dark: #35105f;
        --purple-mid: #8b5cf6;
        --purple-light: #f3edff;
        --page: #faf8ff;
        --ink: #26183c;
        --muted: #786e89;
        --line: #e9e1f3;
        --white: #fff;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        background: var(--page);
        color: var(--ink);
        font-family: Inter, Segoe UI, Arial, sans-serif;
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
    }

    .app {
        display: flex;
        align-items: stretch;
        width: 100%;
        min-height: 100vh;
    }

    .sidebar {
        width: 260px;
        min-width: 260px;
        flex: 0 0 260px;
        min-height: 100vh;
        background: var(--purple-dark);
        color: white;
        padding: 26px 16px;
        display: flex;
        flex-direction: column;
        position: relative;
        z-index: 2;
    }



    .brand {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 10px 30px;
    }

    .brand-icon {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        background: #8b5cf6;
        display: grid;
        place-items: center;
        font-size: 22px;
    }

    .brand-name {
        font-size: 21px;
        font-weight: 800;
    }

    .brand-subtitle {
        display: block;
        color: #d8c8f4;
        font-size: 11px;
        margin-top: 3px;
    }

    .nav-label {
        color: #bda9df;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1.4px;
        padding: 10px 13px;
    }

    .nav-link {
        display: flex;
        gap: 12px;
        align-items: center;
        padding: 12px 13px;
        border-radius: 12px;
        margin: 3px 0;
        color: #e9ddff;
        font-size: 13px;
    }

    .nav-link:hover,
    .nav-link.active {
        background: #59318c;
        color: white;
    }

    .nav-icon {
        width: 20px;
        text-align: center;
    }

    .sidebar-bottom {
        margin-top: auto;
    }

    .sidebar-user {
        border-top: 1px solid #573c76;
        padding: 20px 8px 5px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #a78bfa;
        display: grid;
        place-items: center;
        color: #301050;
        font-weight: 800;
    }

    .sidebar-user small {
        color: #d8c8f4;
        display: block;
        margin-top: 4px;
    }

    .main {
        flex: 1 1 0;
        width: 0;
        min-width: 0;
        margin: 0;
        padding: 0;
        position: relative;
    }


    .topbar {
        min-height: 76px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 32px;
        background: white;
        border-bottom: 1px solid var(--line);
        gap: 15px;
    }

    .topbar h1 {
        font-size: 20px;
        margin: 0;
    }

    .topbar p {
        color: var(--muted);
        font-size: 12px;
        margin: 5px 0 0;
    }

    .top-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .content {
        padding: 30px 32px;
        max-width: 1500px;
        margin: auto;
    }

    .welcome {
        border-radius: 22px;
        padding: 30px;
        color: white;
        background: linear-gradient(120deg, #4c1d95, #7c3aed 65%, #a78bfa);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        overflow: hidden;
    }

    .welcome h2 {
        font-size: 27px;
        margin: 0 0 10px;
    }

    .welcome p {
        color: #eee5ff;
        font-size: 13px;
        line-height: 1.7;
        max-width: 520px;
        margin: 0;
    }

    .welcome-mark {
        font-size: 70px;
        opacity: .9;
        padding: 0 15px;
    }

    .stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
        margin: 22px 0 30px;
    }

    .stat,
    .panel,
    .property-card,
    .booking-card,
    .review-card,
    .message-card {
        background: white;
        border: 1px solid var(--line);
        border-radius: 17px;
    }

    .stat {
        padding: 20px;
        display: flex;
        gap: 14px;
        align-items: center;
    }

    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 13px;
        background: var(--purple-light);
        display: grid;
        place-items: center;
        font-size: 21px;
    }

    .stat small {
        display: block;
        color: var(--muted);
        font-size: 12px;
    }

    .stat strong {
        display: block;
        font-size: 24px;
        margin-top: 5px;
    }

    .section-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        margin: 28px 0 16px;
    }

    .section-heading h2 {
        margin: 0;
        font-size: 19px;
    }

    .section-heading p {
        color: var(--muted);
        font-size: 12px;
        margin: 6px 0 0;
    }

    .filters {
        background: white;
        border: 1px solid var(--line);
        border-radius: 15px;
        padding: 16px;
        display: grid;
        grid-template-columns: 2fr 1.3fr 1fr auto;
        gap: 10px;
        margin: 18px 0;
    }

    .field label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 7px;
    }

    .field input,
    .field textarea,
    .field select {
        width: 100%;
        padding: 11px 12px;
        border: 1px solid var(--line);
        border-radius: 10px;
        outline: none;
        background: white;
        color: var(--ink);
    }

    .field input:focus,
    .field textarea:focus,
    .field select:focus {
        border-color: var(--purple-mid);
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border: 0;
        border-radius: 10px;
        padding: 11px 15px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 700;
        transition: .15s;
    }

    .btn-primary {
        background: var(--purple);
        color: white;
    }

    .btn-primary:hover {
        background: #5b21b6;
    }

    .btn-light {
        background: var(--purple-light);
        color: var(--purple);
    }

    .btn-outline {
        border: 1px solid var(--line);
        color: var(--ink);
        background: white;
    }

    .btn-danger {
        background: #fff0f0;
        color: #b42318;
    }

    .btn-small {
        padding: 8px 10px;
        font-size: 11px;
    }

    .property-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }

    .property-card {
        overflow: hidden;
    }

    .property-visual {
        min-height: 115px;
        padding: 18px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        background: linear-gradient(135deg, #eee4ff, #d8c2ff);
    }

    .property-visual span:first-child {
        font-size: 38px;
    }

    .property-body {
        padding: 18px;
    }

    .property-body h3 {
        margin: 0 0 8px;
        font-size: 16px;
    }

    .property-location {
        color: var(--muted);
        font-size: 12px;
        line-height: 1.6;
    }

    .price {
        color: var(--purple);
        font-size: 21px;
        font-weight: 800;
        margin: 15px 0 8px;
    }

    .price small {
        font-size: 11px;
        color: var(--muted);
        font-weight: 400;
    }

    .amenities {
        font-size: 11px;
        color: var(--muted);
        line-height: 1.7;
        margin: 10px 0 15px;
    }

    .property-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .badge {
        display: inline-flex;
        border-radius: 30px;
        padding: 6px 9px;
        font-size: 10px;
        font-weight: 700;
        text-transform: capitalize;
    }

    .good {
        background: #dcfce7;
        color: #166534;
    }

    .waiting {
        background: #fef3c7;
        color: #92400e;
    }

    .bad {
        background: #fee2e2;
        color: #991b1b;
    }

    .panel {
        padding: 22px;
        margin-bottom: 18px;
    }

    .panel h2 {
        margin: 0 0 16px;
        font-size: 17px;
    }

    .stack {
        display: grid;
        gap: 14px;
    }

    .booking-card,
    .review-card,
    .message-card {
        padding: 19px;
    }

    .card-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 15px;
    }

    .card-row h3 {
        margin: 0 0 7px;
        font-size: 15px;
    }

    .muted {
        color: var(--muted);
        font-size: 12px;
        line-height: 1.7;
    }

    .inline-form {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .review-form {
        border-top: 1px solid var(--line);
        padding-top: 15px;
        margin-top: 15px;
    }

    .review-form textarea {
        min-height: 75px;
        resize: vertical;
    }

    .message-card p {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    .alert {
        padding: 13px 15px;
        border-radius: 12px;
        margin-bottom: 18px;
        font-size: 13px;
    }

    .alert-error {
        background: #fee2e2;
        color: #991b1b;
    }

    .alert-success {
        background: #dcfce7;
        color: #166534;
    }

    .empty {
        padding: 35px 20px;
        text-align: center;
        background: white;
        border: 1px dashed #d8c8f0;
        border-radius: 16px;
    }

    .empty-icon {
        font-size: 36px;
        margin-bottom: 10px;
    }

    .empty h3 {
        margin: 5px 0 8px;
    }

    .empty p {
        color: var(--muted);
        font-size: 12px;
        line-height: 1.7;
    }

    .profile-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .mobile-menu {
        display: none;
    }

    .footer-note {
        color: var(--muted);
        font-size: 11px;
        margin-top: 25px;
        text-align: center;
    }

    @media(max-width:1100px) {
        .property-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .filters {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media(max-width:760px) {
        .app {
            display: block;
        }

        .sidebar {
            width: 100%;
            padding: 13px;
        }

        .brand {
            padding: 0 7px 12px;
        }

        .nav-label,
        .sidebar-bottom {
            display: none;
        }

        .nav-links {
            display: flex;
            overflow-x: auto;
            gap: 5px;
        }

        .nav-link {
            white-space: nowrap;
            padding: 10px;
        }

        .topbar {
            padding: 16px;
        }

        .content {
            padding: 17px;
        }

        .welcome {
            padding: 22px;
        }

        .welcome h2 {
            font-size: 22px;
        }

        .welcome-mark {
            display: none;
        }

        .stats {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .property-grid {
            grid-template-columns: 1fr;
        }

        .filters {
            grid-template-columns: 1fr;
        }

        .profile-grid {
            grid-template-columns: 1fr;
        }
    }

    /* =========================================
   UNIMTAA DASHBOARD LAYOUT FIX
   ========================================= */



    /* Keep sidebar links vertical, not in a row */
    .sidebar nav.nav-links {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        justify-content: flex-start;
        width: 100%;
        gap: 5px;
        overflow: visible;
    }

    .sidebar .nav-link {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: flex-start;
        width: 100%;
        min-width: 0;
        gap: 12px;
        padding: 13px 14px;
        margin: 0;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .sidebar .nav-icon {
        flex: 0 0 20px;
        width: 20px;
    }

    /* Keep the main area beside the sidebar */
    .main {
        flex: 1 1 auto;
        width: calc(100% - 270px);
        min-width: 0;
        max-width: calc(100% - 270px);
        overflow-x: clip;
    }

    .topbar {
        width: 100%;
        min-width: 0;
        padding: 20px 30px;
    }

    .content {
        width: 100%;
        max-width: 1500px;
        margin: 0 auto;
        padding: 30px;
        min-width: 0;
    }

    /* Prevent cards and grids from overflowing */
    .stats,
    .property-grid,
    .profile-grid,
    .filters {
        width: 100%;
        min-width: 0;
    }

    .stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 20px;
    }

    .stat,
    .property-card,
    .panel {
        min-width: 0;
    }

    /* Responsive layout for smaller screens */
    @media (max-width: 900px) {
        .sidebar {
            width: 220px;
            min-width: 220px;
            max-width: 220px;
            flex-basis: 220px;
            padding: 22px 12px;
        }

        .main {
            width: calc(100% - 220px);
            max-width: calc(100% - 220px);
        }

        .content {
            padding: 20px;
        }

        .stats {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 600px) {
        .app {
            flex-direction: column;
        }

        .sidebar {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            min-height: auto;
            flex-basis: auto;
        }

        .sidebar nav.nav-links {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .sidebar-bottom {
            margin-top: 20px;
        }

        .main {
            width: 100%;
            max-width: 100%;
        }

        .topbar {
            padding: 18px;
        }

        .content {
            padding: 16px;
        }

        .welcome {
            padding: 22px;
        }

        .stats {
            grid-template-columns: 1fr;
        }
    }

    /* ===== UniMtaa student dashboard layout correction ===== */

    * {
        box-sizing: border-box;
    }

    html,
    body {
        margin: 0;
        padding: 0;
        width: 100%;
        min-height: 100%;
    }

    body {
        overflow-x: hidden;
    }

    .app {
        display: grid !important;
        grid-template-columns: 270px minmax(0, 1fr) !important;
        align-items: stretch;
        width: 100%;
        min-height: 100vh;
        margin: 0;
        padding: 0;
    }

    .sidebar {
        grid-column: 1;
        grid-row: 1;
        position: relative !important;
        left: auto !important;
        top: auto !important;
        width: 270px !important;
        min-width: 0;
        max-width: none;
        min-height: 100vh;
        height: auto;
        margin: 0;
        z-index: 2;
    }

    .main {
        grid-column: 2;
        grid-row: 1;
        width: 100% !important;
        min-width: 0;
        max-width: none;
        margin: 0 !important;
        padding: 0;
        position: relative;
        left: auto !important;
        transform: none !important;
    }

    .topbar {
        width: 100%;
        min-width: 0;
        margin: 0;
    }

    .content {
        width: 100%;
        max-width: none;
        min-width: 0;
        margin: 0;
        padding: 30px;
    }

    .sidebar nav.nav-links {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        width: 100%;
        gap: 6px;
    }

    .sidebar .nav-link {
        display: flex;
        align-items: center;
        width: 100%;
        min-width: 0;
        white-space: normal;
    }

    @media (max-width: 760px) {
        .app {
            grid-template-columns: 1fr !important;
        }

        .sidebar {
            grid-column: 1;
            grid-row: 1;
            width: 100% !important;
            min-height: auto;
        }

        .main {
            grid-column: 1;
            grid-row: 2;
            width: 100% !important;
        }

        .content {
            padding: 18px;
        }
    }
    </style>
</head>

<body>
    <div class="app">

        <aside class="sidebar">
            <a class="brand" href="student.php">
                <div class="brand-icon">🏠</div>
                <div>
                    <div class="brand-name">CampusNest</div>
                    <span class="brand-subtitle">Student Accommodation</span>
                </div>
            </a>

            <div class="nav-label">Student workspace</div>
            <nav class="nav-links">
                <a class="nav-link <?= $section === 'discover' ? 'active' : '' ?>" href="student.php?section=discover">
                    <span class="nav-icon">⌂</span> Discover Homes
                </a>
                <a class="nav-link <?= $section === 'bookings' ? 'active' : '' ?>" href="student.php?section=bookings">
                    <span class="nav-icon">▣</span> My Bookings
                </a>
                <a class="nav-link <?= $section === 'reviews' ? 'active' : '' ?>" href="student.php?section=reviews">
                    <span class="nav-icon">☆</span> My Reviews
                </a>
                <a class="nav-link <?= $section === 'messages' ? 'active' : '' ?>" href="student.php?section=messages">
                    <span class="nav-icon">✉</span> Messages
                </a>
                <a class="nav-link <?= $section === 'profile' ? 'active' : '' ?>" href="student.php?section=profile">
                    <span class="nav-icon">◉</span> My Profile
                </a>
            </nav>

            <div class="sidebar-bottom">
                <div class="sidebar-user">
                    <div class="avatar"><?= h(strtoupper(substr($studentName, 0, 1))) ?></div>
                    <div>
                        <strong><?= h($studentName) ?></strong>
                        <small>Student account</small>
                    </div>
                </div>
                <a class="nav-link" href="../public/logout.php">
                    <span class="nav-icon">↪</span> Log out
                </a>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div>
                    <h1>
                        <?php
                    $titles = [
                        "discover" => "Discover accommodation",
                        "bookings" => "My bookings",
                        "reviews" => "My reviews",
                        "messages" => "Messages",
                        "profile" => "My profile"
                    ];
                    echo h($titles[$section]);
                    ?>
                    </h1>
                    <p>Your student accommodation space, all in one place.</p>
                </div>
                <div class="top-actions">
                    <a class="btn btn-light" href="student.php?section=profile">
                        ◉ My account
                    </a>
                </div>
            </header>

            <div class="content">

                <?php if ($error !== ""): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
                <?php endif; ?>

                <?php if ($success !== ""): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
                <?php endif; ?>

                <?php if ($section === "discover"): ?>

                <section class="welcome">
                    <div>
                        <h2>Hello, <?= h(explode(" ", trim($studentName))[0]) ?>! 💜</h2>
                        <p>
                            Your next home is waiting. Explore available accommodation,
                            compare your options, and contact landlords directly.
                        </p>
                        <div style="margin-top:20px">
                            <a href="#available-homes" class="btn" style="background:white;color:#5b21b6">
                                Explore available homes →
                            </a>
                        </div>
                    </div>
                    <div class="welcome-mark">🏡</div>
                </section>

                <div class="stats">
                    <div class="stat">
                        <div class="stat-icon">🏠</div>
                        <div>
                            <small>Available properties</small>
                            <strong><?= $availableCount ?></strong>
                        </div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon">▣</div>
                        <div>
                            <small>Active bookings</small>
                            <strong><?= $activeBookings ?></strong>
                        </div>
                    </div>
                    <div class="stat">
                        <div class="stat-icon">☆</div>
                        <div>
                            <small>Reviews submitted</small>
                            <strong><?= $reviewCount ?></strong>
                        </div>
                    </div>
                </div>

                <div class="section-heading" id="available-homes">
                    <div>
                        <h2>Find your next home</h2>
                        <p>Search by property, location, amenities or budget.</p>
                    </div>
                </div>

                <form class="filters" method="GET" action="student.php#available-homes">
                    <input type="hidden" name="section" value="discover">

                    <div class="field">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="e.g. bedsitter, Wi-Fi, furnished"
                            value="<?= h($search) ?>">
                    </div>

                    <div class="field">
                        <label>Location</label>
                        <input type="text" name="location" placeholder="e.g. Madaraka" value="<?= h($location) ?>">
                    </div>

                    <div class="field">
                        <label>Maximum rent (KSh)</label>
                        <input type="number" name="max_price" min="0" step="0.01" placeholder="e.g. 15000"
                            value="<?= h($maxPrice) ?>">
                    </div>

                    <div style="display:flex;align-items:end;gap:7px">
                        <button class="btn btn-primary" type="submit">Search</button>
                        <a class="btn btn-outline" href="student.php?section=discover">
                            Reset
                        </a>
                    </div>
                </form>

                <?php if (!$properties): ?>
                <div class="empty">
                    <div class="empty-icon">🏡</div>
                    <h3>No available properties found</h3>
                    <p>
                        Try another search or check again later.
                        Properties under review or marked unavailable are not shown here.
                    </p>
                </div>
                <?php else: ?>
                <div class="property-grid">
                    <?php foreach ($properties as $property): ?>
                    <article class="property-card">
                        <div class="property-visual">
                            <span>🏡</span>
                            <span class="badge good">Available</span>
                        </div>

                        <div class="property-body">
                            <h3><?= h($property["title"]) ?></h3>

                            <div class="property-location">
                                📍 <?= h($property["location_name"] ?: $property["address"]) ?>
                            </div>

                            <div class="price">
                                KSh <?= number_format((float)$property["price"], 2) ?>
                                <small>/ rental price</small>
                            </div>

                            <div class="muted">
                                <?= h(mb_strimwidth(
                                            $property["description"],
                                            0,
                                            170,
                                            "..."
                                        )) ?>
                            </div>

                            <?php if (!empty($property["amenities"])): ?>
                            <div class="amenities">
                                <strong>Amenities:</strong>
                                <?= h($property["amenities"]) ?>
                            </div>
                            <?php endif; ?>

                            <div class="muted" style="margin-bottom:13px">
                                ⭐
                                <?php if ($property["average_rating"] !== null): ?>
                                <?= h($property["average_rating"]) ?>/5
                                · <?= (int)$property["review_total"] ?> review(s)
                                <?php else: ?>
                                Not yet rated
                                <?php endif; ?>
                            </div>

                            <div class="property-actions">
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

                                <button type="button" class="btn btn-light"
                                    onclick="document.getElementById('message-<?= h($property["property_key"]) ?>').style.display='block'">
                                    Contact landlord
                                </button>
                            </div>

                            <form id="message-<?= h($property["property_key"]) ?>" method="POST" class="review-form"
                                style="display:none">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="action" value="message">
                                <input type="hidden" name="property_key" value="<?= h($property["property_key"]) ?>">

                                <div class="field">
                                    <label>Message the landlord</label>
                                    <textarea name="message" required maxlength="5000"
                                        placeholder="Ask about availability, amenities or viewing..."></textarea>
                                </div>

                                <button class="btn btn-primary" type="submit" style="margin-top:10px">
                                    Send message
                                </button>
                            </form>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php elseif ($section === "bookings"): ?>

                <div class="section-heading">
                    <div>
                        <h2>Your booking history</h2>
                        <p>Track requests and see the latest status of each booking.</p>
                    </div>
                    <a class="btn btn-light" href="student.php?section=discover">
                        + Find a home
                    </a>
                </div>

                <?php if (!$bookings): ?>
                <div class="empty">
                    <div class="empty-icon">▣</div>
                    <h3>No bookings yet</h3>
                    <p>When you request accommodation, it will appear here.</p>
                    <a class="btn btn-primary" href="student.php?section=discover">
                        Browse homes
                    </a>
                </div>
                <?php else: ?>
                <div class="stack">
                    <?php foreach ($bookings as $booking): ?>
                    <article class="booking-card">
                        <div class="card-row">
                            <div>
                                <h3><?= h($booking["title"]) ?></h3>
                                <div class="muted">
                                    📍 <?= h($booking["location_name"] ?: $booking["address"]) ?>
                                </div>
                                <div class="muted">
                                    Requested:
                                    <?= h(date("d M Y, g:i a", strtotime($booking["booking_date"]))) ?>
                                </div>
                                <div class="price">
                                    KSh <?= number_format((float)$booking["price"], 2) ?>
                                </div>
                            </div>

                            <span class="badge <?= statusClass($booking["status"]) ?>">
                                <?= h($booking["status"]) ?>
                            </span>
                        </div>

                        <div class="inline-form" style="margin-top:15px">
                            <?php if ($booking["status"] === "pending"): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="action" value="cancel_booking">
                                <input type="hidden" name="property_key" value="<?= h($booking["property_key"]) ?>">
                                <button class="btn btn-danger btn-small" type="submit"
                                    onclick="return confirm('Cancel this pending booking request?')">
                                    Cancel request
                                </button>
                            </form>
                            <?php endif; ?>

                            <a class="btn btn-light btn-small" href="student.php?section=discover">
                                Browse other properties
                            </a>
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
                                <label>Your rating</label>
                                <select name="rating" required>
                                    <option value="">Choose a rating</option>
                                    <option value="5">★★★★★ Excellent</option>
                                    <option value="4">★★★★☆ Good</option>
                                    <option value="3">★★★☆☆ Average</option>
                                    <option value="2">★★☆☆☆ Poor</option>
                                    <option value="1">★☆☆☆☆ Very poor</option>
                                </select>
                            </div>

                            <div class="field" style="margin-top:12px">
                                <label>Review</label>
                                <textarea name="comment" maxlength="3000"
                                    placeholder="Share your experience..."></textarea>
                            </div>

                            <button class="btn btn-primary" type="submit" style="margin-top:10px">
                                Submit review
                            </button>
                        </form>
                        <?php else: ?>
                        <p class="muted">You have reviewed this property.</p>
                        <?php endif; ?>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php elseif ($section === "reviews"): ?>

                <div class="section-heading">
                    <div>
                        <h2>Your feedback</h2>
                        <p>Reviews you have submitted for completed accommodation bookings.</p>
                    </div>
                </div>

                <?php if (!$reviews): ?>
                <div class="empty">
                    <div class="empty-icon">☆</div>
                    <h3>No reviews yet</h3>
                    <p>After a landlord marks a booking completed, you can submit a review from My Bookings.</p>
                </div>
                <?php else: ?>
                <div class="stack">
                    <?php foreach ($reviews as $review): ?>
                    <article class="review-card">
                        <div class="card-row">
                            <div>
                                <h3><?= h($review["title"]) ?></h3>
                                <div style="color:#8b5cf6;margin:9px 0">
                                    <?= str_repeat("★", (int)$review["rating"]) ?><?= str_repeat("☆", 5 - (int)$review["rating"]) ?>
                                </div>
                            </div>
                            <span class="muted">
                                <?= h(date("d M Y", strtotime($review["created_at"]))) ?>
                            </span>
                        </div>
                        <p class="muted"><?= nl2br(h($review["comment"])) ?></p>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php elseif ($section === "messages"): ?>

                <div class="section-heading">
                    <div>
                        <h2>Your conversations</h2>
                        <p>Messages sent to or received from accommodation providers.</p>
                    </div>
                    <a class="btn btn-light" href="student.php?section=discover">
                        Find a landlord
                    </a>
                </div>

                <?php if (!$messages): ?>
                <div class="empty">
                    <div class="empty-icon">✉</div>
                    <h3>No messages yet</h3>
                    <p>Open a property listing and choose Contact landlord to start a conversation.</p>
                </div>
                <?php else: ?>
                <div class="stack">
                    <?php foreach ($messages as $message): ?>
                    <article class="message-card">
                        <div class="card-row">
                            <div>
                                <h3><?= h($message["property_title"] ?: "Accommodation enquiry") ?></h3>
                                <div class="muted">
                                    From: <?= h($message["sender_name"] ?: $message["sender_email"]) ?>
                                    · To: <?= h($message["receiver_name"] ?: $message["receiver_email"]) ?>
                                </div>
                            </div>
                            <span class="muted">
                                <?= h(date("d M Y, g:i a", strtotime($message["created_at"]))) ?>
                            </span>
                        </div>
                        <p><?= h($message["message"]) ?></p>

                        <?php if ($message["sender_email"] !== $studentEmail && $message["property_key"]): ?>
                        <form method="POST" class="review-form">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                            <input type="hidden" name="action" value="message">
                            <input type="hidden" name="property_key" value="<?= h($message["property_key"]) ?>">
                            <div class="field">
                                <label>Reply to this conversation</label>
                                <textarea name="message" maxlength="5000" required
                                    placeholder="Write your reply..."></textarea>
                            </div>
                            <button class="btn btn-primary" type="submit" style="margin-top:10px">
                                Send reply
                            </button>
                        </form>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php elseif ($section === "profile"): ?>

                <div class="section-heading">
                    <div>
                        <h2>Account information</h2>
                        <p>Keep your contact information up to date.</p>
                    </div>
                </div>

                <section class="panel">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="action" value="profile">

                        <div class="profile-grid">
                            <div class="field">
                                <label>Full name</label>
                                <input type="text" name="name" maxlength="100" value="<?= h($student["name"]) ?>"
                                    required>
                            </div>
                            <div class="field">
                                <label>Email address</label>
                                <input type="email" value="<?= h($student["email"]) ?>" disabled>
                                <small class="muted">Your email is your account's unique identifier.</small>
                            </div>
                            <div class="field">
                                <label>Phone number</label>
                                <input type="tel" name="phone" maxlength="20" value="<?= h($student["phone"]) ?>">
                            </div>
                            <div class="field">
                                <label>Account role</label>
                                <input type="text" value="Student" disabled>
                            </div>
                        </div>

                        <button class="btn btn-primary" type="submit" style="margin-top:18px">
                            Save changes
                        </button>
                    </form>
                </section>

                <section class="panel">
                    <h2>Account safety</h2>
                    <p class="muted">
                        Never share your password with a landlord. Contact accommodation
                        providers through CampusNest and verify listing information before
                        making housing decisions.
                    </p>
                    <a class="btn btn-danger" href="../public/logout.php">Log out</a>
                </section>

                <?php endif; ?>

                <div class="footer-note">
                    CampusNest · Helping students discover accommodation with greater confidence.
                </div>
            </div>
        </main>
    </div>
</body>

</html>