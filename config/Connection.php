<?php
// C:\xampp\htdocs\FarmSystem\config\Database.php (or your connection file)

$host = 'localhost';
$db   = 'farm_system'; // Ensure this matches your actual database name
$user = 'root';          // Default XAMPP user
$pass = 'v1i1n1x1';              // Default XAMPP password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // This creates a PDO object, which matches the retrieveData function I gave you
    $conn = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage());
}
?>