<?php
// views/animal_sow_status.php
$page = "farm";
include '../config/Connection.php';

// =========================================================
// AJAX HANDLERS FOR BOAR SELECTION
// =========================================================
if (isset($_GET['action'])) {
    @ob_end_clean();
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        // Fetch only pens that have at least one active male
        if ($action === 'get_boar_pens' && isset($_GET['bld_id'])) {
            $stmt = $conn->prepare("
                SELECT DISTINCT p.PEN_ID, p.PEN_NAME,
                       (SELECT GROUP_CONCAT(TAG_NO SEPARATOR ', ') 
                        FROM animal_records a2 
                        WHERE a2.PEN_ID = p.PEN_ID AND a2.SEX = 'M' AND a2.IS_ACTIVE = 1 AND a2.CURRENT_STATUS != 'Sold') as BOAR_LIST
                FROM PENS p
                JOIN animal_records a ON p.PEN_ID = a.PEN_ID
                WHERE p.BUILDING_ID = ? AND a.SEX = 'M' AND a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold'
                ORDER BY p.PEN_NAME ASC
            ");
            $stmt->execute([$_GET['bld_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }

        // Fetch the specific boars inside the selected pen
        if ($action === 'get_boars_in_pen' && isset($_GET['pen_id'])) {
            $stmt = $conn->prepare("
                SELECT ANIMAL_ID, TAG_NO 
                FROM animal_records 
                WHERE PEN_ID = ? AND SEX = 'M' AND IS_ACTIVE = 1 AND CURRENT_STATUS != 'Sold'
                ORDER BY TAG_NO ASC
            ");
            $stmt->execute([$_GET['pen_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
// =========================================================

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
$expected_farrowing_date = null;
$expected_weaning_date = null;
$expected_pregnancy_date = null;

$location_id = $_GET['location_id'] ?? '';
$building_id = $_GET['building_id'] ?? '';
$selected_animal_id = $_GET['animal_id'] ?? '';

try {
    // --- 2. FETCH DROPDOWNS ---
    $stmt = $conn->prepare("
        SELECT l.*, 
               (SELECT COUNT(ar.ANIMAL_ID) 
                FROM animal_records ar 
                JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID 
                WHERE ar.LOCATION_ID = l.LOCATION_ID AND ar.IS_ACTIVE = 1 
                  AND (ac.STAGE_NAME LIKE '%Sow%' OR ac.STAGE_NAME LIKE '%Gilt%')
               ) as SOW_COUNT 
        FROM locations l 
        ORDER BY l.LOCATION_NAME
    ");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($location_id) {
        $stmt = $conn->prepare("
            SELECT b.*, 
                   (SELECT COUNT(ar.ANIMAL_ID) 
                    FROM animal_records ar 
                    JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID 
                    WHERE ar.BUILDING_ID = b.BUILDING_ID AND ar.IS_ACTIVE = 1 
                      AND (ac.STAGE_NAME LIKE '%Sow%' OR ac.STAGE_NAME LIKE '%Gilt%')
                   ) as SOW_COUNT 
            FROM buildings b 
            WHERE b.LOCATION_ID = ? 
            ORDER BY b.BUILDING_NAME
        ");
        $stmt->execute([$location_id]);
        $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 3. FETCH SOW LIST ---
    if ($building_id) {
        // FIX: Changed MAX(SERVICE_START_DATE) to fetch the currently ACTIVE,
        // non-cancelled service record instead, so it always reflects the real
        // assigned service date instead of the highest date across all history.
        $sql = "
            SELECT 
                ar.ANIMAL_ID, 
                ar.TAG_NO, 
                ac.STAGE_NAME, 
                IFNULL(ssh.STATUS_NAME, 'DRY') as CURRENT_STATUS, 
                ssh.STATUS_START_DATE,
                (
                    SELECT srv.SERVICE_START_DATE 
                    FROM sow_service_history srv 
                    WHERE srv.ANIMAL_ID = ar.ANIMAL_ID 
                    ORDER BY srv.SERVICE_ID DESC 
                    LIMIT 1
                ) as LAST_SERVICE_DATE
            FROM animal_records ar 
            JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID 
            LEFT JOIN sow_status_history ssh 
                ON ar.ANIMAL_ID = ssh.ANIMAL_ID AND ssh.IS_ACTIVE = 1 
            WHERE ar.BUILDING_ID = ? 
              AND ar.IS_ACTIVE = 1 
              AND (ac.STAGE_NAME LIKE '%Sow%' OR ac.STAGE_NAME LIKE '%Gilt%') 
            ORDER BY ar.TAG_NO ASC
        ";
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

            // Fetch History
            $histSql = "
                SELECT sh.*, srv.SERVICE_TYPE, srv.BOAR_ID, boar.TAG_NO as BOAR_TAG 
                FROM sow_status_history sh 
                LEFT JOIN sow_service_history srv 
                    ON sh.ANIMAL_ID = srv.ANIMAL_ID 
                    AND sh.STATUS_START_DATE = srv.SERVICE_START_DATE 
                    AND sh.STATUS_NAME LIKE CONCAT('SERVICE ', srv.SERVICE_NUMBER) 
                LEFT JOIN animal_records boar ON srv.BOAR_ID = boar.ANIMAL_ID 
                WHERE sh.ANIMAL_ID = ? 
                ORDER BY sh.STATUS_START_DATE DESC, sh.STATUS_ID DESC
            ";
            $stmtHist = $conn->prepare($histSql);
            $stmtHist->execute([$selected_animal_id]);
            $history = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
            $hasHistory = count($history) > 1;

            // Calculate expected dates based on history
            foreach ($history as $h) {
                if (strpos($h['STATUS_NAME'], 'SERVICE') !== false && !$expected_farrowing_date) {
                    $serviceDate = new DateTime($h['STATUS_START_DATE']);
                    $serviceDatePreg = clone $serviceDate;
                    
                    // Farrowing is +114 days
                    $serviceDate->modify('+114 days');
                    $expected_farrowing_date = $serviceDate->format('Y-m-d H:i');
                    
                    // Pregnancy Confirmation is +25 days
                    $serviceDatePreg->modify('+25 days');
                    $expected_pregnancy_date = $serviceDatePreg->format('Y-m-d H:i');
                }
                if ($h['STATUS_NAME'] === 'BIRTHING' && !$expected_weaning_date) {
                    $birthDate = new DateTime($h['STATUS_START_DATE']);
                    $birthDate->modify('+28 days');
                    $expected_weaning_date = $birthDate->format('Y-m-d H:i');
                }
            }

            switch($current_status) {
                case 'DRY':
                    $actions = ['Start Service 1'];
                    if ($hasHistory) $actions[] = 'Undo';
                    break;
                case 'SERVICE 1':
                case 'SERVICE 2':
                case 'SERVICE 3':
                case 'SERVICE 4':
                    $actions = ['Confirmed (Pregnant)', 'Next Service (Reheat)', 'Undo'];
                    break;
                case 'SERVICE 5':
                    $actions = ['Confirmed (Pregnant)', 'Undo'];
                    break;
                case 'PREGNANT':
                    $actions = ['Birthing Started', 'Abortion', 'Undo'];
                    break;
                case 'ABORTION':
                    $actions = ['Recovery (Reset to Dry)', 'Undo'];
                    break;
                case 'BIRTHING':
                    if ($sow_card_done == 1) {
                        $actions = ['Completed (Reset to Dry)', 'Undo'];
                    } else {
                        $actions = ['Go to Sow Card (Required)', 'Undo'];
                    }
                    break;
            }
        }
    }

} catch (Exception $e) { echo "Error: " . $e->getMessage(); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sow Breeding Management | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(236,72,153,0.5);
            
            --pink:           #ec4899;
            --pink-dim:       rgba(236,72,153,0.12);
            --pink-glow:      rgba(236,72,153,0.25);
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --amber:          #f59e0b;
            --amber-dim:      rgba(245,158,11,0.12);
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
            --blue:           #3b82f6;
            --purple:         #a855f7;
            
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --shadow-md:      0 4px 16px rgba(0,0,0,0.4);
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(236,72,153,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
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
            color: var(--pink); background: var(--pink-dim); border: 1px solid rgba(236,72,153,0.2);
            padding: 6px 12px; border-radius: 99px;
        }
        
        .page-header { margin-bottom: 2rem; }
        .page-header h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; }
        .page-header h1 span { background: linear-gradient(135deg, var(--pink), #be185d); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        /* ─── FILTERS ─── */
        .filter-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2rem;
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; align-items: flex-end;
            box-shadow: var(--shadow-md);
        }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { color: var(--text-secondary); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .form-select {
            width: 100%; padding: 12px 16px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition); appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-select:focus { border-color: var(--pink); box-shadow: 0 0 0 3px var(--pink-glow); background: var(--bg-hover); }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; }

        /* ─── TABLE ─── */
        .table-container {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden; margin-bottom: 3rem; box-shadow: var(--shadow-md);
        }
        .table-scroll-wrapper { width: 100%; overflow-x: auto; }
        .sow-table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .sow-table th {
            background: var(--bg-elevated); color: var(--text-muted); font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.07em; padding: 16px; text-align: left; border-bottom: 1px solid var(--border);
        }
        .sow-table td { padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.02); color: var(--text-primary); vertical-align: middle; }
        .sow-table tr:hover { background: rgba(255,255,255,0.02); }
        .sow-table tr.active-row { background: var(--pink-dim); border-left: 3px solid var(--pink); }

        .tag-no { font-family: var(--font-mono); font-weight: 700; font-size: 1rem; color: #fff; }
        .stage-name { color: var(--text-secondary); font-size: 0.9rem; }
        .date-val { font-family: var(--font-mono); font-size: 0.9rem; color: var(--text-primary); }
        .time-val { font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-muted); }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px; border-radius: 99px; font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; white-space: nowrap;
        }
        .status-dry      { background: var(--bg-elevated); color: var(--text-secondary); border: 1px solid var(--border); }
        .status-service  { background: var(--pink-dim); color: var(--pink); border: 1px solid rgba(236,72,153,0.3); }
        .status-pregnant { background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.3); }
        .status-birthing { background: var(--amber-dim); color: var(--amber); border: 1px solid rgba(245,158,11,0.3); }
        .status-abortion { background: var(--red-dim); color: var(--red); border: 1px solid rgba(239,68,68,0.3); }
        
        .btn-manage {
            background: var(--bg-elevated); color: var(--text-primary); border: 1px solid var(--border);
            padding: 8px 16px; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-flex;
            align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 600; font-family: var(--font); transition: var(--transition);
        }
        .btn-manage:hover { background: var(--bg-hover); border-color: var(--text-muted); }
        .btn-manage.active { background: var(--pink); color: #000; border-color: var(--pink); box-shadow: 0 4px 12px var(--pink-glow); }
        .btn-manage.active:hover { background: #f472b6; transform: translateY(-1px); }

        /* ─── DETAIL SECTION ─── */
        .detail-section {
            display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;
            animation: slideIn 0.3s ease-out forwards; opacity: 0; transform: translateY(20px);
        }
        @keyframes slideIn { to { opacity: 1; transform: translateY(0); } }

        /* Status Card (Left Pane) */
        .status-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 2rem; text-align: center; height: fit-content;
            box-shadow: var(--shadow-md); position: relative; overflow: hidden;
        }
        .status-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--pink), #be185d);
        }
        .status-card h3 { font-size: 1.5rem; margin: 0 0 0.5rem 0; color: #fff; font-family: var(--font-mono); }
        .status-card .lbl { color: var(--text-secondary); font-size: 0.85rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
        .current-status-large {
            font-size: 2.5rem; font-weight: 800; margin: 1rem 0 2rem 0;
            background: linear-gradient(135deg, var(--pink), #db2777);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; line-height: 1.1;
        }
        
        .action-btn {
            display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%;
            padding: 14px; margin-bottom: 12px; border-radius: var(--radius-md); border: none;
            font-weight: 700; font-family: var(--font); cursor: pointer; font-size: 0.95rem; transition: var(--transition);
        }
        .action-btn i { font-size: 1.1rem; }
        .btn-primary { background: var(--pink); color: #000; }
        .btn-primary:hover { background: #f472b6; box-shadow: 0 4px 15px var(--pink-glow); transform: translateY(-1px); }
        
        .btn-success { background: var(--emerald); color: #000; }
        .btn-success:hover { background: #34d399; box-shadow: 0 4px 15px var(--emerald-glow); transform: translateY(-1px); }
        
        .btn-warning { background: var(--amber-dim); color: var(--amber); border: 1px solid rgba(245,158,11,0.3); }
        .btn-warning:hover { background: rgba(245,158,11,0.2); border-color: var(--amber); }
        
        .btn-purple { background: var(--purple); color: #fff; }
        .btn-purple:hover { background: #c084fc; box-shadow: 0 4px 15px var(--purple-glow); transform: translateY(-1px); }
        
        .btn-danger { background: var(--red-dim); color: var(--red); border: 1px solid rgba(239,68,68,0.3); }
        .btn-danger:hover { background: rgba(239,68,68,0.2); border-color: var(--red); }

        /* Timeline (Right Pane) */
        .timeline-container {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-md);
        }
        .timeline-title { color: var(--text-primary); font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;}
        
        .timeline { position: relative; padding-left: 20px; }
        .timeline::before { content: ''; position: absolute; left: 6px; top: 10px; bottom: 10px; width: 2px; background: var(--bg-elevated); border-radius: 2px; }
        
        .timeline-item { position: relative; padding-bottom: 1.5rem; }
        .timeline-item:last-child { padding-bottom: 0; }
        
        .timeline-dot {
            position: absolute; left: -20px; top: 4px; width: 14px; height: 14px;
            border-radius: 50%; background: var(--bg-elevated); border: 2px solid var(--text-muted);
            z-index: 2; transition: all 0.3s;
        }
        .timeline-item.active .timeline-dot { background: var(--emerald); border-color: var(--emerald); box-shadow: 0 0 10px var(--emerald-glow); }
        
        .timeline-content { background: var(--bg-elevated); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border); transition: all 0.2s;}
        .timeline-item.active .timeline-content { border-color: rgba(16,185,129,0.3); }
        .timeline-content:hover { background: var(--bg-hover); }
        
        .tl-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
        .tl-status { font-weight: 700; color: var(--text-primary); font-size: 1.05rem; }
        .timeline-item.active .tl-status { color: var(--emerald); }
        
        .tl-time { text-align: right; }
        .tl-date { color: var(--text-secondary); font-family: var(--font-mono); font-size: 0.85rem; font-weight: 600; }
        .tl-hour { color: var(--text-muted); font-family: var(--font-mono); font-size: 0.75rem; }
        
        .tl-meta { font-size: 0.85rem; color: var(--pink); margin-top: 8px; display: inline-flex; align-items: center; gap: 6px; background: var(--pink-dim); padding: 4px 10px; border-radius: 6px;}
        .tl-ended { font-size: 0.8rem; color: var(--text-muted); margin-top: 8px; font-family: var(--font-mono); }

        /* ─── MODALS ─── */
        .modal {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85);
            backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;
            padding: 1rem; box-sizing: border-box;
        }
        .modal.show { display: flex; }
        
        .modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 500px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); display: flex; flex-direction: column;
            animation: modalZoom 0.2s ease-out;
        }
        @keyframes modalZoom { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border); }
        .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: #fff; }
        .modal-header p { margin: 5px 0 0 0; color: var(--text-secondary); font-size: 0.9rem; }
        .modal-body { padding: 2rem; overflow-y: auto; }
        .modal-footer { padding: 1.25rem 2rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); border-radius: 0 0 var(--radius-xl) var(--radius-xl);}

        .form-input {
            width: 100%; padding: 12px 16px; background: var(--bg-elevated);
            border: 1px solid var(--border); border-radius: var(--radius-md);
            color: var(--text-primary); font-size: 0.95rem; font-family: var(--font); outline: none; transition: all var(--transition);
        }
        .form-input:focus { border-color: var(--pink); box-shadow: 0 0 0 3px var(--pink-glow); }

        .radio-group { display: flex; gap: 1.5rem; margin-top: 8px; }
        .radio-label { display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary); font-size: 0.95rem; font-weight: 500;}
        .radio-label input[type="radio"] { appearance: none; width: 20px; height: 20px; border: 2px solid var(--text-muted); border-radius: 50%; outline: none; transition: var(--transition); cursor: pointer; position: relative; margin: 0;}
        .radio-label input[type="radio"]:checked { border-color: var(--pink); }
        .radio-label input[type="radio"]:checked::after { content: ''; position: absolute; top: 4px; left: 4px; width: 8px; height: 8px; background: var(--pink); border-radius: 50%; }

        /* Toast Notifications */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.9rem; font-weight: 600; animation: toastIn 0.3s ease-out;
        }
        @keyframes toastIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .detail-section { grid-template-columns: 1fr; }
            .filter-card { grid-template-columns: 1fr; gap: 1rem; padding: 1.25rem; }
            .modal-content { max-width: 95%; margin: 10px; }
        }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">
    
    <div class="top-bar">
        <a href="farm_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Farm Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-venus"></i> Reproductive Engine</span>
    </div>

    <div class="page-header">
        <h1>Sow Breeding <span>Management</span></h1>
    </div>

    <form class="filter-card" method="GET">
        <div class="form-group">
            <label>1. Select Location</label>
            <select name="location_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Choose Location --</option>
                <?php foreach($locations as $loc): ?>
                    <option value="<?php echo $loc['LOCATION_ID']; ?>" <?php echo $location_id == $loc['LOCATION_ID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($loc['LOCATION_NAME']) . ' (' . $loc['SOW_COUNT'] . ')'; ?>
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
                        <?php echo htmlspecialchars($b['BUILDING_NAME']) . ' (' . $b['SOW_COUNT'] . ')'; ?>
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
                            <th>Last Service Date</th>
                            <th>Status Date</th>
                            <th>Est. Farrowing (114d)</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($sow_list as $row): 
                            $status = $row['CURRENT_STATUS'];
                            $badgeClass = 'status-dry';
                            if(strpos($status, 'SERVICE') !== false) $badgeClass = 'status-service';
                            if($status == 'PREGNANT') $badgeClass = 'status-pregnant';
                            if($status == 'BIRTHING') $badgeClass = 'status-birthing';
                            if($status == 'ABORTION') $badgeClass = 'status-abortion';
                            $isActive = ($selected_animal_id == $row['ANIMAL_ID']);

                            $dateStr = 'N/A'; $timeStr = '';
                            if ($row['STATUS_START_DATE']) {
                                $dt = new DateTime($row['STATUS_START_DATE']);
                                $dateStr = $dt->format('m/d/Y');
                                $timeStr = $dt->format('h:i A');
                            }

                            // Show last service date and est. farrowing for SERVICE and PREGNANT rows
                            $lastServiceStr = '-';
                            $estFarrowingStr = '-';
                            
                            if ((strpos($status, 'SERVICE') !== false || $status === 'PREGNANT') && !empty($row['LAST_SERVICE_DATE'])) {
                                $lsDt = new DateTime($row['LAST_SERVICE_DATE']);
                                $lastServiceStr = $lsDt->format('m/d/Y');
                                $efDt = clone $lsDt;
                                $efDt->modify('+114 days');
                                $estFarrowingStr = $efDt->format('m/d/Y');
                            } elseif ((strpos($status, 'SERVICE') !== false || $status === 'PREGNANT') && !empty($row['STATUS_START_DATE'])) {
                                // Fallback: use the status start date itself if no service record found
                                $lsDt = new DateTime($row['STATUS_START_DATE']);
                                $lastServiceStr = $lsDt->format('m/d/Y') . ' *';
                                $efDt = clone $lsDt;
                                $efDt->modify('+114 days');
                                $estFarrowingStr = $efDt->format('m/d/Y') . ' *';
                            }
                        ?>
                        <tr class="<?php echo $isActive ? 'active-row' : ''; ?>">
                            <td><div class="tag-no"><?php echo $row['TAG_NO']; ?></div></td>
                            <td><div class="stage-name"><?php echo $row['STAGE_NAME']; ?></div></td>
                            <td><span class="status-badge <?php echo $badgeClass; ?>"><?php echo $status; ?></span></td>
                            
                            <td><div class="date-val"><?php echo $lastServiceStr; ?></div></td>
                            
                            <td>
                                <div class="date-val"><?php echo $dateStr; ?></div>
                                <div class="time-val"><?php echo $timeStr; ?></div>
                            </td>
                            
                            <td>
                                <div class="date-val" <?php echo ($status === 'PREGNANT' || strpos($status, 'SERVICE') !== false) ? 'style="color: var(--pink); font-weight: 600;"' : ''; ?>>
                                    <?php echo $estFarrowingStr; ?>
                                </div>
                            </td>

                            <td style="text-align: right;">
                                <a href="?location_id=<?php echo $location_id; ?>&building_id=<?php echo $building_id; ?>&animal_id=<?php echo $row['ANIMAL_ID']; ?>" class="btn-manage <?php echo $isActive ? 'active' : ''; ?>">
                                    <?php echo $isActive ? '<i class="fa-solid fa-check"></i> Selected' : 'Manage Status'; ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif($building_id): ?>
        <div style="text-align: center; padding: 3rem 2rem; color: var(--text-muted); background: var(--bg-surface); border: 1px dashed var(--border); border-radius: var(--radius-xl); margin-bottom: 2rem;">
            <i class="fa-solid fa-piggy-bank" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <h3>No Sows or Gilts found in this building.</h3>
        </div>
    <?php endif; ?>

    <?php if($selected_sow_data): ?>
        <div id="action-area" class="detail-section">
            
            <div class="status-card">
                <div class="lbl">Tag Number</div>
                <h3><?php echo $selected_sow_data['TAG_NO']; ?></h3>
                
                <div class="lbl" style="margin-top: 1.5rem;">Current Cycle Status</div>
                <div class="current-status-large"><?php echo $current_status; ?></div>
                
                <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 8px;">
                    <?php if (empty($actions)): ?>
                        <p style="color:var(--text-muted); font-style: italic;">No actions available for this state.</p>
                    <?php else: ?>
                        <?php foreach($actions as $action): 
                            $btnClass = 'btn-primary'; $val = ''; $icon = '';
                            
                            if (strpos($action, 'Undo') !== false)                                                    { $btnClass = 'btn-warning'; $val = 'undo';           $icon = '<i class="fa-solid fa-rotate-left"></i>'; }
                            elseif (strpos($action, 'Abortion') !== false)                                            { $btnClass = 'btn-danger';  $val = 'abortion';        $icon = '<i class="fa-solid fa-triangle-exclamation"></i>'; }
                            elseif (strpos($action, 'Recovery') !== false)                                            { $btnClass = 'btn-success'; $val = 'next_stage';      $icon = '<i class="fa-solid fa-heart-pulse"></i>'; }
                            elseif (strpos($action, 'Go to Sow Card') !== false)                                      { $btnClass = 'btn-purple';  $val = 'redirect_sow_card'; $icon = '<i class="fa-solid fa-clipboard-list"></i>'; }
                            elseif (strpos($action, 'Confirmed') !== false || strpos($action, 'Start') !== false || strpos($action, 'Birthing') !== false || strpos($action, 'Completed') !== false) { $btnClass = 'btn-success'; $val = 'next_stage'; $icon = '<i class="fa-solid fa-check-double"></i>'; }
                            elseif (strpos($action, 'Next Service') !== false)                                        { $btnClass = 'btn-primary'; $val = 'repeat_service';  $icon = '<i class="fa-solid fa-repeat"></i>'; }
                        ?>
                        <button class="action-btn <?php echo $btnClass; ?>" onclick="handleAction('<?php echo $val; ?>', '<?php echo addslashes($action); ?>')">
                            <?php echo $icon . ' ' . $action; ?>
                        </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="timeline-container">
                <h3 class="timeline-title"><i class="fa-solid fa-clock-rotate-left" style="color:var(--pink);"></i> Cycle History</h3>
                <div class="timeline">
                    <?php foreach($history as $h): 
                        $histDate = new DateTime($h['STATUS_START_DATE']);
                        $hDate = $histDate->format('m/d/Y');
                        $hTime = $histDate->format('h:i A');
                    ?>
                        <div class="timeline-item <?php echo $h['IS_ACTIVE'] ? 'active' : ''; ?>">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div class="tl-header">
                                    <div class="tl-status">
                                        <?php echo $h['STATUS_NAME']; ?>
                                        <?php if($h['PARITY']) echo "<span style='font-size:0.8rem; color:var(--text-muted); font-weight:500; margin-left:8px;'>(Parity: {$h['PARITY']})</span>"; ?>
                                    </div>
                                    <div class="tl-time">
                                        <div class="tl-date"><?php echo $hDate; ?></div>
                                        <div class="tl-hour"><?php echo $hTime; ?></div>
                                    </div>
                                </div>
                                
                                <?php if($h['SERVICE_TYPE']): ?>
                                    <div class="tl-meta">
                                        <i class="fa-solid fa-syringe"></i> <?php echo $h['SERVICE_TYPE']; ?> 
                                        <?php echo $h['BOAR_TAG'] ? '&nbsp;|&nbsp;<i class="fa-solid fa-mars"></i> Boar: '.$h['BOAR_TAG'] : ''; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if($h['IS_ACTIVE']): ?>
                                    <div style="color: var(--emerald); font-size: 0.8rem; margin-top: 8px; font-weight: 700;"><i class="fa-solid fa-circle-dot"></i> Current Active Stage</div>
                                <?php else: ?>
                                    <?php if (!empty($h['STATUS_END_DATE'])): 
                                        $endDate = new DateTime($h['STATUS_END_DATE']);
                                        $eDate = $endDate->format('m/d/Y h:i A');
                                    ?>
                                    <div class="tl-ended">Concluded: <?php echo $eDate; ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    <?php endif; ?>
</div>

<!-- SERVICE MODAL -->
<div id="serviceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h2>Record Service Details</h2>
                <p>Specify how the service was performed.</p>
            </div>
        </div>
        <div class="modal-body">
            <form id="form-service" action="../process/sowStatusAction.php?building_id=<?php echo $building_id; ?>&location_id=<?php echo $location_id; ?>">
                <input type="hidden" name="animal_id" value="<?php echo $selected_sow_data['ANIMAL_ID'] ?? ''; ?>">
                <input type="hidden" name="current_status" value="<?php echo $current_status; ?>">
                <input type="hidden" name="action_type" id="modal_action_type">

                <div class="form-group">
                    <label class="form-label">Service Date &amp; Time</label>
                    <input type="text" name="action_date" id="service_date" class="form-input datetime-picker" required>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Service Method</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="service_type" value="Natural" checked> Natural
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="service_type" value="Artificial"> AI
                        </label>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Locate Boar (Optional if AI)</label>
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
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-manage" style="border:none;" onclick="closeModal('serviceModal')">Cancel</button>
            <button type="submit" form="form-service" class="btn-manage active" style="border:none;" onclick="submitModalForm(event, document.getElementById('form-service'))">Confirm &amp; Save</button>
        </div>
    </div>
</div>

<!-- PREGNANCY MODAL -->
<div id="pregnancyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h2>Confirm Pregnancy</h2>
                <p>Specify the date and time the sow was confirmed pregnant.</p>
            </div>
        </div>
        <div class="modal-body">
            <form id="form-pregnancy" action="../process/sowStatusAction.php?building_id=<?php echo $building_id; ?>&location_id=<?php echo $location_id; ?>">
                <input type="hidden" name="animal_id" value="<?php echo $selected_sow_data['ANIMAL_ID'] ?? ''; ?>">
                <input type="hidden" name="current_status" value="<?php echo $current_status; ?>">
                <input type="hidden" name="action_type" id="pregnancy_action_type">

                <div class="form-group">
                    <label class="form-label">Confirmation Date &amp; Time</label>
                    <input type="text" name="action_date" id="pregnancy_date" class="form-input datetime-picker" required>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-manage" style="border:none;" onclick="closeModal('pregnancyModal')">Cancel</button>
            <button type="submit" form="form-pregnancy" class="action-btn btn-success" style="width:auto; margin:0;" onclick="submitModalForm(event, document.getElementById('form-pregnancy'))">Save Pregnancy</button>
        </div>
    </div>
</div>

<!-- GENERIC MODAL (Birthing, Dry, Undo, Abortion) -->
<div id="genericModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h2 id="generic_modal_title">Confirm Action</h2>
                <p id="generic_modal_desc">Please specify the date and time for this action.</p>
            </div>
        </div>
        <div class="modal-body">
            <form id="form-generic" action="../process/sowStatusAction.php?building_id=<?php echo $building_id; ?>&location_id=<?php echo $location_id; ?>">
                <input type="hidden" name="animal_id" value="<?php echo $selected_sow_data['ANIMAL_ID'] ?? ''; ?>">
                <input type="hidden" name="current_status" value="<?php echo $current_status; ?>">
                <input type="hidden" name="action_type" id="generic_action_type">

                <div class="form-group">
                    <label class="form-label">Action Date &amp; Time</label>
                    <input type="text" name="action_date" id="generic_date" class="form-input datetime-picker" required>
                </div>

                <div class="form-group" id="generic_parity_group" style="margin-top: 1.5rem; display: none;">
                    <label class="form-label">Parity Number (Optional)</label>
                    <input type="number" name="parity" id="generic_parity_input" class="form-input" min="1" placeholder="Leave blank to auto-calculate">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-manage" style="border:none;" onclick="closeModal('genericModal')">Cancel</button>
            <button type="submit" form="form-generic" class="action-btn" id="genericSubmitBtn" style="width:auto; margin:0;" onclick="submitModalForm(event, document.getElementById('form-generic'))">Confirm</button>
        </div>
    </div>
</div>

<script>
    // Extract calculated dates from PHP
    const expectedFarrowingDate  = "<?= $expected_farrowing_date  ?? '' ?>";
    const expectedWeaningDate    = "<?= $expected_weaning_date    ?? '' ?>";
    const expectedPregnancyDate  = "<?= $expected_pregnancy_date  ?? '' ?>";

    // Initialize Flatpickr across all DateTime inputs in modals
    document.addEventListener('DOMContentLoaded', () => {
        flatpickr(".datetime-picker", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",      // Format submitted to the backend
            altInput: true,               // Dummy input for UI display
            altFormat: "M j, Y h:i K",    // Visual Format: Jan 1, 2024 12:00 AM
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
        
        fetchJSON(`?action=get_boar_pens&bld_id=${bld}`).then(data => {
            pen.innerHTML = '<option value="">-- Select Pen --</option>';
            if(data && data.length > 0) {
                data.forEach(i => {
                    pen.innerHTML += `<option value="${i.PEN_ID}" title="Boars inside: ${i.BOAR_LIST}">${i.PEN_NAME} (${i.BOAR_LIST})</option>`;
                });
                pen.disabled = false;
            } else {
                pen.innerHTML = '<option value="">-- No Boars Found --</option>';
            }
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
        
        fetchJSON(`?action=get_boars_in_pen&pen_id=${pen}`).then(data => {
            boar.innerHTML = '<option value="">-- Unknown / External --</option>';
            if(data && data.length > 0) {
                data.forEach(i => boar.innerHTML += `<option value="${i.ANIMAL_ID}">${i.TAG_NO}</option>`);
                boar.disabled = false;
            }
        });
    }

    function handleAction(val, label) {
        const now = new Date();

        if (label.includes('Service')) {
            document.getElementById('modal_action_type').value = val;
            document.getElementById('service_date')._flatpickr.setDate(now); 
            
            // Reset Boar dropdowns
            document.getElementById('boarBld').value = '';
            document.getElementById('boarPen').innerHTML = '<option value="">-- Pen --</option>';
            document.getElementById('boarPen').disabled = true;
            document.getElementById('boarSelect').innerHTML = '<option value="">-- Unknown / External --</option>';
            document.getElementById('boarSelect').disabled = true;

            document.getElementById('serviceModal').classList.add('show');
        } 
        else if (label.includes('Pregnant')) {
            document.getElementById('pregnancy_action_type').value = val;
            
            let defaultDate = now;
            if (expectedPregnancyDate) {
                defaultDate = expectedPregnancyDate;
            }
            
            document.getElementById('pregnancy_date')._flatpickr.setDate(defaultDate);
            document.getElementById('pregnancyModal').classList.add('show');
        }
        else if (val === 'redirect_sow_card') {
            const loc = '<?php echo $selected_sow_data['LOCATION_ID'] ?? ''; ?>';
            const bld = '<?php echo $selected_sow_data['BUILDING_ID'] ?? ''; ?>';
            const aid = '<?php echo $selected_animal_id; ?>';
            const pen = '<?php echo $selected_sow_data['PEN_ID'] ?? ''; ?>';
            window.location.href = `animal_sow_cards.php?location_id=${loc}&building_id=${bld}&pen_id=${pen}&animal_id=${aid}`;
        }
        // Generic Modal (Birthing, Reset to Dry, Undo, Abortion)
        else {
            const titleEl    = document.getElementById('generic_modal_title');
            const descEl     = document.getElementById('generic_modal_desc');
            const typeInput  = document.getElementById('generic_action_type');
            const submitBtn  = document.getElementById('genericSubmitBtn');
            const parityGrp  = document.getElementById('generic_parity_group');

            typeInput.value = val;
            
            // Determine default date based on the specific action
            let defaultDate = now;
            if (label === 'Birthing Started' && expectedFarrowingDate) {
                defaultDate = expectedFarrowingDate;
            } else if (label.includes('Reset to Dry') && expectedWeaningDate) {
                defaultDate = expectedWeaningDate;
            }
            
            document.getElementById('generic_date')._flatpickr.setDate(defaultDate);

            if (val === 'undo') {
                titleEl.innerText = "Confirm Undo";
                descEl.innerHTML  = "<span style='color:var(--red); font-weight:600;'><i class='fa-solid fa-triangle-exclamation'></i> WARNING: Undo will revert the status and close current records. Please confirm the timestamp for this reversal.</span>";
                submitBtn.className = "action-btn btn-warning";
                parityGrp.style.display = "none";
            } else {
                titleEl.innerText = label;
                descEl.innerText  = `Please specify the exact date and time for: ${label}`;
                
                if (label === 'Birthing Started') {
                    parityGrp.style.display = "flex";
                    document.getElementById('generic_parity_input').value = '';
                } else {
                    parityGrp.style.display = "none";
                }
                
                if(val === 'abortion') submitBtn.className = "action-btn btn-danger";
                else submitBtn.className = "action-btn btn-success";
            }
            
            document.getElementById('genericModal').classList.add('show');
        }
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    // Prevents the browser from holding onto the POST request on Refresh (F5)
    async function submitModalForm(e, form) {
        e.preventDefault();
        
        const btn = document.querySelector(`button[form="${form.id}"]`);
        
        let originalText = '';
        if (btn) {
            originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            btn.disabled = true;
        }

        // Temporarily enable disabled fields so their values are sent
        const disabledElements = form.querySelectorAll(':disabled');
        disabledElements.forEach(el => el.disabled = false);

        const formData = new FormData(form);
        const data = new URLSearchParams(formData);
        
        // Re-disable them
        disabledElements.forEach(el => el.disabled = true);

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: data.toString()
            });
            
            const text = await res.text();
            try {
                const jsonResp = JSON.parse(text);
                if (jsonResp.error) {
                    showToast(jsonResp.error, "error");
                    if (btn) {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                    return;
                }
            } catch (e) {
                // Not JSON — treat as redirect/success
            }
            
            // Re-navigate to the clean GET URL
            const cleanUrl = `?location_id=<?php echo $location_id; ?>&building_id=<?php echo $building_id; ?>&animal_id=<?php echo $selected_animal_id; ?>`;
            window.location.href = cleanUrl;
            
        } catch (err) {
            console.error(err);
            showToast("System connection error.", "error");
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    }

    function showToast(msg, type = 'success') {
        const container = document.getElementById('toastContainer');
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--emerald)'}`;
        t.innerHTML = `${type === 'error' ? '<i class="fa-solid fa-circle-exclamation"></i>' : '<i class="fa-solid fa-circle-check"></i>'} ${msg}`;
        container.appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }
</script>

</body>
</html>