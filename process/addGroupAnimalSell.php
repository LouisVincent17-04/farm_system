<?php
// process/addGroupAnimalSell.php
header('Content-Type: application/json');

include '../config/Connection.php';
include '../security/checkRole.php';
session_start();

// 1. Role Check (Admin/Farm Admin only usually)
if (!isset($_SESSION['role']) || $_SESSION['role'] > 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    // 2. Retrieve Inputs
    $selected_ids     = $_POST['animal_ids'] ?? [];
    $costs_data       = $_POST['costs'] ?? []; // Array passed from hidden inputs
    $total_sale_price = floatval($_POST['total_batch_price'] ?? 0);
    $total_overhead   = floatval($_POST['total_overhead'] ?? 0);
    $customer_name    = trim($_POST['customer_name'] ?? '');
    $notes            = trim($_POST['notes'] ?? '');
    $current_user     = $_SESSION['user_id'] ?? 0;

    // 3. Validation
    $count = count($selected_ids);
    if ($count === 0) {
        throw new Exception("No animals selected for sale.");
    }
    if (empty($customer_name)) {
        throw new Exception("Buyer name is required.");
    }
    if ($total_sale_price <= 0) {
        throw new Exception("Total sale price must be greater than 0.");
    }

    // 4. Calculate Distributed Values (Averages)
    $price_per_head    = $total_sale_price / $count;
    $overhead_per_head = $total_overhead / $count;

    // Start Database Transaction
    $conn->beginTransaction();

    // Prepare Statements
    $insertSql = "INSERT INTO animal_sales 
        (animal_id, customer_name, weight_at_sale, price_per_kg, final_sale_price, 
         cost_acquisition, cost_feed_total, cost_medication_total, cost_vaccination_total, 
         cost_checkup_total, cost_vitamins_total, cost_overhead, total_net_worth, gross_profit, notes, created_by) 
        VALUES 
        (:aid, :cust, :wgt, 0, :price, :c_acq, :c_feed, :c_med, :c_vac, :c_chk, :c_vit, :c_ovr, :net, :prof, :notes, :user)";
    
    $insertStmt = $conn->prepare($insertSql);

    $updateSql = "UPDATE animal_records SET CURRENT_STATUS = 'Sold', IS_ACTIVE = 0 WHERE ANIMAL_ID = :aid";
    $updateStmt = $conn->prepare($updateSql);

    // 5. Loop through selected animals
    foreach ($selected_ids as $id) {
        if (!isset($costs_data[$id])) {
            throw new Exception("Cost data missing for animal ID: $id");
        }

        $c = $costs_data[$id];
        
        // Extract Individual Costs (Passed from frontend hidden inputs)
        $acq    = floatval($c['acq']);
        $feed   = floatval($c['feed']);
        $med    = floatval($c['med']);
        $vac    = floatval($c['vac']);
        $vit    = floatval($c['vit']);
        $chk    = floatval($c['chk']);
        $weight = floatval($c['weight']);

        // Calculate Individual Net Worth & Profit
        // Net Worth = Sum of all costs + Allocated Overhead
        $net_worth = $acq + $feed + $med + $vac + $vit + $chk + $overhead_per_head;
        
        // Profit = Allocated Sale Price - Net Worth
        $gross_profit = $price_per_head - $net_worth;

        // Execute Insert
        $insertStmt->execute([
            ':aid'     => $id,
            ':cust'    => $customer_name,
            ':wgt'     => $weight,
            ':price'   => $price_per_head,
            ':c_acq'   => $acq,
            ':c_feed'  => $feed,
            ':c_med'   => $med,
            ':c_vac'   => $vac,
            ':c_chk'   => $chk,
            ':c_vit'   => $vit,
            ':c_ovr'   => $overhead_per_head,
            ':net'     => $net_worth,
            ':prof'    => $gross_profit,
            ':notes'   => $notes,
            ':user'    => $current_user
        ]);

        // Execute Archive (Update Status)
        $updateStmt->execute([':aid' => $id]);
    }

    // 6. Commit Transaction
    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Successfully sold $count animals."]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>