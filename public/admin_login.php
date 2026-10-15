<?php
session_start();
require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $pdo->prepare(
        "SELECT email, name, password, role
         FROM users
         WHERE email = ? AND role = 'admin'
         LIMIT 1"
    );

    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user["password"])) {
        session_regenerate_id(true);

        $_SESSION["email"] = $user["email"];
        $_SESSION["name"] = $user["name"];
        $_SESSION["role"] = $user["role"];

        header("Location: ../dashboard/admin.php");
        exit;
    } else {
        $error = "Invalid admin email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | UniMtaa</title>

    <style>
    :root {
        --burgundy: #800020;
        --burgundy-dark: #4A0E1F;
        --burgundy-deep: #2B0612;
        --burgundy-light: #F9EEF1;
        --burgundy-border: #E8C9D1;
        --text: #1F1A1C;
        --muted: #6B6266;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
        color: var(--text);
        background: #fff;
        min-height: 100vh;
    }

    .admin-page {
        display: grid;
        grid-template-columns: 1.05fr 1fr;
        min-height: 100vh;
    }

    /* ---------- LEFT PANEL ---------- */

    .admin-side {
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 48px 56px;
        color: #fff;
        background:
            radial-gradient(circle at 15% 20%, rgba(255, 255, 255, 0.10) 0, transparent 45%),
            radial-gradient(circle at 85% 85%, rgba(255, 255, 255, 0.07) 0, transparent 40%),
            linear-gradient(145deg, var(--burgundy) 0%, var(--burgundy-dark) 55%, var(--burgundy-deep) 100%);
        overflow: hidden;
    }

    .admin-side::before,
    .admin-side::after {
        content: "";
        position: absolute;
        border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, 0.12);
    }

    .admin-side::before {
        width: 420px;
        height: 420px;
        top: -140px;
        right: -140px;
    }

    .admin-side::after {
        width: 300px;
        height: 300px;
        bottom: -100px;
        left: -100px;
    }

    .brand {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .brand-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.25);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .brand-name {
        font-size: 22px;
        font-weight: 700;
        letter-spacing: 0.3px;
    }

    .brand-sub {
        font-size: 12px;
        opacity: 0.7;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .admin-hero {
        position: relative;
        z-index: 1;
    }

    .admin-hero h1 {
        font-size: 40px;
        line-height: 1.15;
        font-weight: 700;
        margin-bottom: 16px;
    }

    .admin-hero p {
        font-size: 16px;
        line-height: 1.6;
        opacity: 0.8;
        max-width: 420px;
    }

    .admin-points {
        position: relative;
        z-index: 1;
        display: flex;
        gap: 28px;
        flex-wrap: wrap;
        font-size: 13px;
        opacity: 0.85;
    }

    .admin-points span::before {
        content: "✓";
        margin-right: 8px;
        color: #F4C6D0;
        font-weight: 700;
    }

    /* ---------- RIGHT PANEL ---------- */

    .form-side {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 24px;
        background: var(--burgundy-light);
    }

    .login-card {
        width: 100%;
        max-width: 420px;
        background: #fff;
        border-radius: 20px;
        padding: 40px 36px;
        box-shadow: 0 20px 50px rgba(128, 0, 32, 0.12);
        border: 1px solid var(--burgundy-border);
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--burgundy-light);
        color: var(--burgundy);
        border: 1px solid var(--burgundy-border);
        font-size: 12px;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 999px;
        margin-bottom: 18px;
    }

    .login-card h2 {
        font-size: 26px;
        color: var(--burgundy-dark);
        margin-bottom: 6px;
    }

    .subtitle {
        color: var(--muted);
        font-size: 14px;
        margin-bottom: 26px;
    }

    .error-box {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #FDECEC;
        color: #8A1C1C;
        border: 1px solid #F3C2C2;
        border-left: 4px solid #B42318;
        padding: 12px 14px;
        border-radius: 10px;
        font-size: 13.5px;
        margin-bottom: 20px;
    }

    .form-group {
        margin-bottom: 18px;
    }

    .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--burgundy-dark);
        margin-bottom: 7px;
    }

    .input-wrap {
        position: relative;
    }

    .input-wrap .icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 16px;
        opacity: 0.6;
        pointer-events: none;
    }

    .input-wrap input {
        width: 100%;
        padding: 13px 44px 13px 42px;
        font-size: 15px;
        font-family: inherit;
        color: var(--text);
        background: #fff;
        border: 1.5px solid var(--burgundy-border);
        border-radius: 12px;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .input-wrap input::placeholder {
        color: #B8AEB2;
    }

    .input-wrap input:focus {
        border-color: var(--burgundy);
        box-shadow: 0 0 0 4px rgba(128, 0, 32, 0.12);
    }

    .toggle-pw {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        color: var(--burgundy);
        padding: 6px 8px;
        border-radius: 6px;
    }

    .toggle-pw:hover {
        background: var(--burgundy-light);
    }

    .submit-btn {
        width: 100%;
        margin-top: 8px;
        padding: 14px;
        font-size: 15px;
        font-weight: 600;
        font-family: inherit;
        color: #fff;
        background: linear-gradient(135deg, var(--burgundy) 0%, var(--burgundy-dark) 100%);
        border: none;
        border-radius: 12px;
        cursor: pointer;
        box-shadow: 0 8px 20px rgba(128, 0, 32, 0.28);
        transition: transform 0.15s, box-shadow 0.15s;
    }

    .submit-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 26px rgba(128, 0, 32, 0.34);
    }

    .submit-btn:active {
        transform: translateY(0);
    }

    .card-footer {
        margin-top: 24px;
        padding-top: 18px;
        border-top: 1px solid var(--burgundy-border);
        text-align: center;
        font-size: 13px;
        color: var(--muted);
    }

    .card-footer a {
        color: var(--burgundy);
        font-weight: 600;
        text-decoration: none;
    }

    .card-footer a:hover {
        text-decoration: underline;
    }

    /* ---------- RESPONSIVE ---------- */

    @media (max-width: 900px) {
        .admin-page {
            grid-template-columns: 1fr;
        }

        .admin-side {
            display: none;
        }

        .form-side {
            min-height: 100vh;
            background:
                linear-gradient(160deg, var(--burgundy-light) 0%, #fff 100%);
        }
    }
    </style>
</head>

<body>

    <div class="admin-page">

        <!-- LEFT: BRANDING -->
        <div class="admin-side">

            <div class="brand">
                <div class="brand-icon">🏠</div>
                <div>
                    <div class="brand-name">UniMtaa</div>
                    <div class="brand-sub">Admin Portal</div>
                </div>
            </div>

            <div class="admin-hero">
                <h1>Manage the<br>platform with<br>confidence.</h1>
                <p>
                    Review listings, verify landlords and keep student
                    accommodation information accurate and trustworthy.
                </p>
            </div>

            <div class="admin-points">
                <span>Verify listings</span>
                <span>Manage users</span>
                <span>Moderate reviews</span>
            </div>

        </div>


        <!-- RIGHT: LOGIN FORM -->
        <div class="form-side">

            <div class="login-card">

                <div class="badge">🔒 Restricted access</div>

                <h2>Admin Login</h2>
                <p class="subtitle">Sign in to manage the platform.</p>

                <?php if ($error !== ""): ?>
                <div class="error-box">
                    ⚠️ <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <form method="POST">

                    <div class="form-group">
                        <label for="email">Email</label>
                        <div class="input-wrap">
                            <span class="icon">✉️</span>
                            <input type="email" id="email" name="email" placeholder="admin@example.com"
                                value="<?= htmlspecialchars($_POST["email"] ?? "") ?>" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <span class="icon">🔑</span>
                            <input type="password" id="password" name="password" placeholder="Enter your password"
                                required>
                            <button type="button" class="toggle-pw" id="togglePw">Show</button>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">Log In →</button>

                </form>

                <div class="card-footer">
                    Not an admin? <a href="login.php">Go to user login</a>
                </div>

            </div>

        </div>

    </div>

    <script>
    var pw = document.getElementById("password");
    var btn = document.getElementById("togglePw");

    btn.addEventListener("click", function() {
        var hidden = pw.type === "password";
        pw.type = hidden ? "text" : "password";
        btn.textContent = hidden ? "Hide" : "Show";
    });
    </script>

</body>

</html>