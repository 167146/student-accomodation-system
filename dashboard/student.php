<?php

require_once "../auth/auth.php";

requireRole("student");

?>

<!DOCTYPE html>
<html>

<head>
    <title>Student Dashboard</title>
</head>

<body>

    <h1>Student Dashboard</h1>

    <p>
        Welcome,
        <?php echo htmlspecialchars($_SESSION["name"]); ?>!
    </p>

    <p>You are logged in as a student.</p>

    <a href="../public/logout.php">
        Logout
    </a>

</body>

</html>