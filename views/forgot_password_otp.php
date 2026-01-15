<?php
ob_start();

$page = 'login/register';
include '../common/navbar.php';

if(isset($_SESSION['user'])){
    header("Location: ../views/profile.php");
    exit;
}

if($_SESSION['OTP_SENT_SUCCESSFULLY'] == false || !isset($_SESSION['OTP_SENT_SUCCESSFULLY']))
{
    header("Location: ../views/forgot_password.php");
    exit;
}
?>

<style>
    /* --- EXISTING STYLES --- */
    .forgot-header { text-align: center; margin-bottom: 35px; }
    .forgot-header h2 { font-size: 24px; font-weight: 700; color: white; margin-bottom: 12px; }
    .forgot-header p { color: #94a3b8; font-size: 14px; line-height: 1.6; }
    .otp-container { display: flex; gap: 12px; justify-content: center; margin-bottom: 25px; }
    .otp-input {
        width: 50px; height: 56px; text-align: center; font-size: 24px; font-weight: 700;
        border: 2px solid #475569; border-radius: 12px; background: rgba(15, 23, 42, 0.5);
        color: white; outline: none; transition: all 0.3s ease;
    }
    .otp-input:focus {
        border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        background: rgba(15, 23, 42, 0.8); transform: scale(1.05);
    }
    .resend-section { text-align: center; margin-bottom: 25px; }
    .resend-section p { color: #94a3b8; font-size: 14px; margin-bottom: 8px; }
    .resend-section a { color: #60a5fa; text-decoration: none; font-weight: 600; transition: all 0.3s ease; }
    .resend-section a:hover { color: #93c5fd; text-decoration: underline; }
    .countdown { color: #64748b; font-size: 13px; font-style: italic; }
    .forgot-header strong { color: #60a5fa; font-weight: 600; }

    /* --- NEW MODAL STYLES --- */
    .custom-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.85); /* Dark overlay */
        backdrop-filter: blur(5px); z-index: 1000;
        display: flex; justify-content: center; align-items: center;
        opacity: 0; visibility: hidden; transition: all 0.3s ease;
    }
    .custom-modal-overlay.active { opacity: 1; visibility: visible; }
    
    .custom-modal {
        background: #1e293b; /* Matches your card bg */
        width: 90%; max-width: 380px; padding: 30px; border-radius: 20px;
        border: 1px solid rgba(239, 68, 68, 0.3); /* Red border for error */
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        text-align: center; transform: translateY(20px) scale(0.95);
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .custom-modal-overlay.active .custom-modal { transform: translateY(0) scale(1); }

    .modal-icon-circle {
        width: 60px; height: 60px; background: rgba(239, 68, 68, 0.15);
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px; color: #ef4444; font-size: 32px; font-weight: bold;
    }
    
    .modal-title { color: white; font-size: 20px; font-weight: 700; margin-bottom: 10px; }
    .modal-message { color: #94a3b8; font-size: 15px; line-height: 1.5; margin-bottom: 25px; }
    
    .modal-btn {
        width: 100%; padding: 12px; border: none; border-radius: 12px;
        font-weight: 600; font-size: 16px; cursor: pointer; transition: transform 0.2s;
        background: #ef4444; color: white; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
    .modal-btn:hover { background: #dc2626; transform: translateY(-2px); }

    /* Mobile responsiveness */
    @media (max-width: 640px) {
        .forgot-header h2 { font-size: 22px; }
        .forgot-header p { font-size: 13px; }
        .otp-container { gap: 8px; }
        .otp-input { width: 45px; height: 52px; font-size: 22px; }
    }
</style>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FarmPro - Verify Code</title>
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
    <div class="parent-container">
        <div class="bg-decoration bg-circle-1"></div>
        <div class="bg-decoration bg-circle-2"></div>
        <div class="bg-decoration bg-circle-3"></div>

        <div class="auth-container" style="max-width: 420px;">
            <div class="header-bar"></div>

            <div class="form-section" style="padding: 40px 30px;">
                <div class="form-wrapper active">
                    <div class="forgot-header">
                        <h2>Enter Verification Code</h2>
                        <p>We've sent a 6-digit code to <strong id="userEmail"><?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : 'your email'; ?></strong></p>
                    </div>

                    <form id="otpForm" onsubmit="return handleOtpSubmit(event)">
                        <input type="hidden" id="hiddenEmail" name="email" value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>">
                        
                        <div class="otp-container">
                            <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
                            <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
                            <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
                            <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
                            <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
                            <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
                        </div>
                        <input type="hidden" id="otpCode" name="otp">

                        <div class="resend-section">
                            <p>Didn't receive the code? <a href="#" id="resendLink" onclick="resendCode(event)">Resend</a></p>
                            <p class="countdown" id="countdown"></p>
                        </div>

                        <button type="submit" class="submit-btn" id="otpBtn">Verify Code</button>
                    </form>

                    <div class="form-footer" style="margin-top: 30px;">
                        <a href="forgot_password.php">← Back to email</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="custom-modal-overlay" id="errorModal">
        <div class="custom-modal">
            <div class="modal-icon-circle">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="15"></line>
                    <line x1="12" y1="19" x2="12.01" y2="19"></line>
                </svg>
            </div>
            <h3 class="modal-title">Verification Failed</h3>
            <p class="modal-message" id="modalErrorMessage">The code you entered is incorrect.</p>
            <button class="modal-btn" onclick="closeModal()">Try Again</button>
        </div>
    </div>

    <script>
        let countdownTimer;
        let timeLeft = 60;

        function handleOtpSubmit(event) {
            event.preventDefault();
            
            const otpInputs = document.querySelectorAll('.otp-input');
            let otp = '';
            
            otpInputs.forEach(input => {
                if (!input.value) {
                    showNotification('Please enter all 6 digits', 'error');
                    input.focus();
                    return false;
                }
                otp += input.value;
            });
            
            if (otp.length !== 6) {
                showNotification('Please enter all 6 digits', 'error');
                return false;
            }
            
            // Set value to hidden input
            document.getElementById('otpCode').value = otp;
            
            const submitBtn = document.getElementById('otpBtn');
            const originalText = submitBtn.textContent;
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Verifying...';
            
            // --- AJAX SUBMISSION TO SHOW MODAL ON ERROR ---
            const formData = new FormData();
            formData.append('otp', otp);

            fetch('../process/verifyOtp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success! Redirect to Reset Password Page
                    window.location.href = '../views/createNewPassword.php';
                } else {
                    // ERROR! Show the Modal
                    showErrorModal(data.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    
                    // Clear inputs for better UX
                    otpInputs.forEach(input => input.value = '');
                    otpInputs[0].focus();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal("An unexpected error occurred. Please try again.");
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
            
            return false;
        }

        // --- MODAL FUNCTIONS ---
        function showErrorModal(message) {
            const modal = document.getElementById('errorModal');
            const messageEl = document.getElementById('modalErrorMessage');
            
            // Clean up message
            const cleanMessage = message.replace(/^Error:\s*/i, '');
            messageEl.textContent = cleanMessage;
            
            modal.classList.add('active');
        }

        function closeModal() {
            const modal = document.getElementById('errorModal');
            modal.classList.remove('active');
        }

        // Close on background click
        document.getElementById('errorModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // --- EXISTING HELPER FUNCTIONS ---
        function startCountdown() {
            const countdownEl = document.getElementById('countdown');
            const resendLink = document.getElementById('resendLink');
            
            countdownEl.textContent = `Resend available in ${timeLeft}s`;
            resendLink.style.pointerEvents = 'none';
            resendLink.style.opacity = '0.5';
            
            countdownTimer = setInterval(() => {
                timeLeft--;
                if (timeLeft > 0) {
                    countdownEl.textContent = `Resend available in ${timeLeft}s`;
                } else {
                    countdownEl.textContent = '';
                    resendLink.style.pointerEvents = 'auto';
                    resendLink.style.opacity = '1';
                    clearInterval(countdownTimer);
                }
            }, 1000);
        }

        function resendCode(event) {
            event.preventDefault();
            
            if (timeLeft > 0) {
                return;
            }
            
            const email = document.getElementById('hiddenEmail').value;
            const resendLink = document.getElementById('resendLink');
            
            resendLink.textContent = 'Sending...';
            
            const formData = new FormData();
            formData.append('email', email);

            fetch('../process/processOtpSending.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Code sent successfully!', 'success');
                    timeLeft = 60;
                    startCountdown();
                } else {
                    showErrorModal(data.message);
                }
                resendLink.textContent = 'Resend';
            })
            .catch(error => {
                showErrorModal("Failed to connect to server.");
                resendLink.textContent = 'Resend';
            });
        }

        function showNotification(message, type = 'success') {
            const existing = document.querySelector('.notification');
            if (existing) existing.remove();
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            // Add notification styles dynamically if missing from login.css
            if (!document.querySelector('#notify-style')) {
                const style = document.createElement('style');
                style.id = 'notify-style';
                style.innerHTML = `
                    .notification { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 10px; color: white; z-index: 2000; animation: slideIn 0.3s ease; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
                    .notification.success { background: #1e293b; border-left: 4px solid #10b981; color: #10b981; }
                    .notification.error { background: #1e293b; border-left: 4px solid #ef4444; color: #ef4444; }
                    .notification.info { background: #1e293b; border-left: 4px solid #3b82f6; color: #3b82f6; }
                    @keyframes slideIn { from { transform: translateY(-100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const otpInputs = document.querySelectorAll('.otp-input');
            
            otpInputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    const value = this.value;
                    if (value.length === 1 && index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                    if (!/[0-9]/.test(value)) this.value = '';
                });
                
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        otpInputs[index - 1].focus();
                        otpInputs[index - 1].value = '';
                    }
                });
                
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
                    pastedData.split('').forEach((char, i) => {
                        if (otpInputs[i]) otpInputs[i].value = char;
                    });
                    if (pastedData.length > 0) otpInputs[Math.min(pastedData.length, 5)].focus();
                });
            });
            
            const firstInput = otpInputs[0];
            if (firstInput) setTimeout(() => firstInput.focus(), 100);
            
            startCountdown();
        });
    </script>
</body>
</html>

<?php ob_end_flush(); ?>