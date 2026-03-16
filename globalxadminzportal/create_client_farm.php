<?php
// globalxadminportal/create_client_farm.php
session_start();
include '../config/SadminConnection.php';

// Auth guard
if (!isset($_SESSION['admin'])) {
    header('Location: login.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Client Farm | FarmPro Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap');

        :root {
            --bg:      #0f172a;
            --card:    #1e293b;
            --border:  #334155;
            --text:    #e2e8f0;
            --muted:   #64748b;
            --accent:  #34d399;
            --accent2: #059669;
            --danger:  #ef4444;
            --gold:    #facc15;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: fixed; inset: 0;
            background-image:
                linear-gradient(rgba(148,163,184,.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148,163,184,.03) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none; z-index: 0;
        }

        /* ── Top Nav ── */
        .topnav {
            position: sticky; top: 0; z-index: 100;
            background: rgba(15,23,42,.95);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(12px);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 2rem; height: 60px;
        }
        .topnav-brand {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.4rem; letter-spacing: .06em; color: var(--accent);
        }
        .topnav-back {
            display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none; color: var(--muted);
            font-size: .9rem; font-weight: 600; transition: color .2s;
        }
        .topnav-back:hover { color: #fff; }

        /* ── Container ── */
        .container {
            position: relative; z-index: 1;
            max-width: 780px; margin: 0 auto;
            padding: 2.5rem 1.5rem;
        }

        .page-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2.4rem; letter-spacing: .04em; color: #fff;
            margin-bottom: .25rem;
        }
        .page-sub { font-size: .9rem; color: var(--muted); margin-bottom: 2rem; }

        /* ── Alert ── */
        .alert {
            padding: 1rem 1.25rem; border-radius: 10px;
            margin-bottom: 1.5rem; font-weight: 600; display: none;
        }
        .alert.success { background: rgba(52,211,153,.1); border: 1px solid var(--accent); color: #6ee7b7; }
        .alert.error   { background: rgba(239,68,68,.1);  border: 1px solid var(--danger); color: #fca5a5; }

        /* ── Card ── */
        .card {
            background: rgba(30,41,59,.7);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 16px; padding: 2rem;
            backdrop-filter: blur(8px);
            margin-bottom: 1.5rem;
        }
        .card-title {
            font-size: .75rem; font-weight: 700;
            letter-spacing: .15em; text-transform: uppercase;
            color: var(--accent); margin-bottom: 1.25rem;
            padding-bottom: .75rem; border-bottom: 1px solid var(--border);
        }

        /* ── Form ── */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-label {
            display: block; font-size: .82rem; font-weight: 600;
            color: #cbd5e1; margin-bottom: .4rem;
        }
        .form-label span { color: var(--danger); }
        .form-control {
            width: 100%; padding: .75rem 1rem;
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 8px; color: #fff; font-size: .95rem;
            font-family: 'DM Sans', sans-serif;
            transition: border-color .2s; outline: none;
        }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(52,211,153,.1); }
        .form-hint { font-size: .75rem; color: var(--muted); margin-top: .35rem; }

        /* DB name preview */
        .db-preview {
            background: rgba(15,23,42,.8);
            border: 1px dashed #475569;
            border-radius: 8px; padding: .75rem 1rem;
            font-family: monospace; font-size: .9rem;
            color: var(--accent); margin-top: .5rem;
        }
        .db-preview span { color: var(--muted); font-size: .75rem; font-family: 'DM Sans', sans-serif; }

        /* Plan cards */
        .plan-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .plan-card {
            border: 1.5px solid var(--border); border-radius: 10px;
            padding: 1rem; cursor: pointer; transition: all .2s; text-align: center;
        }
        .plan-card:hover { border-color: var(--accent); background: rgba(52,211,153,.05); }
        .plan-card.selected { border-color: var(--accent); background: rgba(52,211,153,.1); }
        .plan-card input { display: none; }
        .plan-name { font-weight: 700; font-size: 1rem; color: #fff; margin-bottom: 4px; }
        .plan-desc { font-size: .78rem; color: var(--muted); }

        /* Submit btn */
        .btn-provision {
            width: 100%; padding: 1rem;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border: none; border-radius: 12px; color: #0f172a;
            font-weight: 800; font-size: 1rem; font-family: 'DM Sans', sans-serif;
            cursor: pointer; transition: all .2s; margin-top: .5rem;
        }
        .btn-provision:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(52,211,153,.3); }
        .btn-provision:disabled { opacity: .5; cursor: not-allowed; transform: none; }

        /* Credentials box (shown after success) */
        .creds-box {
            display: none;
            background: rgba(15,23,42,.9);
            border: 1px solid var(--accent);
            border-radius: 12px; padding: 1.5rem;
            margin-top: 1.5rem;
        }
        .creds-box h3 { color: var(--accent); margin-bottom: 1rem; font-size: 1rem; }
        .cred-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: .6rem; }
        .cred-label { font-size: .8rem; color: var(--muted); }
        .cred-value { font-family: monospace; font-size: .9rem; color: #fff; background: #0f172a; padding: 4px 10px; border-radius: 6px; }
        .cred-warning { margin-top: 1rem; font-size: .8rem; color: #fbbf24; background: rgba(251,191,36,.1); border: 1px solid rgba(251,191,36,.2); padding: .75rem; border-radius: 8px; }

        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; }
            .plan-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav class="topnav">
    <div class="topnav-brand">🌾 FarmPro Admin</div>
    <a href="farm_list.php" class="topnav-back">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Back to Farms
    </a>
</nav>

<div class="container">

    <h1 class="page-title">🚜 New Client Farm</h1>
    <p class="page-sub">Provision a new isolated farm database and register the client.</p>

    <div id="alert" class="alert"></div>

    <form id="farmForm">

        <!-- Owner Info -->
        <div class="card">
            <div class="card-title">👤 Client / Owner Information</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Owner Full Name <span>*</span></label>
                    <input type="text" id="owner_name" class="form-control" placeholder="e.g. Juan Dela Cruz" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Owner Email <span>*</span></label>
                    <input type="email" id="owner_email" class="form-control" placeholder="juan@example.com" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Owner Phone</label>
                <input type="text" id="owner_phone" class="form-control" placeholder="+63 9XX XXX XXXX">
            </div>
        </div>

        <!-- Farm Info -->
        <div class="card">
            <div class="card-title">🏡 Farm Details</div>
            <div class="form-group">
                <label class="form-label">Farm Name <span>*</span></label>
                <input type="text" id="farm_name" class="form-control" placeholder="e.g. Green Pastures Farm" required oninput="previewDbName()">
                <div class="db-preview" id="db-preview">
                    <span>Database name will be:</span><br>
                    <span id="db-name-preview" style="color:#34d399;">—</span>
                </div>
                <div class="form-hint">The database name is auto-derived from the farm name and cannot be changed later.</div>
            </div>
        </div>

        <!-- Plan -->
        <div class="card">
            <div class="card-title">📦 Subscription Plan</div>
            <div class="plan-grid">
                <label class="plan-card" onclick="selectPlan('Basic', this)">
                    <input type="radio" name="plan" value="Basic" checked>
                    <div class="plan-name">Basic</div>
                    <div class="plan-desc">5 users · 500 animals</div>
                </label>
                <label class="plan-card" onclick="selectPlan('Standard', this)">
                    <input type="radio" name="plan" value="Standard">
                    <div class="plan-name">Standard</div>
                    <div class="plan-desc">15 users · 2,000 animals</div>
                </label>
                <label class="plan-card selected" onclick="selectPlan('Premium', this)">
                    <input type="radio" name="plan" value="Premium">
                    <div class="plan-name">Premium</div>
                    <div class="plan-desc">Unlimited users & animals</div>
                </label>
            </div>

            <div class="form-row" style="margin-top:1.25rem;">
                <div class="form-group">
                    <label class="form-label">Max Users</label>
                    <input type="number" id="max_users" class="form-control" value="5" min="1">
                </div>
                <div class="form-group">
                    <label class="form-label">Max Animals</label>
                    <input type="number" id="max_animals" class="form-control" value="500" min="1">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Trial Period (days)</label>
                <input type="number" id="trial_days" class="form-control" value="30" min="1" max="365">
                <div class="form-hint">Farm starts as "Trial" status. Upgrade to Active after payment.</div>
            </div>
        </div>

        <button type="submit" class="btn-provision" id="btnProvision">
            🚀 Provision Farm Database
        </button>
    </form>

    <!-- Credentials shown after success -->
    <div class="creds-box" id="credsBox">
        <h3>✅ Farm Provisioned — Save These Credentials</h3>
        <div class="cred-row"><span class="cred-label">Database Name</span><span class="cred-value" id="cred-db"></span></div>
        <div class="cred-row"><span class="cred-label">DB Username</span><span class="cred-value" id="cred-user"></span></div>
        <div class="cred-row"><span class="cred-label">DB Password</span><span class="cred-value" id="cred-pass"></span></div>
        <div class="cred-row"><span class="cred-label">Trial Ends</span><span class="cred-value" id="cred-trial"></span></div>
        <div class="cred-warning">⚠️ This password is shown only once. Copy and share it securely with the client.</div>
    </div>

</div>

<script>
    let selectedPlan = 'Basic';

    const planLimits = {
        Basic:    { users: 5,  animals: 500 },
        Standard: { users: 15, animals: 2000 },
        Premium:  { users: 999, animals: 99999 },
    };

    // Mark the Basic card as selected on load
    document.querySelector('.plan-card').classList.add('selected');

    function selectPlan(plan, el) {
        document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');
        selectedPlan = plan;
        document.getElementById('max_users').value   = planLimits[plan].users;
        document.getElementById('max_animals').value = planLimits[plan].animals;
    }

    function previewDbName() {
        const raw = document.getElementById('farm_name').value.trim();
        const db  = 'farm_' + raw.toLowerCase().replace(/[^a-z0-9]/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
        document.getElementById('db-name-preview').textContent = db || '—';
    }

    function showAlert(type, msg) {
        const el = document.getElementById('alert');
        el.className = 'alert ' + type;
        el.textContent = msg;
        el.style.display = 'block';
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    document.getElementById('farmForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const btn = document.getElementById('btnProvision');
        btn.disabled = true;
        btn.textContent = '⏳ Provisioning…';

        const payload = {
            farm_name   : document.getElementById('farm_name').value.trim(),
            owner_name  : document.getElementById('owner_name').value.trim(),
            owner_email : document.getElementById('owner_email').value.trim(),
            owner_phone : document.getElementById('owner_phone').value.trim(),
            plan        : selectedPlan,
            max_users   : parseInt(document.getElementById('max_users').value),
            max_animals : parseInt(document.getElementById('max_animals').value),
            trial_days  : parseInt(document.getElementById('trial_days').value),
        };

        try {
            const res  = await fetch('saveClientFarm.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const raw  = await res.text();
            let data;
            try { data = JSON.parse(raw); } catch(err) {
                console.error('Non-JSON response:', raw);
                showAlert('error', '❌ Server error. Check console.');
                btn.disabled = false; btn.textContent = '🚀 Provision Farm Database';
                return;
            }

            if (data.success) {
                showAlert('success', `✅ Farm "${payload.farm_name}" provisioned successfully!`);

                // Show credentials
                document.getElementById('cred-db').textContent    = data.db_name;
                document.getElementById('cred-user').textContent  = data.db_user;
                document.getElementById('cred-pass').textContent  = data.db_password;
                document.getElementById('cred-trial').textContent = data.trial_ends;
                document.getElementById('credsBox').style.display = 'block';
                document.getElementById('credsBox').scrollIntoView({ behavior: 'smooth' });

                document.getElementById('farmForm').reset();
                document.getElementById('db-name-preview').textContent = '—';
            } else {
                showAlert('error', '❌ ' + data.message);
                btn.disabled = false;
                btn.textContent = '🚀 Provision Farm Database';
            }
        } catch (err) {
            console.error(err);
            showAlert('error', '❌ System error. Check console.');
            btn.disabled = false;
            btn.textContent = '🚀 Provision Farm Database';
        }
    });
</script>
</body>
</html>