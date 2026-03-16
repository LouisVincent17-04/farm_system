<?php
// views/suppliers.php
$page = "admin_dashboard";
include '../config/Connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../security/checkAccess.php';
// checkAccess('suppliers'); // Uncomment if you have specific access rules

include '../common/navbar.php';
include '../common/chat_support.php';

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Suppliers</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-title { font-size: 2rem; font-weight: bold; color: white; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #94a3b8; font-weight: 600; font-size: 1rem; margin-bottom: 1rem; transition: color 0.2s; }
        .back-link:hover { color: white; }

        .btn-add { background: linear-gradient(135deg, #f43f5e, #e11d48); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; white-space: nowrap; }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(225, 29, 72, 0.4); }

        /* Table Styling */
        .table-area { background: #1e293b; border-radius: 16px; border: 1px solid #475569; overflow: hidden; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .w-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .w-table th { background: #0f172a; padding: 15px; text-align: left; color: #94a3b8; font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid #334155; }
        .w-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
        .w-table tr:hover { background: rgba(255,255,255,0.02); }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; display: inline-block;}
        .badge.active { background: rgba(34, 197, 94, 0.1); color: #34d399; border: 1px solid rgba(34, 197, 94, 0.3); }
        .badge.inactive { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

        .action-btn { background: transparent; border: 1px solid #475569; color: #e2e8f0; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; transition: 0.2s;}
        .action-btn:hover { background: #334155; color: white;}

        /* Prefixed Modal Styling */
        .supplier-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 1000; overflow-y: auto; }
        .supplier-modal-overlay.active { display: flex; }
        .supplier-modal-content { background: #1e293b; border: 1px solid #475569; border-radius: 16px; width: 100%; max-width: 500px; padding: 2rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); position: relative; }
        .supplier-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .supplier-modal-title { font-size: 1.25rem; font-weight: bold; color: white; }
        .supplier-close-btn { background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; transition: color 0.2s; }
        .supplier-close-btn:hover { color: white; }
        
        .supplier-form-group { margin-bottom: 1rem; }
        .supplier-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .supplier-form-label { display: block; color: #94a3b8; font-size: 0.85rem; margin-bottom: 0.5rem; font-weight: 600; }
        .supplier-form-input, .supplier-form-select { width: 100%; padding: 10px; background: #0f172a; border: 1px solid #475569; color: white; border-radius: 8px; font-size: 1rem; transition: border-color 0.2s; }
        .supplier-form-input:focus, .supplier-form-select:focus { border-color: #f43f5e; outline: none; }
        
        .supplier-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 2rem; }
        .supplier-btn-cancel { background: #475569; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: background 0.2s; }
        .supplier-btn-cancel:hover { background: #334155; }
        .supplier-btn-save { background: linear-gradient(135deg, #f43f5e, #e11d48); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
        .supplier-btn-save:hover { transform: translateY(-2px); }

        /* --- MOBILE RESPONSIVENESS --- */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .header-actions { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .page-title { font-size: 1.5rem; }
            .btn-add { width: 100%; text-align: center; }
            
            .supplier-modal-content { margin: 1rem; padding: 1.5rem; width: auto; max-height: 90vh; overflow-y: auto;}
            .supplier-form-row { grid-template-columns: 1fr; gap: 0; }
            .supplier-modal-actions { flex-direction: column; gap: 10px; }
            .supplier-btn-cancel, .supplier-btn-save { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="admin_dashboard.php" class="back-link">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Dashboard
    </a>

    <div class="header-actions">
        <h1 class="page-title">Supplier Management</h1>
        <button class="btn-add" onclick="openModal()">+ Add Supplier</button>
    </div>

    <div class="table-area">
        <div class="table-responsive">
            <table class="w-table">
                <thead>
                    <tr>
                        <th>Supplier / Company Name</th>
                        <th>Contact Person</th>
                        <th>Contact Number</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($suppliers)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 3rem; color: #64748b;">No suppliers found. Click "+ Add Supplier" to get started.</td></tr>
                    <?php else: ?>
                        <?php foreach($suppliers as $s): ?>
                            <tr>
                                <td style="font-weight: bold; color: white;"><?= htmlspecialchars($s['SUPPLIER_NAME']) ?></td>
                                <td><?= htmlspecialchars($s['CONTACT_PERSON'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($s['CONTACT_NUMBER'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($s['EMAIL'] ?: 'N/A') ?></td>
                                <td><span class="badge <?= strtolower($s['STATUS']) ?>"><?= htmlspecialchars($s['STATUS']) ?></span></td>
                                <td style="display: flex; gap: 8px;">
                                    <button class="action-btn" onclick='openModal(<?= json_encode($s) ?>)'>Edit</button>
                                    <button class="action-btn" style="color: #f87171; border-color: rgba(239, 68, 68, 0.3);" onclick="deleteSupplier(<?= $s['SUPPLIER_ID'] ?>)">Delete</button>
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
            <input type="hidden" id="supplier_id" name="supplier_id">
            
            <div class="supplier-form-group">
                <label class="supplier-form-label">Company / Supplier Name *</label>
                <input type="text" id="supplier_name" name="supplier_name" class="supplier-form-input" required>
            </div>
            <div class="supplier-form-group">
                <label class="supplier-form-label">Contact Person</label>
                <input type="text" id="contact_person" name="contact_person" class="supplier-form-input" placeholder="e.g., Jane Doe">
            </div>
            
            <div class="supplier-form-row">
                <div class="supplier-form-group">
                    <label class="supplier-form-label">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number" class="supplier-form-input" placeholder="+1 234 567 890">
                </div>
                <div class="supplier-form-group">
                    <label class="supplier-form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="supplier-form-input" placeholder="vendor@example.com">
                </div>
            </div>

            <div class="supplier-form-group">
                <label class="supplier-form-label">Address</label>
                <input type="text" id="address" name="address" class="supplier-form-input" placeholder="Physical location or HQ">
            </div>
            <div class="supplier-form-group">
                <label class="supplier-form-label">Status</label>
                <select id="status" name="status" class="supplier-form-select">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <div class="supplier-modal-actions">
                <button type="button" class="supplier-btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="supplier-btn-save" id="saveBtn">Save Supplier</button>
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
            document.getElementById('modalTitle').innerText = "Edit Supplier";
            document.getElementById('supplier_id').value = data.SUPPLIER_ID;
            document.getElementById('supplier_name').value = data.SUPPLIER_NAME;
            document.getElementById('contact_person').value = data.CONTACT_PERSON || '';
            document.getElementById('contact_number').value = data.CONTACT_NUMBER || '';
            document.getElementById('email').value = data.EMAIL || '';
            document.getElementById('address').value = data.ADDRESS || '';
            document.getElementById('status').value = data.STATUS;
        } else {
            document.getElementById('modalTitle').innerText = "Add Supplier";
            form.reset();
            document.getElementById('supplier_id').value = "";
            document.getElementById('status').value = "Active";
        }
    }

    function closeModal() {
        modal.classList.remove('active');
    }

    async function saveSupplier(e) {
        e.preventDefault();
        const btn = document.getElementById('saveBtn');
        const formData = new FormData(form);

        btn.disabled = true;
        btn.innerText = "Saving...";

        try {
            const response = await fetch('../process/saveSupplier.php', {
                method: 'POST',
                body: formData
            });

            const rawText = await response.text();
            let data;
            
            try {
                data = JSON.parse(rawText);
            } catch (err) {
                console.error("Server returned HTML or an error instead of JSON:", rawText);
                alert("ERROR: The system couldn't save. Please ensure '../process/saveSupplier.php' exists and has no errors. Check console for details.");
                btn.disabled = false;
                btn.innerText = "Save Supplier";
                return;
            }

            if(data.success) {
                alert("✅ " + data.message);
                window.location.reload(); 
            } else {
                alert("❌ Error: " + data.message);
            }
        } catch (error) {
            console.error(error);
            alert("A network or system error occurred. Check the console.");
        } finally {
            btn.disabled = false;
            btn.innerText = "Save Supplier";
        }
    }

    async function deleteSupplier(supplierId) {
        if (!confirm("Are you sure you want to delete this supplier? This action cannot be undone.")) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('supplier_id', supplierId);

            const response = await fetch('../process/deleteSupplier.php', {
                method: 'POST',
                body: formData
            });
            
            const rawText = await response.text();
            let data;

            try {
                data = JSON.parse(rawText);
            } catch (err) {
                console.error("Server returned HTML/error:", rawText);
                alert("ERROR: System could not delete. Make sure '../process/deleteSupplier.php' exists.");
                return;
            }

            if (data.success) {
                alert("✅ " + data.message);
                window.location.reload();
            } else {
                alert("❌ " + data.message);
            }
        } catch (error) {
            console.error(error);
            alert("System Error. Check console.");
        }
    }
</script>

</body>
</html>