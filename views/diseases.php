<?php
// diseases.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page = "admin_dashboard";
// Ensure these paths are correct for your setup
include '../common/navbar.php'; 
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(2); // Farm Admin

$message = "";
$error = "";

// --- 1. HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // ADD
        if (isset($_POST['action']) && $_POST['action'] == 'add') {
            $name = trim($_POST['disease_name']);
            $symptoms = trim($_POST['symptoms']);
            $notes = trim($_POST['notes']);

            if (!empty($name)) {
                $sql = "INSERT INTO diseases (DISEASE_NAME, SYMPTOMS, NOTES) VALUES (:name, :symptoms, :notes)";
                $stmt = $conn->prepare($sql);
                if($stmt->execute([':name' => $name, ':symptoms' => $symptoms, ':notes' => $notes])) {
                    $message = "✅ Disease added successfully.";
                } else {
                    $error = "❌ Failed to add disease.";
                }
            } else {
                $error = "❌ Disease Name is required.";
            }
        }

        // EDIT
        if (isset($_POST['action']) && $_POST['action'] == 'edit') {
            $id = $_POST['disease_id'];
            $name = trim($_POST['disease_name']);
            $symptoms = trim($_POST['symptoms']);
            $notes = trim($_POST['notes']);

            if (!empty($name) && !empty($id)) {
                $sql = "UPDATE diseases SET DISEASE_NAME = :name, SYMPTOMS = :symptoms, NOTES = :notes WHERE DISEASE_ID = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([':name' => $name, ':symptoms' => $symptoms, ':notes' => $notes, ':id' => $id]);
                $message = "✅ Disease updated successfully.";
            }
        }

        // DELETE
        if (isset($_POST['action']) && $_POST['action'] == 'delete') {
            $id = $_POST['delete_id'];
            if (!empty($id)) {
                $sql = "DELETE FROM diseases WHERE DISEASE_ID = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([':id' => $id]);
                $message = "✅ Disease deleted successfully.";
            }
        }

    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

// --- 2. FETCH DATA ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$data = [];

