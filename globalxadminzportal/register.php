<?php
// globalxadminportal/register.php
session_start();
if (isset($_SESSION['admin'])) { header('Location: farm_page.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | FarmPro Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Syne:wght@400;600;700;800&display=swap');

        :root {
            --bg:#080d18; --card:#0e1623; --border:#1a2740; --border2:#243450;
            --text:#d4e0f0; --muted:#4a6280; --accent:#34d399; --accent2:#059669;
            --gold:#f0b429; --danger:#ef4444; --blue:#60a5fa;
        }

        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}

        body {
            font-family:'Syne',sans-serif; background:var(--bg); color:var(--text);
            min-height:100vh; display:flex; align-items:center; justify-content:center;
            overflow-x:hidden; padding:2rem 1rem;
        }

        .bg-layer {
            position:fixed; inset:0; z-index:0;
            background:
                radial-gradient(ellipse 80% 60% at 80% 10%, rgba(52,211,153,0.06) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 20% 90%, rgba(240,180,41,0.04) 0%, transparent 55%);
        }

        .grid-lines {
            position:fixed; inset:0; z-index:0;
            background-image:
                linear-gradient(rgba(52,211,153,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(52,211,153,0.025) 1px, transparent 1px);
            background-size:60px 60px;
            animation:gridShift 20s linear infinite;
        }
        @keyframes gridShift { from{background-position:0 0;} to{background-position:60px 60px;} }

        .register-wrap {
            position:relative; z-index:1;
            display:flex; width:100%; max-width:980px;
            border-radius:24px; overflow:hidden;
            border:1px solid var(--border2);
            box-shadow:0 0 0 1px rgba(52,211,153,0.04), 0 40px 80px -20px rgba(0,0,0,.8), 0 0 60px -10px rgba(52,211,153,0.07);
            animation:fadeSlideUp .7s cubic-bezier(0.22,1,0.36,1) both;
        }
        @keyframes fadeSlideUp { from{opacity:0;transform:translateY(28px) scale(.98);} to{opacity:1;transform:translateY(0) scale(1);} }

        /* Left brand panel */
        .brand-panel {
            flex:1;
            background:linear-gradient(145deg, rgba(52,211,153,.07) 0%, rgba(5,150,105,.03) 50%, transparent 100%), var(--card);
            border-right:1px solid var(--border2);
            display:flex; flex-direction:column; justify-content:space-between;
            padding:3rem; position:relative; overflow:hidden;
        }
        .brand-panel::before {
            content:''; position:absolute; bottom:-80px; right:-80px;
            width:350px; height:350px; border-radius:50%;
            background:radial-gradient(circle, rgba(52,211,153,.06) 0%, transparent 70%);
            pointer-events:none;
        }
        .brand-logo { font-family:'Bebas Neue',sans-serif; font-size:2.6rem; letter-spacing:.06em; color:var(--accent); line-height:1; }
        .brand-logo span { color:var(--gold); }
        .brand-tagline { font-size:.7rem; font-weight:600; letter-spacing:.2em; text-transform:uppercase; color:var(--muted); margin-top:6px; }
        .brand-middle { flex:1; display:flex; flex-direction:column; justify-content:center; padding:2.5rem 0; }
        .brand-headline { font-family:'Bebas Neue',sans-serif; font-size:2.8rem; letter-spacing:.03em; line-height:1.08; color:#fff; margin-bottom:1rem; }
        .brand-headline span { color:var(--accent); }
        .brand-desc { font-size:.85rem; color:var(--muted); line-height:1.75; max-width:270px; }
        .brand-footer { font-size:.7rem; color:var(--muted); letter-spacing:.08em; }

        /* Steps */
        .steps { display:flex; flex-direction:column; gap:12px; margin-top:2rem; }
        .step { display:flex; align-items:center; gap:12px; font-size:.8rem; color:var(--muted); }
        .step-dot { width:28px; height:28px; border-radius:50%; flex-shrink:0; background:rgba(255,255,255,.04); border:1px solid var(--border2); display:flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:700; color:var(--muted); transition:all .3s; }
        .step.active .step-dot { background:rgba(52,211,153,.15); border-color:var(--accent); color:var(--accent); }
        .step.done   .step-dot { background:var(--accent); border-color:var(--accent); color:#051a0e; }
        .step.active { color:var(--text); }
        .step.done   { color:var(--accent); }

        /* Form panel */
        .form-panel { width:420px; flex-shrink:0; background:var(--card); display:flex; flex-direction:column; justify-content:center; padding:3rem 2.5rem; }

        .step-page { display:none; }
        .step-page.active { display:block; animation:fadeIn .3s ease; }
        @keyframes fadeIn { from{opacity:0;transform:translateX(10px);} to{opacity:1;transform:translateX(0);} }

        .form-title { font-family:'Bebas Neue',sans-serif; font-size:1.9rem; letter-spacing:.05em; color:#fff; margin-bottom:4px; }
        .form-sub { font-size:.8rem; color:var(--muted); margin-bottom:2rem; line-height:1.5; }

        /* Super Admin info box */
        .role-info {
            border:1.5px solid rgba(52,211,153,.25); border-radius:12px;
            padding:1.25rem 1rem; margin-bottom:1.5rem;
            background:rgba(52,211,153,.05);
            display:flex; align-items:flex-start; gap:12px;
        }
        .role-info-icon { font-size:1.8rem; flex-shrink:0; }
        .role-info-name { font-weight:700; font-size:.92rem; color:#fff; margin-bottom:4px; }
        .role-info-desc { font-size:.75rem; color:var(--muted); line-height:1.5; }

        /* Alert */
        .alert { padding:.75rem 1rem; border-radius:8px; font-size:.82rem; font-weight:600; margin-bottom:1.25rem; display:none; align-items:center; gap:8px; }
        .alert.error   { display:flex; background:rgba(239,68,68,.1);  border:1px solid rgba(239,68,68,.3);  color:#fca5a5; }
        .alert.success { display:flex; background:rgba(52,211,153,.1); border:1px solid rgba(52,211,153,.3); color:#6ee7b7; }

        /* Form elements */
        .form-group { margin-bottom:1.1rem; }
        .form-label { display:block; font-size:.7rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); margin-bottom:.45rem; }
        .input-wrap { position:relative; }
        .input-icon { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--muted); width:17px; height:17px; pointer-events:none; }
        .form-control { width:100%; padding:.8rem 1rem .8rem 2.6rem; background:rgba(255,255,255,.03); border:1px solid var(--border2); border-radius:9px; color:var(--text); font-size:.92rem; font-family:'Syne',sans-serif; outline:none; transition:border-color .2s, box-shadow .2s, background .2s; }
        .form-control:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(52,211,153,.08); background:rgba(52,211,153,.03); }
        .form-control::placeholder { color:#253448; }

        .pw-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--muted); cursor:pointer; padding:4px; display:flex; align-items:center; transition:color .2s; }
        .pw-toggle:hover { color:var(--text); }

        .pw-strength { height:3px; border-radius:2px; margin-top:5px; background:var(--border); overflow:hidden; }
        .pw-strength-bar { height:100%; width:0; border-radius:2px; transition:width .3s, background .3s; }
        .pw-hint { font-size:.7rem; color:var(--muted); margin-top:3px; }

        /* Buttons */
        .btn-row { display:flex; gap:10px; margin-top:.5rem; }
        .btn-back { padding:.8rem 1.25rem; background:transparent; border:1px solid var(--border2); border-radius:9px; color:var(--muted); font-family:'Syne',sans-serif; font-weight:700; font-size:.88rem; cursor:pointer; transition:all .2s; flex-shrink:0; }
        .btn-back:hover { border-color:var(--muted); color:var(--text); }
        .btn-next { flex:1; padding:.85rem; background:linear-gradient(135deg, var(--accent), var(--accent2)); border:none; border-radius:9px; color:#051a0e; font-family:'Syne',sans-serif; font-weight:800; font-size:.9rem; cursor:pointer; transition:all .2s; }
        .btn-next:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 6px 20px rgba(52,211,153,.3); }
        .btn-next:disabled { opacity:.5; cursor:not-allowed; transform:none; box-shadow:none; }

        .login-link { text-align:center; margin-top:1.25rem; font-size:.8rem; color:var(--muted); }
        .login-link a { color:var(--accent); text-decoration:none; font-weight:700; }
        .login-link a:hover { text-decoration:underline; }

        /* Pending box */
        .pending-box { text-align:center; padding:1.5rem 0; }
        .pending-icon { font-size:3.5rem; margin-bottom:1rem; display:block; animation:pulse 2s ease-in-out infinite; }
        @keyframes pulse { 0%,100%{transform:scale(1);} 50%{transform:scale(1.08);} }
        .pending-title { font-family:'Bebas Neue',sans-serif; font-size:1.8rem; letter-spacing:.04em; color:#fff; margin-bottom:.5rem; }
        .pending-badge { display:inline-block; margin:1rem 0; padding:6px 18px; border-radius:100px; font-size:.75rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; }
        .pending-desc { font-size:.82rem; color:var(--muted); line-height:1.7; }

        @media(max-width:780px) {
            .register-wrap { flex-direction:column; max-width:420px; }
            .brand-panel { display:none; }
            .form-panel { width:100%; padding:2.5rem 1.75rem; }
        }
    </style>
</head>
<body>

<div class="bg-layer"></div>
<div class="grid-lines"></div>

<div class="register-wrap">

    <!-- Left: Branding + Step indicators -->
    <div class="brand-panel">
        <div>
            <div class="brand-logo">Farm<span>Pro</span></div>
            <div class="brand-tagline">Admin Portal Registration</div>
        </div>
        <div class="brand-middle">
            <div class="brand-headline">Join the<br><span>Control</span><br>Center.</div>
            <p class="brand-desc">Register as a Super Admin to manage farms, clients, and system settings.</p>
            <div class="steps">
                <div class="step active" id="ind-0"><div class="step-dot">1</div><span>Account details</span></div>
                <div class="step"        id="ind-1"><div class="step-dot">2</div><span>Security setup</span></div>
                <div class="step"        id="ind-2"><div class="step-dot">3</div><span>Confirmation</span></div>
            </div>
        </div>
        <div class="brand-footer">© <?= date('Y') ?> FarmPro SaaS · Secure Registration</div>
    </div>

    <!-- Right: Form -->
    <div class="form-panel">
        <div id="alert" class="alert"></div>

        <!-- STEP 1: Details -->
        <div class="step-page active" id="page-0">
            <div class="form-title">Your Details</div>
            <div class="form-sub">Fill in your Super Admin account information.</div>

            <div class="role-info">
                <span class="role-info-icon">🛡️</span>
                <div>
                    <div class="role-info-name">Super Admin</div>
                    <div class="role-info-desc">Manage farms, clients &amp; system settings</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Full Name</label>
                <div class="input-wrap">
                    <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <input type="text" id="full_name" class="form-control" placeholder="e.g. Juan Dela Cruz" autocomplete="name">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-wrap">
                    <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <input type="email" id="reg_email" class="form-control" placeholder="you@example.com" autocomplete="email">
                </div>
            </div>
            <button class="btn-next" onclick="goStep(1)">Continue →</button>
            <div class="login-link">Already have an account? <a href="login.php">Sign in</a></div>
        </div>

        <!-- STEP 2: Password -->
        <div class="step-page" id="page-1">
            <div class="form-title">Set Password</div>
            <div class="form-sub">Choose a strong password for your account.</div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-wrap">
                    <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <input type="password" id="reg_password" class="form-control" placeholder="Min. 8 characters" oninput="checkStrength()">
                    <button type="button" class="pw-toggle" onclick="toggleField('reg_password')"><svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
                </div>
                <div class="pw-strength"><div class="pw-strength-bar" id="pw-bar"></div></div>
                <div class="pw-hint" id="pw-hint">Enter a password</div>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <div class="input-wrap">
                    <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <input type="password" id="reg_confirm" class="form-control" placeholder="Repeat password">
                    <button type="button" class="pw-toggle" onclick="toggleField('reg_confirm')"><svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
                </div>
            </div>
            <div class="btn-row">
                <button class="btn-back" onclick="goStep(0)">← Back</button>
                <button class="btn-next" id="btn-register" onclick="submitRegistration()">Create Account</button>
            </div>
        </div>

        <!-- STEP 3: Confirmation -->
        <div class="step-page" id="page-2">
            <div class="pending-box">
                <span class="pending-icon" id="pending-icon">⏳</span>
                <div class="pending-title" id="pending-title">Registration Submitted</div>
                <div class="pending-badge" id="pending-badge" style="background:rgba(240,180,41,.12);color:var(--gold);border:1px solid rgba(240,180,41,.25);">PENDING APPROVAL</div>
                <p class="pending-desc" id="pending-desc">Your account has been submitted for review. A Super Admin will approve your access shortly.</p>
                <div style="margin-top:1.5rem;">
                    <a href="login.php" style="color:var(--accent);font-size:.85rem;font-weight:700;text-decoration:none;">← Back to Login</a>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    const selectedRole = 'superadmin';
    let currentStep = 0;

    function goStep(step) {
        hideAlert();
        if (step > currentStep && !validateStep(currentStep)) return;

        document.querySelectorAll('.step-page').forEach(p => p.classList.remove('active'));
        document.getElementById('page-' + step).classList.add('active');

        document.querySelectorAll('.step[id^="ind-"]').forEach((el, i) => {
            el.classList.remove('active','done');
            if (i < step)        el.classList.add('done');
            else if (i === step) el.classList.add('active');
        });

        currentStep = step;
    }

    function validateStep(step) {
        if (step === 0) {
            if (!document.getElementById('full_name').value.trim())  { showAlert('error','❌ Full name is required.'); return false; }
            const email = document.getElementById('reg_email').value.trim();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showAlert('error','❌ Valid email is required.'); return false; }
        }
        return true;
    }

    function checkStrength() {
        const pw = document.getElementById('reg_password').value;
        const bar = document.getElementById('pw-bar');
        const hint = document.getElementById('pw-hint');
        let score = 0;
        if (pw.length >= 8)          score++;
        if (/[A-Z]/.test(pw))        score++;
        if (/[0-9]/.test(pw))        score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;
        const levels = [
            {w:'0%',bg:'transparent',t:'Enter a password'},
            {w:'25%',bg:'#ef4444',t:'Weak'},
            {w:'50%',bg:'#f59e0b',t:'Fair'},
            {w:'75%',bg:'#60a5fa',t:'Good'},
            {w:'100%',bg:'#34d399',t:'Strong ✅'},
        ];
        const l = levels[score]||levels[0];
        bar.style.width = l.w; bar.style.background = l.bg; hint.textContent = l.t;
    }

    function toggleField(id) { const el = document.getElementById(id); el.type = el.type==='password'?'text':'password'; }
    function showAlert(type,msg) { const el=document.getElementById('alert'); el.className='alert '+type; el.textContent=msg; el.style.display='flex'; }
    function hideAlert() { document.getElementById('alert').style.display='none'; }

    async function submitRegistration() {
        const password = document.getElementById('reg_password').value;
        const confirm  = document.getElementById('reg_confirm').value;
        if (!password || password.length < 8) { showAlert('error','❌ Password must be at least 8 characters.'); return; }
        if (password !== confirm) { showAlert('error','❌ Passwords do not match.'); return; }

        const btn = document.getElementById('btn-register');
        btn.disabled = true; btn.textContent = 'Creating…';

        const payload = {
            role      : selectedRole,
            full_name : document.getElementById('full_name').value.trim(),
            email     : document.getElementById('reg_email').value.trim(),
            password  : password,
        };

        try {
            const res  = await fetch('saveRegister.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) });
            const raw  = await res.text();
            let data;
            try { data = JSON.parse(raw); } catch(e) { console.error('Non-JSON:',raw); showAlert('error','❌ Server error. Check console.'); btn.disabled=false; btn.textContent='Create Account'; return; }

            if (data.success) {
                const icon  = document.getElementById('pending-icon');
                const title = document.getElementById('pending-title');
                const badge = document.getElementById('pending-badge');
                const desc  = document.getElementById('pending-desc');

                if (data.status === 'pending') {
                    icon.textContent  = '⏳';
                    title.textContent = 'Registration Submitted!';
                    badge.textContent = 'PENDING APPROVAL';
                    badge.style.cssText = 'background:rgba(240,180,41,.12);color:#f0b429;border:1px solid rgba(240,180,41,.25);display:inline-block;margin:1rem 0;padding:6px 18px;border-radius:100px;font-size:.75rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;';
                    desc.textContent  = data.message || 'Your account is pending approval. A Super Admin will review your request.';
                } else {
                    icon.textContent  = '✅';
                    title.textContent = 'Account Created!';
                    badge.textContent = 'APPROVED';
                    badge.style.cssText = 'background:rgba(52,211,153,.12);color:#34d399;border:1px solid rgba(52,211,153,.25);display:inline-block;margin:1rem 0;padding:6px 18px;border-radius:100px;font-size:.75rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;';
                    desc.textContent  = 'Your account has been created. You can now sign in.';
                }
                goStep(2);
            } else {
                showAlert('error','❌ ' + data.message);
                btn.disabled = false; btn.textContent = 'Create Account';
            }
        } catch(err) {
            console.error(err); showAlert('error','❌ System error. Try again.');
            btn.disabled = false; btn.textContent = 'Create Account';
        }
    }
</script>
</body>
</html>