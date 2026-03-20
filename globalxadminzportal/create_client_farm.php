<?php
// globalxadminportal/create_client_farm.php
require_once 'checkAuth.php';           // uses $_SESSION['user_id'], not $_SESSION['admin']
checkRole('superadmin');                // only superadmins can provision new farms

require_once '../config/SadminConnection.php';

// ============================================================================
// INTERNAL AJAX: Owner autocomplete search
// FIX: was querying admin_users — now queries the unified users table,
//      filtered to role = 'owner' so only valid farm owners appear.
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'search_admin') {
    @ob_end_clean();
    header('Content-Type: application/json');
    $term = '%' . trim($_GET['term'] ?? '') . '%';
    try {
        $stmt = $conn->prepare("
            SELECT full_name, email, phone_no
            FROM   users
            WHERE  (full_name LIKE ? OR email LIKE ? OR phone_no LIKE ?)
              AND  role   IN ('owner', 'superadmin')
              AND  status = 1
            LIMIT  5
        ");
        $stmt->execute([$term, $term, $term]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}
// ============================================================================

$full_name   = $_SESSION['full_name'] ?? 'Admin';
$current_role = $_SESSION['role'] ?? 'superadmin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Client Farm | GATZ SmartFarm</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:      #07090f;
            --card:    #111720;
            --border:  #1c2535;
            --border2: #243045;
            --text:    #c8d8ec;
            --muted:   #455870;
            --accent:  #3dd68c;
            --accent2: #07955a;
            --danger:  #ef4444;
            --gold:    #f4c542;
            --blue:    #4fa3f7;
            --nav-h:   64px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        body::before {
            content: ''; position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background: radial-gradient(ellipse 70% 50% at 15% 0%, rgba(61,214,140,.055) 0%, transparent 60%);
        }
        body::after {
            content: ''; position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image:
                linear-gradient(rgba(61,214,140,.018) 1px, transparent 1px),
                linear-gradient(90deg, rgba(61,214,140,.018) 1px, transparent 1px);
            background-size: 48px 48px;
        }

        /* ── Navbar ── */
        .navbar {
            position: sticky; top: 0; z-index: 100; height: var(--nav-h);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 2rem;
            background: rgba(7,9,15,.85); backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
        }
        .nav-brand { font-family: 'Bebas Neue', sans-serif; font-size: 1.4rem; letter-spacing: .06em; color: var(--accent); text-decoration: none; }
        .nav-brand span { color: var(--gold); }
        .nav-back {
            display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none; color: var(--muted); font-size: .88rem; font-weight: 600;
            padding: .4rem .9rem; border-radius: 8px; transition: color .2s, background .2s;
        }
        .nav-back:hover { color: #fff; background: rgba(255,255,255,.05); }
        .nav-user { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: var(--muted); }
        .nav-avatar { width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent2)); display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; color: #051a0e; }

        /* ── Layout ── */
        .container { position: relative; z-index: 1; max-width: 800px; margin: 0 auto; padding: 2.5rem 1.5rem 4rem; }

        .page-eyebrow { font-size: .7rem; font-weight: 600; letter-spacing: .2em; text-transform: uppercase; color: var(--accent); margin-bottom: .4rem; }
        .page-title { font-family: 'Bebas Neue', sans-serif; font-size: 2.4rem; letter-spacing: .04em; color: #fff; margin-bottom: .25rem; }
        .page-sub { font-size: .88rem; color: var(--muted); margin-bottom: 2rem; line-height: 1.6; }

        /* ── Alert ── */
        .alert { padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem; font-weight: 600; display: none; }
        .alert.success { background: rgba(61,214,140,.08); border: 1px solid var(--accent); color: #6ee7b7; }
        .alert.error   { background: rgba(239,68,68,.08);  border: 1px solid var(--danger); color: #fca5a5; }

        /* ── Cards ── */
        .card {
            background: rgba(17,23,32,.85); border: 1px solid var(--border2);
            border-radius: 16px; padding: 1.75rem 2rem; backdrop-filter: blur(8px);
            margin-bottom: 1.25rem;
        }
        .card-title {
            font-size: .72rem; font-weight: 700; letter-spacing: .15em; text-transform: uppercase;
            color: var(--accent); margin-bottom: 1.25rem; padding-bottom: .75rem;
            border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px;
        }

        /* ── Form elements ── */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-group { margin-bottom: 1rem; position: relative; }
        .form-label { display: block; font-size: .8rem; font-weight: 600; color: #cbd5e1; margin-bottom: .4rem; }
        .form-label span { color: var(--danger); }
        .form-control {
            width: 100%; padding: .75rem 1rem;
            background: rgba(7,9,15,.8); border: 1px solid var(--border);
            border-radius: 8px; color: #fff; font-size: .95rem; font-family: 'DM Sans', sans-serif;
            transition: border-color .2s, box-shadow .2s; outline: none;
        }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(61,214,140,.1); }
        .form-control.valid   { border-color: var(--accent); }
        .form-control.invalid { border-color: var(--danger); }
        .form-hint { font-size: .73rem; color: var(--muted); margin-top: .35rem; line-height: 1.5; }

        /* ── Autocomplete dropdown ── */
        .ac-dropdown {
            position: absolute; top: 100%; left: 0; width: 100%;
            background: #07090f; border: 1px solid var(--accent); border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.6); z-index: 1000;
            display: none; overflow: hidden; margin-top: 4px;
        }
        .ac-item { padding: 10px 14px; cursor: pointer; border-bottom: 1px solid var(--border); transition: background .15s; }
        .ac-item:last-child { border-bottom: none; }
        .ac-item:hover { background: rgba(61,214,140,.08); }
        .ac-name  { font-weight: 700; color: #fff; font-size: .9rem; }
        .ac-email { font-size: .78rem; color: var(--muted); margin-top: 1px; }
        .ac-empty { padding: 12px 14px; font-size: .82rem; color: var(--muted); font-style: italic; }

        /* ── DB name field ── */
        .db-name-input { font-family: 'DM Mono', monospace !important; font-size: .9rem !important; letter-spacing: .04em; }
        .db-status { margin-top: .4rem; font-size: .78rem; font-weight: 600; display: flex; align-items: center; gap: 6px; min-height: 18px; }
        .db-status.ok       { color: var(--accent); }
        .db-status.error    { color: var(--danger); }
        .db-status.checking { color: var(--muted); }
        .db-preview {
            display: none; margin-top: .5rem;
            font-family: 'DM Mono', monospace; font-size: .82rem;
            background: rgba(7,9,15,.9); border: 1px dashed #334155;
            color: var(--accent); padding: 5px 12px; border-radius: 7px;
        }

        /* ── Provision button ── */
        .btn-provision {
            width: 100%; padding: 1rem;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border: none; border-radius: 12px; color: #07090f;
            font-weight: 800; font-size: 1rem; font-family: 'DM Sans', sans-serif;
            cursor: pointer; transition: all .2s; margin-top: .5rem;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-provision:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(61,214,140,.3); }
        .btn-provision:disabled { opacity: .45; cursor: not-allowed; transform: none; box-shadow: none; }

        /* ── Success box ── */
        .success-box {
            display: none;
            background: rgba(61,214,140,.06);
            border: 1px solid var(--accent);
            border-radius: 16px;
            padding: 2rem;
            margin-top: 1.5rem;
            text-align: center;
        }
        .success-box h3 { color: var(--accent); font-size: 1.3rem; margin-bottom: .5rem; }
        .success-box p  { color: var(--text); font-size: .9rem; margin-bottom: 1.25rem; }

        /* Farm Key display — the key employees need to register */
        .farm-key-display {
            display: inline-flex; align-items: center; gap: 10px;
            background: rgba(7,9,15,.9); border: 1px solid var(--border2);
            border-radius: 10px; padding: .75rem 1.25rem; margin: .75rem 0;
        }
        .farm-key-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; color: var(--muted); display: block; margin-bottom: 3px; }
        .farm-key-value { font-family: 'DM Mono', monospace; font-size: 1.15rem; color: #fff; letter-spacing: .06em; }
        .btn-copy {
            padding: 6px 14px; background: rgba(61,214,140,.1); border: 1px solid rgba(61,214,140,.3);
            color: var(--accent); border-radius: 7px; font-size: .78rem; font-weight: 700;
            cursor: pointer; transition: all .2s; white-space: nowrap; font-family: 'DM Sans', sans-serif;
        }
        .btn-copy:hover { background: rgba(61,214,140,.2); }
        .btn-copy.copied { background: rgba(61,214,140,.25); color: #fff; border-color: var(--accent); }

        .key-note {
            font-size: .78rem; color: var(--muted); background: rgba(244,197,66,.06);
            border: 1px solid rgba(244,197,66,.2); border-radius: 8px;
            padding: .65rem 1rem; margin-top: .75rem; line-height: 1.5; text-align: left;
        }
        .key-note strong { color: var(--gold); }

        .success-actions { display: flex; gap: 10px; justify-content: center; margin-top: 1.25rem; flex-wrap: wrap; }
        .btn-action {
            padding: .6rem 1.25rem; border-radius: 8px; font-size: .85rem; font-weight: 700;
            cursor: pointer; font-family: 'DM Sans', sans-serif; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px; transition: all .2s;
        }
        .btn-action.primary { background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #07090f; border: none; }
        .btn-action.primary:hover { transform: translateY(-1px); }
        .btn-action.secondary { background: transparent; color: var(--text); border: 1px solid var(--border2); }
        .btn-action.secondary:hover { border-color: var(--muted); }

        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; }
            .farm-key-display { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="farm_page.php" class="nav-brand">GATZ <span>SmartFarm</span></a>
    <a href="farm_page.php" class="nav-back">
        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Back to Dashboard
    </a>
    <div class="nav-user">
        <div class="nav-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
        <span style="color:var(--text);font-weight:600;"><?= htmlspecialchars($full_name) ?></span>
    </div>
</nav>

<div class="container">

    <div class="page-eyebrow">Farm Provisioning</div>
    <h1 class="page-title">🚜 Create New Client Farm</h1>
    <p class="page-sub">
        Provision an isolated farm database for an existing client. The owner must already be registered in the system.
        After creation, the <strong style="color:#fff;">Farm Key</strong> is shared with the owner so their employees can self-register.
    </p>

    <div id="alert" class="alert"></div>

    <form id="farmForm">

        <!-- Owner Section -->
        <div class="card">
            <div class="card-title">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Client / Owner Information
            </div>

            <p style="font-size:.8rem;color:var(--muted);margin-bottom:1.1rem;background:rgba(79,163,247,.06);border:1px solid rgba(79,163,247,.2);padding:.65rem 1rem;border-radius:8px;">
                💡 Search for an existing registered owner by typing their name, email, or phone number below.
                Only <strong style="color:#fff;">active owners and superadmins</strong> appear in results.
            </p>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Owner Full Name <span>*</span></label>
                    <input type="text" id="owner_name" class="form-control"
                        placeholder="Search by name…" required
                        autocomplete="off" oninput="triggerAutocomplete(this, 'ac-name')">
                    <div id="ac-name" class="ac-dropdown"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Owner Email <span>*</span></label>
                    <input type="email" id="owner_email" class="form-control"
                        placeholder="Search by email…" required
                        autocomplete="off" oninput="triggerAutocomplete(this, 'ac-email')">
                    <div id="ac-email" class="ac-dropdown"></div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Owner Phone</label>
                <input type="text" id="owner_phone" class="form-control"
                    placeholder="Search by phone…"
                    autocomplete="off" oninput="triggerAutocomplete(this, 'ac-phone')">
                <div id="ac-phone" class="ac-dropdown"></div>
            </div>
        </div>

        <!-- Farm Details Section -->
        <div class="card">
            <div class="card-title">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Farm Details
            </div>

            <div class="form-group">
                <label class="form-label">Farm Name <span>*</span></label>
                <input type="text" id="farm_name" class="form-control"
                    placeholder="e.g. Green Pastures Farm" required
                    oninput="autoFillDbName()">
            </div>

            <div class="form-group">
                <label class="form-label">Database Name (Schema) <span>*</span></label>
                <input type="text" id="db_name" class="form-control db-name-input"
                    placeholder="e.g. green_pastures_farm" required
                    autocomplete="off" spellcheck="false"
                    oninput="onDbNameInput()">
                <div class="db-status" id="db-status"></div>
                <div class="form-hint">
                    Lowercase letters, numbers, underscores only. Auto-filled from the farm name.
                    <strong style="color:#fff;">Cannot be changed after creation.</strong>
                </div>
                <div class="db-preview" id="db-preview"></div>
            </div>
        </div>

        <button type="submit" class="btn-provision" id="btnProvision" disabled>
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg>
            Provision Farm Database
        </button>
    </form>

    <!-- ── Success box — shown after provisioning ─────────────────────────── -->
    <div class="success-box" id="successBox">
        <h3>✅ Farm Database Created!</h3>
        <p>The farm database has been provisioned and the owner has been seeded as Farm Super Admin.<br>
        The owner can view their Farm Code anytime from the <strong style="color:#fff;">My Farms</strong> page.</p>

        <div class="success-actions">
            <a href="farm_page.php" class="btn-action primary">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Go to Dashboard
            </a>
            <button class="btn-action secondary" onclick="resetForm()">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Another Farm
            </button>
        </div>
    </div>

</div>

<script>
    // ── Autocomplete ─────────────────────────────────────────────────────────
    let acTimer = null;

    function triggerAutocomplete(inputEl, dropdownId) {
        clearTimeout(acTimer);
        const val = inputEl.value.trim();
        document.querySelectorAll('.ac-dropdown').forEach(d => d.style.display = 'none');

        if (val.length < 2) return;

        acTimer = setTimeout(async () => {
            try {
                const res  = await fetch(`?action=search_admin&term=${encodeURIComponent(val)}`);
                const data = await res.json();
                const dropdown = document.getElementById(dropdownId);

                if (data.length > 0) {
                    let html = '';
                    data.forEach(item => {
                        const safeName  = (item.full_name || '').replace(/'/g, "\\'");
                        const safeEmail = (item.email     || '').replace(/'/g, "\\'");
                        const safePhone = (item.phone_no  || '').replace(/'/g, "\\'");
                        const phoneDisp = item.phone_no ? ` · ${item.phone_no}` : '';
                        html += `
                            <div class="ac-item" onclick="fillAdmin('${safeName}','${safeEmail}','${safePhone}')">
                                <div class="ac-name">${item.full_name}</div>
                                <div class="ac-email">${item.email}${phoneDisp}</div>
                            </div>`;
                    });
                    dropdown.innerHTML = html;
                    dropdown.style.display = 'block';
                } else {
                    dropdown.innerHTML = '<div class="ac-empty">No matching registered owners found.</div>';
                    dropdown.style.display = 'block';
                }
            } catch (e) { /* silently ignore */ }
        }, 300);
    }

    function fillAdmin(name, email, phone) {
        document.getElementById('owner_name').value  = name;
        document.getElementById('owner_email').value = email;
        document.getElementById('owner_phone').value = phone;
        document.querySelectorAll('.ac-dropdown').forEach(d => d.style.display = 'none');
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.ac-dropdown') && !e.target.closest('.form-control')) {
            document.querySelectorAll('.ac-dropdown').forEach(d => d.style.display = 'none');
        }
    });

    // ── Auto-fill DB name from Farm name ─────────────────────────────────────
    function autoFillDbName() {
        const farmName  = document.getElementById('farm_name').value;
        const suggested = farmName
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
        document.getElementById('db_name').value = suggested;
        onDbNameInput();
    }

    // ── DB name live validation ───────────────────────────────────────────────
    let dbNameValid = false;
    let dbCheckTimer = null;

    function onDbNameInput() {
        const input   = document.getElementById('db_name');
        const status  = document.getElementById('db-status');
        const preview = document.getElementById('db-preview');
        const btn     = document.getElementById('btnProvision');
        const raw     = input.value.trim();

        dbNameValid      = false;
        btn.disabled     = true;
        status.className = 'db-status';
        status.textContent = '';
        preview.style.display = 'none';
        document.getElementById('successBox').style.display = 'none';

        if (!raw) return;

        if (!/^[a-z0-9_]+$/.test(raw)) {
            input.classList.add('invalid');
            status.className   = 'db-status error';
            status.textContent = '❌ Only lowercase letters (a-z), numbers, and underscores allowed.';
            return;
        }

        if (raw.length < 3) {
            input.classList.add('invalid');
            status.className   = 'db-status error';
            status.textContent = '❌ Must be at least 3 characters.';
            return;
        }

        input.classList.remove('valid', 'invalid');
        preview.textContent   = raw;
        preview.style.display = 'block';

        clearTimeout(dbCheckTimer);
        status.className   = 'db-status checking';
        status.textContent = '⏳ Checking availability…';

        dbCheckTimer = setTimeout(() => checkDbAvailability(raw), 500);
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
                status.className   = 'db-status ok';
                status.textContent = '✅ Available — this database name is not taken.';
                dbNameValid        = true;
                btn.disabled       = false;
            } else {
                input.classList.remove('valid'); input.classList.add('invalid');
                status.className   = 'db-status error';
                status.textContent = '❌ Already taken — choose a different name.';
                dbNameValid        = false;
                btn.disabled       = true;
            }
        } catch (e) {
            status.className   = 'db-status error';
            status.textContent = '⚠️ Could not verify. Proceed with caution.';
            dbNameValid        = true;
            btn.disabled       = false;
        }
    }

    // ── Form submission ───────────────────────────────────────────────────────
    function showAlert(type, msg) {
        const el         = document.getElementById('alert');
        el.className     = 'alert ' + type;
        el.textContent   = msg;
        el.style.display = 'block';
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    document.getElementById('farmForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        document.getElementById('successBox').style.display = 'none';

        if (!dbNameValid) {
            showAlert('error', '❌ Please enter a valid and available database name.');
            return;
        }

        const ownerEmail = document.getElementById('owner_email').value.trim();
        const ownerName  = document.getElementById('owner_name').value.trim();
        if (!ownerEmail || !ownerName) {
            showAlert('error', '❌ Owner name and email are required. Use the search to find a registered owner.');
            return;
        }

        const btn = document.getElementById('btnProvision');
        btn.disabled    = true;
        btn.innerHTML   = '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="animation:spin .7s linear infinite"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Provisioning…';

        const payload = {
            farm_name   : document.getElementById('farm_name').value.trim(),
            db_name     : document.getElementById('db_name').value.trim(),
            owner_name  : ownerName,
            owner_email : ownerEmail,
            owner_phone : document.getElementById('owner_phone').value.trim(),
        };

        try {
            const res = await fetch('saveClientFarm.php', {
                method : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body   : JSON.stringify(payload),
            });
            const raw = await res.text();
            let data;
            try {
                data = JSON.parse(raw);
            } catch (err) {
                console.error('Non-JSON response:', raw);
                showAlert('error', '❌ Server error — check the browser console.');
                btn.disabled  = false;
                btn.innerHTML = 'Provision Farm Database';
                return;
            }

            if (data.success) {
                document.getElementById('successBox').style.display = 'block';
                document.getElementById('successBox').scrollIntoView({ behavior: 'smooth' });
                document.getElementById('alert').style.display = 'none';
                btn.innerHTML = 'Provision Farm Database';

            } else {
                showAlert('error', '❌ ' + data.message);
                btn.disabled  = false;
                btn.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg> Provision Farm Database';
            }
        } catch (err) {
            console.error(err);
            showAlert('error', '❌ System error — please try again.');
            btn.disabled  = false;
            btn.innerHTML = 'Provision Farm Database';
        }
    });


function resetForm() {
        document.getElementById('farmForm').reset();
        document.getElementById('successBox').style.display  = 'none';
        document.getElementById('alert').style.display       = 'none';
        document.getElementById('db-status').textContent     = '';
        document.getElementById('db-status').className       = 'db-status';
        document.getElementById('db-preview').style.display  = 'none';
        document.getElementById('db_name').classList.remove('valid', 'invalid');
        document.getElementById('btnProvision').disabled     = true;
        document.getElementById('btnProvision').innerHTML    =
            '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg> Provision Farm Database';
        dbNameValid = false;
    }
</script>
<style>
    @keyframes spin { to { transform: rotate(360deg); } }
</style>
</body>
</html>