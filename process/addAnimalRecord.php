<?php
// process/addAnimalRecord.php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

header('Content-Type: application/json');

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and sanitize input data
$tag_no = isset($_POST['tag_no']) ? trim($_POST['tag_no']) : '';
$animal_type_id = isset($_POST['animal_type_id']) ? trim($_POST['animal_type_id']) : null;
$breed_id = isset($_POST['breed_id']) ? trim($_POST['breed_id']) : null;
$birth_date = !empty($_POST['birth_date']) ? trim($_POST['birth_date']) : null;
$sex = isset($_POST['sex']) ? trim($_POST['sex']) : '';
$current_status = isset($_POST['current_status']) ? trim($_POST['current_status']) : '';
$location_id = !empty($_POST['location_id']) ? trim($_POST['location_id']) : null;
$building_id = !empty($_POST['building_id']) ? trim($_POST['building_id']) : null;
$pen_id = !empty($_POST['pen_id']) ? trim($_POST['pen_id']) : null;
$animal_item_id = !empty($_POST['animal_item_id']) ? trim($_POST['animal_item_id']) : null;
$mother_id = !empty($_POST['mother_id']) ? trim($_POST['mother_id']) : null;

// Capture Costs
$acquisition_cost = !empty($_POST['acquisition_cost']) ? $_POST['acquisition_cost'] : 0.00;

// NEW: Capture Purchase Flag (0 = Farm Born, 1 = Purchased)
$is_purchased = isset($_POST['acquisition_type']) ? (int)$_POST['acquisition_type'] : 0;

try {
    // 1. Validate required fields
    if (empty($tag_no) || empty($sex) || empty($current_status)) {
        throw new Exception('Tag number, sex, and current status are required.');
    }

    if (!in_array($sex, ['M', 'F'])) {
        throw new Exception('Invalid sex value.');
    }

    // 2. Check duplicate tag
    $check_sql = "SELECT COUNT(*) as count FROM ANIMAL_RECORDS WHERE TAG_NO = :tag_no AND IS_ACTIVE = 1";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([':tag_no' => $tag_no]);
    $check_row = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($check_row['count'] > 0) {
        throw new Exception('Tag number already exists. Please use a different tag number.');
    }

    $conn->beginTransaction();

    // 3. Insert new animal record
    // UPDATED: Added IS_PURCHASED column
    $insert_sql = "INSERT INTO ANIMAL_RECORDS 
                   (TAG_NO, ANIMAL_TYPE_ID, BREED_ID, BIRTH_DATE, SEX, CURRENT_STATUS, 
                    LOCATION_ID, BUILDING_ID, PEN_ID, IS_ACTIVE, CREATED_AT, 
                    ANIMAL_ITEM_ID, MOTHER_ID, ACQUISITION_COST, IS_PURCHASED)
                   VALUES 
                   (:tag_no, :animal_type_id, :breed_id, :birth_date, 
                    :sex, :current_status, :location_id, :building_id, :pen_id, 1, NOW(), 
                    :animal_item_id, :mother_id, :acq_cost, :is_purchased)";

    $insert_stmt = $conn->prepare($insert_sql);

    $params = [
        ':tag_no'         => $tag_no,
        ':animal_type_id' => $animal_type_id,
        ':breed_id'       => $breed_id,
        ':birth_date'     => $birth_date,
        ':sex'            => $sex,
        ':current_status' => $current_status,
        ':location_id'    => $location_id,
        ':building_id'    => $building_id,
        ':pen_id'         => $pen_id,
        ':animal_item_id' => $animal_item_id,
        ':mother_id'      => $mother_id,
        ':acq_cost'       => $acquisition_cost,
        ':is_purchased'   => $is_purchased // Bind new parameter
    ];

    if ($insert_stmt->execute($params)) {
        $new_animal_id = $conn->lastInsertId();

        // ---------------------------------------------------------
        // IMMEDIATE AUTO-CLASSIFICATION (Fallback Logic)
        // ---------------------------------------------------------
        if ($birth_date) {
            // Find class based on Age & Sex
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
                ':id'    => $new_animal_id
            ]);
        } else {
            // If no birth date, set to 'Unknown Stage'
            $unknown_sql = "UPDATE animal_records SET CLASS_ID = 
                            (SELECT CLASS_ID FROM animal_classifications WHERE STAGE_NAME = 'Unknown Stage' LIMIT 1)
                            WHERE ANIMAL_ID = :id";
            $stmtClass = $conn->prepare($unknown_sql);
            $stmtClass->execute([':id' => $new_animal_id]);
        }
        // ---------------------------------------------------------

        // 4. Insert audit log
        $sourceType = $is_purchased ? "Purchased" : "Farm Born";
        $parentInfo = $mother_id ? " (Mother ID: $mother_id)" : "";
        $costInfo = $acquisition_cost > 0 ? " (Cost: $acquisition_cost)" : "";
        
        $logDetails = "Added Animal ($sourceType). Tag: $tag_no, Sex: $sex, ID: $new_animal_id" . $parentInfo . $costInfo;

        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS)
                    VALUES 
                    (:user_id, :username, 'ADD_ANIMAL', 'ANIMAL_RECORDS', :details, :ip)";
        
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->execute([
            ':user_id'  => $user_id,
            ':username' => $username,
            ':details'  => $logDetails,
            ':ip'       => $ip_address
        ]);

        $conn->commit();

        echo json_encode([
            'success'   => true,
            'message'   => '✅ Animal record added successfully!',
            'animal_id' => $new_animal_id
        ]);
    } else {
        throw new Exception('Error adding animal record.');
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => '❌ An error occurred: ' . $e->getMessage()]);
}
?>