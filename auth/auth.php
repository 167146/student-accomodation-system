<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * Maps each role to its own login page, so an
 * unauthenticated visit always lands on the
 * correct form instead of a generic one.
 */
function loginPageFor($role)
{
    $pages = [
        "student"  => "../public/student_login.php",
        "landlord" => "../public/landlord_login.php",
        "admin"    => "../public/admin_login.php",
    ];

    return $pages[$role] ?? "../public/student_login.php";
}

/*
 * Check whether the user is logged in at all.
 * $expectedRole lets the redirect send them to the
 * right login form rather than always the student one.
 */
function requireLogin($expectedRole = "student")
{
    if (!isset($_SESSION["user_email"])) {

        header("Location: " . loginPageFor($expectedRole));
        exit;
    }
}

/*
 * Check whether the logged-in user has the required role.
 * Redirects to that role's own login page if not logged in,
 * and blocks access outright if logged in as the wrong role.
 */
function requireRole($role)
{
    requireLogin($role);

    if ($_SESSION["role"] !== $role) {

        die("Access denied.");
    }
}

?>