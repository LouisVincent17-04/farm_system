<?php
ob_start(); // <-- Crucial: Starts output buffering so ob_end_clean() doesn't throw a JSON-corrupting error
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

// Handle AJAX Update & Audit Logging
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['location_id'], $_POST['weaning_days'])) {
    ob_end_clean(); // Safely wipes HTML/warnings now that ob_start() is active
    header('Content-Type: application/json');
    
    try {
        $locId = $_POST['location_id'];
        $days = (int)$_POST['weaning_days'];

        // 1. Fetch location name for a readable audit log
        $locStmt = $conn->prepare("SELECT LOCATION_NAME FROM locations WHERE LOCATION_ID = ?");
        $locStmt->execute([$locId]);
        $locName = $locStmt->fetchColumn() ?: "ID $locId";

        // 2. Execute the Update
        $stmt = $conn->prepare("UPDATE locations SET WEANING_DAYS = ? WHERE LOCATION_ID = ?");
        $stmt->execute([$days, $locId]);

        // 3. Insert into Audit Logs
        $userId = $_SESSION['user']['USER_ID'] ?? null;
        $username = $_SESSION['user']['FULL_NAME'] ?? 'System';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $details = "Updated auto-weaning threshold for location '{$locName}' to {$days} days.";
        
        $auditSql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                     VALUES (?, ?, 'UPDATE', 'locations', ?, ?)";
        $auditStmt = $conn->prepare($auditSql);
        $auditStmt->execute([$userId, $username, $details, $ip]);

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Auto Weaning Settings | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(245,158,11,0.5); /* Amber Accent */
            --amber:          #f59e0b;
            --amber-dim:      rgba(245,158,11,0.12);
            --amber-glow:     rgba(245,158,11,0.25);
            --green:          #10b981;
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.18s cubic-bezier(0.4,0,0.2,1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(245,158,11,0.05) 0%, transparent 60%);
        }
        
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR & HEADER ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
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
            color: var(--amber); background: var(--amber-dim); border: 1px solid rgba(245,158,11,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        .page-header { margin-bottom: 2.5rem; }
        .page-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--amber), #b45309);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 0.95rem; }

        /* ─── INFO BANNER ─── */
        .info-banner {
            background: var(--amber-dim); border: 1px solid rgba(245,158,11,0.2);
            border-radius: var(--radius-lg); padding: 1.25rem; margin-bottom: 2rem;
            display: flex; align-items: center; gap: 1rem;
        }
        .info-icon { color: var(--amber); font-size: 1.5rem; flex-shrink: 0; }
        .info-text { color: var(--text-primary); font-size: 0.9rem; line-height: 1.5; font-weight: 500; }

        /* ─── TABLE CARDS ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
        }
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 600px; }
        
        .table thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 16px; text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .table tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        .table tbody tr:last-child { border-bottom: none; }
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }
        .table td { padding: 16px; font-size: 0.95rem; color: var(--text-primary); vertical-align: middle; }

        .location-name { font-weight: 600; color: #fff; font-size: 1.05rem; }
        
        /* ─── INPUTS & BUTTONS ─── */
        .input-wrapper { display: flex; justify-content: center; align-items: center; }
        .day-input {
            background: var(--bg-elevated); border: 1px solid var(--border); color: #fff;
            padding: 10px 14px; border-radius: 8px; width: 110px; font-weight: 700;
            font-size: 1.1rem; font-family: var(--font-mono); text-align: center;
            outline: none; transition: all var(--transition);
        }
        .day-input:focus { border-color: var(--amber); box-shadow: 0 0 0 3px var(--amber-glow); background: var(--bg-hover); }

        .btn-save {
            background: var(--amber); color: #000; border: none; padding: 10px 24px;
            border-radius: 8px; font-weight: 700; font-family: var(--font); font-size: 0.9rem;
            cursor: pointer; transition: all var(--transition); white-space: nowrap;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            min-width: 120px;
        }
        .btn-save:hover:not(:disabled) {
            background: #fbbf24; transform: translateY(-1px); box-shadow: 0 4px 12px var(--amber-glow);
        }
        .btn-save:disabled { opacity: 0.7; cursor: not-allowed; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-header { text-align: center; }
            .info-banner { flex-direction: column; align-items: flex-start; text-align: left; }
            
            .table-wrap { border: none; background: transparent; overflow: visible;}
            .table, .table thead, .table tbody, .table th, .table td, .table tr { display: block; width: 100%; }
            .table thead { display: none; }
            .table tbody tr { 
                background: var(--bg-surface); border: 1px solid var(--border); 
                border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            }
            .table td { 
                display: flex; justify-content: space-between; align-items: center; 
                padding: 0.75rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right;
            }
            .table td:last-child { border-bottom: none; justify-content: flex-end; padding-top: 1rem; gap: 10px; }
            .table td::before { 
                content: attr(data-label); font-weight: 700; color: var(--text-muted); 
                font-size: 0.75rem; text-transform: uppercase; text-align: left;
            }
            .input-wrapper { justify-content: flex-end; }
            .day-input { width: 90px; }
            .btn-save { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="top-bar">
        <a href="settings.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> System Settings
        </a>
        <span class="page-badge"><i class="fa-solid fa-gears"></i> Automations</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1 class="page-title">Auto <span>Weaning</span> Thresholds</h1>
            <p class="page-subtitle">Configure environmental timers for reproductive cycles.</p>
        </div>
    </div>

    <div class="info-banner">
        <i class="fa-solid fa-circle-info info-icon"></i>
        <div class="info-text">
            Set the number of days after a birth when the system should automatically reset a Sow's status from <strong>'Birthing'</strong> back to <strong>'Dry'</strong>. Different geographic locations can have unique thresholds based on local farm practices.
        </div>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Location Registry</th>
                        <th style="text-align: center;">Weaning Threshold (Days)</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($locations as $loc): ?>
                    <tr>
                        <td data-label="Location">
                            <div class="location-name"><?= htmlspecialchars($loc['LOCATION_NAME']) ?></div>
                        </td>
                        <td data-label="Threshold">
                            <div class="input-wrapper">
                                <input type="number" id="days_<?= $loc['LOCATION_ID'] ?>" class="day-input" value="<?= $loc['WEANING_DAYS'] ?>" min="1">
                            </div>
                        </td>
                        <td data-label="Action" style="text-align: right;">
                            <button class="btn-save" onclick="updateThreshold(<?= $loc['LOCATION_ID'] ?>, this)">
                                <i class="fa-solid fa-floppy-disk"></i> Save
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    async function updateThreshold(locId, btn) {
        const daysInput = document.getElementById('days_' + locId);
        const days = daysInput.value;
        
        if(!days || days <= 0) { 
            alert("Please enter a valid number of days (greater than 0)."); 
            daysInput.focus();
            return; 
        }

        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('location_id', locId);
        formData.append('weaning_days', days);

        try {
            const res = await fetch('auto_weaning.php', { method: 'POST', body: formData });
            const result = await res.json();
            
            if(result.success) {
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Saved';
                btn.style.background = "var(--green)";
                btn.style.color = "#fff";
                
                setTimeout(() => { 
                    btn.innerHTML = originalText; 
                    btn.style.background = ""; 
                    btn.style.color = ""; 
                    btn.disabled = false; 
                }, 2000);
            } else {
                alert("Database Error: " + result.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        } catch(e) {
            alert("A system network error occurred.");
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
</script>
</body>
</html>