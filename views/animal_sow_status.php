<?php
// views/manage_sow_status.php
$page = "farm";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';    
checkRole(2);

// --- 1. INITIALIZE VARIABLES ---
$locations = [];
$buildings = [];
$sow_list = [];
$selected_sow_data = null;
$history = [];
$current_status = 'DRY'; 
$actions = [];

$location_id = $_GET['location_id'] ?? '';
$building_id = $_GET['building_id'] ?? '';
$selected_animal_id = $_GET['animal_id'] ?? '';

try {
    // --- 2. FETCH DROPDOWN DATA ---
    
    // Get Locations
    $stmt = $conn->prepare("SELECT * FROM locations ORDER BY LOCATION_NAME");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get Buildings (if Location selected)
    if ($location_id) {
        $stmt = $conn->prepare("SELECT * FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
        $stmt->execute([$location_id]);
        $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 3. FETCH SOW LIST (If Building Selected) ---
    if ($building_id) {
        // We join Classification to filter only Sows/Gilts
        // We LEFT JOIN current status to show it in the table
        $sql = "
            SELECT 
                ar.ANIMAL_ID, 
                ar.TAG_NO, 
                ac.STAGE_NAME,
                IFNULL(ssh.STATUS_NAME, 'DRY') as CURRENT_STATUS,
                ssh.STATUS_START_DATE
            FROM animal_records ar
            JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID
            LEFT JOIN sow_status_history ssh ON ar.ANIMAL_ID = ssh.ANIMAL_ID AND ssh.IS_ACTIVE = 1
            WHERE ar.BUILDING_ID = ? 
            AND ar.IS_ACTIVE = 1
            AND (ac.STAGE_NAME LIKE '%Sow%' OR ac.STAGE_NAME LIKE '%Gilt%')
            ORDER BY ar.TAG_NO ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$building_id]);
        $sow_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 4. FETCH SPECIFIC SOW DETAILS (If "Manage" clicked) ---
    if ($selected_animal_id) {
        // Basic Info
        $stmt = $conn->prepare("SELECT * FROM animal_records WHERE ANIMAL_ID = ?");
        $stmt->execute([$selected_animal_id]);
        $selected_sow_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($selected_sow_data) {
            // Get Current Status
            $stmtStatus = $conn->prepare("SELECT * FROM sow_status_history WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1");
            $stmtStatus->execute([$selected_animal_id]);
            $active_status_row = $stmtStatus->fetch(PDO::FETCH_ASSOC);
            $current_status = $active_status_row ? $active_status_row['STATUS_NAME'] : 'DRY';

            // Define Actions
            switch($current_status) {
                case 'DRY':
                    $actions = ['Start Service 1'];
                    break;
                case 'SERVICE 1':
                case 'SERVICE 2':
                case 'SERVICE 3':
                case 'SERVICE 4':
                    $actions = ['Completed (Pregnant)', 'Next Service (Repeat)', 'Undo'];
                    break;
                case 'SERVICE 5':
                    $actions = ['Completed (Pregnant)', 'Undo'];
                    break;
                case 'PREGNANT':
                    $actions = ['Birthing Started'];
                    break;
                case 'BIRTHING':
                    $actions = ['Completed (Reset to Dry)'];
                    break;
            }

            // Get History Timeline
            $stmtHist = $conn->prepare("SELECT * FROM sow_status_history WHERE ANIMAL_ID = ? ORDER BY STATUS_START_DATE DESC, STATUS_ID DESC");
            $stmtHist->execute([$selected_animal_id]);
            $history = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sow Breeding Management</title>
    <style>
        /* --- CORE STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            color: #e2e8f0; 
            min-height: 100vh; 
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }

        /* Filter Section - Responsive Grid */
        .filter-card {
            background: rgba(30, 41, 59, 0.6); 
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px; 
            padding: 1.5rem; 
            margin-bottom: 2rem;
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); /* Responsive */
            gap: 20px; 
            align-items: flex-end;
        }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 0.9rem; color: #94a3b8; font-weight: 600; }
        .form-select {
            padding: 10px; background: #1e293b; border: 1px solid #475569; 
            color: white; border-radius: 6px; width: 100%; font-size: 1rem;
        }

        /* Sow Table Container - Scroll Wrapper */
        .table-container {
            background: rgba(15, 23, 42, 0.6); border-radius: 12px; overflow: hidden; margin-bottom: 3rem;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        /* SCROLL WRAPPER FOR MOBILE */
        .table-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .sow-table { 
            width: 100%; 
            border-collapse: collapse; 
            min-width: 800px; /* Force scroll on small screens */
        }
        
        .sow-table th { background: rgba(30, 41, 59, 0.8); padding: 15px; text-align: left; color: #94a3b8; font-size: 0.85rem; text-transform: uppercase; white-space: nowrap; }
        .sow-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #cbd5e1; }
        .sow-table tr:hover { background: rgba(255,255,255,0.02); }
        
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; white-space: nowrap; }
        .status-dry { background: rgba(148, 163, 184, 0.2); color: #cbd5e1; }
        .status-service { background: rgba(236, 72, 153, 0.2); color: #f472b6; }
        .status-pregnant { background: rgba(34, 197, 94, 0.2); color: #86efac; }
        .status-birthing { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }

        .btn-manage {
            background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2);
            padding: 6px 12px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.85rem; white-space: nowrap;
        }
        .btn-manage:hover { background: rgba(59, 130, 246, 0.2); }
        .btn-manage.active { background: #3b82f6; color: white; }

        /* Detail / Action Section */
        .detail-section { 
            display: grid; 
            grid-template-columns: 1fr 2fr; 
            gap: 2rem; 
            animation: slideIn 0.3s ease; 
        }
        @keyframes slideIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

        .status-card {
            background: rgba(30, 41, 59, 0.8); border: 1px solid #ec4899;
            border-radius: 16px; padding: 2rem; text-align: center; height: fit-content;
        }
        .current-status-large {
            font-size: 2.5rem; font-weight: 800; margin: 1rem 0;
            background: linear-gradient(135deg, #ec4899, #db2777);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .action-btn {
            display: block; width: 100%; padding: 12px; margin-bottom: 10px;
            border-radius: 8px; border: none; font-weight: 600; cursor: pointer; font-size: 1rem;
        }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-warning { background: rgba(245, 158, 11, 0.1); color: #fbbf24; border: 1px solid #f59e0b; }

        .timeline-container { background: rgba(15, 23, 42, 0.4); border-radius: 12px; padding: 1.5rem; }
        .timeline-item {
            border-left: 3px solid #475569; padding-left: 15px; margin-bottom: 15px; position: relative;
        }
        .timeline-item::before {
            content: ''; position: absolute; left: -6px; top: 0; width: 9px; height: 9px; border-radius: 50%; background: #475569;
        }
        .timeline-item.active { border-color: #10b981; }
        .timeline-item.active::before { background: #10b981; box-shadow: 0 0 10px #10b981; }

        /* Responsive Breakpoints */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .detail-section { grid-template-columns: 1fr; } /* Stack details vertically */
            .status-card { margin-bottom: 1rem; }
            .current-status-large { font-size: 2rem; }
            .filter-card { gap: 1rem; padding: 1rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <h1 style="margin-bottom: 2rem;">Sow Breeding Management</h1>

    <form class="filter-card" method="GET">
        <div class="form-group">
            <label>1. Select Location</label>
            <select name="location_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Choose Location --</option>
                <?php foreach($locations as $loc): ?>
                    <option value="<?php echo $loc['LOCATION_ID']; ?>" <?php echo $location_id == $loc['LOCATION_ID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>2. Select Building</label>
            <select name="building_id" class="form-select" <?php echo empty($buildings) ? 'disabled' : ''; ?> onchange="this.form.submit()">
                <option value="">-- Choose Building --</option>
                <?php foreach($buildings as $b): ?>
                    <option value="<?php echo $b['BUILDING_ID']; ?>" <?php echo $building_id == $b['BUILDING_ID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($b['BUILDING_NAME']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if($building_id && !empty($sow_list)): ?>
        <div class="table-container">
            <div class="table-scroll-wrapper">
                <table class="sow-table">
                    <thead>
                        <tr>
                            <th>Tag No</th>
                            <th>Classification</th>
                            <th>Current Status</th>
                            <th>Status Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($sow_list as $row): 
                            // Determine Badge Color
                            $status = $row['CURRENT_STATUS'];
                            $badgeClass = 'status-dry';
                            if(strpos($status, 'SERVICE') !== false) $badgeClass = 'status-service';
                            if($status == 'PREGNANT') $badgeClass = 'status-pregnant';
                            if($status == 'BIRTHING') $badgeClass = 'status-birthing';
                            
                            $isActive = ($selected_animal_id == $row['ANIMAL_ID']);
                        ?>
                        <tr style="<?php echo $isActive ? 'background: rgba(59, 130, 246, 0.1);' : ''; ?>">
                            <td style="font-weight: bold; color: white;"><?php echo $row['TAG_NO']; ?></td>
                            <td><?php echo $row['STAGE_NAME']; ?></td>
                            <td><span class="status-badge <?php echo $badgeClass; ?>"><?php echo $status; ?></span></td>
                            <td><?php echo $row['STATUS_START_DATE']; ?></td>
                            <td style="text-align: right;">
                                <a href="?location_id=<?php echo $location_id; ?>&building_id=<?php echo $building_id; ?>&animal_id=<?php echo $row['ANIMAL_ID']; ?>" 
                                   class="btn-manage <?php echo $isActive ? 'active' : ''; ?>">
                                    <?php echo $isActive ? 'Selected' : 'Manage Status'; ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif($building_id): ?>
        <div style="text-align: center; padding: 2rem; color: #94a3b8; background: rgba(30,41,59,0.5); border-radius: 12px;">
            No Sows or Gilts found in this building.
        </div>
    <?php endif; ?>

    <?php if($selected_sow_data): ?>
        <div id="action-area" class="detail-section">
            
            <div class="status-card">
                <h3 style="color:white; margin-top:0;">Tag: <?php echo $selected_sow_data['TAG_NO']; ?></h3>
                <div style="color: #94a3b8; font-size: 0.9rem;">Current Cycle Status</div>
                
                <div class="current-status-large"><?php echo $current_status; ?></div>

                <div style="margin-top: 2rem;">
                    <?php if (empty($actions)): ?>
                        <p style="color:#64748b;">No actions available.</p>
                    <?php else: ?>
                        <?php foreach($actions as $action): 
                            $btnClass = 'btn-primary';
                            $val = '';
                            if (strpos($action, 'Undo') !== false) { $btnClass = 'btn-warning'; $val = 'undo'; }
                            elseif (strpos($action, 'Completed') !== false || strpos($action, 'Start') !== false) { $btnClass = 'btn-success'; $val = 'next_stage'; }
                            elseif (strpos($action, 'Next Service') !== false) { $btnClass = 'btn-primary'; $val = 'repeat_service'; }
                        ?>
                        <button class="action-btn <?php echo $btnClass; ?>" 
                                onclick="confirmAction('<?php echo $val; ?>', '<?php echo $action; ?>')">
                            <?php echo $action; ?>
                        </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="timeline-container">
                <h3 style="color: #cbd5e1; margin-top: 0; margin-bottom: 1.5rem;">Cycle History</h3>
                <?php foreach($history as $h): ?>
                    <div class="timeline-item <?php echo $h['IS_ACTIVE'] ? 'active' : ''; ?>">
                        <div style="display:flex; justify-content:space-between; margin-bottom: 5px;">
                            <strong style="color: <?php echo $h['IS_ACTIVE'] ? '#10b981' : '#e2e8f0'; ?>">
                                <?php echo $h['STATUS_NAME']; ?>
                            </strong>
                            <small style="color: #94a3b8;"><?php echo $h['STATUS_START_DATE']; ?></small>
                        </div>
                        <?php if($h['IS_ACTIVE']): ?>
                            <div style="color: #10b981; font-size: 0.8rem;">● Current Active Stage</div>
                        <?php else: ?>
                            <div style="color: #64748b; font-size: 0.8rem;">Ended: <?php echo $h['STATUS_END_DATE']; ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    <?php endif; ?>

</div>

<script>
    // Automatically scroll to action area if a sow is selected
    <?php if($selected_sow_data): ?>
        setTimeout(() => {
            document.getElementById('action-area').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 300); // Slight delay ensures rendering complete
    <?php endif; ?>

    function confirmAction(actionType, label) {
        let msg = `Confirm Action: "${label}"?`;
        if (actionType === 'undo') {
            msg = "⚠️ WARNING: You are about to UNDO the last status change.\n\nThis will revert the sow to the previous status and CANCEL the current service record.\n\nAre you sure?";
        }

        if (confirm(msg)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../process/sowStatusAction.php?building_id=<?php echo $building_id; ?>&location_id=<?php echo $location_id; ?>';
            
            const fields = {
                'animal_id': '<?php echo $selected_sow_data['ANIMAL_ID'] ?? ''; ?>',
                'action_type': actionType,
                'current_status': '<?php echo $current_status; ?>'
            };

            for (const [key, val] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = val;
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
        }
    }
</script>

</body>
</html>