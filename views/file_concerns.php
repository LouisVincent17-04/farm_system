<?php
// views/file_concerns.php
error_reporting(0);
ini_set('display_errors', 0);

$page = "farm"; 
include '../config/Connection.php';
include '../security/checkAccess.php';
checkAccess('file_concerns');

include '../common/navbar.php';
include '../common/chat_support.php';

$user_id = $_SESSION['user']['USER_ID'] ?? 1;

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // Fetch user's recent concerns
    $history_sql = "SELECT * FROM concerns WHERE USER_ID = ? ORDER BY CREATED_AT DESC LIMIT 20";
    $hist_stmt = $conn->prepare($history_sql);
    $hist_stmt->execute([$user_id]);
    $my_concerns = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $my_concerns = [];
}

$categories = [
    "Material/Supply Request",
    "Facility Maintenance",
    "Animal Health Alert",
    "Operational Bottleneck",
    "HR / Administrative",
    "Safety / Security",
    "Other"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Raise a Concern | FarmPro</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        :root {
            --bg-base: #080f1a; --bg-surface: #0d1829; --bg-elevated: #111f35; --bg-hover: #162540;
            --border: rgba(255,255,255,0.07);
            --orange: #f97316; --orange-dim: rgba(249,115,22,0.12); --orange-glow: rgba(249,115,22,0.25);
            --text-primary: #f1f5f9; --text-secondary: #94a3b8; --text-muted: #475569;
            --radius-md: 10px; --radius-xl: 20px; --transition: 0.2s ease;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg-base); color: var(--text-primary); min-height: 100vh; padding-bottom: 60px; background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(249,115,22,0.06) 0%, transparent 60%); }
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2.5rem; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: var(--text-secondary); font-size: 0.875rem; font-weight: 500; padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border); border-radius: var(--radius-md); transition: var(--transition); }
        .back-link:hover { color: #fff; border-color: var(--orange); }

        .page-title { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; }
        .page-title span { background: linear-gradient(135deg, var(--orange), #c2410c); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .page-desc { color: var(--text-secondary); margin-bottom: 2rem; }

        .main-grid { display: grid; grid-template-columns: 400px 1fr; gap: 1.5rem; align-items: start; }

        .form-panel { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; position: sticky; top: 1.5rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: block; color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.05em; }
        .form-control, .form-select { width: 100%; padding: 12px 14px; background: var(--bg-elevated); border: 1px solid var(--border); border-radius: var(--radius-md); color: #fff; font-family: inherit; font-size: 0.95rem; transition: var(--transition); outline: none;}
        .form-control:focus, .form-select:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-glow); background: var(--bg-hover); }
        textarea.form-control { resize: vertical; min-height: 120px; }
        
        .btn-submit { width: 100%; padding: 14px; background: var(--orange); border: none; border-radius: var(--radius-md); color: #fff; font-weight: 700; font-size: 1rem; cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-submit:hover:not(:disabled) { background: #ea580c; transform: translateY(-2px); box-shadow: 0 5px 15px var(--orange-glow); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

        .history-panel { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; }
        .history-header { padding: 1.5rem; border-bottom: 1px solid var(--border); font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--bg-base); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; padding: 1rem; text-align: left; }
        td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 0.95rem; }
        tr:hover { background: rgba(255,255,255,0.01); }

        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
        .b-pending { background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); }
        .b-read { background: rgba(59,130,246,0.15); color: #3b82f6; border: 1px solid rgba(59,130,246,0.3); }
        .b-archived { background: rgba(148,163,184,0.15); color: #94a3b8; border: 1px solid rgba(148,163,184,0.3); }
        
        .toast { position: fixed; top: 20px; right: 20px; background: var(--bg-elevated); color: #fff; padding: 1rem 1.5rem; border-radius: 8px; border-left: 4px solid var(--orange); box-shadow: var(--shadow-md); z-index: 9999; font-weight: 500; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s forwards; }
        @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }

        @media (max-width: 1024px) { .main-grid { grid-template-columns: 1fr; } .form-panel { position: relative; top: 0; } }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">
    <div class="top-bar">
        <a href="farm_dashboard.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <h1 class="page-title">Raise a <span>Concern / Request</span></h1>
    <p class="page-desc">Submit material requests, report operational issues, or raise safety concerns directly to management.</p>

    <div class="main-grid">
        
        <div class="form-panel">
            <form id="concernForm">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select id="category" name="category" class="form-select" required>
                        <option value="">-- Select --</option>
                        <?php foreach($categories as $cat) echo "<option value=\"$cat\">$cat</option>"; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select id="priority" name="priority" class="form-select" required>
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                        <option value="Critical">Critical</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Subject</label>
                    <input type="text" id="subject" name="subject" class="form-control" placeholder="Brief summary..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Full Description</label>
                    <textarea id="description" name="description" class="form-control" placeholder="Provide full details, required quantities, or specific locations..." required></textarea>
                </div>
                <button type="button" class="btn-submit" id="btnSubmit" onclick="submitConcern()">
                    <i class="fa-solid fa-paper-plane"></i> Submit Ticket
                </button>
            </form>
        </div>

        <div class="history-panel">
            <div class="history-header"><i class="fa-solid fa-clock-rotate-left" style="color:var(--orange);"></i> My Submissions</div>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Subject & Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($my_concerns)): ?>
                            <tr><td colspan="4" style="text-align:center; padding: 3rem; color: var(--text-muted);">No concerns filed yet.</td></tr>
                        <?php else: foreach($my_concerns as $c): 
                            $b_class = 'b-pending';
                            if($c['STATUS'] == 'Read') $b_class = 'b-read';
                            if($c['STATUS'] == 'Archived') $b_class = 'b-archived';
                        ?>
                            <tr>
                                <td style="font-family:'DM Mono'; color:var(--text-secondary); font-size:0.85rem;"><?= date('M d, Y', strtotime($c['CREATED_AT'])) ?></td>
                                <td>
                                    <div style="font-weight:700; color:#fff;"><?= htmlspecialchars($c['SUBJECT']) ?></div>
                                    <div style="font-size:0.8rem; color:var(--text-muted);"><?= htmlspecialchars($c['CATEGORY']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($c['PRIORITY']) ?></td>
                                <td><span class="badge <?= $b_class ?>"><?= htmlspecialchars($c['STATUS']) ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function showToast(msg, isError = false) {
        const t = document.createElement('div');
        t.className = 'toast';
        if(isError) t.style.borderLeftColor = 'var(--red)';
        t.innerHTML = `<i class="fa-solid ${isError ? 'fa-xmark' : 'fa-check'}"></i> ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 4000);
    }

    function submitConcern() {
        const form = document.getElementById('concernForm');
        if(!form.checkValidity()) { form.reportValidity(); return; }

        const btn = document.getElementById('btnSubmit');
        const ogText = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

        // --- UPDATED URL TO CAMELCASE ---
        fetch('../process/addConcern.php', {
            method: 'POST', body: new FormData(form)
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                showToast("Concern submitted successfully!");
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast(res.message, true);
                btn.disabled = false; btn.innerHTML = ogText;
            }
        }).catch(err => { showToast("Network Error", true); btn.disabled = false; btn.innerHTML = ogText; });
    }
</script>
</body>
</html>