<?php
session_start();

require_once "../config/database.php";

$error = "";
$email = "";

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
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user["password"])) {
                if (!in_array($user["role"], ["student", "landlord"], true)) {
                    $error = "This account cannot use this login page.";
                } else {
                    session_regenerate_id(true);

                    $_SESSION["user_email"] = $user["email"];
                    $_SESSION["name"] = $user["name"];
                    $_SESSION["role"] = $user["role"];

                    if ($user["role"] === "student") {
                        header("Location: ../dashboard/student.php");
                        exit;
                    }

                    header("Location: ../dashboard/landlord.php");
                    exit;
                }
            } else {
                $error = "Incorrect email or password.";
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = "We could not complete your login. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Unimtaa</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <div class="auth-page">

        <div class="auth-image">
            <img src="https://images.unsplash.com/photo-1560448204-603b3fc33ddc?auto=format&fit=crop&w=1000&q=85"
                alt="Student accommodation">

            <div class="auth-image-overlay">
                <div class="brand" style="margin-bottom:40px;">
                    <div class="brand-icon">🏠</div>
                    <div>
                        <div style="font-size:21px;font-weight:800;">CampusNest</div>
                        <small>Student Accommodation</small>
                    </div>
                </div>

                <h1>Welcome back<br>to your next home.</h1>

                <p>
                    Discover accommodation, compare properties
                    and make informed housing decisions.
                </p>
            </div>
        </div>

        <div class="auth-form-side">
            <div class="auth-card">

                <div class="auth-logo">
                    <div style="font-size:13px;color:#6D28D9;font-weight:700;">
                        Unimtaa
                    </div>
                </div>

                <h2>Welcome back 👋</h2>

                <p class="auth-card-subtitle">
                    Login to continue exploring accommodation.
                </p>

                <?php if (isset($_GET["registered"])): ?>
                <div
                    style="background:#DCFCE7;color:#166534;padding:12px;border-radius:10px;font-size:13px;margin-bottom:20px;">
                    Your account was created. Please log in.
                </div>
                <?php endif; ?>

                <?php if ($error !== ""): ?>
                <div
                    style="background:#FEE2E2;color:#991B1B;padding:12px;border-radius:10px;font-size:13px;margin-bottom:20px;">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="login.php">

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="you@example.com"
                            value="<?php echo htmlspecialchars($email, ENT_QUOTES, "UTF-8"); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter your password"
                            required>
                    </div>

                    <button type="submit" class="btn btn-purple auth-submit">
                        Login to Unimtaa →
                    </button>

                </form>

                <div class="auth-footer">
                    Don't have an account?
                    <a href="register.php">Create one</a>
                </div>

                <div style="text-align:center;margin-top:30px;">
                    <a href="index.php" style="color:#81788A;font-size:12px;">
                        ← Back to home
                    </a>
                </div>

            </div>
        </div>

    </div>

</body>

</html>