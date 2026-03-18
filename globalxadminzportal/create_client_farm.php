<?php
// globalxadminportal/create_client_farm.php
session_start();
include '../config/SadminConnection.php';

// ========================================================================
// INTERNAL AJAX HANDLER FOR AUTOCOMPLETE
// ========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'search_admin') {
    @ob_end_clean();
    header('Content-Type: application/json');
    $term = '%' . trim($_GET['term']) . '%';
    try {
        $stmt = $conn->prepare("SELECT full_name, email, phone_no FROM admin_users WHERE full_name LIKE ? OR email LIKE ? OR phone_no LIKE ? LIMIT 5");
        $stmt->execute([$term, $term, $term]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e) {
        echo json_encode([]);
    }
    exit;
}
// ========================================================================

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
            --blue:    #3b82f6;
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

        .topnav {
            position: sticky; top: 0; z-index: 100;
            background: rgba(15,23,42,.95);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(12px);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 2rem; height: 60px;
        }
        .topnav-brand { font-family: 'Bebas Neue', sans-serif; font-size: 1.4rem; letter-spacing: .06em; color: var(--accent); }
        .topnav-back { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: var(--muted); font-size: .9rem; font-weight: 600; transition: color .2s; }
        .topnav-back:hover { color: #fff; }

        .container { position: relative; z-index: 1; max-width: 780px; margin: 0 auto; padding: 2.5rem 1.5rem; }

        .page-title { font-family: 'Bebas Neue', sans-serif; font-size: 2.4rem; letter-spacing: .04em; color: #fff; margin-bottom: .25rem; }
        .page-sub { font-size: .9rem; color: var(--muted); margin-bottom: 2rem; }

        .alert { padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem; font-weight: 600; display: none; }
        .alert.success { background: rgba(52,211,153,.1); border: 1px solid var(--accent); color: #6ee7b7; }
        .alert.error   { background: rgba(239,68,68,.1);  border: 1px solid var(--danger); color: #fca5a5; }

        .card { background: rgba(30,41,59,.7); border: 1px solid rgba(255,255,255,.08); border-radius: 16px; padding: 2rem; backdrop-filter: blur(8px); margin-bottom: 1.5rem; }
        .card-title { font-size: .75rem; font-weight: 700; letter-spacing: .15em; text-transform: uppercase; color: var(--accent); margin-bottom: 1.25rem; padding-bottom: .75rem; border-bottom: 1px solid var(--border); }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-group { margin-bottom: 1rem; position: relative;}
        .form-label { display: block; font-size: .82rem; font-weight: 600; color: #cbd5e1; margin-bottom: .4rem; }
        .form-label span { color: var(--danger); }
        .form-control {
            width: 100%; padding: .75rem 1rem;
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 8px; color: #fff; font-size: .95rem;
            font-family: 'DM Sans', sans-serif;
            transition: border-color .2s, box-shadow .2s; outline: none;
        }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(52,211,153,.1); }
        .form-control.valid   { border-color: var(--accent); }
        .form-control.invalid { border-color: var(--danger); }
        .form-hint { font-size: .75rem; color: var(--muted); margin-top: .35rem; }

        /* ── Autocomplete Dropdown ── */
        .ac-dropdown {
            position: absolute; top: 100%; left: 0; width: 100%;
            background: #0f172a; border: 1px solid var(--accent); border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 1000;
            display: none; overflow: hidden; margin-top: 4px;
        }
        .ac-item { padding: 10px 15px; cursor: pointer; border-bottom: 1px solid var(--border); transition: 0.2s; }
        .ac-item:last-child { border-bottom: none; }
        .ac-item:hover { background: rgba(52, 211, 153, 0.1); }
        .ac-name { font-weight: bold; color: #fff; font-size: 0.95rem; }
        .ac-email { font-size: 0.8rem; color: var(--muted); }

        /* ── DB Name Field ── */
        .db-field-wrap { position: relative; }
        .db-name-input { font-family: monospace !important; font-size: .95rem !important; letter-spacing: .02em; }
        .db-status { margin-top: .4rem; font-size: .78rem; font-weight: 600; display: flex; align-items: center; gap: 6px; min-height: 18px; }
        .db-status.ok      { color: var(--accent); }
        .db-status.error   { color: var(--danger); }
        .db-status.checking { color: var(--muted); }

        /* Full preview pill */
        .db-full-preview {
            display: inline-block; margin-top: .5rem;
            font-family: monospace; font-size: .85rem;
            background: rgba(15,23,42,.9); border: 1px dashed #475569;
            color: var(--accent); padding: 6px 14px; border-radius: 8px;
        }

        .btn-provision {
            width: 100%; padding: 1rem;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border: none; border-radius: 12px; color: #0f172a;
            font-weight: 800; font-size: 1rem; font-family: 'DM Sans', sans-serif;
            cursor: pointer; transition: all .2s; margin-top: .5rem;
        }
        .btn-provision:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(52,211,153,.3); }
        .btn-provision:disabled { opacity: .5; cursor: not-allowed; transform: none; }

        /* Success Box */
        .success-box { 
            display: none; 
            background: rgba(16, 185, 129, 0.1); 
            border: 1px solid var(--accent); 
            border-radius: 12px; 
            padding: 1.5rem; 
            margin-top: 1.5rem; 
            text-align: center;
        }
        .success-box h3 { color: var(--accent); margin-bottom: 0.5rem; font-size: 1.3rem; }
        .success-box p { color: var(--text); font-size: 1rem; margin-bottom: 0;}
        .db-schema-name { font-family: monospace; font-size: 1.1rem; color: #fff; background: #0f172a; padding: 4px 10px; border-radius: 6px; letter-spacing: 0.05em;}

        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav class="topnav">
    <div class="topnav-brand">🌾 FarmPro Admin</div>
    <a href="farm_page.php" class="topnav-back">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Back to Farms
    </a>
</nav>

<div class="container">

    <h1 class="page-title">🚜 New Client Farm</h1>
    <p class="page-sub">Provision a new isolated farm database and assign an <strong>existing</strong> client.</p>

    <div id="alert" class="alert"></div>

    <form id="farmForm">

        <div class="card">
            <div class="card-title">👤 Client / Owner Information</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Owner Full Name <span>*</span></label>
                    <input type="text" id="owner_name" class="form-control" placeholder="e.g. Juan Dela Cruz" required oninput="triggerAutocomplete(this)">
                    <div id="ac-name" class="ac-dropdown"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Owner Email <span>*</span></label>
                    <input type="email" id="owner_email" class="form-control" placeholder="juan@example.com" required oninput="triggerAutocomplete(this)">
                    <div id="ac-email" class="ac-dropdown"></div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Owner Phone</label>
                <input type="text" id="owner_phone" class="form-control" placeholder="+63 9XX XXX XXXX" oninput="triggerAutocomplete(this)">
                <div id="ac-phone" class="ac-dropdown"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">🏡 Farm Details</div>

            <div class="form-group">
                <label class="form-label">Farm Name <span>*</span></label>
                <input type="text" id="farm_name" class="form-control" placeholder="e.g. Green Pastures Farm" required>
            </div>

            <div class="form-group">
                <label class="form-label">Database Name (Schema) <span>*</span></label>
                <input type="text" id="db_name" class="form-control db-name-input" placeholder="e.g. green_pastures_farm" required autocomplete="off" spellcheck="false" oninput="onDbNameInput()">
                <div class="db-status" id="db-status"></div>
                <div class="form-hint">
                    Only lowercase letters, numbers, and underscores. No spaces. This becomes the actual MySQL database name and <strong style="color:#fff;">cannot be changed later.</strong>
                </div>
                <div class="db-full-preview" id="db-full-preview" style="display:none;"></div>
            </div>
        </div>

        <button type="submit" class="btn-provision" id="btnProvision" disabled>
            🚀 Provision Farm Database
        </button>
    </form>

    <div class="success-box" id="successBox">
        <h3>✅ Farm Database Created Successfully!</h3>
        <p>Schema Name: <span class="db-schema-name" id="success-db-name"></span></p>
    </div>

</div>

<script>
    // ── Autocomplete Logic ───────────────────────────────────────────────────
    let acTimer = null;
    
    function triggerAutocomplete(inputEl) {
        clearTimeout(acTimer);
        const val = inputEl.value.trim();
        const dropdown = inputEl.nextElementSibling;

        // Hide all dropdowns first
        document.querySelectorAll('.ac-dropdown').forEach(d => d.style.display = 'none');

        if (val.length < 2) return;

        acTimer = setTimeout(async () => {
            try {
                const res = await fetch(`?action=search_admin&term=${encodeURIComponent(val)}`);
                const data = await res.json();

                if (data.length > 0) {
                    let html = '';
                    data.forEach(item => {
                        const safeName = item.full_name ? item.full_name.replace(/'/g, "\\'") : '';
                        const safeEmail = item.email ? item.email.replace(/'/g, "\\'") : '';
                        const safePhone = item.phone_no ? item.phone_no.replace(/'/g, "\\'") : '';
                        const displayPhone = item.phone_no ? ` • ${item.phone_no}` : '';

                        html += `
                        <div class="ac-item" onclick="fillAdmin('${safeName}', '${safeEmail}', '${safePhone}')">
                            <div class="ac-name">${item.full_name}</div>
                            <div class="ac-email">${item.email}${displayPhone}</div>
                        </div>`;
                    });
                    dropdown.innerHTML = html;
                    dropdown.style.display = 'block';
                }
            } catch(e) {}
        }, 300);
    }

    function fillAdmin(name, email, phone) {
        document.getElementById('owner_name').value = name;
        document.getElementById('owner_email').value = email;
        document.getElementById('owner_phone').value = phone;
        document.querySelectorAll('.ac-dropdown').forEach(d => d.style.display = 'none');
    }

    // Hide dropdown if clicked outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.ac-dropdown') && !e.target.closest('.form-control')) {
            document.querySelectorAll('.ac-dropdown').forEach(d => d.style.display = 'none');
        }
    });

    // ── DB Name live validation ──────────────────────────────────────────────
    let dbNameValid   = false;
    let checkTimer    = null;

    function onDbNameInput() {
        const input   = document.getElementById('db_name');
        const status  = document.getElementById('db-status');
        const preview = document.getElementById('db-full-preview');
        const btn     = document.getElementById('btnProvision');
        const raw     = input.value.trim();

        dbNameValid = false;
        btn.disabled = true;

        input.classList.remove('valid', 'invalid');
        status.className = 'db-status';
        status.textContent = '';
        preview.style.display = 'none';
        document.getElementById('successBox').style.display = 'none'; 

        if (!raw) return;

        const validFormat = /^[a-z0-9_]+$/.test(raw);
        if (!validFormat) {
            input.classList.add('invalid');
            status.className = 'db-status error';
            status.textContent = '❌ Only lowercase letters (a-z), numbers, and underscores allowed.';
            return;
        }

        if (raw.length < 3) {
            input.classList.add('invalid');
            status.className = 'db-status error';
            status.textContent = '❌ Must be at least 3 characters.';
            return;
        }

        preview.textContent = raw;
        preview.style.display = 'inline-block';

        clearTimeout(checkTimer);
        status.className = 'db-status checking';
        status.textContent = '⏳ Checking availability…';

        checkTimer = setTimeout(() => checkDbAvailability(raw), 500);
    }

    async function checkDbAvailability(dbName) {
        const input  = document.getElementById('db_name');
        const status = document.getElementById('db-status');
        const btn    = document.getElementById('btnProvision');

        try {
            const res  = await fetch(`saveClientFarm.php?action=check_db&db_name=${encodeURIComponent(dbName)}`);
            const data = await res.json();

            if (data.available) {
                input.classList.remove('invalid'); input.classList.add('valid');
                status.className = 'db-status ok';
                status.textContent = '✅ Available — this database name is not taken.';
                dbNameValid = true;
                btn.disabled = false;
            } else {
                input.classList.remove('valid'); input.classList.add('invalid');
                status.className = 'db-status error';
                status.textContent = '❌ Already taken — choose a different database name.';
                dbNameValid = false;
                btn.disabled = true;
            }
        } catch (e) {
            status.className = 'db-status error';
            status.textContent = '⚠️ Could not verify. Proceed with caution.';
            dbNameValid = true; 
            btn.disabled = false;
        }
    }

    // ── Form submit ──────────────────────────────────────────────────────────
    function showAlert(type, msg) {
        const el = document.getElementById('alert');
        el.className = 'alert ' + type;
        el.textContent = msg;
        el.style.display = 'block';
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    document.getElementById('farmForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        document.getElementById('successBox').style.display = 'none';

        if (!dbNameValid) { showAlert('error', '❌ Please enter a valid and available database name.'); return; }

        const btn = document.getElementById('btnProvision');
        btn.disabled = true;
        btn.textContent = '⏳ Provisioning…';

        const payload = {
            farm_name   : document.getElementById('farm_name').value.trim(),
            db_name     : document.getElementById('db_name').value.trim(),
            owner_name  : document.getElementById('owner_name').value.trim(),
            owner_email : document.getElementById('owner_email').value.trim(),
            owner_phone : document.getElementById('owner_phone').value.trim()
        };

        try {
            const res  = await fetch('saveClientFarm.php', {
                method : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body   : JSON.stringify(payload)
            });
            const raw  = await res.text();
            let data;
            try { data = JSON.parse(raw); } catch(err) {
                console.error('Non-JSON:', raw);
                showAlert('error', '❌ Server error. Check console.');
                btn.disabled = false; btn.textContent = '🚀 Provision Farm Database';
                return;
            }

            if (data.success) {
                showAlert('success', `Farm provisioned! Setting up schema...`);
                
                // Show the clean success box
                document.getElementById('success-db-name').textContent = data.db_name;
                document.getElementById('successBox').style.display = 'block';
                document.getElementById('successBox').scrollIntoView({ behavior: 'smooth' });

                // Reset form
                document.getElementById('farmForm').reset();
                document.getElementById('db-status').textContent = '';
                document.getElementById('db-full-preview').style.display = 'none';
                document.getElementById('db_name').classList.remove('valid', 'invalid');
                dbNameValid = false;
                
                // Reset button
                btn.textContent = '🚀 Provision Farm Database';
                
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