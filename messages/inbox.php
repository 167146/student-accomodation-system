<?php
session_start();

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/shared.php";

/* ------------------------------------------------------------------
 * Every message sent to or from the logged-in user, grouped into one
 * conversation per property and other person.
 * ------------------------------------------------------------------ */
$threads = [];
$error = "";

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
    $stmt->execute([$me, $me]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $mine = $m["sender_email"] === $me;
        $other = $mine ? $m["receiver_email"] : $m["sender_email"];
        $otherName = $mine ? $m["receiver_name"] : $m["sender_name"];
        $pk = (string)($m["property_key"] ?? "");
        $key = $pk . "|" . $other;

        if (!isset($threads[$key])) {
            $threads[$key] = [
                "property_key" => $pk,
                "title" => $m["property_title"] ?: "Accommodation enquiry",
                "other" => $other,
                "other_name" => $otherName ?: $other,
                "unread" => 0,
            ];
        }

        $threads[$key]["last_text"] = $m["message"];
        $threads[$key]["last_time"] = $m["created_at"];
        $threads[$key]["last_mine"] = $mine;

        if (!$mine && (int)$m["is_read"] === 0) {
            $threads[$key]["unread"]++;
        }
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = "Messages could not be loaded. Please check that the messages table exists.";
}

uasort($threads, fn($a, $b) => strcmp($b["last_time"], $a["last_time"]));
$unread = ml_unread($pdo, $me);

ml_head("Messages");
ml_side($unread);
?>
<div class="page-head">
    <h1>Messages</h1>
    <p>
        <?= $role === "landlord"
                            ? "Questions from students about your properties."
                            : "Your conversations with landlords." ?>
        <?php if ($unread > 0): ?><strong><?= (int)$unread ?> new</strong><?php endif; ?>
    </p>
</div>

<?php if ($error !== ""): ?>
<div class="alert alert-error" role="alert"><?= h($error) ?></div>
<?php endif; ?>

<?php if (!$threads && $error === ""): ?>
<div class="empty">
    <div class="stat-ico"><?= ico("chat", 30) ?></div>
    <h3>No messages yet</h3>
    <p>
        <?= $role === "landlord"
                            ? "When a student contacts you about a property, the conversation will appear here."
                            : "Open a property and choose Contact landlord to start a conversation." ?>
    </p>
    <a class="btn btn-primary"
        href="<?= $role === "landlord" ? "../dashboard/landlord.php" : "../dashboard/student.php?section=discover" ?>">
        <?= $role === "landlord" ? "Back to Dashboard" : "Find a home" ?>
    </a>
</div>
<?php endif; ?>

<div class="inbox">
    <?php foreach ($threads as $t): ?>
    <a class="item <?= $t["unread"] > 0 ? "unread" : "" ?>"
        href="conversation.php?property=<?= urlencode($t["property_key"]) ?>&with=<?= urlencode($t["other"]) ?>">
        <div class="avatar" aria-hidden="true"><?= h(strtoupper(substr($t["other_name"], 0, 1))) ?></div>
        <div class="mid">
            <strong><?= h($t["other_name"]) ?></strong>
            <span class="pt"><?= h($t["title"]) ?></span>
            <p><?= $t["last_mine"] ? "You: " : "" ?><?= h(mb_strimwidth(trim($t["last_text"]), 0, 110, "...")) ?></p>
        </div>
        <div class="end">
            <?= h(date("d M, g:i a", strtotime($t["last_time"]))) ?>
            <?php if ($t["unread"] > 0): ?>
            <br><span class="new-badge"><?= (int)$t["unread"] ?> new</span>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php ml_end(); ?>