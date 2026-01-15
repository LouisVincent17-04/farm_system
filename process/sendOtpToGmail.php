<?php

// 1. Start Session
session_start();
header('Content-Type: application/json');

// Suppress HTML errors
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 2. Load Composer Autoloader (Since you have a 'vendor' folder)
// We check two common locations just to be safe
if (file_exists('../vendor/autoload.php')) {
    require '../vendor/autoload.php';
} elseif (file_exists('../../vendor/autoload.php')) {
    require '../../vendor/autoload.php';
} else {
    echo json_encode(['success' => false, 'message' => 'Error: Could not find vendor/autoload.php']);
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 3. Include Database Connection
if (!file_exists('../config/Connection.php')) {
    echo json_encode(['success' => false, 'message' => 'Connection file not found.']);
    exit;
}
include '../config/Connection.php';

try {
    // 4. Validate Input
    $email = $_POST['email'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email address format.");
    }

    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 5. Check if Email Exists in Database (Using your specific column names)
    // Note: Adjusted to use FULL_NAME based on your screenshot
    $sql = "SELECT USER_ID, FULL_NAME FROM USERS WHERE EMAIL = :email";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':email' => $email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("This email address is not registered.");
    }

    // 6. Generate OTP
    $otp = sprintf("%06d", mt_rand(0, 999999)); 

    // 7. Store in Session
    $_SESSION['reset_otp'] = $otp;
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_timestamp'] = time();
    
    // 8. Configure PHPMailer
    $mail = new PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'vinxvade@gmail.com'; 
    $mail->Password   = 'pxpm xbkl tpmo bnjg'; // <--- PASTE YOUR 16-CHAR APP PASSWORD HERE
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom('vinxvade@gmail.com', 'FarmPro Support');
    $mail->addAddress($email, $user['FULL_NAME']); // Use the name from DB

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'FarmPro Password Reset Code';
    $mail->Body    = "
    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; max-width: 500px;'>
        <h2 style='color: #2563eb; margin-top: 0;'>Reset Your Password</h2>
        <p style='color: #334155;'>Hello <b>{$user['FULL_NAME']}</b>,</p>
        <p style='color: #334155;'>Use the code below to verify your identity:</p>
        <div style='background: #f1f5f9; padding: 15px; font-size: 24px; font-weight: bold; text-align: center; letter-spacing: 5px; color: #0f172a; border-radius: 6px; margin: 20px 0;'>
            $otp
        </div>
        <p style='color: #64748b; font-size: 12px;'>This code expires in 10 minutes. If you didn't request this, you can ignore this email.</p>
    </div>";
    
    $mail->AltBody = "Hello {$user['FULL_NAME']}, your FarmPro reset code is: $otp";

    $mail->send();
    $_SESSION['OTP_SENT_SUCCESSFULLY'] = true; 
    echo json_encode(['success' => true, 'message' => 'OTP has been sent to your email address.']);

} catch (Exception $e) {
    // If it's a PHPMailer error, get the detailed message
    $errorMsg = $e instanceof Exception && method_exists($e, 'errorMessage') 
        ? $mail->ErrorInfo 
        : $e->getMessage();
        
    echo json_encode(['success' => false, 'message' => "Error: " . $errorMsg]);
}
?>