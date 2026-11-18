<?php
session_start();

if (
    empty($_SESSION["email"]) ||
    ($_SESSION["role"] ?? "") !== "landlord"
) {
    header("Location: ../public/landlord_login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

date_default_timezone_set("Africa/Nairobi");

$landlordEmail = $_SESSION["email"];
$name = $_SESSION["name"] ?? "Landlord";
$landlordName = $name;
$error = "";

if (empty($_SESSION["ll_csrf"])) {
    $_SESSION["ll_csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["ll_csrf"];

function h($v)
{
    return htmlspecialchars((string)($v ?? ""), ENT_QUOTES, "UTF-8");
}

/* ------------------------------------------------------------------
 * Send a reply to a student about one of this landlord's properties
 * ------------------------------------------------------------------ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        if (!hash_equals($csrf, $_POST["csrf_token"] ?? "")) {
            throw new Exception("Your session expired. Refresh the page and try again.");
        }

        $propertyKey = trim($_POST["property_key"] ?? "");
        $studentEmail = trim($_POST["student_email"] ?? "");
        $text = trim($_POST["message"] ?? "");

        if ($text === "" || strlen($text) > 5000) {
            throw new Exception("Enter a reply of up to 5,000 characters.");
        }

        // The property must belong to this landlord.
        $stmt = $pdo->prepare(
            "SELECT property_key FROM properties
             WHERE property_key = ? AND landlord_email = ? LIMIT 1"
        );
        $stmt->execute([$propertyKey, $landlordEmail]);
        if (!$stmt->fetch()) {
            throw new Exception("That property was not found in your listings.");
        }

        // Replies are only allowed to students who have written about it.
        $stmt = $pdo->prepare(
            "SELECT message_key FROM messages
             WHERE property_key = ? AND sender_email = ? AND receiver_email = ?
             LIMIT 1"
        );
        $stmt->execute([$propertyKey, $studentEmail, $landlordEmail]);
        if (!$stmt->fetch()) {
            throw new Exception("You can only reply to students who have messaged you.");
        }

        $stmt = $pdo->prepare(
            "INSERT INTO messages
             (message_key, sender_email, receiver_email, property_key, message, is_read)
             VALUES (?, ?, ?, ?, ?, 0)"
        );
        $stmt->execute([
            bin2hex(random_bytes(32)),
            $landlordEmail,
            $studentEmail,
            $propertyKey,
            $text,
        ]);

        header("Location: landlord_messages.php#t-" . md5($propertyKey . $studentEmail));
        exit;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $error = "The reply could not be sent. Please try again.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

/* ------------------------------------------------------------------
 * Load every message sent to or from this landlord, then group them
 * into one conversation per property and student.
 * ------------------------------------------------------------------ */
$threads = [];
$unreadTotal = 0;

try {
    $stmt = $pdo->prepare(
        "SELECT m.message, m.sender_email, m.receiver_email, m.property_key,
                m.is_read, m.created_at,
                p.title AS property_title,
                s.name AS sender_name, r.name AS receiver_name
         FROM messages m
         LEFT JOIN properties p ON p.property_key = m.property_key
         LEFT JOIN users s ON s.email = m.sender_email
         LEFT JOIN users r ON r.email = m.receiver_email
         WHERE m.sender_email = ? OR m.receiver_email = ?
         ORDER BY m.created_at ASC"
    );
    $stmt->execute([$landlordEmail, $landlordEmail]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $mine = $m["sender_email"] === $landlordEmail;
        $student = $mine ? $m["receiver_email"] : $m["sender_email"];
        $studentName = $mine ? $m["receiver_name"] : $m["sender_name"];
        $key = $m["property_key"] . "|" . $student;

        if (!isset($threads[$key])) {
            $threads[$key] = [
                "id" => md5($m["property_key"] . $student),
                "property_key" => $m["property_key"],
                "property_title" => $m["property_title"] ?: "Accommodation enquiry",
                "student_email" => $student,
                "student_name" => $studentName ?: $student,
                "messages" => [],
                "unread" => 0,
                "last" => $m["created_at"],
                "can_reply" => false,
            ];
        }

        $isNew = !$mine && (int)$m["is_read"] === 0;
        if ($isNew) {
            $threads[$key]["unread"]++;
            $unreadTotal++;
        }
        if (!$mine) {
            $threads[$key]["can_reply"] = true;
        }

        $threads[$key]["messages"][] = [
            "mine" => $mine,
            "text" => $m["message"],
            "time" => $m["created_at"],
            "new" => $isNew,
        ];
        $threads[$key]["last"] = $m["created_at"];
    }

    // Mark everything received as read now that it is on screen.
    $stmt = $pdo->prepare(
        "UPDATE messages SET is_read = 1
         WHERE receiver_email = ? AND is_read = 0"
    );
    $stmt->execute([$landlordEmail]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = $error ?: "Messages could not be loaded. Please check the messages table.";
}

// Newest conversation first.
uasort($threads, fn($a, $b) => strcmp($b["last"], $a["last"]));

$navUnread = 0;

$icons = [
    "house"    => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
    "plus"     => '<path d="M12 5v14M5 12h14"/>',
    "list"     => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
    "chat"     => '<path d="M4 5h16v11H9l-5 4z"/>',
    "logout"   => '<path d="M9 4H5v16h4"/><path d="M16 8l4 4-4 4M20 12H9"/>',
    "pin"      => '<path d="M12 21s7-6.2 7-11a7 7 0 10-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
    "shield"   => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
    "check"    => '<path d="M5 12l5 5 9-10"/>',
    "camera"   => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
];
function ico($name, $size = 20)
{
    global $icons;
    return '<svg class="ico" width="' . (int)$size . '" height="' . (int)$size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($icons[$name] ?? "") . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | UniMtaa</title>
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

    .btn-white {
        background: #fff;
        color: var(--p2);
    }

    .btn-white:hover {
        background: var(--p6);
    }

    .btn-ghost-w {
        background: rgba(255, 255, 255, .14);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, .3);
    }

    .hero-cta {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 22px;
    }

    .stats.four {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .stat b.small {
        font-size: 18px;
    }

    .next {
        display: flex;
        align-items: center;
        gap: 14px;
        margin: 26px 0 0;
        padding: 16px 20px;
        border-radius: 20px;
        background: linear-gradient(120deg, var(--p6), #fff);
        border: 1px solid var(--p5);
    }

    .next .stat-ico {
        background: var(--p3);
        color: #fff;
    }

    .next strong {
        display: block;
        color: var(--p1);
    }

    .next span.t {
        color: var(--muted);
        font-size: 13.5px;
    }

    .next .btn {
        margin-left: auto;
    }

    .chips {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin: 0 0 20px;
    }

    .chip {
        padding: 9px 16px;
        border-radius: 99px;
        border: 1.5px solid var(--line);
        background: #fff;
        font-weight: 700;
        font-size: 13px;
        color: var(--muted);
        transition: background .15s, color .15s;
    }

    .chip:hover {
        background: var(--p6);
        color: var(--p2);
    }

    .chip.on {
        background: var(--p3);
        color: #fff;
        border-color: var(--p3);
    }

    .chip b {
        margin-left: 6px;
        opacity: .8;
    }

    .pill.st {
        top: 14px;
        left: 14px;
    }

    .pill.st-approved {
        background: rgba(255, 255, 255, .94);
        color: var(--good);
    }

    .pill.st-pending {
        background: #FEF3C7;
        color: #92400E;
    }

    .pill.st-rejected {
        background: #FEE2E2;
        color: #991B1B;
    }

    .pill.cnt {
        right: 14px;
        top: 14px;
        background: rgba(31, 18, 54, .55);
        color: #fff;
    }

    .meta-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
        margin: 4px 0 14px;
    }

    .tools {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }

    .tool {
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 22px;
        padding: 24px;
        box-shadow: var(--shadow);
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .tool h3 {
        margin: 6px 0 0;
        font-size: 17px;
        color: var(--p1);
    }

    .tool p {
        margin: 0 0 8px;
        color: var(--muted);
        font-size: 13.5px;
        flex: 1;
    }

    .tool .btn {
        align-self: flex-start;
    }

    .acct {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }

    .acct small {
        display: block;
        color: var(--muted);
        font-size: 11.5px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .acct div div {
        margin-top: 4px;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .note {
        padding: 14px 18px;
        border-radius: 16px;
        background: var(--p6);
        color: var(--p2);
        margin-bottom: 20px;
        font-weight: 600;
    }

    .conv {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 22px;
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .conv+.conv {
        margin-top: 18px;
    }

    .conv-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        padding: 18px 22px;
        background: var(--p7);
        border-bottom: 1px solid var(--line);
    }

    .conv-head h2 {
        margin: 0;
        font-size: 17px;
        color: var(--p1);
    }

    .conv-head small {
        color: var(--muted);
    }

    .new-badge {
        background: var(--p3);
        color: #fff;
        padding: 4px 11px;
        border-radius: 99px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .chat {
        display: grid;
        gap: 10px;
        padding: 20px 22px;
    }

    .crow {
        display: flex;
    }

    .crow.mine {
        justify-content: flex-end;
    }

    .cb {
        max-width: 80%;
        padding: 12px 16px;
        border-radius: 18px 18px 18px 5px;
        background: var(--p6);
        overflow-wrap: anywhere;
    }

    .crow.mine .cb {
        border-radius: 18px 18px 5px 18px;
        background: var(--p3);
        color: #fff;
    }

    .cb.fresh {
        box-shadow: 0 0 0 3px var(--p5);
    }

    .cb .txt {
        white-space: pre-wrap;
    }

    .cb time {
        display: block;
        margin-top: 6px;
        font-size: 11.5px;
        opacity: .7;
    }

    form.replyf {
        padding: 0 22px 22px;
    }

    form.replyf .btn {
        margin-top: 10px;
    }

    @media (max-width: 1100px) {
        .stats.four {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .tools,
        .acct {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 900px) {
        .next {
            flex-wrap: wrap;
        }

        .next .btn {
            margin-left: 0;
        }

        .cb {
            max-width: 92%;
        }
    }

    @media (max-width: 480px) {
        .stats.four {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>

<body>
    <a class="skip" href="#main">Skip to main content</a>

    <div class="app">

        <aside class="side" aria-label="Landlord navigation">
            <a class="brand" href="landlord.php">
                <span class="brand-mark"><?= ico("house", 24) ?></span>
                <span>
                    <span class="brand-name">UniMtaa</span>
                    <span class="brand-sub">Landlord Workspace</span>
                </span>
            </a>

            <div class="nav-label">Manage accommodation</div>
            <nav class="nav" aria-label="Sections">
                <a href="landlord.php">
                    <?= ico("house") ?>
                    Dashboard
                </a>
                <a href="../properties/add.php">
                    <?= ico("plus") ?>
                    Add Property
                </a>
                <a href="../properties/my-properties.php">
                    <?= ico("list") ?>
                    My Properties
                </a>
                <a href="landlord_messages.php" class="active" aria-current="page">
                    <?= ico("chat") ?>
                    Messages
                    <?php if ($navUnread > 0): ?>
                    <span class="dot" aria-label="<?= (int)$navUnread ?> unread"><?= (int)$navUnread ?></span>
                    <?php endif; ?>
                </a>
            </nav>

            <div class="side-foot">
                <div class="me">
                    <div class="avatar" aria-hidden="true"><?= h(strtoupper(substr($name, 0, 1))) ?></div>
                    <div>
                        <strong><?= h($name) ?></strong>
                        <small>Landlord account</small>
                    </div>
                </div>
                <a class="logout" href="../public/logout.php"><?= ico("logout", 18) ?> Log out</a>
            </div>
        </aside>

        <main class="main" id="main">

            <div class="mobile-top">
                <a class="b" href="landlord.php">
                    <span class="brand-mark"
                        style="background:linear-gradient(135deg,var(--p4),var(--p3))"><?= ico("house", 20) ?></span>
                    UniMtaa
                </a>
                <a class="btn btn-soft btn-sm" href="../public/logout.php"><?= ico("logout", 16) ?> Log out</a>
            </div>

            <div class="wrap">

                <div class="page-head">
                    <h1>Messages</h1>
                    <p>Questions from students about your properties. <strong><?= (int)$unreadTotal ?> new</strong></p>
                </div>

                <?php if ($error !== ""): ?>
                <div class="alert alert-error" role="alert"><?= h($error) ?></div>
                <?php endif; ?>

                <?php if (!$threads): ?>
                <div class="empty">
                    <div class="stat-ico"><?= ico("chat", 30) ?></div>
                    <h3>No messages yet</h3>
                    <p>When a student contacts you about a property, the conversation will appear here.</p>
                    <a class="btn btn-primary" href="landlord.php">Back to Dashboard</a>
                </div>
                <?php endif; ?>

                <?php foreach ($threads as $t): ?>
                <section class="conv" id="t-<?= h($t["id"]) ?>">
                    <div class="conv-head">
                        <div>
                            <h2><?= h($t["property_title"]) ?></h2>
                            <small>With <?= h($t["student_name"]) ?> (<?= h($t["student_email"]) ?>)</small>
                        </div>
                        <?php if ($t["unread"] > 0): ?>
                        <span class="new-badge"><?= (int)$t["unread"] ?> new</span>
                        <?php endif; ?>
                    </div>

                    <div class="chat">
                        <?php foreach ($t["messages"] as $m): ?>
                        <div class="crow <?= $m["mine"] ? "mine" : "" ?>">
                            <div class="cb <?= $m["new"] ? "fresh" : "" ?>">
                                <span class="txt"><?= h(trim($m["text"])) ?></span>
                                <time><?= h(date("d M Y, g:i a", strtotime($m["time"]))) ?></time>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($t["can_reply"]): ?>
                    <form method="POST" class="replyf field">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="property_key" value="<?= h($t["property_key"]) ?>">
                        <input type="hidden" name="student_email" value="<?= h($t["student_email"]) ?>">
                        <label for="r-<?= h($t["id"]) ?>">Reply to <?= h($t["student_name"]) ?></label>
                        <textarea id="r-<?= h($t["id"]) ?>" name="message" maxlength="5000" required
                            placeholder="Write your reply..."></textarea>
                        <button type="submit" class="btn btn-primary btn-sm">Send reply</button>
                    </form>
                    <?php endif; ?>
                </section>
                <?php endforeach; ?>

                <div class="foot">UniMtaa · Helping students discover accommodation with greater confidence.</div>
            </div>
        </main>
    </div>
</body>

</html>