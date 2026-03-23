<?php
// globalxadminzportal/change_password.php
// Used for first-time login forced password change
session_start();
if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | FarmPro Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Syne:wght@400;600;700;800&display=swap');
        :root { --bg:#080d18;--card:#0e1623;--border2:#243450;--text:#d4e0f0;--muted:#4a6280;--accent:#34d399;--accent2:#059669;--danger:#ef4444; }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Syne',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;}
        body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(52,211,153,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(52,211,153,.025) 1px,transparent 1px);background-size:60px 60px;pointer-events:none;z-index:0;}
        .wrap{position:relative;z-index:1;width:100%;max-width:420px;background:var(--card);border:1px solid var(--border2);border-radius:20px;padding:2.5rem;box-shadow:0 40px 80px -20px rgba(0,0,0,.7),0 0 40px -10px rgba(52,211,153,.06);}
        .logo{font-family:'Bebas Neue',sans-serif;font-size:1.6rem;letter-spacing:.06em;color:var(--accent);margin-bottom:1.5rem;}
        .title{font-family:'Bebas Neue',sans-serif;font-size:2rem;letter-spacing:.04em;color:#fff;margin-bottom:.25rem;}
        .sub{font-size:.83rem;color:var(--muted);margin-bottom:2rem;line-height:1.5;}
        .alert{padding:.8rem 1rem;border-radius:8px;font-size:.83rem;font-weight:600;margin-bottom:1.25rem;display:none;align-items:center;gap:8px;}
        .alert.error{display:flex;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5;}
        .alert.success{display:flex;background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.3);color:#6ee7b7;}
        .form-group{margin-bottom:1.1rem;}
        .form-label{display:block;font-size:.7rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin-bottom:.45rem;}
        .input-wrap{position:relative;}
        .input-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--muted);width:17px;height:17px;pointer-events:none;}
        .form-control{width:100%;padding:.8rem 2.6rem .8rem 2.6rem;background:rgba(255,255,255,.03);border:1px solid var(--border2);border-radius:9px;color:var(--text);font-size:.92rem;font-family:'Syne',sans-serif;outline:none;transition:border-color .2s,box-shadow .2s;}
        .form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(52,211,153,.08);background:rgba(52,211,153,.03);}
        .pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;padding:4px;display:flex;align-items:center;transition:color .2s;}
        .pw-toggle:hover{color:var(--text);}
        .pw-strength{height:4px;border-radius:2px;margin-top:6px;background:var(--border2);overflow:hidden;}
        .pw-strength-bar{height:100%;width:0;border-radius:2px;transition:width .3s,background .3s;}
        .pw-hint{font-size:.7rem;color:var(--muted);margin-top:4px;}
        .btn{width:100%;padding:.85rem;background:linear-gradient(135deg,var(--accent),var(--accent2));border:none;border-radius:9px;color:#051a0e;font-family:'Syne',sans-serif;font-weight:800;font-size:.92rem;cursor:pointer;transition:all .2s;margin-top:.5rem;}
        .btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(52,211,153,.3);}
        .btn:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none;}
        .skip-link{display:block;text-align:center;margin-top:1rem;font-size:.8rem;color:var(--muted);text-decoration:none;transition:color .2s;}
        .skip-link:hover{color:var(--text);}
    </style>
</head>
<body>
<div class="wrap">
    <div class="logo">🌾 FarmPro</div>
    <div class="title">Set New Password</div>
    <div class="sub">For security, please change your password before continuing.</div>

    <div id="alert" class="alert"></div>

    <div class="form-group">
        <label class="form-label">Current Password</label>
        <div class="input-wrap">
            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <input type="password" id="current_pw" class="form-control" placeholder="Current password">
            <button type="button" class="pw-toggle" onclick="toggleField('current_pw')"><svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
        </div>
    </div>
    <div class="form-group">
        <label class="form-label">New Password</label>
        <div class="input-wrap">
            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <input type="password" id="new_pw" class="form-control" placeholder="Min. 8 characters" oninput="checkStrength()">
            <button type="button" class="pw-toggle" onclick="toggleField('new_pw')"><svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
        </div>
        <div class="pw-strength"><div class="pw-strength-bar" id="pw-bar"></div></div>
        <div class="pw-hint" id="pw-hint">Enter a new password</div>
    </div>
    <div class="form-group">
        <label class="form-label">Confirm New Password</label>
        <div class="input-wrap">
            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <input type="password" id="confirm_pw" class="form-control" placeholder="Repeat new password">
            <button type="button" class="pw-toggle" onclick="toggleField('confirm_pw')"><svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
        </div>
    </div>

    <button class="btn" id="btnChange" onclick="changePassword()">Update Password</button>
    <a href="farm_page.php" class="skip-link">Skip for now →</a>
</div>

<script>
    function toggleField(id) { const el = document.getElementById(id); el.type = el.type === 'password' ? 'text' : 'password'; }

    function checkStrength() {
        const pw = document.getElementById('new_pw').value;
        const bar = document.getElementById('pw-bar');
        const hint = document.getElementById('pw-hint');
        let score = 0;
        if (pw.length >= 8) score++;
        if (/[A-Z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;
        const levels = [
            { w:'0%', bg:'transparent', t:'Enter a new password' },
            { w:'25%', bg:'#ef4444', t:'Weak' },
            { w:'50%', bg:'#f59e0b', t:'Fair' },
            { w:'75%', bg:'#60a5fa', t:'Good' },
            { w:'100%', bg:'#34d399', t:'Strong ✅' },
        ];
        const l = levels[score] || levels[0];
        bar.style.width = l.w; bar.style.background = l.bg; hint.textContent = l.t;
    }

    function showAlert(type, msg) {
        const el = document.getElementById('alert');
        el.className = 'alert ' + type; el.textContent = msg; el.style.display = 'flex';
    }

    async function changePassword() {
        const current = document.getElementById('current_pw').value;
        const newPw   = document.getElementById('new_pw').value;
        const confirm = document.getElementById('confirm_pw').value;

        if (!current || !newPw || !confirm) { showAlert('error', '❌ All fields are required.'); return; }
        if (newPw.length < 8)  { showAlert('error', '❌ New password must be at least 8 characters.'); return; }
        if (newPw !== confirm) { showAlert('error', '❌ Passwords do not match.'); return; }

        const btn = document.getElementById('btnChange');
        btn.disabled = true; btn.textContent = 'Updating…';

        const res  = await fetch('updateProfile.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ action:'update_password', current_password: current, new_password: newPw })
        });
        const data = await res.json();

        if (data.success) {
            showAlert('success', '✅ Password updated! Redirecting…');
            setTimeout(() => window.location.href = 'farm_page.php', 1200);
        } else {
            showAlert('error', '❌ ' + data.message);
            btn.disabled = false; btn.textContent = 'Update Password';
        }
    }
</script>
</body>
</html>