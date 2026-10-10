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

        $stmt = $pdo->prepare(
            "SELECT * FROM users WHERE email = ? AND role = 'landlord' LIMIT 1"
        );

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password"])) {

           $_SESSION["email"] = $user["email"];
$_SESSION["user_email"] = $user["email"];
$_SESSION["name"] = $user["name"];
$_SESSION["role"] = $user["role"];

            header("Location: ../dashboard/landlord.php");
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
    <title>Landlord Login | UniMtaa</title>
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
                        <div style="font-size:21px; font-weight:800;">UniMtaa</div>
                        <small>Student Accommodation</small>
                    </div>
                </div>

                <h1>Manage your properties,<br>trusted by students.</h1>

                <p>List accommodation, track bookings and build trust with verified listings.</p>

            </div>

        </div>

        <!-- LOGIN FORM -->
        <div class="auth-form-side">

            <div class="auth-card">

                <div class="auth-logo">
                    <div style="font-size:13px; color:#6D28D9; font-weight:700;">UniMtaa &middot; Landlord</div>
                </div>

                <h2>Welcome back 👋</h2>
                <p class="auth-card-subtitle">Login to manage your listings.</p>

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
                    <a href="landlord_register.php">Create one</a>
                </div>

                <div style="text-align:center; margin-top:16px;">
                    <a href="landlord_login.php" style="color:#81788A; font-size:12px;">
                        Are you a Landlord? Login here
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