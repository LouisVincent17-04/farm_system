<?php
ob_start(); // Start output buffering

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

    <script>
        // Tab switching
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

        // Password toggle
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

        // Handle Sign In form submission
        function handleSigninSubmit(event) {
            event.preventDefault();
            
            const form = event.target;
            const email = document.getElementById('signinEmail');
            const password = document.getElementById('signinPassword');
            const submitBtn = document.getElementById('signinBtn');
            
            // Validate all inputs
            let isValid = true;
            isValid = validateInput(email) && isValid;
            isValid = validateInput(password) && isValid;
            
            if (!isValid) {
                return false;
            }
            
            // Handle remember me
            const rememberCheckbox = document.getElementById('remember');
            if (rememberCheckbox && rememberCheckbox.checked) {
                localStorage.setItem('rememberedEmail', email.value);
            } else {
                localStorage.removeItem('rememberedEmail');
            }
            
            // Disable button and show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Signing in...';
            
            // Submit the form
            form.submit();
            
            return false;
        }

        // Handle Sign Up form submission
        function handleSignupSubmit(event) {
            event.preventDefault();
            
            const form = event.target;
            const name = document.getElementById('signupName');
            const email = document.getElementById('signupEmail');
            const password = document.getElementById('signupPassword');
            const terms = document.getElementById('terms');
            const submitBtn = document.getElementById('signupBtn');
            
            // Validate all inputs
            let isValid = true;
            isValid = validateInput(name) && isValid;
            isValid = validateInput(email) && isValid;
            isValid = validateInput(password) && isValid;
            
            // Check terms checkbox
            if (!terms.checked) {
                showNotification('Please agree to the terms and conditions', 'error');
                return false;
            }
            
            if (!isValid) {
                return false;
            }
            
            // Disable button and show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating account...';
            
            // Submit the form
            form.submit();
            
            return false;
        }

        // Google authentication
        function handleGoogleAuth() {
            showNotification('Google authentication would be integrated here', 'info');
        }

        // Show notification
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

        // Email validation
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        // Password strength checker
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
    
    if (input.required && !input.value.trim()) {
        isValid = false;
    }
    
    if (input.type === 'email' && input.value && !validateEmail(input.value)) {
        isValid = false;
    }
    
    if (input.type === 'password' && input.value && input.value.length < 8) {
        isValid = false;
    }
    
    if (input.id.includes('Name') && input.value && input.value.length < 2) {
        isValid = false;
    }
    
    if (!isValid) {
        input.classList.add('error');
        if (errorMessage) {
            errorMessage.classList.add('show');
        }
    } else {
        input.classList.remove('error');
        if (errorMessage) {
            errorMessage.classList.remove('show');
        }
    }
    
    return isValid;
}

document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.form-input');
    
    inputs.forEach(input => {
        if (input.value) {
            input.parentElement.classList.add('has-value');
        }
        
        input.addEventListener('input', function() {
            if (this.value) {
                this.parentElement.classList.add('has-value');
            } else {
                this.parentElement.classList.remove('has-value');
            }
            
            this.classList.remove('error');
            const errorMessage = this.parentElement.querySelector('.error-message');
            if (errorMessage) {
                errorMessage.classList.remove('show');
            }
        });
        
        input.addEventListener('blur', function() {
            if (!this.value) {
                this.parentElement.classList.remove('has-value');
            }
            validateInput(this);
        });
        
        input.addEventListener('focus', function() {
            this.classList.remove('error');
            const errorMessage = this.parentElement.querySelector('.error-message');
            if (errorMessage) {
                errorMessage.classList.remove('show');
            }
        });
    });
    
    const signupPassword = document.getElementById('signupPassword');
    const passwordStrength = document.getElementById('passwordStrength');
    const passwordStrengthBar = document.getElementById('passwordStrengthBar');
    
    if (signupPassword) {
        signupPassword.addEventListener('input', function() {
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
    }
    
    const forgotLink = document.querySelector('.forgot-link');
    if (forgotLink) {
        forgotLink.addEventListener('click', function(e) {
            window.location.href = "forgot_password.php";   
        });
    }
    
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    
    const activeForm = document.querySelector('.form-wrapper.active');
    if (activeForm) {
        const firstInput = activeForm.querySelector('.form-input');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
    
    const savedEmail = localStorage.getItem('rememberedEmail');
    if (savedEmail) {
        const emailInput = document.getElementById('signinEmail');
        const rememberCheckbox = document.getElementById('remember');
        
        if (emailInput) {
            emailInput.value = savedEmail;
            emailInput.parentElement.classList.add('has-value');
        }
        
        if (rememberCheckbox) {
            rememberCheckbox.checked = true;
        }
    }
});

// ... keep your existing switchTab, togglePassword, handleSigninSubmit functions ...

// --- GOOGLE AUTHENTICATION LOGIC ---

function handleGoogleAuth() {
    // Initialize the Google Token Client
    const client = google.accounts.oauth2.initTokenClient({
        client_id: '791478894702-qsmtnl2j9hnrbgfh4r0uo5gpqiur2db4.apps.googleusercontent.com', // <--- PASTE YOUR CLIENT ID HERE
        scope: 'email profile',
        callback: (tokenResponse) => {
            if (tokenResponse && tokenResponse.access_token) {
                // Token received, verify it on the backend
                verifyGoogleTokenOnBackend(tokenResponse.access_token);
            }
        },
    });

    // Trigger the Google popup
    client.requestAccessToken();
}

function verifyGoogleTokenOnBackend(accessToken) {
    const submitBtn = document.querySelector('.social-btn'); // Or a specific ID
    const originalText = submitBtn.innerHTML;
    
    // UI Feedback
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>Verifying...</span>';

    // Send the access token to your PHP backend
    fetch('../process/googleLogin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ access_token: accessToken })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Login successful! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = '../views/profile.php'; // Redirect to profile
            }, 1000);
        } else {
            showNotification(data.message || 'Google login failed.', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred during Google login.', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// ... keep your existing showNotification and other functions ...
    </script>
</body>
</html>

<?php ob_end_flush();  ?>