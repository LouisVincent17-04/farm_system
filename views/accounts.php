<?php
// views/accounts.php
$page = "settings";
include '../config/Connection.php';
include '../security/checkAccess.php';
// 1. Security Check
checkAccess('manage_accounts');

include '../common/navbar.php';
include '../common/chat_support.php';

// Filter Logic
$filter_status = isset($_GET['status']) && $_GET['status'] == 'inactive' ? 0 : 1;

try {
    if (!isset($conn)) throw new Exception("Database connection failed.");

    // Updated DATE_FORMAT to mm/dd/yyyy
    $sql = "SELECT USER_ID, FULL_NAME, EMAIL, USER_TYPE, IS_ACTIVE, 
            DATE_FORMAT(CREATED_AT, '%m/%d/%Y') AS JOIN_DATE
            FROM USERS 
            WHERE IS_ACTIVE = :status 
            ORDER BY USER_ID DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':status' => $filter_status]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    
} catch (Exception $e) {
    $users = [];
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"> 
    <title>Account Management | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(168,85,247,0.5); /* Purple Accent */
            --purple:         #a855f7;
            --purple-dim:     rgba(168,85,247,0.12);
            --purple-glow:    rgba(168,85,247,0.25);
            --blue:           #3b82f6;
            --emerald:        #10b981;
            --red:            #ef4444;
            --amber:          #f59e0b;
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.18s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(168,85,247,0.05) 0%, transparent 60%);
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500;
            padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); transition: all var(--transition);
        }
        .back-link:hover { color: var(--text-primary); border-color: var(--border-active); background: var(--bg-hover); }

        .page-badge {
            display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem;
            font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--purple); background: var(--purple-dim); border: 1px solid rgba(168,85,247,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { margin-bottom: 2.5rem; }
        .page-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--purple), #7e22ce);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 0.95rem; }

        /* ─── FILTER TABS & SEARCH ─── */
        .controls-wrapper {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap;
        }

        .filter-tabs {
            display: flex; gap: 8px; background: var(--bg-surface); padding: 6px;
            border-radius: var(--radius-lg); border: 1px solid var(--border);
        }
        .filter-link {
            color: var(--text-secondary); text-decoration: none; font-weight: 600; font-size: 0.85rem;
            padding: 8px 16px; border-radius: 8px; transition: all var(--transition);
        }
        .filter-link:hover { color: var(--text-primary); background: rgba(255,255,255,0.05); }
        .filter-link.active { color: #fff; background: var(--purple); box-shadow: 0 4px 12px var(--purple-glow); pointer-events: none; }

        .search-container { position: relative; flex: 1; max-width: 350px; min-width: 250px; }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; height: 18px; pointer-events: none; }
        .search-input {
            width: 100%; padding: 12px 16px 12px 2.8rem; background: var(--bg-surface);
            border: 1px solid var(--border); border-radius: var(--radius-lg); color: var(--text-primary);
            font-size: 0.95rem; font-family: var(--font); outline: none; transition: all var(--transition);
        }
        .search-input:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow); background: var(--bg-elevated); }
        .search-input::placeholder { color: var(--text-muted); }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
        }
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .table thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 14px 16px; text-align: left;
            border-bottom: 1px solid var(--border); white-space: nowrap;
        }
        .table tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        .table tbody tr:last-child { border-bottom: none; }
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }
        .table td { padding: 14px 16px; font-size: 0.9rem; color: var(--text-primary); vertical-align: middle; }

        .account-info { display: flex; align-items: center; gap: 12px; }
        .account-avatar {
            width: 2.8rem; height: 2.8rem; background: linear-gradient(135deg, var(--purple), #6d28d9);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: white; flex-shrink: 0; font-size: 1rem; border: 2px solid rgba(255,255,255,0.1);
        }
        .user-name { font-weight: 600; color: #fff; font-size: 1rem; }
        .user-email { color: var(--text-secondary); font-family: var(--font-mono); font-size: 0.85rem; }
        .val-mono { font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-muted); }

        /* Role Badges */
        .role-badge { padding: 4px 12px; border-radius: 99px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; white-space: nowrap; }
        .role-badge.user { background: rgba(148, 163, 184, 0.15); color: var(--text-secondary); border: 1px solid rgba(148, 163, 184, 0.3); }
        .role-badge.farm_employee { background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.3); }
        .role-badge.admin { background: var(--blue-dim); color: var(--blue); border: 1px solid rgba(59,130,246,0.3); }
        .role-badge.superadmin { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(168,85,247,0.3); }

        .status-dot { height: 8px; width: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .status-dot.active { background-color: var(--emerald); box-shadow: 0 0 8px var(--emerald); }
        .status-dot.inactive { background-color: var(--red); box-shadow: 0 0 8px var(--red); }
        .status-text { font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); }

        /* Actions */
        .actions { display: flex; gap: 8px; justify-content: flex-end; }
        .action-btn {
            width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border);
            background: var(--bg-elevated); display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all var(--transition); color: var(--text-secondary); text-decoration: none;
        }
        .action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .action-btn.edit:hover { color: var(--blue); border-color: var(--blue); }
        .action-btn.permissions:hover { color: var(--amber); border-color: var(--amber); }
        .action-btn.delete:hover { color: var(--red); border-color: var(--red); }
        .action-btn.reactivate:hover { color: var(--emerald); border-color: var(--emerald); }

        /* ─── MODALS ─── */
        .modal {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85);
            backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;
            padding: 1rem;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); overflow: hidden;
            animation: modalZoom 0.2s ease-out;
        }
        @keyframes modalZoom { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--purple); }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); }

        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.25rem; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        .form-control {
            width: 100%; padding: 12px; background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: 8px; font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .form-control:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow); }

        .btn-modal {
            padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; font-family: var(--font); border: none; font-size: 0.9rem;
        }
        .btn-save { background: var(--purple); color: #fff; }
        .btn-save:hover:not(:disabled) { background: #c084fc; box-shadow: 0 4px 12px var(--purple-glow); transform: translateY(-1px); }
        .btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-cancel { background: transparent; color: var(--text-secondary); border: 1px solid var(--border); }
        .btn-cancel:hover { background: var(--bg-hover); color: var(--text-primary); border-color: var(--text-muted); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.2; display: block; }
        .empty-state h3 { font-size: 1.1rem; color: var(--text-primary); margin: 0 0 0.5rem 0;}

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .controls-wrapper { flex-direction: column; align-items: stretch; }
            .search-container { max-width: none; }
            .filter-tabs { flex-wrap: wrap; justify-content: center; }
            .filter-link { flex: 1; text-align: center; }

            .table-wrap { border: none; background: transparent; overflow-x: visible; box-shadow: none; }
            .table { min-width: 100%; } /* CRITICAL FIX */
            .table, .table thead, .table tbody, .table th, .table td, .table tr { display: block; width: 100%; box-sizing: border-box; }
            .table thead { display: none; }
            .table tbody tr { 
                background: var(--bg-surface); border: 1px solid var(--border); 
                border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem;
            }
            .table td { 
                display: flex; justify-content: space-between; align-items: center; 
                padding: 0.6rem 0; border-bottom: 1px dashed rgba(255,255,255,0.05); text-align: right;
            }
            .table td:last-child { border-bottom: none; justify-content: flex-end; padding-top: 1rem; gap: 10px; }
            .table td::before { 
                content: attr(data-label); font-weight: 700; color: var(--text-muted); 
                font-size: 0.75rem; text-transform: uppercase; text-align: left;
            }
            .table td[data-label="User"] { display: block; text-align: left; padding-bottom: 1rem; margin-bottom: 0.5rem; }
            .table td[data-label="User"]::before { display: none; }
            .table td[data-label="Actions"] { border-top: 1px dashed var(--border); margin-top: 10px;}
            .table td[data-label="Actions"]::before { display: none; }
            
            .modal-content { padding: 1rem; }
            .modal-footer { flex-direction: column-reverse; }
            .btn-modal { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <div class="top-bar">
        <a href="settings.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Settings
        </a>
        <span class="page-badge"><i class="fa-solid fa-users-gear"></i> Administration</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1>Account <span>Management</span></h1>
            <p>Control user profiles, define system roles, and configure access permissions.</p>
        </div>
    </div>

    <div class="controls-wrapper">
        <div class="filter-tabs">
            <a href="?status=active" class="filter-link <?php echo $filter_status == 1 ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-check me-1"></i> Active Users
            </a>
            <a href="?status=inactive" class="filter-link <?php echo $filter_status == 0 ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-xmark me-1"></i> Suspended
            </a>
        </div>

        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" class="search-input" id="searchInput" placeholder="Search by name, email, or role..." onkeyup="filterTable()">
        </div>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Identity</th>
                        <th>Email Directory</th>
                        <th>System Role</th>
                        <th>Account Status</th>
                        <th>Date Onboarded</th>
                        <th style="text-align: right; width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="account-table">
                    <?php if (empty($users)): ?>
                        <tr id="empty-db-row"><td colspan="6" style="padding:0;">
                            <div class="empty-state">
                                <i class="fa-solid fa-users-slash"></i>
                                <h3>No accounts found</h3>
                                <p>There are no users matching this status filter.</p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): 
                            $roleClass = 'user'; $roleText = 'New User';
                            if ($user['USER_TYPE'] == 2) { $roleClass = 'farm_employee'; $roleText = 'Farm Employee'; }
                            if ($user['USER_TYPE'] == 3) { $roleClass = 'admin'; $roleText = 'Admin'; }
                            if ($user['USER_TYPE'] == 4) { $roleClass = 'superadmin'; $roleText = 'Super Admin'; }
                            
                            $initials = strtoupper(substr($user['FULL_NAME'], 0, 1));
                            $isActive = $user['IS_ACTIVE'] == 1;
                        ?>
                        <tr class="user-row" data-user-id="<?php echo $user['USER_ID']; ?>" 
                            data-name="<?php echo htmlspecialchars($user['FULL_NAME']); ?>"
                            data-email="<?php echo htmlspecialchars($user['EMAIL']); ?>"
                            data-role="<?php echo $user['USER_TYPE']; ?>">
                            
                            <td data-label="User">
                                <div class="account-info">
                                    <div class="account-avatar"><?php echo $initials; ?></div>
                                    <div class="user-name"><?php echo htmlspecialchars($user['FULL_NAME']); ?></div>
                                </div>
                            </td>
                            <td data-label="Email" class="user-email"><?php echo htmlspecialchars($user['EMAIL']); ?></td>
                            <td data-label="Role"><span class="role-badge <?php echo $roleClass; ?>"><?php echo $roleText; ?></span></td>
                            <td data-label="Status">
                                <span class="status-dot <?php echo $isActive ? 'active' : 'inactive'; ?>"></span>
                                <span class="status-text"><?php echo $isActive ? 'Active' : 'Suspended'; ?></span>
                            </td>
                            <td data-label="Joined" class="val-mono"><?php echo $user['JOIN_DATE']; ?></td>
                            <td data-label="Actions">
                                <div class="actions">
                                    <?php if ($isActive): ?>
                                        <button class="action-btn edit" onclick="editAccount(this)" title="Edit Profile">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        
                                        <a href="manage_access.php?user_id=<?php echo $user['USER_ID']; ?>" class="action-btn permissions" title="Configure Role & Permissions">
                                            <i class="fa-solid fa-sliders"></i>
                                        </a>

                                        <button class="action-btn delete" onclick="deleteAccount(this)" title="Suspend Account">
                                            <i class="fa-solid fa-ban"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="action-btn reactivate" onclick="reactivateAccount(this)" title="Restore Access">
                                            <i class="fa-solid fa-rotate-left"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div id="empty-state-js" class="empty-state" style="display:none;">
                <i class="fa-solid fa-magnifying-glass"></i>
                <h3>No matches found</h3>
                <p>Try adjusting your search terms.</p>
            </div>
        </div>
    </div>
</div>

<div id="modal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h2>Edit Profile</h2></div>
        <div class="modal-body">
            <form id="account-form">
                <input type="hidden" id="form_user_id" name="user_id">
                <div class="form-group">
                    <label class="form-label">Full Legal Name</label>
                    <input type="text" id="name" name="full_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address / Login</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-modal btn-cancel" onclick="closeModal()">Cancel</button>
            <button class="btn-modal btn-save" onclick="saveAccount()">Update Details</button>
        </div>
    </div>
</div>

<script>
    function showSuccessAndReload(message) {
        alert(message); 
        window.location.reload();
    }
    
    function filterTable() {
        const term = document.getElementById('searchInput').value.toLowerCase();
        const rows = document.querySelectorAll('.user-row');
        let visible = 0;
        
        rows.forEach(r => {
            const txt = r.innerText.toLowerCase();
            if(txt.includes(term)) {
                r.style.display = '';
                visible++;
            } else {
                r.style.display = 'none';
            }
        });
        
        const dbEmpty = document.getElementById('empty-db-row');
        if(dbEmpty && dbEmpty.style.display !== 'none') return; // If inherently empty, let it be
        
        document.getElementById('empty-state-js').style.display = (visible === 0 && rows.length > 0) ? 'block' : 'none';
    }

    // --- Edit Logic ---
    function editAccount(btn) {
        const d = btn.closest('tr').dataset;
        document.getElementById('form_user_id').value = d.userId;
        document.getElementById('name').value = d.name;
        document.getElementById('email').value = d.email;
        document.getElementById('modal').classList.add('show');
    }

    function closeModal() { document.getElementById('modal').classList.remove('show'); }

    function saveAccount() {
        const form = document.getElementById('account-form');
        if(!form.checkValidity()) { form.reportValidity(); return; }
        
        const saveBtn = document.querySelector('#modal .btn-save');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Saving...';

        fetch('../process/editUser.php', { method: 'POST', body: new FormData(form) })
        .then(r => r.json())
        .then(data => {
            if(data.success) { showSuccessAndReload(data.message || 'Profile updated successfully!'); }
            else { alert('Error: ' + data.message); saveBtn.disabled = false; saveBtn.textContent = 'Update Details'; }
        })
        .catch(error => { 
            console.error('Fetch error:', error); 
            alert('A network communication error occurred.'); 
            saveBtn.disabled = false; saveBtn.textContent = 'Update Details'; 
        });
    }

    // --- Delete Logic ---
    function deleteAccount(btn) {
        if(confirm("Suspend this user account? They will lose access to the system immediately.")) {
            const userId = btn.closest('tr').dataset.userId;
            const fd = new FormData();
            fd.append('user_id', userId);

            fetch('../process/deleteUser.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if(data.success) { showSuccessAndReload(data.message); } 
                else { alert('Action Denied: ' + data.message); }
            })
            .catch(err => { console.error(err); alert('Network error.'); });
        }
    }

    // --- Reactivate Logic ---
    function reactivateAccount(btn) {
        if(confirm("Restore access for this user account?")) {
            const userId = btn.closest('tr').dataset.userId;
            const fd = new FormData();
            fd.append('user_id', userId);

            fetch('../process/reactivateUser.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if(data.success) { showSuccessAndReload(data.message); } 
                else { alert('Action Denied: ' + data.message); }
            })
            .catch(err => { console.error(err); alert('Network error.'); });
        }
    }
    
    // Close modal on click outside
    window.onclick = function(e) {
        if(e.target.classList.contains('modal')) e.target.classList.remove('show');
    }
</script>
</body>
</html>