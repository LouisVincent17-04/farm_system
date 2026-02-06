<?php
// process/addBatchMortality.php
header('Content-Type: application/json');
include '../config/Connection.php';
session_start();

$user_id = $_SESSION['user']['USER_ID'] ?? 0;
// Helper to get IP address
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '::1';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['records'])) {
        throw new Exception("No records provided.");
    }

    $records = $input['records'];
    $date = $input['date'] ?? date('Y-m-d H:i:s');
    $customer_name = !empty($input['customer_name']) ? $input['customer_name'] : 'N/A';

    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    $conn->beginTransaction();

    // ---------------------------------------------------------
    // 0. FETCH USERNAME (Required for your audit_log table)
    // ---------------------------------------------------------
    $stmtUser = $conn->prepare("SELECT FULL_NAME FROM users WHERE USER_ID = ?");
    $stmtUser->execute([$user_id]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $username = $userRow['FULL_NAME'] ?? 'Unknown User';

    // ---------------------------------------------------------
    // PREPARE STATEMENTS
    // ---------------------------------------------------------
    
    // 1. Fetch Animal Costs
    $stmtCosts = $conn->prepare("
        SELECT 
            ar.ACQUISITION_COST,
            COALESCE((SELECT SUM(TRANSACTION_COST) FROM feed_transactions WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as feed,
            COALESCE((SELECT SUM(TOTAL_COST) FROM treatment_transactions WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as meds,
            COALESCE((SELECT SUM(VACCINATION_COST + VACCINE_COST) FROM vaccination_records WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as vac,
            COALESCE((SELECT SUM(TOTAL_COST) FROM vitamins_supplements_transactions WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as vit,
            COALESCE((SELECT SUM(COST) FROM check_ups WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as chk
        FROM animal_records ar 
        WHERE ar.ANIMAL_ID = ?
    ");

    // 2. Insert Sale/Mortality Record
    $stmtInsert = $conn->prepare("
        INSERT INTO animal_sales 
        (animal_id, sale_date, customer_name, final_sale_price, 
         cost_acquisition, cost_feed_total, cost_medication_total, cost_vaccination_total, 
         cost_checkup_total, cost_vitamins_total, total_net_worth, gross_profit, 
         notes, created_by, transaction_type) 
        VALUES 
        (:aid, :date, :cust, :price, 
         :acq, :feed, :med, :vac, 
         :chk, :vit, :net, :prof, 
         :notes, :uid, 0)
    ");

    // 3. Update Animal Status
    $stmtUpdate = $conn->prepare("UPDATE animal_records SET CURRENT_STATUS = 'Deceased', IS_ACTIVE = 0 WHERE ANIMAL_ID = ?");

    // 4. Audit Log (Matches your table structure)
    // Columns: LOG_ID, USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS, LOG_DATE
    $stmtAudit = $conn->prepare("
        INSERT INTO audit_logs 
        (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS, LOG_DATE) 
        VALUES 
        (:uid, :uname, :type, :tbl, :details, :ip, NOW())
    ");

    $count = 0;
    $tags_processed = []; // Store tags for the audit log details

    foreach ($records as $rec) {
        $animal_id = $rec['animal_id'];
        $notes = $rec['remarks'] ?? 'Batch Mortality';
        $recovered = floatval($rec['recovered_cost'] ?? 0);

        // Get Animal Tag for the log
        $stmtTag = $conn->prepare("SELECT TAG_NO FROM animal_records WHERE ANIMAL_ID = ?");
        $stmtTag->execute([$animal_id]);
        $tag = $stmtTag->fetchColumn();
        if($tag) $tags_processed[] = $tag;

        // A. Calculate Investment
        $stmtCosts->execute([$animal_id]);
        $costs = $stmtCosts->fetch(PDO::FETCH_ASSOC);

        if (!$costs) continue; 

        $acq = $costs['ACQUISITION_COST'];
        $feed = $costs['feed'];
        $med = $costs['meds'];
        $vac = $costs['vac'];
        $vit = $costs['vit'];
        $chk = $costs['chk'];
        
        $total_net_worth = $acq + $feed + $med + $vac + $vit + $chk; 
        $gross_profit = $recovered - $total_net_worth; 

        // B. Insert Record
        $stmtInsert->execute([
            ':aid' => $animal_id,
            ':date' => $date,
            ':cust' => $customer_name,
            ':price' => $recovered,
            ':acq' => $acq,
            ':feed' => $feed,
            ':med' => $med,
            ':vac' => $vac,
            ':chk' => $chk,
            ':vit' => $vit,
            ':net' => $total_net_worth,
            ':prof' => $gross_profit,
            ':notes' => $notes,
            ':uid' => $user_id
        ]);

        // C. Update Status
        $stmtUpdate->execute([$animal_id]);
        
        $count++;
    }

    // ---------------------------------------------------------
    // D. INSERT AUDIT LOG
    // ---------------------------------------------------------
    if ($count > 0) {
        // Create a summary string of tags (e.g., "Tags: 1001, 1002, 1005")
        $tag_list = implode(', ', array_slice($tags_processed, 0, 10)); // Limit to first 10 to save space
        if (count($tags_processed) > 10) $tag_list .= ", ... (" . (count($tags_processed) - 10) . " more)";

        $actionDetails = "Recorded batch mortality for $count animals. Tags: [$tag_list]. Buyer: $customer_name.";

        $stmtAudit->execute([
            ':uid'     => $user_id,
            ':uname'   => $username,
            ':type'    => 'ADD_MORTALITY',
            ':tbl'     => 'ANIMAL_SALES',
            ':details' => $actionDetails,
            ':ip'      => $ip_address
        ]);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Successfully recorded $count mortality events."]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>