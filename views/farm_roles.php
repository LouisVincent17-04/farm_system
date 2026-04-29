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

if($_SESSION['user']['USER_TYPE'] < 3)
{
    echo "<script>alert('Access denied.'); window.location.href = 'admin_dashboard.php';</script>";
    exit();
}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Farm Roles Management</title>
    
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
            --border-active:  rgba(245,158,11,0.5); /* Gold Accent */
            --gold:           #f59e0b;
            --gold-dim:       rgba(245,158,11,0.12);
            --gold-glow:      rgba(245,158,11,0.25);
            --blue:           #38bdf8;
            --red:            #f87171;
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-md:      10px;
            --radius-xl:      20px;
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.18s cubic-bezier(0.4,0,0.2,1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(245,158,11,0.05) 0%, transparent 60%);
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── HEADER ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; }
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500;
            padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); transition: all var(--transition);
        }
        .back-link:hover { color: var(--text-primary); border-color: var(--border-active); background: var(--bg-hover); }

        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap;
        }
        .header-info h1 {
            font-size: clamp(1.8rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .header-info h1 span {
            background: linear-gradient(135deg, var(--gold), #d97706);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; }

        /* ─── BUTTONS ─── */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 20px; border-radius: var(--radius-md); font-size: 0.9rem;
            font-weight: 600; font-family: var(--font); border: 1px solid transparent;
            cursor: pointer; transition: all var(--transition); text-decoration: none; white-space: nowrap;
        }
        .btn-primary { background: var(--gold); color: #000; }
        .btn-primary:hover { background: #fbbf24; box-shadow: 0 0 16px var(--gold-glow); transform: translateY(-1px); }
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
        }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 14px 16px; text-align: left;
            border-bottom: 1px solid var(--border);
        }
        tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,0.02); }
        td { padding: 14px 16px; font-size: 0.9rem; color: var(--text-primary); vertical-align: middle; }

        .col-name { font-weight: 600; color: #fff; }
        .col-desc { color: var(--text-secondary); font-size: 0.85rem; line-height: 1.5; }

        /* Actions */
        .actions { display: flex; justify-content: center; gap: 8px; }
        .action-btn {
            width: 32px; height: 32px; border-radius: 6px;
            border: 1px solid var(--border); background: var(--bg-elevated);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all var(--transition); color: var(--text-secondary);
        }
        .action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .action-btn.edit:hover { color: var(--blue); border-color: var(--blue); }
        .action-btn.delete:hover { color: var(--red); border-color: var(--red); }

        /* ─── MODAL ─── */
        .modal {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;
            padding: 1rem;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); overflow: hidden;
        }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .modal-header h2 { margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--gold); }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); }

        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.25rem; }
        .form-group:last-child { margin-bottom: 0; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        .form-control {
            width: 100%; padding: 10px 12px; background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: 8px; font-size: 0.9rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .form-control:focus { border-color: var(--gold); box-shadow: 0 0 0 3px var(--gold-glow); }
        textarea.form-control { resize: none; min-height: 100px; }

        /* Character Counter */
        .char-counter { text-align: right; font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-buttons { width: 100%; }
            .header-buttons .btn { width: 100%; }

            .table-wrap { border: none; background: transparent; }
            table, thead, tbody, th, td, tr { display: block; }
            thead { display: none; }
            tbody tr { 
                background: var(--bg-surface); border: 1px solid var(--border); 
                border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem;
            }
            td { 
                display: flex; justify-content: space-between; align-items: center; 
                padding: 0.6rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right;
            }
            td:last-child { border-bottom: none; }
            td::before { 
                content: attr(data-label); font-weight: 700; color: var(--text-muted); 
                font-size: 0.75rem; text-transform: uppercase; text-align: left;
            }
            .actions { justify-content: flex-end; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="top-bar">
        <a href="admin_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-user-shield"></i> Administration</span>
    </div>
    
    <div class="page-header">
        <div class="header-info">
            <h1>Farm <span>Roles</span> Setup</h1>
            <p>Define and manage access roles for farm personnel.</p>
        </div>
        <div class="header-buttons">
            <button class="btn btn-primary" onclick="openModal('add')">
                <i class="fa-solid fa-plus"></i> Add New Role
            </button>
        </div>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width: 30%;">Role Name</th>
                        <th>Description</th>
                        <th style="text-align:center; width: 15%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($roles)): ?>
                        <tr><td colspan="3" style="text-align:center; padding: 4rem 2rem; color: var(--text-muted);">No roles configured.</td></tr>
                    <?php else: ?>
                        <?php foreach($roles as $role): ?>
                            <tr data-id="<?= $role['ROLE_ID'] ?>" 
                                data-name="<?= htmlspecialchars($role['ROLE_NAME']) ?>"
                                data-desc="<?= htmlspecialchars($role['DESCRIPTION']) ?>">
                                <td data-label="Role Name" class="col-name"><?= htmlspecialchars($role['ROLE_NAME']) ?></td>
                                <td data-label="Description" class="col-desc"><?= htmlspecialchars($role['DESCRIPTION'] ?? 'No description provided') ?></td>
                                <td data-label="Actions">
                                    <div class="actions">
                                        <button class="action-btn edit" onclick="openModal('edit', this)" title="Edit Role">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button class="action-btn delete" onclick="deleteRole(<?= $role['ROLE_ID'] ?>)" title="Delete Role">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="roleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add Role</h2>
        </div>
        <form id="roleForm" onsubmit="submitForm(event)">
            <div class="modal-body">
                <input type="hidden" id="role_id" name="role_id">
                <input type="hidden" id="action_type" name="action_type" value="add">

                <div class="form-group">
                    <label class="form-label">Role Name *</label>
                    <input type="text" id="role_name" name="role_name" class="form-control" placeholder="e.g. Farm Manager" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="Brief details about the responsibilities..." maxlength="250" oninput="updateCharCount(this)"></textarea>
                    <div id="charCount" class="char-counter">0 / 250</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Role</button>
            </div>
        </form>
    </div>
</div>

<script>
    function updateCharCount(textarea) {
        const currentLength = textarea.value.length;
        document.getElementById('charCount').textContent = `${currentLength} / 250`;
    }

    function openModal(mode, btn = null) {
        const form = document.getElementById('roleForm');
        form.reset();
        document.getElementById('action_type').value = mode;

        if (mode === 'add') {
            document.getElementById('modalTitle').textContent = 'Add Role';
            document.getElementById('charCount').textContent = '0 / 250';
        } else {
            document.getElementById('modalTitle').textContent = 'Edit Role';
            const tr = btn.closest('tr');
            document.getElementById('role_id').value = tr.dataset.id;
            document.getElementById('role_name').value = tr.dataset.name;
            
            const descField = document.getElementById('description');
            descField.value = tr.dataset.desc;
            document.getElementById('charCount').textContent = `${descField.value.length} / 250`;
        }
        document.getElementById('roleModal').classList.add('show');
    }

    function closeModal() {
        document.getElementById('roleModal').classList.remove('show');
    }

    // Close modal on outside click
    document.getElementById('roleModal').addEventListener('click', function(e) {
        if(e.target === this) closeModal();
    });

    function submitForm(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const action = formData.get('action_type') === 'add' ? '../process/addRole.php' : '../process/editRole.php';

        const submitBtn = e.target.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        fetch(action, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert("Error: " + data.message);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Role';
            }
        })
        .catch(err => {
            alert("System error occurred.");
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Role';
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
                location.reload();
            } else {
                alert("Error: " + data.message);
            }
        });
    }
</script>

</body>
</html>