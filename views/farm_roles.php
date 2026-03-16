<?php
// views/farm_roles.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "admin_dashboard";
include '../config/Connection.php';
include '../security/checkAccess.php';
checkAccess('farm_roles'); 
include '../common/navbar.php';
include '../common/chat_support.php';

// Fetch Roles
$roles = [];
try {
    $stmt = $conn->query("SELECT * FROM farm_roles ORDER BY ROLE_NAME ASC");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to load roles: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Roles Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; margin: 0; padding-bottom: 40px; }
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem; }
        
        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #94a3b8; font-weight: 600; margin-bottom: 20px; transition: 0.2s; }
        .back-link:hover { color: white; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .title { font-size: 2rem; color: #f59e0b; font-weight: bold; margin:0; }
        .btn-add { background: #f59e0b; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-add:hover { background: #d97706; }

        /* Table */
        .table-wrap { background: rgba(30, 41, 59, 0.5); border-radius: 12px; overflow: hidden; border: 1px solid #334155; }
        table { width: 100%; border-collapse: collapse; }
        th { background: rgba(15, 23, 42, 0.9); color: #f59e0b; text-align: left; padding: 1rem; text-transform: uppercase; font-size: 0.8rem; border-bottom: 1px solid #334155; }
        td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; }
        
        .actions button { background: transparent; border: none; font-size: 1.1rem; margin: 0 5px; cursor: pointer; transition: 0.2s; }
        .btn-edit { color: #3b82f6; } .btn-edit:hover { color: #60a5fa; }
        .btn-delete { color: #ef4444; } .btn-delete:hover { color: #f87171; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; z-index: 1000; }
        .modal.show { display: flex; }
        .modal-content { background: #1e293b; width: 100%; max-width: 400px; border-radius: 12px; border: 1px solid #334155; overflow: hidden; }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid #334155; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.5rem; border-top: 1px solid #334155; display: flex; justify-content: flex-end; gap: 10px; }
        
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.85rem; color: #94a3b8; margin-bottom: 5px; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 6px; box-sizing: border-box;}
        
        .btn-cancel { background: transparent; color: #cbd5e1; border: none; cursor: pointer; padding: 8px 16px; }
        .btn-save { background: #f59e0b; color: white; border: none; border-radius: 6px; padding: 8px 16px; cursor: pointer; }
    </style>
</head>
<body>

<div class="container">
    <a href="admin_dashboard.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    
    <div class="header">
        <h1 class="title">Farm Roles Setup</h1>
        <button class="btn-add" onclick="openModal('add')">+ Add Role</button>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width: 25%;">Role Name</th>
                    <th>Description</th>
                    <th style="text-align:center; width: 15%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($roles)): ?>
                    <tr><td colspan="3" style="text-align:center; padding: 2rem;">No roles configured.</td></tr>
                <?php else: ?>
                    <?php foreach($roles as $role): ?>
                        <tr data-id="<?= $role['ROLE_ID'] ?>" 
                            data-name="<?= htmlspecialchars($role['ROLE_NAME']) ?>"
                            data-desc="<?= htmlspecialchars($role['DESCRIPTION']) ?>">
                            <td style="font-weight:bold; color:#fff;"><?= htmlspecialchars($role['ROLE_NAME']) ?></td>
                            <td style="color:#94a3b8;"><?= htmlspecialchars($role['DESCRIPTION'] ?? 'No description') ?></td>
                            <td class="actions" style="text-align:center;">
                                <button class="btn-edit" onclick="openModal('edit', this)"><i class="fa-solid fa-pen-to-square"></i></button>
                                <button class="btn-delete" onclick="deleteRole(<?= $role['ROLE_ID'] ?>)"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="roleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle" style="margin:0; font-size:1.2rem; color:#f59e0b;">Add Role</h2>
        </div>
        <form id="roleForm" onsubmit="submitForm(event)">
            <div class="modal-body">
                <input type="hidden" id="role_id" name="role_id">
                <input type="hidden" id="action_type" name="action_type" value="add">

                <div class="form-group">
                    <label>Role Name *</label>
                    <input type="text" id="role_name" name="role_name" placeholder="e.g. Farm Manager" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="description" name="description" rows="3" placeholder="Brief details about the responsibilities..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-save">Save Role</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(mode, btn = null) {
        document.getElementById('roleForm').reset();
        document.getElementById('action_type').value = mode;

        if (mode === 'add') {
            document.getElementById('modalTitle').textContent = 'Add Role';
        } else {
            document.getElementById('modalTitle').textContent = 'Edit Role';
            
            const tr = btn.closest('tr');
            document.getElementById('role_id').value = tr.dataset.id;
            document.getElementById('role_name').value = tr.dataset.name;
            document.getElementById('description').value = tr.dataset.desc;
        }
        document.getElementById('roleModal').classList.add('show');
    }

    function closeModal() {
        document.getElementById('roleModal').classList.remove('show');
    }

    function submitForm(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const action = formData.get('action_type') === 'add' ? '../process/addRole.php' : '../process/editRole.php';

        fetch(action, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert("Error: " + data.message);
            }
        });
    }

    function deleteRole(id) {
        if(!confirm("Are you sure you want to remove this role?")) return;
        
        const fd = new FormData();
        fd.append('role_id', id);

        fetch('../process/removeRole.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert("Role removed.");
                location.reload();
            } else {
                alert("Error: " + data.message);
            }
        });
    }
</script>

</body>
</html>