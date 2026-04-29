<?php
// C:\xampp\htdocs\FarmSystem\config\Database.php

/* Define specific settings kept for compatibility with the rest of your PHPJabbers script */
$SETTINGS["USERS"] = ''; 
$SETTINGS["USERSData"] = ''; 
$SETTINGS["UserBadge"] = '';
$SETTINGS["UserName"] = '';

// Your specific database credentials
$host = '192.168.1.131';
$db   = 'sadmin_farms';
$user = 'pisadmin';
$pass = 'adminpis';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Creates a PDO object
    $conn = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("  Connection failed: " . $e->getMessage());
}
?>