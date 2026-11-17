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

$propertyKey = trim($_GET["key"] ?? "");
$property = null;
$photos = [];

/*
 * Photo paths are saved as "uploads/xxx.png", relative to the properties
 * folder. Build the full web path to that folder so photos load no matter
 * which folder this page is saved in (one level below the project root).
 */
$imageBase = rtrim(dirname(dirname($_SERVER["SCRIPT_NAME"])), "/\\") . "/properties/";

if ($propertyKey !== "") {
    // Only approved, available listings are visible to students.
    $stmt = $pdo->prepare(
        "SELECT property_key, title, description, address, location_name,
                price, amenities, pets_allowed, parking_available,
                wifi_available, water_reliable, furnished,
                security_details, environment_noise
         FROM properties
         WHERE property_key = ?
           AND verification_status = 'approved'
           AND availability_status = 'available'
         LIMIT 1"
    );
    $stmt->execute([$propertyKey]);
    $property = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($property) {
        // Every photo that belongs to this property, in upload order.
        $photoStmt = $pdo->prepare(
            "SELECT photo_path
             FROM property_photos
             WHERE property_key = ?
             ORDER BY uploaded_at ASC"
        );
        $photoStmt->execute([$propertyKey]);
        $photos = $photoStmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $property ? htmlspecialchars($property["title"]) : "Property not found" ?> | CampusNest</title>

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

    .wrap {
        max-width: 1050px;
        margin: 0 auto;
        padding: 24px 24px 60px;
    }

    .back-link {
        display: inline-block;
        margin-bottom: 18px;
        color: var(--violet-deep);
        font-weight: 600;
        font-size: 14px;
        text-decoration: none;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    .layout {
        display: grid;
        grid-template-columns: 1.3fr 1fr;
        gap: 28px;
        align-items: start;
    }

    /* ---------- Gallery ---------- */

    .gallery {
        background: var(--card);
        border: 1px solid var(--hairline);
        border-radius: 18px;
        padding: 14px;
        box-shadow: 0 10px 26px rgba(76, 29, 149, .07);
    }

    .main-photo {
        position: relative;
        height: 380px;
        border-radius: 12px;
        overflow: hidden;
        background: linear-gradient(135deg, #F3E8FF, #E9E1F2);
        display: grid;
        place-items: center;
        font-size: 56px;
    }

    .main-photo img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .counter {
        position: absolute;
        right: 12px;
        bottom: 12px;
        background: rgba(30, 16, 51, .78);
        color: #fff;
        font-size: 12.5px;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 20px;
    }

    .nav-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 40px;
        height: 40px;
        border: none;
        border-radius: 50%;
        background: rgba(30, 16, 51, .65);
        color: #fff;
        font-size: 20px;
        line-height: 1;
        cursor: pointer;
        z-index: 2;
    }

    .nav-btn:hover {
        background: rgba(124, 58, 237, .9);
    }

    .nav-prev {
        left: 12px;
    }

    .nav-next {
        right: 12px;
    }

    .thumbs {
        display: flex;
        gap: 10px;
        margin-top: 12px;
        overflow-x: auto;
        padding-bottom: 4px;
    }

    .thumb {
        flex: 0 0 90px;
        height: 70px;
        border-radius: 9px;
        overflow: hidden;
        border: 2px solid transparent;
        padding: 0;
        background: none;
        cursor: pointer;
        opacity: .65;
        transition: opacity .15s, border-color .15s;
    }

    .thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .thumb:hover {
        opacity: 1;
    }

    .thumb.active {
        border-color: var(--violet);
        opacity: 1;
    }

    /* ---------- Details ---------- */

    .panel {
        background: var(--card);
        border: 1px solid var(--hairline);
        border-radius: 18px;
        padding: 24px;
        box-shadow: 0 10px 26px rgba(76, 29, 149, .07);
    }

    .verified {
        display: inline-block;
        background: var(--green-soft);
        color: #0B6B4C;
        font-size: 12px;
        font-weight: 700;
        padding: 4px 11px;
        border-radius: 20px;
        margin-bottom: 12px;
    }

    .panel h1 {
        margin: 0 0 6px;
        font-size: 24px;
        color: var(--violet-dark);
    }

    .location {
        color: var(--ink-soft);
        font-size: 14px;
        margin-bottom: 16px;
    }

    .price {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 26px;
        margin-bottom: 18px;
    }

    .price small {
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        font-size: 13px;
        color: var(--ink-soft);
    }

    .tags {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        margin-bottom: 20px;
    }

    .tag {
        background: #F3E8FF;
        color: var(--violet-deep);
        font-size: 12.5px;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 20px;
    }

    .info h3 {
        margin: 18px 0 6px;
        font-size: 14px;
        color: var(--violet-dark);
    }

    .info p {
        margin: 0;
        color: var(--ink-soft);
        font-size: 14px;
        line-height: 1.6;
        white-space: pre-line;
    }

    .not-found {
        max-width: 520px;
        margin: 80px auto;
        text-align: center;
        background: var(--card);
        border: 1px dashed var(--lilac);
        border-radius: 18px;
        padding: 48px 24px;
    }

    .not-found h2 {
        color: var(--violet-dark);
        margin: 0 0 8px;
    }

    .not-found p {
        color: var(--ink-soft);
        margin: 0 0 20px;
    }

    @media (max-width: 800px) {
        .layout {
            grid-template-columns: 1fr;
        }

        .main-photo {
            height: 260px;
        }
    }
    </style>
</head>

<body>
    <div class="wrap">

        <a class="back-link" href="browse.php">&larr; Back to all properties</a>

        <?php if (!$property): ?>

        <div class="not-found">
            <h2>Property not found</h2>
            <p>This listing may have been removed or isn't available right now.</p>
            <a class="back-link" href="browse.php">Browse available properties</a>
        </div>

        <?php else: ?>

        <div class="layout">

            <!-- All photos of this one property -->
            <div class="gallery">
                <div class="main-photo">
                    <?php if (!empty($photos)): ?>
                    <img id="mainPhoto" src="<?= htmlspecialchars($imageBase . $photos[0]) ?>"
                        alt="<?= htmlspecialchars($property["title"]) ?>">
                    <span class="counter" id="counter">1 of <?= count($photos) ?></span>
                    <?php if (count($photos) > 1): ?>
                    <button type="button" class="nav-btn nav-prev" id="prevBtn"
                        aria-label="Previous photo">&#8249;</button>
                    <button type="button" class="nav-btn nav-next" id="nextBtn" aria-label="Next photo">&#8250;</button>
                    <?php endif; ?>
                    <?php else: ?>
                    🏠
                    <?php endif; ?>
                </div>

                <?php if (count($photos) > 1): ?>
                <div class="thumbs">
                    <?php foreach ($photos as $i => $photo): ?>
                    <button type="button" class="thumb <?= $i === 0 ? "active" : "" ?>"
                        data-src="<?= htmlspecialchars($imageBase . $photo) ?>" data-index="<?= $i + 1 ?>">
                        <img src="<?= htmlspecialchars($imageBase . $photo) ?>" alt="Photo <?= $i + 1 ?>">
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Details -->
            <div class="panel">
                <span class="verified">✓ Verified listing</span>

                <h1><?= htmlspecialchars($property["title"]) ?></h1>
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
                    <?php if ($property["pets_allowed"]): ?><span class="tag">Pets allowed</span><?php endif; ?>
                    <span class="tag"><?= htmlspecialchars(ucfirst($property["environment_noise"])) ?> area</span>
                </div>

                <div class="info">
                    <h3>About this place</h3>
                    <p><?= htmlspecialchars($property["description"]) ?></p>

                    <h3>Address</h3>
                    <p><?= htmlspecialchars($property["address"]) ?></p>

                    <h3>Amenities</h3>
                    <p><?= htmlspecialchars($property["amenities"] ?? "") ?></p>

                    <?php if (!empty($property["security_details"])): ?>
                    <h3>Security</h3>
                    <p><?= htmlspecialchars($property["security_details"]) ?></p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <?php endif; ?>

    </div>

    <script>
    // Thumbnails, arrow buttons and the left/right keys all change the
    // large photo, so a property with 10+ photos is easy to browse.
    (function() {
        var main = document.getElementById("mainPhoto");
        var counter = document.getElementById("counter");
        var thumbs = document.querySelectorAll(".thumb");
        var total = thumbs.length;
        var current = 0;

        if (!main || total === 0) {
            return;
        }

        function show(index) {
            current = (index + total) % total;
            var thumb = thumbs[current];

            main.src = thumb.getAttribute("data-src");
            counter.textContent = (current + 1) + " of " + total;

            thumbs.forEach(function(t) {
                t.classList.remove("active");
            });
            thumb.classList.add("active");
            thumb.scrollIntoView({
                block: "nearest",
                inline: "center",
                behavior: "smooth"
            });
        }

        thumbs.forEach(function(thumb, i) {
            thumb.addEventListener("click", function() {
                show(i);
            });
        });

        document.getElementById("prevBtn").addEventListener("click", function() {
            show(current - 1);
        });
        document.getElementById("nextBtn").addEventListener("click", function() {
            show(current + 1);
        });

        document.addEventListener("keydown", function(e) {
            if (e.key === "ArrowLeft") {
                show(current - 1);
            }
            if (e.key === "ArrowRight") {
                show(current + 1);
            }
        });
    })();
    </script>
</body>

</html>