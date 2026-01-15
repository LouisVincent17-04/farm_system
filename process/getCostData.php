<?php
// process/get_cost_data.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

include '../config/Connection.php';

// Helper to ensure valid float return
function getFloat($val) {
    return floatval($val ?: 0);
}

$action = $_GET['action'] ?? '';

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 1. Get Buildings by Location
    if ($action == 'get_buildings') {
        $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
        $stmt->execute([$_GET['loc_id']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // 2. Get Pens by Building
    elseif ($action == 'get_pens') {
        $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
        $stmt->execute([$_GET['bldg_id']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // 3. Get Sows in a Specific Building
    elseif ($action == 'get_sows_in_building') {
        $bldg_id = $_GET['bldg_id'];
        $sql = "SELECT ar.ANIMAL_ID, ar.TAG_NO 
                FROM animal_records ar
                LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID
                WHERE ar.BUILDING_ID = ? 
                AND ar.IS_ACTIVE = 1 
                AND (ac.STAGE_NAME LIKE '%Sow%' OR ac.STAGE_NAME LIKE '%Gilt%')
                ORDER BY ar.TAG_NO ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$bldg_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // 4. Search Sow by Tag
    elseif ($action == 'search_sow') {
        $term = $_GET['term'] . "%";
        $sql = "SELECT ar.ANIMAL_ID, ar.TAG_NO, ar.CURRENT_STATUS as STATUS 
                FROM animal_records ar
                LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID
                WHERE ar.TAG_NO LIKE ? 
                AND ar.IS_ACTIVE = 1
                AND (ac.STAGE_NAME LIKE '%Sow%' OR ac.STAGE_NAME LIKE '%Gilt%')
                LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$term]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // 5. Calculate Sow Net Worth (Core Logic - UPDATED)
    elseif ($action == 'get_sow_net_worth') {
        $id = $_GET['animal_id'];
        
        // A. Get Last Reset Date
        $stmt = $conn->prepare("SELECT LAST_COST_RESET_DATE FROM animal_records WHERE ANIMAL_ID = ?");
        $stmt->execute([$id]);
        $resetDate = $stmt->fetchColumn(); 
        
        // B. Define Date Conditions using Parameters
        // We use an array of parameters to bind safely.
        // Since VACCINATION_DATE is now DATETIME, we use direct comparison (>)
        
        $params = [$id]; // Base param is always Animal ID
        
        if ($resetDate) {
            $feedCond  = "AND TRANSACTION_DATE > ?";
            $medCond   = "AND TRANSACTION_DATE > ?";
            $vacCond   = "AND VACCINATION_DATE > ?"; 
            $vitCond   = "AND TRANSACTION_DATE > ?";
            $checkCond = "AND CHECKUP_DATE > ?";
            
            // Add the date to parameters for binding
            $params[] = $resetDate; 
        } else {
            $feedCond = $medCond = $vacCond = $vitCond = $checkCond = "";
        }

        // C. Feed Cost
        $sqlFeed = "SELECT COALESCE(SUM(TRANSACTION_COST), 0) FROM feed_transactions WHERE ANIMAL_ID = ? $feedCond";
        $st = $conn->prepare($sqlFeed);
        $st->execute($params); 
        $feedCost = getFloat($st->fetchColumn());

        // D. Medical Cost (Using TOTAL_COST from treatment_transactions)
        $sqlMeds = "SELECT COALESCE(SUM(TOTAL_COST), 0) FROM treatment_transactions WHERE ANIMAL_ID = ? $medCond";
        $st = $conn->prepare($sqlMeds);
        $st->execute($params);
        $medCost = getFloat($st->fetchColumn());

        // E. Vaccine Cost (Service + Item Cost) [Updated Logic]
        // We sum both Service Cost and Vaccine Item Cost to get the full value.
        $sqlVac = "SELECT COALESCE(SUM(COALESCE(VACCINATION_COST, 0) + COALESCE(VACCINE_COST, 0)), 0) 
                   FROM vaccination_records WHERE ANIMAL_ID = ? $vacCond";
        $st = $conn->prepare($sqlVac);
        $st->execute($params);
        $vacCost = getFloat($st->fetchColumn());

        // F. Vitamin Cost
        $sqlVit = "SELECT COALESCE(SUM(TOTAL_COST), 0) FROM vitamins_supplements_transactions WHERE ANIMAL_ID = ? $vitCond";
        $st = $conn->prepare($sqlVit);
        $st->execute($params);
        $vitCost = getFloat($st->fetchColumn());

        // G. Checkup Cost
        $sqlCheck = "SELECT COALESCE(SUM(COST), 0) FROM check_ups WHERE ANIMAL_ID = ? $checkCond";
        $st = $conn->prepare($sqlCheck);
        $st->execute($params);
        $checkCost = getFloat($st->fetchColumn());

        // H. Total
        $total = $feedCost + $medCost + $vacCost + $vitCost + $checkCost;

        echo json_encode([
            'total'   => $total,
            'feed'    => $feedCost,
            'meds'    => $medCost,
            'vac'     => $vacCost,
            'vit'     => $vitCost,
            'checkup' => $checkCost
        ]);
    }

    // 6. Get Piglets by Mother (Zero Cost Only)
    elseif ($action == 'get_piglets_by_mother') {
        $mother_id = $_GET['mother_id'];
        
        $sql = "SELECT ANIMAL_ID, TAG_NO 
                FROM animal_records 
                WHERE MOTHER_ID = ? 
                AND IS_ACTIVE = 1 
                AND (ACQUISITION_COST = 0 OR ACQUISITION_COST IS NULL)
                ORDER BY TAG_NO ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$mother_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>