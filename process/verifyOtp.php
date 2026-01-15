<?php
// ../process/verify_otp.php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// 1. Get Input
$input_otp = $_POST['otp'] ?? '';
$session_otp = $_SESSION['reset_otp'] ?? null;
$timestamp = $_SESSION['reset_timestamp'] ?? 0;

// 2. Validate Existence
if (!$session_otp) {
    echo json_encode(['success' => false, 'message' => 'No reset request found. Please go back and try again.']);
    exit;
}

// 3. Validate Expiry (10 minutes = 600 seconds)
if (time() - $timestamp > 600) {
    echo json_encode(['success' => false, 'message' => 'This code has expired. Please request a new one.']);
    exit;
}

// 4. Verify Code
if ($input_otp === $session_otp) {
    // --- SUCCESS ---
    // Create the Session Flag required to access the next page
    $_SESSION['RESET_SUCCESS'] = true;
    
    echo json_encode(['success' => true, 'message' => 'Code verified successfully.']);
} else {
    // --- FAILURE ---
    echo json_encode(['success' => false, 'message' => 'Invalid security code. Please try again.']);
}
?>