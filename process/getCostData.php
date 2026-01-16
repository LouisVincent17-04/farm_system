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

    // [KEEP YOUR EXISTING GET_BUILDINGS, GET_PENS, SEARCH LOGIC HERE]
    // (Hidden for brevity, assume they are unchanged)
    
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
    // 3. Get Sows/Boars
    elseif ($action == 'get_sows_in_pen') {
        $pen_id = $_GET['pen_id'];
        $sql = "SELECT ar.ANIMAL_ID, ar.TAG_NO FROM animal_records ar LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID WHERE ar.PEN_ID = ? AND ar.IS_ACTIVE = 1 AND (ac.STAGE_NAME LIKE '%Sow%' OR ac.STAGE_NAME LIKE '%Gilt%') ORDER BY ar.TAG_NO ASC";
        $stmt = $conn->prepare($sql); $stmt->execute([$pen_id]); echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    elseif ($action == 'get_boars_in_pen') {
        $pen_id = $_GET['pen_id'];
        $sql = "SELECT ar.ANIMAL_ID, ar.TAG_NO FROM animal_records ar LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID WHERE ar.PEN_ID = ? AND ar.IS_ACTIVE = 1 AND (ac.STAGE_NAME LIKE '%Boar%') ORDER BY ar.TAG_NO ASC";
        $stmt = $conn->prepare($sql); $stmt->execute([$pen_id]); echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    // 4. Search
    elseif ($action == 'search_sow') {
        $term = $_GET['term'] . "%";
        $sql = "SELECT ar.ANIMAL_ID, ar.TAG_NO, ar.CURRENT_STATUS as STATUS FROM animal_records ar LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID WHERE ar.TAG_NO LIKE ? AND ar.IS_ACTIVE = 1 AND (ac.STAGE_NAME LIKE '%Sow%' OR ac.STAGE_NAME LIKE '%Gilt%') LIMIT 10";
        $stmt = $conn->prepare($sql); $stmt->execute([$term]); echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ------------------------------------------------------------------
    // 5. Calculate Net Worth (CORRECTED: SINGLE SOURCE OF TRUTH)
    // ------------------------------------------------------------------
    elseif ($action == 'get_sow_net_worth') {
        $id = $_GET['animal_id'];
        
        // A. Get Reset Date
        $stmt = $conn->prepare("SELECT LAST_COST_RESET_DATE, ACQUISITION_COST FROM animal_records WHERE ANIMAL_ID = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $resetDate = $row['LAST_COST_RESET_DATE'];
        $baseCost  = getFloat($row['ACQUISITION_COST']); 

        // B. Query ONLY operational_cost table
        // We filter strictly by datetime_created > resetDate
        
        $sql = "SELECT 
                    COALESCE(SUM(operation_cost), 0) as total_ops,
                    COALESCE(SUM(CASE WHEN description LIKE 'Feed:%' OR description LIKE 'Bulk Feed:%' THEN operation_cost ELSE 0 END), 0) as feed_cost,
                    COALESCE(SUM(CASE WHEN description LIKE 'Treatment:%' THEN operation_cost ELSE 0 END), 0) as med_cost,
                    COALESCE(SUM(CASE WHEN description LIKE 'Vaccine:%' OR description LIKE 'Vaccination:%' THEN operation_cost ELSE 0 END), 0) as vac_cost,
                    COALESCE(SUM(CASE WHEN description LIKE 'Vitamin:%' THEN operation_cost ELSE 0 END), 0) as vit_cost,
                    COALESCE(SUM(CASE WHEN description LIKE 'Checkup:%' THEN operation_cost ELSE 0 END), 0) as checkup_cost,
                    COALESCE(SUM(CASE WHEN description LIKE 'Rollover:%' THEN operation_cost ELSE 0 END), 0) as rollover_cost
                FROM operational_cost 
                WHERE animal_id = ?";
        
        $params = [$id];

        if ($resetDate) {
            $sql .= " AND datetime_created > ?";
            $params[] = $resetDate;
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Map results
        $total_ops = getFloat($result['total_ops']);
        
        // Final Total = ONLY the sum from operational_cost
        // (We return baseCost separately just for UI display, but don't add it to transferable total unless you specifically want to)
        $total = $total_ops;

        echo json_encode([
            'total'   => $total,
            'base'    => $baseCost,
            'feed'    => getFloat($result['feed_cost']),
            'meds'    => getFloat($result['med_cost']),
            'vac'     => getFloat($result['vac_cost']),
            'vit'     => getFloat($result['vit_cost']),
            'checkup' => getFloat($result['checkup_cost']),
            'ops'     => getFloat($result['rollover_cost'])
        ]);
    }

    // 6. Get Piglets
    elseif ($action == 'get_piglets_by_mother') {
        $mother_id = $_GET['mother_id'];
        $sql = "SELECT ANIMAL_ID, TAG_NO FROM animal_records WHERE MOTHER_ID = ? AND IS_ACTIVE = 1 AND (ACQUISITION_COST = 0 OR ACQUISITION_COST IS NULL) ORDER BY TAG_NO ASC";
        $stmt = $conn->prepare($sql); $stmt->execute([$mother_id]); echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

} catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
?>