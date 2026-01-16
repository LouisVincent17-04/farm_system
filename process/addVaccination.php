<?php
// process/addVaccination.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

error_reporting(0);
ini_set('display_errors', 0);

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. RECEIVE DATA
    $animal_id        = $_POST['animal_id'] ?? null;
    $vaccine_item_id  = $_POST['vaccine_item_id'] ?? null; 
    
    // Retrieve Date & Time. Default to NOW if empty.
    $vaccination_date = !empty($_POST['vaccination_date']) ? $_POST['vaccination_date'] : date('Y-m-d H:i:s');
    
    $quantity         = floatval($_POST['quantity'] ?? 0);
    $unit_id          = !empty($_POST['unit_id']) ? $_POST['unit_id'] : null;
    $vet_name         = $_POST['vet_name'] ?? '';
    
    // SERVICE FEE / MANUAL COST entered by the user
    $vaccination_cost = !empty($_POST['cost']) ? floatval($_POST['cost']) : 0; 
    
    $remarks          = $_POST['remarks'] ?? '';

    // 2. VALIDATION
    if (!$animal_id || !$vaccine_item_id || $quantity <= 0 || empty($vet_name)) {
        $response['message'] = 'Missing required fields (Animal, Vaccine, Vet, or valid Quantity).';
        echo json_encode($response);
        exit;
    }

    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        $conn->beginTransaction();

        // 3. CHECK STOCK & CALCULATE VACCINE COST (Inventory Value)
        $stockSql = "SELECT TOTAL_STOCK, TOTAL_COST, SUPPLY_NAME 
                     FROM VACCINES 
                     WHERE SUPPLY_ID = :vac_id 
                     FOR UPDATE"; 
        $stockStmt = $conn->prepare($stockSql);
        $stockStmt->execute([':vac_id' => $vaccine_item_id]);
        
        $stockRow = $stockStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stockRow) {
            throw new Exception("Selected vaccine not found in inventory.");
        }

        $vaccine_name = $stockRow['SUPPLY_NAME'];
        $currentStock = floatval($stockRow['TOTAL_STOCK']);
        $currentValue = floatval($stockRow['TOTAL_COST']);

        if ($currentStock < $quantity) {
            throw new Exception("Insufficient stock for {$vaccine_name}. Available: $currentStock, Requested: $quantity");
        }

        // Calculate Weighted Average Cost
        $price_per_unit = ($currentStock > 0) ? ($currentValue / $currentStock) : 0;
        $vaccine_cost_calculated = $price_per_unit * $quantity; 

        // 4. Get Animal Tag No (For Audit Log)
        $animalSql = "SELECT TAG_NO FROM ANIMAL_RECORDS WHERE ANIMAL_ID = :aid";
        $animalStmt = $conn->prepare($animalSql);
        $animalStmt->execute([':aid' => $animal_id]);
        $animalRow = $animalStmt->fetch(PDO::FETCH_ASSOC);
        $animal_tag = $animalRow['TAG_NO'] ?? 'Unknown Animal';

        // 5. UPDATE INVENTORY (Deduct Stock & Value)
        $updateStockSql = "UPDATE VACCINES 
                           SET TOTAL_STOCK = TOTAL_STOCK - :used_qty, 
                               TOTAL_COST = TOTAL_COST - :used_val,
                               DATE_UPDATED = NOW() 
                           WHERE SUPPLY_ID = :vac_id";
        
        $updateStockStmt = $conn->prepare($updateStockSql);
        if (!$updateStockStmt->execute([
            ':used_qty' => $quantity,
            ':used_val' => $vaccine_cost_calculated,
            ':vac_id'   => $vaccine_item_id
        ])) {
            throw new Exception("Failed to update inventory.");
        }

        // 6. INSERT INTO 'VACCINATION_RECORDS'
        $insertSql = "INSERT INTO VACCINATION_RECORDS 
                      (ANIMAL_ID, VACCINE_ITEM_ID, VET_NAME, QUANTITY, UNIT_ID, 
                       VACCINATION_COST, VACCINE_COST, REMARKS, VACCINATION_DATE, DATE_UPDATED) 
                      VALUES 
                      (:animal_id, :vac_id, :vet_name, :qty, :unit_id, 
                       :svc_cost, :item_cost, :remarks, :vacc_date, NOW())";

        $stmt = $conn->prepare($insertSql);

        $params = [
            ':animal_id' => $animal_id,
            ':vac_id'    => $vaccine_item_id,
            ':vet_name'  => $vet_name,
            ':qty'       => $quantity,
            ':unit_id'   => $unit_id,
            ':svc_cost'  => $vaccination_cost,        // Service Fee
            ':item_cost' => $vaccine_cost_calculated, // Item Cost
            ':remarks'   => $remarks,
            ':vacc_date' => $vaccination_date         // Saves 'YYYY-MM-DD HH:MM:SS'
        ];

        if (!$stmt->execute($params)) {
            throw new Exception("Vaccination record insert failed.");
        }

        // ---------------------------------------------------------
        // 7. INSERT INTO OPERATIONAL_COST (NEW)
        // ---------------------------------------------------------
        // Total Cost = Service Fee + Item Cost
        $total_op_cost = $vaccination_cost + $vaccine_cost_calculated;

        if ($total_op_cost > 0) {
            $opSql = "INSERT INTO operational_cost (animal_id, operation_cost, description, datetime_created) 
                      VALUES (:animal_id, :cost, :desc, :date)";
            $opStmt = $conn->prepare($opSql);
            
            $opDesc = "Vaccine: " . $vaccine_name . " (Qty: " . $quantity . ")";
            
            $opStmt->execute([
                ':animal_id' => $animal_id,
                ':cost'      => $total_op_cost,
                ':desc'      => $opDesc,
                ':date'      => $vaccination_date
            ]);
        }

        // 8. AUDIT LOG
        $cost_fmt = number_format($total_op_cost, 2);
        $prettyDate = date("M d, Y h:i A", strtotime($vaccination_date));

        $logDetails = "Vaccinated Animal $animal_tag with $quantity of $vaccine_name on $prettyDate (Vet: $vet_name). Total Value: ₱$cost_fmt";
        
        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'ADD_VACCINATION', 'VACCINATION_RECORDS', :details, :ip)";
        
        $conn->prepare($log_sql)->execute([
            ':user_id'  => $user_id,
            ':username' => $username,
            ':details'  => $logDetails,
            ':ip'       => $ip_address
        ]);

        // 9. COMMIT
        $conn->commit(); 
        $response['success'] = true;
        $response['message'] = "✅ Vaccination recorded successfully.";
            
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $response['message'] = '❌ Error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>