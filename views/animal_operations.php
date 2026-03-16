<?php
// views/animal_operations.php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

$page = "farm";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('animal_operations');
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php'; // ADDED LOCATION FUNCTION

// =========================================================
// 1. AJAX HANDLER
// =========================================================
if (isset($_GET['action'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $status = $_GET['status_filter'] ?? 'Active';

    // Build Status Clause
    $statusClause = " AND IS_ACTIVE = 1 ";
    if ($status === 'Inactive') $statusClause = " AND IS_ACTIVE = 0 ";
    if ($status === 'All') $statusClause = ""; 

    try {
        if ($action === 'get_buildings' && isset($_GET['loc_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['loc_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_pens' && isset($_GET['bldg_id'])) {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
            $stmt->execute([$_GET['bldg_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_animals' && isset($_GET['pen_id'])) {
            $sql = "SELECT ANIMAL_ID, TAG_NO FROM animal_records WHERE PEN_ID = ? $statusClause ORDER BY TAG_NO";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_GET['pen_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'search_tag' && isset($_GET['query'])) {
            $q = "%" . $_GET['query'] . "%";
            // Restricted Search: Only search animals within the user's location unless user is 1000
            $locRestriction = ($USER_LOCATION_ != 1000) ? " AND LOCATION_ID = $USER_LOCATION_ " : "";
            $sql = "SELECT ANIMAL_ID, TAG_NO, CURRENT_STATUS FROM animal_records WHERE TAG_NO LIKE ? $statusClause $locRestriction LIMIT 5";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$q]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        
        // Fetch Piglets associated with a Cost Transfer
        if ($action === 'get_transfer_details' && isset($_GET['transfer_id'])) {
            $tid = $_GET['transfer_id'];
            
            // Get transfer details including individual parent costs
            $stmt = $conn->prepare("SELECT SOW_ID, BOAR_ID, TRANSFER_DATE, COST_PER_HEAD, SOW_COST, BOAR_COST, PIGLET_COUNT FROM cost_transfers WHERE TRANSFER_ID = ?");
            $stmt->execute([$tid]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($transfer) {
                // Calculate individual parent contributions per piglet
                $piglet_count = max(1, (int)$transfer['PIGLET_COUNT']); // Prevent division by zero
                $sow_share = $transfer['SOW_COST'] / $piglet_count;
                $boar_share = $transfer['BOAR_COST'] / $piglet_count;

                // Match exactly by acquisition cost first (handles multiple litters on same day) AND Limit exactly to the Piglet Count
                $stmt2 = $conn->prepare("SELECT TAG_NO, SEX, CURRENT_STATUS, ACQUISITION_COST 
                                         FROM animal_records 
                                         WHERE MOTHER_ID = :sow 
                                           AND BIRTH_DATE = DATE(:tdate)
                                           AND ABS(ACQUISITION_COST - :cost) < 0.1
                                         ORDER BY ANIMAL_ID ASC 
                                         LIMIT :limit");
                $stmt2->bindValue(':sow', $transfer['SOW_ID'], PDO::PARAM_INT);
                $stmt2->bindValue(':tdate', $transfer['TRANSFER_DATE']);
                $stmt2->bindValue(':cost', $transfer['COST_PER_HEAD']);
                $stmt2->bindValue(':limit', $piglet_count, PDO::PARAM_INT);
                $stmt2->execute();
                $piglets = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                
                // Fallback: If ACQUISITION_COST was modified manually, just get the N most recent piglets from that date
                if (empty($piglets)) {
                    $stmt3 = $conn->prepare("SELECT TAG_NO, SEX, CURRENT_STATUS, ACQUISITION_COST 
                                             FROM animal_records 
                                             WHERE MOTHER_ID = :sow 
                                               AND BIRTH_DATE = DATE(:tdate)
                                             ORDER BY ANIMAL_ID DESC 
                                             LIMIT :limit");
                    $stmt3->bindValue(':sow', $transfer['SOW_ID'], PDO::PARAM_INT);
                    $stmt3->bindValue(':tdate', $transfer['TRANSFER_DATE']);
                    $stmt3->bindValue(':limit', $piglet_count, PDO::PARAM_INT);
                    $stmt3->execute();
                    $piglets = $stmt3->fetchAll(PDO::FETCH_ASSOC);
                }
                
                echo json_encode([
                    'success' => true, 
                    'piglets' => $piglets, 
                    'cost_per_head' => $transfer['COST_PER_HEAD'],
                    'sow_share' => $sow_share,
                    'boar_share' => $boar_share
                ]);
            } else {
                echo json_encode(['success' => false]);
            }
            exit;
        }
    } catch (Exception $e) { echo json_encode([]); exit; }
}

// =========================================================
// 2. MAIN PAGE LOGIC
// =========================================================

// Auto-assign location filter if user is restricted
$filter_loc = ($USER_LOCATION_ != 1000) ? $USER_LOCATION_ : ($_GET['f_loc'] ?? '');
$filter_bld = $_GET['f_bld'] ?? '';
$filter_pen = $_GET['f_pen'] ?? '';

// Filter Locations dropdown based on user access
if ($USER_LOCATION_ != 1000) {
    $stmt = $conn->prepare("SELECT * FROM locations WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME");
    $stmt->execute([$USER_LOCATION_]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
}

$animal_id = $_GET['animal_id'] ?? null;
$animal_info = null;
$records = [];
$total_cost = 0;

if ($animal_id) {
    // A. Fetch Basic Info
    $stmt = $conn->prepare("
        SELECT a.*, 
                at.ANIMAL_TYPE_NAME, b.BREED_NAME, 
                l.LOCATION_NAME, bu.BUILDING_NAME, p.PEN_NAME
        FROM animal_records a
        LEFT JOIN animal_type at ON a.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
        LEFT JOIN breeds b ON a.BREED_ID = b.BREED_ID
        LEFT JOIN locations l ON a.LOCATION_ID = l.LOCATION_ID
        LEFT JOIN buildings bu ON a.BUILDING_ID = bu.BUILDING_ID
        LEFT JOIN pens p ON a.PEN_ID = p.PEN_ID
        WHERE a.ANIMAL_ID = ?
    ");
    $stmt->execute([$animal_id]);
    $animal_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($animal_info) {
        // B. Fetch All Transactions (Included Cost Transfers & REF_ID for Modals)
        $sql = "SELECT * FROM (
            SELECT ft.TRANSACTION_DATE as LOG_DATE, 'Feeding' as LOG_TYPE, f.FEED_NAME as ITEM_NAME, 
                   ft.TRANSACTION_COST as COST, ft.REMARKS, ft.QUANTITY_KG as QTY, 'kg' as UNIT, 0 as REF_ID
            FROM FEED_TRANSACTIONS ft
            JOIN FEEDS f ON ft.FEED_ID = f.FEED_ID
            WHERE ft.ANIMAL_ID = ?
            UNION ALL
            SELECT tt.TRANSACTION_DATE as LOG_DATE, 'Medication' as LOG_TYPE, m.SUPPLY_NAME as ITEM_NAME, 
                   tt.TOTAL_COST as COST, tt.REMARKS, tt.QUANTITY_USED as QTY, 'units' as UNIT, 0 as REF_ID
            FROM TREATMENT_TRANSACTIONS tt
            JOIN MEDICINES m ON tt.ITEM_ID = m.SUPPLY_ID
            WHERE tt.ANIMAL_ID = ?
            UNION ALL
            SELECT vr.VACCINATION_DATE as LOG_DATE, 'Vaccination' as LOG_TYPE, v.SUPPLY_NAME as ITEM_NAME, 
                   (vr.VACCINE_COST + vr.VACCINATION_COST) as COST, vr.REMARKS, vr.QUANTITY as QTY, 'doses' as UNIT, 0 as REF_ID
            FROM VACCINATION_RECORDS vr
            JOIN VACCINES v ON vr.ITEM_ID = v.SUPPLY_ID
            WHERE vr.ANIMAL_ID = ?
            UNION ALL
            SELECT vt.TRANSACTION_DATE as LOG_DATE, 'Vitamins' as LOG_TYPE, vs.SUPPLY_NAME as ITEM_NAME, 
                   vt.TOTAL_COST as COST, vt.REMARKS, vt.QUANTITY_USED as QTY, 'units' as UNIT, 0 as REF_ID
            FROM VITAMINS_SUPPLEMENTS_TRANSACTIONS vt
            JOIN VITAMINS_SUPPLEMENTS vs ON vt.ITEM_ID = vs.SUPPLY_ID
            WHERE vt.ANIMAL_ID = ?
            UNION ALL
            SELECT c.CHECKUP_DATE as LOG_DATE, 'Checkup' as LOG_TYPE, CONCAT('Vet: ', c.VET_NAME) as ITEM_NAME, 
                   c.COST as COST, c.REMARKS, 1 as QTY, 'visit' as UNIT, 0 as REF_ID
            FROM CHECK_UPS c
            WHERE c.ANIMAL_ID = ?
            UNION ALL
            SELECT ct.TRANSFER_DATE as LOG_DATE, 'Cost Transfer' as LOG_TYPE, CONCAT('To ', ct.PIGLET_COUNT, ' Piglets') as ITEM_NAME, 
                   (CASE WHEN ct.SOW_ID = ? THEN ct.SOW_COST ELSE ct.BOAR_COST END) as COST, 
                   'Birthing Cost Deduction' as REMARKS, ct.PIGLET_COUNT as QTY, 'heads' as UNIT, ct.TRANSFER_ID as REF_ID
            FROM cost_transfers ct
            WHERE ct.SOW_ID = ? OR ct.BOAR_ID = ?
        ) AS MasterLog 
        ORDER BY LOG_DATE DESC";

        $stmt = $conn->prepare($sql);
        // Requires binding the animal ID 8 times due to 8 placeholders across the UNION queries
        $stmt->execute([$animal_id, $animal_id, $animal_id, $animal_id, $animal_id, $animal_id, $animal_id, $animal_id]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach($records as $r) {
            // Subtract transfer deductions, add normal costs
            if ($r['LOG_TYPE'] === 'Cost Transfer') {
                $total_cost -= $r['COST'];
            } else {
                $total_cost += $r['COST'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Operational History</title>
    <style>
        /* --- THEME STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0; min-height: 100vh;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }

        /* Back Link - Upper Left, No Outline */
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; 
            text-decoration: none; color: #94a3b8; font-weight: 600; 
            font-size: 1rem; margin-bottom: 1rem; transition: color 0.2s;
            border: none; background: transparent; padding: 0;
        }
        .back-link:hover { color: white; }

        .page-header { margin-bottom: 2rem; border-bottom: 1px solid #334155; padding-bottom: 1rem; }
        .page-title { font-size: 2rem; font-weight: 800; color: white; margin-bottom: 0.5rem; }
        .page-desc { color: #94a3b8; }

        /* Filter Card */
        .filter-card {
            background: rgba(30, 41, 59, 0.6); border: 1px solid #475569;
            border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;
        }
        .filter-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 1rem; align-items: end;
        }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 0.75rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-select, .form-input {
            width: 100%; padding: 10px; background: #0f172a; border: 1px solid #334155;
            color: white; border-radius: 6px; font-size: 0.9rem;
        }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; }
        .form-select:focus, .form-input:focus { border-color: #3b82f6; outline: none; }

        /* Divider */
        .divider-text { text-align: center; color: #64748b; font-size: 0.8rem; font-weight: bold; margin: 1.5rem 0; position: relative; }
        .divider-text::before, .divider-text::after { content: ""; position: absolute; top: 50%; width: 40%; height: 1px; background: #334155; }
        .divider-text::before { left: 0; } .divider-text::after { right: 0; }

        .btn-go {
            background: #3b82f6; color: white; border: none; padding: 10px; 
            border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%;
        }
        .btn-go:hover { background: #2563eb; }

        /* Animal Profile Card */
        .profile-card {
            background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;
            display: flex; justify-content: space-between; align-items: center;
        }
        .profile-main h2 { font-size: 2rem; margin: 0; color: #fff; }
        .profile-sub { color: #86efac; font-size: 0.9rem; margin-top: 5px; }
        .profile-stats { text-align: right; }
        .total-cost-label { font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; }
        .total-cost-val { font-size: 1.8rem; font-weight: 800; color: #fbbf24; }

        /* Table */
        .table-wrapper { background: #1e293b; border-radius: 12px; border: 1px solid #334155; overflow: hidden; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            text-align: left; padding: 1rem; background: #0f172a;
            color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; border-bottom: 2px solid #334155;
        }
        .data-table td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); color: #cbd5e1; }
        .data-table tr:hover { background: rgba(255,255,255,0.02); }

        /* Badges & Buttons */
        .type-badge { padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .type-feeding { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .type-medication { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .type-vaccination { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }
        .type-vitamins { background: rgba(236, 72, 153, 0.15); color: #f472b6; }
        .type-checkup { background: rgba(139, 92, 246, 0.15); color: #a78bfa; }
        .type-cost-transfer { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }

        .cost-val { font-family: monospace; font-weight: 600; color: #fbbf24; }
        .empty-state { text-align: center; padding: 4rem; color: #64748b; }
        
        .btn-view-piglets {
            background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b; color: #fbbf24;
            padding: 5px 10px; border-radius: 6px; font-size: 0.75rem; cursor: pointer;
            transition: all 0.2s; white-space: nowrap;
        }
        .btn-view-piglets:hover { background: #f59e0b; color: #0f172a; }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; backdrop-filter: blur(4px); }
        .modal.show { display: flex; }
        .modal-content { background: #1e293b; border-radius: 12px; width: 100%; max-width: 600px; border: 1px solid #475569; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { margin: 0; color: #fbbf24; font-size: 1.25rem; }
        .modal-body { padding: 0; max-height: 60vh; overflow-y: auto; }
        .btn-close { background: transparent; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; transition: color 0.2s; }
        .btn-close:hover { color: #ef4444; }

        /* --- MOBILE RESPONSIVE CSS --- */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-title { font-size: 1.5rem; }

            /* Stack Profile Card */
            .profile-card { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .profile-stats { text-align: left; border-top: 1px solid rgba(16, 185, 129, 0.3); padding-top: 1rem; width: 100%; }

            /* Table to Card View Transformation */
            .data-table thead { display: none; } /* Hide Headers */
            .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; box-sizing: border-box; }
            
            .data-table tr {
                background: rgba(30, 41, 59, 0.6);
                border: 1px solid #475569;
                border-radius: 12px;
                margin-bottom: 1rem;
                padding: 1rem;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }

            .data-table td {
                padding: 0.5rem 0;
                text-align: right;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid rgba(255,255,255,0.05);
            }

            .data-table td:last-child { border-bottom: none; }

            /* Data Labels via CSS */
            .data-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #94a3b8;
                font-size: 0.85rem;
                text-transform: uppercase;
                margin-right: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="container">
    
    <a href="farm_dashboard.php" class="back-link">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Farm Dashboard
    </a>

    <header class="page-header">
        <h1 class="page-title">Operational History</h1>
        <p class="page-desc">Comprehensive transaction log per animal.</p>
    </header>

    <div class="filter-card">
        <div class="filter-grid" style="margin-bottom: 1rem;">
            <div class="form-group">
                <label>Status Filter</label>
                <select id="status_filter" class="form-select" onchange="resetCascades()">
                    <option value="Active" selected>Active Only (Default)</option>
                    <option value="Inactive">Inactive / Sold / Deceased</option>
                    <option value="All">All Animals</option>
                </select>
            </div>
        </div>

        <div class="filter-grid">
            <div class="form-group">
                <label>1. Location</label>
                <select id="loc_id" class="form-select" onchange="loadBuildings()" <?php echo ($USER_LOCATION_ != 1000) ? 'style="background-color: #0f172a; pointer-events: none; color: #94a3b8;"' : ''; ?>>
                    <?php if($USER_LOCATION_ == 1000): ?>
                        <option value="">-- Select --</option>
                    <?php endif; ?>
                    <?php foreach($locations as $l): ?>
                        <option value="<?= $l['LOCATION_ID'] ?>" <?php echo ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($l['LOCATION_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>2. Building</label>
                <select id="bldg_id" class="form-select" onchange="loadPens()" disabled>
                    <option value="">-- Select --</option>
                </select>
            </div>
            <div class="form-group">
                <label>3. Pen</label>
                <select id="pen_id" class="form-select" onchange="loadAnimals()" disabled>
                    <option value="">-- Select --</option>
                </select>
            </div>
            <div class="form-group">
                <label>4. Animal Tag</label>
                <select id="animal_select" class="form-select" onchange="goToAnimal(this.value)" disabled>
                    <option value="">-- Select --</option>
                </select>
            </div>
        </div>

        <div class="divider-text">OR DIRECT SEARCH</div>

        <div style="display: flex; gap: 10px;">
            <input type="text" id="direct_search" class="form-input" placeholder="Enter Tag No (e.g. A001)...">
            <button class="btn-go" style="width: auto; padding: 0 2rem;" onclick="performDirectSearch()">SEARCH</button>
        </div>
    </div>

    <?php if ($animal_info): ?>
    <div class="profile-card">
        <div class="profile-main">
            <h2><?= htmlspecialchars($animal_info['TAG_NO']) ?></h2>
            <div class="profile-sub">
                <?= htmlspecialchars($animal_info['ANIMAL_TYPE_NAME']) ?> • <?= htmlspecialchars($animal_info['BREED_NAME']) ?> • <?= $animal_info['SEX'] ?>
                <br>
                <?= htmlspecialchars($animal_info['LOCATION_NAME']) ?> > <?= htmlspecialchars($animal_info['BUILDING_NAME']) ?> > <?= htmlspecialchars($animal_info['PEN_NAME']) ?>
            </div>
        </div>
        <div class="profile-stats">
            <div class="total-cost-label">Total Operational Cost</div>
            <div class="total-cost-val">₱<?= number_format($total_cost, 2) ?></div>
            <div style="color: #64748b; font-size: 0.9rem;">Status: <?= $animal_info['CURRENT_STATUS'] ?></div>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Type</th>
                    <th>Item / Description</th>
                    <th>Qty</th>
                    <th>Remarks</th>
                    <th>Cost</th>
                    <th style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="7" class="empty-state">No operational history found for this animal.</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $row): 
                        // Formats string to lowercase and replaces spaces with dashes (e.g. 'Cost Transfer' -> 'type-cost-transfer')
                        $badgeClass = 'type-' . str_replace(' ', '-', strtolower($row['LOG_TYPE']));
                        
                        $isTransfer = $row['LOG_TYPE'] === 'Cost Transfer';
                        $costPrefix = $isTransfer ? '-' : '';
                        $costColor = $isTransfer ? '#f87171' : '#fbbf24'; // Red if deduction, Gold if addition
                    ?>
                    <tr>
                        <td data-label="Date & Time"><?= date('M d, Y h:i A', strtotime($row['LOG_DATE'])) ?></td>
                        <td data-label="Type"><span class="type-badge <?= $badgeClass ?>"><?= $row['LOG_TYPE'] ?></span></td>
                        <td data-label="Item / Desc"><?= htmlspecialchars($row['ITEM_NAME'] ?? '-') ?></td>
                        <td data-label="Qty"><?= number_format($row['QTY'], 2) ?> <?= $row['UNIT'] ?></td>
                        <td data-label="Remarks" style="color:#94a3b8; font-size:0.85rem;"><?= htmlspecialchars($row['REMARKS'] ?? '-') ?></td>
                        <td data-label="Cost" class="cost-val" style="color: <?= $costColor ?>;"><?= $costPrefix ?>₱<?= number_format($row['COST'], 2) ?></td>
                        <td data-label="Action" style="text-align: center;">
                            <?php if ($isTransfer): ?>
                                <button class="btn-view-piglets" onclick="viewTransferDetails(<?= $row['REF_ID'] ?>)">View Piglets</button>
                            <?php else: ?>
                                <span style="color:#475569;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state">
            <h3>Select an animal to view history</h3>
            <p>Use the filters above or search by tag number.</p>
        </div>
    <?php endif; ?>

</div>

<div id="transferModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Piglets (Cost Transfer)</h2>
            <button class="btn-close" onclick="closeTransferModal()">&times;</button>
        </div>
        <div class="modal-body">
            <table class="data-table" style="margin: 0; border-radius: 0; border: none;">
                <thead>
                    <tr>
                        <th>Piglet Tag No</th>
                        <th>Sex</th>
                        <th>Status</th>
                        <th style="text-align:right;">Sow Share</th>
                        <th style="text-align:right;">Boar Share</th>
                        <th style="text-align:right;">Total Added</th>
                    </tr>
                </thead>
                <tbody id="piglet-list">
                    </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // --- API & UTILS ---
    const API_URL = window.location.pathname.split("/").pop();
    const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

    document.addEventListener('DOMContentLoaded', () => {
        // Auto-load buildings if user is restricted to a location
        if (USER_LOCATION != 1000) {
            loadBuildings();
        }
    });

    async function fetchJson(params) {
        try { return await (await fetch(`${API_URL}${params}`)).json(); } 
        catch(e) { return []; }
    }

    function resetSelect(id) {
        const el = document.getElementById(id);
        el.innerHTML = '<option value="">-- Select --</option>';
        el.disabled = true;
    }

    function fillSelect(id, data, valKey, txtKey) {
        const el = document.getElementById(id);
        el.innerHTML = '<option value="">-- Select --</option>';
        data.forEach(item => el.innerHTML += `<option value="${item[valKey]}">${item[txtKey]}</option>`);
        el.disabled = false;
    }

    // --- CASCADING LOGIC ---
    function resetCascades() {
        if (USER_LOCATION == 1000) {
            document.getElementById('loc_id').value = "";
            resetSelect('bldg_id');
        } else {
            loadBuildings(); // Restricted users just refresh from building level
        }
        resetSelect('pen_id');
        resetSelect('animal_select');
    }

    async function loadBuildings() {
        const id = document.getElementById('loc_id').value;
        resetSelect('bldg_id'); resetSelect('pen_id'); resetSelect('animal_select');
        if(!id) return;
        const data = await fetchJson(`?action=get_buildings&loc_id=${id}`);
        fillSelect('bldg_id', data, 'BUILDING_ID', 'BUILDING_NAME');
    }

    async function loadPens() {
        const id = document.getElementById('bldg_id').value;
        resetSelect('pen_id'); resetSelect('animal_select');
        if(!id) return;
        const data = await fetchJson(`?action=get_pens&bldg_id=${id}`);
        fillSelect('pen_id', data, 'PEN_ID', 'PEN_NAME');
    }

    async function loadAnimals() {
        const id = document.getElementById('pen_id').value;
        const status = document.getElementById('status_filter').value;
        resetSelect('animal_select');
        if(!id) return;
        
        // Pass status filter to AJAX
        const data = await fetchJson(`?action=get_animals&pen_id=${id}&status_filter=${status}`);
        fillSelect('animal_select', data, 'ANIMAL_ID', 'TAG_NO');
    }

    // --- NAVIGATION ---
    function goToAnimal(id) {
        if(id) window.location.href = `?animal_id=${id}`;
    }

    async function performDirectSearch() {
        const tag = document.getElementById('direct_search').value.trim();
        const status = document.getElementById('status_filter').value;
        if(!tag) return;

        const data = await fetchJson(`?action=search_tag&query=${encodeURIComponent(tag)}&status_filter=${status}`);
        
        if(data.length === 1) {
            goToAnimal(data[0].ANIMAL_ID);
        } else if (data.length > 1) {
            alert("Multiple animals found. Please be more specific.");
        } else {
            alert("Animal not found in your location (Check your Status Filter).");
        }
    }

    // Enter key support for search
    document.getElementById('direct_search').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') performDirectSearch();
    });

    // --- MODAL LOGIC FOR PIGLETS ---
    async function viewTransferDetails(transferId) {
        const modal = document.getElementById('transferModal');
        const tbody = document.getElementById('piglet-list');
        
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:2rem; color:#94a3b8;">Loading piglets...</td></tr>';
        modal.classList.add('show');

        const data = await fetchJson(`?action=get_transfer_details&transfer_id=${transferId}`);
        
        if (data.success && data.piglets && data.piglets.length > 0) {
            let html = '';
            
            const sowShare = parseFloat(data.sow_share || 0).toFixed(2);
            const boarShare = parseFloat(data.boar_share || 0).toFixed(2);
            const totalAdded = parseFloat(data.cost_per_head || 0).toFixed(2);

            data.piglets.forEach(p => {
                html += `
                    <tr>
                        <td data-label="Tag No" style="font-weight:bold; color:#fff;">${p.TAG_NO}</td>
                        <td data-label="Sex">${p.SEX}</td>
                        <td data-label="Status">${p.CURRENT_STATUS}</td>
                        <td data-label="Sow Share" style="text-align:right; font-family:monospace; color:#f472b6;">+₱${sowShare}</td>
                        <td data-label="Boar Share" style="text-align:right; font-family:monospace; color:#60a5fa;">+₱${boarShare}</td>
                        <td data-label="Total Added" style="text-align:right; font-family:monospace; color:#4ade80; font-weight:bold;">+₱${totalAdded}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:2rem; color:#f87171;">No piglet data found for this transfer.</td></tr>';
        }
    }

    function closeTransferModal() {
        document.getElementById('transferModal').classList.remove('show');
    }

    // Close modal on outside click
    window.onclick = function(e) {
        if (e.target.classList.contains('modal')) {
            closeTransferModal();
        }
    }
</script>

</body>
</html>