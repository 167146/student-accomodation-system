<?php

session_start();

require_once "../config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {

        $message = "Please enter your email and password.";

    } else {

        // Find user by email
        $stmt = $pdo->prepare(
            "SELECT * FROM users WHERE email = ?"
        );

        $stmt->execute([$email]);

        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password"])) {

            // Store user information in session
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["name"] = $user["name"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"];

            // Send user to the correct dashboard
            if ($user["role"] === "student") {

                header("Location: ../dashboard/student.php");
                exit;

            } elseif ($user["role"] === "landlord") {

                header("Location: ../dashboard/landlord.php");
                exit;

            } elseif ($user["role"] === "admin") {

                header("Location: ../dashboard/admin.php");
                exit;
            }

        } else {

            $message = "Invalid email or password.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Student Accommodation</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

    <h1>Login</h1>

    <?php if (!empty($message)): ?>

    <p>
        <?php echo htmlspecialchars($message); ?>
    </p>

    <?php endif; ?>

    <form method="POST" action="">

        <label for="email">
            Email
        </label>

        <br>

        <input type="email" id="email" name="email" required>

        <br><br>

        <label for="password">
            Password
        </label>

        <br>

        <input type="password" id="password" name="password" required>

        <br><br>

        <button type="submit">
            LOGIN
        </button>

    </form>

    <p>
        Don't have an account?
        <a href="register.php">Register</a>
    </p>

</body>

</html>