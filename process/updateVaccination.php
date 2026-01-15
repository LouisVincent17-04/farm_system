<?php
// ../process/updateVaccination.php
session_start();
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/Connection.php';

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $vaccination_id   = $_POST['vaccination_id'] ?? null;
    $animal_id        = $_POST['animal_id'] ?? null;
    $vaccine_item_id  = $_POST['vaccine_item_id'] ?? null; // New Item ID
    $quantity         = floatval($_POST['quantity'] ?? 0); // New Quantity
    $unit_id          = !empty($_POST['unit_id']) ? $_POST['unit_id'] : null;
    $vet_name         = $_POST['vet_name'] ?? '';
    $remarks          = $_POST['remarks'] ?? '';
    
    // Date & Time input
    $vaccination_date = !empty($_POST['vaccination_date']) ? $_POST['vaccination_date'] : date('Y-m-d H:i:s');

    // Service Cost (Manual input)
    $service_cost     = !empty($_POST['cost']) ? floatval($_POST['cost']) : 0;

    if (!$vaccination_id || !$animal_id || !$vaccine_item_id || $quantity <= 0) {
        echo json_encode(['success' => false, 'message' => '❌ Missing required fields or invalid quantity.']);
        exit;
    }

    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        $conn->beginTransaction();

        // 1. GET OLD RECORD & ANIMAL TAG (Lock for update)
        $oldSql = "SELECT VR.VACCINE_ITEM_ID, VR.QUANTITY, VR.VACCINE_COST, AR.TAG_NO, V.SUPPLY_NAME AS OLD_VACCINE_NAME 
                   FROM VACCINATION_RECORDS VR
                   JOIN ANIMAL_RECORDS AR ON VR.ANIMAL_ID = AR.ANIMAL_ID
                   JOIN VACCINES V ON VR.VACCINE_ITEM_ID = V.SUPPLY_ID
                   WHERE VR.VACCINATION_ID = :id FOR UPDATE";
        
        $oldStmt = $conn->prepare($oldSql);
        $oldStmt->execute([':id' => $vaccination_id]);
        $oldRow = $oldStmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldRow) {
            throw new Exception("Record not found or already deleted.");
        }

        $oldItemId = $oldRow['VACCINE_ITEM_ID'];
        $oldQty    = floatval($oldRow['QUANTITY']);
        $oldVal    = floatval($oldRow['VACCINE_COST']); // The monetary value previously deducted
        $animal_tag = $oldRow['TAG_NO'];
        $original_vaccine_name = $oldRow['OLD_VACCINE_NAME']; 

        // 2. REFUND OLD STOCK & VALUE TO INVENTORY
        // We put back exactly what we took out (Quantity and Monetary Value)
        $refundSql = "UPDATE VACCINES 
                      SET TOTAL_STOCK = TOTAL_STOCK + :old_qty, 
                          TOTAL_COST = TOTAL_COST + :old_val,
                          DATE_UPDATED = NOW() 
                      WHERE SUPPLY_ID = :old_item_id";
        
        $refundStmt = $conn->prepare($refundSql);
        if (!$refundStmt->execute([
            ':old_qty' => $oldQty, 
            ':old_val' => $oldVal,
            ':old_item_id' => $oldItemId
        ])) {
             throw new Exception("Failed to refund old stock.");
        }

        // 3. CHECK NEW STOCK AVAILABILITY & CALCULATE NEW COST
        $checkSql = "SELECT TOTAL_STOCK, TOTAL_COST, SUPPLY_NAME 
                     FROM VACCINES 
                     WHERE SUPPLY_ID = :new_item_id FOR UPDATE";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':new_item_id' => $vaccine_item_id]);
        $stockRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$stockRow) {
            throw new Exception("Selected vaccine not found in inventory.");
        }
        
        $new_vaccine_name = $stockRow['SUPPLY_NAME'];
        $current_stock = floatval($stockRow['TOTAL_STOCK']);
        $current_total_value = floatval($stockRow['TOTAL_COST']);

        if ($current_stock < $quantity) {
            throw new Exception("Insufficient stock for {$new_vaccine_name}. Available: {$current_stock}, Requested: $quantity");
        }

        // Calculate Weighted Average Cost for the new deduction
        $price_per_unit = ($current_stock > 0) ? ($current_total_value / $current_stock) : 0;
        $new_item_cost_calculated = $price_per_unit * $quantity;

        // 4. DEDUCT NEW STOCK & VALUE
        $deductSql = "UPDATE VACCINES 
                      SET TOTAL_STOCK = TOTAL_STOCK - :new_qty, 
                          TOTAL_COST = TOTAL_COST - :new_val,
                          DATE_UPDATED = NOW() 
                      WHERE SUPPLY_ID = :new_item_id";
        
        $deductStmt = $conn->prepare($deductSql);
        if (!$deductStmt->execute([
            ':new_qty' => $quantity, 
            ':new_val' => $new_item_cost_calculated,
            ':new_item_id' => $vaccine_item_id
        ])) {
            throw new Exception("Failed to deduct new stock.");
        }

        // 5. UPDATE THE RECORD
        $updateSql = "UPDATE VACCINATION_RECORDS SET 
                        ANIMAL_ID = :animal_id,
                        VACCINE_ITEM_ID = :vaccine_id,
                        VET_NAME = :vet_name,
                        QUANTITY = :qty,
                        UNIT_ID = :unit_id,
                        VACCINATION_COST = :svc_cost, -- Manual Service Fee
                        VACCINE_COST = :item_cost,    -- Calculated Item Value
                        REMARKS = :remarks,
                        VACCINATION_DATE = :vacc_date,
                        DATE_UPDATED = NOW()
                      WHERE VACCINATION_ID = :vacc_id";

        $stmt = $conn->prepare($updateSql);
        $updateParams = [
            ':animal_id'  => $animal_id,
            ':vaccine_id' => $vaccine_item_id,
            ':vet_name'   => $vet_name,
            ':qty'        => $quantity,
            ':unit_id'    => $unit_id,
            ':svc_cost'   => $service_cost,
            ':item_cost'  => $new_item_cost_calculated,
            ':remarks'    => $remarks,
            ':vacc_date'  => $vaccination_date,
            ':vacc_id'    => $vaccination_id
        ];

        if (!$stmt->execute($updateParams)) {
            throw new Exception("Failed to update vaccination record.");
        }

        // 6. INSERT AUDIT LOG
        $total_value_display = number_format($service_cost + $new_item_cost_calculated, 2);
        
        $logDetails = "Updated Vaccination (ID: $vaccination_id) for Animal $animal_tag. Vaccine: {$original_vaccine_name} ({$oldQty}) -> {$new_vaccine_name} ({$quantity}). New Total Value: ₱$total_value_display";
        
        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'EDIT_VACCINATION', 'VACCINATION_RECORDS', :details, :ip)";
        
        $logStmt = $conn->prepare($log_sql);
        $logStmt->execute([
            ':user_id'  => $user_id,
            ':username' => $username,
            ':details'  => $logDetails,
            ':ip'       => $ip_address
        ]);

        // 7. COMMIT EVERYTHING
        $conn->commit();
        $response['success'] = true;
        $response['message'] = '✅ Record updated and inventory adjusted.';

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $response['message'] = '❌ Update Failed: ' . $e->getMessage();
    }
} else {
    $response['message'] = '❌ Invalid request method.';
}

echo json_encode($response);
?>