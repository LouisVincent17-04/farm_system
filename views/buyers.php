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

// --- 1. HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['delete_id'])) {
            $delId = $_POST['delete_id'];
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
        else {
            $name = trim($_POST['full_name']);
            $contact = trim($_POST['contact_no']);
            $addr = trim($_POST['address']);
            $id = $_POST['buyer_id'] ?? null;

            if ($id) {
                $stmt = $conn->prepare("UPDATE buyers SET FULL_NAME=?, CONTACT_NO=?, ADDRESS=? WHERE BUYER_ID=?");
                $stmt->execute([$name, $contact, $addr, $id]);
                $_SESSION['flash_success'] = "Buyer updated successfully.";
            } else {
                $stmt = $conn->prepare("INSERT INTO buyers (FULL_NAME, CONTACT_NO, ADDRESS) VALUES (?, ?, ?)");
                $stmt->execute([$name, $contact, $addr]);
                $_SESSION['flash_success'] = "New buyer added successfully.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Error: " . $e->getMessage();
    }
    header("Location: buyers.php");
    exit();
}

// --- 2. FETCH DATA ---
$buyers = $conn->query("SELECT * FROM buyers WHERE IS_ACTIVE = 1 ORDER BY FULL_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);

$success_msg = $_SESSION['flash_success'] ?? "";
$error_msg = $_SESSION['flash_error'] ?? "";
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Customer Management</title>
    
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
            --border-active:  rgba(56,189,248,0.5); /* Sky Blue Accent */
            --sky:            #38bdf8;
            --sky-dim:        rgba(56,189,248,0.12);
            --sky-glow:       rgba(56,189,248,0.25);
            --green:          #22c55e;
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(56,189,248,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── HEADER & TOP BAR ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500;
            padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); transition: all var(--transition);
        }
        .back-link:hover { color: var(--text-primary); border-color: var(--border-active); background: var(--bg-hover); }

        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap;
        }
        .header-info h1 {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .header-info h1 span {
            background: linear-gradient(135deg, var(--sky), #0ea5e9);
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
        .btn-primary { background: var(--sky); color: #000; }
        .btn-primary:hover { background: #7dd3fc; box-shadow: 0 0 16px var(--sky-glow); transform: translateY(-1px); }
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

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
        }
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 800px; }
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

        .col-name { font-weight: 600; color: #fff; font-size: 1rem; }
        .col-contact { font-family: var(--font-mono); color: var(--sky); font-weight: 500; }
        .col-address { color: var(--text-secondary); line-height: 1.5; font-size: 0.85rem; }

        /* Actions */
        .actions { display: flex; justify-content: center; gap: 8px; }
        .action-btn {
            width: 32px; height: 32px; border-radius: 6px;
            border: 1px solid var(--border); background: var(--bg-elevated);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all var(--transition); color: var(--text-secondary);
        }
        .action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .action-btn.edit:hover { color: var(--sky); border-color: var(--sky); }
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
            border-radius: var(--radius-xl); width: 100%; max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); overflow: hidden;
            animation: modalZoom 0.2s ease-out;
        }
        @keyframes modalZoom { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--sky); }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); }

        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.25rem; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        .form-control {
            width: 100%; padding: 10px 12px; background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: 8px; font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .form-control:focus { border-color: var(--sky); box-shadow: 0 0 0 3px var(--sky-glow); }
        textarea.form-control { resize: none; min-height: 80px; }

        /* ─── ALERTS ─── */
        .alert { padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 1.5rem; text-align: center; font-weight: 600; font-size: 0.9rem; border: 1px solid transparent; }
        .alert-success { background: var(--sky-dim); border-color: rgba(56,189,248,0.2); color: var(--sky); }
        .alert-error { background: rgba(239, 68, 68, 0.12); border-color: rgba(239, 68, 68, 0.2); color: var(--red); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-info { text-align: center; width: 100%; }
            .header-buttons { width: 100%; }
            .header-buttons .btn { width: 100%; }
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
        }
    </style>
</head>
<body>

<div class="container">
    
    <div class="top-bar">
        <a href="admin_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-users"></i> CRM System</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1>Buyer <span>Management</span></h1>
            <p>Maintain customer profiles and track sales associations.</p>
        </div>
        <div class="header-buttons">
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fa-solid fa-plus"></i> Add New Buyer
            </button>
        </div>
    </div>

    <?php if($error_msg): ?> <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation me-2"></i><?= $error_msg ?></div> <?php endif; ?>
    <?php if($success_msg): ?> <div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?= $success_msg ?></div> <?php endif; ?>

    <div class="filters-wrapper">
        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" class="search-input" placeholder="Search by name, contact, or address..." onkeyup="filterTable()">
        </div>
        
        <select class="sort-select" onchange="sortDropdown(this.value)">
            <option value="name_asc">Sort: Name (A-Z)</option>
            <option value="name_desc">Sort: Name (Z-A)</option>
            <option value="newest">Sort: Newest Added</option>
            <option value="oldest">Sort: Oldest Added</option>
        </select>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Contact Number</th>
                        <th>Registered Address</th>
                        <th style="text-align: center; width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="buyer-table-body">
                    <?php if(empty($buyers)): ?>
                        <tr id="empty-state-row"><td colspan="4" style="text-align:center; padding:4rem 2rem; color:var(--text-muted);">No active buyers found.</td></tr>
                    <?php else: ?>
                        <?php foreach($buyers as $b): ?>
                        <tr class="buyer-row" data-id="<?= $b['BUYER_ID'] ?>" data-name="<?= htmlspecialchars(strtolower($b['FULL_NAME'])) ?>">
                            <td data-label="Name" class="col-name"><?= htmlspecialchars($b['FULL_NAME']) ?></td>
                            <td data-label="Contact" class="col-contact"><?= htmlspecialchars($b['CONTACT_NO'] ?? 'N/A') ?></td>
                            <td data-label="Address" class="col-address"><?= htmlspecialchars($b['ADDRESS'] ?? 'N/A') ?></td>
                            <td data-label="Actions">
                                <div class="actions">
                                    <button class="action-btn edit" onclick='openModal(<?= json_encode($b, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit Profile">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Are you sure? This action cannot be undone if no history exists.');">
                                        <input type="hidden" name="delete_id" value="<?= $b['BUYER_ID'] ?>">
                                        <button type="submit" class="action-btn delete" title="Remove Buyer">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
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
</div>

