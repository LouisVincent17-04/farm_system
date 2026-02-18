<?php
// views/reverse_checkup_transaction.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
$page = "transactions"; 

include '../config/Connection.php';
include '../security/checkAccess.php';
// checkAccess('admin_access'); // Uncomment if needed
include '../common/navbar.php';

$last_trans = null;
$message = "";

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // 1. Fetch the VERY latest check-up transaction
    $sql = "SELECT 
                c.CHECK_UP_ID,
                c.CHECKUP_DATE,
                c.VET_NAME,
                c.REMARKS,
                c.COST,
                a.TAG_NO
            FROM check_ups c
            LEFT JOIN animal_records a ON c.ANIMAL_ID = a.ANIMAL_ID
            ORDER BY c.CHECK_UP_ID DESC 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $last_trans = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Reverse Check-up</title>
    <style>
        :root { --dark: #0f172a; --dark-light: #1e293b; --red: #ef4444; --teal: #14b8a6; }
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; }
        .container { max-width: 800px; margin: 3rem auto; padding: 0 1rem; }

        .card { background: var(--dark-light); border: 1px solid rgba(20, 184, 166, 0.3); border-radius: 16px; padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        
        .header { text-align: center; margin-bottom: 2rem; }
        .header h1 { color: var(--teal); margin: 0 0 0.5rem 0; font-size: 2rem; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .header p { color: #94a3b8; }

        .trans-info { background: rgba(15, 23, 42, 0.6); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; border: 1px dashed #64748b; }
        .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .info-row:last-child { border-bottom: none; }
        .label { color: #94a3b8; font-size: 0.9rem; }
        .value { color: white; font-weight: 600; font-family: monospace; font-size: 1rem; }
        
        .btn-reverse {
            width: 100%; padding: 1.2rem;
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            color: white; border: none; border-radius: 12px;
            font-weight: 800; font-size: 1.1rem; cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            text-transform: uppercase; letter-spacing: 1px;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-reverse:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(239, 68, 68, 0.4); }
        .btn-reverse:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center; }
        .alert-warning { background: rgba(20, 184, 166, 0.1); color: #99f6e4; border: 1px solid #14b8a6; }
        
        .empty-state { text-align: center; padding: 3rem; color: #64748b; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="header">
            <h1>
                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Reverse Check-up
            </h1>
            <p>Undo the most recent veterinary check-up record.</p>
        </div>

        <?php if ($last_trans): ?>
            <div class="alert alert-warning">
                ⚠️ <strong>Confirmation:</strong> This will delete the check-up record and remove the associated cost.
            </div>

            <div class="trans-info">
                <h3 style="margin-top:0; color:#cbd5e1; font-size:0.9rem; text-transform:uppercase; margin-bottom:15px;">Target Transaction</h3>
                
                <div class="info-row">
                    <span class="label">Check-up ID</span>
                    <span class="value" style="color:#2dd4bf;">#<?= htmlspecialchars($last_trans['CHECK_UP_ID']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Date Recorded</span>
                    <span class="value"><?= date('M d, Y h:i A', strtotime($last_trans['CHECKUP_DATE'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Animal Tag</span>
                    <span class="value"><?= htmlspecialchars($last_trans['TAG_NO']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Veterinarian</span>
                    <span class="value"><?= htmlspecialchars($last_trans['VET_NAME']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Service Cost</span>
                    <span class="value" style="color:#f472b6;">₱<?= number_format($last_trans['COST'], 2) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Remarks</span>
                    <span class="value" style="font-size:0.85rem; font-style:italic;"><?= htmlspecialchars($last_trans['REMARKS']) ?></span>
                </div>
            </div>

            <button id="btn-reverse" class="btn-reverse" onclick="confirmReversal()">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                Confirm Reversal
            </button>

        <?php else: ?>
            <div class="empty-state">
                <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 1rem; display:block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <h3>No History Found</h3>
                <p>There are no check-up records available to reverse.</p>
            </div>
        <?php endif; ?>
        
        <div style="text-align:center; margin-top:1.5rem;">
            <a href="transactions.php" style="color:#64748b; text-decoration:none; font-size:0.9rem;">← Return to Transactions</a>
        </div>
    </div>
</div>

<script>
    function confirmReversal() {
        if(!confirm("🔴 DANGER: Are you sure you want to delete this check-up record?\n\nAssociated costs will be removed.")) {
            return;
        }

        const btn = document.getElementById('btn-reverse');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = "Processing...";

        fetch('../process/reverseCheckupTransaction.php', {
            method: 'POST'
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert("✅ " + data.message);
                window.location.reload(); 
            } else {
                alert("❌ Error: " + data.message);
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(err => {
            console.error(err);
            alert("System Error: Could not connect to server.");
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }
</script>

</body>
</html>