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

$name = htmlspecialchars(
    $_SESSION["name"] ?? "Landlord",
    ENT_QUOTES,
    "UTF-8"
);

$email = htmlspecialchars(
    $_SESSION["email"] ?? "",
    ENT_QUOTES,
    "UTF-8"
);

$propertyCount = 0;
$availableCount = 0;
$pendingCount = 0;
$dashboardMessage = "";

try {
    /*
     * These queries assume properties are associated with landlords
     * using the landlord's email address. If your properties table
     * uses a different relationship, these queries must be adjusted.
     */
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM properties");
    $propertyColumns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);

    $hasLandlordEmail = in_array(
        "landlord_email",
        $propertyColumns,
        true
    );

    if ($hasLandlordEmail) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM properties WHERE landlord_email = ?"
        );
        $stmt->execute([$_SESSION["email"]]);
        $propertyCount = (int) $stmt->fetchColumn();

        if (in_array("availability_status", $propertyColumns, true)) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM properties
                 WHERE landlord_email = ?
                 AND availability_status = 'available'"
            );
            $stmt->execute([$_SESSION["email"]]);
            $availableCount = (int) $stmt->fetchColumn();
        } elseif (in_array("availabilityStatus", $propertyColumns, true)) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM properties
                 WHERE landlord_email = ?
                 AND availabilityStatus = 'available'"
            );
            $stmt->execute([$_SESSION["email"]]);
            $availableCount = (int) $stmt->fetchColumn();
        }
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    $dashboardMessage =
        "Your dashboard is open, but property statistics need to be connected to your database structure.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Landlord Dashboard | UniMtaa</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
    body {
        margin: 0;
    }

    .landlord-dashboard .sidebar {
        background: linear-gradient(180deg, #581C87, #3B0764);
    }

    .landlord-dashboard .dashboard-logo strong {
        color: white;
    }

    .landlord-dashboard .dashboard-topbar {
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .landlord-dashboard .account-label {
        background: #F3E8FF;
        color: #6D28D9;
        border-radius: 14px;
        padding: 10px 15px;
        font-size: 13px;
        font-weight: 700;
    }

    .landlord-dashboard .welcome-banner {
        position: relative;
        overflow: hidden;
        padding: 36px;
        margin-bottom: 28px;
        color: white;
        border-radius: 24px;
        background: linear-gradient(115deg, #5B21B6, #7C3AED, #A78BFA);
    }

    .landlord-dashboard .welcome-banner h1 {
        color: white;
        font-size: clamp(25px, 3vw, 36px);
        line-height: 1.2;
        margin-bottom: 12px;
    }

    .landlord-dashboard .welcome-banner p {
        color: rgba(255, 255, 255, .88);
        max-width: 650px;
    }

    .landlord-dashboard .welcome-emoji {
        position: absolute;
        right: 35px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 75px;
    }

    .landlord-dashboard .stats-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .landlord-dashboard .stat-card {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 23px;
        box-shadow: 0 8px 25px rgba(76, 29, 149, .06);
    }

    .landlord-dashboard .stat-number {
        margin-top: 3px;
    }

    .landlord-dashboard .dashboard-section-title {
        color: #241536;
        font-size: 22px;
        margin-bottom: 7px;
    }

    .landlord-dashboard .section-description {
        color: #81788A;
        font-size: 14px;
        margin-bottom: 22px;
    }

    .landlord-dashboard .landlord-tools {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 20px;
        margin-bottom: 28px;
    }

    .landlord-dashboard .tool-card {
        background: white;
        padding: 25px;
        border-radius: 20px;
        border: 1px solid #E9E1F2;
        box-shadow: 0 8px 25px rgba(76, 29, 149, .06);
        min-width: 0;
    }

    .landlord-dashboard .tool-icon {
        width: 48px;
        height: 48px;
        border-radius: 15px;
        display: grid;
        place-items: center;
        background: #F3E8FF;
        font-size: 23px;
        margin-bottom: 17px;
    }

    .landlord-dashboard .tool-card h3 {
        color: #3B0764;
        font-size: 17px;
        margin-bottom: 8px;
    }

    .landlord-dashboard .tool-card p {
        color: #81788A;
        font-size: 13px;
        margin-bottom: 20px;
        min-height: 42px;
    }

    .landlord-dashboard .dashboard-note {
        padding: 15px 18px;
        border-radius: 13px;
        background: #F3E8FF;
        color: #5B21B6;
        margin-bottom: 22px;
        font-size: 13px;
    }

    .landlord-dashboard .profile-panel {
        background: white;
        padding: 24px;
        border: 1px solid #E9E1F2;
        border-radius: 20px;
    }

    .landlord-dashboard .profile-panel p {
        margin-top: 8px;
        color: #81788A;
        overflow-wrap: anywhere;
    }

    @media (max-width: 1100px) {
        .landlord-dashboard .landlord-tools {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 750px) {

        .landlord-dashboard .stats-grid,
        .landlord-dashboard .landlord-tools {
            grid-template-columns: 1fr;
        }

        .landlord-dashboard .welcome-banner {
            padding: 26px;
        }

        .landlord-dashboard .welcome-emoji {
            display: none;
        }

        .landlord-dashboard .dashboard-content {
            padding: 20px;
        }
    }
    </style>
</head>

<body>
    <div class="dashboard-layout landlord-dashboard">

        <aside class="sidebar landlord-sidebar">
            <div class="dashboard-logo">
                <div class="dashboard-logo-icon">🏠</div>
                <div>
                    <strong>UniMtaa</strong>
                    <small>Student Accommodation</small>
                </div>
            </div>

            <nav class="dashboard-nav">
                <a class="active" href="landlord.php">
                    <span>🏠 &nbsp; Dashboard</span>
                </a>

                <a href="../properties/add.php">
                    <span>＋ &nbsp; Add Property</span>
                </a>

                <a href="../properties/my-properties.php">
                    <span>⌂ &nbsp; My Properties</span>
                </a>

                <a href="../public/logout.php">
                    <span>↪ &nbsp; Log Out</span>
                </a>
            </nav>

            <div style="
            position:absolute;
            bottom:25px;
            left:18px;
            right:18px;
            border-top:1px solid rgba(255,255,255,.2);
            padding-top:18px;
            color:white;
            overflow-wrap:anywhere;
        ">
                <strong><?php echo $name; ?></strong>
                <p style="font-size:12px;opacity:.75;margin-top:4px;">
                    Landlord account
                </p>
            </div>
        </aside>

        <main class="dashboard-main">

            <header class="dashboard-topbar">
                <div>
                    <strong style="color:#3B0764;">Landlord Workspace</strong>
                    <div style="font-size:12px;color:#81788A;">
                        Manage your accommodation
                    </div>
                </div>

                <div class="account-label">● My account</div>
            </header>

            <section class="dashboard-content">

                <div class="welcome-banner">
                    <h1>Welcome back, <?php echo $name; ?>! 💜</h1>
                    <p>
                        Your landlord workspace is ready. Manage your listings,
                        keep accommodation details up to date, and prepare your
                        properties for student discovery.
                    </p>
                    <div class="welcome-emoji">🏡</div>
                </div>

                <?php if ($dashboardMessage !== ""): ?>
                <div class="dashboard-note">
                    <?php echo htmlspecialchars(
                        $dashboardMessage,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </div>
                <?php endif; ?>

                <div class="stats-grid">

                    <div class="stat-card">
                        <div class="stat-icon purple">🏠</div>
                        <div>
                            <div class="stat-label">My properties</div>
                            <div class="stat-number">
                                <?php echo $propertyCount; ?>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon green">✓</div>
                        <div>
                            <div class="stat-label">Available properties</div>
                            <div class="stat-number">
                                <?php echo $availableCount; ?>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon amber">🛡️</div>
                        <div>
                            <div class="stat-label">Verification</div>
                            <div class="stat-number" style="font-size:19px;">
                                Manage listings
                            </div>
                        </div>
                    </div>

                </div>

                <h2 class="dashboard-section-title">Manage your properties</h2>
                <p class="section-description">
                    Choose what you would like to do next.
                </p>

                <div class="landlord-tools">

                    <article class="tool-card">
                        <div class="tool-icon">🏡</div>
                        <h3>Add a property</h3>
                        <p>
                            Create an accommodation listing with its location,
                            rent, facilities, and description.
                        </p>
                        <a class="btn btn-purple" href="../properties/add.php">
                            Add Property →
                        </a>
                    </article>

                    <article class="tool-card">
                        <div class="tool-icon">📋</div>
                        <h3>My properties</h3>
                        <p>
                            Review and manage the accommodation listings
                            associated with your account.
                        </p>
                        <a class="btn btn-outline-purple" href="../properties/my-properties.php">
                            View Listings →
                        </a>
                    </article>

                    <article class="tool-card">
                        <div class="tool-icon">🛡️</div>
                        <h3>Property verification</h3>
                        <p>
                            Prepare your listing for evidence-based verification
                            and property freshness checks.
                        </p>
                        <span class="btn btn-outline-purple">
                            Verification module
                        </span>
                    </article>

                </div>

                <h2 class="dashboard-section-title">Account information</h2>
                <p class="section-description">
                    These details identify the account currently logged in.
                </p>

                <div class="profile-panel">
                    <strong style="color:#3B0764;">Account name</strong>
                    <p><?php echo $name; ?></p>

                    <strong style="display:block;margin-top:18px;color:#3B0764;">
                        Email address
                    </strong>
                    <p><?php echo $email; ?></p>

                    <strong style="display:block;margin-top:18px;color:#3B0764;">
                        Account type
                    </strong>
                    <p>Landlord</p>
                </div>

            </section>
        </main>
    </div>
</body>

</html>