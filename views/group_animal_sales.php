<?php
// views/group_animal_sales.php
ob_start(); 
error_reporting(E_ALL);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('group_sell_animals');
$page = "transactions";
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php'; // ADDED LOCATION FUNCTION

// 1. AJAX HANDLER
if (isset($_GET['action'])) {
    ob_end_clean(); 
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        if ($action === 'get_buildings' && isset($_GET['location_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['location_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        
        // Fetches ALL Pens and ALL Animals inside a specific building
        if ($action === 'get_bldg_animals_for_sale' && isset($_GET['building_id'])) {
            $bldg_id = $_GET['building_id'];
            
            $sql = "SELECT p.PEN_ID, p.PEN_NAME, 
                           a.ANIMAL_ID, a.TAG_NO, a.CURRENT_ACTUAL_WEIGHT, a.ACQUISITION_COST,
                           COALESCE((SELECT SUM(TRANSACTION_COST) FROM feed_transactions WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_feed,
                           COALESCE((SELECT SUM(TOTAL_COST) FROM treatment_transactions WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_med,
                           COALESCE((SELECT SUM(VACCINATION_COST + VACCINE_COST) FROM vaccination_records WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_vac,
                           COALESCE((SELECT SUM(TOTAL_COST) FROM vitamins_supplements_transactions WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_vit,
                           COALESCE((SELECT SUM(COST) FROM check_ups WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_chk
                    FROM PENS p 
                    LEFT JOIN animal_records a ON p.PEN_ID = a.PEN_ID AND a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold'
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
                    $data[$pid]['animals'][] = $r; 
                }
            }
            echo json_encode(['success' => true, 'pens' => array_values($data)]);
            exit;
        }

        // Global Tag Search Override
        if ($action === 'search_animal_for_batch' && isset($_GET['tag'])) {
            $tag = trim($_GET['tag']);
            $sql = "SELECT a.ANIMAL_ID, a.TAG_NO, a.CURRENT_ACTUAL_WEIGHT, a.ACQUISITION_COST,
                    COALESCE((SELECT SUM(TRANSACTION_COST) FROM feed_transactions WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_feed,
                    COALESCE((SELECT SUM(TOTAL_COST) FROM treatment_transactions WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_med,
                    COALESCE((SELECT SUM(VACCINATION_COST + VACCINE_COST) FROM vaccination_records WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_vac,
                    COALESCE((SELECT SUM(TOTAL_COST) FROM vitamins_supplements_transactions WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_vit,
                    COALESCE((SELECT SUM(COST) FROM check_ups WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_chk
                FROM animal_records a
                WHERE a.TAG_NO = ? AND a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold'";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$tag]);
            $animal = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($animal) {
                echo json_encode(['success' => true, 'animal' => $animal]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Tag not found or animal already sold.']);
            }
            exit;
        }
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); exit; }
}

// 2. PAGE INIT & LOCATION FILTERING
if ($USER_LOCATION_ != 1000) {
    $stmt = $conn->prepare("SELECT * FROM locations WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME");
    $stmt->execute([$USER_LOCATION_]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
}

$buyers = $conn->query("SELECT FULL_NAME FROM buyers WHERE IS_ACTIVE = 1 ORDER BY FULL_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Bulk Sales Terminal | FarmPro</title>

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
            
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --emerald-glow:   rgba(16,185,129,0.25);
            --amber:          #f59e0b;
            --amber-dim:      rgba(245,158,11,0.12);
            --red:            #ef4444;
            --red-dim:        rgba(239,68,68,0.12);
            --blue:           #3b82f6;
            
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(16,185,129,0.06) 0%, transparent 60%);
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
        .back-link:hover { color: var(--text-primary); border-color: var(--emerald); background: var(--bg-hover); }

        .page-badge {
            display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem;
            font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--emerald); background: var(--emerald-dim); border: 1px solid rgba(16,185,129,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── LAYOUT GRID ─── */
        .main-grid { display: grid; grid-template-columns: 420px 1fr; gap: 1.5rem; align-items: start; }

        /* ─── CONTROL PANEL (LEFT) ─── */
        .control-panel {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 2rem; position: sticky; top: 1.5rem;
            box-shadow: var(--shadow-md); z-index: 10; display: flex; flex-direction: column;
        }
        .panel-title { font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 10px;}
        .panel-title i { color: var(--emerald); }
        .panel-subtitle { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 2rem; }

        .step-label { color: var(--emerald); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: block;}
        
        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 6px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;}
        
        .form-control, .form-select, .form-textarea {
            width: 100%; padding: 12px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; transition: var(--transition); outline: none; box-sizing: border-box; font-family: var(--font);
        }
        .form-select {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-control:focus, .form-select:focus, .form-textarea:focus { border-color: var(--emerald); box-shadow: 0 0 0 3px var(--emerald-glow); background: var(--bg-hover); }
        .form-control:disabled, .form-select:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.02); border-color: transparent;}
        .form-textarea { resize: vertical; min-height: 80px; }

        .input-with-btn { display: flex; gap: 8px; }
        .input-with-btn .form-control { flex: 1; }
        .btn-mini {
            background: var(--bg-elevated); border: 1px solid var(--border); color: var(--text-primary);
            border-radius: var(--radius-md); padding: 0 16px; cursor: pointer; font-size: 0.85rem; font-weight: 700;
            white-space: nowrap; flex-shrink: 0; transition: var(--transition); font-family: var(--font); display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .btn-mini:hover { background: var(--emerald); color: #000; border-color: var(--emerald); box-shadow: 0 4px 12px var(--emerald-glow); }

        /* CHECKBOX LIST STYLING */
        .pens-list-container {
            background: var(--bg-base); border: 1px solid var(--border); border-radius: var(--radius-md);
            padding: 1rem; max-height: 250px; overflow-y: auto; margin-top: 5px; box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);
        }
        .pens-list-container::-webkit-scrollbar { width: 6px; }
        .pens-list-container::-webkit-scrollbar-track { background: transparent; }
        .pens-list-container::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }

        .pen-group { margin-bottom: 1rem; background: var(--bg-elevated); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border); }
        .pen-group:last-child { margin-bottom: 0; }
        
        .pen-label { font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 1rem; }
        .pen-label input[type="checkbox"], .animal-label input[type="checkbox"] {
            appearance: none; width: 18px; height: 18px; border: 2px solid var(--text-muted);
            border-radius: 4px; margin: 0; position: relative; cursor: pointer; transition: var(--transition); background: var(--bg-base); flex-shrink: 0;
        }
        .pen-label input[type="checkbox"]:checked, .animal-label input[type="checkbox"]:checked { background: var(--emerald); border-color: var(--emerald); }
        .pen-label input[type="checkbox"]:checked::after, .animal-label input[type="checkbox"]:checked::after {
            content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            color: #000; font-size: 11px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
        }
        .pen-label input[type="checkbox"]:disabled, .animal-label input[type="checkbox"]:disabled { opacity: 0.3; cursor: not-allowed; }

        .animal-list { margin-top: 12px; margin-left: 28px; display: flex; flex-wrap: wrap; gap: 8px; }
        .animal-label {
            font-size: 0.85rem; font-family: var(--font-mono); font-weight: 600; color: var(--text-primary);
            display: flex; align-items: center; gap: 8px; cursor: pointer; background: var(--bg-base);
            padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border); transition: var(--transition); user-select: none;
        }
        .animal-label:hover:not(.disabled) { border-color: rgba(255,255,255,0.2); }
        .animal-label:has(input:checked) { background: var(--emerald-dim); border-color: var(--emerald); color: var(--emerald); }
        .animal-label.disabled { border-color: var(--red-dim); color: var(--red); background: rgba(239,68,68,0.05); cursor: not-allowed;}

        /* Divider */
        .divider-text { text-align: center; color: var(--text-muted); font-size: 0.75rem; font-weight: 700; margin: 1.5rem 0; position: relative; text-transform: uppercase; letter-spacing: 0.1em;}
        .divider-text::before, .divider-text::after { content: ""; position: absolute; top: 50%; width: 35%; height: 1px; background: var(--border); }
        .divider-text::before { left: 0; } .divider-text::after { right: 0; }

        /* Pricing Strategy Toggles */
        .pricing-toggles { display: flex; flex-direction: column; gap: 8px; background: var(--bg-elevated); padding: 12px; border-radius: var(--radius-md); border: 1px solid var(--border);}
        .price-radio { display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem; color: var(--text-primary); font-weight: 500;}
        .price-radio input[type="radio"] { appearance: none; width: 18px; height: 18px; border: 2px solid var(--text-muted); border-radius: 50%; outline: none; transition: var(--transition); cursor: pointer; margin: 0; position: relative;}
        .price-radio input[type="radio"]:checked { border-color: var(--amber); }
        .price-radio input[type="radio"]:checked::after { content: ''; position: absolute; top: 4px; left: 4px; width: 6px; height: 6px; background: var(--amber); border-radius: 50%; }

        /* Financial Summary Box */
        .summary-box { margin-top: 1.5rem; background: var(--bg-base); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border);}
        .summary-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; color: var(--text-secondary); font-weight: 600; margin-bottom: 6px;}
        .summary-row .val { color: #fff; font-family: var(--font-mono); font-weight: 700; }
        
        .overhead-input { width: 100px; padding: 6px 8px; background: var(--bg-surface); border: 1px solid var(--border); border-radius: 6px; color: var(--red); text-align: right; font-family: var(--font-mono); font-weight: 700; outline: none; transition: var(--transition);}
        .overhead-input:focus { border-color: var(--red); box-shadow: 0 0 0 3px var(--red-glow); }

        .summary-total { margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--border); font-weight: 700; color: var(--text-primary); display: flex; justify-content: space-between; align-items: center; font-size: 1.05rem;}
        .summary-total .val { color: var(--emerald); font-size: 1.25rem; font-weight: 800; font-family: var(--font-mono);}
        .summary-total.revenue .val { color: var(--amber); font-size: 1.5rem;}

        /* Profit Box */
        .profit-box { padding: 1.5rem; border-radius: var(--radius-md); text-align: center; margin-top: 1.5rem; border: 1px solid var(--border); transition: var(--transition); background: var(--bg-elevated);}
        .profit-box .lbl { font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; color: var(--text-secondary); margin-bottom: 8px;}
        .profit-box .val { font-size: 2.5rem; font-weight: 800; font-family: var(--font-mono); line-height: 1;}
        
        .profit-pos { border-color: rgba(16,185,129,0.5); background: var(--emerald-dim); box-shadow: inset 0 0 20px rgba(16,185,129,0.1);}
        .profit-pos .val { color: var(--emerald); text-shadow: 0 2px 10px rgba(16,185,129,0.3);}
        
        .profit-neg { border-color: rgba(239,68,68,0.5); background: var(--red-dim); box-shadow: inset 0 0 20px rgba(239,68,68,0.1); animation: pulseError 2s infinite;}
        .profit-neg .val { color: var(--red); text-shadow: 0 2px 10px rgba(239,68,68,0.3);}
        @keyframes pulseError { 0%, 100% { box-shadow: inset 0 0 20px rgba(239,68,68,0.1); } 50% { box-shadow: inset 0 0 30px rgba(239,68,68,0.3); } }

        .btn-submit {
            width: 100%; margin-top: 1.5rem; padding: 16px; background: var(--emerald); border: none;
            border-radius: var(--radius-md); color: #000; font-weight: 800; font-size: 1.05rem; font-family: var(--font);
            cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 10px; text-transform: uppercase; letter-spacing: 0.05em;
        }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; background: var(--bg-elevated); color: var(--text-muted); border: 1px solid var(--border);}
        .btn-submit:hover:not(:disabled) { background: #34d399; box-shadow: 0 8px 25px var(--emerald-glow); transform: translateY(-2px); }

        /* ─── WORKSPACE (RIGHT) ─── */
        .workspace-panel { display: flex; flex-direction: column; gap: 1.5rem; }
        
        .table-section { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-md);}
        .section-header { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; border-bottom: 1px solid var(--border); background: var(--bg-elevated);}
        .section-title { font-size: 1.15rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px;}
        .section-title i { color: var(--emerald); }
        .section-desc { font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px; font-weight: normal;}

        .batch-table-container { max-height: calc(100vh - 250px); overflow-y: auto; }
        .batch-table-container::-webkit-scrollbar { width: 8px; }
        .batch-table-container::-webkit-scrollbar-track { background: var(--bg-surface); }
        .batch-table-container::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }

        .batch-table { width: 100%; border-collapse: collapse; min-width: 600px;}
        .batch-table th {
            text-align: left; padding: 16px; background: var(--bg-base);
            color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 10;
        }
        .batch-table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-primary); vertical-align: middle;}
        .batch-table tr:hover { background: rgba(255,255,255,0.02); }

        .price-input {
            width: 140px; padding: 10px 12px; background: var(--bg-base); border: 1px solid var(--border);
            border-radius: 8px; color: var(--amber); font-weight: 700; text-align: right; font-family: var(--font-mono); font-size: 1rem; outline: none; transition: var(--transition);
        }
        .price-input:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.02); border-color: transparent; color: var(--text-muted);}
        .price-input:focus { border-color: var(--amber); box-shadow: 0 0 0 3px var(--amber-glow); }

        .td-mono { font-family: var(--font-mono); font-weight: 600; }
        .td-tag { font-family: var(--font-mono); font-weight: 700; color: #fff; font-size: 1.05rem;}
        .td-cost { color: var(--pink); }
        .td-weight { color: var(--text-secondary); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); font-style: italic; }

        /* Toast Notifications */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out; display: flex; align-items: center; gap: 8px;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1024px) {
            .main-grid { grid-template-columns: 1fr; }
            .control-panel { position: relative; top: 0; }
        }
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .input-with-btn { flex-direction: column; }
            .input-with-btn .btn-mini { width: 100%; padding: 12px; justify-content: center;}
            
            .batch-table thead { display: none; }
            .batch-table, .batch-table tbody, .batch-table tr, .batch-table td { display: block; width: 100%; box-sizing: border-box; }
            .batch-table tr { background: rgba(30, 41, 59, 0.4); border: 1px solid var(--border); border-radius: var(--radius-lg); margin-bottom: 1rem; padding: 1rem; }
            .batch-table td { display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0; border-bottom: 1px dashed rgba(255,255,255,0.05); text-align: right; }
            .batch-table td:last-child { border-bottom: none; padding-top: 1rem; justify-content: flex-end;}
            .batch-table td::before { content: attr(data-label); font-weight: 700; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; margin-right: 1rem; text-align: left; flex-shrink: 0;}
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
        <span class="page-badge"><i class="fa-solid fa-money-bills"></i> Financial Terminal</span>
    </div>

    <div class="main-grid">
        
        <div class="control-panel">
            <div class="panel-title"><i class="fa-solid fa-cash-register"></i> Bulk Sales Terminal</div>
            <div class="panel-subtitle">Process multiple animal sales simultaneously.</div>

            <span class="step-label">1. Source Target Animals</span>
            
            <div style="background:var(--bg-elevated); padding:1rem; border-radius:var(--radius-md); border:1px solid var(--border); margin-bottom:1.5rem;">
                <div class="form-group">
                    <select id="location_id" class="form-select" onchange="loadBuildings()" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                        <?php if($USER_LOCATION_ == 1000): ?>
                            <option value="">-- Select Location --</option>
                        <?php endif; ?>
                        <?php foreach($locations as $l): ?>
                            <option value="<?php echo $l['LOCATION_ID']; ?>" <?php echo ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($l['LOCATION_NAME']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <select id="building_id" class="form-select" onchange="loadPensAndAnimals()" disabled>
                        <option>-- Select Building --</option>
                    </select>
                </div>
            </div>

            <div class="form-group" id="animal-selection-group" style="display:none;">
                <div class="form-label">
                    <span>Available Pens in Building</span>
                    <i id="pen-loading" class="fa-solid fa-spinner fa-spin" style="display:none; color:var(--emerald);"></i>
                </div>
                <div id="pens-container" class="pens-list-container"></div>
            </div>

            <div class="divider-text">OR ADD BY TAG EXPLICITLY</div>
            
            <div class="form-group">
                <div class="input-with-btn">
                    <input type="text" id="search_tag_input" class="form-control" placeholder="e.g., A001">
                    <button type="button" class="btn-mini" onclick="searchAndAddTag()"><i class="fa-solid fa-magnifying-glass"></i> Find &amp; Add</button>
                </div>
                <div id="search_error" style="color: var(--red); font-size: 0.8rem; margin-top: 6px; display: none;"></div>
            </div>

            <span class="step-label" style="margin-top: 1rem;">2. Pricing Strategy</span>
            
            <div class="pricing-toggles">
                <label class="price-radio">
                    <input type="radio" name="price_mode" value="individual" checked onchange="togglePriceMode()"> Individual Price Input
                </label>
                <label class="price-radio">
                    <input type="radio" name="price_mode" value="per_head" onchange="togglePriceMode()"> Uniform Price per Head
                </label>
                <label class="price-radio">
                    <input type="radio" name="price_mode" value="per_kg" onchange="togglePriceMode()"> Calculate via Price per KG
                </label>
                <label class="price-radio">
                    <input type="radio" name="price_mode" value="lump_sum" onchange="togglePriceMode()"> Fixed Batch Price (Lump Sum)
                </label>
            </div>

            <div class="form-group" id="global_input_div" style="display:none; margin-top: 1rem;">
                <label class="form-label" id="global_price_label" style="color:var(--amber);">Input Amount (₱)</label>
                <input type="number" step="0.01" id="global_price_input" class="form-control" placeholder="0.00" oninput="applyPricing()" style="border-color: var(--amber); font-family: var(--font-mono); font-weight:700; color:var(--amber);">
            </div>

            <span class="step-label" style="margin-top: 2rem;">3. Finalize &amp; Submit</span>
            
            <div class="form-group">
                <select id="buyer_name" class="form-select" required>
                    <option value="">-- Select Registered Buyer --</option>
                    <?php foreach($buyers as $b): ?><option value="<?= htmlspecialchars($b['FULL_NAME']) ?>"><?= htmlspecialchars($b['FULL_NAME']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <input type="text" id="sale_date" class="form-control date-picker" placeholder="Date of Sale" required>
            </div>
            <div class="form-group">
                <textarea id="notes" class="form-textarea" placeholder="Batch Details / Optional Remarks" rows="2"></textarea>
            </div>

            <div class="summary-box">
                <h4 style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; margin: 0 0 10px 0; border-bottom: 1px solid var(--border); padding-bottom: 8px; letter-spacing: 0.05em;">Batch Financial Summary</h4>
                
                <div class="summary-row"><span>Heads Selected:</span> <span id="summ_count" class="val" style="color:#fff;">0</span></div>
                <div class="summary-row"><span>Total Weight:</span> <span id="summ_total_weight" class="val" style="color:var(--text-primary);">0.00 kg</span></div>
                <div class="summary-row" style="margin-top: 8px;"><span>Base Operational Costs:</span> <span id="summ_base_cost" class="val" style="color:var(--text-secondary);">₱0.00</span></div>
                
                <div class="summary-row" style="align-items: center; margin-top: 8px;">
                    <span style="color:var(--red);">+ Overhead Cost Deduction:</span> 
                    <input type="number" id="overhead_cost" value="0.00" step="0.01" class="overhead-input" oninput="recalc()">
                </div>

                <div class="summary-total">
                    <span>Total Net Worth:</span> 
                    <span id="summ_net_worth" class="val" style="color:var(--pink);">₱0.00</span>
                </div>
                <div class="summary-total revenue">
                    <span style="color:var(--text-primary);">Total Sale Revenue:</span> 
                    <span id="total_batch_price_display" class="val">₱0.00</span>
                </div>
            </div>

            <div id="profitBox" class="profit-box">
                <div class="lbl">Estimated Gross Profit</div>
                <div class="val" id="profitDisplay">₱0.00</div>
            </div>

            <button type="button" id="btn_submit" class="btn-submit" onclick="submitBatchSale()" disabled>
                <i class="fa-solid fa-file-invoice-dollar"></i> Confirm Transaction
            </button>
        </div>

        <div class="workspace-panel">
            <div class="table-section">
                <div class="section-header">
                    <div>
                        <div class="section-title"><i class="fa-solid fa-table-list"></i> Selected Animals Pricing Table</div>
                        <div class="section-desc">Animals in <span style="color:var(--red); font-weight:700;">red</span> are missing a registered weight or acquisition cost and cannot be sold.</div>
                    </div>
                </div>
                
                <div class="batch-table-container">
                    <table class="batch-table">
                        <thead>
                            <tr>
                                <th style="padding-left:1.5rem;">Tag No</th>
                                <th>Recorded Weight</th>
                                <th>Cumulative Net Cost</th>
                                <th style="text-align:right; padding-right:1.5rem;">Sale Price (₱)</th>
                            </tr>
                        </thead>
                        <tbody id="animal_table_body">
                            <tr><td colspan="4" class="empty-state"><i class="fa-solid fa-arrow-left me-2" style="font-size: 1.5rem; display:block; margin-bottom:1rem; opacity:0.5;"></i> Select animals from the left panel to populate the pricing ledger.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    const CURRENT_PAGE = window.location.pathname.split("/").pop();
    const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;
    let currentBatchData = []; 
    let fpSaleDate;

    document.addEventListener('DOMContentLoaded', () => {
        // Initialize Flatpickr
        fpSaleDate = flatpickr("#sale_date", {
            dateFormat: "Y-m-d", // Value submitted to PHP
            altInput: true,      // Visual input
            altFormat: "M j, Y",  // mm/dd/yyyy format
            allowInput: true
        });
        
        fpSaleDate.clear(); // Leave the actual input blank by default

        // Auto-load buildings if user is restricted to a location
        if (USER_LOCATION != 1000) {
            document.getElementById('location_id').value = USER_LOCATION;
            loadBuildings();
        }
    });

    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : (type === 'warning' ? 'var(--amber)' : 'var(--emerald)')}`;
        
        let icon = '<i class="fa-solid fa-check"></i>';
        if(type === 'error') icon = '<i class="fa-solid fa-xmark"></i>';
        if(type === 'warning') icon = '<i class="fa-solid fa-triangle-exclamation"></i>';
        
        t.innerHTML = `${icon} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), type === 'error' ? 5000 : 3500);
    }

    function fmt(v) { return parseFloat(v).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}); }

    async function fetchData(urlParams) {
        try { const res = await fetch(`${CURRENT_PAGE}${urlParams}`); return await res.json(); } catch(e) { return []; }
    }

    // --- LOADER LOGIC ---
    async function loadBuildings() {
        const locId = document.getElementById('location_id').value;
        const bSelect = document.getElementById('building_id');
        bSelect.innerHTML = '<option value="">-- Select Building --</option>'; bSelect.disabled = true;
        document.getElementById('animal-selection-group').style.display = 'none';
        
        if(locId) {
            bSelect.innerHTML = '<option value="">Loading...</option>';
            const data = await fetchData(`?action=get_buildings&location_id=${locId}`);
            bSelect.innerHTML = '<option value="">-- Select Building --</option>';
            data.forEach(i => bSelect.innerHTML += `<option value="${i.BUILDING_ID}">${i.BUILDING_NAME}</option>`);
            bSelect.disabled = false;
        }
        renderTable();
    }

    async function loadPensAndAnimals() {
        const bldgId = document.getElementById('building_id').value;
        const container = document.getElementById('pens-container');
        const groupWrapper = document.getElementById('animal-selection-group');
        const loader = document.getElementById('pen-loading');
        
        container.innerHTML = '';
        currentBatchData = [];

        if(!bldgId) { groupWrapper.style.display = 'none'; renderTable(); return; }

        groupWrapper.style.display = 'block';
        loader.style.display = 'inline-block';

        const res = await fetchData(`?action=get_bldg_animals_for_sale&building_id=${bldgId}`);
        loader.style.display = 'none';

        if(res.success && res.pens.length > 0) {
            let html = '';
            res.pens.forEach(p => {
                const isPenEmpty = p.animals.length === 0;
                html += `
                    <div class="pen-group">
                        <label class="pen-label">
                            <input type="checkbox" class="pen-cb" onchange="togglePen(this)" ${isPenEmpty ? 'disabled' : ''}> 
                            <span style="flex-grow:1;"><i class="fa-solid fa-border-all" style="color:var(--text-muted); margin-right:6px;"></i> ${p.pen_name}</span> 
                            ${isPenEmpty ? '<span style="color:var(--text-muted); font-size:0.75rem; font-weight:normal; text-transform:uppercase;">Empty</span>' : `<span style="color:var(--emerald); font-size:0.75rem; background:var(--emerald-dim); padding: 2px 8px; border-radius:4px; font-weight:700;">${p.animals.length} animals</span>`}
                        </label>
                        <div class="animal-list">
                `;
                p.animals.forEach(a => {
                    currentBatchData.push(a); // Store globally
                    
                    const weight = parseFloat(a.CURRENT_ACTUAL_WEIGHT || 0);
                    const acqCost = parseFloat(a.ACQUISITION_COST || 0);
                    const isMissingData = (weight <= 0 || acqCost <= 0);
                    const disabledAttr = isMissingData ? 'disabled title="Missing Weight or Cost"' : '';
                    const classDisabled = isMissingData ? 'disabled' : '';
                    
                    html += `
                        <label class="animal-label ${classDisabled}">
                            <input type="checkbox" class="animal-cb" value="${a.ANIMAL_ID}" onchange="toggleAnimal(this)" ${disabledAttr}> 
                            ${a.TAG_NO} ${isMissingData ? '<i class="fa-solid fa-triangle-exclamation" style="color:var(--red); margin-left:4px;"></i>' : ''}
                        </label>
                    `;
                });
                html += `</div></div>`;
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div style="color:var(--text-muted); padding:10px; font-style:italic;">No available animals found in this building.</div>';
        }
        renderTable();
    }

    // --- SEARCH OVERRIDE ---
    async function searchAndAddTag() {
        const tag = document.getElementById('search_tag_input').value.trim();
        const err = document.getElementById('search_error');
        err.innerText = "";
        err.style.display = "none";

        if(!tag) return;
        
        // check if already loaded in the UI
        const existing = currentBatchData.find(a => a.TAG_NO.toLowerCase() === tag.toLowerCase());
        if (existing) {
            const cb = document.querySelector(`.animal-cb[value="${existing.ANIMAL_ID}"]`);
            if(cb && !cb.disabled) {
                cb.checked = true;
                toggleAnimal(cb);
                document.getElementById('search_tag_input').value = '';
            } else {
                err.innerText = "Animal has missing data and cannot be selected.";
                err.style.display = "block";
            }
            return;
        }

        const res = await fetchData(`?action=search_animal_for_batch&tag=${encodeURIComponent(tag)}`);
        if(res.success) {
            currentBatchData.push(res.animal);
            
            // Ensure Searched Pen exists
            let searchPen = document.getElementById('searched-pen-group');
            if(!searchPen) {
                const container = document.getElementById('pens-container');
                searchPen = document.createElement('div');
                searchPen.id = 'searched-pen-group';
                searchPen.className = 'pen-group';
                searchPen.innerHTML = `
                    <label class="pen-label" style="color:var(--sky);">
                        <input type="checkbox" class="pen-cb" onchange="togglePen(this)"> 
                        <i class="fa-solid fa-magnifying-glass"></i> Directly Searched Tags
                    </label>
                    <div class="animal-list" id="searched-animal-list"></div>
                `;
                container.appendChild(searchPen);
                document.getElementById('animal-selection-group').style.display = 'block';
            }

            const list = document.getElementById('searched-animal-list');
            const a = res.animal;
            const weight = parseFloat(a.CURRENT_ACTUAL_WEIGHT || 0);
            const acqCost = parseFloat(a.ACQUISITION_COST || 0);
            const isMissingData = (weight <= 0 || acqCost <= 0);
            const disabledAttr = isMissingData ? 'disabled' : 'checked';
            const classDisabled = isMissingData ? 'disabled' : '';

            list.insertAdjacentHTML('beforeend', `
                <label class="animal-label ${classDisabled}">
                    <input type="checkbox" class="animal-cb" value="${a.ANIMAL_ID}" onchange="toggleAnimal(this)" ${disabledAttr}> 
                    ${a.TAG_NO} ${isMissingData ? '<i class="fa-solid fa-triangle-exclamation" style="color:var(--red); margin-left:4px;"></i>' : ''}
                </label>
            `);

            const pcb = searchPen.querySelector('.pen-cb');
            const total = searchPen.querySelectorAll('.animal-cb:not(:disabled)').length;
            const checked = searchPen.querySelectorAll('.animal-cb:checked').length;
            pcb.checked = (total > 0 && total === checked);
            pcb.indeterminate = (checked > 0 && checked < total);

            document.getElementById('search_tag_input').value = '';
            renderTable();
        } else {
            err.innerText = res.message;
            err.style.display = "block";
        }
    }

    // --- CHECKBOX LOGIC ---
    function togglePen(penCb) {
        const container = penCb.closest('.pen-group');
        const animalCbs = container.querySelectorAll('.animal-cb:not(:disabled)');
        animalCbs.forEach(cb => cb.checked = penCb.checked);
        renderTable();
    }

    function toggleAnimal(animalCb) {
        const container = animalCb.closest('.pen-group');
        const penCb = container.querySelector('.pen-cb');
        const total = container.querySelectorAll('.animal-cb:not(:disabled)').length;
        const checked = container.querySelectorAll('.animal-cb:checked').length;
        penCb.checked = (total > 0 && total === checked);
        penCb.indeterminate = (checked > 0 && checked < total);
        renderTable();
    }

    // --- TABLE RENDERING ---
    function renderTable() {
        const tbody = document.getElementById('animal_table_body');
        const checkboxes = document.querySelectorAll('.animal-cb:checked');
        
        if (checkboxes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="empty-state"><i class="fa-solid fa-arrow-left" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i> Select animals from the left panel to populate the pricing ledger.</td></tr>';
            applyPricing(); 
            return;
        }

        // Backup existing inputs
        const existingInputs = {};
        document.querySelectorAll('.price-input').forEach(inp => {
            existingInputs[inp.dataset.id] = inp.value;
        });

        tbody.innerHTML = '';
        
        checkboxes.forEach(cb => {
            const id = cb.value;
            const a = currentBatchData.find(x => x.ANIMAL_ID == id);
            if(!a) return;

            const totalCost = parseFloat(a.ACQUISITION_COST || 0) + parseFloat(a.cost_feed) + parseFloat(a.cost_med) + parseFloat(a.cost_vac) + parseFloat(a.cost_vit) + parseFloat(a.cost_chk);
            const weight = parseFloat(a.CURRENT_ACTUAL_WEIGHT || 0);
            
            const savedValue = existingInputs[id] !== undefined ? existingInputs[id] : '';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td data-label="Tag No" class="td-tag" style="padding-left:1.5rem;">${a.TAG_NO}</td>
                <td data-label="Recorded Weight" class="td-weight td-mono">${weight.toFixed(2)} kg</td>
                <td data-label="Net Cost" data-cost="${totalCost}" class="td-cost td-mono">₱${fmt(totalCost)}</td>
                <td data-label="Sale Price" style="text-align:right; padding-right:1.5rem;">
                    <input type="number" step="0.01" min="0" class="price-input" 
                           id="price_${a.ANIMAL_ID}" data-id="${a.ANIMAL_ID}" value="${savedValue}" placeholder="0.00" 
                           oninput="recalc()">
                </td>
            `;
            tbody.appendChild(tr);
        });

        applyPricing();
    }

    // --- PRICING LOGIC ---
    function togglePriceMode() {
        const mode = document.querySelector('input[name="price_mode"]:checked').value;
        const globalInputDiv = document.getElementById('global_input_div');
        const globalInput = document.getElementById('global_price_input');
        const globalLabel = document.getElementById('global_price_label');

        if (mode === 'individual') {
            globalInputDiv.style.display = 'none';
            globalInput.value = '';
        } else if (mode === 'per_head') {
            globalInputDiv.style.display = 'block';
            globalLabel.innerText = "Uniform Price Per Head (₱)";
        } else if (mode === 'per_kg') {
            globalInputDiv.style.display = 'block';
            globalLabel.innerText = "Price Per KG (₱)";
        } else if (mode === 'lump_sum') {
            globalInputDiv.style.display = 'block';
            globalLabel.innerText = "Total Fixed Batch Price (₱)";
        }
        applyPricing();
    }

    function applyPricing() {
        const mode = document.querySelector('input[name="price_mode"]:checked').value;
        const globalVal = parseFloat(document.getElementById('global_price_input').value) || 0;
        const activeRows = document.querySelectorAll('#animal_table_body tr');
        
        // Note: rows don't exist if empty state is showing
        if(activeRows.length === 1 && activeRows[0].querySelector('.empty-state')) { recalc(); return; }

        const count = activeRows.length;

        activeRows.forEach(tr => {
            const input = tr.querySelector('.price-input');
            if(!input) return;
            
            const weight = parseFloat(tr.children[1].getAttribute('data-weight')) || 0;
            
            if (mode === 'individual') {
                input.disabled = false;
            } else {
                input.disabled = true; 
                if (mode === 'per_head') {
                    input.value = globalVal > 0 ? globalVal.toFixed(2) : '';
                } else if (mode === 'per_kg') {
                    input.value = globalVal > 0 ? (weight * globalVal).toFixed(2) : '';
                } else if (mode === 'lump_sum') {
                    input.value = (globalVal > 0 && count > 0) ? (globalVal / count).toFixed(2) : '';
                }
            }
        });
        recalc();
    }

    function recalc() {
        const activeRows = document.querySelectorAll('#animal_table_body tr');
        let totalBaseCost = 0, totalRevenue = 0, totalWeight = 0;
        let count = 0;

        if(!(activeRows.length === 1 && activeRows[0].querySelector('.empty-state'))) {
            count = activeRows.length;
            activeRows.forEach(tr => {
                const cost = parseFloat(tr.children[2].getAttribute('data-cost')) || 0;
                const weight = parseFloat(tr.children[1].getAttribute('data-weight')) || 0;
                const price = parseFloat(tr.querySelector('.price-input').value) || 0;
                
                totalBaseCost += cost;
                totalWeight += weight;
                totalRevenue += price;
            });
        }

        const overhead = parseFloat(document.getElementById('overhead_cost').value) || 0;
        const totalCost = totalBaseCost + overhead;
        
        // Mode specific adjustment to avoid exact division decimals in Lump Sum
        const mode = document.querySelector('input[name="price_mode"]:checked').value;
        const globalVal = parseFloat(document.getElementById('global_price_input').value) || 0;
        if(mode === 'lump_sum' && globalVal > 0 && count > 0) {
            totalRevenue = globalVal; 
        }

        document.getElementById('summ_count').innerText = count;
        document.getElementById('summ_total_weight').innerText = totalWeight.toFixed(2) + " kg";
        document.getElementById('summ_base_cost').innerText = "₱" + fmt(totalBaseCost);
        document.getElementById('summ_net_worth').innerText = "₱" + fmt(totalCost);
        document.getElementById('total_batch_price_display').innerText = "₱" + fmt(totalRevenue);

        const profit = totalRevenue - totalCost;
        document.getElementById('profitDisplay').innerText = "₱" + fmt(profit);
        
        const pBox = document.getElementById('profitBox');
        if (profit >= 0 && totalRevenue > 0) {
            pBox.className = "profit-box profit-pos";
        } else if (profit < 0 && totalRevenue > 0) {
            pBox.className = "profit-box profit-neg";
        } else {
            pBox.className = "profit-box";
        }

        document.getElementById('btn_submit').disabled = (count === 0 || totalRevenue <= 0);
    }

    // --- FORM SUBMISSION ---
    function submitBatchSale() {
        const buyer = document.getElementById('buyer_name').value;
        const saleDate = document.getElementById('sale_date').value;

        if(!buyer) { showToast("Please select a buyer.", "error"); return; }
        if(!saleDate) { showToast("Please select a Date of Sale.", "error"); return; }

        const activeRows = document.querySelectorAll('#animal_table_body tr');
        if(activeRows.length === 0 || (activeRows.length===1 && activeRows[0].querySelector('.empty-state'))) return;

        const payload = new URLSearchParams();
        payload.append('customer_name', buyer);
        payload.append('sale_date', saleDate);
        payload.append('notes', document.getElementById('notes').value);
        
        const count = activeRows.length;
        const overheadTotal = parseFloat(document.getElementById('overhead_cost').value) || 0;
        const overheadPerHead = overheadTotal / count;
        let allPricesValid = true;

        activeRows.forEach(tr => {
            const input = tr.querySelector('.price-input');
            const id = input.getAttribute('data-id');
            const price = parseFloat(input.value) || 0;
            if(price <= 0) allPricesValid = false;
            
            const a = currentBatchData.find(x => x.ANIMAL_ID == id);
            
            payload.append('animal_ids[]', id);
            payload.append(`costs[${id}][sale_price]`, price);
            payload.append(`costs[${id}][overhead]`, overheadPerHead);
            payload.append(`costs[${id}][weight]`, a.CURRENT_ACTUAL_WEIGHT);
            payload.append(`costs[${id}][acq]`, a.ACQUISITION_COST);
            payload.append(`costs[${id}][feed]`, a.cost_feed);
            payload.append(`costs[${id}][med]`, a.cost_med);
            payload.append(`costs[${id}][vac]`, a.cost_vac);
            payload.append(`costs[${id}][vit]`, a.cost_vit);
            payload.append(`costs[${id}][chk]`, a.cost_chk);
        });

        // Force exact lump sum if applicable
        const mode = document.querySelector('input[name="price_mode"]:checked').value;
        const globalInput = parseFloat(document.getElementById('global_price_input').value) || 0;
        if(mode === 'lump_sum' && globalInput > 0) {
            payload.append('exact_lump_sum_total', globalInput); 
        }

        if(!allPricesValid) { showToast("Please ensure all selected animals have a valid sale price greater than 0.", "error"); return; }
        if(!confirm(`Confirm Bulk Sale to ${buyer}? This action is irreversible.`)) return;

        const btn = document.getElementById('btn_submit');
        const ogText = btn.innerHTML;
        btn.disabled = true; 
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing Ledger...';

        fetch('../process/addGroupAnimalSell.php', {
            method: 'POST',
            body: payload
        })
        .then(r => r.text())
        .then(text => {
            try {
                const res = JSON.parse(text);
                if(res.success) {
                    if(res.batch_id) window.open('print_batch_sales_receipt.php?batch_id='+res.batch_id, '_blank');
                    showToast(res.message, "success");
                    setTimeout(() => window.location.reload(), 1500);
                } else { 
                    showToast(res.message, "error"); 
                    btn.disabled=false; btn.innerHTML = ogText; 
                }
            } catch(e) {
                showToast("System Error. Server returned non-JSON data.", "error"); 
                console.error(text);
                btn.disabled=false; btn.innerHTML = ogText;
            }
        }).catch(err => {
            showToast("System connection error.", "error");
            btn.disabled=false; btn.innerHTML = ogText;
        });
    }
</script>
</body>
</html>