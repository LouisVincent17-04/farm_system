<?php
// views/concerns.php
error_reporting(0);
ini_set('display_errors', 0);

$page = "farm"; 
include '../config/Connection.php';
include '../security/checkAccess.php';
checkAccess('concerns');

include '../common/navbar.php';
include '../common/chat_support.php';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // Fetch all concerns with user names
    $sql = "SELECT c.*, u.FULL_NAME 
            FROM concerns c
            LEFT JOIN users u ON c.USER_ID = u.USER_ID
            ORDER BY c.CREATED_AT DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $all_concerns = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $all_concerns = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Concerns | FarmPro</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <style>
        :root {
            --bg-base: #080f1a; --bg-surface: #0d1829; --bg-elevated: #111f35; --bg-hover: #162540;
            --border: rgba(255,255,255,0.07);
            --orange: #f97316; --orange-dim: rgba(249,115,22,0.15);
            --blue: #3b82f6; --blue-dim: rgba(59,130,246,0.15);
            --red: #ef4444; --red-dim: rgba(239,68,68,0.15);
            --emerald: #10b981;
            --text-primary: #f1f5f9; --text-secondary: #94a3b8; --text-muted: #475569;
            --radius-md: 10px; --radius-xl: 20px; --transition: 0.2s ease;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg-base); color: var(--text-primary); min-height: 100vh; padding-bottom: 60px; }
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        .top-bar { display: flex; justify-content: space-between; margin-bottom: 2rem; }
        .page-title { font-size: 2rem; font-weight: 700; color: #fff; margin: 0; }
        .page-title span { color: var(--orange); }

        .table-wrap { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--bg-elevated); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; padding: 1.25rem 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 0.95rem; vertical-align: top;}
        tr:hover { background: rgba(255,255,255,0.01); }

        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-block; white-space: nowrap;}
        .b-pending { background: var(--orange-dim); color: var(--orange); border: 1px solid rgba(249,115,22,0.3); }
        .b-read { background: var(--blue-dim); color: var(--blue); border: 1px solid rgba(59,130,246,0.3); }
        .b-archived { background: rgba(148,163,184,0.15); color: #94a3b8; border: 1px solid rgba(148,163,184,0.3); }

        .action-btn { background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 6px; border-radius: 6px; transition: var(--transition); font-size: 1rem; }
        .action-btn:hover { background: var(--bg-hover); color: #fff; }
        .action-btn.del:hover { color: var(--red); background: var(--red-dim); }
        .action-btn.read:hover { color: var(--emerald); background: rgba(16,185,129,0.1); }
        .action-btn.arch:hover { color: var(--blue); background: var(--blue-dim); }

        /* Modal Styles */
        .modal-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index: 1000; display: none; align-items:center; justify-content:center; backdrop-filter: blur(5px);}
        .modal-overlay.active { display: flex; }
        .modal-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); width: 100%; max-width: 600px; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
        .modal-title { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .modal-title i { color: var(--orange); }
        
        .view-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem; }
        .view-item { display: flex; flex-direction: column; gap: 4px; }
        .view-item.full { grid-column: 1 / -1; }
        .view-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; }
        .view-value { font-size: 0.95rem; color: #fff; }
        .view-desc-box { background: var(--bg-elevated); border: 1px solid var(--border); padding: 1rem; border-radius: var(--radius-md); font-size: 0.95rem; color: var(--text-secondary); line-height: 1.6; min-height: 100px; white-space: pre-wrap; }

        .btn-flex { display: flex; gap: 10px; justify-content: flex-end; margin-top: 2rem; }
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; border: none; font-family: inherit; transition: var(--transition); display: flex; align-items: center; gap: 8px; }
        .btn-cancel { background: var(--bg-elevated); color: var(--text-primary); border: 1px solid var(--border); }
        .btn-cancel:hover { background: var(--bg-hover); }
        .btn-action { background: rgba(16,185,129,0.1); color: var(--emerald); border: 1px solid rgba(16,185,129,0.3); }
        .btn-action:hover { background: var(--emerald); color: #000; }
    </style>
</head>
<body>

<div class="container">
    <div class="top-bar">
        <h1 class="page-title">Manage <span>Concerns</span></h1>
        <a href="farm_dashboard.php" style="color:var(--text-secondary); text-decoration:none;"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Category & Subject</th>
                    <th>Details</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($all_concerns)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">No concerns found.</td></tr>
                <?php else: foreach($all_concerns as $c): 
                    $b_class = 'b-pending';
                    if($c['STATUS'] == 'Read') $b_class = 'b-read';
                    if($c['STATUS'] == 'Archived') $b_class = 'b-archived';
                ?>
                    <tr>
                        <td style="font-family:'DM Mono'; color:var(--text-secondary); font-size:0.85rem;"><?= date('M d, Y', strtotime($c['CREATED_AT'])) ?></td>
                        <td style="color:#fff; font-weight:600;"><?= htmlspecialchars($c['FULL_NAME'] ?? 'Unknown User') ?></td>
                        <td>
                            <div style="font-weight:700; color:#fff;"><?= htmlspecialchars($c['SUBJECT']) ?></div>
                            <div style="font-size:0.8rem; color:var(--orange);"><?= htmlspecialchars($c['CATEGORY']) ?></div>
                        </td>
                        <td style="color:var(--text-secondary); font-size:0.9rem; max-width: 300px;"><?= mb_strimwidth(htmlspecialchars($c['DESCRIPTION']), 0, 50, "...") ?></td>
                        <td><?= htmlspecialchars($c['PRIORITY']) ?></td>
                        <td><span class="badge <?= $b_class ?>"><?= htmlspecialchars($c['STATUS']) ?></span></td>
                        <td style="text-align:right; white-space: nowrap;">
                            
                            <button class="action-btn" title="View Details" onclick='openViewModal(<?= json_encode($c) ?>)'><i class="fa-solid fa-eye"></i></button>

                            <?php if($c['STATUS'] == 'Pending'): ?>
                                <button class="action-btn read" title="Mark as Read" onclick="updateAction(<?= $c['CONCERN_ID'] ?>, 'read')"><i class="fa-solid fa-check-double"></i></button>
                                <button class="action-btn del" title="Delete" onclick="deleteConcern(<?= $c['CONCERN_ID'] ?>)"><i class="fa-solid fa-trash"></i></button>
                            <?php endif; ?>

                            <?php if($c['STATUS'] != 'Archived'): ?>
                                <button class="action-btn arch" title="Archive" onclick="updateAction(<?= $c['CONCERN_ID'] ?>, 'archive')"><i class="fa-solid fa-box-archive"></i></button>
                            <?php endif; ?>
                            
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="viewModal">
    <div class="modal-card">
        <div class="modal-title"><i class="fa-solid fa-clipboard-question"></i> Concern Details</div>
        
        <div class="view-grid">
            <div class="view-item">
                <span class="view-label">Submitted By</span>
                <span class="view-value" id="view_user"></span>
            </div>
            <div class="view-item">
                <span class="view-label">Date Submitted</span>
                <span class="view-value" id="view_date" style="font-family:var(--font-mono); color:var(--text-secondary);"></span>
            </div>
            <div class="view-item">
                <span class="view-label">Category</span>
                <span class="view-value" id="view_category"></span>
            </div>
            <div class="view-item">
                <span class="view-label">Priority / Status</span>
                <span class="view-value"><span id="view_priority" style="font-weight:700;"></span> &nbsp;|&nbsp; <span id="view_status" style="color:var(--text-muted);"></span></span>
            </div>
            <div class="view-item full">
                <span class="view-label">Subject</span>
                <span class="view-value" id="view_subject" style="font-size: 1.1rem; font-weight:700; color:var(--orange);"></span>
            </div>
            <div class="view-item full">
                <span class="view-label">Full Description</span>
                <div class="view-desc-box" id="view_desc"></div>
            </div>
        </div>

        <div class="btn-flex">
            <button type="button" class="btn btn-cancel" onclick="closeModal()">Close</button>
            <button type="button" class="btn btn-action" id="btnMarkRead" style="display:none;">
                <i class="fa-solid fa-check-double"></i> Mark as Read
            </button>
        </div>
    </div>
</div>

<script>
    function updateAction(id, action) {
        if(!confirm(`Are you sure you want to mark this as ${action}?`)) return;
        const endpoint = action === 'read' ? '../process/markAsRead.php' : '../process/archiveConcern.php';
        let fd = new FormData(); fd.append('id', id);
        fetch(endpoint, { method: 'POST', body: fd })
        .then(r=>r.json()).then(res => {
            if(res.success) location.reload();
            else alert(res.message);
        });
    }

    function deleteConcern(id) {
        if(!confirm('Permanently delete this concern? This cannot be undone.')) return;
        let fd = new FormData(); fd.append('id', id);
        fetch('../process/deleteConcern.php', { method: 'POST', body: fd })
        .then(r=>r.json()).then(res => {
            if(res.success) location.reload();
            else alert(res.message);
        });
    }

    function openViewModal(data) {
        document.getElementById('view_user').textContent = data.FULL_NAME || 'Unknown User';
        document.getElementById('view_date').textContent = new Date(data.CREATED_AT).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        document.getElementById('view_category').textContent = data.CATEGORY;
        document.getElementById('view_priority').textContent = data.PRIORITY;
        document.getElementById('view_status').textContent = data.STATUS;
        document.getElementById('view_subject').textContent = data.SUBJECT;
        document.getElementById('view_desc').textContent = data.DESCRIPTION;
        
        // Handle the dynamic "Mark as Read" button inside the modal
        const btnRead = document.getElementById('btnMarkRead');
        if(data.STATUS === 'Pending') {
            btnRead.style.display = 'flex';
            btnRead.onclick = () => updateAction(data.CONCERN_ID, 'read');
        } else {
            btnRead.style.display = 'none';
        }

        document.getElementById('viewModal').classList.add('active');
    }

    function closeModal() { 
        document.getElementById('viewModal').classList.remove('active'); 
    }
</script>
</body>
</html>