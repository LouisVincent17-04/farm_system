<?php
// views/reverse_vaccination_transaction.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
$page = "transactions"; 

include '../config/Connection.php';
include '../security/checkAccess.php';
include '../common/navbar.php';
include '../common/chat_support.php';

$batch_records = [];
$latest_date = null;
$message = "";

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // 1. Get the timestamp of the latest vaccination transaction
    $time_stmt = $conn->query("SELECT VACCINATION_DATE FROM vaccination_records ORDER BY VACCINATION_DATE DESC LIMIT 1");
    $latest_date = $time_stmt->fetchColumn();

    if ($latest_date) {
        // 2. Fetch ALL vaccination records matching that exact timestamp
        $sql = "SELECT 
                    v.VACCINATION_ID,
                    v.VACCINATION_DATE,
                    v.QUANTITY,
                    v.VACCINE_COST,
                    v.VACCINATION_COST,
                    v.VET_NAME,
                    v.ADMINISTERED_BY,
                    a.TAG_NO,
                    vac.SUPPLY_NAME AS VAC_NAME,
                    u.UNIT_ABBR
                FROM vaccination_records v
                LEFT JOIN animal_records a ON v.ANIMAL_ID = a.ANIMAL_ID
                LEFT JOIN vaccines vac ON v.ITEM_ID = vac.SUPPLY_ID
                LEFT JOIN units u ON vac.UNIT_ID = u.UNIT_ID
                WHERE v.VACCINATION_DATE = :latest_date
                ORDER BY a.TAG_NO ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute([':latest_date' => $latest_date]);
        $batch_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Reverse Batch Vaccination</title>
    <style>
        :root { --dark: #0f172a; --dark-light: #1e293b; --red: #ef4444; --green: #10b981; --amber: #f59e0b; }
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; }
        .container { max-width: 900px; margin: 3rem auto; padding: 0 1rem; }

        .card { background: var(--dark-light); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 16px; padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        
        .header { text-align: center; margin-bottom: 2rem; }
        .header h1 { color: var(--green); margin: 0 0 0.5rem 0; font-size: 2rem; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .header p { color: #94a3b8; }

        /* Batch Table Styles */
        .batch-container { background: rgba(15, 23, 42, 0.6); border-radius: 12px; border: 1px solid #334155; margin-bottom: 2rem; overflow: hidden; }
        .batch-header { padding: 1rem; background: rgba(16, 185, 129, 0.1); border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; }
        
        .table-wrap { max-height: 400px; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 1rem; font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; background: #0f172a; position: sticky; top: 0; }
        td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; }
        tr:last-child td { border-bottom: none; }

        .tag-badge { background: #334155; color: white; padding: 4px 8px; border-radius: 6px; font-weight: bold; font-family: monospace; }
        
        .btn-reverse {
            width: 100%; padding: 1.2rem;
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            color: white; border: none; border-radius: 12px;
            font-weight: 800; font-size: 1.1rem; cursor: pointer;
            transition: all 0.2s; text-transform: uppercase; letter-spacing: 1px;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-reverse:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(239, 68, 68, 0.4); }
        .btn-reverse:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center; }
        .alert-warning { background: rgba(245, 158, 11, 0.1); color: #fef3c7; border: 1px solid var(--amber); }
        
        .empty-state { text-align: center; padding: 3rem; color: #64748b; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="header">
            <h1>
                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                Reverse Batch Vaccination
            </h1>
            <p>Delete the most recent group of vaccination records and restore stock.</p>
        </div>

        <?php if (!empty($batch_records)): ?>
            <div class="alert alert-warning">
                ⚠️ <strong>Batch Action:</strong> Reversing this will delete <strong><?= count($batch_records) ?></strong> records and restore all associated inventory.
            </div>

            <div class="batch-container">
                <div class="batch-header">
                    <span style="font-size: 0.85rem; font-weight: 600;">
                        🕒 <?= date('M d, Y h:i A', strtotime($latest_date)) ?>
                    </span>
                    <span style="font-size: 0.85rem; color: var(--green);">
                        Administered by: <?= htmlspecialchars($batch_records[0]['ADMINISTERED_BY'] ?? $batch_records[0]['VET_NAME'] ?? 'Unknown') ?>
                    </span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Animal Tag</th>
                                <th>Vaccine</th>
                                <th>Total Cost</th>
                                <th style="text-align:right;">Quantity Used</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batch_records as $row): 
                                $total_item_cost = $row['VACCINE_COST'] + $row['VACCINATION_COST'];
                            ?>
                            <tr>
                                <td><span class="tag-badge"><?= htmlspecialchars($row['TAG_NO']) ?></span></td>
                                <td><?= htmlspecialchars($row['VAC_NAME']) ?></td>
                                <td style="color: #94a3b8;">₱<?= number_format($total_item_cost, 2) ?></td>
                                <td style="text-align:right; font-weight: bold; color: #34d399;">
                                    <?= number_format($row['QUANTITY'], 2) ?> <?= $row['UNIT_ABBR'] ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <button id="btn-reverse" class="btn-reverse" onclick="confirmReversal()">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                Reverse Entire Batch
            </button>

        <?php else: ?>
            <div class="empty-state">
                <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 1rem; display:block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <h3>No Recent Batch Found</h3>
                <p>There are no vaccination records available to reverse.</p>
            </div>
        <?php endif; ?>
        
        <div style="text-align:center; margin-top:1.5rem;">
            <a href="transactions.php" style="color:#64748b; text-decoration:none; font-size:0.9rem;">← Return to Transactions</a>
        </div>
    </div>
</div>

<script>
    function confirmReversal() {
        const count = <?= count($batch_records) ?>;
        if(!confirm(`🔴 CRITICAL ACTION\n\nYou are about to delete ALL ${count} vaccination records in this batch.\n\nStock levels will be increased accordingly. Do you wish to proceed?`)) {
            return;
        }

        const btn = document.getElementById('btn-reverse');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = "Processing Batch Reversal...";

        fetch('../process/reverseVaccinationTransaction.php', {
            method: 'POST'
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert("✅ Success: " + data.message);
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