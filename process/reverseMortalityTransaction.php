<?php
// process/reverseMortalityTransaction.php
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

    // 1. Find the Most Recent Mortality Transaction
    // transaction_type = 0 denotes Mortality in your schema
    $sql = "SELECT s.*, a.TAG_NO 
            FROM animal_sales s
            LEFT JOIN animal_records a ON s.animal_id = a.ANIMAL_ID
            WHERE s.transaction_type = 0 
            ORDER BY s.sale_id DESC LIMIT 1";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $last_trans = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$last_trans) {
        throw new Exception("No mortality records found to reverse.");
    }

    $mortality_id = $last_trans['sale_id'];
    $animal_id = $last_trans['animal_id'];
    $date_recorded = $last_trans['sale_date'];

    // 2. Resurrect Animal (Animal Records Table)
    // Set status back to 'Active' and IS_ACTIVE to 1
    $updateAnimal = $conn->prepare("UPDATE animal_records 
                                    SET CURRENT_STATUS = 'Active', 
                                        IS_ACTIVE = 1,
                                        UPDATED_AT = NOW() 
                                    WHERE ANIMAL_ID = ?");
    $updateAnimal->execute([$animal_id]);

    // 3. Delete the Mortality Record
    $deleteTrans = $conn->prepare("DELETE FROM animal_sales WHERE sale_id = ?");
    $deleteTrans->execute([$mortality_id]);

    // 4. Audit Log
    $details = "Reversed Mortality #$mortality_id for Animal {$last_trans['TAG_NO']}. Animal status restored to Active.";
    $audit = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                             VALUES (?, ?, 'REVERSE_MORTALITY', 'ANIMAL_SALES', ?, ?)");
    $audit->execute([$user_id, $username, $details, $ip]);

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Reversal Successful! Animal {$last_trans['TAG_NO']} has been restored to active inventory."
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