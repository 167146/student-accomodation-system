<?php
session_start();

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {

        $error = "Please fill in all fields.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } else {

        $stmt = $pdo->prepare(
            "SELECT email
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->execute([$email]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {

            $error = "Email is already registered.";

        } else {

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password, role)
                 VALUES (?, ?, ?, 'landlord')"
            );

            if ($stmt->execute([$name, $email, $hashed_password])) {

                $_SESSION["email"] = $email;
                $_SESSION["name"] = $name;
                $_SESSION["role"] = "landlord";

                header("Location: ../dashboard/landlord.php");
                exit;

            } else {

                $error = "Registration failed. Please try again.";
            }
        }
    }
}

?>