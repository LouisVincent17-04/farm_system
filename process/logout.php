<?php
session_start();
unset($_SESSION['user']);
session_destroy();
header("Location: ../views/login.php?status=success&msg=Logged out successfully");
exit();
?>