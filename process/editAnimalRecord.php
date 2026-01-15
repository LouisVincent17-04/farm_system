<?php
// ../process/editAnimalRecord.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

session_start();
include '../config/Connection.php';
include '../security/checkRole.php';

$acting_user_id = !empty($_SESSION['user']['USER_ID']) ? (int)$_SESSION['user']['USER_ID'] : null;
$acting_username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $animal_id = $_POST['animal_id'];
    $tag_no = strtoupper(trim($_POST['tag_no'])); 
    $sex = $_POST['sex'];
    $animal_type_id = $_POST['animal_type_id'];
    $breed_id = $_POST['breed_id'];
    $birth_date = !empty($_POST['birth_date']) ? date('Y-m-d', strtotime($_POST['birth_date'])) : null;
    $current_status = $_POST['current_status'];
    $location_id = $_POST['location_id'];
    $building_id = $_POST['building_id'];
    $pen_id = $_POST['pen_id'];

    // New Weight Inputs (Allow null/empty if not changed)
    $weight_birth = !empty($_POST['weight_at_birth']) ? $_POST['weight_at_birth'] : null;
    $weight_actual = !empty($_POST['current_actual_weight']) ? $_POST['current_actual_weight'] : null;
    $weight_est = !empty($_POST['current_estimated_weight']) ? $_POST['current_estimated_weight'] : null;

    if (empty($animal_id) || empty($tag_no) || empty($animal_type_id)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing.']);
        exit;
    }

    try {
        if (!isset($conn)) { throw new Exception("Database connection failed."); }

        $conn->beginTransaction();

        // 1. Fetch Original Data & Lock
        $sqlFetch = "SELECT TAG_NO, SEX, ANIMAL_TYPE_ID, BREED_ID, 
                            DATE_FORMAT(BIRTH_DATE, '%Y-%m-%d') AS BIRTH_DATE, 
                            CURRENT_STATUS, LOCATION_ID, BUILDING_ID, PEN_ID,
                            WEIGHT_AT_BIRTH, CURRENT_ACTUAL_WEIGHT, CURRENT_ESTIMATED_WEIGHT
                     FROM Animal_Records WHERE ANIMAL_ID = :id FOR UPDATE";
        $fetch_stmt = $conn->prepare($sqlFetch);
        $fetch_stmt->execute([':id' => $animal_id]);
        $original_row = $fetch_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$original_row) {
            $conn->rollBack();
            throw new Exception("Animal record not found.");
        }

        // 2. Check Duplicate Tag
        $checkSql = "SELECT COUNT(*) AS CNT FROM Animal_Records WHERE TAG_NO = :tag AND ANIMAL_ID != :id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':tag' => $tag_no, ':id' => $animal_id]);
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)['CNT'] > 0) {
            $conn->rollBack();
            throw new Exception("Tag Number '$tag_no' is already assigned.");
        }

        // 3. Prepare Audit Logs
        $changes = [];
        if ($original_row['TAG_NO'] != $tag_no) $changes[] = "Tag: {$original_row['TAG_NO']} -> $tag_no";
        if ($original_row['SEX'] != $sex) $changes[] = "Sex: {$original_row['SEX']} -> $sex";
        if ($original_row['BIRTH_DATE'] != $birth_date) $changes[] = "Birth: {$original_row['BIRTH_DATE']} -> $birth_date";
        if ($original_row['WEIGHT_AT_BIRTH'] != $weight_birth) $changes[] = "Birth Wt: {$original_row['WEIGHT_AT_BIRTH']} -> $weight_birth";
        if ($original_row['CURRENT_ACTUAL_WEIGHT'] != $weight_actual) $changes[] = "Actual Wt: {$original_row['CURRENT_ACTUAL_WEIGHT']} -> $weight_actual";
        
        // 4. Update Main Record
        $sql = "UPDATE Animal_Records SET 
                TAG_NO = :tag, SEX = :sex, ANIMAL_TYPE_ID = :type_id, BREED_ID = :breed_id,
                BIRTH_DATE = :bdate, CURRENT_STATUS = :status, LOCATION_ID = :loc_id,
                BUILDING_ID = :bld_id, PEN_ID = :pen_id, 
                WEIGHT_AT_BIRTH = :w_birth, 
                CURRENT_ACTUAL_WEIGHT = :w_actual, 
                CURRENT_ESTIMATED_WEIGHT = :w_est,
                UPDATED_AT = NOW()
                WHERE ANIMAL_ID = :id";

        $update_stmt = $conn->prepare($sql);
        $update_stmt->execute([
            ':tag' => $tag_no, ':sex' => $sex, ':type_id' => $animal_type_id,
            ':breed_id' => $breed_id, ':bdate' => $birth_date, ':status' => $current_status,
            ':loc_id' => $location_id, ':bld_id' => $building_id, ':pen_id' => $pen_id,
            ':w_birth' => $weight_birth, ':w_actual' => $weight_actual, ':w_est' => $weight_est,
            ':id' => $animal_id
        ]);

        // ---------------------------------------------------------
        // 5. SMART RE-CLASSIFICATION
        // ---------------------------------------------------------
        if ($original_row['BIRTH_DATE'] != $birth_date || $original_row['SEX'] != $sex) {
            if ($birth_date) {
                // Find correct class based on Age & Sex, fallback to Unknown if no match
                $classify_sql = "
                    UPDATE animal_records 
                    SET CLASS_ID = (
                        SELECT IFNULL(
                            (SELECT CLASS_ID FROM animal_classifications 
                             WHERE DATEDIFF(NOW(), :bdate) BETWEEN MIN_DAYS AND MAX_DAYS 
                             AND (REQUIRED_SEX IS NULL OR REQUIRED_SEX = :sex) 
                             LIMIT 1),
                            (SELECT CLASS_ID FROM animal_classifications WHERE STAGE_NAME = 'Unknown Stage' LIMIT 1)
                        )
                    )
                    WHERE ANIMAL_ID = :id
                ";
                
                $stmtClass = $conn->prepare($classify_sql);
                $stmtClass->execute([
                    ':bdate' => $birth_date,
                    ':sex'   => $sex,
                    ':id'    => $animal_id
                ]);
            } else {
                // No birth date -> Unknown
                $unknown_sql = "UPDATE animal_records SET CLASS_ID = 
                                (SELECT CLASS_ID FROM animal_classifications WHERE STAGE_NAME = 'Unknown Stage' LIMIT 1)
                                WHERE ANIMAL_ID = :id";
                $stmtClass = $conn->prepare($unknown_sql);
                $stmtClass->execute([':id' => $animal_id]);
            }
        }
        // ---------------------------------------------------------

        // 6. Save Audit Log
        if (!empty($changes)) {
            $logDetails = "Updated Animal (ID: $animal_id). " . implode("; ", $changes);
            $sqlLog = "INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                       VALUES (:user_id, :username, 'EDIT_ANIMAL', 'ANIMAL_RECORDS', :details, :ip)";
            $log_stmt = $conn->prepare($sqlLog);
            $log_stmt->execute([':user_id' => $acting_user_id, ':username' => $acting_username, ':details' => $logDetails, ':ip' => $ip_address]);
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Animal updated successfully.']);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) { $conn->rollBack(); }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>