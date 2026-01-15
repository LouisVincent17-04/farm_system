<style>
    /* Forgot Password Specific Styles */
.forgot-header {
    text-align: center;
    margin-bottom: 35px;
}

.forgot-header h2 {
    font-size: 24px;
    font-weight: 700;
    color: white;
    margin-bottom: 12px;
}

.forgot-header p {
    color: #94a3b8;
    font-size: 14px;
    line-height: 1.6;
}

/* Static Label Form Group */
.form-group-static {
    margin-bottom: 25px;
    position: relative;
}

.static-label {
    display: block;
    color: #cbd5e1;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 10px;
}

.form-input-static {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #475569;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: rgba(15, 23, 42, 0.5);
    outline: none;
    color: white;
}

.form-input-static::placeholder {
    color: #64748b;
}

.form-input-static:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
    background: rgba(15, 23, 42, 0.8);
}

.form-input-static.error {
    border-color: #ef4444;
    animation: shake 0.3s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(5px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.show {
    opacity: 1;
    visibility: visible;
}

.modal-box {
    background: #1e293b;
    width: 90%;
    max-width: 400px;
    padding: 30px;
    border-radius: 20px;
    border: 1px solid rgba(239, 68, 68, 0.2);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
    text-align: center;
    transform: translateY(20px) scale(0.95);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
}

.modal-overlay.show .modal-box {
    transform: translateY(0) scale(1);
}

.modal-icon {
    width: 60px;
    height: 60px;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    margin: 0 auto 20px;
}

.modal-close-icon {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    color: #64748b;
    font-size: 24px;
    cursor: pointer;
    transition: color 0.2s;
}

.modal-close-icon:hover {
    color: #94a3b8;
}

.modal-title {
    color: white;
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 10px;
}

.modal-message {
    color: #94a3b8;
    font-size: 15px;
    line-height: 1.5;
    margin-bottom: 25px;
}

.modal-close-btn {
    background: #ef4444;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;
}

.modal-close-btn:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
}

/* Mobile responsiveness */
@media (max-width: 640px) {
    .forgot-header h2 {
        font-size: 22px;
    }

    .forgot-header p {
        font-size: 13px;
    }
}

@media (max-width: 375px) {
    .forgot-header h2 {
        font-size: 20px;
    }

    .form-input-static {
        font-size: 16px;
        padding: 13px 14px;
    }
}
</style>
<?php
ob_start();

$page = 'login/register';
include '../common/navbar.php';

if(isset($_SESSION['user'])){
    header("Location: ../views/profile.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FarmPro - Forgot Password</title>
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
                        <h2>Forgot Password</h2>
                        <p>Enter your email address below and we'll send you instructions to reset your password.</p>
                    </div>

                    <form action="../process/sendOtpToGmail.php" method="POST" onsubmit="return handleSubmit(event)">
                        <div class="form-group-static">
                            <label class="static-label">Email Address</label>
                            <input type="email" class="form-input-static" id="resetEmail" name="email" placeholder="Enter your email address" required>
                            <span class="error-message">Please enter a valid email address</span>
                        </div>

                        <button type="submit" class="submit-btn" id="submitBtn">Send Reset Link</button>
                    </form>

                    <div class="form-footer" style="margin-top: 30px;">
                        Remember your password? <a href="login.php">Sign in</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="errorModal">
        <div class="modal-box">
            <button type="button" class="modal-close-icon" onclick="closeModal()">&times;</button>
            <div class="modal-icon">
                <span>!</span>
            </div>
            <h3 class="modal-title">Error</h3>
            <p class="modal-message" id="modalMessage">Something went wrong.</p>
            <button class="modal-close-btn" onclick="closeModal()">Close</button>
        </div>
    </div>

    <script>
        function handleSubmit(event) {
            event.preventDefault();
            
            const form = event.target;
            const email = document.getElementById('resetEmail');
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.textContent;
            
            if (!validateInput(email)) {
                return false;
            }
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';
            
            const formData = new FormData(form);

            fetch('../process/sendOtpToGmail.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'forgot_password_otp.php?email=' + encodeURIComponent(email.value);
                } else {
                    showErrorModal(data.message || 'An error occurred while sending the reset link.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('An unexpected server error occurred.');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
            
            return false;
        }

        function showErrorModal(message) {
            const modal = document.getElementById('errorModal');
            const messageEl = document.getElementById('modalMessage');
            
            const cleanMessage = message.replace(/^Error:\s*/i, '');
            
            messageEl.textContent = cleanMessage;
            modal.classList.add('show');
        }

        function closeModal() {
            const modal = document.getElementById('errorModal');
            modal.classList.remove('show');
        }

        document.getElementById('errorModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        function showNotification(message, type = 'success') {
            const existing = document.querySelector('.notification');
            if (existing) {
                existing.remove();
            }
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideDown 0.4s ease-out reverse';
                setTimeout(() => notification.remove(), 400);
            }, 3000);
        }

        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function validateInput(input) {
            const errorMessage = input.parentElement.querySelector('.error-message');
            let isValid = true;
            
            input.classList.remove('error');
            if (errorMessage) {
                errorMessage.classList.remove('show');
            }
            
            if (input.required && !input.value.trim()) {
                isValid = false;
            }
            
            if (input.type === 'email' && input.value && !validateEmail(input.value)) {
                isValid = false;
            }
            
            if (!isValid) {
                input.classList.add('error');
                if (errorMessage) {
                    errorMessage.classList.add('show');
                }
            }
            
            return isValid;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const email = document.getElementById('resetEmail');
            
            email.addEventListener('input', function() {
                this.classList.remove('error');
                const errorMessage = this.parentElement.querySelector('.error-message');
                if (errorMessage) {
                    errorMessage.classList.remove('show');
                }
            });
            
            email.addEventListener('blur', function() {
                validateInput(this);
            });
            
            email.addEventListener('focus', function() {
                this.classList.remove('error');
                const errorMessage = this.parentElement.querySelector('.error-message');
                if (errorMessage) {
                    errorMessage.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>

<?php ob_end_flush(); ?>