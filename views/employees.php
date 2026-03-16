<?php
// views/employees.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "admin_dashboard";
include '../config/Connection.php';
include '../security/checkAccess.php';
checkAccess('employee_list'); 
include '../common/navbar.php';
include '../common/chat_support.php';

// Fetch Employees
$employees = [];
$roles = [];
try {
    // Get Employees
    $stmt = $conn->query("SELECT * FROM employees ORDER BY FULL_NAME ASC");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get Defined Farm Roles for the dropdown
    $role_stmt = $conn->query("SELECT ROLE_NAME FROM farm_roles ORDER BY ROLE_NAME ASC");
    $roles = $role_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Failed to load data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; margin: 0; padding-bottom: 40px; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        
        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #94a3b8; font-weight: 600; margin-bottom: 20px; transition: 0.2s; }
        .back-link:hover { color: white; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .title { font-size: 2rem; color: #0ea5e9; font-weight: bold; margin:0; }
        .btn-add { background: #0ea5e9; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-add:hover { background: #0284c7; }

        /* Table */
        .table-wrap { background: rgba(30, 41, 59, 0.5); border-radius: 12px; overflow: hidden; border: 1px solid #334155; }
        table { width: 100%; border-collapse: collapse; }
        th { background: rgba(15, 23, 42, 0.9); color: #0ea5e9; text-align: left; padding: 1rem; text-transform: uppercase; font-size: 0.8rem; border-bottom: 1px solid #334155; }
        td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; }
        
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; }
        .b-active { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
        .b-inactive { background: rgba(239, 68, 68, 0.2); color: #f87171; }

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
        
        .form-row { display: flex; gap: 10px; }
        .form-row .form-group { flex: 1; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.85rem; color: #94a3b8; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 6px; box-sizing: border-box; outline: none; transition: 0.2s;}
        .form-group input:focus, .form-group select:focus { border-color: #0ea5e9; }
        
        .btn-cancel { background: transparent; color: #cbd5e1; border: none; cursor: pointer; padding: 8px 16px; }
        .btn-save { background: #0ea5e9; color: white; border: none; border-radius: 6px; padding: 8px 16px; cursor: pointer; }
    </style>
</head>
<body>

<div class="container">
    <a href="admin_dashboard.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    
    <div class="header">
        <h1 class="title">Employee Management</h1>
        <button class="btn-add" onclick="openModal('add')">+ Add Employee</button>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Emp Code</th>
                    <th>Name</th>
                    <th>Position / Role</th>
                    <th>Contact No.</th>
                    <th>Hire Date</th>
                    <th>Status</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($employees)): ?>
                    <tr><td colspan="7" style="text-align:center; padding: 2rem;">No employees found.</td></tr>
                <?php else: ?>
                    <?php foreach($employees as $emp): ?>
                        <tr data-id="<?= $emp['EMPLOYEE_ID'] ?>" 
                            data-code="<?= htmlspecialchars($emp['EMPLOYEE_CODE']) ?>"
                            data-name="<?= htmlspecialchars($emp['FULL_NAME']) ?>"
                            data-pos="<?= htmlspecialchars($emp['POSITION']) ?>"
                            data-contact="<?= htmlspecialchars($emp['CONTACT_NO']) ?>"
                            data-hire="<?= $emp['HIRE_DATE'] ?>"
                            data-status="<?= $emp['STATUS'] ?>">
                            <td style="color:#0ea5e9; font-weight:bold; font-family:monospace;"><?= htmlspecialchars($emp['EMPLOYEE_CODE']) ?></td>
                            <td style="font-weight:bold; color:#fff;"><?= htmlspecialchars($emp['FULL_NAME']) ?></td>
                            <td style="color:#94a3b8;"><?= htmlspecialchars($emp['POSITION']) ?></td>
                            <td><?= htmlspecialchars($emp['CONTACT_NO'] ?? 'N/A') ?></td>
                            <td><?= $emp['HIRE_DATE'] ? date('m/d/Y', strtotime($emp['HIRE_DATE'])) : '-' ?></td>
                            <td>
                                <span class="badge <?= $emp['STATUS'] == 'Active' ? 'b-active' : 'b-inactive' ?>">
                                    <?= $emp['STATUS'] ?>
                                </span>
                            </td>
                            <td class="actions" style="text-align:center;">
                                <button class="btn-edit" onclick="openModal('edit', this)"><i class="fa-solid fa-pen-to-square"></i></button>
                                <button class="btn-delete" onclick="deleteEmployee(<?= $emp['EMPLOYEE_ID'] ?>)"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="empModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle" style="margin:0; font-size:1.2rem; color:#0ea5e9;">Add Employee</h2>
        </div>
        <form id="empForm" onsubmit="submitForm(event)">
            <div class="modal-body">
                <input type="hidden" id="emp_id" name="employee_id">
                <input type="hidden" id="action_type" name="action_type" value="add">

                <div class="form-group">
                    <label>Employee Code (ID) *</label>
                    <input type="number" id="employee_code" name="employee_code" placeholder="e.g. 1001" required>
                </div>
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>
                
                <div class="form-group">
                    <label>Position / Role *</label>
                    <select id="position" name="position" required>
                        <option value="">Select Role...</option>
                        <?php foreach($roles as $role): ?>
                            <option value="<?= htmlspecialchars($role['ROLE_NAME']) ?>">
                                <?= htmlspecialchars($role['ROLE_NAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" id="contact_no" name="contact_no">
                    </div>
                    <div class="form-group">
                        <label>Hire Date</label>
                        <input type="date" id="hire_date" name="hire_date">
                    </div>
                </div>

                <div class="form-group" id="statusGroup" style="display:none;">
                    <label>Status</label>
                    <select id="status" name="status">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-save">Save Employee</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Initialize Flatpickr for mm/dd/yyyy visual input while retaining YYYY-MM-DD backend submission
    const fpDatePicker = flatpickr("#hire_date", {
        dateFormat: "Y-m-d", // Value submitted to PHP
        altInput: true,      // Create dummy input for display
        altFormat: "m/d/Y",  // Visual display format
        allowInput: true
    });

    function openModal(mode, btn = null) {
        document.getElementById('empForm').reset();
        document.getElementById('action_type').value = mode;
        fpDatePicker.clear(); // Reset datepicker

        if (mode === 'add') {
            document.getElementById('modalTitle').textContent = 'Add Employee';
            document.getElementById('statusGroup').style.display = 'none';
            // SET DEFAULT DATE TO TODAY
            fpDatePicker.setDate("today"); 
        } else {
            document.getElementById('modalTitle').textContent = 'Edit Employee';
            document.getElementById('statusGroup').style.display = 'block';
            
            const tr = btn.closest('tr');
            document.getElementById('emp_id').value = tr.dataset.id;
            document.getElementById('employee_code').value = tr.dataset.code;
            document.getElementById('full_name').value = tr.dataset.name;
            document.getElementById('position').value = tr.dataset.pos;
            document.getElementById('contact_no').value = tr.dataset.contact;
            document.getElementById('status').value = tr.dataset.status;
            
            // Use flatpickr api to set the date value properly
            if(tr.dataset.hire) {
                fpDatePicker.setDate(tr.dataset.hire);
            }
        }
        document.getElementById('empModal').classList.add('show');
    }

    function closeModal() {
        document.getElementById('empModal').classList.remove('show');
    }

    function submitForm(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const action = formData.get('action_type') === 'add' ? '../process/addEmployee.php' : '../process/editEmployee.php';

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

    function deleteEmployee(id) {
        if(!confirm("Are you sure you want to remove this employee?")) return;
        
        const fd = new FormData();
        fd.append('employee_id', id);

        fetch('../process/removeEmployee.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert("Employee removed successfully.");
                location.reload();
            } else {
                alert("Error: " + data.message);
            }
        });
    }
</script>

</body>
</html>