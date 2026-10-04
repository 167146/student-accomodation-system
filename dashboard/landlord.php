<?php

require_once "../auth/auth.php";

requireRole("landlord");

?>

<!DOCTYPE html>
<html>

<head>
    <title>Landlord Dashboard</title>
</head>

<body>

    <h1>Landlord Dashboard</h1>

    <p>
        Welcome,
        <?php echo htmlspecialchars($_SESSION["name"]); ?>!
    </p>

    <p>You are logged in as a landlord.</p>

    <a href="../public/logout.php">
        Logout
    </a>

</body>

</html>