<?php
// globalxadminzportal/login.php
session_start();

// Already logged in — redirect based on role
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['is_global'] == 1) {
        header('Location: farm_page.php');
    } else {
        header('Location: my_farms.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | GATZFarm</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Syne:wght@400;600;700;800&display=swap');

        :root {
            --bg:       #080d18;
            --card:     #0e1623;
            --border:   #1a2740;
            --border2:  #243450;
            --text:     #d4e0f0;
            --muted:    #4a6280;
            --accent:   #34d399;
            --accent2:  #059669;
            --gold:     #f0b429;
            --danger:   #ef4444;
            --blue:     #3b82f6;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Syne', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* ── Animated background ── */
        .bg-layer {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, rgba(52,211,153,0.06) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 90%, rgba(240,180,41,0.04) 0%, transparent 55%),
                radial-gradient(ellipse 100% 80% at 50% 50%, rgba(8,13,24,1) 0%, transparent 100%);
        }

        /* Scanline texture */
        .bg-layer::after {
            content: '';
            position: absolute; inset: 0;
            background-image: repeating-linear-gradient(
                0deg,
                transparent,
                transparent 2px,
                rgba(255,255,255,0.008) 2px,
                rgba(255,255,255,0.008) 4px
            );
            pointer-events: none;
        }

        /* Floating grid lines */
        .grid-lines {
            position: fixed; inset: 0; z-index: 0;
            background-image:
                linear-gradient(rgba(52,211,153,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(52,211,153,0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: gridShift 20s linear infinite;
        }

        @keyframes gridShift {
            from { background-position: 0 0; }
            to   { background-position: 60px 60px; }
        }

        /* ── Main layout ── */
        .login-wrap {
            position: relative; z-index: 1;
            display: flex;
            width: 100%; max-width: 960px;
            min-height: 580px;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid var(--border2);
            box-shadow:
                0 0 0 1px rgba(52,211,153,0.05),
                0 40px 80px -20px rgba(0,0,0,0.8),
                0 0 60px -10px rgba(52,211,153,0.08);
            animation: fadeSlideUp 0.7s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(32px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0)     scale(1); }
        }

        /* ── Left panel (branding) ── */
        .brand-panel {
            flex: 1;
            background:
                linear-gradient(145deg, rgba(52,211,153,0.08) 0%, rgba(5,150,105,0.04) 50%, transparent 100%),
                var(--card);
            border-right: 1px solid var(--border2);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }

        .brand-panel::before {
            content: '';
            position: absolute;
            top: -60px; left: -60px;
            width: 300px; height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(52,211,153,0.08) 0%, transparent 70%);
            pointer-events: none;
        }

        .brand-logo {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2.8rem;
            letter-spacing: 0.06em;
            color: var(--accent);
            line-height: 1;
        }
        .brand-logo span { color: var(--gold); }

        .brand-tagline {
            font-size: 0.72rem; font-weight: 600; letter-spacing: 0.2em;
            text-transform: uppercase; color: var(--muted); margin-top: 6px;
        }

        .brand-middle { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 2rem 0; }

        .brand-headline {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 3.2rem; letter-spacing: 0.03em;
            line-height: 1.05; color: #fff; margin-bottom: 1rem;
        }
        .brand-headline span { color: var(--accent); }

        .brand-desc { font-size: 0.88rem; color: var(--muted); line-height: 1.7; font-weight: 400; max-width: 280px; }

        .brand-footer { font-size: 0.72rem; color: var(--muted); letter-spacing: 0.08em; }

        /* Decorative element */
        .brand-deco {
            position: absolute; bottom: -40px; right: -40px;
            width: 200px; height: 200px; border-radius: 50%;
            border: 1px solid rgba(52,211,153,0.1);
        }
        .brand-deco::after {
            content: ''; position: absolute; inset: 20px;
            border-radius: 50%; border: 1px solid rgba(52,211,153,0.06);
        }

        /* ── Right panel (form) ── */
        .form-panel {
            width: 380px; flex-shrink: 0; background: var(--card);
            display: flex; flex-direction: column; justify-content: center;
            padding: 3rem 2.5rem; position: relative;
        }

        .form-section { display: none; animation: fade 0.3s ease forwards; }
        .form-section.active { display: block; }
        @keyframes fade { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .form-title { font-family: 'Bebas Neue', sans-serif; font-size: 2rem; letter-spacing: 0.05em; color: #fff; margin-bottom: 4px; }
        .form-sub { font-size: 0.82rem; color: var(--muted); margin-bottom: 2rem; font-weight: 400; line-height: 1.4; }

        /* Alert */
        .alert {
            padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600;
            margin-bottom: 1.25rem; display: none; align-items: center; gap: 8px;
        }
        .alert.error   { display: flex; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; }
        .alert.success { display: flex; background: rgba(52,211,153,0.1); border: 1px solid rgba(52,211,153,0.3); color: #6ee7b7; }

        /* Form groups */
        .form-group { margin-bottom: 1.25rem; }
        .form-label {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 0.75rem; font-weight: 700; letter-spacing: 0.12em;
            text-transform: uppercase; color: var(--muted); margin-bottom: 0.5rem;
        }
        .forgot-link { text-transform: none; letter-spacing: normal; color: var(--blue); text-decoration: none; font-weight: 600; transition: 0.2s;}
        .forgot-link:hover { text-decoration: underline; color: #60a5fa;}

        .input-wrap { position: relative; }
        .input-icon {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: var(--muted); width: 18px; height: 18px; pointer-events: none;
        }
        .form-control {
            width: 100%; padding: 0.85rem 1rem 0.85rem 2.75rem;
            background: rgba(255,255,255,0.03); border: 1px solid var(--border2);
            border-radius: 10px; color: var(--text); font-size: 0.95rem; font-family: 'Syne', sans-serif;
            outline: none; transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .form-control:focus { border-color: var(--accent); background: rgba(52,211,153,0.04); box-shadow: 0 0 0 3px rgba(52,211,153,0.08); }
        .form-control::placeholder { color: #2a3d55; }
        .form-control.center-text { padding-left: 1rem; text-align: center; letter-spacing: 2px; font-weight: bold;}

        .pw-toggle {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: var(--muted); cursor: pointer; padding: 4px; display: flex; align-items: center; transition: color 0.2s;
        }
        .pw-toggle:hover { color: var(--text); }

        /* Submit */
        .btn-primary {
            width: 100%; padding: 0.9rem;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border: none; border-radius: 10px; color: #051a0e;
            font-family: 'Syne', sans-serif; font-weight: 800; font-size: 0.95rem;
            letter-spacing: 0.06em; cursor: pointer; transition: all 0.2s; margin-top: 0.5rem; position: relative; overflow: hidden;
        }
        .btn-primary::before {
            content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent); opacity: 0; transition: opacity 0.2s;
        }
        .btn-primary:hover::before { opacity: 1; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(52,211,153,0.3); }
        .btn-primary:active { transform: translateY(0); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

        .btn-back { width: 100%; padding: 0.9rem; background: transparent; border: 1px solid var(--border2); border-radius: 10px; color: var(--text); font-family: 'Syne', sans-serif; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: 0.2s; margin-top: 0.5rem; }
        .btn-back:hover { background: rgba(255,255,255,0.05); }

        /* Spinner */
        .spinner { display: none; width: 18px; height: 18px; border: 2px solid rgba(5,26,14,0.3); border-top-color: #051a0e; border-radius: 50%; animation: spin 0.7s linear infinite; margin: 0 auto; }
        .btn-primary.loading .btn-text { display: none; }
        .btn-primary.loading .spinner  { display: block; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .register-link { text-align: center; margin-top: 1.5rem; font-size: 0.85rem; color: var(--muted); }
        .register-link a { color: var(--accent); text-decoration: none; font-weight: bold; transition: 0.2s; }
        .register-link a:hover { text-decoration: underline; color: #fff;}

        @media (max-width: 720px) {
            .login-wrap { flex-direction: column; max-width: 400px; border-radius: 16px; }
            .brand-panel { display: none; }
            .form-panel { width: 100%; padding: 2.5rem 1.75rem; }
        }
    </style>
</head>
<body>

<div class="bg-layer"></div>
<div class="grid-lines"></div>

<div class="login-wrap">

    <div class="brand-panel">
        <div>
            <div class="brand-logo">Farm<span>Pro</span></div>
            <div class="brand-tagline">Client Portal</div>
        </div>

        <div class="brand-middle">
            <div class="brand-headline">Manage.<br><span>Scale.</span><br>Control.</div>
            <p class="brand-desc">Access your centralized dashboard to monitor operations, livestock, and analytics.</p>
        </div>

        <div class="brand-footer">© <?= date('Y') ?> GATZFarm SaaS · Restricted Access</div>
        <div class="brand-deco"></div>
    </div>

    <div class="form-panel">
        
        <div id="section-login" class="form-section active">
            <div class="form-title">Sign In</div>
            <div class="form-sub">Access your GATZFarm dashboard.</div>
            
            <div id="login-alert" class="alert"></div>

            <form id="loginForm">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-wrap">
                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <input type="email" id="email" class="form-control" placeholder="client@GATZFarm.com" required autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <span>Password</span>
                        <a href="#" class="forgot-link" onclick="toggleSection('forgot')">Forgot Password?</a>
                    </label>
                    <div class="input-wrap">
                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <input type="password" id="password" class="form-control" placeholder="••••••••••" required autocomplete="current-password">
                        <button type="button" class="pw-toggle" onclick="togglePw('password')" tabindex="-1">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="btnLogin">
                    <span class="btn-text">Sign In</span>
                    <div class="spinner"></div>
                </button>
                
                <div class="register-link">
                    Don't have an account? <a href="register.php">Register Here</a>
                </div>
            </form>
        </div>

        <div id="section-forgot" class="form-section">
            <div class="form-title">Reset Password</div>
            <div class="form-sub">Enter your email address and we'll send you a 6-digit OTP code.</div>
            
            <div id="forgot-alert" class="alert"></div>

            <form id="forgotForm">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-wrap">
                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <input type="email" id="forgot_email" class="form-control" placeholder="client@GATZFarm.com" required>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="btnForgot">
                    <span class="btn-text">Send Code</span>
                    <div class="spinner"></div>
                </button>
                <button type="button" class="btn-back" onclick="toggleSection('login')">Return to Sign In</button>
            </form>
        </div>

        <div id="section-reset" class="form-section">
            <div class="form-title">Create New Password</div>
            <div class="form-sub">Enter the code sent to your email and your new password.</div>
            
            <div id="reset-alert" class="alert"></div>

            <form id="resetForm">
                <div class="form-group">
                    <label class="form-label">6-Digit Code</label>
                    <input type="text" id="reset_otp" class="form-control center-text" placeholder="000000" maxlength="6" required>
                </div>

                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div class="input-wrap">
                        <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <input type="password" id="reset_password" class="form-control" placeholder="••••••••••" required minlength="8">
                        <button type="button" class="pw-toggle" onclick="togglePw('reset_password')" tabindex="-1">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="btnReset">
                    <span class="btn-text">Update Password</span>
                    <div class="spinner"></div>
                </button>
                <button type="button" class="btn-back" onclick="toggleSection('login')">Cancel</button>
            </form>
        </div>

    </div>

</div>

<script>
    // --- General UI Functions ---
    function toggleSection(section) {
        document.querySelectorAll('.form-section').forEach(el => el.classList.remove('active'));
        document.getElementById('section-' + section).classList.add('active');
        document.querySelectorAll('.alert').forEach(el => el.style.display = 'none');
    }

    function togglePw(id) {
        const pw = document.getElementById(id);
        pw.type = pw.type === 'password' ? 'text' : 'password';
    }

    function showAlert(id, type, msg) {
        const el = document.getElementById(id);
        el.className = 'alert ' + type;
        el.textContent = msg;
        el.style.display = 'flex';
    }

    let userEmailForReset = '';

    // --- Form 1: LOGIN ---
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btnLogin');
        btn.classList.add('loading'); btn.disabled = true;

        const payload = {
            email   : document.getElementById('email').value.trim(),
            password: document.getElementById('password').value,
        };

        try {
            const res  = await fetch('validateLogin.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const data = await res.json();

            if (data.success) {
                showAlert('login-alert', 'success', '✅ Authenticated. Redirecting…');
                setTimeout(() => window.location.href = data.redirect, 800);
            } else {
                showAlert('login-alert', 'error', '❌ ' + data.message);
                btn.classList.remove('loading'); btn.disabled = false;
            }
        } catch (err) {
            showAlert('login-alert', 'error', '❌ System error. Please try again.');
            btn.classList.remove('loading'); btn.disabled = false;
        }
    });

    // --- Form 2: FORGOT PASSWORD (SEND OTP) ---
    document.getElementById('forgotForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btnForgot');
        btn.classList.add('loading'); btn.disabled = true;

        userEmailForReset = document.getElementById('forgot_email').value.trim();

        try {
            const res  = await fetch('forgotPassword.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: userEmailForReset }) });
            const data = await res.json();

            if (data.success) {
                toggleSection('reset');
                showAlert('reset-alert', 'success', '✅ ' + data.message);
            } else {
                showAlert('forgot-alert', 'error', '❌ ' + data.message);
            }
        } catch (err) {
            showAlert('forgot-alert', 'error', '❌ System error. Please try again.');
        }
        btn.classList.remove('loading'); btn.disabled = false;
    });

    // --- Form 3: RESET PASSWORD (VERIFY & UPDATE) ---
    document.getElementById('resetForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btnReset');
        btn.classList.add('loading'); btn.disabled = true;

        const payload = {
            email   : userEmailForReset,
            otp     : document.getElementById('reset_otp').value.trim(),
            password: document.getElementById('reset_password').value,
        };

        try {
            const res  = await fetch('resetPassword.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const data = await res.json();

            if (data.success) {
                toggleSection('login');
                document.getElementById('email').value = userEmailForReset;
                document.getElementById('password').value = '';
                showAlert('login-alert', 'success', '✅ ' + data.message);
            } else {
                showAlert('reset-alert', 'error', '❌ ' + data.message);
                btn.classList.remove('loading'); btn.disabled = false;
            }
        } catch (err) {
            showAlert('reset-alert', 'error', '❌ System error. Please try again.');
            btn.classList.remove('loading'); btn.disabled = false;
        }
    });
</script>
</body>
</html>