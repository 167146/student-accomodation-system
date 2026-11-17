<?php
session_start();

require_once "../config/database.php";

// Students only.
if (
    empty($_SESSION["email"]) ||
    ($_SESSION["role"] ?? "") !== "student"
) {
    header("Location: ../public/student_login.php");
    exit;
}

/*
 * Students only see listings an admin has approved and that are
 * available. Each property appears once, with its first photo as the
 * cover and a count of all its photos.
 */
$search = trim($_GET["q"] ?? "");

$sql = "SELECT p.property_key, p.title, p.location_name, p.price,
               p.pets_allowed, p.parking_available, p.wifi_available,
               p.water_reliable, p.furnished, p.environment_noise,
               (SELECT ph.photo_path
                FROM property_photos ph
                WHERE ph.property_key = p.property_key
                ORDER BY ph.uploaded_at ASC
                LIMIT 1) AS cover_photo,
               (SELECT COUNT(*)
                FROM property_photos ph2
                WHERE ph2.property_key = p.property_key) AS photo_count
        FROM properties p
        WHERE p.verification_status = 'approved'
          AND p.availability_status = 'available'";

$params = [];

if ($search !== "") {
    $sql .= " AND (p.title LIKE ? OR p.location_name LIKE ? OR p.address LIKE ?)";
    $like = "%" . $search . "%";
    $params = [$like, $like, $like];
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

$studentName = htmlspecialchars($_SESSION["name"] ?? "Student", ENT_QUOTES, "UTF-8");

/*
 * Photo paths are saved as "uploads/xxx.png", relative to the properties
 * folder. Build the full web path to that folder so photos load no matter
 * which folder this page is saved in (one level below the project root).
 */
$imageBase = rtrim(dirname(dirname($_SERVER["SCRIPT_NAME"])), "/\\") . "/properties/";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Accommodation | CampusNest</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500;600&display=swap"
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
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: 'Inter', sans-serif;
        background: var(--paper);
        color: var(--ink);
    }

    h1,
    h2,
    h3 {
        font-family: 'Poppins', sans-serif;
    }

    .page-header {
        background:
            radial-gradient(circle at 85% 0%, rgba(183, 148, 244, .35), transparent 55%),
            linear-gradient(120deg, var(--violet-dark), var(--violet) 70%, #8B5CF6);
        color: #fff;
        padding: 42px 24px 78px;
    }

    .inner {
        max-width: 1100px;
        margin: 0 auto;
    }

    .page-header h1 {
        margin: 0 0 8px;
        font-size: clamp(26px, 4vw, 34px);
    }

    .page-header p {
        margin: 0;
        color: rgba(255, 255, 255, .85);
        font-size: 15px;
    }

    .back-link {
        display: inline-block;
        margin-bottom: 16px;
        color: rgba(255, 255, 255, .9);
        font-size: 14px;
        text-decoration: none;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    .search-bar {
        max-width: 1100px;
        margin: -30px auto 0;
        padding: 0 24px;
    }

    .search-bar form {
        display: flex;
        gap: 10px;
        background: var(--card);
        border: 1px solid var(--hairline);
        border-radius: 14px;
        padding: 10px;
        box-shadow: 0 12px 28px rgba(76, 29, 149, .1);
    }

    .search-bar input {
        flex: 1;
        border: none;
        outline: none;
        font: inherit;
        font-size: 15px;
        padding: 10px 12px;
        color: var(--ink);
        min-width: 0;
    }

    .search-bar button {
        border: none;
        cursor: pointer;
        font: inherit;
        font-weight: 600;
        font-size: 14px;
        color: #fff;
        padding: 10px 22px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--violet), var(--violet-deep));
    }

    .content {
        max-width: 1100px;
        margin: 0 auto;
        padding: 28px 24px 60px;
    }

    .result-count {
        color: var(--ink-soft);
        font-size: 14px;
        margin-bottom: 18px;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 22px;
    }

    .listing {
        background: var(--card);
        border: 1px solid var(--hairline);
        border-radius: 18px;
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
        box-shadow: 0 10px 26px rgba(76, 29, 149, .07);
        transition: box-shadow .15s, transform .15s;
    }

    .listing:hover {
        box-shadow: 0 16px 32px rgba(76, 29, 149, .13);
        transform: translateY(-3px);
    }

    .cover {
        position: relative;
        height: 200px;
        background: linear-gradient(135deg, #F3E8FF, #E9E1F2);
        display: grid;
        place-items: center;
        font-size: 40px;
    }

    .cover img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .photo-count {
        position: absolute;
        left: 12px;
        bottom: 12px;
        background: rgba(30, 16, 51, .78);
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
    }

    .verified {
        position: absolute;
        top: 12px;
        right: 12px;
        background: var(--green-soft);
        color: #0B6B4C;
        font-size: 12px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 20px;
    }

    .body {
        padding: 18px 20px 20px;
    }

    .body h3 {
        margin: 0 0 4px;
        font-size: 17px;
        color: var(--violet-dark);
    }

    .location {
        color: var(--ink-soft);
        font-size: 13.5px;
        margin-bottom: 12px;
    }

    .price {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 19px;
    }

    .price small {
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        font-size: 12.5px;
        color: var(--ink-soft);
    }

    .tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 12px;
    }

    .tag {
        background: #F3E8FF;
        color: var(--violet-deep);
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
    }

    .empty {
        background: var(--card);
        border: 1px dashed var(--lilac);
        border-radius: 18px;
        padding: 48px 24px;
        text-align: center;
        color: var(--ink-soft);
    }

    .empty .big {
        font-size: 42px;
        margin-bottom: 8px;
    }

    .empty h3 {
        margin: 0 0 6px;
        color: var(--violet-dark);
    }
    </style>
