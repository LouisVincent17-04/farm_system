<?php

// --- 2. PROCESS SALE (SAVE LOGIC) ---
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_sale'])) {
    try {
        $conn->beginTransaction();

        $animal_id = $_POST['animal_id'];
        
        // 1. Calculate Net Worth & Profit (Server-side validation)
        $net_worth = $_POST['cost_acquisition'] + 
                     $_POST['cost_feed'] + 
                     $_POST['cost_medication'] + 
                     $_POST['cost_vaccination'] + 
                     $_POST['cost_checkup'] + 
                     $_POST['cost_vitamins'] + 
                     $_POST['cost_overhead'];
        
        $final_price = $_POST['final_sale_price'];
        $profit = $final_price - $net_worth;

        // 2. Insert into Sales Table
        $sql = "INSERT INTO animal_sales 
            (animal_id, customer_name, weight_at_sale, price_per_kg, final_sale_price, 
             cost_acquisition, cost_feed_total, cost_medication_total, cost_vaccination_total, 
             cost_checkup_total, cost_vitamins_total, cost_overhead, total_net_worth, gross_profit, notes, created_by) 
            VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $animal_id, 
            $_POST['customer_name'], 
            $_POST['weight_at_sale'], 
            $final_price,
            $_POST['cost_acquisition'], 
            $_POST['cost_feed'], 
            $_POST['cost_medication'],
            $_POST['cost_vaccination'], 
            $_POST['cost_checkup'], 
            $_POST['cost_vitamins'],
            $_POST['cost_overhead'], 
            $net_worth, 
            $profit, 
            $_POST['notes'], 
            $_SESSION['user_id']
        ]);

        // 3. Update Animal Status to 'Sold' & Deactivate
        $updateStmt = $conn->prepare("UPDATE animal_records 
                                      SET CURRENT_STATUS = 'Sold', 
                                          IS_ACTIVE = 0, 
                                          CURRENT_ACTUAL_WEIGHT = ? 
                                      WHERE ANIMAL_ID = ?");
        $updateStmt->execute([$_POST['weight_at_sale'], $animal_id]);

        $conn->commit();
        $message = "<div class='alert alert-success'>✅ Sale Confirmed! Profit: ₱" . number_format($profit, 2) . "</div>";
        
    } catch (Exception $e) {
        $conn->rollBack();
        $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

?>