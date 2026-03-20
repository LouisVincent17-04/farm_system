<?php
// views/buyers.php
ob_start(); // Start output buffering

$page = "admin_dashboard"; 
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('buyer');

include '../common/navbar.php';
include '../common/chat_support.php';

if($_SESSION['user']['USER_TYPE'] < 3)
{
    echo "<script>alert('Access denied.'); window.location.href = 'admin_dashboard.php';</script>";
    exit();
}


// --- 1. HANDLE POST REQUESTS (Add/Edit/Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        // DELETE HANDLER
        if (isset($_POST['delete_id'])) {
            $delId = $_POST['delete_id'];
            
            // Check transactions
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM animal_sales WHERE CUSTOMER_NAME = (SELECT FULL_NAME FROM buyers WHERE BUYER_ID = ?)");
            $checkStmt->execute([$delId]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $_SESSION['flash_error'] = "Cannot delete buyer. Transaction history exists.";
            } else {
                $delStmt = $conn->prepare("UPDATE buyers SET IS_ACTIVE = 0 WHERE BUYER_ID = ?");
                $delStmt->execute([$delId]);
                $_SESSION['flash_success'] = "Buyer removed successfully.";
            }
        } 
        // ADD/EDIT HANDLER
        else {
            $name = trim($_POST['full_name']);
            $contact = trim($_POST['contact_no']);
            $addr = trim($_POST['address']);
            $id = $_POST['buyer_id'] ?? null;

            if ($id) {
                // Edit
                $stmt = $conn->prepare("UPDATE buyers SET FULL_NAME=?, CONTACT_NO=?, ADDRESS=? WHERE BUYER_ID=?");
                $stmt->execute([$name, $contact, $addr, $id]);
                $_SESSION['flash_success'] = "Buyer updated successfully.";
            } else {
                // Add
                $stmt = $conn->prepare("INSERT INTO buyers (FULL_NAME, CONTACT_NO, ADDRESS) VALUES (?, ?, ?)");
                $stmt->execute([$name, $contact, $addr]);
                $_SESSION['flash_success'] = "New buyer added successfully.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Error: " . $e->getMessage();
    }

    // --- REDIRECT TO PREVENT DUPLICATES ---
    header("Location: buyers.php");
    exit();
}

