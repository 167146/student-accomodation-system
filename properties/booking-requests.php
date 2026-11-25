<?php
session_start();

require_once __DIR__ . "/../config/database.php";

if (
    empty($_SESSION["email"]) ||
    ($_SESSION["role"] ?? "") !== "landlord"
) {
    header("Location: ../public/landlord_login.php");
    exit;
}

date_default_timezone_set("Africa/Nairobi");

function h($v)
{
    return htmlspecialchars((string)($v ?? ""), ENT_QUOTES, "UTF-8");
}

$landlordEmail = $_SESSION["email"];
$name = $_SESSION["name"] ?? "Landlord";

if (empty($_SESSION["br_csrf"])) {
    $_SESSION["br_csrf"] = bin2hex(random_bytes(32));
}

$flash = "";
$flashType = "ok";

/*
 * Landlord decisions on a booking request.
 * Allowed moves: pending -> accepted | rejected, accepted -> completed | rejected.
 * The landlord can only act on bookings for properties that they own.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $propertyKey = trim($_POST["property_key"] ?? "");
    $studentEmail = trim($_POST["student_email"] ?? "");
    $decision = $_POST["decision"] ?? "";

    if (!hash_equals($_SESSION["br_csrf"], $_POST["csrf_token"] ?? "")) {
        $flash = "Your session expired. Please try again.";
        $flashType = "bad";
    } elseif (!in_array($decision, ["accepted", "rejected", "completed"], true)) {
        $flash = "Unknown action.";
        $flashType = "bad";
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT b.status
                 FROM bookings b
                 INNER JOIN properties p ON p.property_key = b.property_key
                 WHERE b.property_key = ?
                   AND b.student_email = ?
                   AND p.landlord_email = ?
                 LIMIT 1"
            );
            $stmt->execute([$propertyKey, $studentEmail, $landlordEmail]);
            $current = $stmt->fetchColumn();

            $allowed = [
                "pending"  => ["accepted", "rejected"],
                "accepted" => ["completed", "rejected"],
            ];

            if ($current === false) {
                $flash = "That booking request was not found.";
                $flashType = "bad";
            } elseif (!in_array($decision, $allowed[$current] ?? [], true)) {
                $flash = "That request is already " . $current . " and cannot be changed this way.";
                $flashType = "bad";
            } else {
                $upd = $pdo->prepare(
                    "UPDATE bookings
                     SET status = ?
                     WHERE property_key = ? AND student_email = ?"
                );
                $upd->execute([$decision, $propertyKey, $studentEmail]);
                $flash = "Booking request marked as " . $decision . ".";
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $flash = "Something went wrong while saving. Please try again.";
            $flashType = "bad";
        }
    }
}

/* All booking requests for this landlord's properties. */
$filter = $_GET["show"] ?? "all";
if (!in_array($filter, ["all", "pending", "accepted", "completed", "rejected", "cancelled"], true)) {
    $filter = "all";
}

$requests = [];
$counts = ["pending" => 0, "accepted" => 0, "completed" => 0, "rejected" => 0, "cancelled" => 0];

try {
    $stmt = $pdo->prepare(
        "SELECT b.property_key, b.student_email, b.booking_date, b.status,
                p.title, p.address, p.price,
                u.name AS student_name
         FROM bookings b
         INNER JOIN properties p ON p.property_key = b.property_key
         LEFT JOIN users u ON u.email = b.student_email
         WHERE p.landlord_email = ?
         ORDER BY (b.status = 'pending') DESC, b.booking_date DESC"
    );
    $stmt->execute([$landlordEmail]);
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all as $r) {
        if (isset($counts[$r["status"]])) {
            $counts[$r["status"]]++;
        }
        if ($filter === "all" || $r["status"] === $filter) {
            $requests[] = $r;
        }
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $flash = "Booking requests could not be loaded.";
    $flashType = "bad";
}

