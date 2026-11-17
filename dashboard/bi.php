<?php
session_start();

require_once "../config/database.php";

// Admin only. Change the redirect if your admin login page has another name.
if (
    empty($_SESSION["email"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../public/admin_login.php");
    exit;
}

function rows(PDO $pdo, string $sql): array
{
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function one(PDO $pdo, string $sql)
{
    return $pdo->query($sql)->fetchColumn();
}

/* ---------- KPI numbers ---------- */
$totalProperties = (int)one($pdo, "SELECT COUNT(*) FROM properties");
$approvedCount   = (int)one($pdo, "SELECT COUNT(*) FROM properties WHERE verification_status='approved'");
$totalBookings   = (int)one($pdo, "SELECT COUNT(*) FROM bookings");
$acceptedCount   = (int)one($pdo, "SELECT COUNT(*) FROM bookings WHERE status='accepted'");
$avgRating       = (float)one($pdo, "SELECT COALESCE(AVG(rating),0) FROM reviews");
$avgRent         = (float)one($pdo, "SELECT COALESCE(AVG(price),0) FROM properties WHERE verification_status='approved'");
$acceptRate      = $totalBookings > 0 ? round($acceptedCount / $totalBookings * 100) : 0;

/* ---------- Chart data ---------- */
$rentByArea = rows($pdo,
    "SELECT location_name AS label, ROUND(AVG(price)) AS value
     FROM properties WHERE verification_status='approved'
     GROUP BY location_name ORDER BY value DESC LIMIT 10");

$demandByArea = rows($pdo,
    "SELECT p.location_name AS label, COUNT(*) AS value
     FROM bookings b JOIN properties p ON p.property_key = b.property_key
     GROUP BY p.location_name ORDER BY value DESC LIMIT 10");

$bookingsByMonth = rows($pdo,
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS label, COUNT(*) AS value
     FROM bookings GROUP BY label ORDER BY label ASC");

$bookingStatus = rows($pdo,
    "SELECT status AS label, COUNT(*) AS value FROM bookings GROUP BY status ORDER BY value DESC");

$verification = rows($pdo,
    "SELECT verification_status AS label, COUNT(*) AS value
     FROM properties GROUP BY verification_status");

$ratingDist = rows($pdo,
    "SELECT rating AS label, COUNT(*) AS value FROM reviews GROUP BY rating ORDER BY rating");

$topRated = rows($pdo,
    "SELECT p.title, p.location_name, ROUND(AVG(r.rating),1) AS avg_rating, COUNT(*) AS reviews
     FROM reviews r JOIN properties p ON p.property_key = r.property_key
     GROUP BY p.property_key, p.title, p.location_name
     HAVING COUNT(*) >= 2
     ORDER BY avg_rating DESC, reviews DESC LIMIT 5");

// Make sure ratings 1-5 all appear, even with zero reviews.
$ratingMap = array_fill(1, 5, 0);
foreach ($ratingDist as $r) {
    $ratingMap[(int)$r["label"]] = (int)$r["value"];
}

$charts = [
    "rent"    => $rentByArea,
    "demand"  => $demandByArea,
    "months"  => $bookingsByMonth,
    "status"  => $bookingStatus,
    "verify"  => $verification,
    "ratings" => array_map(fn($k, $v) => ["label" => $k . " ★", "value" => $v], array_keys($ratingMap), $ratingMap),
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Intelligence | CampusNest Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

    <style>
    :root {
        --ink: #2A1418;
        --ink-soft: #6E5358;
        --burgundy: #7A1F2E;
        --burgundy-dark: #4A1019;
        --rose: #C45A6A;
        --paper: #FAF6F6;
        --card: #FFFFFF;
        --hairline: #EBDDDF;
        --green: #0E9F6E;
        --amber: #C08A1E;
        --red: #B3372C;
        --slate: #8A7B7E;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: 'Inter', sans-serif;
        background: var(--paper);
        color: var(--ink);
    }

    h1, h2, h3 { font-family: 'Poppins', sans-serif; }

    .page-header {
        background: linear-gradient(120deg, var(--burgundy-dark), var(--burgundy));
        color: #fff;
        padding: 38px 24px 70px;
    }

    .inner { max-width: 1150px; margin: 0 auto; }
    .page-header h1 { margin: 0 0 6px; font-size: clamp(24px, 4vw, 32px); }
    .page-header p { margin: 0; color: rgba(255,255,255,.8); font-size: 15px; }
    .back-link { display: inline-block; margin-bottom: 14px; color: rgba(255,255,255,.9); font-size: 14px; text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .content { max-width: 1150px; margin: -38px auto 0; padding: 0 24px 60px; }

    .kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 16px;
        margin-bottom: 22px;
    }

    .kpi {
        background: var(--card);
        border: 1px solid var(--hairline);
        border-radius: 14px;
        padding: 18px 20px;
        box-shadow: 0 8px 20px rgba(74, 16, 25, .07);
    }

    .kpi .label { font-size: 12.5px; color: var(--ink-soft); font-weight: 500; }
    .kpi .value { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 26px; margin-top: 4px; color: var(--burgundy-dark); }
    .kpi .sub { font-size: 12px; color: var(--ink-soft); margin-top: 2px; }

    .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }

    .card {
        background: var(--card);
        border: 1px solid var(--hairline);
        border-radius: 16px;
        padding: 20px 22px 22px;
        box-shadow: 0 8px 20px rgba(74, 16, 25, .06);
    }

    .card.wide { grid-column: 1 / -1; }
    .card h3 { margin: 0 0 2px; font-size: 16px; color: var(--burgundy-dark); }
    .card .hint { font-size: 12.5px; color: var(--ink-soft); margin-bottom: 14px; }

    .chart-box { position: relative; height: 280px; }

    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--hairline); }
    th { font-size: 12px; color: var(--ink-soft); font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
    td.num { font-weight: 600; }

    .empty { color: var(--ink-soft); font-size: 14px; padding: 30px 0; text-align: center; }

    @media (max-width: 800px) {
        .grid { grid-template-columns: 1fr; }
    }
    </style>
</head>

<body>

    <header class="page-header">
        <div class="inner">
            <a class="back-link" href="admin.php">&larr; Back to admin dashboard</a>
            <h1>Business Intelligence</h1>
            <p>Rent, demand and satisfaction trends across the areas CampusNest covers.</p>
        </div>
    </header>

    <div class="content">

        <div class="kpis">
            <div class="kpi">
                <div class="label">Properties</div>
                <div class="value"><?= $totalProperties ?></div>
                <div class="sub"><?= $approvedCount ?> verified</div>
            </div>
            <div class="kpi">
                <div class="label">Bookings</div>
                <div class="value"><?= $totalBookings ?></div>
                <div class="sub"><?= $acceptedCount ?> accepted</div>
            </div>
            <div class="kpi">
                <div class="label">Acceptance rate</div>
                <div class="value"><?= $acceptRate ?>%</div>
                <div class="sub">of all booking requests</div>
            </div>
            <div class="kpi">
                <div class="label">Average rent</div>
                <div class="value">KSh <?= number_format($avgRent) ?></div>
                <div class="sub">verified listings</div>
            </div>
            <div class="kpi">
                <div class="label">Average rating</div>
                <div class="value"><?= number_format($avgRating, 1) ?> / 5</div>
                <div class="sub">across all reviews</div>
            </div>
        </div>

        <div class="grid">

            <div class="card">
                <h3>Average rent by area</h3>
                <div class="hint">Which areas cost the most (KSh per month, verified listings)</div>
                <div class="chart-box"><canvas id="rentChart"></canvas></div>
            </div>

            <div class="card">
                <h3>Booking demand by area</h3>
                <div class="hint">Where students are actually booking</div>
                <div class="chart-box"><canvas id="demandChart"></canvas></div>
            </div>

            <div class="card wide">
                <h3>Bookings over time</h3>
                <div class="hint">Booking requests per month</div>
                <div class="chart-box"><canvas id="monthsChart"></canvas></div>
            </div>

            <div class="card">
                <h3>Booking outcomes</h3>
                <div class="hint">How requests end up</div>
                <div class="chart-box"><canvas id="statusChart"></canvas></div>
            </div>

            <div class="card">
                <h3>Verification outcomes</h3>
                <div class="hint">What happens to submitted listings</div>
                <div class="chart-box"><canvas id="verifyChart"></canvas></div>
            </div>

            <div class="card">
                <h3>Rating distribution</h3>
                <div class="hint">How students score the places they stayed in</div>
                <div class="chart-box"><canvas id="ratingChart"></canvas></div>
            </div>

            <div class="card">
                <h3>Top rated properties</h3>
                <div class="hint">At least 2 reviews</div>
                <?php if (empty($topRated)): ?>
                <div class="empty">Not enough reviews yet.</div>
                <?php else: ?>
                <table>
                    <tr><th>Property</th><th>Area</th><th>Rating</th><th>Reviews</th></tr>
                    <?php foreach ($topRated as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t["title"]) ?></td>
                        <td><?= htmlspecialchars($t["location_name"]) ?></td>
                        <td class="num"><?= htmlspecialchars($t["avg_rating"]) ?> ★</td>
                        <td><?= (int)$t["reviews"] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
    var data = <?= json_encode($charts, JSON_NUMERIC_CHECK) ?>;

    var BURGUNDY = "#7A1F2E", ROSE = "#C45A6A", GREEN = "#0E9F6E",
        AMBER = "#C08A1E", RED = "#B3372C", SLATE = "#8A7B7E";

    var statusColors = { accepted: GREEN, pending: AMBER, cancelled: SLATE, rejected: RED,
                         approved: GREEN, rejected_: RED };

    Chart.defaults.font.family = "Inter, sans-serif";
    Chart.defaults.color = "#6E5358";

    function labels(rows) { return rows.map(function(r) { return r.label; }); }
    function values(rows) { return rows.map(function(r) { return r.value; }); }
    function colorsFor(rows) {
        return rows.map(function(r) { return statusColors[r.label] || ROSE; });
    }

    function bar(id, rows, color, horizontal) {
        new Chart(document.getElementById(id), {
            type: "bar",
            data: { labels: labels(rows), datasets: [{ data: values(rows), backgroundColor: color, borderRadius: 6 }] },
            options: {
                indexAxis: horizontal ? "y" : "x",
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { grid: { display: false } }, y: { grid: { color: "#F1E6E8" }, beginAtZero: true } }
            }
        });
    }

    function doughnut(id, rows) {
        new Chart(document.getElementById(id), {
            type: "doughnut",
            data: { labels: labels(rows), datasets: [{ data: values(rows), backgroundColor: colorsFor(rows), borderWidth: 2, borderColor: "#fff" }] },
            options: { maintainAspectRatio: false, cutout: "62%", plugins: { legend: { position: "right" } } }
        });
    }

    bar("rentChart", data.rent, BURGUNDY, true);
    bar("demandChart", data.demand, ROSE, true);
    bar("ratingChart", data.ratings, AMBER, false);
    doughnut("statusChart", data.status);
    doughnut("verifyChart", data.verify);

    new Chart(document.getElementById("monthsChart"), {
        type: "line",
        data: { labels: labels(data.months), datasets: [{ data: values(data.months), borderColor: BURGUNDY, backgroundColor: "rgba(122,31,46,.12)", fill: true, tension: .3, pointRadius: 4 }] },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: "#F1E6E8" } } }
        }
    });
    </script>
</body>

</html>
