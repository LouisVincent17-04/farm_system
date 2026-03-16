<?php
// views/animal_sow_status.php
$page = "farm";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('sow_status');
include '../common/navbar.php';
include '../common/chat_support.php';

// --- 1. INITIALIZE VARIABLES ---
$locations = [];
$buildings = [];
$sow_list = [];
$selected_sow_data = null;
$history = [];
$current_status = 'DRY'; 
$actions = [];
$current_status_id = null;
$sow_card_done = 0; 

$location_id = $_GET['location_id'] ?? '';
$building_id = $_GET['building_id'] ?? '';
$selected_animal_id = $_GET['animal_id'] ?? '';

try {
    // --- 2. FETCH DROPDOWNS ---
    $stmt = $conn->prepare("SELECT * FROM locations ORDER BY LOCATION_NAME");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($location_id) {
        $stmt = $conn->prepare("SELECT * FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
        $stmt->execute([$location_id]);
        $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 3. FETCH SOW LIST ---
    if ($building_id) {
        $sql = "SELECT ar.ANIMAL_ID, ar.TAG_NO, ac.STAGE_NAME, IFNULL(ssh.STATUS_NAME, 'DRY') as CURRENT_STATUS, ssh.STATUS_START_DATE FROM animal_records ar JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID LEFT JOIN sow_status_history ssh ON ar.ANIMAL_ID = ssh.ANIMAL_ID AND ssh.IS_ACTIVE = 1 WHERE ar.BUILDING_ID = ? AND ar.IS_ACTIVE = 1 AND (ac.STAGE_NAME LIKE '%Sow%' OR ac.STAGE_NAME LIKE '%Gilt%') ORDER BY ar.TAG_NO ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$building_id]);
        $sow_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 4. FETCH SELECTED SOW DETAILS ---
    if ($selected_animal_id) {
        $stmt = $conn->prepare("SELECT * FROM animal_records WHERE ANIMAL_ID = ?");
        $stmt->execute([$selected_animal_id]);
        $selected_sow_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($selected_sow_data) {
            $stmtStatus = $conn->prepare("SELECT * FROM sow_status_history WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1");
            $stmtStatus->execute([$selected_animal_id]);
            $active_status_row = $stmtStatus->fetch(PDO::FETCH_ASSOC);
            $current_status = $active_status_row ? $active_status_row['STATUS_NAME'] : 'DRY';
            $current_status_id = $active_status_row['STATUS_ID'] ?? null;
            $sow_card_done = $active_status_row['SOW_CARD_CREATED'] ?? 0;

            // --- STATUS LOGIC ---
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
                    $actions = ['Birthing Started', 'Abortion', 'Undo'];
                    break;
                case 'ABORTION':
                    $actions = ['Recovery (Reset to Dry)', 'Undo'];
                    break;
                case 'BIRTHING':
                    if ($sow_card_done == 1) {
                        $actions = ['Completed (Reset to Dry)'];
                    } else {
                        $actions = ['Go to Sow Card (Required)'];
                    }
                    break;
            }

            $histSql = "SELECT sh.*, srv.SERVICE_TYPE, srv.BOAR_ID, boar.TAG_NO as BOAR_TAG FROM sow_status_history sh LEFT JOIN sow_service_history srv ON sh.ANIMAL_ID = srv.ANIMAL_ID AND sh.STATUS_START_DATE = srv.SERVICE_START_DATE AND sh.STATUS_NAME LIKE CONCAT('SERVICE ', srv.SERVICE_NUMBER) LEFT JOIN animal_records boar ON srv.BOAR_ID = boar.ANIMAL_ID WHERE sh.ANIMAL_ID = ? ORDER BY sh.STATUS_START_DATE DESC, sh.STATUS_ID DESC";
            $stmtHist = $conn->prepare($histSql);
            $stmtHist->execute([$selected_animal_id]);
            $history = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
        }
    }

} catch (Exception $e) { echo "Error: " . $e->getMessage(); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sow Breeding Management</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        
        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #94a3b8; font-weight: 600; font-size: 0.9rem; transition: color 0.2s; }
        .back-link:hover { color: white; }

        .filter-card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 0.9rem; color: #94a3b8; font-weight: 600; }
        .form-select, .form-input { padding: 10px; background: #1e293b; border: 1px solid #475569; color: white; border-radius: 6px; width: 100%; font-size: 1rem; color-scheme: dark;}
        .form-input:focus, .form-select:focus { border-color: #ec4899; outline: none; }
        
        .table-container { background: rgba(15, 23, 42, 0.6); border-radius: 12px; overflow: hidden; margin-bottom: 3rem; border: 1px solid rgba(255,255,255,0.05); }
        .table-scroll-wrapper { width: 100%; overflow-x: auto; }
        .sow-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .sow-table th { background: rgba(30, 41, 59, 0.8); padding: 15px; text-align: left; color: #94a3b8; font-size: 0.85rem; text-transform: uppercase; white-space: nowrap; }
        .sow-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #cbd5e1; }
        .sow-table tr:hover { background: rgba(255,255,255,0.02); }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; white-space: nowrap; }
        .status-dry { background: rgba(148, 163, 184, 0.2); color: #cbd5e1; }
        .status-service { background: rgba(236, 72, 153, 0.2); color: #f472b6; }
        .status-pregnant { background: rgba(34, 197, 94, 0.2); color: #86efac; }
        .status-birthing { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .status-abortion { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #ef4444; } 
        
        .btn-manage { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); padding: 6px 12px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.85rem; white-space: nowrap; }
        .btn-manage:hover { background: rgba(59, 130, 246, 0.2); }
        .btn-manage.active { background: #3b82f6; color: white; }
        .detail-section { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; animation: slideIn 0.3s ease; }
        .status-card { background: rgba(30, 41, 59, 0.8); border: 1px solid #ec4899; border-radius: 16px; padding: 2rem; text-align: center; height: fit-content; }
        .current-status-large { font-size: 2.5rem; font-weight: 800; margin: 1rem 0; background: linear-gradient(135deg, #ec4899, #db2777); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .action-btn { display: block; width: 100%; padding: 12px; margin-bottom: 10px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; font-size: 1rem; }
        
        .btn-primary { background: #3b82f6; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-warning { background: rgba(245, 158, 11, 0.1); color: #fbbf24; border: 1px solid #f59e0b; }
        .btn-purple { background: linear-gradient(135deg, #a855f7, #7c3aed); color: white; box-shadow: 0 4px 12px rgba(168, 85, 247, 0.3); }
        .btn-danger { background: rgba(239, 68, 68, 0.8); color: white; border: 1px solid #ef4444; } 
        
        .timeline-container { background: rgba(15, 23, 42, 0.4); border-radius: 12px; padding: 1.5rem; }
        .timeline-item { border-left: 3px solid #475569; padding-left: 15px; margin-bottom: 15px; position: relative; }
        .timeline-item::before { content: ''; position: absolute; left: -6px; top: 0; width: 9px; height: 9px; border-radius: 50%; background: #475569; }
        .timeline-item.active { border-color: #10b981; }
        .timeline-item.active::before { background: #10b981; box-shadow: 0 0 10px #10b981; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: #1e293b; border-radius: 12px; width: 90%; max-width: 450px; padding: 1.5rem; border: 1px solid #475569; animation: zoomIn 0.2s ease; }
        .modal h2 { margin-top: 0; color: #fff; font-size: 1.2rem; }
        .modal p { color: #94a3b8; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem; }
        .radio-group { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .radio-label { display: flex; align-items: center; gap: 8px; cursor: pointer; color: #e2e8f0; font-size: 0.95rem; }
        .radio-label input { accent-color: #ec4899; width: 18px; height: 18px; }
        @media (max-width: 768px) { .container { padding: 1rem; } .detail-section { grid-template-columns: 1fr; } .filter-card { gap: 1rem; padding: 1rem; } }
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
        <h1 style="margin:0;">Sow Breeding Management</h1>
        <a href="farm_dashboard.php" class="back-link">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Farm Dashboard
        </a>
    </div>

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
                    <thead><tr><th>Tag No</th><th>Classification</th><th>Current Status</th><th>Status Date</th><th style="text-align: right;">Action</th></tr></thead>
                    <tbody>
                        <?php foreach($sow_list as $row): 
                            $status = $row['CURRENT_STATUS'];
                            $badgeClass = 'status-dry';
                            if(strpos($status, 'SERVICE') !== false) $badgeClass = 'status-service';
                            if($status == 'PREGNANT') $badgeClass = 'status-pregnant';
                            if($status == 'BIRTHING') $badgeClass = 'status-birthing';
                            if($status == 'ABORTION') $badgeClass = 'status-abortion';
                            $isActive = ($selected_animal_id == $row['ANIMAL_ID']);

                            // Format Date to mm/dd/yyyy for Table Display
                            $dateStr = 'N/A'; $timeStr = '';
                            if ($row['STATUS_START_DATE']) {
                                $dt = new DateTime($row['STATUS_START_DATE']);
                                $dateStr = $dt->format('m/d/Y');
                                $timeStr = $dt->format('h:i A');
                            }
                        ?>
                        <tr style="<?php echo $isActive ? 'background: rgba(59, 130, 246, 0.1);' : ''; ?>">
                            <td style="font-weight: bold; color: white;"><?php echo $row['TAG_NO']; ?></td>
                            <td><?php echo $row['STAGE_NAME']; ?></td>
                            <td><span class="status-badge <?php echo $badgeClass; ?>"><?php echo $status; ?></span></td>
                            <td>
                                <div><?php echo $dateStr; ?></div>
                                <div style="font-size: 0.85rem; color: #94a3b8; font-family: monospace;"><?php echo $timeStr; ?></div>
                            </td>
                            <td style="text-align: right;">
                                <a href="?location_id=<?php echo $location_id; ?>&building_id=<?php echo $building_id; ?>&animal_id=<?php echo $row['ANIMAL_ID']; ?>" class="btn-manage <?php echo $isActive ? 'active' : ''; ?>">
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
        <div style="text-align: center; padding: 2rem; color: #94a3b8; background: rgba(30,41,59,0.5); border-radius: 12px;">No Sows or Gilts found in this building.</div>
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
                            $btnClass = 'btn-primary'; $val = '';
                            
                            if (strpos($action, 'Undo') !== false) { $btnClass = 'btn-warning'; $val = 'undo'; }
                            elseif (strpos($action, 'Abortion') !== false) { $btnClass = 'btn-danger'; $val = 'abortion'; }
                            elseif (strpos($action, 'Recovery') !== false) { $btnClass = 'btn-success'; $val = 'next_stage'; }
                            elseif (strpos($action, 'Go to Sow Card') !== false) { $btnClass = 'btn-purple'; $val = 'redirect_sow_card'; }
                            elseif (strpos($action, 'Completed') !== false || strpos($action, 'Start') !== false || strpos($action, 'Birthing') !== false) { $btnClass = 'btn-success'; $val = 'next_stage'; }
                            elseif (strpos($action, 'Next Service') !== false) { $btnClass = 'btn-primary'; $val = 'repeat_service'; }
                        ?>
                        <button class="action-btn <?php echo $btnClass; ?>" onclick="handleAction('<?php echo $val; ?>', '<?php echo $action; ?>')">
                            <?php echo $action; ?>
                        </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="timeline-container">
                <h3 style="color: #cbd5e1; margin-top: 0; margin-bottom: 1.5rem;">Cycle History</h3>
                <?php foreach($history as $h): 
                    $histDate = new DateTime($h['STATUS_START_DATE']);
                    // Format History Date to mm/dd/yyyy
                    $hDate = $histDate->format('m/d/Y');
                    $hTime = $histDate->format('h:i A');
                ?>
                    <div class="timeline-item <?php echo $h['IS_ACTIVE'] ? 'active' : ''; ?>">
                        <div style="display:flex; justify-content:space-between; margin-bottom: 5px;">
                            <strong style="color: <?php echo $h['IS_ACTIVE'] ? '#10b981' : '#e2e8f0'; ?>"><?php echo $h['STATUS_NAME']; ?></strong>
                            <div style="text-align: right;">
                                <small style="color: #94a3b8; display: block;"><?php echo $hDate; ?></small>
                                <small style="color: #64748b; font-size: 0.75rem;"><?php echo $hTime; ?></small>
                            </div>
                        </div>
                        <?php if($h['SERVICE_TYPE']): ?>
                            <div style="font-size: 0.8rem; color: #f472b6; margin-top: 4px;">
                                Service: <?php echo $h['SERVICE_TYPE']; ?> 
                                <?php echo $h['BOAR_TAG'] ? ' | Boar: '.$h['BOAR_TAG'] : ''; ?>
                            </div>
                        <?php endif; ?>
                        <?php if($h['IS_ACTIVE']): ?>
                            <div style="color: #10b981; font-size: 0.8rem;">● Current Active Stage</div>
                        <?php else: ?>
                            <?php 
                                $endDate = new DateTime($h['STATUS_END_DATE']);
                                // Format History End Date to mm/dd/yyyy
                                $eDate = $endDate->format('m/d/Y h:i A');
                            ?>
                            <div style="color: #64748b; font-size: 0.8rem;">Ended: <?php echo $eDate; ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div id="serviceModal" class="modal">
    <div class="modal-content">
        <h2>Record Service Details</h2>
        <p>Please specify how the service was performed.</p>
        
        <form onsubmit="submitModalForm(event, this)" action="../process/sowStatusAction.php?building_id=<?php echo $building_id; ?>&location_id=<?php echo $location_id; ?>">
            <input type="hidden" name="animal_id" value="<?php echo $selected_sow_data['ANIMAL_ID'] ?? ''; ?>">
            <input type="hidden" name="current_status" value="<?php echo $current_status; ?>">
            <input type="hidden" name="action_type" id="modal_action_type">

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>Service Date & Time</label>
                <input type="text" name="action_date" id="service_date" class="form-input datetime-picker" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>Service Method</label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="service_type" value="Natural" checked> Natural
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="service_type" value="Artificial"> Artificial Insemination
                    </label>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label>Locate Boar (Optional if AI)</label>
                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <select id="boarBld" class="form-select" onchange="loadBoarPens()">
                        <option value="">-- Building --</option>
                        <?php if($location_id): foreach($buildings as $b): ?>
                            <option value="<?= $b['BUILDING_ID'] ?>"><?= htmlspecialchars($b['BUILDING_NAME']) ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                    <select id="boarPen" class="form-select" disabled onchange="loadBoars()">
                        <option value="">-- Pen --</option>
                    </select>
                </div>
                <select name="boar_id" id="boarSelect" class="form-select" disabled>
                    <option value="">-- Unknown / External --</option>
                </select>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-manage" style="border:none; color: #94a3b8;" onclick="closeModal('serviceModal')">Cancel</button>
                <button type="submit" class="btn-manage active" style="border:none; padding: 10px 20px;">Confirm & Save</button>
            </div>
        </form>
    </div>
</div>

<div id="pregnancyModal" class="modal">
    <div class="modal-content">
        <h2>Confirm Pregnancy</h2>
        <p>Please specify the date and time the sow was confirmed pregnant.</p>
        
        <form onsubmit="submitModalForm(event, this)" action="../process/sowStatusAction.php?building_id=<?php echo $building_id; ?>&location_id=<?php echo $location_id; ?>">
            <input type="hidden" name="animal_id" value="<?php echo $selected_sow_data['ANIMAL_ID'] ?? ''; ?>">
            <input type="hidden" name="current_status" value="<?php echo $current_status; ?>">
            <input type="hidden" name="action_type" id="pregnancy_action_type">

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>Confirmation Date & Time</label>
                <input type="text" name="action_date" id="pregnancy_date" class="form-input datetime-picker" required>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-manage" style="border:none; color: #94a3b8;" onclick="closeModal('pregnancyModal')">Cancel</button>
                <button type="submit" class="btn-manage active btn-success" style="border:none; padding: 10px 20px;">Save Pregnancy</button>
            </div>
        </form>
    </div>
</div>

<div id="genericModal" class="modal">
    <div class="modal-content">
        <h2 id="generic_modal_title">Confirm Action</h2>
        <p id="generic_modal_desc">Please specify the date and time for this action.</p>
        
        <form onsubmit="submitModalForm(event, this)" action="../process/sowStatusAction.php?building_id=<?php echo $building_id; ?>&location_id=<?php echo $location_id; ?>">
            <input type="hidden" name="animal_id" value="<?php echo $selected_sow_data['ANIMAL_ID'] ?? ''; ?>">
            <input type="hidden" name="current_status" value="<?php echo $current_status; ?>">
            <input type="hidden" name="action_type" id="generic_action_type">

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>Action Date & Time</label>
                <input type="text" name="action_date" id="generic_date" class="form-input datetime-picker" required>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-manage" style="border:none; color: #94a3b8;" onclick="closeModal('genericModal')">Cancel</button>
                <button type="submit" class="btn-manage active" id="genericSubmitBtn" style="border:none; padding: 10px 20px;">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Initialize Flatpickr across all DateTime inputs in modals
    document.addEventListener('DOMContentLoaded', () => {
        flatpickr(".datetime-picker", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",      // The format submitted to the backend
            altInput: true,               // Dummy input for UI
            altFormat: "m/d/Y h:i K",     // Visual Format: mm/dd/yyyy hh:mm AM/PM
            allowInput: true
        });
    });

    <?php if($selected_sow_data): ?>
        setTimeout(() => { document.getElementById('action-area').scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 300);
    <?php endif; ?>

    async function fetchJSON(url) {
        try { const r = await fetch(url); return await r.json(); } catch(e) { return []; }
    }

    function loadBoarPens() {
        const bld = document.getElementById('boarBld').value;
        const pen = document.getElementById('boarPen');
        const boar = document.getElementById('boarSelect');
        
        boar.disabled = true;
        boar.innerHTML = '<option value="">-- Unknown / External --</option>';
        
        if(!bld) {
            pen.disabled = true;
            pen.innerHTML = '<option value="">-- Pen --</option>';
            return;
        }
        
        pen.disabled = true;
        pen.innerHTML = '<option>Loading...</option>';
        
        fetchJSON(`../process/getCostData.php?action=get_pens&bld_id=${bld}`).then(data => {
            pen.innerHTML = '<option value="">-- Pen --</option>';
            data.forEach(i => pen.innerHTML += `<option value="${i.PEN_ID}">${i.PEN_NAME}</option>`);
            pen.disabled = false;
        });
    }

    function loadBoars() {
        const pen = document.getElementById('boarPen').value;
        const boar = document.getElementById('boarSelect');
        
        if(!pen) {
            boar.disabled = true;
            boar.innerHTML = '<option value="">-- Unknown / External --</option>';
            return;
        }
        
        boar.disabled = true;
        boar.innerHTML = '<option>Loading...</option>';
        
        fetchJSON(`../process/getCostData.php?action=get_boars_in_pen&pen_id=${pen}`).then(data => {
            boar.innerHTML = '<option value="">-- Unknown / External --</option>';
            data.forEach(i => boar.innerHTML += `<option value="${i.ANIMAL_ID}">${i.TAG_NO}</option>`);
            boar.disabled = false;
        });
    }

    // Modal Display Logic
    function handleAction(val, label) {
        const now = new Date();

        if (label.includes('Service')) {
            document.getElementById('modal_action_type').value = val;
            document.getElementById('service_date')._flatpickr.setDate(now); // Set flatpickr to current time
            document.getElementById('serviceModal').classList.add('show');
        } 
        else if (label.includes('Pregnant')) {
            document.getElementById('pregnancy_action_type').value = val;
            document.getElementById('pregnancy_date')._flatpickr.setDate(now); // Set flatpickr to current time
            document.getElementById('pregnancyModal').classList.add('show');
        }
        else if (val === 'redirect_sow_card') {
            const loc = '<?php echo $selected_sow_data['LOCATION_ID'] ?? ''; ?>';
            const bld = '<?php echo $selected_sow_data['BUILDING_ID'] ?? ''; ?>';
            const aid = '<?php echo $selected_animal_id; ?>';
            const pen = '<?php echo $selected_sow_data['PEN_ID'] ?? ''; ?>';
            window.location.href = `animal_sow_cards.php?location_id=${loc}&building_id=${bld}&pen_id=${pen}&animal_id=${aid}`;
        }
        // Use Generic Modal for EVERYTHING ELSE (Birthing, Abortion, Undo, etc.)
        else {
            const titleEl = document.getElementById('generic_modal_title');
            const descEl = document.getElementById('generic_modal_desc');
            const typeInput = document.getElementById('generic_action_type');
            const submitBtn = document.getElementById('genericSubmitBtn');

            typeInput.value = val;
            document.getElementById('generic_date')._flatpickr.setDate(now); // Set flatpickr to current time

            if (val === 'undo') {
                titleEl.innerText = "Confirm Undo";
                descEl.innerHTML = "<span style='color:#f87171;'>⚠️ WARNING: Undo will revert the status and close current records. Please confirm the timestamp for this reversal.</span>";
                submitBtn.className = "btn-manage active btn-warning";
            } else {
                titleEl.innerText = label;
                descEl.innerText = `Please specify the exact date and time for: ${label}`;
                
                // Style button based on action type
                if(val === 'abortion') submitBtn.className = "btn-manage active btn-danger";
                else submitBtn.className = "btn-manage active btn-success";
            }
            
            document.getElementById('genericModal').classList.add('show');
        }
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    // --- NEW: AJAX FORM HANDLER ---
    // Prevents the browser from holding onto the POST request if the user hits Refresh (F5)
    async function submitModalForm(e, form) {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.innerText;
        btn.innerText = "Saving...";
        btn.disabled = true;

        // Extract disabled elements to temporarily enable them so their values are sent (like boar_id)
        const disabledElements = form.querySelectorAll(':disabled');
        disabledElements.forEach(el => el.disabled = false);

        // Build a proper URL-encoded string so PHP $_POST reads it perfectly
        const formData = new FormData(form);
        const data = new URLSearchParams(formData);
        
        // Re-disable elements
        disabledElements.forEach(el => el.disabled = true);

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: data.toString()
            });
            
            // Re-navigate to the clean GET URL, obliterating POST history entirely
            const cleanUrl = `?location_id=<?php echo $location_id; ?>&building_id=<?php echo $building_id; ?>&animal_id=<?php echo $selected_animal_id; ?>`;
            window.location.href = cleanUrl;
            
        } catch (err) {
            console.error(err);
            alert("❌ System connection error.");
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }
</script>

</body>
</html>