</head>

<body>

    <header class="page-header">
        <div class="inner">
            <!-- Change this link if your student dashboard lives somewhere else. -->
            <a class="back-link" href="../dashboard/student.php">&larr; Back to dashboard</a>
            <h1>Find your next home</h1>
            <p>Verified student accommodation, with photos of every space.</p>
        </div>
    </header>

    <div class="search-bar">
        <form method="GET">
            <input type="text" name="q" placeholder="Search by name, area or address"
                value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Search</button>
        </form>
    </div>

    <div class="content">

        <?php if (empty($properties)): ?>

        <div class="empty">
            <div class="big">🔍</div>
            <h3>No properties to show yet</h3>
            <p>
                <?= $search !== ""
                    ? "Nothing matches \"" . htmlspecialchars($search) . "\". Try a different area or name."
                    : "Listings appear here once an admin has verified them." ?>
            </p>
        </div>

        <?php else: ?>

        <div class="result-count">
            <?= count($properties) ?> verified propert<?= count($properties) === 1 ? "y" : "ies" ?>
        </div>

        <div class="grid">
            <?php foreach ($properties as $property):
                $photoCount = (int)$property["photo_count"];
            ?>
            <a class="listing" href="view.php?key=<?= urlencode($property["property_key"]) ?>">

                <div class="cover">
                    <?php if (!empty($property["cover_photo"])): ?>
                    <img src="<?= htmlspecialchars($imageBase . $property["cover_photo"]) ?>"
                        alt="<?= htmlspecialchars($property["title"]) ?>">
                    <?php else: ?>
                    🏠
                    <?php endif; ?>

                    <span class="verified">✓ Verified</span>

                    <?php if ($photoCount > 0): ?>
                    <span class="photo-count"><?= $photoCount ?> photo<?= $photoCount === 1 ? "" : "s" ?></span>
                    <?php endif; ?>
                </div>

                <div class="body">
                    <h3><?= htmlspecialchars($property["title"]) ?></h3>
                    <div class="location">📍 <?= htmlspecialchars($property["location_name"]) ?></div>

                    <div class="price">
                        KSh <?= number_format((float)$property["price"]) ?>
                        <small>/ month</small>
                    </div>

                    <div class="tags">
                        <?php if ($property["wifi_available"]): ?><span class="tag">Wi-Fi</span><?php endif; ?>
                        <?php if ($property["furnished"]): ?><span class="tag">Furnished</span><?php endif; ?>
                        <?php if ($property["water_reliable"]): ?><span class="tag">Reliable water</span><?php endif; ?>
                        <?php if ($property["parking_available"]): ?><span class="tag">Parking</span><?php endif; ?>
                        <?php if ($property["pets_allowed"]): ?><span class="tag">Pets OK</span><?php endif; ?>
                        <span class="tag"><?= htmlspecialchars(ucfirst($property["environment_noise"])) ?></span>
                    </div>
                </div>

            </a>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>

    </div>

</body>

</html>