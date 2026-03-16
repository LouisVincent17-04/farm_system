<?php
// process/addGroupAnimalSell.php
session_start();
header('Content-Type: application/json');
include '../config/Connection.php';

// Force JSON response
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid Request Method.']); exit;
}

try {
    $conn->beginTransaction();

    $animal_ids = $_POST['animal_ids'] ?? [];
    $costs = $_POST['costs'] ?? [];
    $buyer_name = trim($_POST['customer_name'] ?? 'Unknown Buyer');
    $notes = trim($_POST['notes'] ?? '');
    
    // Optional Lump Sum Override
    $exact_lump_sum_total = isset($_POST['exact_lump_sum_total']) ? floatval($_POST['exact_lump_sum_total']) : 0;
    
    $current_user = $_SESSION['user']['USER_ID'] ?? 0;

    if (empty($animal_ids)) {
        throw new Exception("No animals selected for sale.");
    }
    if (empty($buyer_name)) {
        throw new Exception("Buyer name is required.");
    }

    $batch_id = uniqid('SALE-');

    $sql = "INSERT INTO animal_sales 
            (animal_id, customer_name, weight_at_sale, price_per_kg, final_sale_price, 
             cost_acquisition, cost_feed_total, cost_medication_total, cost_vaccination_total, 
             cost_checkup_total, cost_vitamins_total, cost_overhead, total_net_worth, gross_profit, notes, created_by, batch_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $updateStmt = $conn->prepare("UPDATE animal_records SET CURRENT_STATUS = 'Sold', IS_ACTIVE = 0, CURRENT_ACTUAL_WEIGHT = ? WHERE ANIMAL_ID = ?");

    $count = count($animal_ids);
    $running_total_revenue = 0; // Tracks running total to adjust rounding error in Lump Sum
    $items_processed = 0;

    foreach ($animal_ids as $index => $id) {
        if (!isset($costs[$id])) continue;

        $data = $costs[$id];
        $items_processed++;
        
        $weight = floatval($data['weight'] ?? 0);
        $overhead = floatval($data['overhead'] ?? 0);
        
        // Price logic (Handles lump sum exact distribution without rounding gaps)
        $sale_price = floatval($data['sale_price'] ?? 0);
        
        if ($exact_lump_sum_total > 0) {
            if ($items_processed === $count) {
                // Last item gets the exact remaining remainder
                $sale_price = $exact_lump_sum_total - $running_total_revenue; 
            }
            $running_total_revenue += $sale_price;
        }

        $net_worth = floatval($data['acq']) + floatval($data['feed']) + floatval($data['med']) + 
                     floatval($data['vac']) + floatval($data['chk']) + floatval($data['vit']) + $overhead;
        
        $profit = $sale_price - $net_worth;
        $price_per_kg = ($weight > 0) ? ($sale_price / $weight) : 0;

        // Insert Sales Log
        $stmt->execute([
            $id, $buyer_name, $weight, $price_per_kg, $sale_price,
            $data['acq'], $data['feed'], $data['med'], $data['vac'],
            $data['chk'], $data['vit'], $overhead, $net_worth, $profit, 
            $notes, $current_user, $batch_id
        ]);

        // Archive Animal profile
        $updateStmt->execute([$weight, $id]);
    }

    if ($items_processed === 0) {
         throw new Exception("Invalid cost mapping sent to server.");
    }

    // AUDIT LOG
    $audit_msg = "Group Sale (Batch: $batch_id) processed for $items_processed animals to Buyer: $buyer_name.";
    $audit = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, 'GROUP_SALE', 'ANIMAL_SALES', ?, ?)");
    $username = $_SESSION['user']['FULL_NAME'] ?? 'System';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $audit->execute([$current_user, $username, $audit_msg, $ip]);

    $conn->commit();
    ob_end_clean();
    echo json_encode([
        'success' => true, 
        'message' => "Successfully processed sale for $items_processed animals.", 
        'batch_id' => $batch_id
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>