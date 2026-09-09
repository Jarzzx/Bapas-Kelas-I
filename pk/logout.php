<?php
require_once '../shared/config/auth.php';

// Logout
session_unset();
session_destroy();

header('Location: login.php');
exit;
?>