$statusClass = [
    "pending" => "wait", "accepted" => "good", "completed" => "good",
    "rejected" => "bad", "cancelled" => "bad",
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking requests | UniMtaa</title>
    <style>
    :root {
        --ink: #1F1236;
        --muted: #675E80;
        --line: #E6DEF5;
        --bg: #FAF8FF;
        --card: #fff;
        --brand: #5B21B6;
        --good-bg: #DCFCE7;
        --good: #166534;
        --wait-bg: #FEF3C7;
        --wait: #92400E;
        --bad-bg: #FEE2E2;
        --bad: #991B1B
    }

    * {
        box-sizing: border-box
    }

    body {
        margin: 0;
        background: var(--bg);
        color: var(--ink);
        font-family: system-ui, -apple-system, "Segoe UI", sans-serif
    }

    .wrap {
        max-width: 980px;
        margin: 0 auto;
        padding: 24px 16px 60px
    }

    .top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 18px
    }

    h1 {
        margin: 0;
        font-size: 1.6rem
    }

    .back {
        color: var(--brand);
        text-decoration: none;
        font-weight: 600
    }

    .tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin: 0 0 18px
    }

    .tabs a {
        padding: 8px 14px;
        border-radius: 999px;
        border: 1px solid var(--line);
        background: #fff;
        color: var(--ink);
        text-decoration: none;
        font-size: .9rem
    }

    .tabs a.on {
        background: var(--brand);
        color: #fff;
        border-color: var(--brand)
    }

    .card {
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 18px;
        margin-bottom: 14px;
        box-shadow: 0 1px 2px rgba(46, 16, 101, .06)
    }

    .row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        align-items: flex-start
    }

    .muted {
        color: var(--muted);
        font-size: .92rem
    }

    .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: .8rem;
        font-weight: 700;
        text-transform: capitalize
    }

    .badge.good {
        background: var(--good-bg);
        color: var(--good)
    }

    .badge.wait {
        background: var(--wait-bg);
        color: var(--wait)
    }

    .badge.bad {
        background: var(--bad-bg);
        color: var(--bad)
    }

    .actions {
        display: flex;
        gap: 8px;
        margin-top: 14px;
        flex-wrap: wrap
    }

    button,
    .btn {
        border: 0;
        border-radius: 12px;
        padding: 10px 16px;
        font-weight: 700;
        cursor: pointer;
        font-size: .9rem;
        text-decoration: none;
        display: inline-block
    }

    .b-ok {
        background: var(--brand);
        color: #fff
    }

    .b-no {
        background: #fff;
        color: var(--bad);
        border: 1px solid #F5B5B5
    }

    .b-soft {
        background: #EDE9FE;
        color: var(--brand)
    }

    .flash {
        padding: 12px 16px;
        border-radius: 12px;
        margin-bottom: 16px;
        font-weight: 600
    }

    .flash.ok {
        background: var(--good-bg);
        color: var(--good)
    }

    .flash.bad {
        background: var(--bad-bg);
        color: var(--bad)
    }

    .empty {
        text-align: center;
        padding: 40px 10px;
        color: var(--muted)
    }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="top">
            <h1>Booking requests</h1>
            <a class="back" href="../dashboard/landlord.php">&larr; Back to dashboard</a>
        </div>

        <?php if ($flash !== ""): ?>
        <div class="flash <?= h($flashType) ?>"><?= h($flash) ?></div>
        <?php endif; ?>

        <div class="tabs">
            <a class="<?= $filter === "all" ? "on" : "" ?>" href="?show=all">All</a>
            <?php foreach ($counts as $s => $n): ?>
            <a class="<?= $filter === $s ? "on" : "" ?>" href="?show=<?= h($s) ?>"><?= h(ucfirst($s)) ?>
                (<?= (int)$n ?>)</a>
            <?php endforeach; ?>
        </div>

        <?php if (!$requests): ?>
        <div class="card empty">No booking requests here yet. When a student books one of your properties, it will
            appear on this page.</div>
        <?php endif; ?>

        <?php foreach ($requests as $r): ?>
        <div class="card">
            <div class="row">
                <div>
                    <strong><?= h($r["student_name"] ?: $r["student_email"]) ?></strong>
                    <div class="muted"><?= h($r["student_email"]) ?></div>
                    <div style="margin-top:8px">wants to book <strong><?= h($r["title"]) ?></strong></div>
                    <div class="muted"><?= h($r["address"]) ?> &middot; KES <?= h(number_format((float)$r["price"])) ?>
                        / month</div>
                    <div class="muted">Requested <?= h(date("j M Y, g:i a", strtotime($r["booking_date"]))) ?></div>
                </div>
                <span class="badge <?= h($statusClass[$r["status"]] ?? "wait") ?>"><?= h($r["status"]) ?></span>
            </div>

            <div class="actions">
                <?php if (in_array($r["status"], ["pending", "accepted"], true)): ?>
                <form method="post" style="display:contents">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION["br_csrf"]) ?>">
                    <input type="hidden" name="property_key" value="<?= h($r["property_key"]) ?>">
                    <input type="hidden" name="student_email" value="<?= h($r["student_email"]) ?>">
                    <?php if ($r["status"] === "pending"): ?>
                    <button class="b-ok" name="decision" value="accepted">Accept</button>
                    <?php else: ?>
                    <button class="b-ok" name="decision" value="completed">Mark completed</button>
                    <?php endif; ?>
                    <button class="b-no" name="decision" value="rejected"
                        onclick="return confirm('Reject this booking request?');">Reject</button>
                </form>
                <?php endif; ?>
                <a class="btn b-soft" href="../messages/inbox.php">Message student</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</body>

</html>