// --- 2. FETCH DATA & MESSAGES ---
$buyers = $conn->query("SELECT * FROM buyers WHERE IS_ACTIVE = 1 ORDER BY FULL_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);

// Retrieve Flash Messages
$success_msg = "";
$error_msg = "";

if (isset($_SESSION['flash_success'])) {
    $success_msg = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $error_msg = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Buyer Management</title>
    <style>
        /* Shared Styles */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; margin:0; padding-bottom: 40px; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        
        /* Back Link Style */
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; 
            text-decoration: none; color: #94a3b8; font-weight: 600; 
            font-size: 0.95rem; margin-bottom: 20px; transition: color 0.2s;
        }
        .back-link:hover { color: white; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .header h1 { margin: 0; font-size: 1.8rem; color: #0ea5e9; }
        
        /* Filters & Sort */
        .filters-wrapper { display: flex; gap: 15px; margin-bottom: 2rem; flex-wrap: wrap; }
        .search-container { position: relative; flex: 1; min-width: 250px; }
        .search-input { width: 100%; padding: 14px 14px 14px 45px; background: rgba(30, 41, 59, 0.5); border: 1px solid #334155; border-radius: 0.5rem; color: white; font-size: 1rem; backdrop-filter: blur(10px); box-sizing: border-box; }
        .search-input::placeholder { color: #94a3b8; }
        .search-input:focus { outline: none; border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1); }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 20px; height: 20px; }
        
        .sort-select {
            width: auto; min-width: 220px; padding: 14px; border-radius: 0.5rem;
            background: rgba(30, 41, 59, 0.5); border: 1px solid #334155;
            color: white; font-size: 1rem; outline: none; transition: border-color 0.2s;
            backdrop-filter: blur(10px); cursor: pointer;
        }
        .sort-select:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1); }
        .sort-select option { background: #1e293b; color: white; }

        .card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 1.5rem; overflow-x: auto; }
        
        /* Table Default Styles */
        .table { width: 100%; border-collapse: collapse; min-width: 600px;}
        .table th { text-align: left; padding: 12px; background: rgba(15, 23, 42, 0.8); color: #0ea5e9; border-bottom: 1px solid #334155; text-transform: uppercase; font-size: 0.85rem;}
        .table td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
        
        .btn { padding: 10px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.9rem; transition: transform 0.1s; }
        .btn:active { transform: scale(0.98); }
        
        .btn-primary { background: #0ea5e9; color: white; transition: background 0.2s;}
        .btn-primary:hover { background: #0284c7; }
        
        .btn-edit { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); padding: 6px 12px; font-size: 0.85rem; }
        .btn-edit:hover { background: rgba(59, 130, 246, 0.2); }
        
        .btn-delete { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); padding: 6px 12px; font-size: 0.85rem; margin-left: 5px; }
        .btn-delete:hover { background: rgba(239, 68, 68, 0.2); }
        
        /* Alerts */
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: bold; text-align: center; }
        .alert-success { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid #10b981; }
        .alert-error { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #ef4444; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 999; align-items: center; justify-content: center; padding: 1rem; box-sizing: border-box; }
        .modal.show { display: flex; }
        .modal-content { background: #1e293b; border-radius: 12px; width: 100%; max-width: 450px; padding: 2rem; border: 1px solid #475569; animation: zoomIn 0.2s; position: relative; }
        @keyframes zoomIn { from {transform:scale(0.9); opacity:0;} to {transform:scale(1); opacity:1;} }
        
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 5px; color: #94a3b8; font-size: 0.9rem; }
        .form-input { width: 100%; padding: 12px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 6px; box-sizing: border-box; font-size: 1rem; transition: 0.2s;}
        .form-input:focus { border-color: #0ea5e9; outline: none; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1); }

        /* --- MOBILE RESPONSIVENESS --- */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            
            /* Stack Header */
            .header { flex-direction: column; align-items: stretch; text-align: center; }
            .btn-primary { width: 100%; padding: 12px; font-size: 1rem; }
            
            .filters-wrapper { flex-direction: column; }
            .sort-select { width: 100%; }

            /* Table to Card View Transformation */
            .table thead { display: none; } 
            .table, .table tbody, .table tr, .table td { display: block; width: 100%; box-sizing: border-box; }
            
            .table tr {
                background: rgba(15, 23, 42, 0.6);
                border: 1px solid #475569;
                border-radius: 10px;
                margin-bottom: 1rem;
                padding: 1rem;
            }

            .table td {
                padding: 8px 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                text-align: right;
                border-bottom: 1px solid rgba(255,255,255,0.05);
            }

            .table td:last-child { border-bottom: none; padding-top: 15px; justify-content: flex-end; gap: 10px; }

            /* Add Labels via CSS */
            .table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #94a3b8;
                font-size: 0.85rem;
                text-transform: uppercase;
                margin-right: 10px;
            }

            /* Adjust Buttons for Mobile */
            .btn-edit, .btn-delete { padding: 8px 16px; font-size: 0.9rem; margin: 0; }
            
            /* Specific fix for Address text wrap */
            .table td[data-label="Address"] { display: block; text-align: left; }
            .table td[data-label="Address"]::before { display: block; margin-bottom: 5px; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <a href="admin_dashboard.php" class="back-link">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Admin Dashboard
    </a>

    <div class="header">
        <h1>Buyer Management</h1>
        <button class="btn btn-primary" onclick="openModal()">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add New Buyer
        </button>
    </div>

    <?php if($error_msg): ?>
        <div class="alert alert-error"><?= $error_msg ?></div>
    <?php endif; ?>
    <?php if($success_msg): ?>
        <div class="alert alert-success"><?= $success_msg ?></div>
    <?php endif; ?>

    <div class="filters-wrapper">
        <div class="search-container">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" class="search-input" placeholder="Search buyers by name, contact, or address..." onkeyup="filterTable()">
        </div>
        
        <select class="sort-select" onchange="sortDropdown(this.value)">
            <option value="name_asc">Sort: Name (A-Z)</option>
            <option value="name_desc">Sort: Name (Z-A)</option>
            <option value="newest">Sort: Newest Added</option>
            <option value="oldest">Sort: Oldest Added</option>
        </select>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Address</th>
                    <th style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody id="buyer-table-body">
                <?php if(empty($buyers)): ?>
                    <tr id="empty-state-row"><td colspan="4" style="text-align:center; padding:2rem; color:#64748b;">No buyers found.</td></tr>
                <?php else: ?>
                    <?php foreach($buyers as $b): ?>
                    <tr class="buyer-row" data-id="<?= $b['BUYER_ID'] ?>" data-name="<?= htmlspecialchars(strtolower($b['FULL_NAME'])) ?>">
                        <td data-label="Name" style="font-weight:bold; color:white;"><?= htmlspecialchars($b['FULL_NAME']) ?></td>
                        <td data-label="Contact"><?= htmlspecialchars($b['CONTACT_NO'] ?? 'N/A') ?></td>
                        <td data-label="Address" style="color: #cbd5e1;"><?= htmlspecialchars($b['ADDRESS'] ?? 'N/A') ?></td>
                        <td data-label="Action" style="text-align: center;">
                            <div style="display: flex; justify-content: center; align-items: center;">
                                <button class="btn btn-edit" onclick='openModal(<?= json_encode($b, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                                <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Are you sure? This action cannot be undone if no history exists.');">
                                    <input type="hidden" name="delete_id" value="<?= $b['BUYER_ID'] ?>">
                                    <button type="submit" class="btn btn-delete">Remove</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="buyerModal" class="modal">
    <div class="modal-content">
        <h2 id="modalTitle" style="margin-top:0; color:#0ea5e9; font-size: 1.5rem; margin-bottom: 1.5rem;">Add Buyer</h2>
        <form method="POST">
            <input type="hidden" name="buyer_id" id="buyer_id">
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" id="full_name" class="form-input" required placeholder="e.g. Juan Dela Cruz">
            </div>
            <div class="form-group">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact_no" id="contact_no" class="form-input" placeholder="09123456789">
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea name="address" id="address" class="form-input" rows="3" placeholder="Barangay, City, Province"></textarea>
            </div>
            <div style="text-align: right; margin-top: 1.5rem; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn" style="background:transparent; color:#94a3b8; border:1px solid #475569;" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Buyer</button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- MODAL LOGIC ---
    function openModal(data = null) {
        document.getElementById('buyerModal').classList.add('show');
        if(data) {
            document.getElementById('modalTitle').innerText = 'Edit Buyer';
            document.getElementById('buyer_id').value = data.BUYER_ID;
            document.getElementById('full_name').value = data.FULL_NAME;
            document.getElementById('contact_no').value = data.CONTACT_NO;
            document.getElementById('address').value = data.ADDRESS;
        } else {
            document.getElementById('modalTitle').innerText = 'Add Buyer';
            document.getElementById('buyer_id').value = '';
            document.querySelector('#buyerModal form').reset();
        }
    }
    
    function closeModal() { 
        document.getElementById('buyerModal').classList.remove('show'); 
    }
    
    window.onclick = function(e) {
        if (e.target == document.getElementById('buyerModal')) {
            closeModal();
        }
    }

    // --- SORTING LOGIC ---
    function sortDropdown(val) {
        const tbody = document.getElementById('buyer-table-body');
        const rows = Array.from(tbody.querySelectorAll('.buyer-row'));
        
        rows.sort((a, b) => {
            const nameA = a.dataset.name || '';
            const nameB = b.dataset.name || '';
            const idA = parseInt(a.dataset.id) || 0;
            const idB = parseInt(b.dataset.id) || 0;

            if (val === 'name_asc') return nameA.localeCompare(nameB);
            if (val === 'name_desc') return nameB.localeCompare(nameA);
            if (val === 'newest') return idB - idA; // Highest ID first
            if (val === 'oldest') return idA - idB; // Lowest ID first
        });
        
        rows.forEach(row => tbody.appendChild(row));
    }

    // --- FILTER LOGIC ---
    function filterTable() {
        const term = document.querySelector('.search-input').value.toLowerCase();
        const rows = document.querySelectorAll('.buyer-row');
        let visibleCount = 0;

        rows.forEach(row => {
            // Search across entire row text content
            const textContent = row.textContent.toLowerCase();
            
            if (textContent.includes(term)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        checkEmptyState(visibleCount);
    }

    function checkEmptyState(visibleCount) {
        const tbody = document.getElementById('buyer-table-body');
        let emptyRow = document.getElementById('empty-state-row');
        
        if (visibleCount === 0) {
            if (!emptyRow) {
                emptyRow = document.createElement('tr');
                emptyRow.id = 'empty-state-row';
                emptyRow.innerHTML = '<td colspan="4" style="text-align:center; padding: 2rem; color: #94a3b8;">No buyers found matching your search.</td>';
                tbody.appendChild(emptyRow);
            }
            emptyRow.style.display = '';
        } else {
            if (emptyRow) emptyRow.style.display = 'none';
        }
    }

    // Auto-hide flash messages
    document.addEventListener('DOMContentLoaded', () => {
        const alerts = document.querySelectorAll('.alert');
        if (alerts.length > 0) {
            setTimeout(() => {
                alerts.forEach(el => el.style.display = 'none');
            }, 4000);
        }
    });
</script>

</body>
</html>