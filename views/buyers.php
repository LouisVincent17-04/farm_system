<?php
// views/buyers.php
ob_start(); // Start output buffering

$page = "farm"; 
include '../config/Connection.php';
include '../common/navbar.php';
include '../security/checkRole.php';    
checkRole(2);

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
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; margin:0; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .header h1 { margin: 0; font-size: 1.8rem; }
        
        .card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 1.5rem; }
        
        /* Table Default Styles */
        .table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .table th { text-align: left; padding: 12px; background: rgba(15, 23, 42, 0.8); color: #94a3b8; border-bottom: 1px solid #334155; }
        .table td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
        
        .btn { padding: 10px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; font-size: 0.9rem; transition: transform 0.1s; }
        .btn:active { transform: scale(0.98); }
        
        .btn-primary { background: #3b82f6; color: white; }
        .btn-edit { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); padding: 6px 12px; font-size: 0.85rem; }
        .btn-delete { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); padding: 6px 12px; font-size: 0.85rem; margin-left: 5px; }
        
        /* Alerts */
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: bold; text-align: center; }
        .alert-success { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid #10b981; }
        .alert-error { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #ef4444; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 999; align-items: center; justify-content: center; padding: 1rem; box-sizing: border-box; }
        .modal.show { display: flex; }
        .modal-content { background: #1e293b; border-radius: 12px; width: 100%; max-width: 400px; padding: 2rem; border: 1px solid #475569; animation: zoomIn 0.2s; position: relative; }
        @keyframes zoomIn { from {transform:scale(0.9); opacity:0;} to {transform:scale(1); opacity:1;} }
        
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 5px; color: #94a3b8; font-size: 0.9rem; }
        .form-input { width: 100%; padding: 12px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 6px; box-sizing: border-box; font-size: 1rem; }

        /* --- MOBILE RESPONSIVENESS --- */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            
            /* Stack Header */
            .header { flex-direction: column; align-items: stretch; text-align: center; }
            .btn-primary { width: 100%; padding: 12px; font-size: 1rem; }

            /* Table to Card View Transformation */
            .table thead { display: none; } /* Hide Table Headers */
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
    <div class="header">
        <h1>Buyer Database</h1>
        <button class="btn btn-primary" onclick="openModal()">+ Add New Buyer</button>
    </div>

    <?php if($error_msg): ?>
        <div class="alert alert-error"><?= $error_msg ?></div>
    <?php endif; ?>
    <?php if($success_msg): ?>
        <div class="alert alert-success"><?= $success_msg ?></div>
    <?php endif; ?>

    <div class="card">
        <table class="table">
            <thead><tr><th>Name</th><th>Contact</th><th>Address</th><th>Action</th></tr></thead>
            <tbody>
                <?php if(empty($buyers)): ?>
                    <tr><td colspan="4" style="text-align:center; padding:2rem; color:#64748b;">No buyers found.</td></tr>
                <?php else: ?>
                    <?php foreach($buyers as $b): ?>
                    <tr>
                        <td data-label="Name" style="font-weight:bold; color:white;"><?= htmlspecialchars($b['FULL_NAME']) ?></td>
                        <td data-label="Contact"><?= htmlspecialchars($b['CONTACT_NO']) ?></td>
                        <td data-label="Address"><?= htmlspecialchars($b['ADDRESS']) ?></td>
                        <td data-label="Action">
                            <button class="btn btn-edit" onclick='openModal(<?= json_encode($b) ?>)'>Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure? This action cannot be undone if no history exists.');">
                                <input type="hidden" name="delete_id" value="<?= $b['BUYER_ID'] ?>">
                                <button type="submit" class="btn btn-delete">Remove</button>
                            </form>
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
        <h2 id="modalTitle" style="margin-top:0;">Add Buyer</h2>
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
                <textarea name="address" id="address" class="form-input" rows="3" placeholder="Barangay, City"></textarea>
            </div>
            <div style="text-align: right; margin-top: 1.5rem; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn" style="background:transparent; color:#94a3b8; border:1px solid #475569;" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Buyer</button>
            </div>
        </form>
    </div>
</div>

<script>
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
    function closeModal() { document.getElementById('buyerModal').classList.remove('show'); }
    
    // Close modal if clicked outside
    window.onclick = function(e) {
        if (e.target == document.getElementById('buyerModal')) {
            closeModal();
        }
    }
</script>

</body>
</html>