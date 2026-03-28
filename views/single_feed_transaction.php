<?php
// views/single_feed_transactions.php
error_reporting(0);
ini_set('display_errors', 0);
$page="transactions";

include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('feeding');
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php'; // Included for location restriction

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // 1. Transaction History (Updated DATE_FORMAT)
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
        LEFT JOIN FEEDS f ON ft.FEED_ID = f.FEED_ID
        ORDER BY ft.TRANSACTION_DATE DESC, ft.FT_ID DESC
        LIMIT 100
    ";
    $stmt = $conn->prepare($transactions_sql);
    $stmt->execute();
    $transactions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Locations (Filtered if user is restricted)
    if ($USER_LOCATION_ != 1000) {
        $stmt = $conn->prepare("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME ASC");
        $stmt->execute([$USER_LOCATION_]);
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $locations = $conn->query("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 3. Feeds
    $feeds = $conn->query("SELECT FEED_ID, FEED_NAME, TOTAL_WEIGHT_KG, LOCATION_ID FROM FEEDS ORDER BY FEED_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Feed Distribution Ledger | FarmPro</title>
    
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
            --border-active:  rgba(245,158,11,0.5); /* Amber Accent */
            
            --amber:          #f59e0b;
            --amber-dim:      rgba(245,158,11,0.12);
            --amber-glow:     rgba(245,158,11,0.25);
            --orange:         #f97316;
            --emerald:        #10b981;
            --blue:           #3b82f6;
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
        
        .header-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

        /* BUTTONS */
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
            color: var(--text-muted); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        .table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-primary); vertical-align: middle; white-space: nowrap;}
        .table tr:hover { background: rgba(255,255,255,0.02); }

        .tag-badge { background: var(--blue-dim); color: var(--blue); padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; font-family: var(--font-mono); border: 1px solid rgba(59,130,246,0.3);}
        .pen-badge { background: var(--emerald-dim); color: var(--emerald); padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; border: 1px solid rgba(16,185,129,0.3); }
        .amount { color: var(--amber); font-weight: 700; font-family: var(--font-mono); font-size: 1.05rem; }
        .date-val { font-family: var(--font-mono); color: var(--text-secondary); font-size: 0.9rem;}

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); font-style: italic; }

        /* ─── MODAL ─── */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; box-sizing: border-box; }
        .modal.show { display: flex; }
        
        .modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 600px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); display: flex; flex-direction: column;
            max-height: 90vh; animation: modalZoom 0.2s ease-out;
        }
        @keyframes modalZoom { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border); background: var(--bg-elevated); display: flex; justify-content: space-between; align-items: center;}
        .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px;}
        .modal-header h2 i { color: var(--amber); }
        .modal-body { padding: 2rem; overflow-y: auto; }
        .modal-footer { padding: 1.25rem 2rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); border-radius: 0 0 var(--radius-xl) var(--radius-xl);}

        /* Form Elements inside Modal */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.25rem;}
        
        .form-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        
        .form-control, .form-select {
            width: 100%; padding: 12px 14px; background: var(--bg-elevated);
            border: 1px solid var(--border); color: var(--text-primary);
            border-radius: var(--radius-md); font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition); box-sizing: border-box;
        }
        .form-control:focus, .form-select:focus { border-color: var(--amber); box-shadow: 0 0 0 3px var(--amber-glow); background: var(--bg-hover); }
        .form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; cursor: pointer; }
        .form-select:disabled, .form-control:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.02); }

        /* Radio Group */
        .radio-group { display: flex; gap: 1.5rem; margin-bottom: 1.5rem; background: var(--bg-elevated); padding: 12px 16px; border-radius: var(--radius-md); border: 1px solid var(--border); }
        .radio-label { display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-primary); font-weight: 600; font-size: 0.95rem;}
        .radio-label input[type="radio"] { appearance: none; width: 20px; height: 20px; border: 2px solid var(--text-muted); border-radius: 50%; outline: none; transition: var(--transition); cursor: pointer; position: relative; margin: 0;}
        .radio-label input[type="radio"]:checked { border-color: var(--amber); }
        .radio-label input[type="radio"]:checked::after { content: ''; position: absolute; top: 4px; left: 4px; width: 8px; height: 8px; background: var(--amber); border-radius: 50%; }

        .hidden { display: none !important; }

        /* Summary Box */
        .summary-box {
            background: var(--amber-dim); border: 1px solid rgba(245,158,11,0.3); border-radius: var(--radius-md);
            padding: 1.5rem; text-align: center; margin-top: 1.5rem; display: none;
        }
        .summary-title { color: var(--amber); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
        .summary-value { font-size: 2.5rem; font-weight: 800; color: #fff; margin: 0.5rem 0; font-family: var(--font-mono); line-height: 1;}
        .summary-meta { color: var(--text-secondary); font-size: 0.9rem; font-weight: 500;}
        .stock-warning { color: var(--red); font-size: 0.9rem; margin-top: 10px; display: none; font-weight: 700; background: var(--red-dim); padding: 6px; border-radius: 6px;}

        .btn-cancel { padding: 10px 20px; background: transparent; border: 1px solid var(--border); color: var(--text-secondary); border-radius: var(--radius-md); cursor: pointer; font-weight: 700; transition: var(--transition); font-family: var(--font);}
        .btn-cancel:hover { background: var(--bg-hover); color: #fff; }
        .btn-save { padding: 10px 20px; background: var(--amber); border: none; color: #000; border-radius: var(--radius-md); cursor: pointer; font-weight: 700; font-family: var(--font); transition: var(--transition); display: inline-flex; align-items: center; gap: 8px;}
        .btn-save:hover:not(:disabled) { background: #fbbf24; transform: translateY(-1px); box-shadow: 0 4px 12px var(--amber-glow);}
        .btn-save:disabled { opacity: 0.5; cursor: not-allowed; background: var(--bg-elevated); color: var(--text-muted); border: 1px solid var(--border);}

        /* Toast Notifications */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out; display: flex; align-items: center; gap: 8px;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .header-actions { width: 100%; display: grid; grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; gap: 0;}
            
            .table-wrap { border: none; background: transparent; overflow: visible; box-shadow: none; }
            .table thead { display: none; }
            .table tbody, .table tr, .table td { display: block; width: 100%; box-sizing: border-box; }
            
            .table tr {
                background: var(--bg-surface); border: 1px solid var(--border);
                border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem;
                box-shadow: var(--shadow-md);
            }
            .table td {
                display: flex; justify-content: space-between; align-items: center; text-align: right;
                padding: 0.6rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); white-space: normal;
            }
            .table td:last-child { border-bottom: none; }
            
            .table td::before {
                content: attr(data-label); font-weight: 700; color: var(--text-muted);
                font-size: 0.75rem; text-transform: uppercase; margin-right: 1rem; text-align: left;
            }
        }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">
    
    <div class="top-bar">
        <a href="single_feed_management.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Feed Management
        </a>
        <span class="page-badge"><i class="fa-solid fa-bowl-food"></i> Nutrition Ledger</span>
    </div>

    <header class="page-header">
        <div class="header-info">
            <h1>Feed Distribution <span>Ledger</span></h1>
            <p>Record and track targeted individual or bulk pen feeding transactions.</p>
        </div>
        
        <div class="header-actions">
            <button class="btn-base global-undo-btn" onclick="undoLastFeed()">
                <i class="fa-solid fa-rotate-left"></i> Undo Last Entry
            </button>
            <button class="btn-base add-btn" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Record Feeding
            </button>
        </div>
    </header>

    <div class="search-container">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Quick search by tag, pen, or feed type..." onkeyup="filterTable()">
    </div>

    <div class="table-container">
        <div class="table-scroll-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Pen Target</th>
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
                            <td data-label="Pen Target"><span class="pen-badge"><i class="fa-solid fa-border-all"></i> <?php echo htmlspecialchars($row['PEN_NAME']); ?></span></td>
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

<div id="modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fa-solid fa-pen-to-square"></i> Record Feeding</h2>
        </div>
        <div class="modal-body">
            <form id="bulk-feed-form">
                
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="feed_mode" value="bulk" checked onchange="toggleMode()">
                        Bulk by Pen (All Animals)
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="feed_mode" value="individual" onchange="toggleMode()">
                        Target Individual
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label" style="color:var(--amber);">1. Select Target</label>
                    <div class="form-row">
                        <div class="form-group" style="margin:0;">
                            <label>Location</label>
                            <select id="location_id" class="form-select" onchange="handleLocationChange()" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                                <?php if ($USER_LOCATION_ == 1000): ?>
                                    <option value="">-- Choose Location --</option>
                                <?php endif; ?>
                                <?php foreach($locations as $loc): ?>
                                    <option value="<?= $loc['LOCATION_ID'] ?>" <?php echo ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Building</label>
                            <select id="building_id" class="form-select" onchange="loadPens()" disabled><option value="">Select Location First</option></select>
                        </div>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Pen Name <i id="pen-loading" class="fa-solid fa-spinner fa-spin" style="display:none; color:var(--amber);"></i></label>
                        <select id="pen_id" class="form-select" onchange="handlePenChange()" disabled><option value="">Select Building First</option></select>
                    </div>
                    
                    <div class="form-group hidden" id="animal-wrapper" style="margin-top:1.25rem; margin-bottom:0;">
                        <label>Select Animal <i id="animal-loading" class="fa-solid fa-spinner fa-spin" style="display:none; color:var(--amber);"></i></label>
                        <select id="animal_id" class="form-select" onchange="handleAnimalChange()" disabled>
                            <option value="">Select Pen First</option>
                        </select>
                    </div>
                </div>

                <div id="feed-section" style="opacity: 0.3; pointer-events: none; transition: opacity 0.3s ease;">
                    <label class="form-label" style="color:var(--amber); margin-top:20px; display:block; margin-bottom:12px;">2. Feeding Details</label>
                    <div class="form-group">
                        <label>Feed Selection</label>
                        <select id="feed_id" class="form-select" onchange="calculateTotal()" disabled>
                            <option value="">Select Location First</option>
                        </select>
                    </div>
                    <div class="form-row" style="margin:0;">
                        <div class="form-group" style="margin:0;"> 
                            <label>Feed per Animal (kg)</label>
                            <input type="number" id="qty_per_head" class="form-control" step="0.01" min="0.01" placeholder="e.g. 0.5" oninput="calculateTotal()">
                        </div>
                        <div class="form-group" style="margin:0;"> 
                            <label>Date &amp; Time</label>
                            <input type="text" id="transaction_date" class="form-control date-picker" placeholder="Select Date & Time" required>
                        </div>
                    </div>
                </div>

                <div class="summary-box" id="summary-box">
                    <div class="summary-title">Total to Deduct from Inventory</div>
                    <div class="summary-value"><span id="total-deduction">0.00</span> kg</div>
                    <div class="summary-meta">
                        Feeding <span id="animal-count-display" style="color:var(--emerald); font-weight:800; font-family:var(--font-mono);">0</span> animals 
                        x <span id="per-head-display" style="font-family:var(--font-mono); font-weight:700;">0</span> kg/head
                    </div>
                    <div id="stock-warning" class="stock-warning"><i class="fa-solid fa-triangle-exclamation"></i> Insufficient Stock in Inventory!</div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn-save" id="btn-save" onclick="saveTransaction()" disabled><i class="fa-solid fa-floppy-disk"></i> Confirm Feeding</button>
        </div>
    </div>
</div>

<script>
    const allFeeds = <?php echo json_encode($feeds); ?>;
    let currentAnimalCount = 0;
    let feedMode = 'bulk';
    let fpTransactionDate;
    const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

    document.addEventListener('DOMContentLoaded', () => {
        // Initialize Flatpickr for Date and Time
        fpTransactionDate = flatpickr("#transaction_date", {
            enableTime: true,
            dateFormat: "Y-m-d H:i", // Backend expected format
            altInput: true,
            altFormat: "M j, Y h:i K", // mm/dd/yyyy display format with AM/PM
            allowInput: true
        });
        filterTable();

        // Auto-trigger location change if user is restricted
        if (USER_LOCATION != 1000) {
            handleLocationChange();
        }
    });

    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--emerald)'}`;
        t.innerHTML = `${type === 'error' ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-check"></i>'} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    function toggleMode() {
        feedMode = document.querySelector('input[name="feed_mode"]:checked').value;
        const animalWrapper = document.getElementById('animal-wrapper');
        const penId = document.getElementById('pen_id').value;

        // Reset UI
        document.getElementById('summary-box').style.display = 'none';
        document.getElementById('feed-section').style.opacity = '0.3';
        document.getElementById('feed-section').style.pointerEvents = 'none';
        document.getElementById('btn-save').disabled = true;

        if (feedMode === 'individual') {
            animalWrapper.classList.remove('hidden');
            if(penId) loadAnimalsForPen(penId); 
        } else {
            animalWrapper.classList.add('hidden');
            if(penId) handlePenChange(); 
        }
    }

    function handleLocationChange() { 
        loadBuildings(); 
        filterFeedsByLocation(); 
    }

    function filterFeedsByLocation() {
        const locId = document.getElementById('location_id').value;
        const feedSelect = document.getElementById('feed_id');
        feedSelect.innerHTML = '<option value="">Select Feed</option>';
        
        if (!locId) {
            feedSelect.disabled = true;
            feedSelect.innerHTML = '<option value="">Select Location First</option>';
            return;
        }

        const filteredFeeds = allFeeds.filter(feed => feed.LOCATION_ID == locId);
        if (filteredFeeds.length > 0) {
            feedSelect.disabled = false;
            filteredFeeds.forEach(feed => {
                const opt = document.createElement('option');
                opt.value = feed.FEED_ID;
                opt.textContent = `${feed.FEED_NAME} (Stock: ${feed.TOTAL_WEIGHT_KG}kg)`;
                opt.dataset.stock = feed.TOTAL_WEIGHT_KG; 
                feedSelect.appendChild(opt);
            });
        } else {
            feedSelect.disabled = true;
            feedSelect.innerHTML = '<option value="">No feeds available in this location</option>';
        }
    }

    async function loadBuildings() {
        const locId = document.getElementById('location_id').value;
        const bldg = document.getElementById('building_id');
        const pen = document.getElementById('pen_id');
        const animal = document.getElementById('animal_id');
        
        bldg.innerHTML = '<option>Loading...</option>'; bldg.disabled = true;
        pen.innerHTML = '<option>Select Building First</option>'; pen.disabled = true;
        animal.innerHTML = '<option>Select Pen First</option>'; animal.disabled = true;
        updateFormState(false);

        if(!locId) return;

        try {
            const res = await fetch(`../process/getHierarchyPlaceData.php?action=get_buildings&location_id=${locId}`);
            const data = await res.json();
            bldg.innerHTML = '<option value="">Select Building</option>';
            data.forEach(b => bldg.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`);
            bldg.disabled = false;
        } catch (err) {
            console.error(err);
            bldg.innerHTML = '<option value="">Error loading buildings</option>';
        }
    }

    async function loadPens() {
        const bldgId = document.getElementById('building_id').value;
        const pen = document.getElementById('pen_id');
        const animal = document.getElementById('animal_id');
        
        pen.innerHTML = '<option>Loading...</option>'; pen.disabled = true;
        animal.innerHTML = '<option>Select Pen First</option>'; animal.disabled = true;
        updateFormState(false);

        if(!bldgId) return;

        try {
            const res = await fetch(`../process/getHierarchyPlaceData.php?action=get_pens&building_id=${bldgId}`);
            const data = await res.json();
            pen.innerHTML = '<option value="">Select Pen</option>';
            data.forEach(p => pen.innerHTML += `<option value="${p.PEN_ID}">${p.PEN_NAME}</option>`);
            pen.disabled = false;
        } catch (err) {
            console.error(err);
            pen.innerHTML = '<option value="">Error loading pens</option>';
        }
    }

    function handlePenChange() {
        const penId = document.getElementById('pen_id').value;
        if(!penId) {
            updateFormState(false);
            return;
        }

        if (feedMode === 'bulk') {
            getPenCount(penId);
        } else {
            loadAnimalsForPen(penId);
        }
    }

    async function getPenCount(penId) {
        document.getElementById('pen-loading').style.display='inline-block';
        try {
            const res = await fetch(`../process/getHierarchyPlaceData.php?action=get_pen_details&pen_id=${penId}`);
            const data = await res.json();
            currentAnimalCount = parseInt(data.count) || 0;
            updateFormState(currentAnimalCount > 0);
        } catch (err) {
            console.error(err);
            updateFormState(false);
        } finally {
            document.getElementById('pen-loading').style.display='none';
        }
    }

    async function loadAnimalsForPen(penId) {
        const animalSelect = document.getElementById('animal_id');
        animalSelect.innerHTML = '<option>Loading...</option>';
        animalSelect.disabled = true;
        document.getElementById('animal-loading').style.display='inline-block';

        try {
            const res = await fetch(`../process/getAnimalsByPen.php?pen_id=${penId}`);
            const data = await res.json();
            
            animalSelect.innerHTML = '<option value="">Select Animal</option>';
            
            if(data.animal_record && data.animal_record.length > 0) {
                data.animal_record.forEach(a => {
                    if(a.IS_ACTIVE == 1) {
                        animalSelect.innerHTML += `<option value="${a.ANIMAL_ID}">${a.TAG_NO}</option>`;
                    }
                });
                animalSelect.disabled = false;
            } else {
                animalSelect.innerHTML = '<option value="">No active animals found</option>';
            }
        } catch (err) {
            console.error(err);
            animalSelect.innerHTML = '<option value="">Error loading animals</option>';
        } finally {
            document.getElementById('animal-loading').style.display='none';
            updateFormState(false); // Must select an animal first
        }
    }

    function handleAnimalChange() {
        const animalId = document.getElementById('animal_id').value;
        currentAnimalCount = animalId ? 1 : 0;
        updateFormState(currentAnimalCount > 0);
    }

    function updateFormState(isValid) {
        const sec = document.getElementById('feed-section');
        const sum = document.getElementById('summary-box');
        const btn = document.getElementById('btn-save');
        
        if (isValid) {
            sec.style.opacity="1"; sec.style.pointerEvents="auto"; sum.style.display="block";
            calculateTotal();
        } else {
            if(feedMode === 'bulk' && document.getElementById('pen_id').value) showToast("This pen is currently empty.", "warning");
            sec.style.opacity="0.3"; sec.style.pointerEvents="none"; sum.style.display="none"; btn.disabled = true;
        }
    }

    function calculateTotal() {
        const qty = parseFloat(document.getElementById('qty_per_head').value) || 0;
        const total = currentAnimalCount * qty;
        
        document.getElementById('animal-count-display').textContent = currentAnimalCount;
        document.getElementById('per-head-display').textContent = qty;
        document.getElementById('total-deduction').textContent = total.toFixed(2);

        const feed = document.getElementById('feed_id');
        const opt = feed.options[feed.selectedIndex];
        const warn = document.getElementById('stock-warning');
        const btn = document.getElementById('btn-save');
        
        if(opt && opt.dataset.stock) {
            const stock = parseFloat(opt.dataset.stock);
            if(total > stock) {
                warn.style.display = 'block'; 
                warn.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> Insufficient Stock! Only ${stock.toFixed(2)}kg available.`;
                btn.disabled = true;
            } else if (total > 0) {
                warn.style.display = 'none'; btn.disabled = false;
            } else {
                warn.style.display = 'none'; btn.disabled = true;
            }
        } else {
            warn.style.display = 'none'; btn.disabled = true;
        }
    }

    function undoLastFeed() {
        if(confirm("Are you sure you want to undo the last feeding transaction? This will restore the inventory.")) {
            fetch('../process/undoFeedTransaction.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=undo_last' 
            }).then(r => r.json()).then(d => {
                showToast(d.message, d.success ? "success" : "error");
                if(d.success) {
                    setTimeout(() => window.location.reload(), 1500);
                }
            }).catch(err => {
                showToast("System connection error.", "error");
            });
        }
    }

    function saveTransaction() {
        const penId = document.getElementById('pen_id').value;
        const feedId = document.getElementById('feed_id').value;
        const qty = document.getElementById('qty_per_head').value;
        const date = document.getElementById('transaction_date').value;
        const animalId = document.getElementById('animal_id').value;

        if(!penId || !feedId || !qty || !date) { showToast("Please complete all fields.", "error"); return; }
        if(feedMode === 'individual' && !animalId) { showToast("Please select a target animal.", "error"); return; }

        const btn = document.getElementById('btn-save');
        const ogText = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

        const fd = new FormData();
        fd.append('pen_id', penId); 
        fd.append('feed_id', feedId);
        fd.append('qty_per_head', qty); 
        fd.append('transaction_date', date);
        
        if(feedMode === 'individual') {
            fd.append('animal_id', animalId);
        }

        fetch('../process/addSingleFeedTransaction.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if(d.success) { 
                showToast(d.message, "success"); 
                setTimeout(() => window.location.reload(), 1500);
            }
            else { 
                showToast(d.message, "error"); 
                btn.disabled = false; 
                btn.innerHTML = ogText; 
            }
        })
        .catch(err => {
            showToast("System error occurred.", "error");
            btn.disabled = false;
            btn.innerHTML = ogText;
        });
    }

    function openAddModal() {
        document.getElementById('modal').classList.add('show');
        document.getElementById('bulk-feed-form').reset();
        
        // Clear input on load so it starts completely blank
        fpTransactionDate.clear();
        
        // Ensure restricted location is applied to modal forms
        if (USER_LOCATION != 1000) {
            document.getElementById('location_id').value = USER_LOCATION;
            handleLocationChange();
        } else {
            document.getElementById('location_id').value = "";
            document.getElementById('building_id').innerHTML = '<option value="">Select Location First</option>';
            document.getElementById('building_id').disabled = true;
        }

        toggleMode();
    }
    
    function closeModal() { 
        document.getElementById('modal').classList.remove('show'); 
    }

    // Close modal on outside click
    window.onclick = function(e) {
        if (e.target.classList.contains('modal')) {
            closeModal();
        }
    }
    
    function filterTable() {
        const term = document.getElementById('searchInput').value.toLowerCase();
        const rows = document.querySelectorAll('#transaction-table tr');
        
        if (rows.length === 1 && rows[0].querySelector('.empty-state')) {
            document.getElementById('empty-state').style.display = 'none';
            return;
        }

        let visible = 0;
        rows.forEach(r => {
            if(r.textContent.toLowerCase().includes(term)) { r.style.display=''; visible++; }
            else { r.style.display='none'; }
        });
        document.getElementById('empty-state').style.display = (visible === 0) ? 'block' : 'none';
    }
</script>
</body>
</html>