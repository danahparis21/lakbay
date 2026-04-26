<?php
// hiker frontend/logout.php - Destroy session and logout

session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page (go back one directory then into login and signup folder)
header('Location: ../login-and-signup/login.php');
exit;
?>