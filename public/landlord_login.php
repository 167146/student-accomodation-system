<?php
session_start();

require_once __DIR__ . "/../config/database.php";

$error = "";

if (
    isset($_SESSION["email"]) &&
    ($_SESSION["role"] ?? "") === "landlord"
) {
    header("Location: ../dashboard/landlord.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT email, name, password, role
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $user &&
                $user["role"] === "landlord" &&
                password_verify($password, $user["password"])
            ) {
                session_regenerate_id(true);

                $_SESSION["email"] = $user["email"];
                $_SESSION["name"] = $user["name"];
                $_SESSION["role"] = "landlord";

                header("Location: ../dashboard/landlord.php");
                exit;
            }

            $error = "Incorrect landlord email or password.";

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = "A database error occurred. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Landlord Login | UniMtaa</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
    .landlord-login-page {
        min-height: 100vh;
        display: grid;
        grid-template-columns: 1fr 1fr;
        background: #FAF7FF;
    }

    .landlord-login-image {
        min-height: 100vh;
        position: relative;
        background:
            linear-gradient(135deg,
                rgba(59, 7, 100, .88),
                rgba(109, 40, 217, .58)),
            url("https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1400&q=85") center/cover no-repeat;
        display: flex;
        align-items: center;
        padding: 10%;
        color: white;
    }

    .landlord-login-image h1 {
        font-size: clamp(38px, 4vw, 58px);
        line-height: 1.1;
        margin-bottom: 20px;
    }

    .landlord-login-image p {
        max-width: 440px;
        color: rgba(255, 255, 255, .88);
        font-size: 16px;
    }

    .landlord-login-form-side {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px;
    }

    .landlord-login-card {
        width: 100%;
        max-width: 440px;
        background: white;
        padding: 38px;
        border-radius: 24px;
        border: 1px solid #E9E1F2;
        box-shadow: 0 18px 55px rgba(76, 29, 149, .10);
    }

    .landlord-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 35px;
    }

    .landlord-brand-icon {
        width: 48px;
        height: 48px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, #6D28D9, #A78BFA);
        border-radius: 15px;
        font-size: 24px;
    }

    .landlord-brand strong {
        display: block;
        color: #4C1D95;
        font-size: 22px;
    }

    .landlord-brand small {
        color: #81788A;
    }

    .landlord-login-card h2 {
        color: #241536;
        font-size: 29px;
        margin-bottom: 8px;
    }

    .landlord-login-intro {
        color: #81788A;
        margin-bottom: 28px;
        font-size: 14px;
    }

    .landlord-error {
        background: #FEE2E2;
        color: #991B1B;
        padding: 12px 14px;
        border-radius: 12px;
        margin-bottom: 18px;
        font-size: 14px;
    }

    .landlord-login-card .form-group {
        margin-bottom: 20px;
    }

    .landlord-login-card .form-control {
        min-height: 48px;
    }

    .landlord-login-card .auth-submit {
        width: 100%;
        min-height: 49px;
        margin-top: 5px;
    }

    .landlord-login-footer {
        margin-top: 24px;
        text-align: center;
        color: #81788A;
        font-size: 13px;
    }

    .landlord-login-footer a {
        color: #6D28D9;
        font-weight: 700;
    }

    @media (max-width: 800px) {
        .landlord-login-page {
            grid-template-columns: 1fr;
        }

        .landlord-login-image {
            min-height: 260px;
            padding: 35px;
        }

        .landlord-login-image h1 {
            font-size: 35px;
        }

        .landlord-login-form-side {
            padding: 24px;
        }

        .landlord-login-card {
            padding: 27px;
        }
    }
    </style>
</head>

<body>
    <div class="landlord-login-page">

        <section class="landlord-login-image">
            <div>
                <h1>Your properties.<br>Your opportunities.</h1>
                <p>
                    Welcome to UniMtaa. Manage your accommodation
                    listings and connect with students looking for a place
                    to call home.
                </p>
            </div>
        </section>

        <main class="landlord-login-form-side">
            <div class="landlord-login-card">

                <div class="landlord-brand">
                    <div class="landlord-brand-icon">🏠</div>
                    <div>
                        <strong>UniMtaa</strong>
                        <small>Student Accommodation</small>
                    </div>
                </div>

                <h2>Welcome back!</h2>
                <p class="landlord-login-intro">
                    Log in to your landlord account.
                </p>

                <?php if ($error !== ""): ?>
                <div class="landlord-error">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="landlord-email">Email address</label>
                        <input type="email" name="email" class="form-control" placeholder="you@example.com"
                            autocomplete="email" required>
                    </div>

                    <div class="form-group">
                        <label for="landlord-password">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter your password"
                            autocomplete="current-password" required>
                    </div>

                    <button type="submit" class="btn btn-purple auth-submit">
                        Log In to Dashboard →
                    </button>
                </form>

                <div class="landlord-login-footer">
                    Looking for a student account?
                    <a href="login.php">Student Login</a>
                    <p style="margin-top: 12px;">
                        Don't have a landlord account?
                        <a href="landlord_register.php">Register here</a>
                    </p>
                    <p style="margin-top: 12px;">
                        <a href="index.php">← Back to home</a>
                    </p>
                </div>

            </div>
        </main>
    </div>
</body>

</html>