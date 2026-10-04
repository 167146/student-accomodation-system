<?php

session_start();

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $password = $_POST["password"];
    $confirmPassword = $_POST["confirm_password"];
    $role = $_POST["role"];

    if (
        empty($name) ||
        empty($email) ||
        empty($password) ||
        empty($confirmPassword) ||
        empty($role)
    ) {

        $error = "Please fill in all required fields.";

    } elseif ($password !== $confirmPassword) {

        $error = "Passwords do not match.";

    } elseif (!in_array($role, ["student", "landlord"])) {

        $error = "Invalid account type.";

    } else {

        $stmt = $pdo->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $stmt->execute([$email]);

        if ($stmt->fetch()) {

            $error = "An account with this email already exists.";

        } else {

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare(
                "INSERT INTO users
                (name, email, password, phone, role)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $name,
                $email,
                $passwordHash,
                $phone,
                $role
            ]);

            header("Location: login.php");
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Create Account | CampusNest
    </title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

    <div class="auth-page">


        <!-- IMAGE -->

        <div class="auth-image">

            <img src="https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1000&q=85"
                alt="Student apartment">

            <div class="auth-image-overlay">

                <h1>
                    Your next home<br>
                    is waiting.
                </h1>

                <p>
                    Join UniMtaaand discover
                    accommodation that fits your lifestyle,
                    budget and university journey.
                </p>

            </div>

        </div>


        <!-- FORM -->

        <div class="auth-form-side">

            <div class="auth-card">

                <div class="brand" style="margin-bottom:25px;">

                    <div class="brand-icon">
                        🏠
                    </div>

                    <div>

                        <div class="brand-name">
                            UniMtaa
                        </div>

                        <span class="brand-subtitle">
                            Student Accommodation
                        </span>

                    </div>

                </div>


                <h2>
                    Create your account
                </h2>

                <p class="auth-card-subtitle">
                    Start exploring better accommodation today.
                </p>


                <?php if (!empty($error)): ?>

                <div style="
                    background:#FEE2E2;
                    color:#991B1B;
                    padding:12px;
                    border-radius:10px;
                    font-size:13px;
                    margin-bottom:20px;
                ">

                    <?php echo htmlspecialchars($error); ?>

                </div>

                <?php endif; ?>


                <form method="POST">


                    <div class="form-group">

                        <label>
                            Full Name
                        </label>

                        <input type="text" name="name" class="form-control" placeholder="Your full name" required>

                    </div>


                    <div class="form-group">

                        <label>
                            Email Address
                        </label>

                        <input type="email" name="email" class="form-control" placeholder="you@example.com" required>

                    </div>


                    <div class="form-group">

                        <label>
                            Phone Number
                        </label>

                        <input type="tel" name="phone" class="form-control" placeholder="07XX XXX XXX">

                    </div>


                    <div class="form-group">

                        <label>
                            I am registering as
                        </label>


                        <div class="role-selection">


                            <label class="role-option">

                                <input type="radio" name="role" value="student" required>

                                <div class="role-option-icon">
                                    🎓
                                </div>

                                <strong>
                                    Student
                                </strong>

                                <small>
                                    Find accommodation
                                </small>

                            </label>


                            <label class="role-option">

                                <input type="radio" name="role" value="landlord">

                                <div class="role-option-icon">
                                    🏠
                                </div>

                                <strong>
                                    Landlord
                                </strong>

                                <small>
                                    List accommodation
                                </small>

                            </label>


                        </div>

                    </div>


                    <div class="form-group">

                        <label>
                            Password
                        </label>

                        <input type="password" name="password" class="form-control" placeholder="Create a password"
                            required>

                    </div>


                    <div class="form-group">

                        <label>
                            Confirm Password
                        </label>

                        <input type="password" name="confirm_password" class="form-control"
                            placeholder="Repeat your password" required>

                    </div>


                    <button type="submit" class="btn btn-purple auth-submit">

                        Create Account →

                    </button>


                </form>


                <div class="auth-footer">

                    Already have an account?

                    <a href="login.php">
                        Login
                    </a>

                </div>


            </div>

        </div>

    </div>

</body>

</html>