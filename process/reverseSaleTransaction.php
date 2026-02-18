<?php
// process/reverseSaleTransaction.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

// User Context
$user_id = $_SESSION['user']['USER_ID'] ?? 1;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip = $_SERVER['REMOTE_ADDR'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid Request Method.");
    }

    $conn->beginTransaction();

    // 1. Find the Most Recent Sale Transaction
    // We order by sale_id DESC to get the very last one.
    $sql = "SELECT s.*, a.TAG_NO 
            FROM animal_sales s
            LEFT JOIN animal_records a ON s.animal_id = a.ANIMAL_ID
            ORDER BY s.sale_id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $last_trans = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$last_trans) {
        throw new Exception("No sale transactions found to reverse.");
    }

    $sale_id = $last_trans['sale_id'];
    $animal_id = $last_trans['animal_id'];
    $customer = $last_trans['customer_name'];
    $amount = $last_trans['final_sale_price'];

    // 2. Revert Animal Status (Animal Records Table)
    // Set status back to 'Active' (or whatever default active status is used) and IS_ACTIVE to 1
    // We also might want to clear the CURRENT_ACTUAL_WEIGHT if it was updated during sale, 
    // but usually keeping the weight is fine or safer. Let's just reactivate.
    $updateAnimal = $conn->prepare("UPDATE animal_records 
                                    SET CURRENT_STATUS = 'Active', 
                                        IS_ACTIVE = 1,
                                        UPDATED_AT = NOW() 
                                    WHERE ANIMAL_ID = ?");
    $updateAnimal->execute([$animal_id]);

    // 3. Delete the Sale Transaction Record
    $deleteTrans = $conn->prepare("DELETE FROM animal_sales WHERE sale_id = ?");
    $deleteTrans->execute([$sale_id]);

    // 4. Audit Log
    $details = "Reversed Sale #$sale_id for Animal {$last_trans['TAG_NO']}. Customer: $customer. Amount: ₱" . number_format($amount, 2);
    $audit = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                             VALUES (?, ?, 'REVERSE_SALE', 'ANIMAL_SALES', ?, ?)");
    $audit->execute([$user_id, $username, $details, $ip]);

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Reversal Successful! Animal {$last_trans['TAG_NO']} is now Active."
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>