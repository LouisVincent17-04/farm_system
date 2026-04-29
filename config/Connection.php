<?php
// FarmSystem/config/Connection.php

if (session_status() === PHP_SESSION_NONE) session_start();

$host = '192.168.1.131';
$db   = 'sadmin_farms';
$user = 'pisadmin';
$pass = 'adminpis';
$charset = 'utf8mb4';

// ── Dynamic DB selection ──────────────────────────────────────────────────────
// When the owner selects a farm from the portal (my_farms.php), the chosen
// farm's db_name is stored in $_SESSION['active_farm']['db_name'].
// If no farm is selected yet, redirect back to the portal to choose one.
// ─────────────────────────────────────────────────────────────────────────────
if (!empty($_SESSION['active_farm']['db_name'])) {
    $db = $_SESSION['active_farm']['db_name'];
} else {
    // No active farm in session — send user back to the portal to select one
    header('Location: ../globalxadminzportal/my_farms.php');
    exit;
}

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("  Connection failed: " . $e->getMessage());
}
?>