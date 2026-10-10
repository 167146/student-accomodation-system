<?php

session_start();

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {

        $error = "Please enter your email and password.";

    } else {

        $stmt = $pdo->prepare(
            "SELECT email, name, password, role
             FROM users
             WHERE email = ?
             AND role = 'landlord'
             LIMIT 1"
        );

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password"])) {

            $_SESSION["email"] = $user["email"];
            $_SESSION["name"] = $user["name"];
            $_SESSION["role"] = $user["role"];

            header("Location: ../dashboard/landlord.php");
            exit;

        } else {

            $error = "Invalid landlord email or password.";
        }
    }
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Landlord Login | UniMtaa</title>

</head>

<body>

    <h1>Landlord Login</h1>

    <?php if (!empty($error)): ?>

    <p style="color:red;">
        <?php echo htmlspecialchars($error); ?>
    </p>

    <?php endif; ?>

    <?php if (isset($_GET["registered"])): ?>

    <p style="color:green;">
        Registration successful. You can now log in.
    </p>

    <?php endif; ?>

    <form method="POST">

        <label>Email</label><br>

        <input type="email" name="email" required>

        <br><br>

        <label>Password</label><br>

        <input type="password" name="password" required>

        <br><br>

        <button type="submit">
            Landlord Login
        </button>

    </form>

    <p>
        Don't have an account?
        <a href="landlord_register.php">
            Register
        </a>
    </p>

    <p>
        Are you a student?
        <a href="student_login.php">
            Student Login
        </a>
    </p>

</body>

</html>