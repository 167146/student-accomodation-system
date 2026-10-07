<?php

session_start();

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get form information
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $role = $_POST["role"] ?? "";


    // ==========================================
    // VALIDATION
    // ==========================================

    if (
        empty($name) ||
        empty($email) ||
        empty($password) ||
        empty($confirmPassword) ||
        empty($role)
    ) {

        $error = "Please fill in all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif ($password !== $confirmPassword) {

        $error = "Passwords do not match.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters long.";

    } elseif (!in_array($role, ["student", "landlord"], true)) {

        // Admin registration is NOT allowed here.
        $error = "Please select either Student or Landlord.";

    } else {

        // ==========================================
        // CHECK WHETHER EMAIL ALREADY EXISTS
        // NO ID USED
        // ==========================================

        $stmt = $pdo->prepare(
            "SELECT email
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->execute([$email]);

        $existingUser = $stmt->fetch();


        if ($existingUser) {

            $error = "An account with this email already exists.";

        } else {

            // ==========================================
            // HASH THE PASSWORD
            // ==========================================

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            // ==========================================
            // INSERT USER INTO DATABASE
            // ==========================================

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


            // ==========================================
            // REGISTRATION SUCCESSFUL
            // ==========================================

            header("Location: login.php?registered=1");
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

    <title>Create Account | CampusNest</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>


<body>

    <div class="auth-page">


        <!-- ==========================================
         LEFT IMAGE
         ========================================== -->

        <div class="auth-image">

            <img src="https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1000&q=85"
                alt="Student accommodation">

            <div class="auth-image-overlay">

                <h1>
                    Your next home<br>
                    is waiting.
                </h1>

                <p>

                    Join CampusNest and discover
                    accommodation that fits your
                    lifestyle, budget and university
                    journey.

                </p>

            </div>

        </div>


        <!-- ==========================================
         REGISTRATION FORM
         ========================================== -->

        <div class="auth-form-side">

            <div class="auth-card">


                <!-- LOGO -->

                <div class="brand" style="margin-bottom:25px;">

                    <div class="brand-icon">
                        🏠
                    </div>

                    <div>

                        <div class="brand-name">
                            CampusNest
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


                <!-- ==========================================
                 ERROR MESSAGE
                 ========================================== -->

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


                <!-- ==========================================
                 REGISTRATION FORM
                 ========================================== -->

                <form method="POST" action="">


                    <!-- NAME -->

                    <div class="form-group">

                        <label>
                            Full Name
                        </label>

                        <input type="text" name="name" class="form-control" placeholder="Your full name"
                            value="<?php echo htmlspecialchars($_POST["name"] ?? ""); ?>" required>

                    </div>


                    <!-- EMAIL -->

                    <div class="form-group">

                        <label>
                            Email Address
                        </label>

                        <input type="email" name="email" class="form-control" placeholder="you@example.com"
                            value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>" required>

                    </div>


                    <!-- PHONE -->

                    <div class="form-group">

                        <label>
                            Phone Number
                        </label>

                        <input type="tel" name="phone" class="form-control" placeholder="07XX XXX XXX"
                            value="<?php echo htmlspecialchars($_POST["phone"] ?? ""); ?>">

                    </div>


                    <!-- ROLE -->

                    <div class="form-group">

                        <label>
                            I am registering as
                        </label>


                        <div class="role-selection">


                            <!-- STUDENT -->

                            <label class="role-option">

                                <input type="radio" name="role" value="student" <?php
                                    if (($_POST["role"] ?? "") === "student") {
                                        echo "checked";
                                    }
                                    ?> required>

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


                            <!-- LANDLORD -->

                            <label class="role-option">

                                <input type="radio" name="role" value="landlord" <?php
                                    if (($_POST["role"] ?? "") === "landlord") {
                                        echo "checked";
                                    }
                                    ?>>

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


                    <!-- PASSWORD -->

                    <div class="form-group">

                        <label>
                            Password
                        </label>

                        <input type="password" name="password" class="form-control" placeholder="Create a password"
                            required>

                    </div>


                    <!-- CONFIRM PASSWORD -->

                    <div class="form-group">

                        <label>
                            Confirm Password
                        </label>

                        <input type="password" name="confirm_password" class="form-control"
                            placeholder="Repeat your password" required>

                    </div>


                    <!-- REGISTER BUTTON -->

                    <button type="submit" class="btn btn-purple auth-submit">

                        Create Account →

                    </button>


                </form>


                <!-- LOGIN LINK -->

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