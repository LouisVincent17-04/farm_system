<?php
// views/animal_type.php
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('animal_type');
$page = "admin_dashboard"; 
include '../common/navbar.php';
include '../common/chat_support.php';

if($_SESSION['user']['USER_TYPE'] < 3)
{
    echo "<script>alert('Access denied.'); window.location.href = 'admin_dashboard.php';</script>";
    exit();
}

// Check for status messages from redirects
$status = $_GET['status'] ?? '';
$msg = $_GET['msg'] ?? '';

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 1. Fetch Animal Types
    $sql = "SELECT * FROM Animal_Type ORDER BY ANIMAL_TYPE_ID ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $animal_type_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $animal_type_data = [];
    $status = 'error';
    $msg = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Animal Species Management</title>
    
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
            --border-active:  rgba(16,185,129,0.5); /* Emerald Accent */
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --emerald-glow:   rgba(16,185,129,0.25);
            --blue:           #38bdf8;
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(16,185,129,0.06) 0%, transparent 60%);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* ─── TOP BAR ─── */
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
            color: var(--emerald); background: var(--emerald-dim); border: 1px solid rgba(16,185,129,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── PAGE HEADER ─── */
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap;
        }
        .header-info h1 {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .header-info h1 span {
            background: linear-gradient(135deg, var(--emerald), #059669);
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
        .btn-primary { background: var(--emerald); color: #000; }
        .btn-primary:hover { background: #34d399; box-shadow: 0 0 16px var(--emerald-glow); transform: translateY(-2px); }
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        /* ─── SEARCH BAR ─── */
        .search-container { position: relative; margin-bottom: 1.5rem; }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; height: 18px; }
        .search-input {
            width: 100%; padding: 14px 14px 14px 2.8rem; background: var(--bg-surface);
            border: 1px solid var(--border); border-radius: var(--radius-lg);
            color: var(--text-primary); font-size: 1rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .search-input:focus { border-color: var(--emerald); box-shadow: 0 0 0 3px var(--emerald-glow); background: var(--bg-hover); }

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

        .col-id { font-family: var(--font-mono); color: var(--text-muted); font-size: 0.85rem; }
        .col-name { font-weight: 600; font-size: 1.05rem; color: #fff; }

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

        /* ─── MODALS ─── */
        .modal {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;
            padding: 1rem;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 450px;
            box-shadow: var(--shadow-md); overflow: hidden;
        }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--emerald); }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); }

        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        .form-control {
            width: 100%; padding: 10px 12px; background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: 8px; font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .form-control:focus { border-color: var(--emerald); box-shadow: 0 0 0 3px var(--emerald-glow); }

        /* ─── ALERTS ─── */
        .alert-box { padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 1.5rem; text-align: center; font-weight: 600; font-size: 0.9rem; }
        .alert-success { background: var(--emerald-dim); border: 1px solid rgba(16, 185, 129, 0.3); color: var(--emerald); }
        .alert-error { background: var(--red-dim); border: 1px solid rgba(239, 68, 68, 0.3); color: var(--red); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.2; display: block; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-info { width: 100%; text-align: center; }
            .add-btn { width: 100%; justify-content: center; }

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
            td:last-child { border-bottom: none; justify-content: center; padding-top: 1rem; }
            td::before { 
                content: attr(data-label); font-weight: 700; color: var(--text-muted); 
                font-size: 0.75rem; text-transform: uppercase; text-align: left;
            }
            .actions { justify-content: flex-end; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div class="top-bar">
            <a href="admin_dashboard.php" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
            <span class="page-badge"><i class="fa-solid fa-dna"></i> Genetics & Species</span>
        </div>

        <div class="page-header">
            <div class="header-info">
                <h1>Animal <span>Species</span> Management</h1>
                <p>Configure and manage global animal categories across the farm.</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Add New Species
            </button>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert-box alert-<?php echo htmlspecialchars($status); ?>">
                <i class="fa-solid <?php echo ($status == 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?> me-2"></i>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" class="search-input" placeholder="Search animal types by name..." onkeyup="filterTable()">
        </div>

        <div class="table-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 20%;">ID</th>
                            <th>Species Name</th>
                            <th style="text-align: center; width: 15%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="animal-type-table">
                        <?php if (!empty($animal_type_data)): ?>
                            <?php foreach($animal_type_data as $data): ?>
                            <tr data-id="<?php echo $data['ANIMAL_TYPE_ID']; ?>">
                                <td data-label="Type ID" class="col-id">#<?php echo $data['ANIMAL_TYPE_ID']; ?></td>
                                <td data-label="Species Name" class="col-name">
                                    <div class="animal-type-details">
                                        <h3><?php echo htmlspecialchars($data['ANIMAL_TYPE_NAME']); ?></h3>
                                    </div>
                                </td>
                                <td data-label="Actions">
                                    <div class="actions">
                                        <button class="action-btn edit" onclick="editAnimalType(this)" title="Edit">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button class="action-btn delete" onclick="deleteAnimalType(this)" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div id="empty-state" class="empty-state" style="<?php echo empty($animal_type_data) ? 'display:block' : 'display:none'; ?>">
                    <i class="fa-solid fa-paw"></i>
                    <h3>No species found</h3>
                    <p>Try adding a new species or adjusting your search filters.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Species</h2>
            </div>
            <div class="modal-body">
                <form id="addAnimalTypeForm" method="POST" action="../process/addAnimalType.php">
                    <div class="form-group">
                        <label class="form-label">Species Name *</label>
                        <input type="text" class="form-control" id="add_animal_type_name" name="animal_type_name" placeholder="example: Swine, Cattle, Poultry" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeAddModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddForm()">Save Species</button>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Species</h2>
            </div>
            <div class="modal-body">
                <form id="editAnimalTypeForm" method="POST" action="../process/updateAnimalType.php">
                    <input type="hidden" id="edit_animal_type_id" name="animal_type_id">
                    <div class="form-group">
                        <label class="form-label">Species Name *</label>
                        <input type="text" class="form-control" id="edit_animal_type_name" name="animal_type_name" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitEditForm()">Update Species</button>
            </div>
        </div>
    </div>

    <form id="deleteAnimalTypeForm" method="POST" action="../process/deleteAnimalType.php" style="display: none;">
        <input type="hidden" id="delete_animal_type_id" name="animal_type_id">
    </form>

    <script>
        function openAddModal() {
            document.getElementById('addAnimalTypeForm').reset();
            document.getElementById('addModal').classList.add('show');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('show');
        }

        function submitAddForm() {
            const form = document.getElementById('addAnimalTypeForm');
            const name = document.getElementById('add_animal_type_name').value.trim();
            if (!name) { alert('Please fill in the animal type name'); return; }
            if (confirm('Do you want to add this animal type?')) { form.submit(); }
        }

        function editAnimalType(button) {
            const row = button.closest('tr');
            const id = row.getAttribute('data-id');
            const name = row.querySelector('.animal-type-details h3').textContent.trim();
            
            document.getElementById('edit_animal_type_id').value = id;
            document.getElementById('edit_animal_type_name').value = name;
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        function submitEditForm() {
            const form = document.getElementById('editAnimalTypeForm');
            const name = document.getElementById('edit_animal_type_name').value.trim();
            if (!name) { alert('Please fill in the animal type name'); return; }
            if (confirm('Do you want to update this animal type?')) { form.submit(); }
        }

        function deleteAnimalType(button) {
            const row = button.closest('tr');
            const id = row.getAttribute('data-id');
            if (confirm('Are you sure you want to delete this animal type?')) {
                document.getElementById('delete_animal_type_id').value = id;
                document.getElementById('deleteAnimalTypeForm').submit();
            }
        }

        function filterTable() {
            const term = document.querySelector('.search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#animal-type-table tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.querySelector('.animal-type-details h3').textContent.toLowerCase();
                if (name.includes(term)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            checkEmptyState(visibleCount);
        }

        function checkEmptyState(visibleCount) {
            const tbody = document.getElementById('animal-type-table');
            const emptyState = document.getElementById('empty-state');
            const actualVisibleCount = visibleCount !== undefined ? visibleCount : tbody.querySelectorAll('tr:not([style*="display: none"])').length;
            emptyState.style.display = (actualVisibleCount === 0) ? 'block' : 'none';
        }

        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                closeAddModal();
                closeEditModal();
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            checkEmptyState();
            setTimeout(() => {
                document.querySelectorAll('.alert-box').forEach(el => el.style.display = 'none');
            }, 5000);
        });
    </script>
</body>
</html>