try {
    $query = "SELECT * FROM diseases";
    $params = [];
    if (!empty($search)) {
        $query .= " WHERE DISEASE_NAME LIKE :s OR SYMPTOMS LIKE :s OR NOTES LIKE :s";
        $params[':s'] = "%$search%";
    }
    $query .= " ORDER BY DISEASE_ID DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Disease Management</title>
    <style>
        /* --- GLOBAL STYLES --- */
        body { 
            font-family: system-ui, -apple-system, sans-serif; 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            color: #e2e8f0; 
            margin: 0; 
            padding-bottom: 80px; 
        }
        .disease-container { 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 1.5rem; 
        }
        
        /* Header Flexbox */
        .disease-header-flex { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 2rem; 
            flex-wrap: wrap; 
            gap: 1rem; 
        }
        .disease-title { 
            font-size: 2rem; 
            font-weight: 800; 
            background: linear-gradient(135deg, #f43f5e, #e11d48); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
            margin: 0; 
        }
        
        /* Controls */
        .disease-controls { 
            display: flex; 
            gap: 10px; 
            align-items: center; 
        }
        .disease-search-form { 
            display: flex; 
            gap: 10px; 
        }
        .disease-search-input { 
            padding: 10px 15px; 
            border-radius: 8px; 
            border: 1px solid #334155; 
            background: #1e293b; 
            color: white; 
            width: 250px; 
        }
        
        .disease-btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            gap: 5px; 
            transition: 0.2s; 
            white-space: nowrap; 
        }
        .disease-btn-primary { 
            background: #e11d48; 
            color: white; 
        }
        .disease-btn-primary:hover { 
            background: #be123c; 
        }
        .disease-btn-edit { 
            background: #3b82f6; 
            color: white; 
            padding: 6px 12px; 
            font-size: 0.85rem; 
        }
        .disease-btn-edit:hover {
            background: #2563eb;
        }
        .disease-btn-delete { 
            background: #ef4444; 
            color: white; 
            padding: 6px 12px; 
            font-size: 0.85rem; 
        }
        .disease-btn-delete:hover {
            background: #dc2626;
        }

        /* Table Responsive Wrapper */
        .disease-table-wrap { 
            background: rgba(30, 41, 59, 0.6); 
            border: 1px solid #334155; 
            border-radius: 16px; 
            overflow: hidden; 
            backdrop-filter: blur(10px); 
            width: 100%; 
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch; 
            margin-top: 1rem; 
        }
        .disease-table { 
            width: 100%; 
            border-collapse: collapse; 
            min-width: 800px; 
        }
        .disease-table-header { 
            background: rgba(15, 23, 42, 0.95); 
            color: #fb7185; 
            text-align: left; 
            padding: 1rem; 
            font-size: 0.85rem; 
            text-transform: uppercase; 
            border-bottom: 1px solid #334155; 
            white-space: nowrap; 
        }
        .disease-table-cell { 
            padding: 1rem; 
            border-bottom: 1px solid rgba(255,255,255,0.05); 
            font-size: 0.95rem; 
            color: #cbd5e1; 
            vertical-align: top; 
        }
        .disease-table-row:hover { 
            background: rgba(255,255,255,0.02); 
        }

        /* Alerts */
        .disease-alert { 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: 8px; 
        }
        .disease-alert-success { 
            background: rgba(16, 185, 129, 0.2); 
            color: #34d399; 
            border: 1px solid rgba(16, 185, 129, 0.3); 
        }
        .disease-alert-error { 
            background: rgba(239, 68, 68, 0.2); 
            color: #f87171; 
            border: 1px solid rgba(239, 68, 68, 0.3); 
        }

        /* --- MODAL STYLES (Fixed Z-Index) --- */
        .disease-modal-overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.8); 
            display: none; 
            justify-content: center; 
            align-items: center; 
            z-index: 10000; 
            backdrop-filter: blur(5px);
        }
        .disease-modal { 
            background: #1e293b; 
            padding: 2rem; 
            border-radius: 16px; 
            width: 90%; 
            max-width: 500px; 
            border: 1px solid #475569; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); 
            position: relative;
            max-height: 90vh; 
            overflow-y: auto; 
            animation: diseaseFadeIn 0.2s ease-out;
        }
        @keyframes diseaseFadeIn { 
            from { opacity: 0; transform: scale(0.95); } 
            to { opacity: 1; transform: scale(1); } 
        }
        
        .disease-modal-header { 
            font-size: 1.5rem; 
            font-weight: bold; 
            margin-bottom: 1rem; 
            color: #e2e8f0; 
        }
        .disease-form-group { 
            margin-bottom: 1rem; 
        }
        .disease-form-label { 
            display: block; 
            margin-bottom: 0.5rem; 
            font-size: 0.9rem; 
            color: #94a3b8; 
        }
        .disease-form-control { 
            width: 100%; 
            padding: 12px; 
            background: #0f172a; 
            border: 1px solid #334155; 
            border-radius: 8px; 
            color: white; 
            box-sizing: border-box; 
            font-size: 1rem; 
        }
        .disease-form-control:focus { 
            outline: none; 
            border-color: #e11d48; 
        }
        textarea.disease-form-control {
            font-family: inherit;
            resize: vertical;
        }
        .disease-modal-actions { 
            display: flex; 
            justify-content: flex-end; 
            gap: 10px; 
            margin-top: 1.5rem; 
        }
        .disease-btn-cancel { 
            background: transparent; 
            border: 1px solid #475569; 
            color: #94a3b8; 
        }
        .disease-btn-cancel:hover {
            background: rgba(71, 85, 105, 0.2);
        }

        /* --- MOBILE RESPONSIVENESS FIXES --- */
        @media (max-width: 768px) {
            .disease-container { 
                padding: 1rem; 
                padding-top: 1rem; 
            }
            
            /* Stack header items vertically */
            .disease-header-flex { 
                flex-direction: column; 
                align-items: stretch; 
                gap: 15px; 
            }
            .disease-title { 
                text-align: center; 
                font-size: 1.75rem; 
            }
            
            /* Make controls stack vertically */
            .disease-controls { 
                flex-direction: column; 
                width: 100%; 
                gap: 10px; 
            }
            .disease-search-form { 
                width: 100%; 
                display: flex; 
                gap: 10px; 
            }
            .disease-search-input { 
                flex-grow: 1; 
                width: auto; 
            }
            
            /* Make Add Button Full Width */
            .disease-btn-add-new { 
                width: 100%; 
            }
            
            /* Modal Adjustments */
            .disease-modal { 
                width: 95%; 
                padding: 1.5rem; 
                margin: 10px; 
            }
        }
    </style>
</head>
<body>

