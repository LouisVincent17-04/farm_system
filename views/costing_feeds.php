<?php
// views/costing_feeds.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering immediately
ob_start();

$page = "costing";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('feed_consumption');
include '../common/navbar.php';
include '../common/chat_support.php';

// --- AJAX HANDLER ---
if (isset($_GET['action'])) {
    // Discard any output from included files
    ob_end_clean();
    
    // Start fresh buffer for JSON only
    ob_start();
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    $response = [];
    
    try {

    if ($action === 'get_buildings' && isset($_GET['location_id'])) {
        $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ?");
        $stmt->execute([$_GET['location_id']]);
        $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($response);
        exit;
    }

    if ($action === 'get_pens' && isset($_GET['building_id'])) {
        $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM pens WHERE BUILDING_ID = ?");
        $stmt->execute([$_GET['building_id']]);
        $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($response);
        exit;
    }

    if ($action === 'get_animals' && isset($_GET['pen_id'])) {
        $stmt = $conn->prepare("SELECT ANIMAL_ID, TAG_NO FROM animal_records WHERE PEN_ID = ? AND CURRENT_STATUS = 'Active'");
        $stmt->execute([$_GET['pen_id']]);
        $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($response);
        exit;
    }

    // NEW: Get feeding history for entire PEN
    if ($action === 'get_pen_history' && isset($_GET['pen_id'])) {
        $query = "SELECT 
                    ar.TAG_NO,
                    ft.TRANSACTION_DATE, 
                    f.FEED_NAME, 
                    ft.QUANTITY_KG, 
                    ft.TRANSACTION_COST, 
                    ft.REMARKS 
                  FROM feed_transactions ft
                  JOIN feeds f ON ft.FEED_ID = f.FEED_ID
                  JOIN animal_records ar ON ft.ANIMAL_ID = ar.ANIMAL_ID
                  WHERE ar.PEN_ID = ?
                  ORDER BY ft.TRANSACTION_DATE DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$_GET['pen_id']]);
        
        $html = "";
        $total_cost = 0;
        
        if ($stmt->rowCount() > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $date = date("M d, Y h:i A", strtotime($row['TRANSACTION_DATE']));
                $cost = number_format($row['TRANSACTION_COST'], 2);
                $total_cost += $row['TRANSACTION_COST'];
                
                $html .= "<tr>
                            <td data-label=\"Animal Tag\"><span class=\"tag-badge\"><i class=\"fa-solid fa-tag\"></i> {$row['TAG_NO']}</span></td>
                            <td data-label=\"Date & Time\" class=\"td-date\">{$date}</td>
                            <td data-label=\"Feed Name\" style=\"font-weight:700; color:#fff;\">{$row['FEED_NAME']}</td>
                            <td data-label=\"Qty Consumed\" class=\"td-mono\">{$row['QUANTITY_KG']} <span style=\"color:var(--text-muted);\">kg</span></td>
                            <td data-label=\"Cost\" class=\"td-cost\">₱ {$cost}</td>
                            <td data-label=\"Remarks\" style=\"color:var(--text-secondary); font-size:0.85rem;\">" . htmlspecialchars($row['REMARKS'] ?? '-') . "</td>
                          </tr>";
            }
        } else {
            $html = "<tr><td colspan='6' class='empty-state'><i class='fa-solid fa-ghost' style='font-size:2rem; display:block; margin-bottom:1rem; opacity:0.5;'></i> No feeding history found for this pen.</td></tr>";
        }

        echo json_encode(['html' => $html, 'total' => number_format($total_cost, 2)]);
        exit;
    }

    // Get feeding history for single ANIMAL
    if ($action === 'get_history' && isset($_GET['animal_id'])) {
        $query = "SELECT 
                    ft.TRANSACTION_DATE, 
                    f.FEED_NAME, 
                    ft.QUANTITY_KG, 
                    ft.TRANSACTION_COST, 
                    ft.REMARKS 
                  FROM feed_transactions ft
                  JOIN feeds f ON ft.FEED_ID = f.FEED_ID
                  WHERE ft.ANIMAL_ID = ?
                  ORDER BY ft.TRANSACTION_DATE DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$_GET['animal_id']]);
        
        $html = "";
        $total_cost = 0;
        
        if ($stmt->rowCount() > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $date = date("M d, Y h:i A", strtotime($row['TRANSACTION_DATE']));
                $cost = number_format($row['TRANSACTION_COST'], 2);
                $total_cost += $row['TRANSACTION_COST'];
                
                $html .= "<tr>
                            <td data-label=\"Date & Time\" class=\"td-date\">{$date}</td>
                            <td data-label=\"Feed Name\" style=\"font-weight:700; color:#fff;\">{$row['FEED_NAME']}</td>
                            <td data-label=\"Qty Consumed\" class=\"td-mono\">{$row['QUANTITY_KG']} <span style=\"color:var(--text-muted);\">kg</span></td>
                            <td data-label=\"Cost\" class=\"td-cost\">₱ {$cost}</td>
                            <td data-label=\"Remarks\" style=\"color:var(--text-secondary); font-size:0.85rem;\">" . htmlspecialchars($row['REMARKS'] ?? '-') . "</td>
                          </tr>";
            }
        } else {
            $html = "<tr><td colspan='5' class='empty-state'><i class='fa-solid fa-ghost' style='font-size:2rem; display:block; margin-bottom:1rem; opacity:0.5;'></i> No feeding history found for this animal.</td></tr>";
        }

        echo json_encode(['html' => $html, 'total' => number_format($total_cost, 2)]);
        exit;
    }
    
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Fetch Initial Locations
$locations = $conn->query("SELECT LOCATION_ID, LOCATION_NAME FROM locations");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Feeding Cost History | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

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
            --orange:         #f97316;
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --blue:           #3b82f6;
            --blue-dim:       rgba(59,130,246,0.12);
            --purple:         #a855f7;
            --red:            #f87171;
            
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

        /* ─── LAYOUT GRID ─── */
        .main-grid { display: grid; grid-template-columns: 360px 1fr; gap: 1.5rem; align-items: start; }

        /* ─── CONTROL PANEL (LEFT) ─── */
        .control-panel {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 2rem; position: sticky; top: 1.5rem;
            box-shadow: var(--shadow-md); z-index: 10; display: flex; flex-direction: column;
        }
        .panel-title { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 10px;}
        .panel-title i { color: var(--amber); }
        .panel-subtitle { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 2rem; }

        .form-group { margin-bottom: 1.25rem; display: flex; flex-direction: column; gap: 6px;}
        .form-label { color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: flex; justify-content: space-between; align-items: center; }
        
        .form-select {
            width: 100%; padding: 12px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; transition: var(--transition); outline: none; box-sizing: border-box; font-family: var(--font);
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-select:focus { border-color: var(--amber); box-shadow: 0 0 0 3px var(--amber-glow); background: var(--bg-hover); }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.02); border-color: transparent;}

        /* ─── WORKSPACE (RIGHT) ─── */
        .workspace-panel { display: flex; flex-direction: column; gap: 1.5rem; }
        
        .table-section { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-md); display: none; animation: fadeIn 0.4s ease;}
        .section-header { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; border-bottom: 1px solid var(--border); background: var(--bg-elevated); flex-wrap: wrap; gap: 1rem;}
        .section-title { font-size: 1.15rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px;}
        .section-title i { color: var(--amber); }
        
        .view-mode-badge {
            background: var(--blue-dim); color: var(--blue); padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; border: 1px solid rgba(59,130,246,0.3); white-space: nowrap; display: inline-flex; align-items: center; gap: 6px;
        }
        .view-mode-badge.single { background: var(--emerald-dim); color: var(--emerald); border-color: rgba(16,185,129,0.3);}

        .tag-display { color: #fff; font-weight: 700; font-family: var(--font-mono); font-size: 1.1rem;}

        .table-scroll-wrapper { max-height: calc(100vh - 350px); overflow-y: auto; }
        .table-scroll-wrapper::-webkit-scrollbar { width: 8px; }
        .table-scroll-wrapper::-webkit-scrollbar-track { background: var(--bg-surface); }
        .table-scroll-wrapper::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }

        .data-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .data-table th {
            background: var(--bg-base); color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase;
            letter-spacing: 0.05em; padding: 16px; text-align: left; font-weight: 700; border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 10;
        }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; color: var(--text-primary); white-space: nowrap;}
        .data-table tr:hover { background: rgba(255,255,255,0.01); }

        .tag-badge { background: var(--emerald-dim); color: var(--emerald); padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; font-family: var(--font-mono); border: 1px solid rgba(16,185,129,0.3); display: inline-flex; align-items: center; gap: 4px;}
        .td-date { font-size: 0.9rem; color: var(--text-secondary); font-family: var(--font-mono);}
        .td-mono { font-family: var(--font-mono); font-weight: 600;}
        .td-cost { color: var(--amber); font-weight: 700; font-family: var(--font-mono); font-size: 1.05rem;}
        
        .empty-state { text-align: center; padding: 4rem; color: var(--text-muted); font-style: italic; }

        .total-box {
            display: flex; justify-content: flex-end; align-items: center; padding: 1.5rem; gap: 1rem;
            background: var(--bg-elevated); border-top: 1px solid var(--border);
        }
        .total-box .lbl { color: var(--text-secondary); font-size: 0.85rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;}
        .total-box .val { color: var(--amber); font-size: 2rem; font-weight: 800; font-family: var(--font-mono); line-height: 1;}

        /* ─── MOBILE SWIPE ANIMATION OVERLAY ─── */
        .scroll-hint-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(5px);
            display: none; flex-direction: column; align-items: center; justify-content: center;
            z-index: 9999; color: #fff; transition: opacity 0.4s ease; pointer-events: none;
        }
        .scroll-hint-icon {
            font-size: 3rem; display: inline-block; animation: swipeHand 1.8s infinite ease-in-out;
            color: var(--amber); filter: drop-shadow(0 4px 15px rgba(245,158,11,0.5));
        }
        .scroll-hint-text {
            margin-top: 1.5rem; font-weight: 700; font-size: 1.1rem; letter-spacing: 0.05em; color: #fff;
            background: var(--bg-elevated); padding: 12px 24px; border-radius: 99px;
            border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 25px rgba(0,0,0,0.5); font-family: var(--font);
        }
        
        @keyframes swipeHand {
            0% { transform: translateX(40px) rotate(-15deg); opacity: 0; }
            20% { opacity: 1; }
            80% { opacity: 1; }
            100% { transform: translateX(-40px) rotate(-15deg); opacity: 0; }
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1024px) {
            .main-grid { grid-template-columns: 1fr; }
            .control-panel { position: relative; top: 0; }
        }
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .table-header-box { flex-direction: column; align-items: flex-start; }
            
            /* Table to Cards */
            .table-wrap { border: none; background: transparent; overflow: visible; box-shadow: none; }
            .data-table thead { display: none; }
            .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; box-sizing: border-box; }
            
            .data-table tr {
                background: var(--bg-surface); border: 1px solid var(--border);
                border-radius: var(--radius-lg); margin-bottom: 1rem; padding: 1.25rem;
                box-shadow: var(--shadow-md);
            }
            .data-table td {
                display: flex; justify-content: space-between; align-items: center; text-align: right;
                padding: 0.6rem 0; border-bottom: 1px dashed rgba(255,255,255,0.05); white-space: normal;
            }
            .data-table td:last-child { border-bottom: none; padding-top: 1rem;}
            
            .data-table td::before {
                content: attr(data-label); font-weight: 700; color: var(--text-muted);
                font-size: 0.75rem; text-transform: uppercase; margin-right: 1rem; text-align: left; flex-shrink: 0;
            }
        }
    </style>
