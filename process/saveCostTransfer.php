<?php
// process/saveCostTransfer.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require '../config/Connection.php';

// Helper to ensure float
if (!function_exists('getFloat')) {
    function getFloat($val) {
        return floatval($val ?: 0);
    }
}

// Check Request Method
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // ==================================================================
    //  HANDLE DATA RETRIEVAL (GET)
    // ==================================================================
    if ($method === 'GET') {
        
        if ($action == 'get_buildings') {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ?");
            $stmt->execute([$_GET['loc_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        elseif ($action == 'search_sow') {
            $term = $_GET['term'] . "%";
            $sql = "SELECT ar.ANIMAL_ID, ar.TAG_NO, ar.CURRENT_STATUS as STATUS 
                    FROM animal_records ar
                    LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID
                    WHERE (ar.TAG_NO LIKE ? OR ac.STAGE_NAME LIKE '%Sow%' OR ac.STAGE_NAME LIKE '%Gilt%') 
                    AND ar.IS_ACTIVE = 1";
            
            $params = [$term];
            if(!empty($_GET['loc_id'])) { $sql .= " AND ar.LOCATION_ID = ?"; $params[] = $_GET['loc_id']; }
            if(!empty($_GET['bldg_id'])) { $sql .= " AND ar.BUILDING_ID = ?"; $params[] = $_GET['bldg_id']; }
            
            $sql .= " LIMIT 10";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }

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

        elseif ($action == 'get_sow_net_worth') {
            $id = $_GET['animal_id'];
            
            // 1. Get Reset Date
            $stmt = $conn->prepare("SELECT LAST_COST_RESET_DATE FROM animal_records WHERE ANIMAL_ID = ?");
            $stmt->execute([$id]);
            $resetDate = $stmt->fetchColumn(); 
            
            $params = [$id];
            if ($resetDate) {
                // Use precise DATETIME comparison
                $dateCond = "AND TRANSACTION_DATE > ?";
                $dateCondCheck = "AND CHECKUP_DATE > ?";
                $dateCondVacc = "AND VACCINATION_DATE > ?";
                $params[] = $resetDate;
            } else {
                $dateCond = $dateCondCheck = $dateCondVacc = "";
            }

            // Sum Feeds
            $st = $conn->prepare("SELECT SUM(TRANSACTION_COST) FROM feed_transactions WHERE ANIMAL_ID = ? $dateCond");
            $st->execute($params);
            $feed = getFloat($st->fetchColumn());
            
            // Sum Meds
            $st = $conn->prepare("SELECT SUM(TOTAL_COST) FROM treatment_transactions WHERE ANIMAL_ID = ? $dateCond");
            $st->execute($params);
            $meds = getFloat($st->fetchColumn());

            // Sum Vaccines (Service + Item)
            $st = $conn->prepare("SELECT SUM(COALESCE(VACCINATION_COST,0) + COALESCE(VACCINE_COST,0)) FROM vaccination_records WHERE ANIMAL_ID = ? $dateCondVacc");
            $st->execute($params);
            $vac = getFloat($st->fetchColumn());

            // Sum Vitamins
            $st = $conn->prepare("SELECT SUM(TOTAL_COST) FROM vitamins_supplements_transactions WHERE ANIMAL_ID = ? $dateCond");
            $st->execute($params);
            $vit = getFloat($st->fetchColumn());

            // Sum Checkups
            $st = $conn->prepare("SELECT SUM(COST) FROM check_ups WHERE ANIMAL_ID = ? $dateCondCheck");
            $st->execute($params);
            $check = getFloat($st->fetchColumn());

            $total = $feed + $meds + $vac + $vit + $check;

            echo json_encode([
                'total' => $total,
                'feed' => $feed,
                'meds' => $meds,
                'vac' => $vac,
                'vit' => $vit,
                'checkup' => $check
            ]);
        }
    }

    // ==================================================================
    //  HANDLE TRANSFER EXECUTION (POST) - This was missing!
    // ==================================================================
    elseif ($method === 'POST') {
        
        $sow_id = $_POST['sow_id'] ?? null;
        $piglet_ids_json = $_POST['piglet_ids'] ?? '[]';
        $total_amount = getFloat($_POST['total_amount'] ?? 0);

        $piglet_ids = json_decode($piglet_ids_json, true);

        if (!$sow_id || empty($piglet_ids)) {
            throw new Exception("Missing Sow ID or Piglets list.");
        }

        if ($total_amount <= 0) {
            throw new Exception("Total amount to transfer must be greater than zero.");
        }

        $count = count($piglet_ids);
        $cost_per_piglet = $total_amount / $count;

        $conn->beginTransaction();

        // 1. Update Piglets: Add Cost
        // We use string interpolation for the IDs safely because they come from internal ID list
        // but parameterized query is better. Let's loop for safety.
        $updatePiglet = $conn->prepare("UPDATE animal_records SET ACQUISITION_COST = ACQUISITION_COST + :cost, UPDATED_AT = NOW() WHERE ANIMAL_ID = :id");
        
        foreach ($piglet_ids as $pid) {
            $updatePiglet->execute([':cost' => $cost_per_piglet, ':id' => $pid]);
        }

        // 2. Reset Sow: Set Reset Date to NOW()
        $resetSow = $conn->prepare("UPDATE animal_records SET LAST_COST_RESET_DATE = NOW(), UPDATED_AT = NOW() WHERE ANIMAL_ID = :sid");
        $resetSow->execute([':sid' => $sow_id]);

        // 3. Log the Transfer (Optional but recommended)
        // Check if `cost_transfers` table exists, otherwise skip or log to audit_logs
        // For now, let's log to audit_logs if it exists
        $auditSql = "INSERT INTO audit_logs (ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, LOG_DATE) 
                     VALUES ('COST_TRANSFER', 'ANIMAL_RECORDS', :details, NOW())";
        $auditStmt = $conn->prepare($auditSql);
        $details = "Transferred ₱" . number_format($total_amount, 2) . " from Sow #$sow_id to $count piglets (₱" . number_format($cost_per_piglet, 2) . "/each).";
        $auditStmt->execute([':details' => $details]);

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Transfer successful! Sow cost reset.',
            'transferred_amount' => $total_amount,
            'piglet_count' => $count
        ]);
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>