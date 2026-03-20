<?php
session_start();
unset($_SESSION['user']);
session_destroy();
header("Location: ../globalxadminzportal/login.php?status=success&msg=Logged out successfully");
exit();
?>