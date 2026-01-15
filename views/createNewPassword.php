<?php
ob_start();

$page = 'login/register';
include '../common/navbar.php';

if(isset($_SESSION['user'])){
    header("Location: ../views/profile.php");
    exit;
}

if($_SESSION['RESET_SUCCESS'] !== true || !isset($_SESSION['RESET_SUCCESS'])){
    header("Location: ../views/forgot_password.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FarmPro - Create New Password</title>
    <link rel="stylesheet" href="../css/login.css">
    <style>
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

        .password-field {
            position: relative;
        }

        .form-input-static {
            width: 100%;
            padding: 14px 50px 14px 16px;
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

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            transition: color 0.3s ease;
            font-size: 18px;
            user-select: none;
            z-index: 2;
        }

        .password-toggle:hover {
            color: #60a5fa;
        }

        .password-strength {
            height: 4px;
            background: #475569;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .password-strength-bar.weak {
            width: 33%;
            background: #ef4444;
        }

        .password-strength-bar.medium {
            width: 66%;
            background: #f59e0b;
        }

        .password-strength-bar.strong {
            width: 100%;
            background: #10b981;
        }

        @media (max-width: 640px) {
            .forgot-header h2 { font-size: 22px; }
            .forgot-header p { font-size: 13px; }
        }

        @media (max-width: 375px) {
            .forgot-header h2 { font-size: 20px; }
            .form-input-static { font-size: 16px; padding: 13px 50px 13px 14px; }
        }
    </style>
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
                        <h2>Create New Password</h2>
                        <p>Your new password must be different from previously used passwords.</p>
                    </div>

                    <form action="../process/changePassword.php" method="POST" onsubmit="return handleSubmit(event)">
                        <input type="hidden" name="email" value="">
                        <input type="hidden" name="token" value="">
                        
                        <div class="form-group-static">
                            <label class="static-label">New Password</label>
                            <div class="password-field">
                                <input type="password" class="form-input-static" id="newPassword" name="password" placeholder="Enter new password" required>
                                <span class="password-toggle" onclick="togglePassword('newPassword')">👁</span>
                            </div>
                            <div class="password-strength" id="passwordStrength" style="display: none;">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <span class="error-message">Password must be at least 8 characters</span>
                        </div>

                        <div class="form-group-static">
                            <label class="static-label">Confirm Password</label>
                            <div class="password-field">
                                <input type="password" class="form-input-static" id="confirmPassword" name="confirm_password" placeholder="Re-enter new password" required>
                                <span class="password-toggle" onclick="togglePassword('confirmPassword')">👁</span>
                            </div>
                            <span class="error-message">Passwords do not match</span>
                        </div>

                        <button type="submit" class="submit-btn" id="submitBtn">Reset Password</button>
                    </form>

                    <div class="form-footer" style="margin-top: 30px;">
                        <a href="login.php">← Back to sign in</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggle = input.parentElement.querySelector('.password-toggle');
            
            if (input.type === 'password') {
                input.type = 'text';
                toggle.textContent = '👁‍🗨';
            } else {
                input.type = 'password';
                toggle.textContent = '👁';
            }
        }

        function checkPasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            return strength;
        }

        function handleSubmit(event) {
            event.preventDefault();
            
            const form = event.target;
            const newPassword = document.getElementById('newPassword');
            const confirmPassword = document.getElementById('confirmPassword');
            const submitBtn = document.getElementById('submitBtn');
            
            let isValid = true;
            
            if (!validateInput(newPassword)) {
                isValid = false;
            }
            
            if (!validateInput(confirmPassword)) {
                isValid = false;
            }
            
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.classList.add('error');
                const errorMessage = confirmPassword.parentElement.parentElement.querySelector('.error-message');
                if (errorMessage) {
                    errorMessage.classList.add('show');
                }
                isValid = false;
            }
            
            if (!isValid) {
                return false;
            }
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Resetting...';
            
            form.submit();
            
            return false;
        }

        function validateInput(input) {
            const errorMessage = input.parentElement.parentElement.querySelector('.error-message');
            let isValid = true;
            
            input.classList.remove('error');
            if (errorMessage) {
                errorMessage.classList.remove('show');
            }
            
            if (input.required && !input.value.trim()) {
                isValid = false;
            }
            
            if (input.type === 'password' && input.value && input.value.length < 8) {
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

        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('newPassword');
            const confirmPassword = document.getElementById('confirmPassword');
            const passwordStrength = document.getElementById('passwordStrength');
            const passwordStrengthBar = document.getElementById('passwordStrengthBar');
            
            newPassword.addEventListener('input', function() {
                this.classList.remove('error');
                const errorMessage = this.parentElement.parentElement.querySelector('.error-message');
                if (errorMessage) {
                    errorMessage.classList.remove('show');
                }
                
                const strength = checkPasswordStrength(this.value);
                
                if (this.value.length > 0) {
                    passwordStrength.style.display = 'block';
                    passwordStrengthBar.classList.remove('weak', 'medium', 'strong');
                    
                    if (strength <= 2) {
                        passwordStrengthBar.classList.add('weak');
                    } else if (strength <= 4) {
                        passwordStrengthBar.classList.add('medium');
                    } else {
                        passwordStrengthBar.classList.add('strong');
                    }
                } else {
                    passwordStrength.style.display = 'none';
                }
            });
            
            newPassword.addEventListener('blur', function() {
                validateInput(this);
            });
            
            newPassword.addEventListener('focus', function() {
                this.classList.remove('error');
                const errorMessage = this.parentElement.parentElement.querySelector('.error-message');
                if (errorMessage) {
                    errorMessage.classList.remove('show');
                }
            });
            
            confirmPassword.addEventListener('input', function() {
                this.classList.remove('error');
                const errorMessage = this.parentElement.parentElement.querySelector('.error-message');
                if (errorMessage) {
                    errorMessage.classList.remove('show');
                }
                
                if (newPassword.value && this.value && newPassword.value !== this.value) {
                    this.classList.add('error');
                    if (errorMessage) {
                        errorMessage.classList.add('show');
                    }
                }
            });
            
            confirmPassword.addEventListener('blur', function() {
                if (newPassword.value !== this.value) {
                    this.classList.add('error');
                    const errorMessage = this.parentElement.parentElement.querySelector('.error-message');
                    if (errorMessage) {
                        errorMessage.classList.add('show');
                    }
                } else {
                    validateInput(this);
                }
            });
            
            confirmPassword.addEventListener('focus', function() {
                this.classList.remove('error');
                const errorMessage = this.parentElement.parentElement.querySelector('.error-message');
                if (errorMessage) {
                    errorMessage.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>

<?php ob_end_flush(); ?>