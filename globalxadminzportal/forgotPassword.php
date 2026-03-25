<?php
// globalxadminzportal/forgotPassword.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once '../config/SadminConnection.php';

// 1. Load PHPMailer via Composer's Autoloader! (This is the fix)
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

try {
    // 2. Verify user exists
    $stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Security best practice: Don't reveal if an email exists or not
        echo json_encode(['success' => true, 'message' => 'If your email is registered, a reset code has been sent.']);
        exit;
    }

    // 3. Generate a 6-digit OTP and set expiry
    $otp = sprintf("%06d", mt_rand(1, 999999));
    $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
    $update->execute([$otp, $expires, $email]);

    // 4. Send Email using PHPMailer
    $mail = new PHPMailer(true);

    // --- YOUR SMTP SETTINGS ---
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; 
    $mail->SMTPAuth   = true;
    $mail->Username   = 'vinxvadezxz@gmail.com'; // Your sending email
    $mail->Password   = 'txvu surv isyb neng';    // Your App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587; 

    // --- EMAIL CONTENT ---
    $mail->setFrom('vinxvadezxz@gmail.com', 'FarmPro Admin');
    $mail->addAddress($email, $user['full_name']);
    $mail->isHTML(true);
    $mail->Subject = 'FarmPro - Password Reset Code';
    $mail->Body    = "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
            <h2 style='color: #059669;'>Password Reset Request</h2>
            <p>Hello {$user['full_name']},</p>
            <p>We received a request to reset your FarmPro password. Use the code below to proceed.</p>
            <div style='text-align: center; margin: 30px 0;'>
                <span style='font-size: 24px; font-weight: bold; background: #f3f4f6; padding: 10px 20px; border-radius: 6px; letter-spacing: 5px; color: #000;'>{$otp}</span>
            </div>
            <p style='color: #6b7280; font-size: 12px;'>This code will expire in 15 minutes. If you did not request this, please ignore this email.</p>
        </div>
    ";

    $mail->send();

    echo json_encode(['success' => true, 'message' => 'Reset code sent successfully to your email.']);

} catch (Exception $e) {
    // Catch mailer specific errors so the JS doesn't crash
    echo json_encode(['success' => false, 'message' => 'Mailer Error: ' . $mail->ErrorInfo]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>