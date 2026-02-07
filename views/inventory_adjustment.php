<?php
// views/inventory_adjustment.php
include '../config/Connection.php';
include '../security/checkAccess.php';
checkAccess('farm'); 
include '../common/navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Adjustment</title>
    <style>
        :root { --dark: #0f172a; --dark-light: #1e293b; --red: #ef4444; --orange: #f97316; --gray: #64748b; --blue: #3b82f6; }
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; font-family: system-ui, sans-serif; min-height: 100vh; }
        .container { max-width: 700px; margin: 3rem auto; padding: 0 1rem; }

        .card { background: var(--dark-light); padding: 2rem; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .header { text-align: center; margin-bottom: 2rem; }
        .header h1 { color: var(--red); margin: 0 0 0.5rem 0; font-size: 1.8rem; }
        .header p { color: #94a3b8; margin: 0; }

        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #cbd5e1; }
        
        select, input, textarea {
            width: 100%; padding: 0.8rem; background: #334155; border: 1px solid #475569;
            color: white; border-radius: 8px; font-size: 1rem; outline: none; transition: 0.2s;
        }
        select:focus, input:focus { border-color: var(--red); box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2); }

        /* Calculation Mode Toggles */
        .mode-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .mode-option input { display: none; }
        .mode-label {
            display: flex; align-items: center; justify-content: center; gap: 8px; padding: 1rem;
            background: #334155; border: 1px solid #475569; border-radius: 8px; cursor: pointer; 
            font-weight: bold; color: #94a3b8; transition: 0.2s;
        }
        /* Active States */
        input[value="amount"]:checked + .mode-label { background: rgba(239, 68, 68, 0.15); border-color: var(--red); color: var(--red); }
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
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="header">
            <h1>⚖️ Inventory Adjustment</h1>
            <p>Deduct stock for Expired, Damaged, or Stolen items.</p>
        </div>

        <div id="alertBox" class="alert"></div>

        <form id="adjustForm">
            <div class="form-group">
                <label>Date of Adjustment</label>
                <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
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
                <label>How do you want to input?</label>
                <div class="mode-grid">
                    <label class="mode-option">
                        <input type="radio" name="input_mode" value="amount" checked onchange="toggleMode()">
                        <div class="mode-label">Input Deduction Amount</div>
                    </label>
                    <label class="mode-option">
                        <input type="radio" name="input_mode" value="balance" onchange="toggleMode()">
                        <div class="mode-label">Input Ending Balance</div>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label id="qtyLabel">Amount to Remove</label>
                <input type="number" id="input_value" name="input_value" step="any" min="0" placeholder="0.00" onkeyup="calculateAdjustment()" required>
                <div id="calcPreview" class="calculation-preview"></div>
                <small id="qtyWarning" style="color: #ef4444; display:none; margin-top:5px;">⚠️ Cannot result in negative stock.</small>
            </div>

            <div class="form-group">
                <label>Reason for Deduction</label>
                <select name="reason" required>
                    <option value="Expired">📅 Expired</option>
                    <option value="Damaged">💥 Damaged / Spillage</option>
                    <option value="Stolen">🦹 Stolen / Lost</option>
                    <option value="Correction">📝 Audit Correction</option>
                </select>
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
                    opt.dataset.expiry = item.expiry;
                    opt.text = `${item.name} (${item.stock} ${item.unit}) - Exp: ${item.expiry}`;
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
        
        if(mode === 'amount') {
            label.textContent = "Amount to Remove";
            input.placeholder = "e.g., 5.0";
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
        const preview = document.getElementById('calcPreview');
        const warning = document.getElementById('qtyWarning');
        const btn = document.getElementById('submitBtn');

        if (isNaN(input)) {
            preview.textContent = "";
            return;
        }

        let deductedAmount = 0;
        let finalStock = 0;

        if (mode === 'amount') {
            // Input is the deduction
            deductedAmount = input;
            finalStock = current - deductedAmount;
            preview.textContent = `Formula: ${current} (Start) - ${deductedAmount} (Deduct) = ${finalStock.toFixed(2)} (New Stock)`;
        } else {
            // Input is the balance (Left)
            finalStock = input;
            deductedAmount = current - finalStock;
            preview.textContent = `Formula: ${current} (Start) - ${finalStock} (Left) = ${deductedAmount.toFixed(2)} (To Deduct)`;
        }

        // Validation
        if (finalStock < 0 || deductedAmount < 0) {
            warning.style.display = 'block';
            btn.disabled = true;
            btn.style.opacity = '0.5';
            if(deductedAmount < 0) preview.textContent = "Error: New stock cannot be higher than current stock in Deduction mode.";
        } else {
            warning.style.display = 'none';
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    }

    // 5. Submit
    document.getElementById('adjustForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Final Validation
        const current = parseFloat(document.getElementById('hidden_stock').value);
        const input = parseFloat(document.getElementById('input_value').value);
        const mode = document.querySelector('input[name="input_mode"]:checked').value;
        
        let deduction = (mode === 'amount') ? input : (current - input);

        if (deduction > current || deduction < 0) {
            alert("Invalid calculation. Check your values.");
            return;
        }

        if(!confirm(`Confirm deducting ${deduction.toFixed(2)} from inventory?`)) return;

        const fd = new FormData(this);
        fd.append('request_type', 'submit_adjustment');
        // Backend expects the calculated deduction amount, we can calculate it there or send raw inputs.
        // Let's send raw inputs and let backend do math to be safe.

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
        })
        .catch(err => {
            alert("System Error: " + err);
            btn.disabled = false;
        });
    });
</script>

</body>
</html>