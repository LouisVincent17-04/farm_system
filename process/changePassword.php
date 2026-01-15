<?php
session_start();
header('Content-Type: application/json');

// 1. Suppress HTML errors
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // 2. Check Database Connection
    if (!file_exists('../config/Connection.php')) {
        throw new Exception("Configuration file not found.");
    }
    
    ob_start();
    include '../config/Connection.php';
    ob_end_clean();

    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 3. Security Check: Ensure the user passed the OTP verification
    if (!isset($_SESSION['RESET_SUCCESS']) || $_SESSION['RESET_SUCCESS'] !== true) {
        throw new Exception("Unauthorized access. Please verify your OTP first.");
    }

    if (!isset($_SESSION['reset_email'])) {
        throw new Exception("Session expired. Please start the process again.");
    }

    $email = $_SESSION['reset_email'];

    // 4. Get Input
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 5. Backend Validation
    if (empty($password) || empty($confirm_password)) {
        throw new Exception("Please fill in all fields.");
    }

    if ($password !== $confirm_password) {
        throw new Exception("Passwords do not match.");
    }

    if (strlen($password) < 8) {
        throw new Exception("Password must be at least 8 characters long.");
    }

    // 6. Hash the Password
    // PASSWORD_DEFAULT uses Bcrypt, which fits easily in standard VARCHAR columns (255)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 7. Update Password in Database
    $sql = "UPDATE USERS SET PASSWORD = :password WHERE EMAIL = :email";
    
    $stmt = $conn->prepare($sql);
    
    $params = [
        ':password' => $hashed_password,
        ':email'    => $email
    ];

    if ($stmt->execute($params)) {
        
        // 8. Cleanup Session
        unset($_SESSION['reset_otp']);
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_timestamp']);
        unset($_SESSION['RESET_SUCCESS']);
        
        // Redirect to Login with success message
        header('Location:../views/login.php?success_msg=Password changed successfully!');
        exit;
    } else {
        throw new Exception("Database Update Failed.");
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>