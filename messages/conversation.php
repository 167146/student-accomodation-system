<?php
session_start();

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/shared.php";

$propertyKey = trim($_GET["property"] ?? ($_POST["property"] ?? ""));
$with = trim($_GET["with"] ?? ($_POST["with"] ?? ""));
$error = "";

if ($with === "" || $with === $me) {
    header("Location: inbox.php");
    exit;
}

if (empty($_SESSION["ml_csrf"])) {
    $_SESSION["ml_csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["ml_csrf"];

/* Messages between the two people about this property (empty = no property). */
function ml_thread($pdo, $me, $with, $propertyKey)
{
    $sql = "SELECT m.message, m.sender_email, m.is_read, m.created_at
            FROM messages m
            WHERE ((m.sender_email = ? AND m.receiver_email = ?)
                OR (m.sender_email = ? AND m.receiver_email = ?))";
    $params = [$me, $with, $with, $me];

    if ($propertyKey === "") {
        $sql .= " AND m.property_key IS NULL";
    } else {
        $sql .= " AND m.property_key = ?";
        $params[] = $propertyKey;
    }

    $stmt = $pdo->prepare($sql . " ORDER BY m.created_at ASC");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ------------------------------------------------------------------
 * Send a reply. Only possible inside an existing conversation.
 * ------------------------------------------------------------------ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        if (!hash_equals($csrf, $_POST["csrf_token"] ?? "")) {
            throw new Exception("Your session expired. Refresh the page and try again.");
        }

        $text = trim($_POST["message"] ?? "");
        if ($text === "" || strlen($text) > 5000) {
            throw new Exception("Enter a message of up to 5,000 characters.");
        }

        if (!ml_thread($pdo, $me, $with, $propertyKey)) {
            throw new Exception("Conversation not found.");
        }

        $stmt = $pdo->prepare(
            "INSERT INTO messages
             (message_key, sender_email, receiver_email, property_key, message, is_read)
             VALUES (?, ?, ?, ?, ?, 0)"
        );
        $stmt->execute([
            bin2hex(random_bytes(32)),
            $me,
            $with,
            $propertyKey === "" ? null : $propertyKey,
            $text,
        ]);

        header(
            "Location: conversation.php?property=" . urlencode($propertyKey) .
            "&with=" . urlencode($with) . "#end"
        );
        exit;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $error = "The message could not be sent. Please try again.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

/* ------------------------------------------------------------------
 * Load the conversation and mark what was received as read.
 * ------------------------------------------------------------------ */
$messages = [];
$title = "Accommodation enquiry";
$otherName = $with;

try {
    $messages = ml_thread($pdo, $me, $with, $propertyKey);

    if ($messages) {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$with]);
        $otherName = $stmt->fetchColumn() ?: $with;

        if ($propertyKey !== "") {
            $stmt = $pdo->prepare("SELECT title FROM properties WHERE property_key = ? LIMIT 1");
            $stmt->execute([$propertyKey]);
            $title = $stmt->fetchColumn() ?: $title;
        }

        $sql = "UPDATE messages SET is_read = 1
                WHERE receiver_email = ? AND sender_email = ? AND is_read = 0";
        $params = [$me, $with];
        if ($propertyKey === "") {
            $sql .= " AND property_key IS NULL";
        } else {
            $sql .= " AND property_key = ?";
            $params[] = $propertyKey;
        }
        $pdo->prepare($sql)->execute($params);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = $error ?: "The conversation could not be loaded.";
}

$unread = ml_unread($pdo, $me);

ml_head($messages ? "Chat with " . $otherName : "Conversation");
ml_side($unread);
?>
<div class="topline">
    <a class="btn btn-soft btn-sm" href="inbox.php">&larr; All messages</a>
</div>

<?php if ($error !== ""): ?>
<div class="alert alert-error" role="alert"><?= h($error) ?></div>
<?php endif; ?>

<?php if (!$messages): ?>
<div class="empty">
    <div class="stat-ico"><?= ico("chat", 30) ?></div>
    <h3>Conversation not found</h3>
    <p>This conversation does not exist or you are not part of it.</p>
    <a class="btn btn-primary" href="inbox.php">Back to messages</a>
</div>
<?php else: ?>

<section class="convbox">
    <div class="conv-head">
        <div>
            <h2><?= h($title) ?></h2>
            <small>With <?= h($otherName) ?> (<?= h($with) ?>)</small>
        </div>
    </div>

    <div class="chat">
        <?php foreach ($messages as $m):
                            $mine = $m["sender_email"] === $me;
                            $fresh = !$mine && (int)$m["is_read"] === 0;
                        ?>
        <div class="crow <?= $mine ? "mine" : "" ?>">
            <div class="cb <?= $fresh ? "fresh" : "" ?>">
                <span class="txt"><?= h(trim($m["message"])) ?></span>
                <time><?= h(date("d M Y, g:i a", strtotime($m["created_at"]))) ?></time>
            </div>
        </div>
        <?php endforeach; ?>
        <span id="end"></span>
    </div>

    <form method="POST" class="replyf field">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="property" value="<?= h($propertyKey) ?>">
        <input type="hidden" name="with" value="<?= h($with) ?>">
        <label for="reply">Reply to <?= h($otherName) ?></label>
        <textarea id="reply" name="message" maxlength="5000" required placeholder="Write your message..."></textarea>
        <button type="submit" class="btn btn-primary btn-sm">Send message</button>
    </form>
</section>

<?php endif; ?>
<?php ml_end(); ?>