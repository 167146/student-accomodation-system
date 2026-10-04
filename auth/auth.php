<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * Check whether the user is logged in.
 */
function requireLogin()
{
    if (!isset($_SESSION["user_id"])) {

        header("Location: ../public/login.php");
        exit;
    }
}

/*
 * Check whether the logged-in user
 * has the required role.
 */
function requireRole($role)
{
    requireLogin();

    if ($_SESSION["role"] !== $role) {

        die("Access denied.");
    }
}

?>