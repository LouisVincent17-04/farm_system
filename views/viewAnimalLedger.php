<?php
// views/viewAnimalLedger.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('animal_report');
include '../common/navbar.php';
include '../common/chat_support.php';

$animal_id = $_GET['id'] ?? 0;

try {
    if (!$animal_id) throw new Exception("No Animal ID provided.");

    // --- 1. Get Animal Profile Info ---
    $profile_sql = "SELECT 
                        ar.*,
                        at.ANIMAL_TYPE_NAME, b.BREED_NAME, ac.CLASS_ID, ac.STAGE_NAME,
                        l.LOCATION_NAME, bld.BUILDING_NAME, p.PEN_NAME,
                        m.TAG_NO as MOTHER_TAG, f.TAG_NO as FATHER_TAG,
                        DATEDIFF(NOW(), ar.BIRTH_DATE) AS DAYS_OLD
                    FROM ANIMAL_RECORDS ar
                    LEFT JOIN ANIMAL_TYPE at ON ar.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
                    LEFT JOIN BREEDS b ON ar.BREED_ID = b.BREED_ID
                    LEFT JOIN ANIMAL_CLASSIFICATIONS ac ON ar.CLASS_ID = ac.CLASS_ID
                    LEFT JOIN LOCATIONS l ON ar.LOCATION_ID = l.LOCATION_ID
                    LEFT JOIN BUILDINGS bld ON ar.BUILDING_ID = bld.BUILDING_ID
                    LEFT JOIN PENS p ON ar.PEN_ID = p.PEN_ID
                    LEFT JOIN ANIMAL_RECORDS m ON ar.MOTHER_ID = m.ANIMAL_ID
                    LEFT JOIN ANIMAL_RECORDS f ON ar.FATHER_ID = f.ANIMAL_ID
                    WHERE ar.ANIMAL_ID = ?";
    $stmt = $conn->prepare($profile_sql);
    $stmt->execute([$animal_id]);
    $animal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$animal) throw new Exception("Animal not found in records.");

    // --- 2. Build the Combined Ledger ---
    $ledger = [];

    // A. Feedings (Table: feed_transactions)
    try {
        $feed_sql = "SELECT 
                        ft.TRANSACTION_DATE AS txn_date, 
                        'Feeding' AS type, 
                        CONCAT('Consumed ', ft.QUANTITY_KG, ' kg of ', f.FEED_NAME) AS details,
                        ft.TRANSACTION_COST AS cost,
                        ft.REMARKS AS remarks
                     FROM feed_transactions ft
                     LEFT JOIN feeds f ON ft.FEED_ID = f.FEED_ID
                     WHERE ft.ANIMAL_ID = ?";
        $stmt = $conn->prepare($feed_sql);
        $stmt->execute([$animal_id]);
        $ledger = array_merge($ledger, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e) {}

    // B. Medications/Treatments (Table: treatment_transactions)
    try {
        $med_sql = "SELECT 
                        tt.TRANSACTION_DATE AS txn_date, 
                        'Medication' AS type, 
                        CONCAT('Administered ', IFNULL(m.SUPPLY_NAME, 'Medicine'), ' - ', IFNULL(tt.DOSAGE, 'N/A')) AS details,
                        tt.TOTAL_COST AS cost,
                        tt.REMARKS AS remarks
                    FROM treatment_transactions tt
                    LEFT JOIN medicines m ON tt.ITEM_ID = m.SUPPLY_ID
                    WHERE tt.ANIMAL_ID = ?";
        $stmt = $conn->prepare($med_sql);
        $stmt->execute([$animal_id]);
        $ledger = array_merge($ledger, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e) {}

    // C. Vaccinations (Table: vaccination_records)
    try {
        $vax_sql = "SELECT 
                        vr.VACCINATION_DATE AS txn_date, 
                        'Vaccination' AS type, 
                        CONCAT('Administered ', IFNULL(v.SUPPLY_NAME, 'Vaccine')) AS details,
                        (IFNULL(vr.VACCINATION_COST, 0) + IFNULL(vr.VACCINE_COST, 0)) AS cost,
                        vr.REMARKS AS remarks
                    FROM vaccination_records vr
                    LEFT JOIN vaccines v ON vr.ITEM_ID = v.SUPPLY_ID
                    WHERE vr.ANIMAL_ID = ?";
        $stmt = $conn->prepare($vax_sql);
        $stmt->execute([$animal_id]);
        $ledger = array_merge($ledger, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e) {}

    // D. Vitamins & Supplements (Table: vitamins_supplements_transactions)
    try {
        $vit_sql = "SELECT 
                        vst.TRANSACTION_DATE AS txn_date, 
                        'Supplement' AS type, 
                        CONCAT('Given ', IFNULL(vs.SUPPLY_NAME, 'Vitamins/Supplements'), ' - ', IFNULL(vst.DOSAGE, 'N/A')) AS details,
                        vst.TOTAL_COST AS cost,
                        vst.REMARKS AS remarks
                    FROM vitamins_supplements_transactions vst
                    LEFT JOIN vitamins_supplements vs ON vst.ITEM_ID = vs.SUPPLY_ID
                    WHERE vst.ANIMAL_ID = ?";
        $stmt = $conn->prepare($vit_sql);
        $stmt->execute([$animal_id]);
        $ledger = array_merge($ledger, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e) {}

    // E. Health Checkups (Table: check_ups)
    try {
        $chk_sql = "SELECT 
                        CHECKUP_DATE AS txn_date, 
                        'Checkup' AS type, 
                        CONCAT('Checkup by ', IFNULL(VET_NAME, 'Vet')) AS details,
                        COST AS cost,
                        REMARKS AS remarks
                    FROM check_ups
                    WHERE ANIMAL_ID = ?";
        $stmt = $conn->prepare($chk_sql);
        $stmt->execute([$animal_id]);
        $ledger = array_merge($ledger, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e) {}

    // F. Cost Transfers (Table: cost_transfers)
    try {
        if($animal['CLASS_ID'] == 7) {
            $ct_sql = "SELECT 
                        TRANSFER_DATE AS txn_date, 
                        'Cost Transfer' AS type, 
                        CONCAT('Transferred to ', PIGLET_COUNT, ' piglets (₱', COST_PER_HEAD, '/head)') AS details,
                        (BOAR_COST * -1) AS cost,
                        'Cost reset/transfer for birthing cycle' AS remarks
                    FROM cost_transfers
                    WHERE BOAR_ID = ?";
            $stmt = $conn->prepare($ct_sql);
            $stmt->execute([$animal_id]);
            $ledger = array_merge($ledger, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        else if($animal['CLASS_ID'] == 8) {
            $ct_sql = "SELECT 
                        TRANSFER_DATE AS txn_date, 
                        'Cost Transfer' AS type, 
                        CONCAT('Transferred to ', PIGLET_COUNT, ' piglets (₱', COST_PER_HEAD, '/head)') AS details,
                        (SOW_COST * -1) AS cost,
                        'Cost reset/transfer for birthing cycle' AS remarks
                    FROM cost_transfers
                    WHERE SOW_ID = ?";
            $stmt = $conn->prepare($ct_sql);
            $stmt->execute([$animal_id]);
            $ledger = array_merge($ledger, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
    } catch(Exception $e) {}

    // G. Miscellaneous Fees (Table: animal_misc_fees)
    try {
        $misc_sql = "SELECT 
                        CREATED_AT AS txn_date, 
                        'Misc Fee' AS type, 
                        FEE_DESCRIPTION AS details,
                        AMOUNT AS cost,
                        'Manual Entry' AS remarks
                    FROM animal_misc_fees
                    WHERE ANIMAL_ID = ?";
        $stmt = $conn->prepare($misc_sql);
        $stmt->execute([$animal_id]);
        $ledger = array_merge($ledger, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e) {}

    // 3. Sort Ledger by Date Descending
    usort($ledger, function($a, $b) {
        return strtotime($b['txn_date']) - strtotime($a['txn_date']);
    });

} catch (Exception $e) {
    $error = $e->getMessage();
}

$net_cost = $animal['ACQUISITION_COST'] + array_sum(array_column($ledger, 'cost'));

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Animal Ledger | Tag <?= htmlspecialchars($animal['TAG_NO'] ?? '') ?></title>

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
            
            --slate:          #94a3b8;
            --slate-dim:      rgba(148,163,184,0.15);
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.15);
            --amber:          #f59e0b;
            --amber-dim:      rgba(245,158,11,0.15);
            --red:            #ef4444;
            --red-dim:        rgba(239,68,68,0.15);
            --blue:           #3b82f6;
            --blue-dim:       rgba(59,130,246,0.15);
            --purple:         #a855f7;
            --purple-dim:     rgba(168,85,247,0.15);
            --pink:           #ec4899;
            --pink-dim:       rgba(236,72,153,0.15);
            
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
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500;
            padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); transition: all var(--transition);
        }
        .back-link:hover { color: #fff; border-color: rgba(255,255,255,0.2); background: var(--bg-hover); }

        /* ─── ERROR STATE ─── */
        .error-box { background: var(--red-dim); border: 1px solid var(--red); padding: 2rem; border-radius: var(--radius-lg); color: var(--red); text-align: center; margin-top: 2rem;}
        .error-box h3 { margin-top: 1rem; font-weight: 600;}

        /* ─── PROFILE HERO CARD ─── */
        .profile-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden; margin-bottom: 2.5rem;
            box-shadow: var(--shadow-md); position: relative;
        }
        .profile-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--emerald), var(--blue)); }

        .profile-header {
            padding: 2rem; display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid var(--border); background: rgba(255,255,255,0.02);
            flex-wrap: wrap; gap: 1rem;
        }
        .ph-left { display: flex; align-items: center; gap: 1.5rem; }
        .ph-icon { width: 64px; height: 64px; border-radius: 16px; background: var(--emerald-dim); color: var(--emerald); display: flex; align-items: center; justify-content: center; font-size: 2rem; border: 1px solid rgba(16,185,129,0.3);}
        
        .tag-no { font-size: 2.2rem; font-weight: 800; font-family: var(--font-mono); color: #fff; margin: 0 0 0.25rem 0; line-height: 1; letter-spacing: -0.02em;}
        .tag-sub { color: var(--text-secondary); font-size: 1rem; font-weight: 500; display: flex; align-items: center; gap: 8px;}

        .status-badge {
            padding: 8px 16px; border-radius: 99px; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 6px;
        }
        .s-active { background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.3); }
        .s-sold { background: var(--amber-dim); color: var(--amber); border: 1px solid rgba(245,158,11,0.3); }
        .s-dead { background: var(--red-dim); color: var(--red); border: 1px solid rgba(239,68,68,0.3); }

        /* ─── PROFILE STATS GRID ─── */
        .profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1px; background: var(--border); }
        .profile-item { background: var(--bg-surface); padding: 1.5rem; display: flex; flex-direction: column; justify-content: center;}
        
        .p-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 6px;}
        .p-value { font-size: 1.25rem; color: #fff; font-weight: 700; font-family: var(--font); margin-bottom: 0.25rem; line-height: 1.2;}
        .p-sub { font-size: 0.85rem; color: var(--text-secondary); font-weight: 500;}

        /* Value Colors */
        .val-blue { color: var(--blue); }
        .val-amber { color: var(--amber); font-family: var(--font-mono); }
        .val-emerald { color: var(--emerald); font-family: var(--font-mono); }

        /* ─── LEDGER TABLE ─── */
        .section-title { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 10px;}

        .table-section { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-md);}
        .table-scroll-wrapper { overflow-x: auto; }
        .table-scroll-wrapper::-webkit-scrollbar { height: 8px; }
        .table-scroll-wrapper::-webkit-scrollbar-track { background: var(--bg-surface); }
        .table-scroll-wrapper::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }

        .data-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .data-table th { background: var(--bg-elevated); color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 16px; text-align: left; font-weight: 700; border-bottom: 1px solid var(--border); }
        .data-table td { padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-primary); vertical-align: top; font-size: 0.95rem; }
        .data-table tr:hover { background: rgba(255,255,255,0.01); }

        /* Ledger specific cells */
        .td-date { color: var(--text-secondary); font-family: var(--font-mono); font-size: 0.9rem; white-space: nowrap;}
        .td-details { font-weight: 600; color: #fff; line-height: 1.4;}
        .td-remarks { color: var(--text-secondary); font-size: 0.85rem; font-style: italic; }
        .td-cost { text-align: right; font-family: var(--font-mono); font-weight: 700; font-size: 1.05rem; white-space: nowrap;}
        
        .cost-add { color: var(--amber); }
        .cost-sub { color: var(--emerald); }
        .cost-zero { color: var(--text-muted); }

        /* Type Badges */
        .badge-type { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; border: 1px solid transparent;}
        .t-feed { background: var(--amber-dim); color: var(--amber); border-color: rgba(245,158,11,0.3); }
        .t-med { background: var(--red-dim); color: var(--red); border-color: rgba(239,68,68,0.3); }
        .t-vax { background: var(--blue-dim); color: var(--blue); border-color: rgba(59,130,246,0.3); }
        .t-vit { background: var(--emerald-dim); color: var(--emerald); border-color: rgba(16,185,129,0.3); }
        .t-chk { background: var(--purple-dim); color: var(--purple); border-color: rgba(168,85,247,0.3); }
        .t-trans { background: var(--pink-dim); color: var(--pink); border-color: rgba(236,72,153,0.3); }
        .t-misc { background: var(--slate-dim); color: var(--text-primary); border-color: rgba(148,163,184,0.3); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); font-style: italic; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .profile-header { flex-direction: column; align-items: flex-start; }
            .ph-left { width: 100%; }
            
            /* Table to Cards */
            .data-table thead { display: none; }
            .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; box-sizing: border-box; }
            
            .data-table tr { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-lg); margin-bottom: 1rem; padding: 1.25rem; box-shadow: var(--shadow-md); }
            .data-table td { display: flex; flex-direction: column; align-items: flex-start; text-align: left; padding: 0.6rem 0; border-bottom: 1px dashed rgba(255,255,255,0.05); gap: 4px; }
            .data-table td:last-child { border-bottom: none; padding-top: 1rem; align-items: flex-end;}
            
            .data-table td::before { content: attr(data-label); font-weight: 700; color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; }
            .td-cost { text-align: right; width: 100%;}
        }
    </style>
