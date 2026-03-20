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

if($_SESSION['user']['USER_TYPE'] < 3)
{
    echo "<script>alert('Access denied.'); window.location.href = 'admin_dashboard.php';</script>";
    exit();
}

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
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; margin: 0; padding-bottom: 40px; min-height: 100vh;}
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        
        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #94a3b8; font-weight: 600; margin-bottom: 20px; transition: 0.2s; }
        .back-link:hover { color: white; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;}
        .title { font-size: 2rem; color: #0ea5e9; font-weight: bold; margin:0; }
        .btn-add { background: #0ea5e9; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; transition: background 0.2s; display: flex; align-items: center; gap: 8px;}
        .btn-add:hover { background: #0284c7; }

        /* Filters & Sort */
        .filters-wrapper { display: flex; gap: 15px; margin-bottom: 2rem; flex-wrap: wrap; }
        .search-container { position: relative; flex: 1; min-width: 250px; }
        .search-input { width: 100%; padding: 1rem 1rem 1rem 3rem; background: rgba(30, 41, 59, 0.5); border: 1px solid #334155; border-radius: 0.5rem; color: white; font-size: 1rem; backdrop-filter: blur(10px); box-sizing: border-box; }
        .search-input::placeholder { color: #94a3b8; }
        .search-input:focus { outline: none; border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1); }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 20px; height: 20px; }
        
        .sort-select {
            width: auto; min-width: 220px; padding: 1rem; border-radius: 0.5rem;
            background: rgba(30, 41, 59, 0.5); border: 1px solid #334155;
            color: white; font-size: 1rem; outline: none; transition: border-color 0.2s;
            backdrop-filter: blur(10px); cursor: pointer;
        }
        .sort-select:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1); }
        .sort-select option { background: #1e293b; color: white; }

        /* Table */
        .table-wrap { background: rgba(30, 41, 59, 0.5); border-radius: 12px; overflow: hidden; border: 1px solid #334155; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { background: rgba(15, 23, 42, 0.9); color: #0ea5e9; text-align: left; padding: 1rem; text-transform: uppercase; font-size: 0.8rem; border-bottom: 1px solid #334155; }
        td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; }
        
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; }
        .b-active { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
        .b-inactive { background: rgba(239, 68, 68, 0.2); color: #f87171; }

        .actions { display: flex; justify-content: center; align-items: center; }
        .actions button { background: transparent; border: none; font-size: 1.1rem; margin: 0 5px; cursor: pointer; transition: 0.2s; }
        .btn-edit { color: #3b82f6; } .btn-edit:hover { color: #60a5fa; transform: scale(1.1); }
        .btn-delete { color: #ef4444; } .btn-delete:hover { color: #f87171; transform: scale(1.1); }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; z-index: 1000; padding: 1rem; box-sizing: border-box;}
        .modal.show { display: flex; }
        .modal-content { background: #1e293b; width: 100%; max-width: 450px; border-radius: 12px; border: 1px solid #334155; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);}
        .modal-header { padding: 1.5rem; border-bottom: 1px solid #334155; }
        .modal-body { padding: 1.5rem; max-height: 70vh; overflow-y: auto; }
        .modal-footer { padding: 1.5rem; border-top: 1px solid #334155; display: flex; justify-content: flex-end; gap: 10px; }
        
        .form-row { display: flex; gap: 10px; }
        .form-row .form-group { flex: 1; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.85rem; color: #94a3b8; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 6px; box-sizing: border-box; outline: none; transition: 0.2s;}
        .form-group input:focus, .form-group select:focus { border-color: #0ea5e9; }
        
        .btn-cancel { background: transparent; color: #cbd5e1; border: 1px solid #475569; cursor: pointer; padding: 8px 16px; border-radius: 6px; transition: 0.2s; }
        .btn-cancel:hover { background: rgba(255,255,255,0.05); color: white; }
        .btn-save { background: #0ea5e9; color: white; border: none; border-radius: 6px; padding: 8px 16px; cursor: pointer; font-weight: 600; transition: 0.2s; }
        .btn-save:hover { background: #0284c7; }

        @media (max-width: 768px) {
            .filters-wrapper { flex-direction: column; }
            .sort-select { width: 100%; }
            .form-row { flex-direction: column; gap: 0; }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="admin_dashboard.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    
    <div class="header">
        <h1 class="title">Employee Management</h1>
        <button class="btn-add" onclick="openModal('add')"><i class="fa-solid fa-plus"></i> Add Employee</button>
    </div>

    <div class="filters-wrapper">
        <div class="search-container">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" class="search-input" placeholder="Search employees by name, code, or role..." onkeyup="filterTable()">
        </div>
        
        <select class="sort-select" onchange="sortDropdown(this.value)">
            <option value="name_asc">Sort: Name (A-Z)</option>
            <option value="name_desc">Sort: Name (Z-A)</option>
            <option value="date_desc">Sort: Newest Hired</option>
            <option value="date_asc">Sort: Oldest Hired</option>
        </select>
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
            <tbody id="employee-table-body">
                <?php if(empty($employees)): ?>
                    <tr id="empty-state-row"><td colspan="7" style="text-align:center; padding: 3rem; color: #94a3b8;">No employees found.</td></tr>
                <?php else: ?>
                    <?php foreach($employees as $emp): ?>
                        <tr class="emp-row"
                            data-id="<?= $emp['EMPLOYEE_ID'] ?>" 
                            data-code="<?= htmlspecialchars($emp['EMPLOYEE_CODE']) ?>"
                            data-name="<?= htmlspecialchars(strtolower($emp['FULL_NAME'])) ?>"
                            data-pos="<?= htmlspecialchars(strtolower($emp['POSITION'])) ?>"
                            data-contact="<?= htmlspecialchars($emp['CONTACT_NO']) ?>"
                            data-hire="<?= $emp['HIRE_DATE'] ?>"
                            data-timestamp="<?= $emp['HIRE_DATE'] ? strtotime($emp['HIRE_DATE']) : 0 ?>"
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
                            <td class="actions">
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
                    <input type="text" id="full_name" name="full_name" placeholder="Enter Full Name" required>
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
                        <input type="text" id="contact_no" name="contact_no" placeholder="09xxxxxxxxx">
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
    // --- SORTING LOGIC ---
    function sortDropdown(val) {
        const tbody = document.getElementById('employee-table-body');
        const rows = Array.from(tbody.querySelectorAll('.emp-row'));
        
        rows.sort((a, b) => {
            const nameA = a.dataset.name || '';
            const nameB = b.dataset.name || '';
            const dateA = parseInt(a.dataset.timestamp) || 0;
            const dateB = parseInt(b.dataset.timestamp) || 0;

            if (val === 'name_asc') return nameA.localeCompare(nameB);
            if (val === 'name_desc') return nameB.localeCompare(nameA);
            if (val === 'date_desc') return dateB - dateA;
            if (val === 'date_asc') return dateA - dateB;
        });
        
        // Re-append sorted rows
        rows.forEach(row => tbody.appendChild(row));
    }

    // --- SEARCH / FILTER LOGIC ---
    function filterTable() {
        const searchTerm = document.querySelector('.search-input').value.toLowerCase();
        const rows = document.querySelectorAll('.emp-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const name = row.dataset.name || '';
            const code = row.dataset.code ? row.dataset.code.toLowerCase() : '';
            const pos = row.dataset.pos || '';
            
            if (name.includes(searchTerm) || code.includes(searchTerm) || pos.includes(searchTerm)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        checkEmptyState(visibleCount);
    }

    function checkEmptyState(visibleCount) {
        const tbody = document.getElementById('employee-table-body');
        let emptyRow = document.getElementById('empty-state-row');
        
        if (visibleCount === 0) {
            if (!emptyRow) {
                emptyRow = document.createElement('tr');
                emptyRow.id = 'empty-state-row';
                emptyRow.innerHTML = '<td colspan="7" style="text-align:center; padding: 3rem; color: #94a3b8;">No employees found matching your search.</td>';
                tbody.appendChild(emptyRow);
            }
            emptyRow.style.display = '';
        } else {
            if (emptyRow) emptyRow.style.display = 'none';
        }
    }


    // --- MODAL & FORM LOGIC ---
    const fpDatePicker = flatpickr("#hire_date", {
        dateFormat: "Y-m-d", // Value submitted to PHP
        altInput: true,      // Create dummy input for display
        altFormat: "m/d/Y",  // Visual display format
        allowInput: true
    });

    function openModal(mode, btn = null) {
        document.getElementById('empForm').reset();
        document.getElementById('action_type').value = mode;
        fpDatePicker.clear();

        if (mode === 'add') {
            document.getElementById('modalTitle').textContent = 'Add Employee';
            document.getElementById('statusGroup').style.display = 'none';
            fpDatePicker.setDate("today"); 
        } else {
            document.getElementById('modalTitle').textContent = 'Edit Employee';
            document.getElementById('statusGroup').style.display = 'block';
            
            const tr = btn.closest('tr');
            document.getElementById('emp_id').value = tr.dataset.id;
            document.getElementById('employee_code').value = tr.dataset.code;
            
            // To properly capitalize names during edit loading
            const rawName = tr.dataset.name.replace(/\b\w/g, l => l.toUpperCase()); 
            document.getElementById('full_name').value = rawName;
            
            document.getElementById('position').value = tr.dataset.pos.replace(/\b\w/g, l => l.toUpperCase());
            document.getElementById('contact_no').value = tr.dataset.contact;
            document.getElementById('status').value = tr.dataset.status;
            
            if(tr.dataset.hire) {
                fpDatePicker.setDate(tr.dataset.hire);
            }
        }
        document.getElementById('empModal').classList.add('show');
    }

    function closeModal() {
        document.getElementById('empModal').classList.remove('show');
    }

    // Close modal when clicking outside
    document.getElementById('empModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    function submitForm(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const action = formData.get('action_type') === 'add' ? '../process/addEmployee.php' : '../process/editEmployee.php';

        const submitBtn = document.querySelector('.btn-save');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        fetch(action, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert("Error: " + data.message);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Employee';
            }
        })
        .catch(err => {
            alert("System Error");
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Employee';
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