</head>
<body>

<div class="scroll-hint-overlay" id="mobileScrollHint">
    <div class="scroll-hint-icon"><i class="fa-solid fa-hand-pointer"></i></div>
    <div class="scroll-hint-text">Swipe left on the table for more info</div>
</div>

<div class="container">
    
    <div class="top-bar">
        <a href="costing_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Costing Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-wheat-awn"></i> Feeding Analytics</span>
    </div>

    <header class="page-header">
        <div class="header-info">
            <h1>Feeding Cost <span>History</span></h1>
            <p>Analyze historical feed consumption and associated costs.</p>
        </div>
    </header>

    <div class="main-grid">
        
        <div class="control-panel">
            <div class="panel-title"><i class="fa-solid fa-filter"></i> Select Target</div>
            <div class="panel-subtitle">Select a pen to view all animals, or select a specific animal.</div>
            
            <div class="form-group">
                <label class="form-label">1. Select Location</label>
                <select id="selLocation" class="form-select" onchange="loadBuildings()">
                    <option value="">-- Choose Location --</option>
                    <?php while($row = $locations->fetch(PDO::FETCH_ASSOC)): ?>
                        <option value="<?= $row['LOCATION_ID'] ?>"><?= htmlspecialchars($row['LOCATION_NAME']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">2. Select Building</label>
                <select id="selBuilding" class="form-select" onchange="loadPens()" disabled>
                    <option value="">-- Select Location First --</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">3. Select Pen</label>
                <select id="selPen" class="form-select" onchange="loadPenHistory()" disabled>
                    <option value="">-- Select Building First --</option>
                </select>
            </div>

            <div style="border-top: 1px dashed var(--border); margin: 1.5rem 0;"></div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">4. Filter by Animal <span style="text-transform:none; font-weight:normal; opacity:0.7;">(Optional)</span></label>
                <select id="selAnimal" class="form-select" onchange="loadHistory()" disabled>
                    <option value="">-- All Animals in Pen --</option>
                </select>
            </div>
        </div>

        <div class="workspace-panel">
            <div id="resultsArea" class="table-section">
                
                <div class="section-header">
                    <div>
                        <div class="section-title"><i class="fa-solid fa-list-ul"></i> Feeding Transactions</div>
                        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-top: 8px;">
                            <span id="viewModeBadge" class="view-mode-badge"></span>
                            <div id="selectedTagDisplay" class="tag-display"></div>
                        </div>
                    </div>
                </div>
                
                <div class="table-scroll-wrapper">
                    <table class="data-table">
                        <thead id="tableHeader">
                            </thead>
                        <tbody id="historyTableBody">
                            </tbody>
                    </table>
                </div>

                <div class="total-box">
                    <span class="lbl">Total Feeding Cost</span>
                    <span class="val">₱ <span id="grandTotal">0.00</span></span>
                </div>

            </div>
            
            <div id="defaultEmptyState" class="empty-state" style="background: var(--bg-surface); border: 1px dashed var(--border); border-radius: var(--radius-xl);">
                <i class="fa-solid fa-arrow-left" style="font-size: 2.5rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
                <h3 style="margin:0 0 0.5rem 0; color: #fff;">Select a Target</h3>
                <p style="margin:0;">Use the filters on the left to load transaction history.</p>
            </div>
            
        </div>

    </div>
</div>

<script>
    // State flag to ensure the animation only plays once per page session
    let hasShownScrollHint = false;

    function triggerMobileScrollHint() {
        const scrollHint = document.getElementById('mobileScrollHint');
        const tableContainer = document.querySelector('.table-scroll-wrapper');
        
        // Trigger condition: <= 870px width and hasn't been shown yet
        if (window.innerWidth <= 870 && !hasShownScrollHint) {
            scrollHint.style.display = 'flex';
            hasShownScrollHint = true;

            const dismissHint = () => {
                scrollHint.style.opacity = '0';
                setTimeout(() => {
                    scrollHint.style.display = 'none';
                }, 400); // Wait for CSS transition
                
                // Cleanup listeners
                tableContainer.removeEventListener('scroll', dismissHint);
                window.removeEventListener('touchstart', dismissHint);
                window.removeEventListener('click', dismissHint);
            };

            // Auto dismiss after 3 seconds
            setTimeout(dismissHint, 3000);

            // Instant dismiss if user interacts
            tableContainer.addEventListener('scroll', dismissHint, { once: true });
            window.addEventListener('touchstart', dismissHint, { once: true });
            window.addEventListener('click', dismissHint, { once: true });
        }
    }

    async function fetchData(params) {
        try {
            const response = await fetch(`costing_feeds.php?${params}`);
            const text = await response.text();
            
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON received:', text);
                alert('Error: Server returned invalid response. Check console for details.');
                return [];
            }
        } catch (error) {
            console.error('Fetch error:', error);
            return [];
        }
    }

    async function loadBuildings() {
        const locationId = document.getElementById('selLocation').value;
        const buildSelect = document.getElementById('selBuilding');
        const penSelect = document.getElementById('selPen');
        const animalSelect = document.getElementById('selAnimal');
        const resultsArea = document.getElementById('resultsArea');
        const defaultState = document.getElementById('defaultEmptyState');

        buildSelect.innerHTML = '<option value="">Loading...</option>';
        penSelect.innerHTML = '<option value="">-- Select Building First --</option>';
        animalSelect.innerHTML = '<option value="">-- All Animals in Pen --</option>';
        buildSelect.disabled = true; penSelect.disabled = true; animalSelect.disabled = true;
        
        resultsArea.style.display = 'none';
        defaultState.style.display = 'block';

        if (locationId) {
            const data = await fetchData(`action=get_buildings&location_id=${locationId}`);
            
            buildSelect.innerHTML = '<option value="">-- Choose Building --</option>';
            data.forEach(item => {
                buildSelect.innerHTML += `<option value="${item.BUILDING_ID}">${item.BUILDING_NAME}</option>`;
            });
            buildSelect.disabled = false;
        } else {
            buildSelect.innerHTML = '<option value="">-- Select Location First --</option>';
        }
    }

    async function loadPens() {
        const buildId = document.getElementById('selBuilding').value;
        const penSelect = document.getElementById('selPen');
        const animalSelect = document.getElementById('selAnimal');
        const resultsArea = document.getElementById('resultsArea');
        const defaultState = document.getElementById('defaultEmptyState');

        penSelect.innerHTML = '<option value="">Loading...</option>';
        animalSelect.innerHTML = '<option value="">-- All Animals in Pen --</option>';
        penSelect.disabled = true; animalSelect.disabled = true;
        
        resultsArea.style.display = 'none';
        defaultState.style.display = 'block';

        if (buildId) {
            const data = await fetchData(`action=get_pens&building_id=${buildId}`);
            
            penSelect.innerHTML = '<option value="">-- Choose Pen --</option>';
            data.forEach(item => {
                penSelect.innerHTML += `<option value="${item.PEN_ID}">${item.PEN_NAME}</option>`;
            });
            penSelect.disabled = false;
        } else {
            penSelect.innerHTML = '<option value="">-- Select Building First --</option>';
        }
    }

    async function loadPenHistory() {
        const penId = document.getElementById('selPen').value;
        const penSelect = document.getElementById('selPen');
        const animalSelect = document.getElementById('selAnimal');
        const resultsArea = document.getElementById('resultsArea');
        const defaultState = document.getElementById('defaultEmptyState');
        
        const tableBody = document.getElementById('historyTableBody');
        const grandTotal = document.getElementById('grandTotal');
        const tagDisplay = document.getElementById('selectedTagDisplay');
        const viewModeBadge = document.getElementById('viewModeBadge');
        const tableHeader = document.getElementById('tableHeader');

        if (!penId) {
            resultsArea.style.display = 'none';
            defaultState.style.display = 'block';
            animalSelect.disabled = true;
            animalSelect.innerHTML = '<option value="">-- All Animals in Pen --</option>';
            return;
        }

        // Load animals for the dropdown
        animalSelect.innerHTML = '<option value="">Loading...</option>';
        animalSelect.disabled = true;
        
        const animals = await fetchData(`action=get_animals&pen_id=${penId}`);
        animalSelect.innerHTML = '<option value="">-- All Animals in Pen --</option>';
        animals.forEach(item => {
            animalSelect.innerHTML += `<option value="${item.ANIMAL_ID}">Tag: ${item.TAG_NO}</option>`;
        });
        animalSelect.disabled = false;

        // Show pen-level history (all animals)
        const penText = penSelect.options[penSelect.selectedIndex].text;
        tagDisplay.textContent = penText;
        viewModeBadge.className = 'view-mode-badge';
        viewModeBadge.innerHTML = '<i class="fa-solid fa-border-all"></i> Pen View (All Animals)';

        // Update table header to include Animal Tag column
        tableHeader.innerHTML = `
            <tr>
                <th style="padding-left:1.5rem;">Animal Tag</th>
                <th>Date &amp; Time</th>
                <th>Feed Name</th>
                <th>Qty Consumed</th>
                <th>Cost</th>
                <th>Remarks</th>
            </tr>
        `;

        const response = await fetchData(`action=get_pen_history&pen_id=${penId}`);
        
        tableBody.innerHTML = response.html;
        grandTotal.textContent = response.total;
        
        defaultState.style.display = 'none';
        resultsArea.style.display = 'block';

        // Trigger mobile swipe animation after table populates
        triggerMobileScrollHint();
    }

    async function loadHistory() {
        const animalId = document.getElementById('selAnimal').value;
        const animalSelect = document.getElementById('selAnimal');
        const resultsArea = document.getElementById('resultsArea');
        const defaultState = document.getElementById('defaultEmptyState');
        
        const tableBody = document.getElementById('historyTableBody');
        const grandTotal = document.getElementById('grandTotal');
        const tagDisplay = document.getElementById('selectedTagDisplay');
        const viewModeBadge = document.getElementById('viewModeBadge');
        const tableHeader = document.getElementById('tableHeader');

        if (!animalId) {
            // If "All Animals" is selected, reload pen history
            loadPenHistory();
            return;
        }

        // Single animal view
        const tagText = animalSelect.options[animalSelect.selectedIndex].text;
        tagDisplay.textContent = tagText;
        viewModeBadge.className = 'view-mode-badge single';
        viewModeBadge.innerHTML = '<i class="fa-solid fa-bullseye"></i> Single Animal View';

        // Update table header (no Animal Tag column needed)
        tableHeader.innerHTML = `
            <tr>
                <th style="padding-left:1.5rem;">Date &amp; Time</th>
                <th>Feed Name</th>
                <th>Qty Consumed</th>
                <th>Cost</th>
                <th>Remarks</th>
            </tr>
        `;

        const response = await fetchData(`action=get_history&animal_id=${animalId}`);
        
        tableBody.innerHTML = response.html;
        grandTotal.textContent = response.total;
        
        defaultState.style.display = 'none';
        resultsArea.style.display = 'block';

        // Trigger mobile swipe animation after table populates
        triggerMobileScrollHint();
    }
</script>

</body>
</html>