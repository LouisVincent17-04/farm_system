<?php
// views/group_feed_transaction.php
error_reporting(0);
ini_set('display_errors', 0);
$page="transactions";

include '../config/Connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../functions/getUsersLocation.php'; 

// =========================================================
// INTERNAL AJAX HANDLERS
// =========================================================
if (isset($_GET['action'])) {
    
    if ($_GET['action'] === 'get_pens_animals' && isset($_GET['bldg_id'])) {
        @ob_end_clean();
        header('Content-Type: application/json');
        $bldg_id = $_GET['bldg_id'];
        
        $sql = "SELECT p.PEN_ID, p.PEN_NAME, a.ANIMAL_ID, a.TAG_NO 
                FROM PENS p 
                LEFT JOIN ANIMAL_RECORDS a ON p.PEN_ID = a.PEN_ID AND a.IS_ACTIVE = 1 AND a.CURRENT_STATUS = 'Active'
                WHERE p.BUILDING_ID = ? 
                ORDER BY p.PEN_NAME, a.TAG_NO";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([$bldg_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [];
        foreach($results as $r) {
            $pid = $r['PEN_ID'];
            if(!isset($data[$pid])) {
                $data[$pid] = ['pen_id' => $pid, 'pen_name' => $r['PEN_NAME'], 'animals' => []];
            }
            if($r['ANIMAL_ID']) {
                $data[$pid]['animals'][] = ['animal_id' => $r['ANIMAL_ID'], 'tag_no' => $r['TAG_NO']];
            }
        }
        echo json_encode(array_values($data));
        exit;
    }

    if ($_GET['action'] === 'get_feeds') {
        @ob_end_clean();
        header('Content-Type: application/json');
        if ($USER_LOCATION_ != 1000) {
            $stmt = $conn->prepare("SELECT FEED_ID, FEED_NAME, TOTAL_WEIGHT_KG, LOCATION_ID FROM FEEDS WHERE LOCATION_ID = ? ORDER BY FEED_NAME ASC");
            $stmt->execute([$USER_LOCATION_]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            echo json_encode($conn->query("SELECT FEED_ID, FEED_NAME, TOTAL_WEIGHT_KG, LOCATION_ID FROM FEEDS ORDER BY FEED_NAME ASC")->fetchAll(PDO::FETCH_ASSOC));
        }
        exit;
    }
}
// =========================================================

include '../security/checkAccess.php';
checkAccess('feeding');
include '../common/navbar.php';
include '../common/chat_support.php';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // 1. Transaction History 
    $transactions_sql = "
        SELECT 
            ft.FT_ID,
            ft.TRANSACTION_DATE,
            DATE_FORMAT(ft.TRANSACTION_DATE, '%m/%d/%Y %h:%i %p') AS FORMATTED_DATE,
            ft.QUANTITY_KG,
            ft.REMARKS,
            a.TAG_NO,
            p.PEN_NAME,
            f.FEED_NAME
        FROM FEED_TRANSACTIONS ft
        LEFT JOIN ANIMAL_RECORDS a ON ft.ANIMAL_ID = a.ANIMAL_ID
        LEFT JOIN PENS p ON a.PEN_ID = p.PEN_ID
        LEFT JOIN BUILDINGS b ON p.BUILDING_ID = b.BUILDING_ID
        LEFT JOIN FEEDS f ON ft.FEED_ID = f.FEED_ID
    ";
    
    if ($USER_LOCATION_ != 1000) {
        $transactions_sql .= " WHERE b.LOCATION_ID = :loc_id ";
    }
    
    $transactions_sql .= " ORDER BY ft.TRANSACTION_DATE DESC, ft.FT_ID DESC LIMIT 100";
    
    $stmt = $conn->prepare($transactions_sql);
    if ($USER_LOCATION_ != 1000) {
        $stmt->execute([':loc_id' => $USER_LOCATION_]);
    } else {
        $stmt->execute();
    }
    $transactions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Locations 
    if ($USER_LOCATION_ != 1000) {
        $loc_stmt = $conn->prepare("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME ASC");
        $loc_stmt->execute([$USER_LOCATION_]);
        $locations = $loc_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $locations = $conn->query("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 3. Feeds 
    if ($USER_LOCATION_ != 1000) {
        $feeds_stmt = $conn->prepare("SELECT FEED_ID, FEED_NAME, TOTAL_WEIGHT_KG, LOCATION_ID FROM FEEDS WHERE LOCATION_ID = ? ORDER BY FEED_NAME ASC");
        $feeds_stmt->execute([$USER_LOCATION_]);
        $feeds = $feeds_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $feeds = $conn->query("SELECT FEED_ID, FEED_NAME, TOTAL_WEIGHT_KG, LOCATION_ID FROM FEEDS ORDER BY FEED_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Batch Feed Distribution | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;700&display=swap" rel="stylesheet">
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
            --border-active:  rgba(245,158,11,0.5);
            
            --amber:          #f59e0b;
            --amber-dim:      rgba(245,158,11,0.12);
            --amber-glow:     rgba(245,158,11,0.25);
            --orange:         #f97316;
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --blue:           #3b82f6;
            --blue-dim:       rgba(59,130,246,0.12);
            --purple:         #a855f7;
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
            
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
            font-family: var(--font); background: var(--bg-base); color: var(--text-primary);
            min-height: 100vh; padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(245,158,11,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

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
            font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--amber); background: var(--amber-dim); border: 1px solid rgba(245,158,11,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem; gap: 1.5rem; flex-wrap: wrap; }
        .header-info h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; letter-spacing: -0.02em;}
        .header-info h1 span { background: linear-gradient(135deg, var(--amber), #b45309); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }
        
        .header-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

        /* ─── BUTTONS (page level) ─── */
        .btn-base {
            display: inline-flex; align-items: center; gap: 8px; border: none; padding: 12px 24px;
            border-radius: var(--radius-md); font-weight: 700; font-size: 0.95rem; font-family: var(--font);
            cursor: pointer; transition: all var(--transition); white-space: nowrap; justify-content: center;
        }
        .add-btn { background: var(--amber); color: #000; box-shadow: 0 4px 15px var(--amber-glow);}
        .add-btn:hover { background: #fbbf24; transform: translateY(-2px); }
        .global-undo-btn { background: var(--bg-elevated); color: var(--text-secondary); border: 1px solid var(--border); }
        .global-undo-btn:hover { background: var(--red-dim); color: var(--red); border-color: var(--red); transform: translateY(-2px); }

        /* ─── SEARCH & TABLE ─── */
        .search-container { position: relative; margin-bottom: 1.5rem; max-width: 600px;}
        .search-input {
            width: 100%; padding: 14px 16px 14px 45px; background: var(--bg-surface);
            border: 1px solid var(--border); border-radius: var(--radius-md); color: var(--text-primary);
            font-size: 0.95rem; font-family: var(--font); outline: none; transition: var(--transition);
        }
        .search-input:focus { border-color: var(--amber); box-shadow: 0 0 0 3px var(--amber-glow); }
        .search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;}
        
        .table-container {
            background: var(--bg-surface); border-radius: var(--radius-xl); border: 1px solid var(--border);
            overflow: hidden; box-shadow: var(--shadow-md); margin-bottom: 3rem;
        }
        .table-scroll-wrapper { width: 100%; overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .table th {
            text-align: left; padding: 16px; background: var(--bg-elevated);
            color: var(--text-muted); font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.05em; border-bottom: 1px solid var(--border); white-space: nowrap;
        }
        .table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-primary); vertical-align: middle; white-space: nowrap;}
        .table tr:hover { background: rgba(255,255,255,0.02); }

        .tag-badge { background: var(--blue-dim); color: var(--blue); padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; font-family: var(--font-mono); border: 1px solid rgba(59,130,246,0.3);}
        .pen-badge { background: var(--emerald-dim); color: var(--emerald); padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; border: 1px solid rgba(16,185,129,0.3); }
        .amount { color: var(--amber); font-weight: 700; font-family: var(--font-mono); font-size: 1.05rem; }
        .date-val { font-family: var(--font-mono); color: var(--text-secondary); font-size: 0.9rem;}
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); font-style: italic; }

        /* ─── TOAST ─── */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out; display: flex; align-items: center; gap: 8px;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* ═══════════════════════════════════════════
           ─── MODAL ───
        ═══════════════════════════════════════════ */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .modal.show { display: flex; }

        .modal-content {
            background: var(--bg-surface);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            width: 100%;
            max-width: 680px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 32px 64px -12px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.04);
            animation: modalZoom 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
        }
        @keyframes modalZoom {
            from { transform: scale(0.92) translateY(12px); opacity: 0; }
            to   { transform: scale(1) translateY(0); opacity: 1; }
        }

        /* Header */
        .modal-header {
            padding: 1.4rem 1.75rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: linear-gradient(135deg, rgba(245,158,11,0.06) 0%, transparent 60%), var(--bg-elevated);
        }
        .modal-header h2 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.01em;
        }
        .modal-header h2 i { color: var(--amber); font-size: 1rem; }
        .modal-header-meta {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 400;
            margin-left: 2px;
        }
        .btn-close {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            width: 32px; height: 32px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            transition: all var(--transition);
            flex-shrink: 0;
        }
        .btn-close:hover {
            background: rgba(248,113,113,0.15);
            border-color: var(--red);
            color: var(--red);
        }

        /* Body */
        .modal-body {
            padding: 1.75rem;
            overflow-y: auto;
            flex: 1;
        }
        .modal-body::-webkit-scrollbar { width: 5px; }
        .modal-body::-webkit-scrollbar-track { background: transparent; }
        .modal-body::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }

        /* Footer */
        .modal-footer {
            padding: 1.1rem 1.75rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            background: var(--bg-elevated);
            flex-shrink: 0;
        }

        /* Step Labels */
        .step-label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1.1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        .step-number {
            width: 22px; height: 22px;
            background: var(--amber);
            color: #000;
            font-size: 0.68rem;
            font-weight: 800;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .step-title {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-secondary);
        }

        /* Form Elements */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1rem; }
        .form-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.06em;
        }
        .form-control, .form-select {
            width: 100%;
            padding: 11px 14px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-family: var(--font);
            outline: none;
            transition: all var(--transition);
            box-sizing: border-box;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--amber);
            box-shadow: 0 0 0 3px var(--amber-glow);
            background: var(--bg-hover);
        }
        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            cursor: pointer;
        }
        .form-select:disabled, .form-control:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            background: rgba(255,255,255,0.02);
        }

        /* Pen / Animal List */
        .pens-list-container {
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 0.75rem;
            max-height: 240px;
            overflow-y: auto;
        }
        .pens-list-container::-webkit-scrollbar { width: 5px; }
        .pens-list-container::-webkit-scrollbar-track { background: transparent; }
        .pens-list-container::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }

        .pen-group {
            margin-bottom: 0.65rem;
            background: var(--bg-surface);
            padding: 0.875rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        .pen-group:last-child { margin-bottom: 0; }
        .pen-label {
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 0.95rem;
        }
        .pen-label input[type="checkbox"] { accent-color: var(--amber); width: 16px; height: 16px; cursor: pointer; margin: 0; }

        .animal-list { margin-top: 10px; margin-left: 24px; display: flex; flex-wrap: wrap; gap: 6px; }
        .animal-label {
            font-size: 0.8rem;
            font-family: var(--font-mono);
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            background: rgba(255,255,255,0.04);
            padding: 5px 10px;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.06);
            transition: var(--transition);
        }
        .animal-label:hover { background: rgba(245,158,11,0.1); border-color: rgba(245,158,11,0.3); }
        .animal-label input[type="checkbox"] { accent-color: var(--amber); width: 14px; height: 14px; cursor: pointer; margin: 0; }

        /* Method Toggle */
        .method-toggle {
            display: flex;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            overflow: hidden;
            padding: 3px;
            gap: 3px;
            margin-bottom: 1rem;
        }
        .method-btn {
            flex: 1;
            padding: 9px;
            text-align: center;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-secondary);
            cursor: pointer;
            background: transparent;
            border: none;
            transition: var(--transition);
            border-radius: 6px;
            font-family: var(--font);
        }
        .method-btn.active { background: var(--amber); color: #000; }

        /* Summary Box */
        .summary-box {
            background: linear-gradient(135deg, rgba(245,158,11,0.08), rgba(245,158,11,0.03));
            border: 1px solid rgba(245,158,11,0.22);
            border-radius: var(--radius-md);
            padding: 1.25rem 1.5rem;
            display: none;
            margin-top: 1.25rem;
        }
        .summary-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .summary-main .summary-title {
            color: var(--text-muted);
            font-size: 0.68rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.07em;
            margin-bottom: 4px;
        }
        .summary-value {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--amber);
            font-family: var(--font-mono);
            line-height: 1;
        }
        .summary-value-unit {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-left: 4px;
        }
        .summary-stats {
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-align: right;
        }
        .summary-stat {
            font-size: 0.82rem;
            color: var(--text-secondary);
        }
        .summary-stat strong {
            color: var(--emerald);
            font-family: var(--font-mono);
            font-weight: 700;
        }
        .stock-warning {
            margin-top: 0.875rem;
            color: #fca5a5;
            font-size: 0.85rem;
            font-weight: 700;
            background: rgba(248,113,113,0.1);
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid rgba(248,113,113,0.2);
            display: none;
        }

        /* Modal Buttons */
        .btn-cancel {
            padding: 10px 20px;
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-secondary);
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
            font-family: var(--font);
        }
        .btn-cancel:hover { background: var(--bg-hover); color: #fff; border-color: rgba(255,255,255,0.15); }

        .btn-save {
            padding: 10px 22px;
            background: var(--amber);
            border: none;
            color: #000;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 700;
            font-size: 0.9rem;
            font-family: var(--font);
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 10px rgba(245,158,11,0.25);
        }
        .btn-save:hover:not(:disabled) { background: #fbbf24; transform: translateY(-1px); box-shadow: 0 6px 18px rgba(245,158,11,0.35); }
        .btn-save:disabled { opacity: 0.4; cursor: not-allowed; background: var(--bg-elevated); color: var(--text-muted); border: 1px solid var(--border); box-shadow: none; }

        /* Misc modal helpers */
        .resource-link {
            display: inline-flex; align-items: center; gap: 5px; font-size: 0.8rem;
            color: var(--blue); text-decoration: none; transition: color 0.2s; font-weight: 600;
        }
        .resource-link:hover { color: #93c5fd; }

        .btn-mini {
            background: var(--bg-elevated); border: 1px solid var(--border); color: var(--text-secondary);
            border-radius: 6px; padding: 5px 10px; cursor: pointer; font-size: 0.75rem; font-weight: 600;
            transition: var(--transition); display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-mini:hover:not(:disabled) { background: var(--bg-hover); border-color: var(--text-muted); color: var(--text-primary); }
        .btn-mini:disabled { opacity: 0.5; cursor: not-allowed; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .header-actions { width: 100%; display: grid; grid-template-columns: 1fr; }
            .btn-base { justify-content: center; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .modal-footer { flex-direction: column; }
            .modal-footer button { width: 100%; }
            .modal-body { padding: 1.25rem; }
            .modal-header { padding: 1.1rem 1.25rem; }
            .summary-stats { text-align: left; }

            /* Table → Cards */
            .table-container { border: none; background: transparent; overflow: visible; box-shadow: none; }
            .table thead { display: none; }
            .table tbody, .table tr, .table td { display: block; width: 100%; box-sizing: border-box; }
            .table tr {
                background: var(--bg-surface); border: 1px solid var(--border);
                border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem;
                box-shadow: var(--shadow-md);
            }
            .table td {
                display: flex; justify-content: space-between; align-items: center; text-align: right;
                padding: 0.6rem 0; border-bottom: 1px dashed rgba(255,255,255,0.05); white-space: normal;
            }
            .table td:last-child { border-bottom: none; padding-top: 1rem; }
            .table td::before {
                content: attr(data-label); font-weight: 700; color: var(--text-muted);
                font-size: 0.75rem; text-transform: uppercase; margin-right: 1rem; text-align: left; flex-shrink: 0;
            }
        }
    </style>
</head>
<body>

    <div id="toastContainer"></div>

    <div class="container">
        
        <div class="top-bar">
            <a href="transactions.php" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Transactions 
            </a>
            <span class="page-badge"><i class="fa-solid fa-wheat-awn"></i> Batch Nutrition</span>
        </div>

        <header class="page-header">
            <div class="header-info">
                <h1>Batch Feed <span>Distribution</span></h1>
                <p>Record and track bulk animal feeding transactions across multiple pens.</p>
            </div>
            <div class="header-actions">
                <button class="btn-base global-undo-btn" onclick="undoLastFeed()">
                    <i class="fa-solid fa-rotate-left"></i> Undo Last Entry
                </button>
                <button class="btn-base add-btn" onclick="openAddModal()">
                    <i class="fa-solid fa-pen-to-square"></i> Bulk Feed Selection
                </button>
            </div>
        </header>

        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" class="search-input" id="searchInput" placeholder="Quick search logs by tag, pen, or feed type..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <div class="table-scroll-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Target Pen</th>
                            <th>Animal Tag</th>
                            <th>Feed Commodity</th>
                            <th>Qty (KG)</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="transaction-table">
                        <?php if(empty($transactions_data)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i class="fa-solid fa-ghost" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    No feeding transactions recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($transactions_data as $row): ?>
                            <tr>
                                <td data-label="Date & Time" class="date-val"><?php echo $row['FORMATTED_DATE']; ?></td>
                                <td data-label="Target Pen"><span class="pen-badge"><i class="fa-solid fa-border-all"></i> <?php echo htmlspecialchars($row['PEN_NAME']); ?></span></td>
                                <td data-label="Animal Tag">
                                    <?php if($row['TAG_NO']): ?>
                                        <span class="tag-badge"><i class="fa-solid fa-tag"></i> <?php echo htmlspecialchars($row['TAG_NO']); ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:0.85rem; font-style:italic;">Bulk Pen</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Feed Commodity" style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($row['FEED_NAME']); ?></td>
                                <td data-label="Qty (KG)" class="amount"><?php echo number_format($row['QUANTITY_KG'], 2); ?></td>
                                <td data-label="Remarks" style="font-size:0.9rem; color:var(--text-secondary);"><?php echo htmlspecialchars($row['REMARKS'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div id="empty-state" class="empty-state" style="display:none;">
                    <i class="fa-solid fa-magnifying-glass" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>
                    No records found matching your search.
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════
         MODAL
    ═══════════════════════════════════════════ -->
    <div id="modal" class="modal">
        <div class="modal-content">

            <div class="modal-header">
                <h2>
                    <i class="fa-solid fa-list-check"></i>
                    Bulk Feed Selection
                    <span class="modal-header-meta">— batch distribution</span>
                </h2>
                <button class="btn-close" onclick="closeModal()" title="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="modal-body">
                <form id="bulk-feed-form">

                    <!-- STEP 1 -->
                    <div class="step-label">
                        <span class="step-number">1</span>
                        <span class="step-title">Target Area &amp; Animals</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Location</label>
                            <select id="location_id" class="form-select" onchange="handleLocationChange()" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                                <?php if($USER_LOCATION_ == 1000): ?>
                                    <option value="">— Choose Location —</option>
                                <?php endif; ?>
                                <?php foreach($locations as $loc): ?>
                                    <option value="<?php echo $loc['LOCATION_ID']; ?>" <?php echo ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Building</label>
                            <select id="building_id" class="form-select" onchange="loadPensAndAnimals()" disabled>
                                <option value="">Select location first</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="animal-selection-group" style="display:none; margin-bottom:0;">
                        <label class="form-label">
                            Pens &amp; Animals
                            <i id="pen-loading" class="fa-solid fa-spinner fa-spin" style="display:none; color:var(--amber); margin-left:6px;"></i>
                        </label>
                        <div id="pens-container" class="pens-list-container"></div>
                    </div>

                    <!-- STEP 2 -->
                    <div id="feed-section" style="opacity:0.3; pointer-events:none; transition:opacity 0.3s ease; margin-top:1.5rem;">
                        <div class="step-label">
                            <span class="step-number">2</span>
                            <span class="step-title">Feeding Details</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Feed Selection</label>
                            <select id="feed_id" class="form-select" onchange="calculateTotal()" disabled>
                                <option value="">Select location first</option>
                            </select>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                                <a href="purch_feeds_feeding.php" target="_blank" class="resource-link">
                                    Manage / Purchase Feeds
                                    <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.7rem;"></i>
                                </a>
                                <button type="button" id="refresh-feeds-btn" class="btn-mini" onclick="refreshFeedsList()">
                                    <i class="fa-solid fa-rotate-right"></i> Sync
                                </button>
                            </div>
                        </div>

                        <div class="form-row" style="margin:0;">
                            <div class="form-group" style="margin:0;">
                                <label class="form-label">Distribution Method</label>
                                <div class="method-toggle">
                                    <button type="button" class="method-btn active" id="method-per-head" onclick="setMethod('head')">Per Head</button>
                                    <button type="button" class="method-btn" id="method-total" onclick="setMethod('total')">Total Group</button>
                                </div>
                                <input type="hidden" id="calc_method" value="head">
                                <input type="number" id="input_qty" class="form-control" step="0.01" min="0.01" placeholder="e.g. 0.5 kg per animal" oninput="calculateTotal()">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label class="form-label">Date &amp; Time</label>
                                <input type="text" id="transaction_date" class="form-control date-picker" placeholder="Select date &amp; time" required>
                            </div>
                        </div>
                    </div>

                    <!-- SUMMARY -->
                    <div class="summary-box" id="summary-box">
                        <div class="summary-inner">
                            <div class="summary-main">
                                <div class="summary-title">Total Stock Deduction</div>
                                <div class="summary-value">
                                    <span id="total-deduction">0.00</span><span class="summary-value-unit">kg</span>
                                </div>
                            </div>
                            <div class="summary-stats">
                                <div class="summary-stat">
                                    Animals &nbsp;<strong id="animal-count-display">0</strong>
                                </div>
                                <div class="summary-stat">
                                    Per head &nbsp;<strong id="per-head-display">0.00</strong> kg
                                </div>
                            </div>
                        </div>
                        <div id="stock-warning" class="stock-warning">
                            <i class="fa-solid fa-triangle-exclamation"></i> Insufficient stock in inventory!
                        </div>
                    </div>

                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn-save" id="btn-save" onclick="saveBulkFeed()" disabled>
                    <i class="fa-solid fa-floppy-disk"></i> Confirm Feeding
                </button>
            </div>

        </div>
    </div>

    <script>
        let allFeeds = <?php echo json_encode($feeds); ?>;
        const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;
        let currentAnimalCount = 0;
        let fpTransactionDate;

        document.addEventListener('DOMContentLoaded', () => {
            fpTransactionDate = flatpickr("#transaction_date", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                altInput: true,
                altFormat: "M j, Y h:i K",
                allowInput: true
            });
            filterTable();
        });

        function showToast(msg, type = 'success') {
            const t = document.createElement('div');
            t.className = 'toast';
            t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--emerald)'}`;
            t.innerHTML = `${type === 'error' ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-check"></i>'} ${msg}`;
            document.getElementById('toastContainer').appendChild(t);
            setTimeout(() => t.remove(), 3500);
        }

        /* ── FEEDING METHOD TOGGLE ── */
        function setMethod(method) {
            const btnHead  = document.getElementById('method-per-head');
            const btnTotal = document.getElementById('method-total');
            const inputField = document.getElementById('input_qty');

            document.getElementById('calc_method').value = method;

            if (method === 'head') {
                btnHead.classList.add('active');
                btnTotal.classList.remove('active');
                inputField.placeholder = "e.g. 0.5 kg per animal";
            } else {
                btnTotal.classList.add('active');
                btnHead.classList.remove('active');
                inputField.placeholder = "e.g. 25 kg total to split";
            }
            calculateTotal();
        }

        async function refreshFeedsList() {
            const btn = document.getElementById('refresh-feeds-btn');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Syncing';
            btn.disabled = true;

            try {
                const res  = await fetch('group_feed_transaction.php?action=get_feeds');
                const data = await res.json();
                allFeeds = data;
                filterFeedsByLocation();
                showToast("Feed inventory synced.", "success");
            } catch (e) {
                showToast("Failed to sync inventory.", "error");
            } finally {
                btn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Sync';
                btn.disabled = false;
            }
        }

        function handleLocationChange() {
            loadBuildings();
            filterFeedsByLocation();
            document.getElementById('animal-selection-group').style.display = 'none';
            updateSelection();
        }

        function filterFeedsByLocation() {
            const locId = document.getElementById('location_id').value;
            const feedSelect = document.getElementById('feed_id');
            feedSelect.innerHTML = '<option value="">Select Feed</option>';

            if (!locId) {
                feedSelect.disabled = true;
                feedSelect.innerHTML = '<option value="">Select location first</option>';
                return;
            }

            const filteredFeeds = allFeeds.filter(feed => feed.LOCATION_ID == locId);

            if (filteredFeeds.length > 0) {
                feedSelect.disabled = false;
                filteredFeeds.forEach(feed => {
                    const opt = document.createElement('option');
                    opt.value = feed.FEED_ID;
                    opt.textContent = `${feed.FEED_NAME}  (Stock: ${feed.TOTAL_WEIGHT_KG} kg)`;
                    opt.dataset.stock = feed.TOTAL_WEIGHT_KG;
                    feedSelect.appendChild(opt);
                });
            } else {
                feedSelect.disabled = true;
                feedSelect.innerHTML = '<option value="">No feeds available at this location</option>';
            }
            calculateTotal();
        }

        async function loadBuildings() {
            const locId = document.getElementById('location_id').value;
            const bldg  = document.getElementById('building_id');

            bldg.innerHTML = '<option>Loading...</option>';
            bldg.disabled  = true;
            if (!locId) return;

            try {
                const res  = await fetch(`../process/getHierarchyPlaceData.php?action=get_buildings&location_id=${locId}`);
                const data = await res.json();
                bldg.innerHTML = '<option value="">Select Building</option>';
                data.forEach(b => bldg.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`);
                bldg.disabled = false;
            } catch (err) {
                bldg.innerHTML = '<option value="">Error loading buildings</option>';
            }
        }

        async function loadPensAndAnimals() {
            const bldgId       = document.getElementById('building_id').value;
            const container    = document.getElementById('pens-container');
            const groupWrapper = document.getElementById('animal-selection-group');
            const loader       = document.getElementById('pen-loading');

            container.innerHTML = '';
            updateSelection();

            if (!bldgId) { groupWrapper.style.display = 'none'; return; }

            groupWrapper.style.display  = 'block';
            loader.style.display = 'inline-block';

            try {
                const res  = await fetch(`group_feed_transaction.php?action=get_pens_animals&bldg_id=${bldgId}`);
                const pens = await res.json();

                if (pens.length === 0) {
                    container.innerHTML = '<div style="color:var(--text-muted); padding:10px; font-style:italic;">No pens or active animals found in this building.</div>';
                    return;
                }

                let html = '';
                pens.forEach(p => {
                    const isEmpty = p.animals.length === 0;
                    html += `
                        <div class="pen-group">
                            <label class="pen-label">
                                <input type="checkbox" class="pen-cb" value="${p.pen_id}" onchange="togglePen(this)" ${isEmpty ? 'disabled' : ''}>
                                <i class="fa-solid fa-border-all" style="color:var(--text-muted); font-size:0.85rem;"></i>
                                ${p.pen_name}
                                ${isEmpty
                                    ? '<span style="color:var(--red); font-size:0.7rem; font-weight:700; text-transform:uppercase; margin-left:4px;">(Empty)</span>'
                                    : `<span style="color:var(--emerald); font-size:0.7rem; font-weight:700; text-transform:uppercase; margin-left:4px;">(${p.animals.length} animals)</span>`
                                }
                            </label>
                            <div class="animal-list">
                    `;
                    p.animals.forEach(a => {
                        html += `
                            <label class="animal-label">
                                <input type="checkbox" class="animal-cb" value="${a.animal_id}" onchange="toggleAnimal(this)">
                                ${a.tag_no}
                            </label>
                        `;
                    });
                    html += `</div></div>`;
                });
                container.innerHTML = html;

            } catch (err) {
                container.innerHTML = '<div style="color:var(--red); padding:10px;">Error loading data.</div>';
            } finally {
                loader.style.display = 'none';
            }
        }

        function togglePen(penCb) {
            const container = penCb.closest('.pen-group');
            container.querySelectorAll('.animal-cb').forEach(cb => cb.checked = penCb.checked);
            updateSelection();
        }

        function toggleAnimal(animalCb) {
            const container = animalCb.closest('.pen-group');
            const penCb  = container.querySelector('.pen-cb');
            const total   = container.querySelectorAll('.animal-cb').length;
            const checked = container.querySelectorAll('.animal-cb:checked').length;

            penCb.checked       = (total > 0 && total === checked);
            penCb.indeterminate = (checked > 0 && checked < total);
            updateSelection();
        }

        function updateSelection() {
            currentAnimalCount = document.querySelectorAll('.animal-cb:checked').length;
            const sec = document.getElementById('feed-section');
            const sum = document.getElementById('summary-box');

            if (currentAnimalCount > 0) {
                sec.style.opacity = "1"; sec.style.pointerEvents = "auto"; sum.style.display = "block";
            } else {
                sec.style.opacity = "0.3"; sec.style.pointerEvents = "none"; sum.style.display = "none";
            }
            calculateTotal();
        }

        /* ── CALCULATOR ── */
        function calculateTotal() {
            const method   = document.getElementById('calc_method').value;
            const rawInput = parseFloat(document.getElementById('input_qty').value) || 0;

            let totalToDeduct = 0;
            let qtyPerHead    = 0;

            if (currentAnimalCount > 0) {
                if (method === 'head') {
                    qtyPerHead    = rawInput;
                    totalToDeduct = currentAnimalCount * rawInput;
                } else {
                    totalToDeduct = rawInput;
                    qtyPerHead    = rawInput / currentAnimalCount;
                }
            }

            document.getElementById('animal-count-display').textContent = currentAnimalCount;
            document.getElementById('per-head-display').textContent     = qtyPerHead.toFixed(2);
            document.getElementById('total-deduction').textContent      = totalToDeduct.toFixed(2);

            const feed = document.getElementById('feed_id');
            const opt  = feed.options[feed.selectedIndex];
            const warn = document.getElementById('stock-warning');
            const btn  = document.getElementById('btn-save');

            if (opt && opt.dataset.stock) {
                const stock = parseFloat(opt.dataset.stock);
                if (totalToDeduct > stock) {
                    warn.style.display = 'flex';
                    warn.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i>&nbsp; Insufficient stock — only ${stock.toFixed(2)} kg available.`;
                    btn.disabled = true;
                } else if (totalToDeduct <= 0) {
                    warn.style.display = 'none';
                    btn.disabled = true;
                } else {
                    warn.style.display = 'none';
                    btn.disabled = false;
                }
            } else {
                btn.disabled = true;
            }
        }

        /* ── UNDO ── */
        function undoLastFeed() {
            if (!confirm("Are you sure you want to UNDO the very last feeding transaction?\n\nThis will remove the records and restore the stock.")) return;

            const btn      = document.querySelector('.global-undo-btn');
            const origHTML = btn.innerHTML;
            btn.disabled   = true;
            btn.innerHTML  = '<i class="fa-solid fa-spinner fa-spin"></i> Restoring...';

            fetch('../process/undoFeedTransaction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=undo_last'
            })
            .then(r => r.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    btn.disabled  = false;
                    btn.innerHTML = origHTML;
                }
            })
            .catch(() => {
                showToast("System connection error.", "error");
                btn.disabled  = false;
                btn.innerHTML = origHTML;
            });
        }

        /* ── SUBMIT ── */
        function saveBulkFeed() {
            const animalCbs = document.querySelectorAll('.animal-cb:checked');
            const feedId    = document.getElementById('feed_id').value;
            const date      = document.getElementById('transaction_date').value;
            const qtyPerHead = parseFloat(document.getElementById('per-head-display').textContent);

            if (animalCbs.length === 0)        { showToast("Please select at least one animal.", "error"); return; }
            if (!feedId || qtyPerHead <= 0 || !date) { showToast("Please fill in all feeding details.", "error"); return; }

            const btn = document.getElementById('btn-save');
            btn.disabled  = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            const fd = new FormData();
            animalCbs.forEach(cb => fd.append('animal_ids[]', cb.value));
            fd.append('feed_id', feedId);
            fd.append('qty_per_head', qtyPerHead);
            fd.append('transaction_date', date);

            fetch('../process/addFeedTransaction.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    showToast(d.message, "success");
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(d.message, "error");
                    btn.disabled  = false;
                    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Confirm Feeding';
                }
            })
            .catch(() => {
                showToast("An unexpected error occurred.", "error");
                btn.disabled  = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Confirm Feeding';
            });
        }

        /* ── OPEN / CLOSE ── */
        function openAddModal() {
            document.getElementById('modal').classList.add('show');
            document.getElementById('bulk-feed-form').reset();
            fpTransactionDate.clear();
            setMethod('head');

            const locSelect = document.getElementById('location_id');

            if (USER_LOCATION != 1000) {
                locSelect.value = USER_LOCATION;
                handleLocationChange();
            } else {
                locSelect.value = "";
                const bldg = document.getElementById('building_id');
                bldg.innerHTML = '<option value="">Select location first</option>';
                bldg.disabled  = true;
                document.getElementById('animal-selection-group').style.display = 'none';
                document.getElementById('feed_id').innerHTML = '<option value="">Select location first</option>';
                document.getElementById('feed_id').disabled  = true;
                updateSelection();
            }
        }

        function closeModal() {
            document.getElementById('modal').classList.remove('show');
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) closeModal();
        };

        /* ── SEARCH ── */
        function filterTable() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#transaction-table tr');
            let visible = 0;

            if (rows.length === 1 && rows[0].querySelector('.empty-state')) {
                document.getElementById('empty-state').style.display = 'none';
                return;
            }

            rows.forEach(r => {
                if (r.textContent.toLowerCase().includes(term)) { r.style.display = ''; visible++; }
                else { r.style.display = 'none'; }
            });

            document.getElementById('empty-state').style.display = (visible === 0) ? 'block' : 'none';
        }
    </script>
</body>
</html>