<?php

require_once "../auth/auth.php";

requireRole("admin");

?>

<!DOCTYPE html>
<html>

<head>
    <title>Admin Dashboard</title>
</head>

<body>

    <h1>Administrator Dashboard</h1>

    <p>
        Welcome,
        <?php echo htmlspecialchars($_SESSION["name"]); ?>!
    </p>

    <p>You are logged in as an administrator.</p>

    <a href="../public/logout.php">
        Logout
    </a>

</body>

</html>