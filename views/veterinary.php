<?php
// ../views/veterinary.php
error_reporting(0);
ini_set('display_errors', 0);
$page="admin_dashboard";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('veterinary');
include '../common/navbar.php';
include '../config/Queries.php';
include '../functions/getInitialsFunction.php';
include '../common/chat_support.php';

if($_SESSION['user']['USER_TYPE'] < 3)
{
    echo "<script>alert('Access denied.'); window.location.href = 'admin_dashboard.php';</script>";
    exit();
}

// Retrieve directly from VETERINARIANS table
$sql = "SELECT VET_ID, FULL_NAME, CONTACT_INFO FROM VETERINARIANS ORDER BY VET_ID DESC";
$vet_data = retrieveData($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Veterinary Management System</title>
    
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
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR & HEADER ─── */
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
            color: var(--purple); background: var(--purple-dim); border: 1px solid rgba(139,92,246,0.2);
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
            background: linear-gradient(135deg, var(--purple), #7c3aed);
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
        .btn-primary { background: var(--purple); color: #fff; }
        .btn-primary:hover { background: #7c3aed; box-shadow: 0 0 16px var(--purple-glow); transform: translateY(-2px); }
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        /* ─── SEARCH ─── */
        .search-container { position: relative; margin-bottom: 1.5rem; }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; height: 18px; }
        .search-input {
            width: 100%; padding: 14px 14px 14px 2.8rem; background: var(--bg-surface);
            border: 1px solid var(--border); border-radius: var(--radius-lg);
            color: var(--text-primary); font-size: 1rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .search-input:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow); background: var(--bg-hover); }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
        }
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 800px; }
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

        .vet-info { display: flex; align-items: center; gap: 1rem; }
        .vet-avatar {
            width: 2.8rem; height: 2.8rem; background: linear-gradient(135deg, var(--purple), #6d28d9);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1rem; color: #fff; border: 2px solid rgba(255,255,255,0.1);
        }
        .vet-details h3 { font-size: 1.05rem; font-weight: 600; color: #fff; margin: 0; }
        .contact-text { color: var(--text-secondary); font-family: var(--font-mono); font-size: 0.85rem; }

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
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); overflow: hidden;
        }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--purple); }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); }

        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.25rem; }
        .form-group:last-child { margin-bottom: 0; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        .form-control {
            width: 100%; padding: 10px 12px; background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: 8px; font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .form-control:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); display: none; }
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
            .vet-info { justify-content: flex-end; width: 100%; }
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
            <span class="page-badge"><i class="fa-solid fa-stethoscope"></i> Clinical Staff</span>
        </div>

        <div class="page-header">
            <div class="header-info">
                <h1>Veterinary <span>Management</span></h1>
                <p>Manage your professional veterinary team and clinical contacts.</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fa-solid fa-user-md"></i> Add Veterinarian
            </button>
        </div>

        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" class="search-input" placeholder="Search veterinarians by name or contact..." onkeyup="filterTable()">
        </div>

        <div class="table-card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Practitioner Details</th>
                            <th>Contact Information</th>
                            <th style="text-align: center; width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="veterinarian-table">
                        <?php foreach ($vet_data as $vet): ?>
                        <tr data-id="<?php echo $vet['VET_ID']; ?>" 
                            data-name="<?php echo htmlspecialchars($vet['FULL_NAME']); ?>" 
                            data-contact="<?php echo htmlspecialchars($vet['CONTACT_INFO']); ?>">
                            <td data-label="Veterinarian">
                                <div class="vet-info">
                                    <div class="vet-avatar"><?php echo getInitials($vet['FULL_NAME']); ?></div>
                                    <div class="vet-details">
                                        <h3><?php echo htmlspecialchars($vet['FULL_NAME']); ?></h3>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Contact">
                                <div class="contact-text">
                                    <i class="fa-solid fa-phone-alt me-2" style="font-size: 0.75rem; opacity: 0.5;"></i>
                                    <?php echo !empty($vet['CONTACT_INFO']) ? htmlspecialchars($vet['CONTACT_INFO']) : "Not Set"; ?>
                                </div>
                            </td>
                            <td data-label="Actions">
                                <div class="actions">
                                    <button class="action-btn edit" onclick="editVet(this)" title="Edit Profile">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button class="action-btn delete" onclick="deleteVet(this)" title="Remove Account">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="empty-state" class="empty-state" style="<?php echo empty($vet_data) ? 'display:block' : 'display:none'; ?>">
                    <i class="fa-solid fa-user-md"></i>
                    <h3>No medical staff found</h3>
                    <p>Register your first veterinarian to begin managing clinical records.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Add New Veterinarian</h2>
            </div>
            <div class="modal-body">
                <form id="vet-form" method="POST" action="">
                    <input type="hidden" id="vet_id" name="user_id">
                    
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="name" name="fullName" placeholder="Dr. Louis Vincent" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Number *</label>
                        <input type="text" class="form-control" id="contact" name="contactInfo" placeholder="e.g. 09657877713" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn btn-primary btn-save" onclick="submitForm()">Save Specialist</button>
            </div>
        </div>
    </div>

    <form id="deleteVetForm" method="POST" action="../process/deleteVeterinarian.php" style="display: none;">
        <input type="hidden" id="delete_vet_id" name="user_id">
    </form>

    <script>
        function openAddModal() {
            document.getElementById('modal-title').textContent = 'Add New Veterinarian';
            document.querySelector('.btn-save').textContent = 'Add Veterinarian';
            const form = document.getElementById('vet-form');
            form.action = '../process/addVeterinarian.php'; 
            form.reset();
            document.getElementById('vet_id').value = '';
            document.getElementById('modal').classList.add('show');
        }

        function editVet(button) {
            const row = button.closest('tr');
            const id = row.getAttribute('data-id');
            const name = row.getAttribute('data-name');
            const contact = row.getAttribute('data-contact');
            
            document.getElementById('modal-title').textContent = 'Edit Professional Details';
            document.querySelector('.btn-save').textContent = 'Update Profile';
            const form = document.getElementById('vet-form');
            form.action = '../process/editVeterinarian.php'; 
            
            document.getElementById('vet_id').value = id;
            document.getElementById('name').value = name;
            document.getElementById('contact').value = contact;
            document.getElementById('modal').classList.add('show');
        }

        function submitForm() {
            const form = document.getElementById('vet-form');
            const name = document.getElementById('name').value.trim();
            const contact = document.getElementById('contact').value.trim();

            if (!name || !contact) { alert('Please fill in all required fields'); return; }
            const actionText = document.querySelector('.btn-save').textContent;
            if (confirm(`Are you sure you want to ${actionText.toLowerCase()}?`)) { form.submit(); }
        }

        function deleteVet(button) {
            const row = button.closest('tr');
            const id = row.getAttribute('data-id');
            if (confirm('Permanently remove this veterinarian from the system?')) {
                document.getElementById('delete_vet_id').value = id;
                document.getElementById('deleteVetForm').submit();
            }
        }

        function closeModal() { document.getElementById('modal').classList.remove('show'); }

        function filterTable() {
            const term = document.querySelector('.search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#veterinarian-table tr');
            let count = 0;

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                const match = text.includes(term);
                row.style.display = match ? '' : 'none';
                if(match) count++;
            });

            document.getElementById('empty-state').style.display = (count === 0) ? 'block' : 'none';
        }

        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) closeModal();
        });

        document.addEventListener('DOMContentLoaded', filterTable);
    </script>
</body>
</html>