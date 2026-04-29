<?php
// views/suppliers.php
$page = "admin_dashboard";
include '../config/Connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../security/checkAccess.php';
checkAccess('suppliers'); 

include '../common/navbar.php';
include '../common/chat_support.php';

if($_SESSION['user']['USER_TYPE'] < 3)
{
    echo "<script>alert('Access denied.'); window.location.href = 'admin_dashboard.php';</script>";
    exit();
}

// Fetch all suppliers
$suppliers = [];
try {
    $stmt = $conn->query("SELECT * FROM suppliers ORDER BY SUPPLIER_NAME ASC");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    // Silently handle error
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Supplier Management System</title>
    
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
            --border-active:  rgba(225,29,72,0.5); /* Rose Accent */
            --rose:           #e11d48;
            --rose-dim:       rgba(225,29,72,0.12);
            --rose-glow:      rgba(225,29,72,0.25);
            --green:          #22c55e;
            --green-dim:      rgba(34,197,94,0.12);
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(225,29,72,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; }
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
            color: var(--rose); background: var(--rose-dim); border: 1px solid rgba(225,29,72,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap;
        }
        .header-info h1 {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .header-info h1 span {
            background: linear-gradient(135deg, var(--rose), #be123c);
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
        .btn-primary { background: var(--rose); color: #fff; }
        .btn-primary:hover { background: #be123c; box-shadow: 0 0 16px var(--rose-glow); transform: translateY(-2px); }
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        /* ─── SEARCH ─── */
        .search-container { position: relative; margin-bottom: 1.5rem; }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; height: 18px; pointer-events: none; }
        .search-input {
            width: 100%; padding: 14px 14px 14px 2.8rem; background: var(--bg-surface);
            border: 1px solid var(--border); border-radius: var(--radius-lg);
            color: var(--text-primary); font-size: 1rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .search-input:focus { border-color: var(--rose); box-shadow: 0 0 0 3px var(--rose-glow); background: var(--bg-hover); }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
        }
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .table thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 14px 16px; text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .table tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        .table tbody tr:last-child { border-bottom: none; }
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }
        .table td { padding: 14px 16px; font-size: 0.9rem; color: var(--text-primary); vertical-align: middle; }

        .col-name { font-weight: 700; color: #fff; font-size: 1rem; }
        .val-mono { font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-secondary); }

        .badge {
            padding: 4px 10px; border-radius: 99px; font-size: 0.7rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; display: inline-block;
        }
        .badge.active   { background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.2); }
        .badge.inactive { background: var(--red-dim);   color: var(--red);   border: 1px solid rgba(248,113,113,0.2); }

        /* Actions */
        .actions { display: flex; gap: 8px; justify-content: center; }
        .action-btn {
            width: 32px; height: 32px; border-radius: 6px;
            border: 1px solid var(--border); background: var(--bg-elevated);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all var(--transition); color: var(--text-secondary);
        }
        .action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .action-btn.edit:hover { color: var(--blue); border-color: var(--blue); }
        .action-btn.delete:hover { color: var(--red); border-color: var(--red); }

        /* ─── MODALS ─── */
        .supplier-modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85);
            backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;
            padding: 1rem;
        }
        .supplier-modal-overlay.active { display: flex; }
        .supplier-modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 500px;
            box-shadow: var(--shadow-md); overflow: hidden;
            animation: modalZoom 0.2s ease-out;
        }
        @keyframes modalZoom { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        .supplier-modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .supplier-modal-title { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--rose); }
        .supplier-close-btn { background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; transition: color 0.2s; }
        .supplier-close-btn:hover { color: var(--red); }

        .modal-body { padding: 1.5rem; }
        .supplier-modal-actions {
            padding: 1.25rem 1.5rem; border-top: 1px solid var(--border);
            display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated);
        }

        .supplier-form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.25rem; }
        .supplier-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .supplier-form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        .supplier-form-input, .supplier-form-select {
            width: 100%; padding: 10px 12px; background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: 8px; font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .supplier-form-input:focus, .supplier-form-select:focus { border-color: var(--rose); box-shadow: 0 0 0 3px var(--rose-glow); }
        .supplier-form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-info { text-align: left; }
            .btn-add { width: 100%; justify-content: center; }

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
            .table td:last-child { border-bottom: none; justify-content: flex-end; padding-top: 1rem; }
            .table td::before { 
                content: attr(data-label); font-weight: 700; color: var(--text-muted); 
                font-size: 0.75rem; text-transform: uppercase; text-align: left; flex-shrink: 0; margin-right: 1rem;
            }
            
            /* Hide the data-label for the Company Name to make it a clean card header */
            .table td[data-label="Company"] { display: block; text-align: left; padding-bottom: 1rem; margin-bottom: 0.5rem; border-bottom: 1px dashed rgba(255,255,255,0.05); }
            .table td[data-label="Company"]::before { display: none; }
            
            .table td[data-label="Actions"] { border-top: 1px dashed var(--border); margin-top: 10px; }
            .table td[data-label="Actions"]::before { display: none; }
            .actions { justify-content: flex-end; width: 100%; }
            
            .supplier-form-row { grid-template-columns: 1fr; gap: 0; }
            .supplier-modal-actions { flex-direction: column-reverse; }
            .supplier-modal-actions button { width: 100%; justify-content: center; margin: 0; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <div class="top-bar">
        <a href="admin_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-truck-loading"></i> Supply Chain</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1>Supplier <span>Management</span></h1>
            <p>Maintain reliable partnerships with your farm vendors and logistics providers.</p>
        </div>
        <button class="btn btn-primary btn-add" onclick="openModal()">
            <i class="fa-solid fa-plus"></i> Add New Supplier
        </button>
    </div>

    <div class="search-container">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
        <input type="text" class="search-input" placeholder="Search by company name, contact person, or location..." onkeyup="filterTable()">
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Company Name</th>
                        <th>Contact Person</th>
                        <th>Contact Details</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th style="text-align: center; width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="supplier-table-body">
                    <?php if(empty($suppliers)): ?>
                        <tr id="empty-row">
                            <td colspan="6" style="text-align: center; padding: 4rem 2rem; color: var(--text-muted);">
                                <i class="fa-solid fa-building-circle-exclamation" style="font-size: 2.5rem; opacity: 0.2; margin-bottom: 1rem; display:block;"></i>
                                No suppliers found. Start by adding a new vendor.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($suppliers as $s): ?>
                            <tr>
                                <td data-label="Company" class="col-name"><?= htmlspecialchars($s['SUPPLIER_NAME']) ?></td>
                                <td data-label="Contact Person"><?= htmlspecialchars($s['CONTACT_PERSON'] ?: 'N/A') ?></td>
                                <td data-label="Number" class="val-mono"><?= htmlspecialchars($s['CONTACT_NUMBER'] ?: 'N/A') ?></td>
                                <td data-label="Email" class="val-mono" style="color:var(--rose);"><?= htmlspecialchars($s['EMAIL'] ?: 'N/A') ?></td>
                                <td data-label="Status">
                                    <span class="badge <?= strtolower($s['STATUS']) ?>">
                                        <?= htmlspecialchars($s['STATUS']) ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="actions">
                                        <button class="action-btn edit" onclick='openModal(<?= json_encode($s) ?>)' title="Edit Details"><i class="fa-solid fa-pen-to-square"></i></button>
                                        <button class="action-btn delete" onclick="deleteSupplier(<?= $s['SUPPLIER_ID'] ?>)" title="Remove Supplier"><i class="fa-solid fa-trash-can"></i></button>
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

<div class="supplier-modal-overlay" id="supplierModal">
    <div class="supplier-modal-content">
        <div class="supplier-modal-header">
            <h2 class="supplier-modal-title" id="modalTitle">Add Supplier</h2>
            <button class="supplier-close-btn" onclick="closeModal()">&times;</button>
        </div>
        <form id="supplierForm" onsubmit="saveSupplier(event)">
            <div class="modal-body">
                <input type="hidden" id="supplier_id" name="supplier_id">
                
                <div class="supplier-form-group">
                    <label class="supplier-form-label">Company / Supplier Name *</label>
                    <input type="text" id="supplier_name" name="supplier_name" class="supplier-form-input" required placeholder="e.g. AgriSupply Corp">
                </div>

                <div class="supplier-form-group">
                    <label class="supplier-form-label">Contact Person</label>
                    <input type="text" id="contact_person" name="contact_person" class="supplier-form-input" placeholder="e.g. John Smith">
                </div>
                
                <div class="supplier-form-row">
                    <div class="supplier-form-group">
                        <label class="supplier-form-label">Contact Number</label>
                        <input type="text" id="contact_number" name="contact_number" class="supplier-form-input" placeholder="09123456789">
                    </div>
                    <div class="supplier-form-group">
                        <label class="supplier-form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="supplier-form-input" placeholder="name@company.com">
                    </div>
                </div>

                <div class="supplier-form-group">
                    <label class="supplier-form-label">Office Address</label>
                    <input type="text" id="address" name="address" class="supplier-form-input" placeholder="City, Province, HQ location">
                </div>

                <div class="supplier-form-group" style="margin-bottom: 0;">
                    <label class="supplier-form-label">Operational Status</label>
                    <select id="status" name="status" class="supplier-form-select">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="supplier-modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveBtn">Save Vendor</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('supplierModal');
    const form = document.getElementById('supplierForm');

    function openModal(data = null) {
        modal.classList.add('active');
        if(data) {
            document.getElementById('modalTitle').innerText = "Edit Supplier Profile";
            document.getElementById('supplier_id').value = data.SUPPLIER_ID;
            document.getElementById('supplier_name').value = data.SUPPLIER_NAME;
            document.getElementById('contact_person').value = data.CONTACT_PERSON || '';
            document.getElementById('contact_number').value = data.CONTACT_NUMBER || '';
            document.getElementById('email').value = data.EMAIL || '';
            document.getElementById('address').value = data.ADDRESS || '';
            document.getElementById('status').value = data.STATUS;
        } else {
            document.getElementById('modalTitle').innerText = "Add New Supplier";
            form.reset();
            document.getElementById('supplier_id').value = "";
            document.getElementById('status').value = "Active";
        }
    }

    function closeModal() { modal.classList.remove('active'); }

    async function saveSupplier(e) {
        e.preventDefault();
        const btn = document.getElementById('saveBtn');
        const formData = new FormData(form);
        btn.disabled = true;
        btn.innerText = "Saving...";

        try {
            const response = await fetch('../process/saveSupplier.php', { method: 'POST', body: formData });
            const data = await response.json();

            if(data.success) {
                location.reload(); 
            } else {
                alert("Error: " + data.message);
                btn.disabled = false;
                btn.innerText = "Save Vendor";
            }
        } catch (error) {
            alert("A system error occurred. Please check the console.");
            btn.disabled = false;
            btn.innerText = "Save Vendor";
        }
    }

    async function deleteSupplier(supplierId) {
        if (!confirm("Are you sure you want to delete this supplier? This action cannot be undone.")) return;

        try {
            const formData = new FormData();
            formData.append('supplier_id', supplierId);
            const response = await fetch('../process/deleteSupplier.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
            }
        } catch (error) {
            alert("System Error. Check console.");
        }
    }

    function filterTable() {
        const term = document.querySelector('.search-input').value.toLowerCase();
        const rows = document.querySelectorAll('#supplier-table-body tr:not(#empty-row)');
        let count = 0;

        rows.forEach(row => {
            const match = row.innerText.toLowerCase().includes(term);
            row.style.display = match ? '' : 'none';
            if(match) count++;
        });

        const empty = document.getElementById('empty-row');
        if(empty) empty.style.display = count === 0 ? '' : 'none';
    }

    window.onclick = function(e) { if (e.target == modal) closeModal(); }
</script>

</body>
</html>