<div id="buyerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add Buyer</h2>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="buyer_id" id="buyer_id">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" required placeholder="e.g. Juan Dela Cruz">
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_no" id="contact_no" class="form-control" placeholder="e.g. 09123456789">
                </div>
                <div class="form-group">
                    <label class="form-label">Complete Address</label>
                    <textarea name="address" id="address" class="form-control" rows="3" placeholder="Barangay, City, Province"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Profile</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(data = null) {
        const modal = document.getElementById('buyerModal');
        modal.classList.add('show');
        if(data) {
            document.getElementById('modalTitle').innerText = 'Edit Customer Profile';
            document.getElementById('buyer_id').value = data.BUYER_ID;
            document.getElementById('full_name').value = data.FULL_NAME;
            document.getElementById('contact_no').value = data.CONTACT_NO;
            document.getElementById('address').value = data.ADDRESS;
        } else {
            document.getElementById('modalTitle').innerText = 'Register New Buyer';
            document.getElementById('buyer_id').value = '';
            document.querySelector('#buyerModal form').reset();
        }
    }
    
    function closeModal() { document.getElementById('buyerModal').classList.remove('show'); }
    
    window.onclick = function(e) { if (e.target.classList.contains('modal')) closeModal(); }

    function sortDropdown(val) {
        const tbody = document.getElementById('buyer-table-body');
        const rows = Array.from(tbody.querySelectorAll('.buyer-row'));
        rows.sort((a, b) => {
            if (val === 'name_asc') return a.dataset.name.localeCompare(b.dataset.name);
            if (val === 'name_desc') return b.dataset.name.localeCompare(a.dataset.name);
            const idA = parseInt(a.dataset.id) || 0;
            const idB = parseInt(b.dataset.id) || 0;
            return val === 'newest' ? idB - idA : idA - idB;
        });
        rows.forEach(row => tbody.appendChild(row));
    }

    function filterTable() {
        const term = document.querySelector('.search-input').value.toLowerCase();
        const rows = document.querySelectorAll('.buyer-row');
        let count = 0;
        rows.forEach(row => {
            const match = row.textContent.toLowerCase().includes(term);
            row.style.display = match ? '' : 'none';
            if(match) count++;
        });
        const empty = document.getElementById('empty-state-row');
        if(empty) empty.style.display = count === 0 ? '' : 'none';
    }

    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(el => el.style.opacity = '0');
            setTimeout(() => { document.querySelectorAll('.alert').forEach(el => el.style.display = 'none'); }, 500);
        }, 4000);
    });
</script>

</body>
</html>