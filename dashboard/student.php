<?php

session_start();

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {

        $error = "Please enter your email and password.";

    } else {

        // Filter by role in the query itself, so a landlord
        // or admin account can never authenticate on this form.
        $stmt = $pdo->prepare(
            "SELECT * FROM users WHERE email = ? AND role = 'student' LIMIT 1"
        );

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password"])) {

            $_SESSION["user_email"] = $user["email"];
            $_SESSION["name"] = $user["name"];
            $_SESSION["role"] = $user["role"];

            header("Location: ../dashboard/student.php");
            exit;

        } else {

            $error = "Incorrect email or password.";

        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login | CampusNest</title>
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

    <div class="auth-page">

        <!-- LEFT IMAGE -->
        <div class="auth-image">

            <img src="https://images.unsplash.com/photo-1560448204-603b3fc33ddc?auto=format&fit=crop&w=1000&q=85"
                alt="Beautiful student accommodation">

            <div class="auth-image-overlay">

                <div class="brand" style="margin-bottom:40px;">
                    <div class="brand-icon">🏠</div>
                    <div>
                        <div style="font-size:21px; font-weight:800;">CampusNest</div>
                        <small>Student Accommodation</small>
                    </div>
                </div>

                <h1>Welcome back<br>to your next home.</h1>

                <p>Discover accommodation, compare properties and make informed housing decisions.</p>

            </div>

        </div>

        <!-- LOGIN FORM -->
        <div class="auth-form-side">

            <div class="auth-card">

                <div class="auth-logo">
                    <div style="font-size:13px; color:#6D28D9; font-weight:700;">CampusNest &middot; Student</div>
                </div>

                <h2>Welcome back 👋</h2>
                <p class="auth-card-subtitle">Login to continue exploring accommodation.</p>

                <?php if (!empty($error)): ?>
                <div
                    style="background:#FEE2E2; color:#991B1B; padding:12px; border-radius:10px; font-size:13px; margin-bottom:20px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <form method="POST">

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter your password"
                            required>
                    </div>

                    <button type="submit" class="btn btn-purple auth-submit">Login to CampusNest →</button>

                </form>

                <div class="auth-footer">
                    Don't have an account?
                    <a href="student_register.php">Create one</a>
                </div>

                <div style="text-align:center; margin-top:16px;">
                    <a href="landlord_login.php" style="color:#81788A; font-size:12px;">
                        Are you a landlord? Login here
                    </a>
                </div>

                <div style="text-align:center; margin-top:14px;">
                    <a href="index.php" style="color:#81788A; font-size:12px;">← Back to home</a>
                </div>

            </div>

        </div>

    </div>

</body>

</html>