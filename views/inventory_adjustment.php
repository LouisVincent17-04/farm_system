<?php
// views/inventory_adjustment.php
$page = "farm";
date_default_timezone_set('Asia/Manila'); 
include '../config/Connection.php';
include '../security/checkAccess.php';
checkAccess('farm'); 
include '../common/navbar.php';
include '../common/chat_support.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Inventory Adjustment | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(239, 68, 68, 0.5); /* Red Accent */
            --red:            #ef4444;
            --red-dim:        rgba(239, 68, 68, 0.12);
            --red-glow:       rgba(239, 68, 68, 0.25);
            --blue:           #3b82f6;
            --blue-dim:       rgba(59, 130, 246, 0.12);
            --orange:         #f97316;
            --green:          #10b981;
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.18s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(239, 68, 68, 0.05) 0%, transparent 60%);
        }

        /* ─── LAYOUT ─── */
        .container { max-width: 700px; margin: 0 auto; padding: 2rem 1.5rem; }

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
            color: var(--red); background: var(--red-dim); border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── CARD ─── */
        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .header { text-align: center; margin-bottom: 2.5rem; }
        .header h1 { 
            font-size: 1.8rem; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--red), #b91c1c);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .header p { color: var(--text-secondary); font-size: 0.95rem; }

        /* ─── FORM ─── */
        .form-group { margin-bottom: 1.5rem; }
        .form-label {
            display: block; font-size: 0.72rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.06em; color: var(--text-secondary); margin-bottom: 8px;
        }

        .form-control {
            width: 100%; padding: 12px 16px; background: var(--bg-elevated);
            border: 1px solid var(--border); border-radius: var(--radius-md);
            color: var(--text-primary); font-size: 1rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .form-control:focus { border-color: var(--border-active); box-shadow: 0 0 0 3px var(--red-glow); background: var(--bg-hover); }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center;
            padding-right: 40px; cursor: pointer;
        }

        /* Mode Toggles */
        .mode-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0.5rem; }
        .mode-option input { display: none; }
        .mode-label {
            display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px;
            background: var(--bg-elevated); border: 1px solid var(--border); border-radius: var(--radius-md);
            cursor: pointer; font-weight: 600; font-size: 0.85rem; color: var(--text-secondary);
            transition: all var(--transition); text-align: center; height: 100%;
        }
        .mode-label i { font-size: 0.9rem; opacity: 0.7; }
        
        input[value="quantity"]:checked + .mode-label { background: var(--red-dim); border-color: var(--red); color: var(--red); }
        input[value="balance"]:checked + .mode-label { background: var(--blue-dim); border-color: var(--blue); color: var(--blue); }

        /* Info Display */
        .stock-info {
            margin-top: 12px; padding: 16px; background: rgba(249, 115, 22, 0.05); 
            border: 1px solid rgba(249, 115, 22, 0.2); border-radius: var(--radius-md);
            color: var(--orange); display: none; font-size: 0.9rem; line-height: 1.6;
        }
        .stock-info strong { color: #fff; font-family: var(--font-mono); }

        .calculation-preview {
            font-size: 0.8rem; color: var(--text-muted); text-align: right; margin-top: 8px; font-style: italic; font-family: var(--font-mono);
        }
        .add-preview { color: var(--green); font-weight: 600; }
        .deduct-preview { color: var(--red); font-weight: 600; }

        .btn-submit {
            width: 100%; padding: 14px; background: linear-gradient(135deg, var(--red), #b91c1c);
            border: none; color: white; font-weight: 700; border-radius: var(--radius-md);
            cursor: pointer; font-size: 1rem; font-family: var(--font); margin-top: 1rem;
            transition: all var(--transition); box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 20px var(--red-glow); }
        .btn-submit:disabled { opacity: 0.4; cursor: not-allowed; transform: none; box-shadow: none; }

        /* Alerts */
        .alert { padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 1.5rem; display: none; text-align: center; font-weight: 600; font-size: 0.9rem; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid var(--green); color: var(--green); }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid var(--red); color: var(--red); }

        @media (max-width: 600px) {
            .container { padding: 1rem; }
            .card { padding: 1.5rem; }
            .mode-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <div class="top-bar">
        <a href="farm_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-scale-balanced"></i> Stock Audit</span>
    </div>

    <div class="card">
        <div class="header">
            <h1>Inventory Adjustment</h1>
            <p>Reconcile stock discrepancies due to damage, expiry, or audit.</p>
        </div>

        <div id="alertBox" class="alert"></div>

        <form id="adjustForm">
            <div class="form-group">
                <label class="form-label">Timestamp of Adjustment</label>
                <input type="text" id="adj_date" name="date" class="form-control" required value="<?php echo date('Y-m-d H:i'); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Resource Category</label>
                <select id="category" name="category" class="form-control" onchange="loadBatches()" required>
                    <option value="">-- Select Category --</option>
                    <option value="feed">📦 Feeds</option>
                    <option value="medicine">💊 Medicines</option>
                    <option value="vitamin">🧪 Vitamins & Supplements</option>
                    <option value="vaccine">💉 Vaccines</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Specific Batch / Expiry</label>
                <select id="batch_id" name="batch_id" class="form-control" onchange="showStock()" disabled required>
                    <option value="">-- Select Category First --</option>
                </select>
                
                <div id="stockDisplay" class="stock-info">
                    <i class="fa-solid fa-circle-info me-2"></i> Current Stock: <strong id="currentStock">0</strong> <span id="unitLabel"></span><br>
                    <i class="fa-solid fa-calendar-xmark me-2"></i> Batch Expiry: <strong id="expiryLabel">-</strong>
                </div>
                <input type="hidden" id="hidden_stock" value="0">
            </div>

            <div class="form-group">
                <label class="form-label">Adjustment Reason</label>
                <select id="reason_select" name="reason" class="form-control" onchange="calculateAdjustment()" required>
                    <option value="Expired">📅 Expired (Deduct Only)</option>
                    <option value="Damaged">💥 Damaged / Spillage (Deduct Only)</option>
                    <option value="Stolen">🦹 Stolen / Lost (Deduct Only)</option>
                    <option value="Correction">📝 Audit Correction (Flex)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Input Methodology</label>
                <div class="mode-grid">
                    <label class="mode-option">
                        <input type="radio" name="input_mode" value="quantity" checked onchange="toggleMode()">
                        <div class="mode-label"><i class="fa-solid fa-plus-minus"></i> Difference Qty</div>
                    </label>
                    <label class="mode-option">
                        <input type="radio" name="input_mode" value="balance" onchange="toggleMode()">
                        <div class="mode-label"><i class="fa-solid fa-equals"></i> Ending Balance</div>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" id="qtyLabel">Quantity to Remove</label>
                <input type="number" id="input_value" name="input_value" class="form-control" step="any" placeholder="0.00" onkeyup="calculateAdjustment()" required>
                <div id="calcPreview" class="calculation-preview"></div>
                <small id="qtyWarning" style="color: var(--red); display:none; margin-top:8px; font-weight:600;">
                    <i class="fa-solid fa-circle-exclamation"></i> Cannot result in negative stock.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">Administrative Remarks</label>
                <textarea name="remarks" class="form-control" rows="2" placeholder="Explain the discrepancy details..."></textarea>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">Confirm Adjustment</button>
        </form>
    </div>
</div>

<script>
    // Initialize Flatpickr
    flatpickr("#adj_date", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        altInput: true,
        altFormat: "m/d/Y h:i K",
        allowInput: true,
        defaultDate: "today"
    });

    function loadBatches() {
        const cat = document.getElementById('category').value;
        const select = document.getElementById('batch_id');
        const display = document.getElementById('stockDisplay');
        
        select.innerHTML = '<option value="">Searching...</option>';
        select.disabled = true;
        display.style.display = 'none';

        if(!cat) {
            select.innerHTML = '<option value="">-- Select Category First --</option>';
            return;
        }

        const fd = new FormData();
        fd.append('request_type', 'fetch_batches');
        fd.append('category', cat);

        fetch('../process/processInventoryAdjustment.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            select.innerHTML = '<option value="">-- Select Item Batch --</option>';
            if(data.length > 0) {
                data.forEach(item => {
                    let opt = document.createElement('option');
                    opt.value = item.id; 
                    opt.dataset.stock = item.stock;
                    opt.dataset.unit = item.unit;
                    let expParts = item.expiry.split('-');
                    let formattedExpiry = `${expParts[1]}/${expParts[2]}/${expParts[0]}`;
                    opt.dataset.expiry = formattedExpiry;
                    opt.text = `${item.name} (${item.stock} ${item.unit}) - Exp: ${formattedExpiry}`;
                    select.appendChild(opt);
                });
                select.disabled = false;
            } else {
                select.innerHTML = '<option value="">No items found</option>';
            }
        });
    }

    function showStock() {
        const select = document.getElementById('batch_id');
        const display = document.getElementById('stockDisplay');
        if(select.value) {
            const opt = select.options[select.selectedIndex];
            document.getElementById('currentStock').textContent = opt.dataset.stock;
            document.getElementById('unitLabel').textContent = opt.dataset.unit;
            document.getElementById('expiryLabel').textContent = opt.dataset.expiry;
            document.getElementById('hidden_stock').value = opt.dataset.stock;
            display.style.display = 'block';
            calculateAdjustment();
        } else {
            display.style.display = 'none';
        }
    }

    function toggleMode() {
        const mode = document.querySelector('input[name="input_mode"]:checked').value;
        const label = document.getElementById('qtyLabel');
        const input = document.getElementById('input_value');
        const reason = document.getElementById('reason_select').value;
        
        if(mode === 'quantity') {
            label.textContent = reason === 'Correction' ? "Quantity to Adjust (+/-)" : "Quantity to Remove";
            input.placeholder = reason === 'Correction' ? "e.g., 5.0 or -5.0" : "e.g., 5.0";
        } else {
            label.textContent = "Actual Remaining Balance";
            input.placeholder = "What is physically left?";
        }
        calculateAdjustment();
    }

    function calculateAdjustment() {
        const current = parseFloat(document.getElementById('hidden_stock').value) || 0;
        const input = parseFloat(document.getElementById('input_value').value);
        const mode = document.querySelector('input[name="input_mode"]:checked').value;
        const reason = document.getElementById('reason_select').value;
        const preview = document.getElementById('calcPreview');
        const warning = document.getElementById('qtyWarning');
        const btn = document.getElementById('submitBtn');

        if(mode === 'quantity') {
            document.getElementById('qtyLabel').textContent = reason === 'Correction' ? "Quantity to Adjust (+/-)" : "Quantity to Remove";
        }

        if (isNaN(input)) {
            preview.textContent = "";
            warning.style.display = 'none';
            btn.disabled = true;
            return;
        }

        let deductedQuantity = (mode === 'quantity') ? input : (current - input);
        let finalStock = current - deductedQuantity;
        let hasError = false;

        if (finalStock > current) {
            let addedAmt = finalStock - current;
            preview.innerHTML = `${current} <span class="add-preview">+ ${addedAmt.toFixed(2)}</span> = ${finalStock.toFixed(2)}`;
        } else {
            let deductedAmt = current - finalStock;
            preview.innerHTML = `${current} <span class="deduct-preview">- ${deductedAmt.toFixed(2)}</span> = ${finalStock.toFixed(2)}`;
        }

        if (finalStock < 0) {
            hasError = true;
            warning.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Cannot result in negative stock.';
        } else if (finalStock > current && reason !== 'Correction') {
            hasError = true;
            warning.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Reasons other than Correction must be deductions.';
        } else if (mode === 'quantity' && input < 0 && reason !== 'Correction') {
            hasError = true;
            warning.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Value must be positive for this reason.';
        }

        if (hasError) {
            warning.style.display = 'block';
            btn.disabled = true;
            preview.textContent = ""; 
        } else {
            warning.style.display = 'none';
            btn.disabled = false;
        }
    }

    document.getElementById('adjustForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const current = parseFloat(document.getElementById('hidden_stock').value);
        const input = parseFloat(document.getElementById('input_value').value);
        const mode = document.querySelector('input[name="input_mode"]:checked').value;
        const reason = document.getElementById('reason_select').value;
        let deduction = (mode === 'quantity') ? input : (current - input);

        let confirmMsg = deduction < 0 
            ? `Add ${Math.abs(deduction).toFixed(2)} to inventory as Audit Correction?`
            : `Deduct ${deduction.toFixed(2)} from current stock?`;

        if(!confirm(confirmMsg)) return;

        const fd = new FormData(this);
        fd.append('request_type', 'submit_adjustment');
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerText = "Processing...";

        fetch('../process/processInventoryAdjustment.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(res => {
            const alertBox = document.getElementById('alertBox');
            alertBox.textContent = res.message;
            alertBox.className = res.success ? 'alert alert-success' : 'alert alert-error';
            alertBox.style.display = 'block';
            if(res.success) {
                setTimeout(() => {
                    document.getElementById('input_value').value = '';
                    document.getElementById('calcPreview').textContent = '';
                    document.getElementById('alertBox').style.display = 'none';
                    btn.disabled = false;
                    btn.innerText = "Confirm Adjustment";
                    loadBatches();
                }, 2000);
            } else {
                btn.disabled = false;
                btn.innerText = "Confirm Adjustment";
            }
        }).catch(() => {
            alert("Network error occurred.");
            btn.disabled = false;
        });
    });
</script>

</body>
</html>