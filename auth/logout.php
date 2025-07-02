<?php
require_once 'session.php';

// Destroy the session
destroySession();

// Redirect to login page with message
$_SESSION['message'] = "You have been successfully logged out.";
$_SESSION['message_type'] = "info";

header("Location: ../index.php");
exit;
?>