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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"> 
    <title>Operational History | FarmPro</title>
    
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
            --border-active:  rgba(239,68,68,0.5); /* Red Accent */
            
            --red:            #ef4444; --red-dim:        rgba(239,68,68,0.12); --red-glow:       rgba(239,68,68,0.25);
            --emerald:        #10b981; --emerald-dim:    rgba(16,185,129,0.12);
            --blue:           #3b82f6; --blue-dim:       rgba(59,130,246,0.12);
            --amber:          #f59e0b; --amber-dim:      rgba(245,158,11,0.12);
            --purple:         #a855f7; --purple-dim:     rgba(168,85,247,0.12);
            --cyan:           #06b6d4; --cyan-dim:       rgba(6,182,212,0.12);
            --orange:         #f97316; --orange-dim:     rgba(249,115,22,0.12);
            --pink:           #ec4899; --pink-dim:       rgba(236,72,153,0.12);
            
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(239,68,68,0.06) 0%, transparent 60%);
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
            color: var(--red); background: var(--red-dim); border: 1px solid rgba(239,68,68,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { margin-bottom: 2.5rem; }
        .page-header h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; letter-spacing: -0.02em;}
        .page-header h1 span { background: linear-gradient(135deg, var(--red), #991b1b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .page-header p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }

        /* ─── FILTERS ─── */
        .filter-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
        }
        
        .filter-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 1.25rem; align-items: end; margin-bottom: 1rem;
        }
        
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        
        .form-select, .form-input {
            width: 100%; padding: 12px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition); box-sizing: border-box;
        }
        .form-select {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-input:focus, .form-select:focus { border-color: var(--red); box-shadow: 0 0 0 3px var(--red-glow); background: var(--bg-hover); }
        .form-select:disabled, .form-input:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.02); }

        .search-row { display: flex; gap: 10px; align-items: stretch; margin-top: 1rem;}
        .btn-go {
            background: var(--red); color: #fff; border: none; padding: 0 2rem; 
            border-radius: var(--radius-md); cursor: pointer; font-weight: 700; font-family: var(--font);
            transition: var(--transition); font-size: 0.95rem; white-space: nowrap; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-go:hover { background: #b91c1c; box-shadow: 0 4px 15px var(--red-glow); transform: translateY(-1px); }

        /* Divider */
        .divider-text { text-align: center; color: var(--text-muted); font-size: 0.75rem; font-weight: 700; margin: 1.5rem 0; position: relative; text-transform: uppercase; letter-spacing: 0.1em;}
        .divider-text::before, .divider-text::after { content: ""; position: absolute; top: 50%; width: 38%; height: 1px; background: var(--border); }
        .divider-text::before { left: 0; } .divider-text::after { right: 0; }

        /* ─── ANIMAL PROFILE CARD ─── */
        .profile-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 2rem; margin-bottom: 2rem;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: var(--shadow-md); position: relative; overflow: hidden;
            flex-wrap: wrap; gap: 1.5rem;
        }
        .profile-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%;
            background: linear-gradient(180deg, var(--red), #991b1b);
        }
        .profile-main h2 { font-size: 2.2rem; margin: 0 0 0.5rem 0; color: #fff; font-family: var(--font-mono); letter-spacing: -0.02em;}
        .profile-sub { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5; }
        .profile-sub i { color: var(--red); margin-right: 4px; opacity: 0.8; width: 16px; text-align: center;}
        
        .profile-stats { text-align: right; background: var(--bg-elevated); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border);}
        .total-cost-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; margin-bottom: 0.25rem;}
        .total-cost-val { font-size: 2rem; font-weight: 800; color: var(--amber); font-family: var(--font-mono); line-height: 1.1; margin-bottom: 0.75rem;}
        .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.3); border-radius: 99px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;}
        .status-badge.sold { background: var(--blue-dim); color: var(--blue); border-color: rgba(59,130,246,0.3); }
        .status-badge.deceased { background: rgba(255,255,255,0.05); color: var(--text-muted); border-color: var(--border); }

        /* ─── DATA TABLE ─── */
        .table-wrapper { background: var(--bg-surface); border-radius: var(--radius-xl); border: 1px solid var(--border); overflow: hidden; box-shadow: var(--shadow-md); }
        .data-table { width: 100%; border-collapse: collapse; min-width: 900px;}
        .data-table th {
            text-align: left; padding: 14px 16px; background: var(--bg-elevated);
            color: var(--text-muted); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border);
        }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-primary); vertical-align: middle;}
        .data-table tr:hover { background: rgba(255,255,255,0.02); }

        /* Dynamic Row Badges */
        .type-badge { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 6px;}
        .type-feeding { background: var(--orange-dim); color: var(--orange); }
        .type-medication { background: var(--blue-dim); color: var(--blue); }
        .type-vaccination { background: var(--purple-dim); color: var(--purple); }
        .type-vitamins { background: var(--emerald-dim); color: var(--emerald); }
        .type-checkup { background: var(--cyan-dim); color: var(--cyan); }
        .type-cost-transfer { background: var(--pink-dim); color: var(--pink); }

        .td-mono { font-family: var(--font-mono); font-size: 0.9rem; }
        .cost-val { font-family: var(--font-mono); font-weight: 700; font-size: 1rem; color: var(--amber); }
        .cost-neg { color: var(--red); }
        
        .empty-state { text-align: center; padding: 4rem; color: var(--text-muted); font-style: italic; }

        .btn-view-piglets {
            background: var(--pink-dim); border: 1px solid rgba(236,72,153,0.3); color: var(--pink);
            padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer;
            transition: all var(--transition); white-space: nowrap; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-view-piglets:hover { background: var(--pink); color: #000; box-shadow: 0 4px 12px var(--pink-glow); transform: translateY(-1px); }

        /* ─── MODAL ─── */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; box-sizing: border-box; }
        .modal.show { display: flex; }
        .modal-content { background: var(--bg-surface); border-radius: var(--radius-xl); width: 100%; max-width: 650px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); animation: modalZoom 0.2s ease-out;}
        @keyframes modalZoom { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--bg-elevated);}
        .modal-header h2 { margin: 0; color: #fff; font-size: 1.25rem; display: flex; align-items: center; gap: 10px;}
        .modal-header h2 i { color: var(--pink); }
        .modal-body { padding: 0; max-height: 60vh; overflow-y: auto; }
        .btn-close { background: transparent; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; transition: color var(--transition); }
        .btn-close:hover { color: var(--red); }

        /* Toast Notifications */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out; display: flex; align-items: center; gap: 8px;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* --- MOBILE RESPONSIVE --- */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .profile-card { flex-direction: column; align-items: stretch; padding: 1.5rem;}
            .profile-stats { text-align: left; }
            .search-row { flex-direction: column; }
            .btn-go { width: 100%; padding: 12px; justify-content: center;}

            /* Table to Card View */
            .data-table thead { display: none; }
            .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; box-sizing: border-box; }
            .data-table tr { background: rgba(30, 41, 59, 0.4); border: 1px solid var(--border); border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem; }
            .data-table td { padding: 0.6rem 0; text-align: right; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed rgba(255,255,255,0.05); }
            .data-table td:last-child { border-bottom: none; padding-top: 1rem; justify-content: flex-end;}
            .data-table td::before { content: attr(data-label); font-weight: 700; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; margin-right: 1rem; text-align: left;}
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
        <span class="page-badge"><i class="fa-solid fa-clock-rotate-left"></i> Audit Trail</span>
    </div>

    <header class="page-header">
        <h1>Operational <span>History</span></h1>
        <p>Comprehensive transaction log and cost analysis per animal.</p>
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
                <select id="loc_id" class="form-select" onchange="loadBuildings()" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
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

        <div class="divider-text">Or Direct Search</div>

        <div class="search-row">
            <input type="text" id="direct_search" class="form-input" placeholder="Enter precise Tag No (e.g. A001)...">
            <button class="btn-go" onclick="performDirectSearch()"><i class="fa-solid fa-magnifying-glass"></i> Find Record</button>
        </div>
    </div>

    <?php if ($animal_info): ?>
    <div class="profile-card">
        <div class="profile-main">
            <h2><?= htmlspecialchars($animal_info['TAG_NO']) ?></h2>
            <div class="profile-sub">
                <div><i class="fa-solid fa-dna"></i> <?= htmlspecialchars($animal_info['ANIMAL_TYPE_NAME']) ?> &bull; <?= htmlspecialchars($animal_info['BREED_NAME']) ?> &bull; <?= $animal_info['SEX'] === 'M' ? 'Male' : 'Female' ?></div>
                <div style="margin-top: 6px;"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($animal_info['LOCATION_NAME']) ?> &nbsp;&gt;&nbsp; <?= htmlspecialchars($animal_info['BUILDING_NAME']) ?> &nbsp;&gt;&nbsp; <?= htmlspecialchars($animal_info['PEN_NAME']) ?></div>
            </div>
        </div>
        <div class="profile-stats">
            <div class="total-cost-label">Total Operational Cost</div>
            <div class="total-cost-val">₱<?= number_format($total_cost, 2) ?></div>
            
            <?php 
                $statBadge = 'status-badge';
                if ($animal_info['CURRENT_STATUS'] == 'Sold') $statBadge .= ' sold';
                if ($animal_info['CURRENT_STATUS'] == 'Deceased') $statBadge .= ' deceased';
            ?>
            <div class="<?= $statBadge ?>">
                <?php if ($animal_info['CURRENT_STATUS'] == 'Active' || $animal_info['CURRENT_STATUS'] == 'Sold' || $animal_info['CURRENT_STATUS'] == 'Deceased'): ?>
                    <i class="fa-solid fa-circle-dot"></i> 
                <?php else: ?>
                    <i class="fa-solid fa-tag"></i> 
                <?php endif; ?>
                <?= $animal_info['CURRENT_STATUS'] ?>
            </div>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Log Type</th>
                    <th>Item / Description</th>
                    <th>Quantity</th>
                    <th>Remarks</th>
                    <th>Cost Impact</th>
                    <th style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="7" class="empty-state"><i class="fa-solid fa-ghost" style="font-size: 2rem; margin-bottom:1rem; display:block; opacity: 0.5;"></i>No operational history found for this animal.</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $row): 
                        // Formats string to lowercase and replaces spaces with dashes (e.g. 'Cost Transfer' -> 'type-cost-transfer')
                        $badgeClass = 'type-' . str_replace(' ', '-', strtolower($row['LOG_TYPE']));
                        
                        $isTransfer = $row['LOG_TYPE'] === 'Cost Transfer';
                        $costPrefix = $isTransfer ? '-' : '+';
                        $costClass = $isTransfer ? 'cost-neg' : ''; // Red if deduction, Gold if addition
                        
                        // Icons for badges
                        $typeIcon = '';
                        switch($row['LOG_TYPE']) {
                            case 'Feeding': $typeIcon = '<i class="fa-solid fa-wheat-awn"></i> '; break;
                            case 'Medication': $typeIcon = '<i class="fa-solid fa-pills"></i> '; break;
                            case 'Vaccination': $typeIcon = '<i class="fa-solid fa-syringe"></i> '; break;
                            case 'Vitamins': $typeIcon = '<i class="fa-solid fa-flask"></i> '; break;
                            case 'Checkup': $typeIcon = '<i class="fa-solid fa-stethoscope"></i> '; break;
                            case 'Cost Transfer': $typeIcon = '<i class="fa-solid fa-money-bill-transfer"></i> '; break;
                        }
                    ?>
                    <tr>
                        <td data-label="Timestamp" class="td-mono"><?= date('M d, Y h:i A', strtotime($row['LOG_DATE'])) ?></td>
                        <td data-label="Log Type"><span class="type-badge <?= $badgeClass ?>"><?= $typeIcon . $row['LOG_TYPE'] ?></span></td>
                        <td data-label="Item / Desc" style="font-weight:600;"><?= htmlspecialchars($row['ITEM_NAME'] ?? '-') ?></td>
                        <td data-label="Quantity" class="td-mono"><?= number_format($row['QTY'], 2) ?> <span style="color:var(--text-muted);"><?= $row['UNIT'] ?></span></td>
                        <td data-label="Remarks" style="color:var(--text-muted); font-size:0.85rem;"><?= htmlspecialchars($row['REMARKS'] ?? '-') ?></td>
                        <td data-label="Cost Impact" class="cost-val <?= $costClass ?>"><?= $costPrefix ?>₱<?= number_format($row['COST'], 2) ?></td>
                        <td data-label="Action" style="text-align: center;">
                            <?php if ($isTransfer): ?>
                                <button class="btn-view-piglets" onclick="viewTransferDetails(<?= $row['REF_ID'] ?>)">
                                    <i class="fa-solid fa-magnifying-glass"></i> View Piglets
                                </button>
                            <?php else: ?>
                                <span style="color:var(--text-muted); opacity: 0.3;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state" style="background: var(--bg-surface); border: 1px dashed var(--border); border-radius: var(--radius-xl);">
            <i class="fa-solid fa-arrow-up" style="font-size: 2.5rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
            <h3 style="margin:0 0 0.5rem 0; color: #fff;">Select an animal to view history</h3>
            <p style="margin:0;">Use the cascade filters above or perform a direct search by tag number.</p>
        </div>
    <?php endif; ?>

