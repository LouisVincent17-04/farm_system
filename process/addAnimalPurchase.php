<?php
// process/addAnimalPurchase.php
session_start();
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

        $item_type_id = 13; // Animals
        $unit_id = $_POST['unit_id'] ?? null;
        $date_of_purchase = $_POST['date_of_purchase'] ?? date('Y-m-d'); 
        $item_description = $_POST['item_description'] ?? null;
        
        $location_id = !empty($_POST['location_id']) ? $_POST['location_id'] : null;
        $building_id = !empty($_POST['building_id']) ? $_POST['building_id'] : null;
        $pen_id      = !empty($_POST['pen_id']) ? $_POST['pen_id'] : null;

        // NEW: Capture Supplier and fallback to 'General Supplier' if empty
        $supplier = !empty(trim($_POST['supplier'] ?? '')) ? trim($_POST['supplier']) : 'General Supplier';
        
        // Capture Reference Number
        $reference_no = !empty(trim($_POST['reference_no'] ?? '')) ? trim($_POST['reference_no']) : null;

        // Retrieve Arrays of items
        $item_names = $_POST['item_names'] ?? [];
        $weights = $_POST['weights'] ?? [];
        $unit_costs = $_POST['unit_costs'] ?? [];

        if (empty($item_names)) {
            throw new Exception("No animals were submitted.");
        }

        $conn->beginTransaction();

        $sql = "INSERT INTO ITEMS (
                    ITEM_NAME, ITEM_TYPE_ID, QUANTITY, UNIT_ID, UNIT_COST, 
                    DATE_OF_PURCHASE, ITEM_DESCRIPTION, LOCATION_ID, 
                    BUILDING_ID, PEN_ID, TOTAL_COST, TOTAL_QTY, STATUS, ITEM_NET_WEIGHT,
                    supplier, reference_no
                ) VALUES (
                    :item_name, :item_type_id, 1, :unit_id, :unit_cost, 
                    :date_of_purchase, :item_description, :location_id, 
                    :building_id, :pen_id, :total_cost, 1, 0, :weight,
                    :supplier, :reference_no
                )";
        
        $stmt = $conn->prepare($sql);

        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'ADD_ITEM', 'ITEMS', :details, :ip)";
        $log_stmt = $conn->prepare($log_sql);

        $total_inserted = 0;

        // Loop through submitted arrays
        for ($i = 0; $i < count($item_names); $i++) {
            $current_name = trim($item_names[$i]);
            $current_weight = floatval($weights[$i] ?? 0);
            $current_cost = floatval($unit_costs[$i] ?? 0);

            if (empty($current_name)) continue;

            $params = [
                ':item_name'        => $current_name,
                ':item_type_id'     => $item_type_id,
                ':unit_id'          => $unit_id,
                ':unit_cost'        => $current_cost,
                ':date_of_purchase' => $date_of_purchase,
                ':item_description' => $item_description,
                ':location_id'      => $location_id,
                ':building_id'      => $building_id,
                ':pen_id'           => $pen_id,
                ':total_cost'       => $current_cost, // Quantity is 1, so cost = total
                ':weight'           => $current_weight,
                ':supplier'         => $supplier,
                ':reference_no' => $reference_no
            ];

            if ($stmt->execute($params)) {
                $new_item_id = $conn->lastInsertId();
                $total_inserted++;

                // Audit Log per item (now uses the guaranteed $supplier value)
                $logDetails = "Added Animal Purchase: $current_name (Supplier: $supplier, Cost: $current_cost). New ID: $new_item_id";
                $log_stmt->execute([
                    ':user_id'  => $user_id,
                    ':username' => $username,
                    ':details'  => $logDetails,
                    ':ip'       => $ip_address
                ]);
            } else {
                throw new Exception("Database Insert Error for item: $current_name.");
            }
        }

        if ($total_inserted === 0) {
            throw new Exception("No valid animal records were provided to insert.");
        }

        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "  Successfully recorded $total_inserted animal purchase(s)."
        ]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode([
            'success' => false, 
            'message' => '  Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method.'
    ]);
}
?>