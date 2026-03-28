<?php
// diseases.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page = "admin_dashboard";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('diseases');
include '../common/navbar.php'; 
include '../common/chat_support.php';

$message = "";
$error = "";

// --- 1. HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] == 'add') {
            $name = trim($_POST['disease_name']);
            $symptoms = trim($_POST['symptoms']);
            $notes = trim($_POST['notes']);

            if (!empty($name)) {
                $sql = "INSERT INTO diseases (DISEASE_NAME, SYMPTOMS, NOTES) VALUES (:name, :symptoms, :notes)";
                $stmt = $conn->prepare($sql);
                if($stmt->execute([':name' => $name, ':symptoms' => $symptoms, ':notes' => $notes])) {
                    $message = "Disease added successfully.";
                } else {
                    $error = "Failed to add disease.";
                }
            } else {
                $error = "Disease Name is required.";
            }
        }

        if (isset($_POST['action']) && $_POST['action'] == 'edit') {
            $id = $_POST['disease_id'];
            $name = trim($_POST['disease_name']);
            $symptoms = trim($_POST['symptoms']);
            $notes = trim($_POST['notes']);

            if (!empty($name) && !empty($id)) {
                $sql = "UPDATE diseases SET DISEASE_NAME = :name, SYMPTOMS = :symptoms, NOTES = :notes WHERE DISEASE_ID = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([':name' => $name, ':symptoms' => $symptoms, ':notes' => $notes, ':id' => $id]);
                $message = "Disease updated successfully.";
            }
        }

        if (isset($_POST['action']) && $_POST['action'] == 'delete') {
            $id = $_POST['delete_id'];
            if (!empty($id)) {
                $sql = "DELETE FROM diseases WHERE DISEASE_ID = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([':id' => $id]);
                $message = "Disease deleted successfully.";
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
    <title>Disease Management System</title>
    
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
            --blue:           #3b82f6;
            --red:            #f87171;
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-md:      10px;
            --radius-lg:      14px;
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
        .btn-primary:hover { background: #be123c; box-shadow: 0 0 16px var(--rose-glow); transform: translateY(-1px); }
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        /* ─── SEARCH BAR ─── */
        .search-wrapper {
            display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;
            background: var(--bg-surface); border: 1px solid var(--border);
            padding: 1rem; border-radius: var(--radius-xl); align-items: center;
        }
        .search-container { position: relative; flex: 1; min-width: 250px; display: flex; align-items: center; }
        .search-icon { position: absolute; left: 1rem; color: var(--text-muted); width: 18px; height: 18px; pointer-events: none; }
        .search-input {
            width: 100%; padding: 12px 12px 12px 2.8rem; background: var(--bg-elevated);
            border: 1px solid var(--border); border-radius: var(--radius-md); color: var(--text-primary);
            font-size: 0.95rem; font-family: var(--font); outline: none; transition: all var(--transition);
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
        .table td { padding: 16px; font-size: 0.9rem; color: var(--text-primary); vertical-align: top; }

        .col-id { font-family: var(--font-mono); color: var(--text-muted); font-size: 0.85rem; }
        .col-name { font-weight: 700; color: #fff; font-size: 1.05rem; }
        .col-text { line-height: 1.6; color: var(--text-secondary); }

        /* Actions */
        .actions { display: flex; gap: 8px; justify-content: flex-end; }
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
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85);
            backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;
            padding: 1rem;
        }
        .modal-overlay.show { display: flex; }
        .modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 500px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); overflow: hidden;
        }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--rose); }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); }

        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.25rem; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        .form-control {
            width: 100%; padding: 12px; background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: 8px; font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .form-control:focus { border-color: var(--rose); box-shadow: 0 0 0 3px var(--rose-glow); }
        textarea.form-control { resize: vertical; min-height: 80px; line-height: 1.5; }

        /* ─── ALERTS ─── */
        .alert { padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 1.5rem; text-align: center; font-weight: 600; font-size: 0.9rem; }
        .alert-success { background: var(--rose-dim); border: 1px solid rgba(225,29,72,0.3); color: var(--rose); }
        .alert-error { background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: var(--red); }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-info { text-align: left; }
            .btn-add-new { width: 100%; }
            .search-wrapper { flex-direction: column; align-items: stretch; }

            .table-wrap { border: none; background: transparent; }
            .table, .table thead, .table tbody, .table th, .table td, .table tr { display: block; }
            .table thead { display: none; }
            .table tbody tr { 
                background: var(--bg-surface); border: 1px solid var(--border); 
                border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem;
            }
            .table td { 
                display: flex; justify-content: space-between; align-items: flex-start; 
                padding: 0.75rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right;
            }
            .table td:last-child { border-bottom: none; justify-content: flex-end; padding-top: 1rem; }
            .table td::before { 
                content: attr(data-label); font-weight: 700; color: var(--text-muted); 
                font-size: 0.75rem; text-transform: uppercase; text-align: left; flex-shrink: 0;
            }
            .col-name { text-align: right; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="top-bar">
        <a href="admin_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-virus-covid"></i> Biosecurity</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1>Disease <span>Management</span></h1>
            <p>Register symptoms and treatment protocols for livestock health tracking.</p>
        </div>
        <button class="btn btn-primary btn-add-new" onclick="openDiseaseModal('diseaseAddModal')">
            <i class="fa-solid fa-plus"></i> Add New Disease
        </button>
    </div>

    <?php if($message): ?> <div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i> <?= $message ?></div> <?php endif; ?>
    <?php if($error): ?> <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation me-2"></i> <?= $error ?></div> <?php endif; ?>

    <div class="search-wrapper">
        <form method="GET" action="diseases.php" class="search-container">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" name="search" class="search-input" placeholder="Search by name, symptoms, or notes..." value="<?= htmlspecialchars($search) ?>">
        </form>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th style="width: 250px;">Disease Name</th>
                        <th>Symptoms</th>
                        <th>Treatment Notes</th>
                        <th style="text-align: right; width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($data)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:5rem; color:var(--text-muted);">No disease records found.</td></tr>
                    <?php else: ?>
                        <?php foreach($data as $row): ?>
                        <tr>
                            <td data-label="ID" class="col-id">#<?= $row['DISEASE_ID'] ?></td>
                            <td data-label="Disease" class="col-name"><?= htmlspecialchars($row['DISEASE_NAME']) ?></td>
                            <td data-label="Symptoms" class="col-text"><?= nl2br(htmlspecialchars($row['SYMPTOMS'])) ?></td>
                            <td data-label="Notes" class="col-text" style="font-size:0.85rem;"><?= !empty($row['NOTES']) ? nl2br(htmlspecialchars($row['NOTES'])) : '<em style="opacity:0.4">No notes</em>' ?></td>
                            <td data-label="Actions">
                                <div class="actions">
                                    <button class="action-btn edit" onclick="editDisease(<?= htmlspecialchars(json_encode($row)) ?>)" title="Edit Record">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button class="action-btn delete" onclick="deleteDisease(<?= $row['DISEASE_ID'] ?>)" title="Delete Record">
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

<div id="diseaseAddModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header"><h2>Add New Disease</h2></div>
        <form method="POST" action="diseases.php">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Disease Name *</label>
                    <input type="text" name="disease_name" class="form-control" required placeholder="e.g. African Swine Fever">
                </div>
                <div class="form-group">
                    <label class="form-label">Symptoms</label>
                    <textarea name="symptoms" class="form-control" placeholder="Describe physical signs..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Treatment Protocols / Notes</label>
                    <textarea name="notes" class="form-control" placeholder="Recommended actions or medications..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeDiseaseModal('diseaseAddModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Disease</button>
            </div>
        </form>
    </div>
</div>

<div id="diseaseEditModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header"><h2>Edit Disease</h2></div>
        <form method="POST" action="diseases.php">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="disease_id" id="diseaseEditId">
                <div class="form-group">
                    <label class="form-label">Disease Name *</label>
                    <input type="text" name="disease_name" id="diseaseEditName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Symptoms</label>
                    <textarea name="symptoms" id="diseaseEditSymptoms" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" id="diseaseEditNotes" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeDiseaseModal('diseaseEditModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Changes</button>
            </div>
        </form>
    </div>
</div>

<form id="diseaseDeleteForm" method="POST" action="diseases.php" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="delete_id" id="diseaseDeleteInput">
</form>

<script>
    window.openDiseaseModal = function(id) {
        const modal = document.getElementById(id);
        if(modal) modal.classList.add('show');
    }

    window.closeDiseaseModal = function(id) {
        const modal = document.getElementById(id);
        if(modal) modal.classList.remove('show');
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

    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.classList.remove('show');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const alerts = document.querySelectorAll('.alert');
        setTimeout(() => {
            alerts.forEach(a => a.style.display = 'none');
        }, 5000);
    });
</script>
</body>
</html>