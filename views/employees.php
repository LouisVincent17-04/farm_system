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
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    
    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(56,189,248,0.5); /* Sky Blue Accent */
            --sky:            #38bdf8;
            --sky-dim:        rgba(56,189,248,0.12);
            --sky-glow:       rgba(56,189,248,0.25);
            --green:          #22c55e;
            --green-dim:      rgba(34,197,94,0.12);
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-sm:      6px;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --shadow-sm:      0 1px 3px rgba(0,0,0,0.4);
            --shadow-md:      0 4px 16px rgba(0,0,0,0.4);
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
            padding-bottom: 60px;
            background-image:
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(56,189,248,0.06) 0%, transparent 60%),
                radial-gradient(ellipse 40% 30% at 85% 10%, rgba(56,189,248,0.04) 0%, transparent 50%);
        }

        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR & HEADER ─── */
        .top-bar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap;
        }
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
            color: var(--sky); background: var(--sky-dim); border: 1px solid rgba(56,189,248,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap;
        }
        .header-info h1 {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin: 0 0 0.25rem 0;
        }
        .header-info h1 span {
            background: linear-gradient(135deg, var(--sky), #0ea5e9);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .header-info p { color: var(--text-secondary); font-size: 0.9rem; margin: 0; }

        /* ─── BUTTONS ─── */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 20px; border-radius: var(--radius-md); font-size: 0.9rem;
            font-weight: 600; font-family: var(--font); border: 1px solid transparent;
            cursor: pointer; transition: all var(--transition); text-decoration: none; white-space: nowrap;
        }
        .btn-primary { background: var(--sky); color: #000; border-color: var(--sky); }
        .btn-primary:hover { background: #0ea5e9; box-shadow: 0 0 16px var(--sky-glow); color: #fff; transform: translateY(-2px); }
        
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        /* ─── FILTER & SEARCH BAR ─── */
        .filters-wrapper {
            display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;
            background: var(--bg-surface); border: 1px solid var(--border);
            padding: 1rem; border-radius: var(--radius-xl); align-items: center;
        }
        .search-container { position: relative; flex: 1; min-width: 250px; display: flex; align-items: center; }
        .search-icon {
            position: absolute; left: 1rem; color: var(--text-muted); width: 18px; height: 18px; pointer-events: none;
        }
        .search-input {
            width: 100%; padding: 12px 12px 12px 2.8rem; background: var(--bg-elevated);
            border: 1px solid var(--border); border-radius: var(--radius-md); color: var(--text-primary);
            font-size: 0.9rem; font-family: var(--font); outline: none; transition: all var(--transition);
        }
        .search-input::placeholder { color: var(--text-muted); }
        .search-input:focus { border-color: var(--sky); box-shadow: 0 0 0 3px var(--sky-glow); background: var(--bg-hover); }

        .sort-select {
            width: auto; min-width: 200px; padding: 12px 36px 12px 12px;
            background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-primary); font-size: 0.9rem; font-family: var(--font);
            border-radius: var(--radius-md); outline: none; transition: all var(--transition);
            appearance: none; cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center;
        }
        .sort-select:focus { border-color: var(--sky); box-shadow: 0 0 0 3px var(--sky-glow); background: var(--bg-hover); }
        .sort-select option { background: #1e293b; color: var(--text-primary); }

        /* ─── TABLE ─── */
        .table-wrap {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 12px 16px; text-align: left;
            border-bottom: 1px solid var(--border); white-space: nowrap;
        }
        tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,0.02); }
        td { padding: 12px 16px; font-size: 0.85rem; color: var(--text-primary); vertical-align: middle; }

        /* Badges */
        .badge {
            display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px;
            border-radius: 99px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase;
        }
        .b-active   { background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.2); }
        .b-inactive { background: var(--red-dim);   color: var(--red);   border: 1px solid rgba(248,113,113,0.2); }

        /* Typography Utilities */
        .col-name { font-weight: 600; color: var(--text-primary); font-size: 0.95rem; }
        .val-mono { font-family: var(--font-mono); color: var(--sky); font-weight: 600; font-size: 0.85rem;}
        .val-date { color: var(--text-secondary); font-size: 0.85rem; }

        /* Action Buttons */
        .actions { display: flex; justify-content: center; gap: 8px; }
        .action-btn {
            width: 32px; height: 32px; border-radius: var(--radius-sm);
            border: 1px solid var(--border); background: var(--bg-elevated);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all var(--transition); color: var(--text-secondary); font-size: 0.85rem;
        }
        .action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .action-btn.edit:hover { color: var(--sky); border-color: var(--sky); }
        .action-btn.delete:hover { color: var(--red); border-color: var(--red); }

        /* ─── MODAL ─── */
        .modal {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;
            padding: 1rem; overflow-y: auto;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 550px;
            max-height: 90vh; display: flex; flex-direction: column; box-shadow: var(--shadow-md);
            margin: auto;
        }
        .modal-header {
            padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--text-primary); }
        
        .modal-body { padding: 1.5rem; overflow-y: auto; }
        .modal-footer {
            padding: 1.25rem 1.5rem; border-top: 1px solid var(--border);
            display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated);
        }

        /* Form Layouts in Modal */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1rem; }
        .form-group:last-child { margin-bottom: 0; }
        .form-label {
            font-size: 0.72rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.06em; color: var(--text-secondary); display: flex; align-items: center; gap: 5px;
        }
        .form-control {
            width: 100%; padding: 10px 12px; height: 42px; background: var(--bg-elevated);
            border: 1px solid var(--border); color: var(--text-primary);
            border-radius: var(--radius-md); font-size: 0.9rem; font-family: var(--font);
            outline: none; transition: border-color var(--transition), box-shadow var(--transition);
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center;
            padding-right: 36px; cursor: pointer;
        }
        .form-control:focus { border-color: var(--sky); box-shadow: 0 0 0 3px var(--sky-glow); background: var(--bg-hover); }

        .alert {
            padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 1.5rem;
            display: none; font-size: 0.9rem; text-align: center; font-weight: 600;
        }
        .alert.success { background: var(--green-dim); border: 1px solid rgba(34,197,94,0.3); color: var(--green); }
        .alert.error { background: var(--red-dim); border: 1px solid rgba(248,113,113,0.3); color: var(--red); }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-buttons { width: 100%; }
            .header-buttons .btn { width: 100%; }
            .filters-wrapper { flex-direction: column; }
            .sort-select { width: 100%; }
            .form-row { grid-template-columns: 1fr; gap: 1rem; }
            .modal-footer { flex-direction: column; }
            .modal-footer .btn { width: 100%; justify-content: center; }

            /* Mobile Table to Cards */
            .table-wrap { border: none; background: transparent; overflow: visible; }
            .table { min-width: 0; display: block; }
            .table thead { display: none; }
            .table tbody { display: block; width: 100%; }
            .table tr { 
                display: block; background: var(--bg-surface); 
                border: 1px solid var(--border); border-radius: var(--radius-lg); 
                margin-bottom: 1rem; padding: 1.25rem; box-shadow: var(--shadow-sm);
            }
            .table td { 
                display: flex; justify-content: space-between; align-items: center; 
                padding: 0.75rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); 
                text-align: right; white-space: normal;
            }
            .table td:last-child { border-bottom: none; }
            .table td::before { 
                content: attr(data-label); font-weight: 700; color: var(--text-muted); 
                font-size: 0.75rem; text-transform: uppercase; margin-right: 1rem; text-align: left; flex-shrink: 0;
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
        <span class="page-badge"><i class="fa-solid fa-users"></i> Personnel</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1>Employee <span>Management</span></h1>
            <p>Manage farm staff, roles, and administrative access.</p>
        </div>
        <div class="header-buttons">
            <button class="btn btn-primary" onclick="openModal('add')">
                <i class="fa-solid fa-user-plus"></i> Add Employee
            </button>
        </div>
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

    <div class="table-card">
        <div class="table-wrap">
            <table class="table">
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
                        <tr id="empty-state-row">
                            <td colspan="7" style="text-align:center; padding: 4rem 2rem; color: var(--text-muted);">
                                <i class="fa-solid fa-user-slash" style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 1rem; display:block;"></i>
                                No employees found.
                            </td>
                        </tr>
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
                                
                                <td data-label="Emp Code" class="val-mono"><?= htmlspecialchars($emp['EMPLOYEE_CODE']) ?></td>
                                <td data-label="Name" class="col-name"><?= htmlspecialchars($emp['FULL_NAME']) ?></td>
                                <td data-label="Position / Role" style="color:var(--text-secondary);"><?= htmlspecialchars($emp['POSITION']) ?></td>
                                <td data-label="Contact No."><?= htmlspecialchars($emp['CONTACT_NO'] ?? 'N/A') ?></td>
                                <td data-label="Hire Date" class="val-date"><?= $emp['HIRE_DATE'] ? date('m/d/Y', strtotime($emp['HIRE_DATE'])) : '-' ?></td>
                                <td data-label="Status">
                                    <span class="badge <?= $emp['STATUS'] == 'Active' ? 'b-active' : 'b-inactive' ?>">
                                        <?= $emp['STATUS'] ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="actions">
                                        <button class="action-btn edit" onclick="openModal('edit', this)" title="Edit">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button class="action-btn delete" onclick="deleteEmployee(<?= $emp['EMPLOYEE_ID'] ?>)" title="Delete">
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

<div id="empModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add Employee</h2>
            <button class="action-btn" onclick="closeModal()" style="border:none; background:transparent;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="empForm" onsubmit="submitForm(event)">
            <div class="modal-body">
                <div id="add-alert" class="alert"></div>
                <input type="hidden" id="emp_id" name="employee_id">
                <input type="hidden" id="action_type" name="action_type" value="add">

                <div class="form-group">
                    <label class="form-label">Employee Code (ID) *</label>
                    <input type="number" id="employee_code" name="employee_code" class="form-control" placeholder="e.g. 1001" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Enter Full Name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Position / Role *</label>
                    <select id="position" name="position" class="form-control" required>
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
                        <label class="form-label">Contact Number</label>
                        <input type="text" id="contact_no" name="contact_no" class="form-control" placeholder="09xxxxxxxxx">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hire Date</label>
                        <input type="date" id="hire_date" name="hire_date" class="form-control date-picker">
                    </div>
                </div>

                <div class="form-group" id="statusGroup" style="display:none;">
                    <label class="form-label">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-save">Save Employee</button>
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
                emptyRow.innerHTML = '<td colspan="7" style="text-align:center; padding: 4rem 2rem; color: var(--text-muted);"><i class="fa-solid fa-user-slash" style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 1rem; display:block;"></i>No employees found matching your search.</td>';
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
            document.getElementById('statusGroup').style.display = 'flex';
            
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
                const alertEl = document.getElementById('add-alert');
                alertEl.textContent = data.message;
                alertEl.className = 'alert success';
                alertEl.style.display = 'block';
                setTimeout(() => location.reload(), 1000);
            } else {
                const alertEl = document.getElementById('add-alert');
                alertEl.textContent = "Error: " + data.message;
                alertEl.className = 'alert error';
                alertEl.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Employee';
            }
        })
        .catch(err => {
            const alertEl = document.getElementById('add-alert');
            alertEl.textContent = "System Error";
            alertEl.className = 'alert error';
            alertEl.style.display = 'block';
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