```php
<?php
session_start();

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (
        $name === "" ||
        $email === "" ||
        $phone === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {
        $error = "Please fill in all the fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } elseif ($password !== $confirm_password) {
        $error = "The passwords do not match.";

    } elseif (strlen($password) < 8) {
        $error = "Your password must have at least 8 characters.";

    } else {
        try {
            // Check whether this email is already registered.
            $stmt = $pdo->prepare(
                "SELECT email FROM users WHERE email = ? LIMIT 1"
            );
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error = "This email is already registered.";

            } else {
                // Securely hash the password.
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Register the user specifically as a landlord.
                $stmt = $pdo->prepare(
                    "INSERT INTO users
                    (name, email, phone, password, role)
                    VALUES (?, ?, ?, ?, 'landlord')"
                );

                $stmt->execute([
                    $name,
                    $email,
                    $phone,
                    $hashed_password
                ]);

                // Send the landlord to the landlord login page.
                header("Location: landlord_login.php?registered=1");
                exit;
            }

        } catch (PDOException $e) {
            $error = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landlord Registration | UniMtaa</title>
</head>

<body>

    <h1>Join UniMtaa as a Landlord</h1>
    <p>Create an account to list and manage your properties.</p>

    <?php if ($error !== ""): ?>
    <p style="color: red;">
        <?= htmlspecialchars($error) ?>
    </p>
    <?php endif; ?>

    <form method="POST" action="">

        <label for="name">Full Name</label><br>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST["name"] ?? "") ?>" required><br><br>

        <label for="email">Email Address</label><br>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
            required><br><br>

        <label for="phone">Phone Number</label><br>
        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($_POST["phone"] ?? "") ?>"
            required><br><br>

        <label for="password">Password</label><br>
        <input type="password" id="password" name="password" minlength="8" required><br><br>

        <label for="confirm_password">Confirm Password</label><br>
        <input type="password" id="confirm_password" name="confirm_password" minlength="8" required><br><br>

        <button type="submit">Create Landlord Account</button>

    </form>

    <p>
        Already have an account?
        <a href="landlord_login.php">Log in here</a>
    </p>

    <p>
        Looking for student registration?
        <a href="student_register.php">Register as a student</a>
    </p>

    <p>
        <a href="index.php">Back to home</a>
    </p>

</body>

</html>