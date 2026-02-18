<?php
// views/reverse_feeding_transaction.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
$page = "transactions"; // Or "transactions", depending on your menu structure

include '../config/Connection.php';
include '../security/checkAccess.php';

// IMPORTANT: Ensure only Admins or specific roles can access this page
// checkAccess('admin_access'); // Uncomment if you have a specific admin permission column
include '../common/navbar.php';

$last_batch = null;
$message = "";

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // 1. Fetch the VERY latest batch to display what will be deleted
    $sql = "SELECT 
                ft.BATCH_ID,
                ft.TRANSACTION_DATE,
                f.FEED_NAME,
                COUNT(ft.ANIMAL_ID) as animal_count,
                SUM(ft.QUANTITY_KG) as total_kg,
                MAX(ft.REMARKS) as remarks,
                u.FULL_NAME as user_name
            FROM feed_transactions ft
            LEFT JOIN feeds f ON ft.FEED_ID = f.FEED_ID
            LEFT JOIN users u ON u.USER_ID = 1 -- Assuming system or join appropriately if user_id stored
            GROUP BY ft.BATCH_ID
            ORDER BY ft.FT_ID DESC 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $last_batch = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Reverse Feeding</title>
    <style>
        :root { --dark: #0f172a; --dark-light: #1e293b; --red: #ef4444; --orange: #f97316; }
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; }
        .container { max-width: 800px; margin: 3rem auto; padding: 0 1rem; }

        .card { background: var(--dark-light); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 16px; padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        
        .header { text-align: center; margin-bottom: 2rem; }
        .header h1 { color: var(--red); margin: 0 0 0.5rem 0; font-size: 2rem; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .header p { color: #94a3b8; }

        .batch-info { background: rgba(15, 23, 42, 0.6); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; border: 1px dashed #475569; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
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
        .alert-warning { background: rgba(245, 158, 11, 0.1); color: #fbbf24; border: 1px solid #fbbf24; }
        
        .empty-state { text-align: center; padding: 3rem; color: #64748b; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="header">
            <h1>
                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                Admin Reversal Tool
            </h1>
            <p>Undo the most recent feeding transaction batch.</p>
        </div>

        <?php if ($last_batch): ?>
            <div class="alert alert-warning">
                ⚠️ <strong>Warning:</strong> This action is permanent. It will delete transaction records and restore inventory.
            </div>

            <div class="batch-info">
                <h3 style="margin-top:0; color:#cbd5e1; font-size:0.9rem; text-transform:uppercase; margin-bottom:15px;">Target Transaction Details</h3>
                
                <div class="info-row">
                    <span class="label">Batch ID</span>
                    <span class="value" style="color:#fbbf24;"><?= htmlspecialchars($last_batch['BATCH_ID']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Date Recorded</span>
                    <span class="value"><?= date('M d, Y h:i A', strtotime($last_batch['TRANSACTION_DATE'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Feed Used</span>
                    <span class="value"><?= htmlspecialchars($last_batch['FEED_NAME']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Animals Affected</span>
                    <span class="value"><?= $last_batch['animal_count'] ?> Heads</span>
                </div>
                <div class="info-row">
                    <span class="label">Total Quantity</span>
                    <span class="value" style="color:#34d399;"><?= number_format($last_batch['total_kg'], 2) ?> KG</span>
                </div>
                <div class="info-row">
                    <span class="label">Remarks</span>
                    <span class="value" style="font-size:0.85rem; font-style:italic;"><?= htmlspecialchars($last_batch['remarks']) ?></span>
                </div>
            </div>

            <button id="btn-reverse" class="btn-reverse" onclick="confirmReversal()">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                Confirm Reversal
            </button>

        <?php else: ?>
            <div class="empty-state">
                <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 1rem; display:block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <h3>No Recent Transactions</h3>
                <p>There are no feeding records available to reverse.</p>
            </div>
        <?php endif; ?>
        
        <div style="text-align:center; margin-top:1.5rem;">
            <a href="transactions.php" style="color:#64748b; text-decoration:none; font-size:0.9rem;">← Return to Transactions</a>
        </div>
    </div>
</div>

<script>
    function confirmReversal() {
        if(!confirm("🔴 DANGER: Are you absolutely sure you want to delete this transaction?\n\nThis will restore the inventory stock and remove costs.")) {
            return;
        }

        const btn = document.getElementById('btn-reverse');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = "Processing Reversal...";

        // Pointing to the backend processor we created earlier
        fetch('../process/reverseFeedingTransaction.php', {
            method: 'POST'
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert("✅ Success: " + data.message);
                window.location.reload(); // Reload to update the empty state or show next latest
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