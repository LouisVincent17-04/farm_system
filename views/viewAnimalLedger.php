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

    // printf("<pre>");
    // print_r($animal);
    // printf("</pre>");
    // echo "<hr><h2>Debug:".$animal['CLASS_ID']."</h2>";
    // exit;
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
                        'Vitamins/Supplements' AS type, 
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
        if($animal['CLASS_ID'] == 7)
        {
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
        else if($animal['CLASS_ID'] == 8)
        {
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

    // 3. Sort Ledger by Date Descending
    usort($ledger, function($a, $b) {
        return strtotime($b['txn_date']) - strtotime($a['txn_date']);
    });

} catch (Exception $e) {
    $error = $e->getMessage();
}


$net_cost = $animal['ACQUISITION_COST'] + array_sum(array_column($ledger, 'cost'));

// printf("<pre>");
//         print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
//         printf("</pre>");
//         exit;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Ledger - <?= htmlspecialchars($animal['TAG_NO'] ?? 'Profile') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; margin: 0; padding-bottom: 40px; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        
        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #94a3b8; font-weight: 600; font-size: 0.95rem; margin-bottom: 20px; transition: color 0.2s; }
        .back-link:hover { color: white; }

        /* --- PROFILE CARD --- */
        .profile-card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: 16px; overflow: hidden; margin-bottom: 2rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5); }
        .profile-header { background: rgba(15, 23, 42, 0.8); padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; }
        .tag-no { font-size: 2.2rem; font-weight: 800; color: #4ade80; margin: 0; }
        .status-badge { padding: 6px 16px; border-radius: 999px; font-weight: bold; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; }
        
        .s-active { background: rgba(34,197,94,0.2); color: #4ade80; border: 1px solid rgba(34,197,94,0.5); }
        .s-sold { background: rgba(251,191,36,0.2); color: #fbbf24; border: 1px solid rgba(251,191,36,0.5); }
        .s-dead { background: rgba(239,68,68,0.2); color: #f87171; border: 1px solid rgba(239,68,68,0.5); }

        .profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1px; background: #334155; }
        .profile-item { background: rgba(30, 41, 59, 0.9); padding: 1.5rem; }
        .p-label { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 600; margin-bottom: 5px; }
        .p-value { font-size: 1.1rem; color: #fff; font-weight: 500; }
        .p-sub { font-size: 0.8rem; color: #64748b; margin-top: 4px; }

        /* --- LEDGER TABLE --- */
        .table-wrap { background: rgba(30, 41, 59, 0.5); border-radius: 16px; overflow: hidden; border: 1px solid #334155; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { background: rgba(15, 23, 42, 0.9); color: #94a3b8; text-align: left; padding: 1rem; font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid #334155; }
        td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.95rem; color: #e2e8f0; vertical-align: top;}
        tr:hover { background: rgba(255,255,255,0.02); }

        .badge-type { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
        .t-feed { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .t-med { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .t-vax { background: rgba(14, 165, 233, 0.15); color: #38bdf8; border: 1px solid rgba(14, 165, 233, 0.3); }
        .t-vit { background: rgba(163, 230, 53, 0.15); color: #bef264; border: 1px solid rgba(163, 230, 53, 0.3); }
        .t-chk { background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); }
        .t-trans { background: rgba(99, 102, 241, 0.15); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.3); }

        .empty-state { text-align: center; padding: 4rem; color: #64748b; font-style: italic; }
        
        @media (max-width: 768px) {
            .profile-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .table-wrap { overflow-x: auto; }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="animal_report.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to Animal Report
    </a>

    <?php if(isset($error)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; padding: 1.5rem; border-radius: 12px; color: #ef4444; text-align: center;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 2rem; margin-bottom: 1rem;"></i>
            <h3><?= $error ?></h3>
        </div>
    <?php else: 
        $status_css = 's-active';
        if(in_array($animal['CURRENT_STATUS'], ['Sold'])) $status_css = 's-sold';
        if(in_array($animal['CURRENT_STATUS'], ['Dead', 'Deceased', 'Cull'])) $status_css = 's-dead';
    ?>

        <div class="profile-card">
            <div class="profile-header">
                <div>
                    <h1 class="tag-no"><?= htmlspecialchars($animal['TAG_NO']) ?></h1>
                    <div style="color: #94a3b8; margin-top: 5px;">
                        <?= htmlspecialchars($animal['ANIMAL_TYPE_NAME']) ?> • <?= htmlspecialchars($animal['BREED_NAME']) ?>
                    </div>
                </div>
                <div class="status-badge <?= $status_css ?>">
                    <?= htmlspecialchars($animal['CURRENT_STATUS']) ?>
                </div>
            </div>

            <div class="profile-grid">
                <div class="profile-item">
                    <div class="p-label">Stage / Class</div>
                    <div class="p-value"><?= htmlspecialchars($animal['STAGE_NAME'] ?? 'Unclassified') ?></div>
                    <div class="p-sub"><?= $animal['SEX'] == 'M' ? 'Male' : 'Female' ?></div>
                </div>
                <div class="profile-item">
                    <div class="p-label">Age & Birth</div>
                    <div class="p-value"><?= $animal['DAYS_OLD'] !== null ? $animal['DAYS_OLD'] . ' days old' : 'Unknown' ?></div>
                    <div class="p-sub">Born: <?= $animal['BIRTH_DATE'] ? date('M d, Y', strtotime($animal['BIRTH_DATE'])) : 'N/A' ?></div>
                </div>
                <div class="profile-item">
                    <div class="p-label">Weight (KG)</div>
                    <div class="p-value"><span style="color:#60a5fa"><?= $animal['CURRENT_ESTIMATED_WEIGHT'] ?: '-' ?></span> Est.</div>
                    <div class="p-sub"><span style="color:#34d399"><?= $animal['CURRENT_ACTUAL_WEIGHT'] ?: '-' ?></span> Actual</div>
                </div>
                <div class="profile-item">
                    <div class="p-label">Location</div>
                    <div class="p-value"><?= htmlspecialchars($animal['BUILDING_NAME'] ?? 'No Building') ?></div>
                    <div class="p-sub">Pen: <?= htmlspecialchars($animal['PEN_NAME'] ?? 'No Pen') ?></div>
                </div>
                <div class="profile-item">
                    <div class="p-label">Lineage</div>
                    <div class="p-value" style="color:#f472b6;">Dam: <?= htmlspecialchars($animal['MOTHER_TAG'] ?? 'Unknown') ?></div>
                    <div class="p-sub" style="color:#60a5fa;">Sire: <?= htmlspecialchars($animal['FATHER_TAG'] ?? 'Unknown') ?></div>
                </div>
                <div class="profile-item">
                    <div class="p-label">Acquisition Cost</div>
                    <div class="p-value" style="color:#fbbf24;">₱<?= number_format($animal['ACQUISITION_COST'], 2) ?></div>
                </div>
                <div class="profile-item">
                    <div class="p-label">Net Cost</div>
                    <div class="p-value" style="color:#fbbf24;">
                        
                        ₱<?php echo number_format($net_cost, 2) ?>
                    </div>
                </div>
            </div>
        </div>

        <h3 style="color:#94a3b8; margin-bottom: 1rem;">Operations & Treatment Ledger</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Details / Action Performed</th>
                        <th>Remarks / Reason</th>
                        <th style="text-align:right;">Cost (PHP)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($ledger)): ?>
                        <tr><td colspan="5" class="empty-state">No operational history found for this animal.</td></tr>
                    <?php else: ?>
                        <?php foreach($ledger as $row): 
                            $badgeClass = 't-feed'; // default
                            if ($row['type'] == 'Medication') $badgeClass = 't-med';
                            if ($row['type'] == 'Vaccination') $badgeClass = 't-vax';
                            if ($row['type'] == 'Supplement') $badgeClass = 't-vit';
                            if ($row['type'] == 'Checkup') $badgeClass = 't-chk';
                            if ($row['type'] == 'Cost Transfer') $badgeClass = 't-trans';
                        ?>
                        <tr>
                            <td style="color:#94a3b8; white-space:nowrap;"><?= date('M d, Y H:i', strtotime($row['txn_date'])) ?></td>
                            <td><span class="badge-type <?= $badgeClass ?>"><?= htmlspecialchars($row['type']) ?></span></td>
                            <td style="color:#fff; font-weight:500;"><?= htmlspecialchars($row['details']) ?></td>
                            <td style="color:#cbd5e1; font-size:0.85rem;"><?= htmlspecialchars($row['remarks'] ?? '-') ?></td>
                            <td style="text-align:right; color:#fbbf24;">
                                <?php 
                                    if ($row['type'] == 'Cost Transfer'){
                                        echo $row['cost'] < 0 ? '₱ '.number_format($row['cost'], 2) : '-';
                                    }
                                    else
                                    {
                                        echo $row['cost'] > 0 ? '₱'.number_format($row['cost'], 2) : '-';
                                    }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>