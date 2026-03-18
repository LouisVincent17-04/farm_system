<?php
// globalxadminportal/profile.php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }

include '../config/SadminConnection.php';

// Refresh admin data from DB
$stmt = $conn->prepare("SELECT * FROM admin_users WHERE admin_id = ?");
$stmt->execute([$_SESSION['admin']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$admin) { session_destroy(); header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | FarmPro Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Syne:wght@400;600;700;800&display=swap');

        :root {
            --bg: #080d18; --card: #0e1623; --border: #1a2740; --border2: #243450;
            --text: #d4e0f0; --muted: #4a6280; --accent: #34d399; --accent2: #059669;
            --gold: #f0b429; --danger: #ef4444;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Syne', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

        body::before {
            content: ''; position: fixed; inset: 0;
            background-image: linear-gradient(rgba(52,211,153,.025) 1px, transparent 1px), linear-gradient(90deg, rgba(52,211,153,.025) 1px, transparent 1px);
            background-size: 60px 60px; pointer-events: none; z-index: 0;
        }

        .topnav {
            position: sticky; top: 0; z-index: 100;
            background: rgba(8,13,24,.95); border-bottom: 1px solid var(--border2);
            backdrop-filter: blur(12px);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 2rem; height: 60px;
        }
        .topnav-brand { font-family: 'Bebas Neue', sans-serif; font-size: 1.4rem; letter-spacing: .06em; color: var(--accent); }
        .topnav-back { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: var(--muted); font-size: .88rem; font-weight: 600; transition: color .2s; }
        .topnav-back:hover { color: #fff; }

        .container { position: relative; z-index: 1; max-width: 680px; margin: 0 auto; padding: 2.5rem 1.5rem; }

        .page-title { font-family: 'Bebas Neue', sans-serif; font-size: 2.2rem; letter-spacing: .04em; color: #fff; margin-bottom: .25rem; }
        .page-sub { font-size: .85rem; color: var(--muted); margin-bottom: 2rem; }

        .alert { padding: .85rem 1rem; border-radius: 8px; font-size: .85rem; font-weight: 600; margin-bottom: 1.25rem; display: none; align-items: center; gap: 8px; }
        .alert.success { display: flex; background: rgba(52,211,153,.1); border: 1px solid rgba(52,211,153,.3); color: #6ee7b7; }
        .alert.error   { display: flex; background: rgba(239,68,68,.1);  border: 1px solid rgba(239,68,68,.3);  color: #fca5a5; }

        /* Avatar card */
        .avatar-card {
            background: linear-gradient(145deg, rgba(52,211,153,.07), rgba(5,150,105,.03)), var(--card);
            border: 1px solid var(--border2); border-radius: 16px;
            padding: 2rem; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;
        }
        .avatar {
            width: 80px; height: 80px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            display: flex; align-items: center; justify-content: center;
            font-family: 'Bebas Neue', sans-serif; font-size: 2.2rem; color: #051a0e;
            flex-shrink: 0; border: 3px solid rgba(52,211,153,.2);
        }
        .avatar-info h2 { font-size: 1.3rem; font-weight: 700; color: #fff; margin-bottom: 4px; }
        .role-badge {
            display: inline-block; padding: 3px 12px; border-radius: 100px;
            font-size: .72rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
            background: rgba(52,211,153,.12); color: var(--accent); border: 1px solid rgba(52,211,153,.25);
        }
        .last-login { font-size: .78rem; color: var(--muted); margin-top: 6px; }

        /* Tabs */
        .tabs { display: flex; gap: 4px; margin-bottom: 1.5rem; background: var(--card); padding: 4px; border-radius: 10px; border: 1px solid var(--border2); }
        .tab-btn {
            flex: 1; padding: .65rem; border: none; border-radius: 7px; cursor: pointer;
            font-family: 'Syne', sans-serif; font-size: .85rem; font-weight: 700;
            background: transparent; color: var(--muted); transition: all .2s;
        }
        .tab-btn.active { background: rgba(52,211,153,.12); color: var(--accent); }
        .tab-btn:hover:not(.active) { color: var(--text); background: rgba(255,255,255,.04); }

        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* Cards */
        .card { background: var(--card); border: 1px solid var(--border2); border-radius: 16px; padding: 1.75rem; margin-bottom: 1.25rem; }
        .card-title { font-size: .72rem; font-weight: 700; letter-spacing: .15em; text-transform: uppercase; color: var(--accent); margin-bottom: 1.25rem; padding-bottom: .75rem; border-bottom: 1px solid var(--border); }

        .form-group { margin-bottom: 1.1rem; }
        .form-label { display: block; font-size: .72rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); margin-bottom: .45rem; }
        .input-wrap { position: relative; }
        .input-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); width: 17px; height: 17px; pointer-events: none; }
        .form-control {
            width: 100%; padding: .8rem 1rem .8rem 2.6rem;
            background: rgba(255,255,255,.03); border: 1px solid var(--border2);
            border-radius: 9px; color: var(--text); font-size: .92rem; font-family: 'Syne', sans-serif;
            outline: none; transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(52,211,153,.08); background: rgba(52,211,153,.03); }
        .form-control:read-only { opacity: .55; cursor: not-allowed; }
        .form-control.no-icon { padding-left: 1rem; }

        .pw-toggle { position: absolute; right: 13px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--muted); cursor: pointer; padding: 4px; display: flex; align-items: center; transition: color .2s; }
        .pw-toggle:hover { color: var(--text); }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        .btn-save {
            display: inline-flex; align-items: center; gap: 8px;
            padding: .8rem 2rem; background: linear-gradient(135deg, var(--accent), var(--accent2));
            border: none; border-radius: 9px; color: #051a0e;
            font-family: 'Syne', sans-serif; font-weight: 800; font-size: .9rem;
            cursor: pointer; transition: all .2s;
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(52,211,153,.3); }
        .btn-save:disabled { opacity: .5; cursor: not-allowed; transform: none; box-shadow: none; }

        .pw-strength { height: 4px; border-radius: 2px; margin-top: 6px; background: var(--border); overflow: hidden; }
        .pw-strength-bar { height: 100%; width: 0; border-radius: 2px; transition: width .3s, background .3s; }
        .pw-hint { font-size: .72rem; color: var(--muted); margin-top: 4px; }

        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<nav class="topnav">
    <div class="topnav-brand">🌾 FarmPro Admin</div>
    <a href="farm_page.php" class="topnav-back">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Dashboard
    </a>
</nav>

<div class="container">

    <h1 class="page-title">My Profile</h1>
    <p class="page-sub">Manage your account details and security settings.</p>

    <div id="alert" class="alert"></div>

    <!-- Avatar card -->
    <div class="avatar-card">
        <div class="avatar"><?= strtoupper(substr($admin['full_name'], 0, 1)) ?></div>
        <div class="avatar-info">
            <h2><?= htmlspecialchars($admin['full_name']) ?></h2>
            <span class="role-badge">Super Admin</span>
          
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('info', this)">Account Info</button>
        <button class="tab-btn" onclick="switchTab('password', this)">Change Password</button>
    </div>

    <!-- Tab: Account Info -->
    <div id="tab-info" class="tab-panel active">
        <div class="card">
            <div class="card-title">Personal Information</div>
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <div class="input-wrap">
                    <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <input type="text" id="full_name" class="form-control" value="<?= htmlspecialchars($admin['full_name']) ?>" placeholder="Your full name">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-wrap">
                    <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <input type="email" id="email" class="form-control" value="<?= htmlspecialchars($admin['email']) ?>" placeholder="admin@farmpro.com">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <div class="input-wrap">
                    <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <input type="text" class="form-control" value="Superadmin" readonly>
                </div>
            </div>
            <button class="btn-save" onclick="saveInfo()">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Save Changes
            </button>
        </div>
    </div>

    <!-- Tab: Change Password -->
    <div id="tab-password" class="tab-panel">
        <div class="card">
            <div class="card-title">Change Password</div>
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <div class="input-wrap">
                    <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <input type="password" id="current_pw" class="form-control" placeholder="Enter current password">
                    <button type="button" class="pw-toggle" onclick="toggleField('current_pw')">
                        <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <div class="input-wrap">
                    <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <input type="password" id="new_pw" class="form-control" placeholder="Min. 8 characters" oninput="checkStrength()">
                    <button type="button" class="pw-toggle" onclick="toggleField('new_pw')">
                        <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
                <div class="pw-strength"><div class="pw-strength-bar" id="pw-bar"></div></div>
                <div class="pw-hint" id="pw-hint">Enter a new password</div>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <div class="input-wrap">
                    <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <input type="password" id="confirm_pw" class="form-control" placeholder="Repeat new password">
                    <button type="button" class="pw-toggle" onclick="toggleField('confirm_pw')">
                        <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
            </div>
            <button class="btn-save" onclick="savePassword()">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Update Password
            </button>
        </div>
    </div>

</div>

<script>
    function switchTab(name, el) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('tab-' + name).classList.add('active');
        document.getElementById('alert').style.display = 'none';
    }

    function toggleField(id) {
        const el = document.getElementById(id);
        el.type = el.type === 'password' ? 'text' : 'password';
    }

    function showAlert(type, msg) {
        const el = document.getElementById('alert');
        el.className = 'alert ' + type;
        el.textContent = msg;
        el.style.display = 'flex';
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function checkStrength() {
        const pw  = document.getElementById('new_pw').value;
        const bar = document.getElementById('pw-bar');
        const hint = document.getElementById('pw-hint');
        let score = 0;
        if (pw.length >= 8) score++;
        if (/[A-Z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;

        const levels = [
            { w: '0%',   bg: 'transparent', t: 'Enter a new password' },
            { w: '25%',  bg: '#ef4444', t: 'Weak' },
            { w: '50%',  bg: '#f59e0b', t: 'Fair' },
            { w: '75%',  bg: '#60a5fa', t: 'Good' },
            { w: '100%', bg: '#34d399', t: 'Strong ✅' },
        ];
        const l = levels[score] || levels[0];
        bar.style.width = l.w; bar.style.background = l.bg; hint.textContent = l.t;
    }

    async function saveInfo() {
        const payload = {
            action   : 'update_info',
            full_name: document.getElementById('full_name').value.trim(),
            email    : document.getElementById('email').value.trim(),
        };
        const res  = await fetch('updateProfile.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json();
        showAlert(data.success ? 'success' : 'error', (data.success ? '✅ ' : '❌ ') + data.message);
    }

    async function savePassword() {
        const current = document.getElementById('current_pw').value;
        const newPw   = document.getElementById('new_pw').value;
        const confirm = document.getElementById('confirm_pw').value;

        if (!current || !newPw || !confirm) { showAlert('error', '❌ All password fields are required.'); return; }
        if (newPw.length < 8) { showAlert('error', '❌ New password must be at least 8 characters.'); return; }
        if (newPw !== confirm) { showAlert('error', '❌ New passwords do not match.'); return; }

        const res  = await fetch('updateProfile.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action: 'update_password', current_password: current, new_password: newPw })
        });
        const data = await res.json();
        showAlert(data.success ? 'success' : 'error', (data.success ? '✅ ' : '❌ ') + data.message);
        if (data.success) { document.getElementById('current_pw').value = ''; document.getElementById('new_pw').value = ''; document.getElementById('confirm_pw').value = ''; checkStrength(); }
    }
</script>
</body>
</html>