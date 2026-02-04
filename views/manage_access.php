<?php
// views/manage_access.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page = "settings";
include '../config/Connection.php';
include '../security/checkAccess.php';

// 1. Security Check
checkAccess('manage_accounts');
include '../common/navbar.php';
include '../config/PageList.php';

// 1. Fetch Users (Exclude active Super Admins to prevent self-lockout)
try {
    // We select ALL users except Role 4 (Super Admin)
    $users = $conn->query("SELECT USER_ID, FULL_NAME, USER_TYPE FROM users WHERE IS_ACTIVE = 1 ORDER BY USER_ID")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching users: " . $e->getMessage());
}

// 2. Handle User Selection
$selected_user = $_GET['user_id'] ?? 0;
$permissions = [];
$current_role = 1; // Default to 'New User' if not found

if ($selected_user) {
    try {
        // A. Fetch Existing Permissions
        $stmt = $conn->prepare("SELECT * FROM access_control WHERE user_id = ?");
        $stmt->execute([$selected_user]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) $permissions = $result;

        // B. Fetch Current Role from Users table
        $roleStmt = $conn->prepare("SELECT USER_TYPE FROM users WHERE USER_ID = ?");
        $roleStmt->execute([$selected_user]);
        $fetchedRole = $roleStmt->fetchColumn();
        
        if($fetchedRole) $current_role = $fetchedRole;

    } catch (Exception $e) {
        echo "<div style='color:red; background:white; padding:10px;'>Error: " . $e->getMessage() . "</div>";
    }
}
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
        .card-header { background: rgba(15, 23, 42, 0.5); padding: 1rem; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; }
        .card-title { color: #f43f5e; font-weight: 700; margin: 0; font-size: 0.95rem; text-transform: uppercase; }
        .card-body { padding: 1rem; }
        
        .chk-item { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; padding: 6px; border-radius: 6px; cursor: pointer; transition: background 0.2s; }
        .chk-item:hover { background: rgba(255,255,255,0.05); }
        .chk-item input { width: 18px; height: 18px; accent-color: #f43f5e; cursor: pointer; }
        .chk-item span { color: #cbd5e1; font-size: 0.9rem; }
        
        .select-all-link { color: #60a5fa; font-size: 0.75rem; text-decoration: none; cursor: pointer; }
        .select-all-link:hover { text-decoration: underline; }

        .sticky-footer { position: fixed; bottom: 0; left: 0; width: 100%; background: #0f172a; border-top: 1px solid #334155; padding: 1rem; text-align: center; box-shadow: 0 -5px 20px rgba(0,0,0,0.5); z-index: 100;}
        
        /* Control Panel */
        .control-panel { background: #1e293b; padding: 1.5rem; border-radius: 12px; border: 1px solid #334155; margin-bottom: 2rem; display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap; }
        .form-group { display: flex; flex-direction: column; gap: 8px; width: 100%; max-width: 400px;}
        .form-group label { color: #94a3b8; font-weight: bold; font-size: 0.9rem; }
        .form-control { background: #0f172a; border: 1px solid #334155; color: white; padding: 10px; border-radius: 8px; width: 100%; }
        
        .btn-save { background: #22c55e; color: #064e3b; border: none; padding: 12px 40px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1rem; }
    </style>
</head>
<body>

<div class="access-container">
    <h1 style="color: white; margin-bottom: 1.5rem;">🛡️ User Access Control</h1>

    <div class="control-panel">
        <form method="GET" style="display:flex; gap:20px; align-items:flex-end; width:100%;">
            <div class="form-group">
                <label>Select User to Configure:</label>
                <select name="user_id" class="form-control" onchange="this.form.submit()" required>
                    <option value="">-- Choose User --</option>
                    <?php foreach($users as $u): 
                        $rName = match($u['USER_TYPE']) { 1 => 'New User', 2 => 'Farm User', 3 => 'Admin', 4 => 'Super Admin', default => 'Unknown' };
                    ?>
                        <option value="<?= $u['USER_ID'] ?>" <?= $selected_user == $u['USER_ID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['FULL_NAME']) ?> (<?= $rName ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if($selected_user): ?>
        <?php if(!isset($permission_map)): ?>
            <div style="color:var(--danger); padding:20px; border:1px solid red;">CRITICAL ERROR: config/PageList.php is missing.</div>
        <?php else: ?>
    
        <form action="../process/saveAccess.php" method="POST">
            <input type="hidden" name="user_id" value="<?= $selected_user ?>">
            
            <div class="control-panel" style="margin-bottom: 1.5rem; background: #0f172a; border-color: #3b82f6;">
                <div class="form-group">
                    <label style="color: #60a5fa;">User Role (Updating this saves to 'users' table):</label>
                    <select name="role_id" id="roleSelector" class="form-control" onchange="applyRolePreset(this.value)">
                        <option value="1" <?= $current_role == 1 ? 'selected' : '' ?>>👤 New User (No Access)</option>
                        <option value="2" <?= $current_role == 2 ? 'selected' : '' ?>>🚜 Farm User (Operations)</option>
                        <option value="3" <?= $current_role == 3 ? 'selected' : '' ?>>👔 Admin (Management)</option>
                        <option value="4" <?= $current_role == 4 ? 'selected' : '' ?>>⚡ Super Admin (Full Control)</option>
                    </select>
                    <small style="color: #64748b;">Note: Changing this dropdown automatically checks/unchecks permissions below.</small>
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
                                // Logic: Check if DB has '1' OR if it's a new setup and array is empty
                                $isChecked = (isset($permissions[$col_name]) && $permissions[$col_name] == 1) ? 'checked' : '';
                            ?>
                                <label class="chk-item">
                                    <input type="checkbox" name="perms[<?= $col_name ?>]" value="1" class="perm-chk" id="chk_<?= $col_name ?>" <?= $isChecked ?>>
                                    <span><?= $label ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="height: 100px;"></div>

            <div class="sticky-footer">
                <button type="submit" class="btn-save">💾 Save Role & Permissions</button>
            </div>
        </form>
        
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// --- AUTO-CHECK LOGIC (Based on Role ID) ---
const presets = {
    '1': [], // New User: Clear All
    '2': [   // Farm User: Basic Ops
        'dashboard', 'animal_record', 'location', 'building', 'pen', 'breed', 
        'farm', 'animal_class', 'event_scheduler', 'animal_transfer', 'animal_weights', 'animal_operations',
        'transactions', 'individual_operations', 'feeding', 'medication', 'vitamins_supplements_trans', 'check_ups', 'vaccination',
        'reports', 'animal_report', 'medication_report', 'vaccination_report'
    ],
    '3': 'ALL_EXCEPT_SYSTEM', // Admin: All except Settings/Audit
    '4': 'ALL' // Super Admin: Everything
};

function applyRolePreset(roleId) {
    const checkboxes = document.querySelectorAll('.perm-chk');
    const preset = presets[roleId];

    if (preset === 'ALL') {
        checkboxes.forEach(cb => cb.checked = true);
    } else if (preset === 'ALL_EXCEPT_SYSTEM') {
        checkboxes.forEach(cb => {
            if(cb.id === 'chk_manage_accounts' || cb.id === 'chk_audit_logs') {
                cb.checked = false;
            } else {
                cb.checked = true;
            }
        });
    } else {
        // Array based logic
        checkboxes.forEach(cb => {
            const key = cb.id.replace('chk_', '');
            cb.checked = preset.includes(key);
        });
    }
}

function toggleCard(link) {
    const card = link.closest('.card');
    const checkboxes = card.querySelectorAll('input[type="checkbox"]');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
    link.innerText = allChecked ? "Select All" : "Unselect All";
}
</script>

</body>
</html>