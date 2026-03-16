<?php
// views/auto_weaning.php
$page = "settings";
include '../config/Connection.php';
include '../security/checkAccess.php';
checkAccess('settings');
include '../common/navbar.php';
include '../common/chat_support.php';

if($_SESSION['user']['USER_TYPE'] !== 4) {
    echo "<script>alert('Access denied.'); window.location.href = 'admin_dashboard.php';</script>";
    exit();
}

// Handle AJAX Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['location_id'], $_POST['weaning_days'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    try {
        $stmt = $conn->prepare("UPDATE locations SET WEANING_DAYS = ? WHERE LOCATION_ID = ?");
        $stmt->execute([(int)$_POST['weaning_days'], $_POST['location_id']]);
        echo json_encode(['success' => true, 'message' => 'Updated successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Fetch Locations
$locations = $conn->query("SELECT LOCATION_ID, LOCATION_NAME, COALESCE(WEANING_DAYS, 30) as WEANING_DAYS FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Auto Weaning Settings</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; margin: 0; }
        .container { max-width: 900px; margin: 0 auto; padding: 2rem; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #94a3b8; font-weight: 600; font-size: 1rem; transition: color 0.2s; margin-bottom: 1.5rem; }
        .back-link:hover { color: white; }
        .header-section { margin-bottom: 2rem; }
        .page-title { font-size: 2.2rem; font-weight: 800; color: #f59e0b; margin: 0 0 10px 0; }
        
        .table-wrap { background: rgba(30, 41, 59, 0.6); border: 1px solid #475569; border-radius: 12px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0f172a; padding: 15px; text-align: left; color: #94a3b8; text-transform: uppercase; font-size: 0.85rem; border-bottom: 1px solid #475569; }
        td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
        tr:hover { background: rgba(255,255,255,0.02); }
        
        .day-input { background: #0f172a; border: 1px solid #475569; color: #fff; padding: 10px; border-radius: 6px; width: 100px; font-weight: bold; font-size: 1.1rem; text-align: center; }
        .day-input:focus { border-color: #f59e0b; outline: none; }
        
        .btn-save { background: #f59e0b; color: #000; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-save:hover { background: #d97706; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <a href="settings.php" class="back-link">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Settings
        </a>

        <div class="header-section">
            <h1 class="page-title">Auto Weaning Thresholds</h1>
            <p style="color: #94a3b8;">Set the number of days after a birth when the system should automatically reset a Sow's status from 'Birthing' back to 'Dry'. Different locations can have different thresholds.</p>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Location Name</th>
                        <th style="text-align: center;">Weaning Threshold (Days)</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($locations as $loc): ?>
                    <tr>
                        <td style="font-weight: 600; font-size: 1.1rem;"><?= htmlspecialchars($loc['LOCATION_NAME']) ?></td>
                        <td style="text-align: center;">
                            <input type="number" id="days_<?= $loc['LOCATION_ID'] ?>" class="day-input" value="<?= $loc['WEANING_DAYS'] ?>" min="1">
                        </td>
                        <td style="text-align: right;">
                            <button class="btn-save" onclick="updateThreshold(<?= $loc['LOCATION_ID'] ?>, this)">Save</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        async function updateThreshold(locId, btn) {
            const days = document.getElementById('days_' + locId).value;
            if(!days || days <= 0) { alert("Please enter a valid number of days."); return; }

            const originalText = btn.innerText;
            btn.innerText = "Saving...";
            btn.disabled = true;

            const formData = new FormData();
            formData.append('location_id', locId);
            formData.append('weaning_days', days);

            try {
                const res = await fetch('auto_weaning.php', { method: 'POST', body: formData });
                const result = await res.json();
                if(result.success) {
                    btn.innerText = "Saved ✓";
                    btn.style.background = "#10b981";
                    btn.style.color = "white";
                    setTimeout(() => { btn.innerText = "Save"; btn.style.background = ""; btn.style.color = ""; btn.disabled = false; }, 2000);
                } else {
                    alert("Error: " + result.message);
                    btn.innerText = originalText;
                    btn.disabled = false;
                }
            } catch(e) {
                alert("System error occurred.");
                btn.innerText = originalText;
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>