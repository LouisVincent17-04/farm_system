<?php
// views/breed.php
error_reporting(0);
ini_set('display_errors', 0);

$page="admin_dashboard";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('breed');

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

    // 1. Fetch Breeds with associated Animal Type and Animal Count
    $sql = "SELECT 
                b.BREED_ID, 
                b.BREED_NAME, 
                b.ANIMAL_TYPE_ID, 
                t.ANIMAL_TYPE_NAME,
                (SELECT COUNT(a.ANIMAL_ID) FROM animal_records a WHERE a.BREED_ID = b.BREED_ID AND a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold') as ANIMAL_COUNT
            FROM BREEDS b
            LEFT JOIN ANIMAL_TYPE t ON b.ANIMAL_TYPE_ID = t.ANIMAL_TYPE_ID
            ORDER BY b.BREED_NAME ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $breed_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Animal Types for Dropdown
    $animal_types_sql = "SELECT * FROM Animal_Type ORDER BY ANIMAL_TYPE_NAME ASC";
    $stmt = $conn->prepare($animal_types_sql);
    $stmt->execute();
    $animal_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $breed_data = [];
    $animal_types = [];
    $status = 'error';
    $msg = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Breed Management System</title>
    
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
            --border-active:  rgba(139,92,246,0.5); /* Purple Accent */
            --purple:         #8b5cf6;
            --purple-dim:     rgba(139,92,246,0.12);
            --purple-glow:    rgba(139,92,246,0.25);
            --blue:           #38bdf8;
            --blue-dim:       rgba(56,189,248,0.12);
            --emerald:        #10b981;
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(139,92,246,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
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
            color: var(--purple); background: var(--purple-dim); border: 1px solid rgba(139,92,246,0.2);
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
            background: linear-gradient(135deg, var(--purple), #7c3aed);
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
        .btn-primary { background: var(--purple); color: #fff; }
        .btn-primary:hover { background: #7c3aed; box-shadow: 0 0 16px var(--purple-glow); transform: translateY(-1px); }
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        /* ─── FILTERS & SEARCH ─── */
        .filters-wrapper {
            display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;
            background: var(--bg-surface); border: 1px solid var(--border);
            padding: 1rem; border-radius: var(--radius-xl); align-items: center;
        }
        .search-container { position: relative; flex: 1; min-width: 250px; display: flex; align-items: center; }
        .search-icon { position: absolute; left: 1rem; color: var(--text-muted); width: 18px; height: 18px; pointer-events: none; }
        .search-input {
            width: 100%; padding: 12px 12px 12px 2.8rem; background: var(--bg-elevated);
            border: 1px solid var(--border); border-radius: var(--radius-md); color: var(--text-primary);
            font-size: 0.9rem; font-family: var(--font); outline: none; transition: all var(--transition);
        }
        .search-input:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow); background: var(--bg-hover); }

        .sort-select {
            width: auto; min-width: 220px; padding: 12px 36px 12px 12px;
            background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-primary); font-size: 0.9rem; font-family: var(--font);
            border-radius: var(--radius-md); outline: none; transition: all var(--transition);
            appearance: none; cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center;
        }

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

        .col-id { font-family: var(--font-mono); color: var(--text-muted); font-size: 0.85rem; }
        .col-name { font-weight: 600; color: #fff; font-size: 1.05rem; }
        .animal-type-tag { color: var(--purple); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; background: var(--purple-dim); padding: 4px 10px; border-radius: 99px; border: 1px solid rgba(139,92,246,0.2); }
        
        .count-badge { font-family: var(--font-mono); font-weight: 700; color: var(--blue); background: var(--blue-dim); padding: 4px 12px; border-radius: 6px; border: 1px solid rgba(56,189,248,0.2); }
        .count-badge.empty { color: var(--text-muted); background: rgba(255,255,255,0.05); border-color: var(--border); }

        /* Actions */
        .actions { display: flex; gap: 8px; }
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
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85);
            backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;
            padding: 1rem;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 480px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); overflow: hidden;
        }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--purple); }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); }

        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.25rem; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        .form-control {
            width: 100%; padding: 10px 12px; background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: 8px; font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .form-control:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow); }
        select.form-control { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; }

        /* ─── ALERTS ─── */
        .alert-box { padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 1.5rem; text-align: center; font-weight: 600; font-size: 0.9rem; }
        .alert-success { background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: var(--emerald); }
        .alert-error { background: var(--red-dim); border: 1px solid rgba(239, 68, 68, 0.3); color: var(--red); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.2; display: block; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-info { width: 100%; text-align: center; }
            .add-btn { width: 100%; justify-content: center; }
            .filters-wrapper { flex-direction: column; }
            .sort-select { width: 100%; }

            .table-wrap { border: none; background: transparent; }
            .table, .table thead, .table tbody, .table th, .table td, .table tr { display: block; }
            .table thead { display: none; }
            .table tbody tr { 
                background: var(--bg-surface); border: 1px solid var(--border); 
                border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem;
            }
            .table td { 
                display: flex; justify-content: space-between; align-items: center; 
                padding: 0.6rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right;
            }
            .table td:last-child { border-bottom: none; justify-content: flex-end; padding-top: 1rem; gap: 10px; }
            .table td::before { 
                content: attr(data-label); font-weight: 700; color: var(--text-muted); 
                font-size: 0.75rem; text-transform: uppercase; text-align: left;
            }
            .actions { justify-content: flex-end; width: 100%; }
            .breed-info { justify-content: flex-end; }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div class="top-bar">
            <a href="admin_dashboard.php" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
            <span class="page-badge"><i class="fa-solid fa-dna"></i> Genetics</span>
        </div>

        <div class="page-header">
            <div class="header-info">
                <h1>Breed <span>Management</span></h1>
                <p>Configure livestock lineages and track breed-specific stock counts.</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Add New Breed
            </button>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert-box alert-<?php echo htmlspecialchars($status); ?>">
                <i class="fa-solid <?php echo ($status == 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?> me-2"></i>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="filters-wrapper">
            <div class="search-container">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" class="search-input" placeholder="Search breeds by name or animal type..." onkeyup="filterTable()">
            </div>
            
            <select class="sort-select" onchange="sortDropdown(this.value)">
                <option value="name_asc">Sort: Breed Name (A-Z)</option>
                <option value="name_desc">Sort: Breed Name (Z-A)</option>
                <option value="count_desc">Sort: Most Animals</option>
                <option value="count_asc">Sort: Least Animals</option>
            </select>
        </div>

        <div class="table-card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 120px;">ID</th>
                            <th>Breed Details</th>
                            <th>Species Association</th>
                            <th>Current Inventory</th>
                            <th style="text-align: center; width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="breed-table">
                        <?php foreach($breed_data as $data): ?>
                        <tr data-id="<?php echo $data['BREED_ID']; ?>" 
                            data-animal-type-id="<?php echo $data['ANIMAL_TYPE_ID']; ?>"
                            data-name="<?php echo htmlspecialchars(strtolower($data['BREED_NAME'])); ?>"
                            data-count="<?php echo $data['ANIMAL_COUNT']; ?>">
                            
                            <td data-label="ID" class="col-id">#<?php echo $data['BREED_ID']; ?></td>
                            
                            <td data-label="Breed Name">
                                <div class="breed-info">
                                    <div class="breed-details">
                                        <h3 class="col-name"><?php echo htmlspecialchars($data['BREED_NAME']); ?></h3>
                                    </div>
                                </div>
                            </td>
                            
                            <td data-label="Animal Type">
                                 <span class="animal-type-tag"><?php echo htmlspecialchars($data['ANIMAL_TYPE_NAME']); ?></span>
                            </td>
                            
                            <td data-label="Animals">
                                <span class="count-badge <?php echo ($data['ANIMAL_COUNT'] == 0) ? 'empty' : ''; ?>">
                                    <?php echo $data['ANIMAL_COUNT']; ?> ACTIVE
                                </span>
                            </td>

                            <td data-label="Actions">
                                <div class="actions">
                                    <button class="action-btn edit" onclick="editBreed(this)" title="Edit Breed"><i class="fa-solid fa-pen-to-square"></i></button>
                                    <button class="action-btn delete" onclick="deleteBreed(this)" title="Delete Breed"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="empty-state" class="empty-state" style="<?php echo empty($breed_data) ? 'display:block' : 'display:none'; ?>">
                    <i class="fa-solid fa-folder-open"></i>
                    <h3>No lineages defined</h3>
                    <p>Start by adding your first animal breed to the system.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Breed</h2>
            </div>
            <div class="modal-body">
                <form id="addBreedForm" method="POST" action="../process/addBreed.php">
                    <div class="form-group">
                        <label class="form-label">Breed Name *</label>
                        <input type="text" class="form-control" id="add_breed_name" name="breed_name" placeholder="e.g. Yorkshire, Duroc" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Associated Species *</label>
                        <select class="form-control" id="add_animal_type" name="animal_type_id" required>
                            <option value="">Select Animal Type</option>
                            <?php foreach($animal_types as $type): ?>
                                <option value="<?php echo $type['ANIMAL_TYPE_ID']; ?>">
                                    <?php echo htmlspecialchars($type['ANIMAL_TYPE_NAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeAddModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddForm()">Save Breed</button>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Breed Details</h2>
            </div>
            <div class="modal-body">
                <form id="editBreedForm" method="POST" action="../process/updateBreeds.php">
                    <input type="hidden" id="edit_breed_id" name="breed_id">
                    <div class="form-group">
                        <label class="form-label">Breed Name *</label>
                        <input type="text" class="form-control" id="edit_breed_name" name="breed_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Associated Species *</label>
                        <select class="form-control" id="edit_animal_type" name="animal_type_id" required>
                            <option value="">Select Animal Type</option>
                            <?php foreach($animal_types as $type): ?>
                                <option value="<?php echo $type['ANIMAL_TYPE_ID']; ?>">
                                    <?php echo htmlspecialchars($type['ANIMAL_TYPE_NAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitEditForm()">Update Changes</button>
            </div>
        </div>
    </div>

    <form id="deleteBreedForm" method="POST" action="../process/deleteBreeds.php" style="display: none;">
        <input type="hidden" id="delete_breed_id" name="breed_id">
    </form>

    <script>
        function sortDropdown(val) {
            const tbody = document.getElementById('breed-table');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort((a, b) => {
                if (val === 'name_asc') return a.dataset.name.localeCompare(b.dataset.name);
                if (val === 'name_desc') return b.dataset.name.localeCompare(a.dataset.name);
                if (val === 'count_desc') return parseInt(b.dataset.count) - parseInt(a.dataset.count);
                if (val === 'count_asc') return parseInt(a.dataset.count) - parseInt(b.dataset.count);
            });
            rows.forEach(row => tbody.appendChild(row));
        }

        function openAddModal() {
            document.getElementById('addBreedForm').reset();
            document.getElementById('addModal').classList.add('show');
        }

        function editBreed(button) {
            const row = button.closest('tr');
            document.getElementById('edit_breed_id').value = row.dataset.id;
            document.getElementById('edit_breed_name').value = row.querySelector('.col-name').textContent.trim();
            document.getElementById('edit_animal_type').value = row.dataset.animalTypeId;
            document.getElementById('editModal').classList.add('show');
        }

        function deleteBreed(button) {
            const row = button.closest('tr');
            const name = row.querySelector('.col-name').textContent.trim();
            if (confirm(`Permanently remove breed: ${name}?`)) {
                document.getElementById('delete_breed_id').value = row.dataset.id;
                document.getElementById('deleteBreedForm').submit();
            }
        }

        function filterTable() {
            const term = document.querySelector('.search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#breed-table tr');
            let visibleCount = 0;
            rows.forEach(row => {
                const match = row.innerText.toLowerCase().includes(term);
                row.style.display = match ? '' : 'none';
                if(match) visibleCount++;
            });
            document.getElementById('empty-state').style.display = visibleCount === 0 ? 'block' : 'none';
        }

        function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }
        function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }
        function submitAddForm() { document.getElementById('addBreedForm').submit(); }
        function submitEditForm() { document.getElementById('editBreedForm').submit(); }

        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) { closeAddModal(); closeEditModal(); }
        });

        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                document.querySelectorAll('.alert-box').forEach(el => el.style.display = 'none');
            }, 5000);
        });
    </script>
</body>
</html>