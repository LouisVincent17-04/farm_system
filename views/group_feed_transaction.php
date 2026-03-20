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
    <title>Group Feeding Transactions</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        /* --- CORE STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; color: white; padding-bottom: 80px; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; width: 100%; }

        /* HEADER & BACK BUTTON */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1.5rem; }
        .header-info h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: bold; margin-bottom: 0.5rem; line-height: 1.2; }
        .header-info p { color: #cbd5e1; font-size: 0.95rem; }

        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #94a3b8; font-weight: 600; font-size: 0.95rem; margin-bottom: 10px; transition: color 0.2s; }
        .back-link:hover { color: white; }

        .header-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

        /* BUTTONS */
        .btn-base { display: flex; align-items: center; justify-content: center; gap: 8px; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1); white-space: nowrap; }
        .add-btn { background: linear-gradient(135deg, #2563eb, #9333ea); color: white; }
        .add-btn:hover { background: linear-gradient(135deg, #1d4ed8, #7c3aed); transform: translateY(-2px); }

        .global-undo-btn { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .global-undo-btn:hover { background: rgba(245, 158, 11, 0.25); border-color: #fbbf24; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(251, 191, 36, 0.1); }

        /* SEARCH & TABLE */
        .search-container { position: relative; margin-bottom: 2rem; }
        .search-input { width: 100%; padding: 14px 14px 14px 45px; background: rgba(30, 41, 59, 0.5); border: 1px solid #475569; border-radius: 8px; color: white; font-size: 1rem; backdrop-filter: blur(10px); outline: none; transition: border-color 0.2s; }
        .search-input:focus { border-color: #3b82f6; }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 20px; height: 20px; }
        
        .table-container { background: rgba(30, 41, 59, 0.5); border-radius: 12px; border: 1px solid #475569; overflow: hidden; overflow-x: auto;}
        .table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .table th { padding: 1rem 1.5rem; text-align: left; font-size: 0.85rem; font-weight: 600; color: #e2e8f0; text-transform: uppercase; background: rgba(15, 23, 42, 0.5); border-bottom: 1px solid #475569; }
        .table td { padding: 1rem 1.5rem; vertical-align: middle; color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .table tbody tr:hover { background: rgba(255, 255, 255, 0.02); }

        .tag-badge { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; white-space: nowrap; }
        .pen-badge { background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; border: 1px solid rgba(16, 185, 129, 0.2); white-space: nowrap; }
        .amount { color: #34d399; font-weight: 600; font-family: 'Segoe UI', monospace; }

        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
        .modal.show { display: flex; }
        .modal-content { background: #1e293b; border-radius: 16px; width: 100%; max-width: 650px; border: 1px solid #475569; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); display: flex; flex-direction: column; max-height: 90vh; }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid #334155; }
        .modal-header h2 { margin: 0; font-size: 1.4rem; color: #fff; }
        .modal-body { padding: 1.5rem; overflow-y: auto; flex-grow: 1; }
        .modal-footer { padding: 1.5rem; border-top: 1px solid #334155; display: flex; justify-content: flex-end; gap: 10px; }

        /* FORM ELEMENTS */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; display: flex; flex-direction: column; }
        .form-group label { display: block; color: #94a3b8; font-size: 0.85rem; margin-bottom: 8px; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: white; font-size: 0.95rem; transition: border-color 0.2s; outline: none;}
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        
        select[disabled], input[disabled], input[readonly] { opacity: 0.6; cursor: not-allowed; background: #1e293b; color:#94a3b8;}

        .resource-link { display: inline-flex; align-items: center; gap: 5px; font-size: 0.85rem; color: #60a5fa; text-decoration: none; transition: color 0.2s; font-weight: 600; }
        .resource-link:hover { color: #93c5fd; text-decoration: underline; }
        
        .btn-mini { background: #334155; border: 1px solid #475569; color: #fff; border-radius: 6px; padding: 6px 12px; cursor: pointer; font-size: 0.8rem; white-space: nowrap; transition: 0.2s; }
        .btn-mini:hover:not(:disabled) { background: #475569; }
        .btn-mini:disabled { opacity: 0.5; cursor: not-allowed; }

        /* CHECKBOX LIST STYLING */
        .pens-list-container { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 10px; max-height: 250px; overflow-y: auto; margin-top: 5px; }
        .pen-group { margin-bottom: 10px; background: rgba(30, 41, 59, 0.5); padding: 12px; border-radius: 8px; border: 1px solid #334155; }
        .pen-group:last-child { margin-bottom: 0; }
        .pen-label { font-weight: bold; color: #fff; display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 1rem; }
        .animal-list { margin-top: 12px; margin-left: 28px; display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; }
        .animal-label { font-size: 0.85rem; color: #cbd5e1; display: flex; align-items: center; gap: 6px; cursor: pointer; background: rgba(255,255,255,0.03); padding: 5px 8px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.05); transition: background 0.2s; }
        .animal-label:hover { background: rgba(255,255,255,0.1); }
        .animal-label input[type="checkbox"], .pen-label input[type="checkbox"] { accent-color: #3b82f6; width: 18px; height: 18px; cursor: pointer; }

        /* Toggle Button styling for Method */
        .method-toggle { display: flex; background: #0f172a; border: 1px solid #334155; border-radius: 8px; overflow: hidden; margin-bottom: 10px; }
        .method-btn { flex: 1; padding: 10px; text-align: center; font-size: 0.9rem; font-weight: 600; color: #94a3b8; cursor: pointer; background: transparent; border: none; transition: 0.2s; }
        .method-btn.active { background: #3b82f6; color: white; }

        /* Summary Box */
        .summary-box { background: rgba(15, 23, 42, 0.6); border: 1px solid #3b82f6; border-radius: 8px; padding: 15px; text-align: center; margin-top: 15px; display: none; }
        .summary-title { color: #94a3b8; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; }
        .summary-value { font-size: 2rem; font-weight: 800; color: white; margin: 5px 0; }
        .stock-warning { color: #ef4444; font-size: 0.85rem; margin-top: 10px; display: none; font-weight: bold; }
        
        .loading { display: inline-block; width: 12px; height: 12px; border: 2px solid #fff; border-radius: 50%; border-top-color: transparent; animation: spin 0.8s linear infinite; margin-left: 5px; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .btn-cancel { padding: 12px 20px; background: transparent; border: 1px solid #475569; color: #cbd5e1; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
        .btn-cancel:hover { background: rgba(255,255,255,0.05); color: white; }
        .btn-save { padding: 12px 20px; background: #2563eb; border: none; color: white; border-radius: 8px; cursor: pointer; font-weight: 600; transition: background 0.2s; }
        .btn-save:hover { background: #1d4ed8; }
        .btn-save:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .header-info { text-align: left; }
            .header-actions { flex-direction: column; width: 100%; gap: 10px; }
            .btn-base { width: 100%; justify-content: center; }

            .form-row { grid-template-columns: 1fr; gap: 0; }
            .modal-footer { flex-direction: column; }
            .modal-footer button { width: 100%; margin-left: 0; }

            .table-container { border: none; background: transparent; overflow: visible;}
            .table { min-width: 0; display: block; }
            .table thead { display: none; }
            .table tbody { display: block; width: 100%; }
            .table tr { display: block; background: #1e293b; border: 1px solid #475569; border-radius: 12px; margin-bottom: 1rem; padding: 0.5rem; }
            .table td { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0.5rem; border-bottom: 1px dashed rgba(255,255,255,0.05); text-align: right; font-size: 0.95rem; }
            .table td:last-child { border-bottom: none; }
            .table td::before { content: attr(data-label); font-weight: 700; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; margin-right: 1rem; text-align: left; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="transactions.php" class="back-link">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Transactions 
        </a>

        <div class="header">
            <div class="header-info">
                <h1>Group Feeding Transactions</h1>
                <p>Record and track bulk animal feeding</p>
            </div>
            
            <div class="header-actions">
                <button class="btn-base global-undo-btn" onclick="undoLastFeed()">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                    Undo Last Feed
                </button>

                <button class="btn-base add-btn" onclick="openAddModal()">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Bulk Feed Selection
                </button>
            </div>
        </div>

        <div class="search-container">
            <svg class="search-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Search logs by tag, pen, or feed..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Pen Name</th>
                        <th>Animal Tag</th>
                        <th>Feed Used</th>
                        <th>Qty (KG)</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody id="transaction-table">
                    <?php if(empty($transactions_data)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 3rem; color:#94a3b8;">No feeding transactions recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($transactions_data as $row): ?>
                        <tr>
                            <td data-label="Date & Time" style="color:#94a3b8;"><?php echo $row['FORMATTED_DATE']; ?></td>
                            <td data-label="Pen Name"><span class="pen-badge"><?php echo htmlspecialchars($row['PEN_NAME']); ?></span></td>
                            <td data-label="Animal Tag"><span class="tag-badge"><?php echo htmlspecialchars($row['TAG_NO']); ?></span></td>
                            <td data-label="Feed Used" style="font-weight: 500; color: #fff;"><?php echo htmlspecialchars($row['FEED_NAME']); ?></td>
                            <td data-label="Qty (KG)" class="amount"><?php echo number_format($row['QUANTITY_KG'], 2); ?></td>
                            <td data-label="Remarks" style="font-size:0.9rem; color:#cbd5e1;"><?php echo htmlspecialchars($row['REMARKS'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="empty-state" style="text-align:center; padding:3rem; display:none; color:#94a3b8;">
                No records found matching your search.
            </div>
        </div>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Bulk Feed Selection</h2></div>
            <div class="modal-body">
                <form id="bulk-feed-form">
                    
                    <div class="form-group">
                        <label style="color:#93c5fd; font-size:1rem; margin-bottom:15px; font-weight:700;">1. Target Area & Animals</label>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Location</label>
                                <select id="location_id" onchange="handleLocationChange()" <?php echo ($USER_LOCATION_ != 1000) ? 'style="background-color: #1e293b; pointer-events: none; color: #94a3b8;"' : ''; ?>>
                                    <?php if($USER_LOCATION_ == 1000): ?>
                                        <option value="">Select Location</option>
                                    <?php endif; ?>
                                    <?php foreach($locations as $loc): ?>
                                        <option value="<?php echo $loc['LOCATION_ID']; ?>" <?php echo ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Building</label>
                                <select id="building_id" onchange="loadPensAndAnimals()" disabled><option value="">Select Location First</option></select>
                            </div>
                        </div>
                        
                        <div class="form-group" id="animal-selection-group" style="display:none;">
                            <label>Select Pens & Animals <span id="pen-loading" style="display:none;" class="loading"></span></label>
                            <div id="pens-container" class="pens-list-container">
                                </div>
                        </div>
                    </div>

                    <div id="feed-section" style="opacity: 0.5; pointer-events: none;">
                        <label style="color:#93c5fd; font-size:1rem; margin-top:10px; display:block; margin-bottom:15px; font-weight:700;">2. Feeding Details</label>
                        <div class="form-group">
                            <label>Feed Selection</label>
                            <select id="feed_id" onchange="calculateTotal()" disabled>
                                <option value="">Select Location First</option>
                            </select>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                                <a href="purch_feeds_feeding.php" target="_blank" class="resource-link" title="Opens in a new tab">
                                    Manage / Purchase Feeds ↗
                                </a>
                                <button type="button" id="refresh-feeds-btn" class="btn-mini" onclick="refreshFeedsList()" style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3);">
                                    ↻ Refresh Feeds
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group"> 
                                <label>Distribution Method</label>
                                <div class="method-toggle">
                                    <button type="button" class="method-btn active" id="method-per-head" onclick="setMethod('head')">Per Head</button>
                                    <button type="button" class="method-btn" id="method-total" onclick="setMethod('total')">Total for Group</button>
                                </div>
                                <input type="hidden" id="calc_method" value="head">
                                
                                <input type="number" id="input_qty" step="0.01" min="0.01" placeholder="e.g. 0.5" oninput="calculateTotal()">
                            </div>
                            <div class="form-group"> <label>Date & Time</label>
                                <input type="text" id="transaction_date" class="form-input date-picker" placeholder="Select Date & Time" required>
                            </div>
                        </div>
                    </div>

                    <div class="summary-box" id="summary-box">
                        <div class="summary-title">Total to Deduct from Stock</div>
                        <div class="summary-value"><span id="total-deduction">0.00</span> kg</div>
                        <div style="color:#64748b; font-size:0.9rem; margin-top:5px;">
                            Feeding <span id="animal-count-display" style="color:#34d399; font-weight:bold;">0</span> animals 
                            (<span id="per-head-display">0</span> kg/head)
                        </div>
                        <div id="stock-warning" class="stock-warning">⚠️ Insufficient Stock!</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn-save" id="btn-save" onclick="saveBulkFeed()">Confirm Feeding</button>
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
                altFormat: "m/d/Y h:i K", 
                allowInput: true
            });
            filterTable();
        });

        // --- NEW: FEEDING METHOD TOGGLE ---
        function setMethod(method) {
            const btnHead = document.getElementById('method-per-head');
            const btnTotal = document.getElementById('method-total');
            const inputField = document.getElementById('input_qty');
            
            document.getElementById('calc_method').value = method;

            if (method === 'head') {
                btnHead.classList.add('active');
                btnTotal.classList.remove('active');
                inputField.placeholder = "e.g. 0.5 (kg per animal)";
            } else {
                btnTotal.classList.add('active');
                btnHead.classList.remove('active');
                inputField.placeholder = "e.g. 25 (total kg to split)";
            }
            
            calculateTotal(); // Re-run math
        }

        async function refreshFeedsList() {
            const btn = document.getElementById('refresh-feeds-btn');
            btn.innerHTML = '↻ Loading...';
            btn.disabled = true;

            try {
                const res = await fetch('group_feed_transaction.php?action=get_feeds');
                const data = await res.json();
                
                allFeeds = data; // Update global array
                filterFeedsByLocation(); // Re-populate dropdown
                
                btn.innerHTML = '↻ Refresh Feeds';
                btn.disabled = false;
            } catch (e) {
                btn.innerHTML = '❌ Error';
                setTimeout(() => { 
                    btn.innerHTML = '↻ Refresh Feeds'; 
                    btn.disabled = false; 
                }, 2000);
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
                feedSelect.innerHTML = '<option value="">No feeds available here</option>';
            }
            calculateTotal(); // Re-validate if stock changed
        }

        async function loadBuildings() {
            const locId = document.getElementById('location_id').value;
            const bldg = document.getElementById('building_id');
            
            bldg.innerHTML = '<option>Loading...</option>'; bldg.disabled = true;
            if(!locId) return;

            const res = await fetch(`../process/getHierarchyPlaceData.php?action=get_buildings&location_id=${locId}`);
            const data = await res.json();
            bldg.innerHTML = '<option value="">Select Building</option>';
            data.forEach(b => bldg.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`);
            bldg.disabled = false;
        }

        async function loadPensAndAnimals() {
            const bldgId = document.getElementById('building_id').value;
            const container = document.getElementById('pens-container');
            const groupWrapper = document.getElementById('animal-selection-group');
            const loader = document.getElementById('pen-loading');
            
            container.innerHTML = '';
            updateSelection(); 

            if(!bldgId) {
                groupWrapper.style.display = 'none';
                return;
            }

            groupWrapper.style.display = 'block';
            loader.style.display = 'inline-block';

            const res = await fetch(`group_feed_transaction.php?action=get_pens_animals&bldg_id=${bldgId}`);
            const pens = await res.json();
            loader.style.display = 'none';

            if(pens.length === 0) {
                container.innerHTML = '<div style="color:#94a3b8; padding:10px;">No pens/animals found in this building.</div>';
                return;
            }

            let html = '';
            pens.forEach(p => {
                const isPenEmpty = p.animals.length === 0;
                html += `
                    <div class="pen-group">
                        <label class="pen-label">
                            <input type="checkbox" class="pen-cb" value="${p.pen_id}" onchange="togglePen(this)" ${isPenEmpty ? 'disabled' : ''}> 
                            ${p.pen_name} ${isPenEmpty ? '<span style="color:#ef4444; font-size:0.8rem;">(Empty)</span>' : `<span style="color:#34d399; font-size:0.8rem;">(${p.animals.length} animals)</span>`}
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
        }

        function togglePen(penCb) {
            const container = penCb.closest('.pen-group');
            const animalCbs = container.querySelectorAll('.animal-cb');
            animalCbs.forEach(cb => cb.checked = penCb.checked);
            updateSelection();
        }

        function toggleAnimal(animalCb) {
            const container = animalCb.closest('.pen-group');
            const penCb = container.querySelector('.pen-cb');
            const total = container.querySelectorAll('.animal-cb').length;
            const checked = container.querySelectorAll('.animal-cb:checked').length;
            
            penCb.checked = (total > 0 && total === checked);
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
                sec.style.opacity = "0.5"; sec.style.pointerEvents = "none"; sum.style.display = "none";
            }
            calculateTotal();
        }

        // --- MATH CALCULATOR ---
        function calculateTotal() {
            const method = document.getElementById('calc_method').value;
            const rawInput = parseFloat(document.getElementById('input_qty').value) || 0;
            
            let totalToDeduct = 0;
            let qtyPerHead = 0;

            if (currentAnimalCount > 0) {
                if (method === 'head') {
                    // Input is per head. Total is input * count
                    qtyPerHead = rawInput;
                    totalToDeduct = currentAnimalCount * rawInput;
                } else {
                    // Input is total. Per head is input / count
                    totalToDeduct = rawInput;
                    qtyPerHead = rawInput / currentAnimalCount;
                }
            }
            
            document.getElementById('animal-count-display').textContent = currentAnimalCount;
            // Limit decimals on display so it looks clean (e.g. 25kg / 3 animals = 8.33/head)
            document.getElementById('per-head-display').textContent = qtyPerHead.toFixed(2); 
            document.getElementById('total-deduction').textContent = totalToDeduct.toFixed(2);

            const feed = document.getElementById('feed_id');
            const opt = feed.options[feed.selectedIndex];
            const warn = document.getElementById('stock-warning');
            const btn = document.getElementById('btn-save');
            
            if(opt && opt.dataset.stock) {
                if(totalToDeduct > parseFloat(opt.dataset.stock) || totalToDeduct <= 0) {
                    if (totalToDeduct > parseFloat(opt.dataset.stock)) {
                        warn.style.display = 'block'; 
                        warn.innerText = '⚠️ Insufficient Stock!';
                    } else {
                        warn.style.display = 'none';
                    }
                    btn.disabled = true;
                } else {
                    warn.style.display = 'none'; btn.disabled = false;
                }
            } else {
                btn.disabled = true; 
            }
        }

        function undoLastFeed() {
            if(confirm("Are you sure you want to UNDO the very last feeding transaction? \n\nThis will remove the records and restore the stock.")) {
                const btn = document.querySelector('.global-undo-btn');
                const origText = btn.innerHTML;
                btn.disabled = true; btn.innerHTML = 'Restoring...';

                fetch('../process/undoFeedTransaction.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=undo_last' 
                })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert("Error: " + data.message);
                        btn.disabled = false; btn.innerHTML = origText;
                    }
                })
                .catch(e => {
                    alert("System Error");
                    btn.disabled = false; btn.innerHTML = origText;
                });
            }
        }

        // --- SUBMITTER ---
        function saveBulkFeed() {
            const animalCbs = document.querySelectorAll('.animal-cb:checked');
            const feedId = document.getElementById('feed_id').value;
            const date = document.getElementById('transaction_date').value;
            
            // To prevent math errors, we ALWAYS submit the computed 'Per Head' value to the backend
            const qtyPerHead = parseFloat(document.getElementById('per-head-display').textContent);

            if(animalCbs.length === 0) { alert("Please select at least one animal."); return; }
            if(!feedId || qtyPerHead <= 0 || !date) { alert("Please fill in all feeding details."); return; }

            const btn = document.getElementById('btn-save');
            btn.disabled = true; btn.innerHTML = 'Saving...';

            const fd = new FormData();
            
            animalCbs.forEach(cb => {
                fd.append('animal_ids[]', cb.value);
            });
            
            fd.append('feed_id', feedId);
            fd.append('qty_per_head', qtyPerHead); 
            fd.append('transaction_date', date);

            fetch('../process/addFeedTransaction.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if(d.success) { 
                    alert(d.message); 
                    window.location.reload(); 
                } else { 
                    alert(d.message); 
                    btn.disabled=false; 
                    btn.innerHTML='Confirm Feeding'; 
                }
            })
            .catch(e => {
                alert("An unexpected error occurred.");
                btn.disabled = false;
                btn.innerHTML = 'Confirm Feeding';
            });
        }

        function openAddModal() {
            document.getElementById('modal').classList.add('show');
            document.getElementById('bulk-feed-form').reset();
            fpTransactionDate.clear(); 
            
            // Reset UI state
            setMethod('head'); 
            
            const locSelect = document.getElementById('location_id');
            
            if (USER_LOCATION != 1000) {
                locSelect.value = USER_LOCATION;
                handleLocationChange();
            } else {
                locSelect.value = "";
                document.getElementById('building_id').innerHTML = '<option value="">Select Location First</option>';
                document.getElementById('building_id').disabled = true;
                document.getElementById('animal-selection-group').style.display = 'none';
                document.getElementById('feed_id').innerHTML = '<option value="">Select Location First</option>';
                document.getElementById('feed_id').disabled = true;
                updateSelection();
            }
        }
        
        function closeModal() { 
            document.getElementById('modal').classList.remove('show'); 
        }
        
        function filterTable() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#transaction-table tr');
            let visible = 0;
            
            if (rows.length === 1 && rows[0].children.length === 1) {
                document.getElementById('empty-state').style.display = 'none';
                return;
            }

            rows.forEach(r => {
                if(r.textContent.toLowerCase().includes(term)) { r.style.display=''; visible++; }
                else { r.style.display='none'; }
            });
            
            document.getElementById('empty-state').style.display = (visible === 0) ? 'block' : 'none';
        }
    </script>
</body>
</html>