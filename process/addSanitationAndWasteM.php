<?php
session_start(); // 1. Start Session
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

header('Content-Type: application/json');

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        // 1. Retrieve Basic Info
        $item_name = $_POST['item_name'];
        $item_type_id = 5; // Sanitation & Waste Management
        $item_quantity = floatval($_POST['item_quantity'] ?? 0);
        $item_net_weight = floatval($_POST['item_net_weight'] ?? 0);
        $unit_id = $_POST['unit_id'];
        $unit_cost = floatval($_POST['unit_cost'] ?? 0);
        $item_category = $_POST['item_category'];
        $date_of_purchase = $_POST['date_of_purchase'];
        $item_description = $_POST['item_description'] ?? null;
        
        // 2. Retrieve New Location Info
        $location_id = !empty($_POST['location_id']) ? $_POST['location_id'] : null;
        $building_id = !empty($_POST['building_id']) ? $_POST['building_id'] : null;
        $pen_id      = !empty($_POST['pen_id']) ? $_POST['pen_id'] : null;

        // 3. Handle zero/null values for weight/quantity
        $item_net_weight = ($item_net_weight <= 0) ? null : $item_net_weight;
        $item_quantity = ($item_quantity <= 0) ? null : $item_quantity;

        // Validation
        if (empty($item_name) || empty($unit_id) || empty($date_of_purchase) || $item_quantity === null) {
             throw new Exception("Missing required fields (Name, Quantity, Unit, Date).");
        }

        // 4. Calculate Total Cost
        $total_cost = $item_quantity * $unit_cost;

        // ---------------------------------------------------------
        // START TRANSACTION
        // ---------------------------------------------------------
        $conn->beginTransaction();

        // 5. INSERT ITEM
        // Note: Removed TO_DATE(). MySQL handles 'YYYY-MM-DD' strings automatically.
        $sql = "INSERT INTO ITEMS (
                    ITEM_NAME, ITEM_TYPE_ID, QUANTITY, ITEM_NET_WEIGHT, UNIT_ID, UNIT_COST, 
                    ITEM_CATEGORY, DATE_OF_PURCHASE, ITEM_DESCRIPTION, LOCATION_ID, 
                    BUILDING_ID, PEN_ID, TOTAL_COST, STATUS
                ) VALUES (
                    :item_name, :item_type_id, :item_quantity, :item_net_weight, :unit_id, :unit_cost, 
                    :item_category, :date_of_purchase, :item_description, :location_id, 
                    :building_id, :pen_id, :total_cost, 0
                )";
        
        $stmt = $conn->prepare($sql);
        
        $params = [
            ':item_name'        => $item_name,
            ':item_type_id'     => $item_type_id,
            ':item_quantity'    => $item_quantity,
            ':item_net_weight'  => $item_net_weight,
            ':unit_id'          => $unit_id,
            ':unit_cost'        => $unit_cost,
            ':item_category'    => $item_category,
            ':date_of_purchase' => $date_of_purchase,
            ':item_description' => $item_description,
            ':location_id'      => $location_id,
            ':building_id'      => $building_id,
            ':pen_id'           => $pen_id,
            ':total_cost'       => $total_cost
        ];
        
        // Execute Insert
        if ($stmt->execute($params)) {
            
            // 6. Fetch the last inserted ID
            $new_item_id = $conn->lastInsertId();
            
            if (!$new_item_id) {
                 throw new Exception("Failed to retrieve new Item ID for logging.");
            }

            // 7. INSERT AUDIT LOG
            $logDetails = "Added new Sanitation Item: $item_name (Qty: $item_quantity, Cost: $total_cost). New ID: $new_item_id";
            
            $log_sql = "INSERT INTO AUDIT_LOGS 
                        (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                        VALUES 
                        (:user_id, :username, 'ADD_ITEM', 'ITEMS', :details, :ip)";
            
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
            
            echo json_encode([
                'success' => true, 
                'message' => '✅ Purchase recorded successfully (ID: ' . $new_item_id . ')'
            ]);
            
        } else {
            throw new Exception("Database Insert Error.");
        }
        
    } catch (Exception $e) {
        // Rollback if anything failed
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
        'message' => 'Invalid request method.'
    ]);
}
?>