<?php

// --- AUDIT LOG CONTEXT ---
// Ensure this runs if not already defined in the header
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : 1;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// --- 2. PROCESS SALE (SAVE LOGIC) ---
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_sale'])) {
    try {
        $conn->beginTransaction();

        $animal_id = $_POST['animal_id'];
        
        // 0. Fetch Tag Number for Audit Log (Before processing)
        $tagStmt = $conn->prepare("SELECT TAG_NO FROM animal_records WHERE ANIMAL_ID = ?");
        $tagStmt->execute([$animal_id]);
        $tag_no = $tagStmt->fetchColumn() ?? 'Unknown Tag';

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

        // 4. Insert Audit Log
        $audit_action = "SINGLE_SALE";
        $audit_details = "Sold Animal $tag_no to '{$_POST['customer_name']}'. Price: " . number_format($final_price, 2) . ". Profit: " . number_format($profit, 2);

        $audit_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                      VALUES (?, ?, ?, 'ANIMAL_SALES', ?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->execute([$user_id, $username, $audit_action, $audit_details, $ip_address]);

        $conn->commit();
        $message = "<div class='alert alert-success'>✅ Sale Confirmed! Profit: ₱" . number_format($profit, 2) . "</div>";
        
    } catch (Exception $e) {
        $conn->rollBack();
        $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}
?>