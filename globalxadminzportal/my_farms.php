<?php
// globalxadminportal/my_farms.php
session_start();

if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
if ($_SESSION['is_incharge'] == 1) { header('Location: farm_page.php'); exit; }

require_once '../config/SadminConnection.php';

$admin_id  = $_SESSION['admin'];
$full_name = $_SESSION['full_name'];

// Fetch the current owner's details to get their farm code
$stmtMe = $conn->prepare("SELECT farm_code, is_owner FROM admin_users WHERE admin_id = ?");
$stmtMe->execute([$admin_id]);
$me = $stmtMe->fetch(PDO::FETCH_ASSOC);
$my_farm_code = $me['farm_code'] ?? '';
$is_owner = $me['is_owner'] ?? 0;

// ========================================================================
// INTERNAL AJAX HANDLERS FOR STRICT SEARCH & ASSIGNMENT
// ========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'search_user') {
    @ob_end_clean();
    header('Content-Type: application/json');
    $term = '%' . trim($_GET['term']) . '%';
    try {
        if (empty($my_farm_code)) {
            echo json_encode([]); exit; // Cannot search if they don't have an organization code
        }

        // STRICT SEARCH: Must match the owner's farm_code, must be active, must NOT be the owner themselves
        $stmt = $conn->prepare("
            SELECT admin_id, full_name, email 
            FROM admin_users 
            WHERE (full_name LIKE ? OR email LIKE ?) 
              AND status = 1 
              AND farm_code = ? 
              AND admin_id != ?
            LIMIT 5
        ");
        $stmt->execute([$term, $term, $my_farm_code, $admin_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e) { echo json_encode([]); }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_user') {
    @ob_end_clean();
    header('Content-Type: application/json');
    try {
        $target_admin_id  = (int)$_POST['target_admin_id'];
        $target_farm_id   = (int)$_POST['farm_id'];

        if (!$target_admin_id || !$target_farm_id) {
            throw new Exception("Please select a valid user and farm.");
        }

        // Security Check: Verify the CURRENT user actually has access to the farm
        $checkAccess = $conn->prepare("SELECT 1 FROM assigned_farms WHERE admin_id = ? AND farm_id = ?");
        $checkAccess->execute([$admin_id, $target_farm_id]);
        if (!$checkAccess->fetch()) {
            throw new Exception("Unauthorized: You do not have permission to manage this farm.");
        }

        // Insert assignment
        $stmt = $conn->prepare("INSERT IGNORE INTO assigned_farms (admin_id, farm_id) VALUES (?, ?)");
        $stmt->execute([$target_admin_id, $target_farm_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'User successfully granted access to the farm!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'This user already has access to this farm.']);
        }
    } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
    exit;
}
// ========================================================================

// Fetch only the farms assigned to this specific user
$stmt = $conn->prepare("
    SELECT f.farm_id, f.farm_name, f.farm_status, f.created_at, af.assigned_at
    FROM assigned_farms af
    JOIN farms f ON f.farm_id = af.farm_id
    WHERE af.admin_id = ? AND f.farm_status != -1
    ORDER BY f.farm_name ASC
");
$stmt->execute([$admin_id]);
$my_farms = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Farms | FarmPro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:       #07090f; --surface:  #0d1117; --card:     #111720;
            --border:   #1c2535; --border2:  #243045; --text:     #c8d8ec;
            --muted:    #455870; --accent:   #3dd68c; --accent2:  #07955a;
            --nav-h:    64px; --blue:     #3b82f6;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

        .navbar { position: sticky; top: 0; z-index: 100; height: var(--nav-h); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; background: rgba(7,9,15,.85); backdrop-filter: blur(16px); border-bottom: 1px solid var(--border); }
        .nav-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .nav-logo { font-family: 'Bebas Neue', sans-serif; font-size: 1.55rem; color: var(--accent); line-height: 1; }
        .nav-links { display: flex; align-items: center; gap: 15px; }
        .nav-logout { color: #f87171; text-decoration: none; font-size: .85rem; font-weight: bold; transition: 0.2s;}
        .nav-logout:hover { color: #fca5a5; }
        .nav-user { display: flex; align-items: center; gap: 9px; padding: .35rem .75rem .35rem .45rem; border-radius: 100px; background: rgba(255,255,255,.03); border: 1px solid var(--border2); }
        .nav-avatar { width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent2)); display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; color: #051a0e; }
        .nav-username { font-size: .8rem; font-weight: 600; color: var(--text); }

        .page-wrap { padding: 3rem 2rem; max-width: 1200px; margin: 0 auto; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 15px;}
        .page-title { font-family: 'Bebas Neue', sans-serif; font-size: 2.6rem; color: #fff; margin-bottom: 0.2rem; }
        .page-sub { color: var(--muted); }

        /* The Invite Code Box */
        .invite-box { background: rgba(59,130,246,0.05); border: 1px solid rgba(59,130,246,0.3); padding: 1.25rem 1.5rem; border-radius: 12px; margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;}
        .invite-label { font-size: 0.8rem; color: var(--blue); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px; }
        .invite-code { font-family: 'DM Mono', monospace; font-size: 1.8rem; color: #fff; letter-spacing: 2px; font-weight: bold; }
        .invite-desc { font-size: 0.85rem; color: var(--muted); max-width: 400px; line-height: 1.4;}

        .btn-assign { background: linear-gradient(135deg, var(--blue), #2563eb); border: none; color: #fff; padding: 10px 20px; border-radius: 8px; font-weight: bold; font-family: 'DM Sans', sans-serif; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-assign:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(59,130,246,0.3); }

        .farms-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .farm-card { background: var(--card); border: 1px solid var(--border2); border-radius: 16px; padding: 2rem; transition: 0.3s; text-decoration: none; display: block; position: relative; overflow: hidden; }
        .farm-card:hover { transform: translateY(-5px); border-color: var(--accent); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .farm-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--accent), var(--accent2)); opacity: 0; transition: 0.3s; }
        .farm-card:hover::before { opacity: 1; }
        .farm-icon { font-size: 2.5rem; margin-bottom: 1rem; display: block; }
        .farm-name { font-size: 1.3rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem; }
        
        .badge { display: inline-flex; padding: 4px 10px; border-radius: 100px; font-size: .7rem; font-weight: 700; text-transform: uppercase; margin-bottom: 1rem;}
        .badge-active   { background: rgba(61,214,140,.1);  color: var(--accent); border: 1px solid rgba(61,214,140,.2);  }
        .badge-inactive { background: rgba(69,88,112,.12);   color: var(--muted);  border: 1px solid rgba(69,88,112,.2);  }

        .farm-meta { font-size: 0.8rem; color: var(--muted); display: flex; justify-content: space-between; border-top: 1px solid var(--border); padding-top: 1rem; margin-top: 1rem;}
        .btn-launch { display: inline-block; width: 100%; text-align: center; padding: 12px; background: rgba(255,255,255,0.05); color: #fff; border-radius: 8px; font-weight: bold; transition: 0.2s;}
        .farm-card:hover .btn-launch { background: var(--accent); color: #000; }
        .empty-state { text-align: center; padding: 4rem; background: var(--card); border: 1px dashed var(--border2); border-radius: 16px; color: var(--muted); }

        /* MODAL */
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center; padding:1rem; }
        .modal.show { display:flex; }
        .modal-content { background:var(--card); border-radius:16px; width:100%; max-width:450px; border:1px solid var(--border2); box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); }
        .modal-header { padding:1.5rem; border-bottom:1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { margin:0; font-size:1.2rem; color:#fff; }
        .modal-close { background: none; border: none; color: var(--muted); cursor: pointer; font-size: 1.2rem; transition: 0.2s;}
        .modal-close:hover { color: white; }
        .modal-body { padding:1.5rem; position: relative; }
        .form-group { margin-bottom: 1.2rem; position: relative;}
        .form-label { display: block; font-size: .8rem; color: var(--muted); margin-bottom: .4rem; font-weight: 600;}
        .form-control { width: 100%; padding: 12px; background: #0f172a; border: 1px solid var(--border); border-radius: 8px; color: #fff; font-size: .95rem; outline: none; transition: 0.2s;}
        .form-control:focus { border-color: var(--blue); }

        .ac-dropdown { position: absolute; top: 100%; left: 0; width: 100%; background: #0f172a; border: 1px solid var(--blue); border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 1000; display: none; overflow: hidden; margin-top: 4px; max-height: 200px; overflow-y: auto;}
        .ac-item { padding: 10px 15px; cursor: pointer; border-bottom: 1px solid var(--border); transition: 0.2s; }
        .ac-item:hover { background: rgba(59, 130, 246, 0.1); }
        .ac-name { font-weight: bold; color: #fff; font-size: 0.9rem; }
        .ac-email { font-size: 0.75rem; color: var(--muted); }
        .btn-submit { width: 100%; padding: 12px; background: linear-gradient(135deg, var(--blue), #2563eb); border: none; color: white; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.2s; font-size: 1rem; margin-top: 10px;}
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(59,130,246,0.3); }

        @media (max-width: 640px) { .nav-username { display: none; } .page-wrap { padding: 2rem 1rem; } }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">
        <div class="nav-logo">Farm<span>Pro</span></div>
    </div>
    <div class="nav-links">
        <div class="nav-user">
            <div class="nav-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
            <span class="nav-username"><?= htmlspecialchars($full_name) ?></span>
        </div>
        <a href="logout.php" class="nav-logout">Logout</a>
    </div>
</nav>

<main class="page-wrap">

    <div class="page-header">
        <div>
            <h1 class="page-title">My Farms</h1>
            <p class="page-sub">Select a farm below to access its operational dashboard.</p>
        </div>
        <?php if (!empty($my_farms) && $is_owner == 1): ?>
        <button class="btn-assign" onclick="openAssignModal()">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
            Assign User to Farm
        </button>
        <?php endif; ?>
    </div>

    <?php if ($is_owner == 1 && !empty($my_farm_code)): ?>
        <div class="invite-box">
            <div>
                <div class="invite-label">Your Organization Farm Code</div>
                <div class="invite-code"><?= htmlspecialchars($my_farm_code) ?></div>
            </div>
            <div class="invite-desc">
                Give this code to your farm managers or employees. When they register an account, they must enter this code to be eligible for assignment to your farms.
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($my_farms)): ?>
        <div class="empty-state">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🌾</div>
            <h3>No Farms Assigned</h3>
            <p>You have not been assigned to any farms yet. Please contact your system administrator.</p>
        </div>
    <?php else: ?>
        <div class="farms-grid">
            <?php foreach($my_farms as $farm): ?>
                <a href="#" class="farm-card"> <span class="farm-icon">🚜</span>
                    <div class="farm-name"><?= htmlspecialchars($farm['farm_name']) ?></div>
                    
                    <?php if ($farm['farm_status'] == 1): ?>
                        <span class="badge badge-active">Operational</span>
                    <?php else: ?>
                        <span class="badge badge-inactive">Suspended</span>
                    <?php endif; ?>

                    <div class="btn-launch">Launch Dashboard →</div>
                    
                    <div class="farm-meta">
                        <span>ID: #<?= $farm['farm_id'] ?></span>
                        <span>Assigned: <?= date('M Y', strtotime($farm['assigned_at'])) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

<div id="assignModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Assign Employee</h2>
            <button class="modal-close" onclick="closeAssignModal()">✕</button>
        </div>
        <div class="modal-body">
            <form id="assignForm" onsubmit="submitAssignment(event)">
                <input type="hidden" name="action" value="assign_user">
                <input type="hidden" id="target_admin_id" name="target_admin_id" required>
                
                <div class="form-group">
                    <label class="form-label">Select Your Farm</label>
                    <select name="farm_id" class="form-control" required>
                        <option value="" disabled selected>-- Choose a Farm --</option>
                        <?php foreach($my_farms as $f): ?>
                            <option value="<?= $f['farm_id'] ?>"><?= htmlspecialchars($f['farm_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Search Approved Employees</label>
                    <input type="text" id="search_input" class="form-control" placeholder="Type name or email..." autocomplete="off" oninput="searchUser(this)">
                    <div id="ac_dropdown" class="ac-dropdown"></div>
                    <div style="font-size: 0.75rem; color: var(--muted); margin-top: 5px;">
                        Only showing active users who registered with your Farm Code (<strong><?= htmlspecialchars($my_farm_code) ?></strong>).
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="btnAssign">Grant Access</button>
            </form>
        </div>
    </div>
</div>

<script>
    let searchTimer = null;

    function searchUser(inputEl) {
        clearTimeout(searchTimer);
        const val = inputEl.value.trim();
        const dropdown = document.getElementById('ac_dropdown');
        
        document.getElementById('target_admin_id').value = '';
        dropdown.style.display = 'none';
        
        if (val.length < 2) return;

        searchTimer = setTimeout(async () => {
            try {
                const res = await fetch(`?action=search_user&term=${encodeURIComponent(val)}`);
                const data = await res.json();
                
                if (data.length > 0) {
                    let html = '';
                    data.forEach(item => {
                        const safeName = item.full_name.replace(/'/g, "\\'");
                        const safeEmail = item.email.replace(/'/g, "\\'");
                        html += `
                        <div class="ac-item" onclick="selectUser(${item.admin_id}, '${safeName}', '${safeEmail}')">
                            <div class="ac-name">${item.full_name}</div>
                            <div class="ac-email">${item.email}</div>
                        </div>`;
                    });
                    dropdown.innerHTML = html;
                    dropdown.style.display = 'block';
                } else {
                    dropdown.innerHTML = '<div class="ac-item"><div class="ac-email">No matching employees found under your Farm Code.</div></div>';
                    dropdown.style.display = 'block';
                }
            } catch(e) {}
        }, 300);
    }

    function selectUser(id, name, email) {
        document.getElementById('target_admin_id').value = id;
        document.getElementById('search_input').value = `${name} (${email})`;
        document.getElementById('ac_dropdown').style.display = 'none';
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.ac-dropdown') && !e.target.closest('#search_input')) {
            document.getElementById('ac_dropdown').style.display = 'none';
        }
    });

    function openAssignModal() {
        document.getElementById('assignForm').reset();
        document.getElementById('target_admin_id').value = '';
        document.getElementById('assignModal').classList.add('show');
    }

    function closeAssignModal() {
        document.getElementById('assignModal').classList.remove('show');
    }

    function submitAssignment(e) {
        e.preventDefault();
        
        if(!document.getElementById('target_admin_id').value) {
            alert("Please select a user from the search dropdown.");
            return;
        }

        const form = document.getElementById('assignForm');
        const fd = new FormData(form);
        const btn = document.getElementById('btnAssign');
        
        btn.disabled = true; btn.textContent = 'Assigning...';

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    alert(data.message);
                    closeAssignModal();
                } else { alert("Error: " + data.message); }
                btn.disabled = false; btn.textContent = 'Grant Access';
            }).catch(err => {
                alert("System Error Occurred.");
                btn.disabled = false; btn.textContent = 'Grant Access';
            });
    }
</script>

</body>
</html>