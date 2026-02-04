<?php
// process/saveAnimalFCR.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

// Turn off error display for clean JSON output
error_reporting(0);
ini_set('display_errors', 0);

// --- AUDIT LOG CONTEXT ---
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : 1;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Retrieve POST Data
    $animal_id    = $_POST['animal_id'] ?? null;
    $pen_id       = $_POST['pen_id'] ?? null;
    $class_id     = $_POST['class_id'] ?? null;
    $birth_weight = floatval($_POST['birth_weight'] ?? 0);
    $feed_share   = floatval($_POST['feed_share'] ?? 0);
    $actual_weight= floatval($_POST['actual_weight'] ?? 0);
    $new_fcr      = floatval($_POST['fcr'] ?? 0);
    $weigh_date   = $_POST['weigh_date'] ?? date('Y-m-d');
    
    // --- CALCULATION LOGIC ---
    // Gain = Feed Share * FCR
    // Est Weight = Birth Weight + Gain
    $gain_est = $feed_share * $new_fcr; 
    $est_weight = $birth_weight + $gain_est;
    
    // Variance = Actual - Estimated
    $variance = $actual_weight - $est_weight; 

    if (!$animal_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data.']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // 2. UPDATE ANIMAL RECORD
        $sqlAnimal = "UPDATE animal_records 
                      SET CURRENT_ESTIMATED_WEIGHT = :est_weight,
                          CURRENT_ACTUAL_WEIGHT = :act_weight,
                          UPDATED_AT = NOW() 
                      WHERE ANIMAL_ID = :id";
        $stmt1 = $conn->prepare($sqlAnimal);
        $stmt1->execute([
            ':est_weight' => $est_weight,
            ':act_weight' => $actual_weight,
            ':id' => $animal_id
        ]);

        // 3. UPDATE CLASSIFICATION FCR (Optional, if class_id provided)
        if ($class_id && $new_fcr > 0) {
            $sqlClass = "UPDATE animal_classifications 
                         SET FCR = :fcr 
                         WHERE CLASS_ID = :class_id";
            $stmt2 = $conn->prepare($sqlClass);
            $stmt2->execute([':fcr' => $new_fcr, ':class_id' => $class_id]);
        }

        // 4. INSERT FCR LOG
        $sqlLog = "INSERT INTO animal_fcr_logs 
                   (ANIMAL_ID, PEN_ID, LOG_DATE, BIRTH_WEIGHT, FEED_SHARE_KG, 
                    FCR_USED, TOTAL_GAIN_EST, ESTIMATED_WEIGHT, ACTUAL_WEIGHT, 
                    VARIANCE, CREATED_BY, CREATED_AT) 
                   VALUES 
                   (:aid, :pid, :ldate, :bweight, :feed, 
                    :fcr, :gain, :est, :act, 
                    :var, :user, NOW())";
        
        $stmt3 = $conn->prepare($sqlLog);
        $stmt3->execute([
            ':aid'     => $animal_id,
            ':pid'     => $pen_id,
            ':ldate'   => $weigh_date,
            ':bweight' => $birth_weight,
            ':feed'    => $feed_share,
            ':fcr'     => $new_fcr,
            ':gain'    => $gain_est,
            ':est'     => $est_weight,
            ':act'     => $actual_weight,
            ':var'     => $variance,
            ':user'    => $user_id
        ]);

        // 5. INSERT AUDIT LOG
        if ($stmt3->rowCount() > 0) {
            // Fetch Tag No for clearer logs
            $stmtTag = $conn->prepare("SELECT TAG_NO FROM animal_records WHERE ANIMAL_ID = ?");
            $stmtTag->execute([$animal_id]);
            $tag_no = $stmtTag->fetchColumn() ?: 'Unknown';

            $audit_action = "UPDATE_FCR_WEIGHT";
            $audit_details = "Updated FCR/Weight for Animal $tag_no. Actual: $actual_weight kg. Est: $est_weight kg. FCR: $new_fcr";

            $audit_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                          VALUES (?, ?, ?, 'ANIMAL_RECORDS', ?, ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $audit_stmt->execute([$user_id, $username, $audit_action, $audit_details, $ip_address]);
        }

        $conn->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'FCR updated. Weight recalculated using (Feed * FCR).'
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request Method']);
}
?>