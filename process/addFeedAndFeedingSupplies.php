<?php
// process/addFeedAndFeedingSupplies.php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';
header('Content-Type: application/json');

$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($conn)) throw new Exception("Database connection failed.");

        // Read the JSON payload sent from the dynamic table
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!$data || empty($data['items'])) {
            throw new Exception("No items provided.");
        }

        // 1. Extract Global Invoice Info
        $date_of_purchase = $data['date_of_purchase'] ?? null;
        $location_id = $data['location_id'] ?? null;
        $building_id = !empty($data['building_id']) ? $data['building_id'] : null;
        $pen_id      = !empty($data['pen_id']) ? $data['pen_id'] : null;
        $supplier = !empty(trim($data['supplier'] ?? '')) ? trim($data['supplier']) : 'General Supplier';
        $reference_no = !empty(trim($data['reference_no'] ?? '')) ? trim($data['reference_no']) : null;

        if(empty($location_id) || empty($date_of_purchase)) {
            throw new Exception("Location and Purchase Date are required.");
        }

        $conn->beginTransaction();
        $inserted_count = 0;

        // Prepare statements for bulk insert
        $sql = "INSERT INTO ITEMS (
                    ITEM_NAME, ITEM_TYPE_ID, QUANTITY, ITEM_NET_WEIGHT, UNIT_ID, UNIT_COST, 
                    ITEM_CATEGORY, DATE_OF_PURCHASE, EXPIRATION_DATE, ITEM_DESCRIPTION, LOCATION_ID, 
                    BUILDING_ID, PEN_ID, TOTAL_COST, STATUS, SUPPLIER, REFERENCE_NO
                ) VALUES (
                    :item_name, :item_type_id, :item_quantity, :item_net_weight, :unit_id, :unit_cost, 
                    :item_category, :date_of_purchase, :expiration_date, :item_description, :location_id, 
                    :building_id, :pen_id, :total_cost, 0, :supplier, :reference_no
                )";
        $stmt = $conn->prepare($sql);

        $log_sql = "INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (:user_id, :username, 'ADD_ITEM', 'ITEMS', :details, :ip)";
        $log_stmt = $conn->prepare($log_sql);

        // 2. Loop through every dynamically added row
        foreach ($data['items'] as $item) {
            $item_name = trim($item['item_name']);
            $item_quantity = floatval($item['quantity'] ?? 0);
            $unit_cost = floatval($item['unit_cost'] ?? 0);
            $unit_id = $item['unit_id'];
            
            if (empty($item_name) || empty($unit_id) || $item_quantity <= 0) {
                throw new Exception("Invalid item data. Ensure all rows have a Name, Unit, and Quantity > 0.");
            }

            $item_net_weight = floatval($item['net_weight'] ?? 0);
            $item_net_weight = ($item_net_weight <= 0) ? null : $item_net_weight;
            $expiration_date = !empty($item['expiration_date']) ? $item['expiration_date'] : null;

            if($expiration_date && $expiration_date < $date_of_purchase) {
                throw new Exception("Expiration date for '$item_name' cannot be before the purchase date.");
            }

            $total_cost = $item_quantity * $unit_cost;

            $params = [
                ':item_name'        => $item_name,
                ':item_type_id'     => 2, // Feeds & Feeding Supplies
                ':item_quantity'    => $item_quantity,
                ':item_net_weight'  => $item_net_weight,
                ':unit_id'          => $unit_id,
                ':unit_cost'        => $unit_cost,
                ':item_category'    => $item['category'] ?? '1',
                ':date_of_purchase' => $date_of_purchase,
                ':expiration_date'  => $expiration_date,
                ':item_description' => $item['description'] ?? null,
                ':location_id'      => $location_id,
                ':building_id'      => $building_id,
                ':pen_id'           => $pen_id,
                ':total_cost'       => $total_cost,
                ':supplier'         => $supplier,
                ':reference_no'     => $reference_no
            ];

            if (!$stmt->execute($params)) {
                throw new Exception("Failed to insert item: $item_name");
            }

            // Log the action
            $logDetails = "Added Feed Purchase: $item_name (Qty: $item_quantity, Cost: $total_cost). Expiry: " . ($expiration_date ?? 'N/A');
            $log_stmt->execute([
                ':user_id'  => $user_id,
                ':username' => $username,
                ':details'  => $logDetails,
                ':ip'       => $ip_address
            ]);
            
            $inserted_count++;
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "✅ Successfully recorded $inserted_count feed item(s)."]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => '❌ Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>