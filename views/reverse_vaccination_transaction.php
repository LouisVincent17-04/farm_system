<?php
// views/reverse_vaccination_transaction.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
$page = "transactions"; 

include '../config/Connection.php';
include '../security/checkAccess.php';
include '../common/navbar.php';
include '../common/chat_support.php';

$batches = [];
$message = "";

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // FETCH ALL BATCHES for the dropdown
    // We group by VACCINATION_DATE to identify batches
    $sql = "SELECT 
                v.VACCINATION_DATE,
                MAX(v.ADMINISTERED_BY) as ADMINISTERED_BY,
                MAX(v.VET_NAME) as VET_NAME,
                COUNT(v.VACCINATION_ID) as record_count,
                GROUP_CONCAT(DISTINCT vac.SUPPLY_NAME SEPARATOR ', ') as items_used,
                SUM(v.QUANTITY) as total_qty,
                SUM(v.VACCINE_COST + v.VACCINATION_COST) as total_cost
            FROM vaccination_records v
            LEFT JOIN vaccines vac ON v.ITEM_ID = vac.SUPPLY_ID
            GROUP BY v.VACCINATION_DATE
            ORDER BY v.VACCINATION_DATE DESC 
            LIMIT 100"; 

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reverse Vaccination | FarmPro</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            
            --red:            #ef4444;
            --red-dim:        rgba(239,68,68,0.12);
            --red-glow:       rgba(239,68,68,0.25);
            --emerald:        #10b981;
            --amber:          #f59e0b;
            
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --shadow-md:      0 4px 16px rgba(0,0,0,0.4);
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font); background: var(--bg-base); color: var(--text-primary);
            min-height: 100vh; padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(239,68,68,0.08) 0%, transparent 60%);
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500;
            padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); transition: all var(--transition);
        }
        .back-link:hover { color: var(--text-primary); border-color: rgba(255,255,255,0.2); background: var(--bg-hover); }

        .page-badge {
            display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem;
            font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--red); background: var(--red-dim); border: 1px solid rgba(239,68,68,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { text-align: center; margin-bottom: 3rem; }
        .page-title {
            font-size: clamp(2rem, 4vw, 2.8rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; letter-spacing: -0.02em; display: flex; align-items: center; justify-content: center; gap: 12px;
        }
        .page-title span { background: linear-gradient(135deg, var(--red), #991b1b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .page-desc { color: var(--text-secondary); font-size: 1.05rem; }

        /* ─── LAYOUT GRID ─── */
        .main-grid { display: grid; grid-template-columns: 1fr 1.25fr; gap: 2rem; align-items: start; }

        /* ─── CONTROL PANEL (LEFT) ─── */
        .control-panel {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-md);
        }
        .panel-title { font-size: 1.15rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 10px;}
        .panel-title i { color: var(--emerald); }
        
        .form-group { margin-top: 1.5rem; display: flex; flex-direction: column; gap: 6px;}
        .form-label { color: var(--text-secondary); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;}
        
        .form-select {
            width: 100%; padding: 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; transition: var(--transition); outline: none; box-sizing: border-box; font-family: var(--font);
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; cursor: pointer;
        }
        .form-select:focus { border-color: var(--emerald); box-shadow: 0 0 0 3px rgba(16,185,129,0.15); background: var(--bg-hover); }

        .alert-box {
            background: var(--red-dim); border: 1px solid rgba(239,68,68,0.3); border-radius: var(--radius-md);
            padding: 1rem 1.25rem; font-size: 0.9rem; color: #fca5a5; margin-top: 1.5rem;
            display: flex; align-items: flex-start; gap: 10px; line-height: 1.5;
        }
        .alert-box i { color: var(--red); font-size: 1.1rem; margin-top: 2px;}

        /* ─── DETAILS PANEL (RIGHT) ─── */
        .details-panel {
            background: var(--bg-surface); border: 1px solid rgba(239,68,68,0.3);
            border-radius: var(--radius-xl); padding: 2rem; box-shadow: 0 10px 30px rgba(239,68,68,0.1);
            position: relative; overflow: hidden; transition: var(--transition);
            opacity: 0.3; pointer-events: none;
        }
        .details-panel.active { opacity: 1; pointer-events: auto; }
        .details-panel::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: repeating-linear-gradient(45deg, var(--red), var(--red) 10px, #7f1d1d 10px, #7f1d1d 20px);
        }

        .details-title { font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 8px;}
        .details-title i { color: var(--red); }

        .info-grid { display: flex; flex-direction: column; gap: 10px; }
        .info-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md);
        }
        .info-lbl { color: var(--text-secondary); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;}
        .info-val { color: #fff; font-weight: 700; font-family: var(--font-mono); font-size: 1.05rem;}
        .info-val.highlight { color: var(--emerald); }

        .btn-reverse {
            width: 100%; margin-top: 2rem; padding: 16px; background: var(--red); border: none;
            border-radius: var(--radius-md); color: #fff; font-weight: 800; font-size: 1.05rem; font-family: var(--font);
            cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 10px; text-transform: uppercase; letter-spacing: 0.05em;
        }
        .btn-reverse:disabled { opacity: 0.5; cursor: not-allowed; background: var(--bg-elevated); color: var(--text-muted); border: 1px solid var(--border);}
        .btn-reverse:hover:not(:disabled) { background: #dc2626; box-shadow: 0 8px 25px var(--red-glow); transform: translateY(-2px); }

        /* Toast Notifications */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out; display: flex; align-items: center; gap: 8px;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        .empty-state { text-align: center; padding: 3rem; color: var(--text-muted); background: var(--bg-surface); border: 1px dashed var(--border); border-radius: var(--radius-xl); grid-column: 1 / -1;}

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .main-grid { grid-template-columns: 1fr; }
            .page-title { font-size: 2rem; }
        }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">
    
    <div class="top-bar">
        <a href="transactions.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Transactions
        </a>
        <span class="page-badge"><i class="fa-solid fa-triangle-exclamation"></i> Danger Zone</span>
    </div>

    <div class="page-header">
        <h1 class="page-title"><i class="fa-solid fa-clock-rotate-left" style="color:var(--red);"></i> Admin <span>Undo</span> Vaccination</h1>
        <p class="page-desc">Select a historical vaccination batch to permanently delete its records and restore inventory.</p>
    </div>

    <?php if (empty($batches) && empty($message)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-ghost" style="font-size: 3rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>
            <h3>No Transactions Found</h3>
            <p>There are no vaccination records available to reverse.</p>
        </div>
    <?php else: ?>
        <div class="main-grid">
            
            <div class="control-panel">
                <div class="panel-title"><i class="fa-solid fa-magnifying-glass"></i> Target Selection</div>
                <div class="form-group">
                    <label class="form-label">Select Batch Timestamp</label>
                    <select id="batchSelect" class="form-select" onchange="handleBatchSelection()">
                        <option value="">-- Choose a Batch to Reverse --</option>
                        <?php foreach($batches as $b): ?>
                            <option value="<?= htmlspecialchars($b['VACCINATION_DATE']) ?>">
                                <?= date('M d, Y - h:i A', strtotime($b['VACCINATION_DATE'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="alert-box">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>
                        <strong>Warning:</strong> This action is permanent. Reversing a batch will completely delete its transaction records and re-add the used quantities back into your vaccine inventory.
                    </div>
                </div>
            </div>

            <div id="detailsPanel" class="details-panel">
                <div class="details-title"><i class="fa-solid fa-circle-info"></i> Batch Details Confirmation</div>
                
                <div class="info-grid">
                    <div class="info-row">
                        <span class="info-lbl">Date Recorded</span>
                        <span class="info-val" id="lbl_date">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl">Vaccines Used</span>
                        <span class="info-val highlight" id="lbl_items" style="font-family: var(--font); font-size: 0.95rem;">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl">Administered By</span>
                        <span class="info-val" id="lbl_person" style="font-family: var(--font); font-size: 0.95rem;">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl">Records Affected</span>
                        <span class="info-val" id="lbl_count">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl">Total Value</span>
                        <span class="info-val" id="lbl_cost" style="color:var(--emerald);">—</span>
                    </div>
                </div>

                <button id="btn-reverse" class="btn-reverse" onclick="confirmReversal()" disabled>
                    <i class="fa-solid fa-trash-can"></i> Execute Reversal
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // Pass PHP batch array to JS for instant lookup
    const batchData = <?php echo json_encode($batches); ?>;

    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--emerald)'}`;
        t.innerHTML = `${type === 'error' ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-check"></i>'} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    function handleBatchSelection() {
        const selectedDate = document.getElementById('batchSelect').value;
        const panel = document.getElementById('detailsPanel');
        const btn = document.getElementById('btn-reverse');

        if (!selectedDate) {
            panel.classList.remove('active');
            btn.disabled = true;
            return;
        }

        // Find data in JS array
        const batch = batchData.find(b => String(b.VACCINATION_DATE) === String(selectedDate));

        if (batch) {
            // Format Date
            const d = new Date(batch.VACCINATION_DATE);
            const dateStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute:'2-digit' });

            document.getElementById('lbl_date').textContent = dateStr;
            document.getElementById('lbl_items').textContent = batch.items_used || 'Unknown';
            document.getElementById('lbl_person').textContent = batch.ADMINISTERED_BY || batch.VET_NAME || 'Unknown';
            document.getElementById('lbl_count').textContent = batch.record_count + ' Records';
            document.getElementById('lbl_cost').textContent = '₱ ' + parseFloat(batch.total_cost).toLocaleString('en-PH', {minimumFractionDigits:2});

            panel.classList.add('active');
            btn.disabled = false;
        }
    }

    function confirmReversal() {
        const selectedDate = document.getElementById('batchSelect').value;
        if (!selectedDate) return;

        if(!confirm(`DANGER: Are you absolutely sure you want to delete this vaccination batch?\n\nThis will permanently remove the transactions from the ledger and restore the vaccine stock.`)) {
            return;
        }

        const btn = document.getElementById('btn-reverse');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

        const fd = new FormData();
        fd.append('batch_date', selectedDate);

        fetch('../process/reverseVaccinationTransaction.php', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                showToast(data.message, "success");
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast(data.message || "Failed to reverse transaction.", "error");
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(err => {
            console.error(err);
            showToast("System connection error.", "error");
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }
</script>

</body>
</html>