<?php
// process/saveAnimalFCR.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

// Turn off error display for clean JSON output
error_reporting(0);
ini_set('display_errors', 0);

$user_id = $_SESSION['user']['USER_ID'] ?? 1;

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
    
    // --- UPDATED LOGIC (User Request) ---
    // Gain = Feed * FCR
    // Est Weight = Birth + Gain
    
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

        // 1. UPDATE ANIMAL RECORD
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

        // 2. UPDATE CLASSIFICATION
        if ($class_id && $new_fcr > 0) {
            $sqlClass = "UPDATE animal_classifications 
                         SET FCR = :fcr 
                         WHERE CLASS_ID = :class_id";
            $stmt2 = $conn->prepare($sqlClass);
            $stmt2->execute([':fcr' => $new_fcr, ':class_id' => $class_id]);
        }

        // 3. INSERT LOG
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