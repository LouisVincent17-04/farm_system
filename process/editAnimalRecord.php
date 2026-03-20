<?php
// process/editAnimalRecord.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

session_start();
include '../config/Connection.php';
include '../security/checkRole.php';

$acting_user_id  = !empty($_SESSION['user']['USER_ID']) ? (int)$_SESSION['user']['USER_ID'] : null;
$acting_username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address      = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $animal_id      = $_POST['animal_id'] ?? null;
    $tag_no         = strtoupper(trim($_POST['tag_no'] ?? '')); 
    $sex            = $_POST['sex'] ?? null;
    $animal_type_id = $_POST['animal_type_id'] ?? null;
    $breed_id       = $_POST['breed_id'] ?? null;
    $birth_date     = !empty($_POST['birth_date']) ? date('Y-m-d', strtotime($_POST['birth_date'])) : null;
    $current_status = $_POST['current_status'] ?? null;
    $location_id    = $_POST['location_id'] ?? null;
    $building_id    = $_POST['building_id'] ?? null;
    $pen_id         = $_POST['pen_id'] ?? null;

    // Parents - Convert empty strings to strictly NULL for foreign keys
    $mother_id = (isset($_POST['mother_id']) && trim($_POST['mother_id']) !== '') ? trim($_POST['mother_id']) : null;
    $father_id = (isset($_POST['father_id']) && trim($_POST['father_id']) !== '') ? trim($_POST['father_id']) : null;

    // Weights - Use strict isset checks so '0' is allowed but empty is null
    $weight_birth  = (isset($_POST['weight_at_birth']) && trim($_POST['weight_at_birth']) !== '') ? trim($_POST['weight_at_birth']) : null;
    $weight_actual = (isset($_POST['current_actual_weight']) && trim($_POST['current_actual_weight']) !== '') ? trim($_POST['current_actual_weight']) : null;
    $weight_est    = (isset($_POST['current_estimated_weight']) && trim($_POST['current_estimated_weight']) !== '') ? trim($_POST['current_estimated_weight']) : null;

    if (empty($animal_id) || empty($tag_no) || empty($animal_type_id)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing.']);
        exit;
    }

    try {
        if (!isset($conn)) { throw new Exception("Database connection failed."); }

        $conn->beginTransaction();

        // 1. Fetch Original Data
        $sqlFetch = "SELECT TAG_NO, SEX, ANIMAL_TYPE_ID, BREED_ID, 
                            DATE_FORMAT(BIRTH_DATE, '%Y-%m-%d') AS BIRTH_DATE, 
                            CURRENT_STATUS, LOCATION_ID, BUILDING_ID, PEN_ID,
                            WEIGHT_AT_BIRTH, CURRENT_ACTUAL_WEIGHT, CURRENT_ESTIMATED_WEIGHT,
                            MOTHER_ID, FATHER_ID
                     FROM Animal_Records WHERE ANIMAL_ID = :orig_id FOR UPDATE";
        $fetch_stmt = $conn->prepare($sqlFetch);
        $fetch_stmt->execute([':orig_id' => $animal_id]);
        $original_row = $fetch_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$original_row) {
            $conn->rollBack();
            throw new Exception("Animal record not found.");
        }

        // 2. Duplicate Tag Check
        $checkSql = "SELECT COUNT(*) AS CNT FROM Animal_Records WHERE TAG_NO = :check_tag AND ANIMAL_ID != :check_id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':check_tag' => $tag_no, ':check_id' => $animal_id]);
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)['CNT'] > 0) {
            $conn->rollBack();
            throw new Exception("Tag Number '$tag_no' is already assigned.");
        }

        // 3. Update Main Record
        $sql = "UPDATE Animal_Records SET 
                TAG_NO = :tag, SEX = :sex, ANIMAL_TYPE_ID = :type_id, BREED_ID = :breed_id,
                BIRTH_DATE = :bdate, CURRENT_STATUS = :status, LOCATION_ID = :loc_id,
                BUILDING_ID = :bld_id, PEN_ID = :pen_id, 
                WEIGHT_AT_BIRTH = :w_birth, CURRENT_ACTUAL_WEIGHT = :w_actual, CURRENT_ESTIMATED_WEIGHT = :w_est,
                MOTHER_ID = :mother_id, FATHER_ID = :father_id,
                UPDATED_AT = NOW()
                WHERE ANIMAL_ID = :id";

        $update_stmt = $conn->prepare($sql);
        $update_stmt->execute([
            ':tag' => $tag_no, ':sex' => $sex, ':type_id' => $animal_type_id,
            ':breed_id' => $breed_id, ':bdate' => $birth_date, ':status' => $current_status,
            ':loc_id' => $location_id, ':bld_id' => $building_id, ':pen_id' => $pen_id,
            ':w_birth' => $weight_birth, ':w_actual' => $weight_actual, ':w_est' => $weight_est,
            ':mother_id' => $mother_id, ':father_id' => $father_id,
            ':id' => $animal_id
        ]);

        // ---------------------------------------------------------
        // 4. SMART LITTER CASCADE (No HY093 Errors!)
        // ---------------------------------------------------------
        // Sync the sire/dam across the entire litter (matching mother & birthdate)
        if ($mother_id && $birth_date) {
            $syncSire = $conn->prepare("
                UPDATE Animal_Records 
                SET FATHER_ID = :sync_father_id, UPDATED_AT = NOW() 
                WHERE MOTHER_ID = :sync_mother_id 
                AND DATE(BIRTH_DATE) = :sync_b_date 
                AND ANIMAL_ID != :sync_curr_id
            ");
            $syncSire->execute([
                ':sync_father_id' => $father_id, 
                ':sync_mother_id' => $mother_id,
                ':sync_b_date'    => $birth_date,
                ':sync_curr_id'   => $animal_id
            ]);
        }
        
        if ($father_id && $birth_date) {
            $syncDam = $conn->prepare("
                UPDATE Animal_Records 
                SET MOTHER_ID = :sync_mother_id_2, UPDATED_AT = NOW() 
                WHERE FATHER_ID = :sync_father_id_2 
                AND DATE(BIRTH_DATE) = :sync_b_date_2 
                AND ANIMAL_ID != :sync_curr_id_2
            ");
            $syncDam->execute([
                ':sync_mother_id_2' => $mother_id, 
                ':sync_father_id_2' => $father_id,
                ':sync_b_date_2'    => $birth_date,
                ':sync_curr_id_2'   => $animal_id
            ]);
        }
        // ---------------------------------------------------------

        // ---------------------------------------------------------
        // 5. SMART RE-CLASSIFICATION
        // ---------------------------------------------------------
        if ($original_row['BIRTH_DATE'] != $birth_date || $original_row['SEX'] != $sex) {
            if ($birth_date) {
                $classify_sql = "
                    UPDATE animal_records 
                    SET CLASS_ID = (
                        SELECT IFNULL(
                            (SELECT CLASS_ID FROM animal_classifications 
                             WHERE DATEDIFF(NOW(), :cls_bdate) BETWEEN MIN_DAYS AND MAX_DAYS 
                             AND (REQUIRED_SEX IS NULL OR REQUIRED_SEX = :cls_sex) 
                             LIMIT 1),
                            (SELECT CLASS_ID FROM animal_classifications WHERE STAGE_NAME = 'Unknown Stage' LIMIT 1)
                        )
                    )
                    WHERE ANIMAL_ID = :cls_id
                ";
                $stmtClass = $conn->prepare($classify_sql);
                $stmtClass->execute([
                    ':cls_bdate' => $birth_date,
                    ':cls_sex'   => $sex,
                    ':cls_id'    => $animal_id
                ]);
            } else {
                $unknown_sql = "UPDATE animal_records SET CLASS_ID = 
                                (SELECT CLASS_ID FROM animal_classifications WHERE STAGE_NAME = 'Unknown Stage' LIMIT 1)
                                WHERE ANIMAL_ID = :unk_id";
                $stmtClass = $conn->prepare($unknown_sql);
                $stmtClass->execute([':unk_id' => $animal_id]);
            }
        }
        // ---------------------------------------------------------

        // 6. Save Audit Log
        $logDetails = "Updated Animal ID: $animal_id.";
        $sqlLog = "INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                   VALUES (:log_uid, :log_uname, 'EDIT_ANIMAL', 'ANIMAL_RECORDS', :log_details, :log_ip)";
        $log_stmt = $conn->prepare($sqlLog);
        $log_stmt->execute([
            ':log_uid'     => $acting_user_id, 
            ':log_uname'   => $acting_username, 
            ':log_details' => $logDetails, 
            ':log_ip'      => $ip_address
        ]);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Animal and litter updated successfully.']);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) { $conn->rollBack(); }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>