</head>
<body>

<div class="container">
    <div class="top-bar">
        <a href="<?= $_SESSION['animal_report_url'] ?? 'animal_report.php' ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Animal Report
        </a>
    </div>

    <?php if(isset($error)): ?>
        <div class="error-box">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 3rem; margin-bottom: 1rem;"></i>
            <h3><?= htmlspecialchars($error) ?></h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">The requested animal profile could not be loaded.</p>
        </div>
    <?php else: 
        $status_css = 's-active';
        $status_icon = 'fa-check';
        if(in_array($animal['CURRENT_STATUS'], ['Sold'])) { $status_css = 's-sold'; $status_icon = 'fa-tag'; }
        if(in_array($animal['CURRENT_STATUS'], ['Dead', 'Deceased', 'Cull'])) { $status_css = 's-dead'; $status_icon = 'fa-skull'; }
    ?>

        <div class="profile-card">
            <div class="profile-header">
                <div class="ph-left">
                    <div class="ph-icon"><i class="fa-solid fa-paw"></i></div>
                    <div>
                        <h1 class="tag-no"><?= htmlspecialchars($animal['TAG_NO']) ?></h1>
                        <div class="tag-sub">
                            <?= htmlspecialchars($animal['ANIMAL_TYPE_NAME'] ?? 'Unknown Type') ?> 
                            &bull; 
                            <?= htmlspecialchars($animal['BREED_NAME'] ?? 'Unknown Breed') ?>
                        </div>
                    </div>
                </div>
                <div class="status-badge <?= $status_css ?>">
                    <i class="fa-solid <?= $status_icon ?>"></i> <?= htmlspecialchars($animal['CURRENT_STATUS']) ?>
                </div>
            </div>

            <div class="profile-grid">
                <div class="profile-item">
                    <div class="p-label"><i class="fa-solid fa-layer-group"></i> Stage / Class</div>
                    <div class="p-value"><?= htmlspecialchars($animal['STAGE_NAME'] ?? 'Unclassified') ?></div>
                    <div class="p-sub"><?= $animal['SEX'] == 'M' ? 'Male' : 'Female' ?></div>
                </div>
                <div class="profile-item">
                    <div class="p-label"><i class="fa-solid fa-cake-candles"></i> Age & Birth</div>
                    <div class="p-value"><?= $animal['DAYS_OLD'] !== null ? $animal['DAYS_OLD'] . ' days old' : 'Unknown' ?></div>
                    <div class="p-sub">Born: <?= $animal['BIRTH_DATE'] ? date('M d, Y', strtotime($animal['BIRTH_DATE'])) : 'N/A' ?></div>
                </div>
                <div class="profile-item">
                    <div class="p-label"><i class="fa-solid fa-weight-scale"></i> Weight (KG)</div>
                    <div class="p-value val-blue"><?= $animal['CURRENT_ESTIMATED_WEIGHT'] ?: '-' ?> <span style="font-size:0.8rem; font-weight:500; color:var(--text-secondary); font-family:var(--font);">Est.</span></div>
                    <div class="p-sub"><span style="color:var(--emerald); font-weight:700; font-family:var(--font-mono);"><?= $animal['CURRENT_ACTUAL_WEIGHT'] ?: '-' ?></span> Actual</div>
                </div>
                <div class="profile-item">
                    <div class="p-label"><i class="fa-solid fa-location-dot"></i> Location</div>
                    <div class="p-value" style="font-size: 1.1rem;"><?= htmlspecialchars($animal['BUILDING_NAME'] ?? 'No Building') ?></div>
                    <div class="p-sub">Pen: <?= htmlspecialchars($animal['PEN_NAME'] ?? 'No Pen') ?></div>
                </div>
                <div class="profile-item">
                    <div class="p-label"><i class="fa-solid fa-dna"></i> Lineage</div>
                    <div class="p-value" style="font-size: 1.05rem; color:var(--pink);">Dam: <?= htmlspecialchars($animal['MOTHER_TAG'] ?? 'Unknown') ?></div>
                    <div class="p-sub" style="color:var(--blue); font-weight:600;">Sire: <?= htmlspecialchars($animal['FATHER_TAG'] ?? 'Unknown') ?></div>
                </div>
                <div class="profile-item">
                    <div class="p-label"><i class="fa-solid fa-money-bill-transfer"></i> Acquisition Cost</div>
                    <div class="p-value val-amber">₱<?= number_format($animal['ACQUISITION_COST'], 2) ?></div>
                    <div class="p-sub">Initial Capital</div>
                </div>
                <div class="profile-item" style="background: rgba(16,185,129,0.05);">
                    <div class="p-label" style="color:var(--emerald);"><i class="fa-solid fa-chart-line"></i> Total Net Cost</div>
                    <div class="p-value val-emerald" style="font-size: 1.5rem;">₱<?= number_format($net_cost, 2) ?></div>
                    <div class="p-sub">Acquisition + Operations + Misc</div>
                </div>
            </div>
        </div>

        <div class="section-title"><i class="fa-solid fa-book-open"></i> Operations & Treatment Ledger</div>

        <div class="table-section">
            <div class="table-scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Details / Action Performed</th>
                            <th>Remarks / Reason</th>
                            <th style="text-align:right;">Cost Impact</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($ledger)): ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="fa-solid fa-ghost" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    No operational history found for this animal.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($ledger as $row): 
                                $badgeClass = 't-feed'; $icon = 'fa-wheat-awn';
                                if ($row['type'] == 'Medication') { $badgeClass = 't-med'; $icon = 'fa-syringe'; }
                                if ($row['type'] == 'Vaccination') { $badgeClass = 't-vax'; $icon = 'fa-shield-virus'; }
                                if ($row['type'] == 'Supplement' || $row['type'] == 'Vitamins/Supplements') { $badgeClass = 't-vit'; $icon = 'fa-flask'; $row['type'] = 'Supplement'; }
                                if ($row['type'] == 'Checkup') { $badgeClass = 't-chk'; $icon = 'fa-stethoscope'; }
                                if ($row['type'] == 'Cost Transfer') { $badgeClass = 't-trans'; $icon = 'fa-arrow-right-arrow-left'; }
                                if ($row['type'] == 'Misc Fee') { $badgeClass = 't-misc'; $icon = 'fa-file-invoice-dollar'; }
                                
                                $costVal = floatval($row['cost']);
                                $costStr = '-';
                                $costClass = 'cost-zero';
                                
                                if ($costVal > 0) {
                                    $costStr = '+ ₱ ' . number_format($costVal, 2);
                                    $costClass = 'cost-add';
                                } else if ($costVal < 0) {
                                    $costStr = '- ₱ ' . number_format(abs($costVal), 2);
                                    $costClass = 'cost-sub';
                                }
                            ?>
                            <tr>
                                <td data-label="Date" class="td-date"><?= date('M d, Y h:i A', strtotime($row['txn_date'])) ?></td>
                                <td data-label="Type">
                                    <span class="badge-type <?= $badgeClass ?>"><i class="fa-solid <?= $icon ?>"></i> <?= htmlspecialchars($row['type']) ?></span>
                                </td>
                                <td data-label="Details" class="td-details"><?= htmlspecialchars($row['details']) ?></td>
                                <td data-label="Remarks" class="td-remarks"><?= htmlspecialchars($row['remarks'] ?? '-') ?></td>
                                <td data-label="Cost Impact" class="td-cost <?= $costClass ?>">
                                    <?= $costStr ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>
</div>

</body>
</html>