<?php
// views/inventory_adjustment.php
$page = "farm";
date_default_timezone_set('Asia/Manila'); // Ensure timezone is set to local time
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
    <title>Inventory Adjustment</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        :root { --dark: #0f172a; --dark-light: #1e293b; --red: #ef4444; --orange: #f97316; --gray: #64748b; --blue: #3b82f6; --green: #10b981; }
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; font-family: system-ui, sans-serif; min-height: 100vh; margin: 0; }
        
        /* Wrapper to align button left on wide screens */
        .nav-wrapper { max-width: 1400px; margin: 1.5rem auto 0; padding: 0 1rem; }

        /* Main Form Container - Centered and Narrow */
        .container { max-width: 700px; margin: 1rem auto 3rem; padding: 0 1rem; }
        
        /* Back Link Style */
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; 
            text-decoration: none; color: #94a3b8; font-weight: 600; 
            font-size: 0.95rem; transition: color 0.2s;
        }
        .back-link:hover { color: white; }

        .card { background: var(--dark-light); padding: 2rem; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .header { text-align: center; margin-bottom: 2rem; }
        .header h1 { color: var(--red); margin: 0 0 0.5rem 0; font-size: 1.8rem; }
        .header p { color: #94a3b8; margin: 0; }

        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #cbd5e1; }
        
        select, input, textarea {
            width: 100%; padding: 0.8rem; background: #0f172a; border: 1px solid #475569; box-sizing: border-box;
            color: white; border-radius: 8px; font-size: 1rem; outline: none; transition: 0.2s;
        }
        select:focus, input:focus { border-color: var(--red); box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2); }

        /* Calculation Mode Toggles */
        .mode-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .mode-option input { display: none; }
        .mode-label {
            display: flex; align-items: center; justify-content: center; gap: 8px; padding: 1rem; text-align: center;
            background: #0f172a; border: 1px solid #475569; border-radius: 8px; cursor: pointer; 
            font-weight: bold; color: #94a3b8; transition: 0.2s; height: 100%; box-sizing: border-box;
        }
        /* Active States */
        input[value="quantity"]:checked + .mode-label { background: rgba(239, 68, 68, 0.15); border-color: var(--red); color: var(--red); }
        input[value="balance"]:checked + .mode-label { background: rgba(59, 130, 246, 0.15); border-color: var(--blue); color: var(--blue); }

        /* Stock Info Display */
        .stock-info {
            margin-top: 5px; padding: 15px; background: rgba(249, 115, 22, 0.1); 
            border: 1px solid rgba(249, 115, 22, 0.3); border-radius: 8px; color: var(--orange); 
            display: none; font-size: 0.95rem; line-height: 1.6;
        }
        .stock-info strong { font-size: 1.1rem; color: #fdba74; }

        .btn-submit {
            width: 100%; padding: 1rem; background: linear-gradient(135deg, #ef4444, #b91c1c);
            border: none; color: white; font-weight: bold; border-radius: 8px; cursor: pointer;
            font-size: 1rem; margin-top: 1rem; transition: transform 0.2s;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: none; text-align: center; }
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #22c55e; color: #86efac; }
        .alert-error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; }
        
        .calculation-preview {
            font-size: 0.9rem; color: #94a3b8; text-align: right; margin-top: 5px; font-style: italic;
        }
        .add-preview { color: var(--green); }
        .deduct-preview { color: var(--red); }
    </style>
</head>
<body>

<div class="nav-wrapper">
    <a href="farm_dashboard.php" class="back-link">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Farm Dashboard
    </a>
</div>

<div class="container">
    <div class="card">
        <div class="header">
            <h1>⚖️ Inventory Adjustment</h1>
            <p>Adjust stock for Expired, Damaged, Stolen, or Audit corrections.</p>
        </div>

        <div id="alertBox" class="alert"></div>

        <form id="adjustForm">
            <div class="form-group">
                <label>Date & Time of Adjustment</label>
                <input type="text" id="adj_date" name="date" required value="<?php echo date('Y-m-d H:i'); ?>">
            </div>

            <div class="form-group">
                <label>Resource Category</label>
                <select id="category" name="category" onchange="loadBatches()" required>
                    <option value="">-- Select Category --</option>
                    <option value="feed">Feeds</option>
                    <option value="medicine">Medicines</option>
                    <option value="vitamin">Vitamins & Supplements</option>
                    <option value="vaccine">Vaccines</option>
                </select>
            </div>

            <div class="form-group">
                <label>Select Specific Batch (by Expiry)</label>
                <select id="batch_id" name="batch_id" onchange="showStock()" disabled required>
                    <option value="">-- Select Category First --</option>
                </select>
                
                <div id="stockDisplay" class="stock-info">
                    Current Stock: <strong id="currentStock">0</strong> <span id="unitLabel"></span><br>
                    Batch Expiry: <strong id="expiryLabel">-</strong>
                </div>
                <input type="hidden" id="hidden_stock" value="0">
            </div>

            <div class="form-group">
                <label>Reason for Adjustment</label>
                <select id="reason_select" name="reason" onchange="calculateAdjustment()" required>
                    <option value="Expired">📅 Expired (Deduct Only)</option>
                    <option value="Damaged">💥 Damaged / Spillage (Deduct Only)</option>
                    <option value="Stolen">🦹 Stolen / Lost (Deduct Only)</option>
                    <option value="Correction">📝 Audit Correction (Can Add or Deduct)</option>
                </select>
            </div>

            <div class="form-group">
                <label>How do you want to input?</label>
                <div class="mode-grid">
                    <label class="mode-option">
                        <input type="radio" name="input_mode" value="quantity" checked onchange="toggleMode()">
                        <div class="mode-label">Input Difference Quantity</div>
                    </label>
                    <label class="mode-option">
                        <input type="radio" name="input_mode" value="balance" onchange="toggleMode()">
                        <div class="mode-label">Input Ending Balance</div>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label id="qtyLabel">Quantity to Remove</label>
                <input type="number" id="input_value" name="input_value" step="any" placeholder="0.00" onkeyup="calculateAdjustment()" required>
                <div id="calcPreview" class="calculation-preview"></div>
                <small id="qtyWarning" style="color: #ef4444; display:none; margin-top:5px;">⚠️ Cannot result in negative stock.</small>
            </div>

            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" rows="2" placeholder="Optional details..."></textarea>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">Confirm Adjustment</button>
        </form>
    </div>
</div>

<script>
    // Initialize Flatpickr for Date and Time
    flatpickr("#adj_date", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",      // The format submitted to the backend
        altInput: true,               // Dummy input for UI
        altFormat: "m/d/Y h:i K",     // Visual Format: mm/dd/yyyy hh:mm AM/PM
        allowInput: true,
        defaultDate: "today"
    });

    // 1. Load Batches
    function loadBatches() {
        const cat = document.getElementById('category').value;
        const select = document.getElementById('batch_id');
        const display = document.getElementById('stockDisplay');
        
        select.innerHTML = '<option value="">Loading...</option>';
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
                    
                    // Format Expiry Date safely for frontend visual (mm/dd/yyyy)
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

    // 2. Show Stock
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

    // 3. Toggle Mode UI
    function toggleMode() {
        const mode = document.querySelector('input[name="input_mode"]:checked').value;
        const label = document.getElementById('qtyLabel');
        const input = document.getElementById('input_value');
        const reason = document.getElementById('reason_select').value;
        
        if(mode === 'quantity') {
            label.textContent = reason === 'Correction' ? "Quantity to Adjust (+/-)" : "Quantity to Remove";
            input.placeholder = reason === 'Correction' ? "e.g., 5.0 or -5.0" : "e.g., 5.0";
        } else {
            label.textContent = "Actual Remaining Balance (What is left?)";
            input.placeholder = "e.g., 45.0";
        }
        calculateAdjustment();
    }

    // 4. Calculate Logic
    function calculateAdjustment() {
        const current = parseFloat(document.getElementById('hidden_stock').value) || 0;
        const input = parseFloat(document.getElementById('input_value').value);
        const mode = document.querySelector('input[name="input_mode"]:checked').value;
        const reason = document.getElementById('reason_select').value;
        
        const preview = document.getElementById('calcPreview');
        const warning = document.getElementById('qtyWarning');
        const btn = document.getElementById('submitBtn');

        // Dynamically update label based on reason if in quantity mode
        if(mode === 'quantity') {
            document.getElementById('qtyLabel').textContent = reason === 'Correction' ? "Quantity to Adjust (+/-)" : "Quantity to Remove";
        }

        if (isNaN(input)) {
            preview.textContent = "";
            warning.style.display = 'none';
            btn.disabled = true;
            return;
        }

        let deductedQuantity = 0;
        let finalStock = 0;
        let hasError = false;

        if (mode === 'quantity') {
            // If they put a negative number in quantity mode, it means they are adding stock (mathematically -- is +)
            deductedQuantity = input; 
            finalStock = current - deductedQuantity;
        } else {
            // Input is the absolute ending balance
            finalStock = input;
            deductedQuantity = current - finalStock; // e.g., 100 start - 120 final = -20 deducted (which means +20 added)
        }

        // Preview rendering
        if (finalStock > current) {
            let addedAmt = finalStock - current;
            preview.innerHTML = `Formula: ${current} (Start) <span class="add-preview">+ ${addedAmt.toFixed(2)} (Added)</span> = ${finalStock.toFixed(2)} (New Stock)`;
        } else {
            let deductedAmt = current - finalStock;
            preview.innerHTML = `Formula: ${current} (Start) <span class="deduct-preview">- ${deductedAmt.toFixed(2)} (Deducted)</span> = ${finalStock.toFixed(2)} (New Stock)`;
        }

        // Validation Rules
        if (finalStock < 0) {
            hasError = true;
            warning.textContent = "⚠️ Cannot result in negative stock.";
        } else if (finalStock > current && reason !== 'Correction') {
            hasError = true;
            warning.textContent = "⚠️ You cannot increase stock unless the reason is set to 'Audit Correction'.";
        } else if (mode === 'quantity' && input < 0 && reason !== 'Correction') {
            hasError = true;
            warning.textContent = "⚠️ Deduction quantity cannot be negative unless the reason is 'Audit Correction'.";
        }

        // Apply UI Errors
        if (hasError) {
            warning.style.display = 'block';
            btn.disabled = true;
            btn.style.opacity = '0.5';
            preview.textContent = ""; // Hide preview on error to avoid confusion
        } else {
            warning.style.display = 'none';
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    }

    // 5. Submit
    document.getElementById('adjustForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const current = parseFloat(document.getElementById('hidden_stock').value);
        const input = parseFloat(document.getElementById('input_value').value);
        const mode = document.querySelector('input[name="input_mode"]:checked').value;
        const reason = document.getElementById('reason_select').value;
        
        let deduction = (mode === 'quantity') ? input : (current - input);

        // Final Security Checks
        if (current - deduction < 0) {
            alert("Invalid calculation. Stock cannot be negative.");
            return;
        }
        if (deduction < 0 && reason !== 'Correction') {
            alert("Cannot increase stock unless using Audit Correction.");
            return;
        }

        let confirmMsg = "";
        if (deduction < 0) {
            confirmMsg = `Confirm ADDING ${Math.abs(deduction).toFixed(2)} to inventory as an Audit Correction?`;
        } else {
            confirmMsg = `Confirm DEDUCTING ${deduction.toFixed(2)} from inventory?`;
        }

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
                    loadBatches(); // Reload stock
                }, 2000);
            } else {
                btn.disabled = false;
                btn.innerText = "Confirm Adjustment";
            }
        })
        .catch(err => {
            alert("System Error: " + err);
            btn.disabled = false;
            btn.innerText = "Confirm Adjustment";
        });
    });
</script>

</body>
</html>