</div>

<div id="transferModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fa-solid fa-piggy-bank"></i> Piglet Cost Allocation</h2>
            <button class="btn-close" onclick="closeTransferModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <table class="data-table" style="margin: 0; border-radius: 0; border: none; min-width: 100%;">
                <thead style="position: sticky; top: 0;">
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
        if (USER_LOCATION != 1000) {
            loadBuildings();
        }
    });

    async function fetchJson(params) {
        try { return await (await fetch(`${API_URL}${params}`)).json(); } 
        catch(e) { return []; }
    }

    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--emerald)'}`;
        t.innerHTML = `${type === 'error' ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-check"></i>'} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 3500);
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
        
        const el = document.getElementById('bldg_id');
        el.innerHTML = '<option value="">Loading...</option>';
        
        const data = await fetchJson(`?action=get_buildings&loc_id=${id}`);
        fillSelect('bldg_id', data, 'BUILDING_ID', 'BUILDING_NAME');
    }

    async function loadPens() {
        const id = document.getElementById('bldg_id').value;
        resetSelect('pen_id'); resetSelect('animal_select');
        if(!id) return;
        
        const el = document.getElementById('pen_id');
        el.innerHTML = '<option value="">Loading...</option>';

        const data = await fetchJson(`?action=get_pens&bldg_id=${id}`);
        fillSelect('pen_id', data, 'PEN_ID', 'PEN_NAME');
    }

    async function loadAnimals() {
        const id = document.getElementById('pen_id').value;
        const status = document.getElementById('status_filter').value;
        resetSelect('animal_select');
        if(!id) return;
        
        const el = document.getElementById('animal_select');
        el.innerHTML = '<option value="">Loading...</option>';
        
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
            showToast("Multiple animals found. Please be more specific.", "warning");
        } else {
            showToast("Animal not found in your location (Check your Status Filter).", "error");
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
        
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:3rem; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin me-2"></i> Loading piglets...</td></tr>';
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
                        <td data-label="Tag No" style="font-weight:700; color:#fff; font-family:var(--font-mono);">${p.TAG_NO}</td>
                        <td data-label="Sex">${p.SEX === 'M' ? '<i class="fa-solid fa-mars" style="color:var(--blue)"></i>' : '<i class="fa-solid fa-venus" style="color:var(--pink)"></i>'} ${p.SEX}</td>
                        <td data-label="Status"><span style="color:var(--text-secondary); font-size:0.85rem;">${p.CURRENT_STATUS}</span></td>
                        <td data-label="Sow Share" style="text-align:right; font-family:var(--font-mono); color:var(--pink);">+₱${sowShare}</td>
                        <td data-label="Boar Share" style="text-align:right; font-family:var(--font-mono); color:var(--blue);">+₱${boarShare}</td>
                        <td data-label="Total Added" style="text-align:right; font-family:var(--font-mono); color:var(--emerald); font-weight:700;">+₱${totalAdded}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:3rem; color:var(--red);"><i class="fa-solid fa-ghost display-block margin-bottom"></i> No piglet data found for this transfer.</td></tr>';
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