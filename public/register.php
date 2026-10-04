<?php

require_once "../config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $password = $_POST["password"];
    $confirmPassword = $_POST["confirm_password"];
    $role = $_POST["role"];

    // Check that all fields are filled
    if (
        empty($name) ||
        empty($email) ||
        empty($phone) ||
        empty($password) ||
        empty($confirmPassword) ||
        empty($role)
    ) {
        $message = "Please fill in all fields.";
    }

    // Check email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    }

    // Check password confirmation
    elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
    }

    // Only students and landlords can register
    elseif ($role !== "student" && $role !== "landlord") {
        $message = "Invalid registration role.";
    }

    else {

        // Check whether email already exists
        $check = $pdo->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $check->execute([$email]);

        if ($check->fetch()) {

            $message = "An account with this email already exists.";

        } else {

            // Hash the password
            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Insert the user into the database
            $sql = "INSERT INTO users
                    (name, email, password, phone, role)
                    VALUES (?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $name,
                $email,
                $passwordHash,
                $phone,
                $role
            ]);

            $message = "Registration successful! You can now log in.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register - Student Accommodation</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

    <h1>Create Account</h1>

    <?php if (!empty($message)): ?>

    <p>
        <?php echo htmlspecialchars($message); ?>
    </p>

    <?php endif; ?>

    <form method="POST" action="">

        <label for="name">Name</label>
        <br>

        <input type="text" id="name" name="name" required>

        <br><br>

        <label for="email">Email</label>
        <br>

        <input type="email" id="email" name="email" required>

        <br><br>

        <label for="phone">Phone</label>
        <br>

        <input type="text" id="phone" name="phone" required>

        <br><br>

        <label for="password">Password</label>
        <br>

        <input type="password" id="password" name="password" required>

        <br><br>

        <label for="confirm_password">
            Confirm Password
        </label>
        <br>

        <input type="password" id="confirm_password" name="confirm_password" required>

        <br><br>

        <label for="role">Role</label>
        <br>

        <select id="role" name="role" required>

            <option value="">Select Role</option>

            <option value="student">
                Student
            </option>

            <option value="landlord">
                Landlord
            </option>

        </select>

        <br><br>

        <button type="submit">
            REGISTER
        </button>

    </form>

    <p>
        Already have an account?
        <a href="login.php">Login</a>
    </p>

</body>

</html>