<div class="disease-container">
    
    <div class="disease-header-flex">
        <div>
            <h1 class="disease-title">Disease Management</h1>
            <p style="color:#94a3b8; margin:0;">Register and monitor livestock diseases.</p>
        </div>
        <div class="disease-controls">
            <form method="GET" action="diseases.php" class="disease-search-form">
                <input type="text" name="search" class="disease-search-input" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="disease-btn disease-btn-primary">🔍</button>
            </form>
            <button type="button" onclick="openDiseaseModal('diseaseAddModal')" class="disease-btn disease-btn-primary disease-btn-add-new">+ Add New</button>
        </div>
    </div>

    <?php if($message): ?> <div class="disease-alert disease-alert-success"><?= $message ?></div> <?php endif; ?>
    <?php if($error): ?> <div class="disease-alert disease-alert-error"><?= $error ?></div> <?php endif; ?>

    <div class="disease-table-wrap">
        <table class="disease-table">
            <thead>
                <tr>
                    <th class="disease-table-header" style="width: 50px;">ID</th>
                    <th class="disease-table-header" style="width: 250px;">Disease Name</th>
                    <th class="disease-table-header">Symptoms</th>
                    <th class="disease-table-header">Notes</th>
                    <th class="disease-table-header" style="width: 150px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="diseaseTableBody">
                <?php if(empty($data)): ?>
                    <tr class="disease-table-row"><td class="disease-table-cell" colspan="5" style="text-align:center; padding:3rem; color:#64748b;">No diseases found.</td></tr>
                <?php else: ?>
                    <?php foreach($data as $row): ?>
                    <tr class="disease-table-row">
                        <td class="disease-table-cell" style="color:#64748b;">#<?= $row['DISEASE_ID'] ?></td>
                        <td class="disease-table-cell" style="font-weight:bold; color:white;"><?= htmlspecialchars($row['DISEASE_NAME']) ?></td>
                        <td class="disease-table-cell"><?= nl2br(htmlspecialchars($row['SYMPTOMS'])) ?></td>
                        <td class="disease-table-cell" style="color:#94a3b8;"><?= nl2br(htmlspecialchars($row['NOTES'])) ?></td>
                        <td class="disease-table-cell" style="text-align: right;">
                            <button class="disease-btn disease-btn-edit" onclick="editDisease(<?= htmlspecialchars(json_encode($row)) ?>)">Edit</button>
                            <button class="disease-btn disease-btn-delete" onclick="deleteDisease(<?= $row['DISEASE_ID'] ?>)">Del</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Add Modal -->
<div id="diseaseAddModal" class="disease-modal-overlay">
    <div class="disease-modal">
        <div class="disease-modal-header">Add New Disease</div>
        <form method="POST" action="diseases.php" id="diseaseAddForm">
            <input type="hidden" name="action" value="add">
            
            <div class="disease-form-group">
                <label class="disease-form-label">Disease Name *</label>
                <input type="text" name="disease_name" class="disease-form-control" required placeholder="e.g. Swine Flu">
            </div>
            
            <div class="disease-form-group">
                <label class="disease-form-label">Symptoms</label>
                <textarea name="symptoms" class="disease-form-control" rows="3" placeholder="e.g. Fever, coughing..."></textarea>
            </div>

            <div class="disease-form-group">
                <label class="disease-form-label">Notes / Treatment Hint</label>
                <textarea name="notes" class="disease-form-control" rows="2" placeholder="Optional notes..."></textarea>
            </div>

            <div class="disease-modal-actions">
                <button type="button" class="disease-btn disease-btn-cancel" onclick="closeDiseaseModal('diseaseAddModal')">Cancel</button>
                <button type="submit" class="disease-btn disease-btn-primary">Save Disease</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="diseaseEditModal" class="disease-modal-overlay">
    <div class="disease-modal">
        <div class="disease-modal-header">Edit Disease</div>
        <form method="POST" action="diseases.php" id="diseaseEditForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="disease_id" id="diseaseEditId">
            
            <div class="disease-form-group">
                <label class="disease-form-label">Disease Name *</label>
                <input type="text" name="disease_name" id="diseaseEditName" class="disease-form-control" required>
            </div>
            
            <div class="disease-form-group">
                <label class="disease-form-label">Symptoms</label>
                <textarea name="symptoms" id="diseaseEditSymptoms" class="disease-form-control" rows="3"></textarea>
            </div>

            <div class="disease-form-group">
                <label class="disease-form-label">Notes</label>
                <textarea name="notes" id="diseaseEditNotes" class="disease-form-control" rows="2"></textarea>
            </div>

            <div class="disease-modal-actions">
                <button type="button" class="disease-btn disease-btn-cancel" onclick="closeDiseaseModal('diseaseEditModal')">Cancel</button>
                <button type="submit" class="disease-btn disease-btn-primary">Update Disease</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="diseaseDeleteForm" method="POST" action="diseases.php" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="delete_id" id="diseaseDeleteInput">
</form>

<script>
    // Ensure functions are global
    window.openDiseaseModal = function(id) {
        var modal = document.getElementById(id);
        if(modal) {
            modal.style.display = 'flex';
        } else {
            console.error("Modal not found: " + id);
        }
    }

    window.closeDiseaseModal = function(id) {
        document.getElementById(id).style.display = 'none';
    }

    window.editDisease = function(data) {
        document.getElementById('diseaseEditId').value = data.DISEASE_ID;
        document.getElementById('diseaseEditName').value = data.DISEASE_NAME;
        document.getElementById('diseaseEditSymptoms').value = data.SYMPTOMS || '';
        document.getElementById('diseaseEditNotes').value = data.NOTES || '';
        openDiseaseModal('diseaseEditModal');
    }

    window.deleteDisease = function(id) {
        if(confirm('Are you sure you want to delete this disease record? This cannot be undone.')) {
            document.getElementById('diseaseDeleteInput').value = id;
            document.getElementById('diseaseDeleteForm').submit();
        }
    }

    // Close modal if clicking overlay
    window.onclick = function(event) {
        if (event.target.classList.contains('disease-modal-overlay')) {
            event.target.style.display = "none";
        }
    }
</script>

</body>
</html>