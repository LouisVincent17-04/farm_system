<?php
session_start(); // 1. Start Session
header('Content-Type: application/json');

// Turn off default error reporting to prevent PHP warnings breaking JSON
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/Connection.php';

// Get User Info (Safe Fallback)
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $animal_id   = $_POST['animal_id'] ?? null;
    $item_id     = $_POST['item_id'] ?? null; // Maps to MEDICINES.SUPPLY_ID
    $dosage      = $_POST['dosage'] ?? '';
    $quantity    = floatval($_POST['quantity_used'] ?? 0);
    $trans_date  = $_POST['transaction_date'] ?? date('Y-m-d');
    $remarks     = $_POST['remarks'] ?? '';

    // 1. Basic Validation
    if (!$animal_id || !$item_id || $quantity <= 0) {
        $response['message'] = 'Missing required fields or invalid quantity.';
        echo json_encode($response);
        exit;
    }

    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        // ---------------------------------------------------------
        // START TRANSACTION
        // ---------------------------------------------------------
        $conn->beginTransaction();

        // 2. Check Stock & Cost in MEDICINES Table (Lock Row)
        // We need TOTAL_COST to calculate the unit price dynamically
        $stockSql = "SELECT TOTAL_STOCK, TOTAL_COST, SUPPLY_NAME 
                     FROM MEDICINES 
                     WHERE SUPPLY_ID = :id 
                     FOR UPDATE";
        $stockStmt = $conn->prepare($stockSql);
        $stockStmt->execute([':id' => $item_id]);
        
        $stockRow = $stockStmt->fetch(PDO::FETCH_ASSOC);

        if (!$stockRow) {
            throw new Exception("Medicine item not found in inventory.");
        }

        $medicine_name = $stockRow['SUPPLY_NAME'];
        $currentStock  = floatval($stockRow['TOTAL_STOCK']);
        $currentValue  = floatval($stockRow['TOTAL_COST']);

        // Check Stock Availability
        if ($currentStock < $quantity) {
            throw new Exception("Insufficient stock for {$medicine_name}. Available: {$currentStock}");
        }

        // ---------------------------------------------------------
        // 3. CALCULATE COST
        // Logic: (Total Value / Total Stock) = Price Per Unit
        //        Price Per Unit * Quantity Used = Transaction Cost
        // ---------------------------------------------------------
        $price_per_unit = ($currentStock > 0) ? ($currentValue / $currentStock) : 0;
        $transaction_cost = $price_per_unit * $quantity;

        // 4. Get Animal Tag No (For the Audit Log)
        $animalSql = "SELECT TAG_NO FROM ANIMAL_RECORDS WHERE ANIMAL_ID = :aid";
        $animalStmt = $conn->prepare($animalSql);
        $animalStmt->execute([':aid' => $animal_id]);
        $animalRow = $animalStmt->fetch(PDO::FETCH_ASSOC);
        $animal_tag = $animalRow['TAG_NO'] ?? 'Unknown Animal';

        // 5. Update MEDICINES Inventory
        // We deduct the Quantity AND the calculated Cost Value to keep inventory valuation accurate
        $updateStockSql = "UPDATE MEDICINES 
                           SET TOTAL_STOCK = TOTAL_STOCK - :qty, 
                               TOTAL_COST = TOTAL_COST - :cost_val,
                               DATE_UPDATED = NOW() 
                           WHERE SUPPLY_ID = :id";
        
        $updateStmt = $conn->prepare($updateStockSql);
        if (!$updateStmt->execute([
            ':qty' => $quantity, 
            ':cost_val' => $transaction_cost,
            ':id' => $item_id
        ])) {
            throw new Exception("Stock update failed.");
        }

        // 6. Insert into TREATMENT_TRANSACTIONS with TOTAL_COST
        $insertSql = "INSERT INTO TREATMENT_TRANSACTIONS 
                      (ANIMAL_ID, ITEM_ID, DOSAGE, QUANTITY_USED, TOTAL_COST, TRANSACTION_DATE, REMARKS, CREATED_AT) 
                      VALUES 
                      (:animal_id, :item_id, :dosage, :qty, :total_cost, :t_date, :remarks, NOW())";

        $stmt = $conn->prepare($insertSql);

        $params = [
            ':animal_id'  => $animal_id,
            ':item_id'    => $item_id,
            ':dosage'     => $dosage,
            ':qty'        => $quantity,
            ':total_cost' => $transaction_cost, // Insert calculated cost
            ':t_date'     => $trans_date,
            ':remarks'    => $remarks
        ];

        if (!$stmt->execute($params)) {
            throw new Exception("Transaction insert failed.");
        }

        // 7. INSERT AUDIT LOG
        // Formatted cost for log
        $cost_display = number_format($transaction_cost, 2);
        $logDetails = "Administered $quantity of $medicine_name (Cost: ₱$cost_display) to Animal $animal_tag (ID $animal_id). Dosage: $dosage.";

        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'ADD_TREATMENT', 'TREATMENT_TRANSACTIONS', :details, :ip)";

        $log_stmt = $conn->prepare($log_sql);
        
        $log_params = [
            ':user_id'  => $user_id,
            ':username' => $username,
            ':details'  => $logDetails,
            ':ip'       => $ip_address
        ];

        if (!$log_stmt->execute($log_params)) {
            throw new Exception("Audit Log Failed.");
        }

        // 8. COMMIT EVERYTHING
        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = "✅ Treatment saved! Cost: ₱" . number_format($transaction_cost, 2);

    } catch (Exception $e) {
        // Rollback on error
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