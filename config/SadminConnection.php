<?php
// config/SadminConnection.php
// Connects to the super admin control plane database (sadmin_farms)

define('SADMIN_DB_HOST', 'localhost');
define('SADMIN_DB_NAME', 'sadmin_farms');
define('SADMIN_DB_USER', 'root');       // Change to your MySQL super user
define('SADMIN_DB_PASS', 'v1i1n1x1');           // Change to your MySQL password

// This connection has CREATE DATABASE / CREATE USER privileges
define('SADMIN_DB_ROOT_USER', 'root');  // MySQL root for provisioning
define('SADMIN_DB_ROOT_PASS', 'v1i1n1x1');      // MySQL root password

try {
    $conn = new PDO(
        "mysql:host=" . SADMIN_DB_HOST . ";dbname=" . SADMIN_DB_NAME . ";charset=utf8mb4",
        SADMIN_DB_USER,
        SADMIN_DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'Control plane DB connection failed: ' . $e->getMessage()]));
}

// Root connection (used only during farm provisioning)
function getRootConn() {
    try {
        return new PDO(
            "mysql:host=" . SADMIN_DB_HOST . ";charset=utf8mb4",
            SADMIN_DB_ROOT_USER,
            SADMIN_DB_ROOT_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true, // needed for multi-statement DDL
            ]
        );
    } catch (PDOException $e) {
        throw new Exception('Root DB connection failed: ' . $e->getMessage());
    }
}
?>