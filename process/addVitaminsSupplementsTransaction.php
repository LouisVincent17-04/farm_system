<?php
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

    $animal_id   = $_POST['animal_id'] ?? null;
    $item_id     = $_POST['item_id'] ?? null; // ID from VITAMINS_SUPPLEMENTS
    $dosage      = trim($_POST['dosage'] ?? '');
    $quantity    = floatval($_POST['quantity_used'] ?? 0);
    $trans_date  = $_POST['transaction_date'] ?? date('Y-m-d');
    $remarks     = trim($_POST['remarks'] ?? '');

    // 1. Basic Validation
    if (!$animal_id || !$item_id || $quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Missing fields or invalid quantity.']);
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

        // 2. Lock & Fetch Inventory Data (Including Cost)
        $stockSql = "SELECT TOTAL_STOCK, TOTAL_COST, SUPPLY_NAME 
                     FROM VITAMINS_SUPPLEMENTS 
                     WHERE SUPPLY_ID = :id 
                     FOR UPDATE";
        $stockStmt = $conn->prepare($stockSql);
        $stockStmt->execute([':id' => $item_id]);
        
        $stockRow = $stockStmt->fetch(PDO::FETCH_ASSOC);

        if (!$stockRow) {
            throw new Exception("Supplement item not found in inventory.");
        }

        $supply_name   = $stockRow['SUPPLY_NAME'];
        $currentStock  = floatval($stockRow['TOTAL_STOCK']);
        $currentValue  = floatval($stockRow['TOTAL_COST']);

        // Check Stock Availability
        if ($currentStock < $quantity) {
            throw new Exception("Insufficient stock for {$supply_name}. Available: {$currentStock}");
        }

        // ---------------------------------------------------------
        // 3. CALCULATE COST (Weighted Average)
        // Unit Price = Total Value / Total Stock
        // Transaction Cost = Unit Price * Quantity Used
        // ---------------------------------------------------------
        $price_per_unit = ($currentStock > 0) ? ($currentValue / $currentStock) : 0;
        $transaction_cost = $price_per_unit * $quantity;

        // 4. Get Animal Tag (For Log)
        $tagStmt = $conn->prepare("SELECT TAG_NO FROM ANIMAL_RECORDS WHERE ANIMAL_ID = ?");
        $tagStmt->execute([$animal_id]);
        $animal_tag = $tagStmt->fetchColumn() ?: 'Unknown';

        // 5. Update Inventory (Deduct Stock & Value)
        $updateStockSql = "UPDATE VITAMINS_SUPPLEMENTS 
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
            throw new Exception("Inventory update failed.");
        }

        // 6. Insert Transaction Record (With Cost)
        $insertSql = "INSERT INTO VITAMINS_SUPPLEMENTS_TRANSACTIONS 
                      (ANIMAL_ID, ITEM_ID, DOSAGE, QUANTITY_USED, TOTAL_COST, TRANSACTION_DATE, REMARKS, CREATED_AT) 
                      VALUES 
                      (:aid, :iid, :dos, :qty, :cost, :date, :rem, NOW())";

        $stmt = $conn->prepare($insertSql);
        $stmt->execute([
            ':aid'  => $animal_id,
            ':iid'  => $item_id,
            ':dos'  => $dosage,
            ':qty'  => $quantity,
            ':cost' => $transaction_cost,
            ':date' => $trans_date,
            ':rem'  => $remarks
        ]);

        // 7. Audit Log
        $cost_fmt = number_format($transaction_cost, 2);
        $logDetails = "Gave $quantity of $supply_name (Cost: ₱$cost_fmt) to Animal $animal_tag. Dosage: $dosage";

        $logSql = "INSERT INTO AUDIT_LOGS 
                   (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                   VALUES 
                   (:uid, :uname, 'ADD_VITAMIN_TXN', 'VITAMINS_TRANSACTIONS', :det, :ip)";
        
        $conn->prepare($logSql)->execute([
            ':uid' => $user_id, 
            ':uname' => $username, 
            ':det' => $logDetails, 
            ':ip' => $ip_address
        ]);

        // 8. Commit
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "✅ Record saved! Cost: ₱$cost_fmt"
        ]);

    } catch (Exception $e) {
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