<?php
ob_start(); // Start output buffering
// profile.php
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
    <title>FarmPro - Login & Register</title>
    <link rel="stylesheet" href="../css/login.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        /* --- NEW MODAL STYLES (Copied/Adapted for consistency) --- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.show {
            display: flex;
            opacity: 1;
        }

        .modal-box {
            background: #1e293b; /* Dark slate background */
            width: 90%;
            max-width: 400px;
            padding: 30px;
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .modal-overlay.show .modal-box {
            transform: scale(1);
        }

        .modal-icon-wrapper {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        /* Success State */
        .modal-box.success .modal-icon-wrapper {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            box-shadow: 0 0 0 8px rgba(34, 197, 94, 0.05);
        }

        /* Error State */
        .modal-box.error .modal-icon-wrapper {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            box-shadow: 0 0 0 8px rgba(239, 68, 68, 0.05);
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 10px;
        }

        .modal-message {
            color: #94a3b8;
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .modal-btn {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .modal-box.success .modal-btn {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }

        .modal-box.error .modal-btn {
            background: #334155;
            color: white;
        }

        .modal-btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="parent-container">
        <div class="bg-decoration bg-circle-1"></div>
        <div class="bg-decoration bg-circle-2"></div>
        <div class="bg-decoration bg-circle-3"></div>

        <div class="auth-container">
            <div class="header-bar"></div>

            <div class="logo-section">
                <div class="logo">
                    <div class="logo-icon">🌱</div>
                    <div class="logo-text">FarmPro</div>
                </div>
                <p class="tagline">Smart farming solutions for modern agriculture</p>
            </div>

            <div class="tab-switcher">
                <button class="tab-btn active" onclick="switchTab('signin')">Sign In</button>
                <button class="tab-btn" onclick="switchTab('signup')">Sign Up</button>
            </div>

            <div class="form-section">
                <div class="form-wrapper active" id="signinForm">
                    <div class="social-login">
                        <button class="social-btn" onclick="handleGoogleAuth()">
                            <span class="social-icon">G</span>
                            <span>Continue with Google</span>
                        </button>
                    </div>

                    <div class="divider">or sign in with email</div>

                    <form action="../process/validateLogin.php" method="POST" onsubmit="return handleSigninSubmit(event)">
                        <div class="form-group">
                            <input type="email" class="form-input" id="signinEmail" name="email" placeholder=" " required>
                            <label class="form-label">Email Address</label>
                            <span class="error-message">Please enter a valid email address</span>
                        </div>

                        <div class="form-group">
                            <input type="password" class="form-input" id="signinPassword" name="password" placeholder=" " required>
                            <label class="form-label">Password</label>
                            <span class="password-toggle" onclick="togglePassword('signinPassword')">👁</span>
                            <span class="error-message">Password is required</span>
                        </div>

                        <div class="form-options">
                            <div class="checkbox-wrapper">
                                <input type="checkbox" id="remember">
                                <label for="remember">Remember me</label>
                            </div>
                            <a href="#" class="forgot-link">Forgot password?</a>
                        </div>

                        <button type="submit" class="submit-btn" id="signinBtn">Sign In</button>
                    </form>

                    <div class="form-footer">
                        Don't have an account? <a href="#" onclick="switchTab('signup')">Sign up</a>
                    </div>
                </div>

                <div class="form-wrapper" id="signupForm">
                    <div class="social-login">
                        <button class="social-btn" onclick="handleGoogleAuth()">
                            <span class="social-icon">G</span>
                            <span>Continue with Google</span>
                        </button>
                    </div>

                    <div class="divider">or sign up with email</div>

                    <form action="../process/validateRegistration.php" method="POST" onsubmit="return handleSignupSubmit(event)">
                        <div class="form-group">
                            <input type="text" class="form-input" id="signupName" name="fullname" placeholder=" " required>
                            <label class="form-label">Full Name</label>
                            <span class="error-message">Please enter your full name</span>
                        </div>

                        <div class="form-group">
                            <input type="email" class="form-input" id="signupEmail" name="email" placeholder=" " required>
                            <label class="form-label">Email Address</label>
                            <span class="error-message">Please enter a valid email address</span>
                        </div>

                        <div class="form-group">
                            <input type="password" class="form-input" id="signupPassword" name="password" placeholder=" " required>
                            <label class="form-label">Password</label>
                            <span class="password-toggle" onclick="togglePassword('signupPassword')">👁</span>
                            <div class="password-strength" id="passwordStrength" style="display: none;">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <span class="error-message">Password must be at least 8 characters</span>
                        </div>

                        <div class="form-options">
                            <div class="checkbox-wrapper">
                                <input type="checkbox" id="terms" required>
                                <label for="terms">I agree to the terms and conditions</label>
                            </div>
                        </div>

                        <button type="submit" class="submit-btn" id="signupBtn">Create Account</button>
                    </form>

                    <div class="form-footer">
                        Already have an account? <a href="#" onclick="switchTab('signin')">Sign in</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="statusModal">
        <div class="modal-box" id="modalBox">
            <div class="modal-icon-wrapper">
                <span id="modalIcon">✓</span>
            </div>
            <h3 class="modal-title" id="modalTitle">Success!</h3>
            <p class="modal-message" id="modalMessage">Operation completed successfully.</p>
            <button class="modal-btn" id="modalBtn" onclick="closeModal()">Continue</button>
        </div>
    </div>

    <script>
        // --- MODAL LOGIC ---
        let redirectUrl = null;

        function showModal(title, message, type = 'success', redirect = null) {
            const modalOverlay = document.getElementById('statusModal');
            const modalBox = document.getElementById('modalBox');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalIcon = document.getElementById('modalIcon');
            const modalBtn = document.getElementById('modalBtn');

            // Reset classes
            modalBox.classList.remove('success', 'error');
            
            // Set content and style
            modalBox.classList.add(type);
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            redirectUrl = redirect;

            if (type === 'success') {
                modalIcon.textContent = '✓';
                modalBtn.textContent = 'Continue';
            } else {
                modalIcon.textContent = '✕';
                modalBtn.textContent = 'Try Again';
            }

            // Show modal
            modalOverlay.classList.add('show');
        }

        function closeModal() {
            const modalOverlay = document.getElementById('statusModal');
            modalOverlay.classList.remove('show');
            
            if (redirectUrl) {
                window.location.href = redirectUrl;
            }
        }

        // --- EXISTING LOGIC ---

        function switchTab(tab) {
            const signinForm = document.getElementById('signinForm');
            const signupForm = document.getElementById('signupForm');
            const tabBtns = document.querySelectorAll('.tab-btn');

            if (tab === 'signin') {
                signinForm.classList.add('active');
                signupForm.classList.remove('active');
                tabBtns[0].classList.add('active');
                tabBtns[1].classList.remove('active');
            } else {
                signupForm.classList.add('active');
                signinForm.classList.remove('active');
                tabBtns[1].classList.add('active');
                tabBtns[0].classList.remove('active');
            }
        }

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

        // --- AUTH SUBMISSION HANDLERS UPDATED TO USE MODAL ---

      function handleSigninSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const email = document.getElementById('signinEmail');
    const password = document.getElementById('signinPassword');
    const submitBtn = document.getElementById('signinBtn');
    const originalText = submitBtn.textContent;

    if (!email.value || !password.value) {
        showModal('Validation Error', 'Please fill in all fields.', 'error');
        return false;
    }
    
    submitBtn.disabled = true;
    submitBtn.textContent = 'Signing in...';

    const formData = new FormData(form);

    fetch('../process/validateLogin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Change this redirect to admin_dashboard.php
            showModal('Welcome Back!', 'Login successful. Redirecting to your dashboard...', 'success', '../views/admin_dashboard.php');
        } else {
            showModal('Login Failed', data.message || 'Invalid email or password.', 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showModal('System Error', 'Something went wrong. Please try again later.', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
    
    return false;
}
        function handleSignupSubmit(event) {
            event.preventDefault();
            
            const form = event.target;
            const submitBtn = document.getElementById('signupBtn');
            const originalText = submitBtn.textContent;
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating account...';
            
            const formData = new FormData(form);

            fetch('../process/validateRegistration.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showModal('Account Created', 'Your account has been created successfully!', 'success', 'login.php');
                    // Or switch tab if preferred: switchTab('signin');
                } else {
                    showModal('Registration Failed', data.message || 'Unable to create account.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showModal('System Error', 'Something went wrong. Please try again later.', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
            
            return false;
        }

        // --- VALIDATION HELPERS ---

        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
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

        function validateInput(input) {
            const errorMessage = input.parentElement.querySelector('.error-message');
            let isValid = true;
            
            if (input.required && !input.value.trim()) isValid = false;
            if (input.type === 'email' && input.value && !validateEmail(input.value)) isValid = false;
            if (input.type === 'password' && input.value && input.value.length < 8) isValid = false;
            if (input.id.includes('Name') && input.value && input.value.length < 2) isValid = false;
            
            if (!isValid) {
                input.classList.add('error');
                if (errorMessage) errorMessage.classList.add('show');
            } else {
                input.classList.remove('error');
                if (errorMessage) errorMessage.classList.remove('show');
            }
            return isValid;
        }

        // --- EVENT LISTENERS ---

        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-input');
            
            inputs.forEach(input => {
                if (input.value) input.parentElement.classList.add('has-value');
                input.addEventListener('input', function() {
                    this.parentElement.classList.toggle('has-value', !!this.value);
                    this.classList.remove('error');
                    const err = this.parentElement.querySelector('.error-message');
                    if (err) err.classList.remove('show');
                });
                input.addEventListener('blur', function() { validateInput(this); });
            });
            
            const signupPassword = document.getElementById('signupPassword');
            const passwordStrength = document.getElementById('passwordStrength');
            const passwordStrengthBar = document.getElementById('passwordStrengthBar');
            
            if (signupPassword) {
                signupPassword.addEventListener('input', function() {
                    const strength = checkPasswordStrength(this.value);
                    if (this.value.length > 0) {
                        passwordStrength.style.display = 'block';
                        passwordStrengthBar.className = 'password-strength-bar'; // Reset
                        if (strength <= 2) passwordStrengthBar.classList.add('weak');
                        else if (strength <= 4) passwordStrengthBar.classList.add('medium');
                        else passwordStrengthBar.classList.add('strong');
                    } else {
                        passwordStrength.style.display = 'none';
                    }
                });
            }
            
            const forgotLink = document.querySelector('.forgot-link');
            if (forgotLink) {
                forgotLink.addEventListener('click', function(e) {
                    window.location.href = "forgot_password.php";   
                });
            }
        });

        // --- GOOGLE AUTHENTICATION LOGIC ---

        function handleGoogleAuth() {
            // Initialize the Google Token Client
            const client = google.accounts.oauth2.initTokenClient({
                client_id: '791478894702-qsmtnl2j9hnrbgfh4r0uo5gpqiur2db4.apps.googleusercontent.com', 
                scope: 'email profile',
                callback: (tokenResponse) => {
                    if (tokenResponse && tokenResponse.access_token) {
                        verifyGoogleTokenOnBackend(tokenResponse.access_token);
                    }
                },
            });
            client.requestAccessToken();
        }

        function verifyGoogleTokenOnBackend(accessToken) {
            const submitBtn = document.querySelector('.social-btn'); 
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>Verifying...</span>';

            fetch('../process/googleLogin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ access_token: accessToken })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showModal('Login Successful', 'Redirecting to your dashboard...', 'success', '../views/profile.php');
                } else {
                    showModal('Google Login Failed', data.message || 'Please try again.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showModal('System Error', 'An error occurred during Google login.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        }
    </script>
</body>
</html>

<?php ob_end_flush();  ?>