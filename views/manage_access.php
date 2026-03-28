<?php
// views/manage_access.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page = "settings";
include '../config/Connection.php';
include '../security/checkAccess.php';

checkAccess('manage_accounts');
include '../common/navbar.php';
include '../config/PageList.php';   // provides $permission_map and $restricted_for_basic_roles
include '../common/chat_support.php';

// 1. Fetch all active users (exclude Super Admins, USER_TYPE=4)
try {
    $users = $conn->query("
        SELECT USER_ID, FULL_NAME, USER_TYPE
        FROM   users
        WHERE  IS_ACTIVE = 1
        ORDER  BY USER_ID
    ")->fetchAll(PDO::FETCH_ASSOC);

    $locations = $conn->query("
        SELECT LOCATION_ID, LOCATION_NAME
        FROM   LOCATIONS
        ORDER  BY LOCATION_NAME ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error fetching data: " . $e->getMessage());
}

// 2. Handle user selection
$selected_user      = $_GET['user_id'] ?? 0;
$permissions        = [];
$current_role       = 1;
$current_location   = null;
$selected_user_name = '';

if ($selected_user) {
    try {
        $stmt = $conn->prepare("SELECT * FROM access_control WHERE user_id = ?");
        $stmt->execute([$selected_user]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) $permissions = $result;

        $roleStmt = $conn->prepare("SELECT FULL_NAME, USER_TYPE, LOCATION_ID FROM users WHERE USER_ID = ?");
        $roleStmt->execute([$selected_user]);
        $userRow = $roleStmt->fetch(PDO::FETCH_ASSOC);

        if ($userRow) {
            $selected_user_name = $userRow['FULL_NAME'];
            $current_role       = $userRow['USER_TYPE'];
            $current_location   = $userRow['LOCATION_ID'];
        }
    } catch (Exception $e) {
        echo "<div style='color:red;background:white;padding:10px;'>Error: " . $e->getMessage() . "</div>";
    }
}

// 3. Pass both restriction lists to JS
$restricted_json            = json_encode($restricted_for_basic_roles);
$restricted_super_json      = json_encode($restricted_for_non_superadmin);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manage Access | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            
            --rose:           #f43f5e;
            --emerald:        #10b981;
            --blue:           #3b82f6;
            --amber:          #f59e0b;
            
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --font:           'DM Sans', system-ui, sans-serif;
            --transition:     0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: var(--font); background: var(--bg-base); color: var(--text-primary);
            margin: 0; padding-bottom: 80px; /* Space for sticky footer */
        }
        .access-container { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }
        
        .page-title { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 1.5rem 0; color: #fff; letter-spacing: -0.02em; display: flex; align-items: center; gap: 10px;}

        /* ─── CONTROL PANEL ─── */
        .control-panel {
            background: var(--bg-surface); padding: 1.5rem; border-radius: var(--radius-lg);
            border: 1px solid var(--border); margin-bottom: 2rem; display: flex; gap: 20px;
            align-items: flex-start; flex-wrap: wrap; box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        }
        .control-panel.active-panel { background: var(--bg-elevated); border-color: rgba(59,130,246,0.3); }

        .form-group { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 250px; }
        .form-group label { color: var(--text-secondary); font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
        
        .form-control {
            background: var(--bg-base); border: 1px solid var(--border); color: white;
            padding: 12px 16px; border-radius: var(--radius-md); width: 100%; font-family: var(--font); font-size: 0.95rem;
            outline: none; transition: var(--transition); appearance: none;
        }
        select.form-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 16px center; cursor: pointer;
        }
        .form-control:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        .hint-text { color: var(--text-muted); font-size: 0.8rem; }

        /* ─── RESTRICTION BANNER ─── */
        .restriction-notice {
            display: none; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: var(--radius-md); padding: 1rem 1.25rem; font-size: 0.9rem; color: #fcd34d;
            margin-bottom: 1.5rem; align-items: center; gap: 10px; font-weight: 500;
        }
        .restriction-notice.show { display: flex; }

        /* ─── PERMISSION CARDS ─── */
        .grid-wrapper { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }

        .card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; transition: var(--transition); }
        .card:hover { border-color: rgba(255,255,255,0.15); box-shadow: 0 10px 30px rgba(0,0,0,0.3); transform: translateY(-2px);}
        
        .card-header {
            background: var(--bg-elevated); padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
        }
        .card-title { color: var(--rose); font-weight: 700; margin: 0; font-size: 1rem; }
        .card-body { padding: 1rem 1.5rem; }

        .select-all-link { color: var(--blue); font-size: 0.8rem; font-weight: 600; text-decoration: none; cursor: pointer; transition: var(--transition); }
        .select-all-link:hover { color: #93c5fd; }

        /* Checkbox Items */
        .chk-item {
            display: flex; align-items: center; gap: 12px; margin-bottom: 8px; padding: 8px 10px;
            border-radius: 8px; cursor: pointer; transition: var(--transition); border: 1px solid transparent;
        }
        .chk-item:hover:not(.is-restricted) { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.05); }
        
        .chk-item input {
            appearance: none; width: 18px; height: 18px; border: 2px solid var(--text-muted); border-radius: 4px;
            margin: 0; cursor: pointer; position: relative; transition: var(--transition); background: var(--bg-base); flex-shrink: 0;
        }
        .chk-item input:checked { background: var(--rose); border-color: var(--rose); }
        .chk-item input:checked::after {
            content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            color: #fff; font-size: 11px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
        }
        .chk-item span { color: var(--text-primary); font-size: 0.95rem; font-weight: 500; user-select: none; }

        /* Locked/restricted checkbox styling */
        .chk-item.is-restricted { opacity: 0.5; cursor: not-allowed; }
        .chk-item.is-restricted input { cursor: not-allowed; background: rgba(255,255,255,0.05); border-color: transparent;}
        .chk-item.is-restricted span { color: var(--text-muted); }
        .chk-item.is-restricted span::after { content: '\f023'; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 0.75rem; margin-left: 8px; color: var(--amber); opacity: 0.8; }

        /* ─── STICKY FOOTER ─── */
        .sticky-footer {
            position: fixed; bottom: 0; left: 0; width: 100%; background: rgba(15,23,42,0.9);
            backdrop-filter: blur(10px); border-top: 1px solid var(--border); padding: 1.25rem;
            display: flex; justify-content: center; box-shadow: 0 -10px 40px rgba(0,0,0,0.5); z-index: 100;
        }
        .btn-save {
            background: var(--emerald); color: #000; border: none; padding: 14px 40px;
            border-radius: var(--radius-md); font-weight: 800; cursor: pointer; font-size: 1rem; font-family: var(--font);
            transition: var(--transition); display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(16,185,129,0.2);
        }
        .btn-save:hover { background: #34d399; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16,185,129,0.4); }

        /* ─── PROFESSIONAL MODAL ─── */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(8px);
            z-index: 2000; display: none; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .modal-overlay.show { display: flex; opacity: 1; }
        
        .modal-card {
            background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl);
            width: 100%; max-width: 450px; padding: 2.5rem 2rem; text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); transform: scale(0.95); opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden;
        }
        .modal-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--amber), #d97706); }
        .modal-overlay.show .modal-card { transform: scale(1); opacity: 1; }
        
        .modal-icon-wrapper {
            width: 72px; height: 72px; border-radius: 50%; background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3); display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem auto; color: var(--amber); font-size: 2rem; box-shadow: 0 0 20px rgba(245, 158, 11, 0.2);
        }
        
        .modal-title { color: white; font-size: 1.5rem; font-weight: 700; margin: 0 0 0.75rem 0; letter-spacing: -0.02em;}
        .modal-text { color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 2rem; line-height: 1.5; }
        .modal-highlight { color: var(--rose); font-size: 1.1rem; display: inline-block; margin-top: 8px; font-weight: 700; font-family: var(--font-mono);}
        
        .modal-actions { display: flex; gap: 1rem; justify-content: center; }
        .btn-cancel {
            background: transparent; color: var(--text-secondary); border: 1px solid var(--border);
            padding: 12px 24px; border-radius: var(--radius-md); font-weight: 700; font-family: var(--font);
            cursor: pointer; transition: var(--transition); flex: 1;
        }
        .btn-cancel:hover { background: var(--bg-hover); color: white; border-color: var(--text-muted); }
        .btn-confirm {
            background: var(--blue); color: white; border: none; padding: 12px 24px; flex: 1;
            border-radius: var(--radius-md); font-weight: 700; font-family: var(--font); cursor: pointer;
            transition: var(--transition); box-shadow: 0 4px 15px rgba(59,130,246,0.3); display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-confirm:hover { background: #2563eb; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(59,130,246,0.4);}

        /* Toast Notifications */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out; display: flex; align-items: center; gap: 8px;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
</head>
<body>
<div id="toastContainer"></div>

<div class="access-container">
    <h1 class="page-title"><i class="fa-solid fa-shield-halved" style="color:var(--rose);"></i> User Access Control</h1>

    <div class="control-panel">
        <form method="GET" style="display:flex; gap:20px; align-items:flex-end; width:100%; flex-wrap: wrap;">
            <div class="form-group" style="max-width:400px; margin:0;">
                <label>Select User to Configure:</label>
                <select name="user_id" class="form-control" onchange="this.form.submit()" required>
                    <option value="">-- Choose User --</option>
                    <?php foreach($users as $u):
                        $rName = match((int)$u['USER_TYPE']) {
                            1 => 'New User', 2 => 'Farm Employee',
                            3 => 'Admin',    4 => 'Super Admin', default => 'Unknown'
                        };
                    ?>
                        <option value="<?= $u['USER_ID'] ?>" <?= $selected_user == $u['USER_ID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['FULL_NAME']) ?> (<?= $rName ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if ($selected_user): ?>
    <?php if (!isset($permission_map)): ?>
        <div style="color:var(--red); padding:20px; border:1px solid var(--red); background:var(--red-dim); border-radius:var(--radius-md); font-weight:700;"><i class="fa-solid fa-triangle-exclamation"></i> CRITICAL ERROR: config/PageList.php is missing.</div>
    <?php else: ?>

    <div class="restriction-notice <?= in_array((int)$current_role, [1, 2, 3]) ? 'show' : '' ?>" id="restrictionNotice">
        <i class="fa-solid fa-lock"></i>
        <span id="restrictionText">
            <?php if (in_array((int)$current_role, [1, 2])): ?>
                Some permissions are disabled for this role. Promote to Admin or Super Admin to unlock them.
            <?php elseif ((int)$current_role === 3): ?>
                The System section (Settings, Manage Accounts, Audit Logs) is reserved for Super Admins only.
            <?php endif; ?>
        </span>
    </div>

    <form id="accessForm" action="../process/saveAccess.php" method="POST">
        <input type="hidden" name="user_id" value="<?= $selected_user ?>">

        <div class="control-panel active-panel">

            <div class="form-group">
                <label style="color:var(--blue);">User Role Hierarchy:</label>
                <select name="role_id" id="roleSelector" class="form-control" onchange="applyRolePreset(this.value)">
                    <option value="1" <?= $current_role == 1 ? 'selected' : '' ?>>👤 New User (No Access)</option>
                    <option value="2" <?= $current_role == 2 ? 'selected' : '' ?>>🚜 Farm Employee (Operations)</option>
                    <option value="3" <?= $current_role == 3 ? 'selected' : '' ?>>👔 Admin (Management)</option>
                    <option value="4" <?= $current_role == 4 ? 'selected' : '' ?>>⚡ Super Admin (Full Control)</option>
                </select>
                <span class="hint-text">Changing this automatically configures permissions below.</span>
            </div>

            <div class="form-group">
                <label style="color:var(--emerald);">Assigned Location Constraint:</label>
                <select name="location_id" id="locationSelector" class="form-control">
                    <option value="">Assign Location...</option>
                    <option value="1000" <?= $current_location == 1000 ? 'selected' : '' ?>>All Locations (Global)</option>
                    <?php foreach($locations as $loc): ?>
                        <option value="<?= $loc['LOCATION_ID'] ?>" <?= ($current_location !== null && $current_location == $loc['LOCATION_ID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="hint-text">Leave unassigned for standard Global/All Locations access.</span>
            </div>
        </div>

        <div class="grid-wrapper">
            <?php foreach($permission_map as $category => $pages): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?= $category ?></h3>
                        <span class="select-all-link" onclick="toggleCard(this)">Select All</span>
                    </div>
                    <div class="card-body">
                        <?php foreach($pages as $col_name => $label):
                            $isChecked = (isset($permissions[$col_name]) && $permissions[$col_name] == 1) ? 'checked' : '';

                            // Restricted for basic roles (USER_TYPE 1 or 2)
                            $isBasicRestricted = in_array($col_name, $restricted_for_basic_roles)
                                                 && in_array((int)$current_role, [1, 2]);

                            // Restricted for non-superadmin (USER_TYPE 1, 2, or 3)
                            $isSuperRestricted = in_array($col_name, $restricted_for_non_superadmin)
                                                 && (int)$current_role !== 4;

                            $isRestricted    = $isBasicRestricted || $isSuperRestricted;
                            $disabledAttr    = $isRestricted ? 'disabled' : '';
                            $restrictedClass = $isRestricted ? 'is-restricted' : '';

                            // data-type tells JS which restriction bucket this checkbox belongs to
                            $dataRestrict = in_array($col_name, $restricted_for_basic_roles)     ? 'basic'
                                          : (in_array($col_name, $restricted_for_non_superadmin) ? 'super'
                                          : 'none');
                        ?>
                            <label class="chk-item <?= $restrictedClass ?>">
                                <input type="checkbox"
                                    name="perms[<?= $col_name ?>]"
                                    value="1"
                                    class="perm-chk"
                                    id="chk_<?= $col_name ?>"
                                    data-restrict-type="<?= $dataRestrict ?>"
                                    <?= $isChecked ?>
                                    <?= $disabledAttr ?>>
                                <span><?= $label ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="sticky-footer">
            <button type="button" class="btn-save" onclick="openConfirmationModal()"><i class="fa-solid fa-shield-check"></i> Save Role &amp; Permissions</button>
        </div>
    </form>

    <div id="confirmModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-icon-wrapper">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h2 class="modal-title">Confirm Access Changes</h2>
            <p class="modal-text">
                You are about to update the functional role and system permissions for<br>
                <strong class="modal-highlight"><?= htmlspecialchars($selected_user_name) ?></strong>.<br><br>
                Are you sure you want to proceed?
            </p>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeConfirmationModal()">Cancel</button>
                <button type="button" class="btn-confirm" onclick="submitForm()"><i class="fa-solid fa-check"></i> Apply Changes</button>
            </div>
        </div>
    </div>

    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    // ── Toast Notification Helper ───────────────────────────────────────────
    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--emerald)'}`;
        t.innerHTML = `${type === 'error' ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-check"></i>'} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    // ── Restriction lists (from PageList.php via PHP) ─────────────────────────
    const RESTRICTED_BASIC = <?= $restricted_json ?>;        // locked for USER_TYPE 1 & 2
    const RESTRICTED_SUPER = <?= $restricted_super_json ?>;  // locked for USER_TYPE 1, 2 & 3 (non-superadmin)

    // ── Apply restrictions based on role ─────────────────────────────────────
    function applyRestrictions(roleId) {
        const role    = parseInt(roleId);
        const notice  = document.getElementById('restrictionNotice');
        const noticeText = document.getElementById('restrictionText');

        // Basic-role restrictions: disabled for role 1 and 2
        RESTRICTED_BASIC.forEach(col => {
            const cb    = document.getElementById('chk_' + col);
            const label = cb ? cb.closest('.chk-item') : null;
            if (!cb || !label) return;

            if (role === 1 || role === 2) {
                cb.disabled = true;
                cb.checked  = false;
                label.classList.add('is-restricted');
            } else {
                cb.disabled = false;
                label.classList.remove('is-restricted');
            }
        });

        // Super-admin-only restrictions: disabled for everyone except role 4
        RESTRICTED_SUPER.forEach(col => {
            const cb    = document.getElementById('chk_' + col);
            const label = cb ? cb.closest('.chk-item') : null;
            if (!cb || !label) return;

            if (role !== 4) {
                cb.disabled = true;
                cb.checked  = false;
                label.classList.add('is-restricted');
            } else {
                cb.disabled = false;
                label.classList.remove('is-restricted');
            }
        });

        // Update notice banner
        if (role === 1 || role === 2) {
            notice.classList.add('show');
            noticeText.textContent = 'Some permissions are disabled for this role. Promote to Admin or Super Admin to unlock them.';
        } else if (role === 3) {
            notice.classList.add('show');
            noticeText.textContent = 'The System section (Settings, Manage Accounts, Audit Logs) is reserved for Super Admins only.';
        } else {
            notice.classList.remove('show');
        }

        document.querySelectorAll('.card').forEach(card => updateCardHeaderText(card));
    }

    // ── Role presets ──────────────────────────────────────────────────────────
    const presets = {
        '1': [],    // New User: clear all
        '2': [      // Farm Employee: basic ops (restricted columns auto-excluded by applyRestrictions)
            'dashboard', 'animal_record',
            'farm', 'animal_class', 'event_scheduler', 'animal_transfer',
            'animal_weights', 'animal_operations',
            'transactions', 'individual_operations', 'feeding', 'medication',
            'vitamins_supplements_trans', 'check_ups', 'vaccination',
            'batch_group_operations', 'group_medication', 'group_vitamins',
            'group_checkup', 'group_vaccination',
        ],
        '3': 'ALL_EXCEPT_SYSTEM',   // Admin: everything except SYSTEM (auto-locked by applyRestrictions)
        '4': 'ALL',                 // Super Admin: everything
    };

    function applyRolePreset(roleId) {
        const checkboxes = document.querySelectorAll('.perm-chk');
        const preset     = presets[roleId];

        if (preset === 'ALL') {
            checkboxes.forEach(cb => { cb.checked = true; });
        } else if (preset === 'ALL_EXCEPT_SYSTEM') {
            checkboxes.forEach(cb => {
                if (cb.disabled) return;
                cb.checked = true; // applyRestrictions below will uncheck/disable SYSTEM cols
            });
        } else {
            checkboxes.forEach(cb => {
                if (cb.disabled) return;
                const key  = cb.id.replace('chk_', '');
                cb.checked = preset.includes(key);
            });
        }

        // Always apply restrictions after setting checkboxes
        applyRestrictions(roleId);
    }

    // ── Card select all / unselect all ────────────────────────────────────────
    function toggleCard(link) {
        const card       = link.closest('.card');
        const checkboxes = Array.from(card.querySelectorAll('input[type="checkbox"]:not(:disabled)'));
        const allChecked = checkboxes.every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
        updateCardHeaderText(card);
    }

    function updateCardHeaderText(card) {
        const checkboxes = Array.from(card.querySelectorAll('input[type="checkbox"]:not(:disabled)'));
        const link       = card.querySelector('.select-all-link');
        if (!link) return;
        if (checkboxes.length === 0) {
            // All checkboxes in this card are disabled (e.g. SYSTEM for non-superadmin)
            link.style.display = 'none';
            return;
        }
        link.style.display = '';
        link.innerText = checkboxes.every(cb => cb.checked) ? 'Unselect All' : 'Select All';
    }

    // ── Page load ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const roleSel = document.getElementById('roleSelector');
        if(roleSel) {
            applyRestrictions(roleSel.value);
            document.querySelectorAll('.card').forEach(card => updateCardHeaderText(card));
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success') === '1') {
            showToast('Permissions and Role successfully updated!');
            window.history.replaceState({}, document.title, window.location.pathname + '?user_id=<?= $selected_user ?>');
        }
    });

    // ── Modal ─────────────────────────────────────────────────────────────────
    const modal = document.getElementById('confirmModal');
    const form  = document.getElementById('accessForm');

    function openConfirmationModal()  { modal.classList.add('show'); }
    function closeConfirmationModal() { modal.classList.remove('show'); }

    function submitForm() {
        const btn = document.querySelector('.btn-confirm');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        btn.disabled  = true;
        form.submit();
    }

    // Close on outside click
    modal.addEventListener('click', e => {
        if (e.target === modal) closeConfirmationModal();
    });
</script>

</body>
</html>