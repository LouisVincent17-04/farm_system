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
    <title>Manage Access | FarmPro</title>
    <style>
        .access-container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .grid-wrapper { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }

        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; overflow: hidden; }
        .card-header { background: rgba(15,23,42,0.5); padding: 1rem; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; }
        .card-title { color: #f43f5e; font-weight: 700; margin: 0; font-size: 0.95rem; text-transform: uppercase; }
        .card-body { padding: 1rem; }

        .chk-item { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; padding: 6px; border-radius: 6px; cursor: pointer; transition: background 0.2s; }
        .chk-item:hover { background: rgba(255,255,255,0.05); }
        .chk-item input { width: 18px; height: 18px; accent-color: #f43f5e; cursor: pointer; }
        .chk-item span { color: #cbd5e1; font-size: 0.9rem; }

        /* Locked/restricted checkbox styling */
        .chk-item.is-restricted { opacity: 0.4; cursor: not-allowed; }
        .chk-item.is-restricted:hover { background: transparent; }
        .chk-item.is-restricted input { cursor: not-allowed; }
        .chk-item.is-restricted span::after {
            content: ' 🔒';
            font-size: 0.75rem;
            opacity: 0.7;
        }

        .select-all-link { color: #60a5fa; font-size: 0.75rem; text-decoration: none; cursor: pointer; }
        .select-all-link:hover { text-decoration: underline; }

        .sticky-footer { position: fixed; bottom: 0; left: 0; width: 100%; background: #0f172a; border-top: 1px solid #334155; padding: 1rem; text-align: center; box-shadow: 0 -5px 20px rgba(0,0,0,0.5); z-index: 100; }

        .control-panel { background: #1e293b; padding: 1.5rem; border-radius: 12px; border: 1px solid #334155; margin-bottom: 2rem; display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; }
        .form-group { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 250px; }
        .form-group label { color: #94a3b8; font-weight: bold; font-size: 0.9rem; }
        .form-control { background: #0f172a; border: 1px solid #334155; color: white; padding: 10px; border-radius: 8px; width: 100%; }
        .form-control:focus { outline: none; border-color: #3b82f6; }

        .btn-save { background: #22c55e; color: #064e3b; border: none; padding: 12px 40px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1rem; transition: transform 0.2s, background 0.2s; }
        .btn-save:hover { background: #16a34a; transform: translateY(-2px); color: white; }

        /* Restriction notice banner */
        .restriction-notice {
            display: none;
            background: rgba(251,191,36,0.08);
            border: 1px solid rgba(251,191,36,0.3);
            border-radius: 8px;
            padding: 0.65rem 1rem;
            font-size: 0.82rem;
            color: #fcd34d;
            margin-bottom: 1.25rem;
            align-items: center;
            gap: 8px;
        }
        .restriction-notice.show { display: flex; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 2000; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { display: flex; opacity: 1; }
        .modal-card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; width: 100%; max-width: 450px; padding: 2rem; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); transform: translateY(20px); transition: transform 0.3s ease; }
        .modal-overlay.show .modal-card { transform: translateY(0); }
        .modal-icon { font-size: 3rem; margin-bottom: 1rem; display: block; }
        .modal-title { color: white; font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
        .modal-text { color: #94a3b8; font-size: 0.95rem; margin-bottom: 2rem; line-height: 1.5; }
        .modal-text strong { color: #38bdf8; }
        .modal-actions { display: flex; gap: 1rem; justify-content: center; }
        .btn-cancel { background: transparent; color: #94a3b8; border: 1px solid #475569; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-cancel:hover { background: rgba(255,255,255,0.05); color: white; }
        .btn-confirm { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px rgba(59,130,246,0.3); }
        .btn-confirm:hover { background: linear-gradient(135deg, #2563eb, #1d4ed8); transform: translateY(-1px); }
    </style>
</head>
<body>

<div class="access-container">
    <h1 style="color:white; margin-bottom:1.5rem;">🛡️ User Access Control</h1>

    <!-- User selector -->
    <div class="control-panel">
        <form method="GET" style="display:flex; gap:20px; align-items:flex-end; width:100%;">
            <div class="form-group" style="max-width:400px;">
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
        <div style="color:var(--danger);padding:20px;border:1px solid red;">CRITICAL ERROR: config/PageList.php is missing.</div>
    <?php else: ?>

    <!-- Restriction notice — shown for basic roles or non-superadmins -->
    <div class="restriction-notice <?= in_array((int)$current_role, [1, 2, 3]) ? 'show' : '' ?>" id="restrictionNotice">
        🔒 <span id="restrictionText">
            <?php if (in_array((int)$current_role, [1, 2])): ?>
                Some permissions are disabled for this role. Promote to Admin or Super Admin to unlock them.
            <?php elseif ((int)$current_role === 3): ?>
                The System section (Settings, Manage Accounts, Audit Logs) is reserved for Super Admins only.
            <?php endif; ?>
        </span>
    </div>

    <form id="accessForm" action="../process/saveAccess.php" method="POST">
        <input type="hidden" name="user_id" value="<?= $selected_user ?>">

        <div class="control-panel" style="margin-bottom:1.5rem; background:#0f172a; border-color:#3b82f6;">

            <div class="form-group">
                <label style="color:#60a5fa;">User Role:</label>
                <select name="role_id" id="roleSelector" class="form-control" onchange="applyRolePreset(this.value)">
                    <option value="1" <?= $current_role == 1 ? 'selected' : '' ?>>👤 New User (No Access)</option>
                    <option value="2" <?= $current_role == 2 ? 'selected' : '' ?>>🚜 Farm Employee (Operations)</option>
                    <option value="3" <?= $current_role == 3 ? 'selected' : '' ?>>👔 Admin (Management)</option>
                    <option value="4" <?= $current_role == 4 ? 'selected' : '' ?>>⚡ Super Admin (Full Control)</option>
                </select>
                <small style="color:#64748b;">Changing this automatically checks/unchecks permissions below.</small>
            </div>

            <div class="form-group">
                <label style="color:#34d399;">Assigned Location:</label>
                <select name="location_id" id="locationSelector" class="form-control">
                    <option value="">Assign Location...</option>
                    <option value="1000" <?= $current_location == 1000 ? 'selected' : '' ?>>All Locations...</option>
                    <?php foreach($locations as $loc): ?>
                        <option value="<?= $loc['LOCATION_ID'] ?>" <?= ($current_location !== null && $current_location == $loc['LOCATION_ID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color:#64748b;">Leave unassigned for Global/All Locations access.</small>
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

        <div style="height:100px;"></div>

        <div class="sticky-footer">
            <button type="button" class="btn-save" onclick="openConfirmationModal()">💾 Save Role & Permissions</button>
        </div>
    </form>

    <!-- Confirmation modal -->
    <div id="confirmModal" class="modal-overlay">
        <div class="modal-card">
            <span class="modal-icon">⚠️</span>
            <h2 class="modal-title">Confirm Access Changes</h2>
            <p class="modal-text">
                You are about to update the role and permissions for<br>
                <strong><?= htmlspecialchars($selected_user_name) ?></strong>.<br><br>
                Are you sure you want to proceed?
            </p>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeConfirmationModal()">Cancel</button>
                <button type="button" class="btn-confirm" onclick="submitForm()">Yes, Save Changes</button>
            </div>
        </div>
    </div>

    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
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
            checkboxes.forEach(cb => { if (!cb.disabled) cb.checked = true; });
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

        // Always apply restrictions after setting checkboxes —
        // this unchecks and disables anything the role isn't allowed to have
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
        const currentRole = document.getElementById('roleSelector').value;
        applyRestrictions(currentRole);
        document.querySelectorAll('.card').forEach(card => updateCardHeaderText(card));

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success') === '1') {
            setTimeout(() => {
                alert('✅ Permissions and Role successfully updated!');
                window.history.replaceState({}, document.title, window.location.pathname + '?user_id=<?= $selected_user ?>');
            }, 100);
        }
    });

    // ── Modal ─────────────────────────────────────────────────────────────────
    const modal = document.getElementById('confirmModal');
    const form  = document.getElementById('accessForm');

    function openConfirmationModal()  { modal.classList.add('show'); }
    function closeConfirmationModal() { modal.classList.remove('show'); }

    function submitForm() {
        const btn = document.querySelector('.btn-confirm');
        btn.innerHTML = 'Saving...';
        btn.disabled  = true;
        form.submit();
    }

    modal.addEventListener('click', e => {
        if (e.target === modal) closeConfirmationModal();
    });
</script>

</body>
</html>