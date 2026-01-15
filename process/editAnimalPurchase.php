<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

header('Content-Type: application/json');

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    
    $item_id = $_POST['item_id'];

    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        // ---------------------------------------------------------
        // START TRANSACTION
        // ---------------------------------------------------------
        $conn->beginTransaction();

        // 1. Retrieve and Sanitize Data
        $item_name = trim($_POST['item_name']);
        $item_type_id = 13; // Animals
        $item_quantity = floatval($_POST['item_quantity'] ?? 0);
        
        // --- UPDATED: Retrieve Weight ---
        $weight = floatval($_POST['weight'] ?? 0);
        // --------------------------------

        $unit_id = $_POST['unit_id'];
        $unit_cost = floatval($_POST['unit_cost'] ?? 0);
        $date_of_purchase = $_POST['date_of_purchase']; // MySQL handles YYYY-MM-DD string directly
        $item_description = $_POST['item_description'] ?? null;
        
        $location_id = !empty($_POST['location_id']) ? $_POST['location_id'] : null;
        $building_id = !empty($_POST['building_id']) ? $_POST['building_id'] : null;
        $pen_id = !empty($_POST['pen_id']) ? $_POST['pen_id'] : null;
        
        // Handle zero values for quantity
        $item_quantity = ($item_quantity <= 0) ? null : $item_quantity;
        
        // Calculate Total Cost
        $total_cost = $item_quantity * $unit_cost;

        // 2. Get Original Data (For Audit Log Comparison)
        $original_sql = "SELECT ITEM_NAME, QUANTITY, UNIT_COST, TOTAL_COST, ITEM_NET_WEIGHT 
                         FROM ITEMS WHERE ITEM_ID = :item_id";
        $original_stmt = $conn->prepare($original_sql);
        $original_stmt->execute([':item_id' => $item_id]);
        
        $original_row = $original_stmt->fetch(PDO::FETCH_ASSOC);
        
        $original_name = $original_row['ITEM_NAME'] ?? 'N/A';
        $original_qty = $original_row['QUANTITY'] ?? '0';
        $original_cost = $original_row['TOTAL_COST'] ?? '0';
        $original_weight = $original_row['ITEM_NET_WEIGHT'] ?? '0';
        
        // 3. UPDATE Query (Updated ITEM_NET_WEIGHT)
        // Note: Removed TO_DATE() and replaced SYSDATE with NOW()
        $sql = "UPDATE ITEMS SET 
                ITEM_NAME = :item_name, 
                ITEM_TYPE_ID = :item_type_id, 
                QUANTITY = :item_quantity,
                ITEM_NET_WEIGHT = :weight, 
                UNIT_ID = :unit_id, 
                UNIT_COST = :unit_cost, 
                DATE_OF_PURCHASE = :date_of_purchase, 
                ITEM_DESCRIPTION = :item_description,
                LOCATION_ID = :location_id,
                BUILDING_ID = :building_id,
                PEN_ID = :pen_id,
                TOTAL_COST = :total_cost,
                DATE_UPDATED = NOW() 
                WHERE ITEM_ID = :item_id";
        
        $stmt = $conn->prepare($sql);
        
        $params = [
            ':item_name'        => $item_name,
            ':item_type_id'     => $item_type_id,
            ':item_quantity'    => $item_quantity,
            ':weight'           => $weight,
            ':unit_id'          => $unit_id,
            ':unit_cost'        => $unit_cost,
            ':date_of_purchase' => $date_of_purchase,
            ':item_description' => $item_description,
            ':location_id'      => $location_id,
            ':building_id'      => $building_id,
            ':pen_id'           => $pen_id,
            ':total_cost'       => $total_cost,
            ':item_id'          => $item_id
        ];

        // Execute Update
        if (!$stmt->execute($params)) {
            throw new Exception("Database Update Error.");
        }

        // 4. INSERT AUDIT LOG
        // Build log details to show what changed
        $logDetails = "Updated Animal Purchase (ID: $item_id). ";
        $logDetails .= "Name: $original_name -> $item_name. ";
        $logDetails .= "Cost: $original_cost -> $total_cost. ";
        
        if ($original_weight != $weight) {
            $logDetails .= "Weight: $original_weight -> $weight. ";
        }
        
        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'EDIT_ITEM', 'ITEMS', :details, :ip)";
        
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

        // 5. COMMIT EVERYTHING
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => '✅ Animal purchase updated successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        
        echo json_encode([
            'success' => false, 
            'message' => '❌ Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method or missing Item ID.'
    ]);
}
?>