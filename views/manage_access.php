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
include '../common/chat_support.php';

// 1. Fetch Users (Exclude active Super Admins to prevent self-lockout)
try {
    // We select ALL users except Role 4 (Super Admin)
    $users = $conn->query("SELECT USER_ID, FULL_NAME, USER_TYPE FROM users WHERE IS_ACTIVE = 1 ORDER BY USER_ID")->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all available locations for the assignment dropdown
    $locations = $conn->query("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching data: " . $e->getMessage());
}

// 2. Handle User Selection
$selected_user = $_GET['user_id'] ?? 0;
$permissions = [];
$current_role = 1; // Default to 'New User' if not found
$current_location = null; // Default to Global/NULL
$selected_user_name = ''; // To display in the modal

if ($selected_user) {
    try {
        // A. Fetch Existing Permissions
        $stmt = $conn->prepare("SELECT * FROM access_control WHERE user_id = ?");
        $stmt->execute([$selected_user]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) $permissions = $result;

        // B. Fetch Current Role, Location, and Name from Users table
        $roleStmt = $conn->prepare("SELECT FULL_NAME, USER_TYPE, LOCATION_ID FROM users WHERE USER_ID = ?");
        $roleStmt->execute([$selected_user]);
        $userRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
        
        if($userRow) {
            $selected_user_name = $userRow['FULL_NAME'];
            $current_role = $userRow['USER_TYPE'];
            $current_location = $userRow['LOCATION_ID'];
        }

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
        .control-panel { background: #1e293b; padding: 1.5rem; border-radius: 12px; border: 1px solid #334155; margin-bottom: 2rem; display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; }
        .form-group { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 250px;}
        .form-group label { color: #94a3b8; font-weight: bold; font-size: 0.9rem; }
        .form-control { background: #0f172a; border: 1px solid #334155; color: white; padding: 10px; border-radius: 8px; width: 100%; }
        .form-control:focus { outline: none; border-color: #3b82f6; }
        
        .btn-save { background: #22c55e; color: #064e3b; border: none; padding: 12px 40px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1rem; transition: transform 0.2s, background 0.2s;}
        .btn-save:hover { background: #16a34a; transform: translateY(-2px); color: white;}

        /* --- MODAL STYLES --- */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.show {
            display: flex;
            opacity: 1;
        }
        .modal-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            width: 100%;
            max-width: 450px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }
        .modal-overlay.show .modal-card {
            transform: translateY(0);
        }
        .modal-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
        .modal-title {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .modal-text {
            color: #94a3b8;
            font-size: 0.95rem;
            margin-bottom: 2rem;
            line-height: 1.5;
        }
        .modal-text strong {
            color: #38bdf8;
        }
        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        .btn-cancel {
            background: transparent;
            color: #94a3b8;
            border: 1px solid #475569;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-cancel:hover {
            background: rgba(255,255,255,0.05);
            color: white;
        }
        .btn-confirm {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);
        }
        .btn-confirm:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-1px);
            box-shadow: 0 6px 8px rgba(59, 130, 246, 0.4);
        }
    </style>
</head>
<body>

<div class="access-container">
    <h1 style="color: white; margin-bottom: 1.5rem;">🛡️ User Access Control</h1>

    <div class="control-panel">
        <form method="GET" style="display:flex; gap:20px; align-items:flex-end; width:100%;">
            <div class="form-group" style="max-width: 400px;">
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
    
        <form id="accessForm" action="../process/saveAccess.php" method="POST">
            <input type="hidden" name="user_id" value="<?= $selected_user ?>">
            
            <div class="control-panel" style="margin-bottom: 1.5rem; background: #0f172a; border-color: #3b82f6;">
                
                <div class="form-group">
                    <label style="color: #60a5fa;">User Role:</label>
                    <select name="role_id" id="roleSelector" class="form-control" onchange="applyRolePreset(this.value)">
                        <option value="1" <?= $current_role == 1 ? 'selected' : '' ?>>👤 New User (No Access)</option>
                        <option value="2" <?= $current_role == 2 ? 'selected' : '' ?>>🚜 Farm User (Operations)</option>
                        <option value="3" <?= $current_role == 3 ? 'selected' : '' ?>>👔 Admin (Management)</option>
                        <option value="4" <?= $current_role == 4 ? 'selected' : '' ?>>⚡ Super Admin (Full Control)</option>
                    </select>
                    <small style="color: #64748b;">Changing this automatically checks/unchecks permissions below.</small>
                </div>

                <div class="form-group">
                    <label style="color: #34d399;">Assigned Location:</label>
                    <select name="location_id" id="locationSelector" class="form-control">
                        <option value="">Assign Location...</option>
                        <option value="1000" <?= $current_location == 1000 ? 'selected' : '' ?>>All Locations...</option>
                        <?php foreach($locations as $loc): ?>
                            <option value="<?= $loc['LOCATION_ID'] ?>" <?= ($current_location !== null && $current_location == $loc['LOCATION_ID']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #64748b;">Leave unassigned (default) for Global/All Locations access.</small>
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
                <button type="button" class="btn-save" onclick="openConfirmationModal()">💾 Save Role & Permissions</button>
            </div>
        </form>

        <div id="confirmModal" class="modal-overlay">
            <div class="modal-card">
                <span class="modal-icon">⚠️</span>
                <h2 class="modal-title">Confirm Access Changes</h2>
                <p class="modal-text">
                    You are about to update the role and permissions for <br>
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

    // Loop through all cards and update the "Select/Unselect" text
    document.querySelectorAll('.card').forEach(card => {
        updateCardHeaderText(card);
    });
}

function toggleCard(link) {
    const card = link.closest('.card');
    const checkboxes = card.querySelectorAll('input[type="checkbox"]');
    
    // Check if they are currently ALL checked
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    // Toggle to the opposite
    checkboxes.forEach(cb => cb.checked = !allChecked);
    
    // Update the text
    updateCardHeaderText(card);
}

// Helper function to update the text based on current checkbox state
function updateCardHeaderText(card) {
    const checkboxes = card.querySelectorAll('input[type="checkbox"]');
    const link = card.querySelector('.select-all-link');
    
    // Check if every single box in this card is checked
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    // Update text
    link.innerText = allChecked ? "Unselect All" : "Select All";
}

// Run on page load to set initial state correctly
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.card').forEach(card => {
        updateCardHeaderText(card);
    });
    
    // Check for success parameter in URL to show an alert
    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.get('success') === '1') {
        // Using a brief timeout so the DOM fully loads before alert
        setTimeout(() => {
            alert('✅ Permissions and Role successfully updated!');
            // Clean the URL so refresh doesn't trigger it again
            window.history.replaceState({}, document.title, window.location.pathname + "?user_id=<?= $selected_user ?>");
        }, 100);
    }
});

// --- MODAL LOGIC ---
const modal = document.getElementById('confirmModal');
const form = document.getElementById('accessForm');

function openConfirmationModal() {
    modal.classList.add('show');
}

function closeConfirmationModal() {
    modal.classList.remove('show');
}

function submitForm() {
    // Disable button to prevent double submit
    const btn = document.querySelector('.btn-confirm');
    btn.innerHTML = 'Saving...';
    btn.disabled = true;
    
    form.submit();
}

// Close modal if user clicks outside the card
modal.addEventListener('click', function(e) {
    if (e.target === modal) {
        closeConfirmationModal();
    }
});
</script>

</body>
</html>