<?php
require_once 'config/session.php';

session_destroy();
header("Location: login.php");
exit();
?>
