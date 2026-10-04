<?php

session_start();

// Remove all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Send the user back to login
header("Location: login.php");
exit;

?>