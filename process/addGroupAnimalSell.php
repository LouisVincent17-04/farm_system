<?php
// process/addGroupAnimalSell.php
header('Content-Type: application/json');
include '../config/Connection.php';
include '../security/checkRole.php';
session_start();

$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : 1;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid request.");

    $selected_ids      = $_POST['animal_ids'] ?? [];
    $costs_data        = $_POST['costs'] ?? [];
    $customer_name     = trim($_POST['customer_name'] ?? '');
    $notes             = trim($_POST['notes'] ?? '');
    
    if (count($selected_ids) === 0) throw new Exception("No animals selected.");
    if (empty($customer_name)) throw new Exception("Buyer required.");

    $batch_id = uniqid('BATCH-');
    $notes_with_batch = $notes . " [Batch: $batch_id]";
    $total_sale_price_calculated = 0; 

    $conn->beginTransaction();

    $insertSql = "INSERT INTO animal_sales 
        (animal_id, customer_name, weight_at_sale, price_per_kg, final_sale_price, 
         cost_acquisition, cost_feed_total, cost_medication_total, cost_vaccination_total, 
         cost_checkup_total, cost_vitamins_total, cost_overhead, total_net_worth, gross_profit, notes, created_by) 
        VALUES 
        (:aid, :cust, :wgt, 0, :price, :c_acq, :c_feed, :c_med, :c_vac, :c_chk, :c_vit, 0, :net, :prof, :notes, :user)";
    
    $insertStmt = $conn->prepare($insertSql);
    $updateStmt = $conn->prepare("UPDATE animal_records SET CURRENT_STATUS = 'Sold', IS_ACTIVE = 0 WHERE ANIMAL_ID = :aid");

    foreach ($selected_ids as $id) {
        if (!isset($costs_data[$id])) throw new Exception("Data error for ID: $id");
        $c = $costs_data[$id];
        
        $sale_price = floatval($c['sale_price']);
        if ($sale_price <= 0) throw new Exception("Sale price must be greater than 0 for all animals.");

        $total_sale_price_calculated += $sale_price;

        $acq = floatval($c['acq']);
        $feed = floatval($c['feed']);
        $med = floatval($c['med']);
        $vac = floatval($c['vac']);
        $vit = floatval($c['vit']);
        $chk = floatval($c['chk']);
        $wgt = floatval($c['weight']);

        $net_worth = $acq + $feed + $med + $vac + $vit + $chk;
        $gross_profit = $sale_price - $net_worth;

        $insertStmt->execute([
            ':aid' => $id, ':cust' => $customer_name, ':wgt' => $wgt, ':price' => $sale_price,
            ':c_acq' => $acq, ':c_feed' => $feed, ':c_med' => $med, ':c_vac' => $vac, ':c_chk' => $chk, ':c_vit' => $vit,
            ':net' => $net_worth, ':prof' => $gross_profit, ':notes' => $notes_with_batch, ':user' => $user_id
        ]);
        $updateStmt->execute([':aid' => $id]);
    }

    $audit_details = "Sold " . count($selected_ids) . " animals. Total: " . number_format($total_sale_price_calculated, 2);
    $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, 'GROUP_SALE', 'ANIMAL_SALES', ?, ?)")
         ->execute([$user_id, $username, $audit_details, $ip_address]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Sold successfully.", 'batch_id' => $batch_id]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>