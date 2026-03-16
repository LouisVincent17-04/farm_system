<?php
// process/getCostData.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

include '../config/Connection.php';

function getFloat($val) { return floatval($val ?: 0); }

$action = $_GET['action'] ?? '';

try {
    if (!isset($conn)) throw new Exception("Database connection failed.");

    // 1. Get Buildings
    if ($action == 'get_buildings') {
        $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
        $stmt->execute([$_GET['loc_id']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    // 2. Get Pens
    elseif ($action == 'get_pens') {
        $bld_id = $_GET['bld_id'] ?? $_GET['bldg_id'] ?? 0;
        $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
        $stmt->execute([$bld_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    // 3. Get Sows
    elseif ($action == 'get_sows_in_pen') {
        $pen_id = $_GET['pen_id'];
        $sql = "SELECT ar.ANIMAL_ID, ar.TAG_NO FROM animal_records ar LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID WHERE ar.PEN_ID = ? AND ar.IS_ACTIVE = 1 AND (ac.STAGE_NAME LIKE '%Sow%' OR ac.STAGE_NAME LIKE '%Gilt%') ORDER BY ar.TAG_NO ASC";
        $stmt = $conn->prepare($sql); $stmt->execute([$pen_id]); echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    // 4. Get Boars
    elseif ($action == 'get_boars_in_pen') {
        $pen_id = $_GET['pen_id'];
        $sql = "SELECT ar.ANIMAL_ID, ar.TAG_NO FROM animal_records ar LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID WHERE ar.PEN_ID = ? AND ar.IS_ACTIVE = 1 AND (ac.STAGE_NAME LIKE '%Boar%') ORDER BY ar.TAG_NO ASC";
        $stmt = $conn->prepare($sql); $stmt->execute([$pen_id]); echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ------------------------------------------------------------------
    // 5. Calculate Net Worth Breakdown
    // ------------------------------------------------------------------
    elseif ($action == 'get_sow_net_worth') {
        $id = $_GET['animal_id'];

        $stmt = $conn->prepare("SELECT LAST_COST_RESET_DATE, ACQUISITION_COST FROM animal_records WHERE ANIMAL_ID = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $resetDate = $row['LAST_COST_RESET_DATE'];
        $baseCost  = getFloat($row['ACQUISITION_COST']);

        // ------------------------------------------------------------------
        // Operational costs: NO reset date filter.
        // ALL operations are included regardless of when they happened.
        // The transferred_cost is what determines how much has already
        // been given away — cutting ops by date was double-penalizing.
        // ------------------------------------------------------------------
        $sql_ops = "
            SELECT
                COALESCE(SUM(CASE WHEN
                    description LIKE 'Feed:%'         OR
                    description LIKE 'Bulk Feed:%'    OR
                    description LIKE 'Treatment:%'    OR
                    description LIKE 'Vaccine:%'      OR
                    description LIKE 'Vaccination:%'  OR
                    description LIKE 'Vitamin:%'      OR
                    description LIKE 'Checkup%'
                THEN ABS(operation_cost) ELSE 0 END), 0) AS total_ops,

                COALESCE(SUM(CASE WHEN
                    description LIKE 'Feed:%' OR
                    description LIKE 'Bulk Feed:%'
                THEN ABS(operation_cost) ELSE 0 END), 0) AS feed_cost,

                COALESCE(SUM(CASE WHEN
                    description LIKE 'Treatment:%'
                THEN ABS(operation_cost) ELSE 0 END), 0) AS med_cost,

                COALESCE(SUM(CASE WHEN
                    description LIKE 'Vaccine:%' OR
                    description LIKE 'Vaccination:%'
                THEN ABS(operation_cost) ELSE 0 END), 0) AS vac_cost,

                COALESCE(SUM(CASE WHEN
                    description LIKE 'Vitamin:%'
                THEN ABS(operation_cost) ELSE 0 END), 0) AS vit_cost,

                COALESCE(SUM(CASE WHEN
                    description LIKE 'Checkup%'
                THEN ABS(operation_cost) ELSE 0 END), 0) AS checkup_cost

            FROM operational_cost
            WHERE animal_id = ?
        ";

        $stmt_ops = $conn->prepare($sql_ops);
        $stmt_ops->execute([$id]);
        $result_ops = $stmt_ops->fetch(PDO::FETCH_ASSOC);
        $total_ops  = getFloat($result_ops['total_ops']);

        // ------------------------------------------------------------------
        // Transferred costs: ALL transfers regardless of reset date.
        // This is the true measure of what has already been distributed.
        // ------------------------------------------------------------------
        $sql_trans = "
            SELECT COALESCE(SUM(
                CASE WHEN SOW_ID  = ? THEN ABS(SOW_COST)
                     WHEN BOAR_ID = ? THEN ABS(BOAR_COST)
                     ELSE 0 END
            ), 0)
            FROM cost_transfers
            WHERE (SOW_ID = ? OR BOAR_ID = ?)
        ";

        $stmt_trans = $conn->prepare($sql_trans);
        $stmt_trans->execute([$id, $id, $id, $id]);
        $transferred_cost = getFloat($stmt_trans->fetchColumn());

        // Final Math: everything the animal has accumulated minus what's been transferred
        $total_available = ($baseCost + $total_ops) - $transferred_cost;

        echo json_encode([
            'success'          => true,
            'total'            => $total_available,
            'acquisition_cost' => $baseCost,
            'operation_cost'   => $total_ops,
            'transferred_cost' => $transferred_cost,
            'feed'             => getFloat($result_ops['feed_cost']),
            'meds'             => getFloat($result_ops['med_cost']),
            'vac'              => getFloat($result_ops['vac_cost']),
            'vit'              => getFloat($result_ops['vit_cost']),
            'checkup'          => getFloat($result_ops['checkup_cost'])
        ]);
        exit;
    }

    // 6. Get Piglets
    elseif ($action == 'get_piglets_by_mother') {
        $mother_id = $_GET['mother_id'];
        $sql = "SELECT ANIMAL_ID, TAG_NO FROM animal_records WHERE MOTHER_ID = ? AND IS_ACTIVE = 1 AND (ACQUISITION_COST = 0 OR ACQUISITION_COST IS NULL) ORDER BY TAG_NO ASC";
        $stmt = $conn->prepare($sql); $stmt->execute([$mother_id]); echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

} catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
?>