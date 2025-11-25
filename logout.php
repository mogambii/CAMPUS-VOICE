<?php
require_once 'includes/functions.php';

if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'logout');
}

session_destroy();
header('Location: login.php');
exit();
?>
