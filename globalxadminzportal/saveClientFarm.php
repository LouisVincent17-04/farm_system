<?php
// globalxadminportal/saveClientFarm.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

session_start();
include '../config/SadminConnection.php'; // Master DB Connection ($conn)

// ========================================================================
// ⚠️ YOUR MASTER MYSQL CREDENTIALS 
// Used to connect to the newly created database to inject the tables
// ========================================================================
$master_db_host = "localhost";
$master_db_user = "root"; // <-- CHANGE THIS if needed
$master_db_pass = "v1i1n1x1";     // <-- CHANGE THIS if needed
// ========================================================================

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// ========================================================================
// ACTION 1: LIVE DATABASE AVAILABILITY CHECK (GET)
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check_db') {
    $db_name = trim($_GET['db_name']);
    if (!preg_match('/^[a-z0-9_]{3,64}$/', $db_name)) {
        echo json_encode(['available' => false]);
        exit;
    }
    try {
        $stmt = $conn->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([$db_name]);
        $exists = $stmt->fetch();
        echo json_encode(['available' => !$exists]);
    } catch (Exception $e) {
        echo json_encode(['available' => false]);
    }
    exit;
}

// ========================================================================
// ACTION 2: PROVISION NEW FARM & ASSIGN EXISTING OWNER (POST)
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
        exit;
    }

    $farm_name   = trim($data['farm_name'] ?? '');
    $db_name     = trim($data['db_name'] ?? '');
    $owner_email = trim($data['owner_email'] ?? '');
    $owner_phone = trim($data['owner_phone'] ?? '');

    if (empty($farm_name) || empty($db_name) || empty($owner_email)) {
        echo json_encode(['success' => false, 'message' => 'Farm Name, Database Name, and Owner Email are required.']);
        exit;
    }

    if (!preg_match('/^[a-z0-9_]{3,64}$/', $db_name)) {
        echo json_encode(['success' => false, 'message' => 'Invalid database name format.']);
        exit;
    }

    $db_created = false; 

    try {
        // 1. Check if DB is taken
        $stmt = $conn->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([$db_name]);
        if ($stmt->fetch()) {
            throw new Exception("Database '$db_name' is already taken.");
        }
        
        // 2. CHECK IF ADMIN EXISTS (STRICT ENFORCEMENT)
        $checkEmail = $conn->prepare("SELECT admin_id, full_name, password FROM admin_users WHERE email = ?");
        $checkEmail->execute([$owner_email]);
        $existingAdmin = $checkEmail->fetch(PDO::FETCH_ASSOC);
        
        if (!$existingAdmin) {
            // Halt execution! User must be registered first.
            throw new Exception("User not found. Please ensure the client is registered before creating a farm for them.");
        }

        $admin_id = $existingAdmin['admin_id'];
        $owner_name = $existingAdmin['full_name'];
        $app_pass_hashed = $existingAdmin['password']; // Copy their existing hash to the new tenant DB

        $db_user = substr($db_name, 0, 16) . '_' . rand(100,999);
        $db_pass = bin2hex(random_bytes(8)); 
        
        // 3. RUN DDL COMMANDS (Create DB & User)
        $conn->exec("CREATE DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->exec("CREATE USER '$db_user'@'localhost' IDENTIFIED BY '$db_pass'");
        $conn->exec("GRANT ALL PRIVILEGES ON `$db_name`.* TO '$db_user'@'localhost'");
        $conn->exec("FLUSH PRIVILEGES");
        
        $db_created = true; 

        // ========================================================================
        // 4. MIGRATE BASE SCHEMA TO THE NEW DATABASE
        // ========================================================================
        try {
            $tenant_conn = new PDO("mysql:host=$master_db_host;dbname=$db_name;charset=utf8mb4", $master_db_user, $master_db_pass);
            $tenant_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $schema_tables = <<<SQL
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `user_types`;
CREATE TABLE `user_types`  (
  `USER_TYPE_ID` int(11) NOT NULL,
  `USER_TYPE_NAME` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`USER_TYPE_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

INSERT INTO `user_types` VALUES (1, 'New User'), (2, 'Farm Employee'), (3, 'Admin'), (4, 'Super Admin');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users`  (
  `USER_ID` int(11) NOT NULL AUTO_INCREMENT,
  `FULL_NAME` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `EMAIL` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `PASSWORD` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CREATED_AT` datetime NULL DEFAULT NULL,
  `USER_TYPE` int(11) NULL DEFAULT 1,
  `CONTACT_INFO` bigint(20) NULL DEFAULT NULL,
  `GOOGLE_ID` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `IS_ACTIVE` tinyint(1) NOT NULL DEFAULT 1,
  `DATE_UPDATED` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `LOCATION_ID` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`USER_ID`) USING BTREE,
  UNIQUE INDEX `EMAIL`(`EMAIL`) USING BTREE,
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`USER_TYPE`) REFERENCES `user_types` (`USER_TYPE_ID`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `access_control`;
CREATE TABLE `access_control`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `dashboard` tinyint(1) NULL DEFAULT 0,
  `animal_record` tinyint(1) NULL DEFAULT 0,
  `farm_roles` tinyint(4) NULL DEFAULT 0,
  `employee_list` tinyint(4) NULL DEFAULT 0,
  `animal_type` tinyint(1) NULL DEFAULT 0,
  `location` tinyint(1) NULL DEFAULT 0,
  `building` tinyint(1) NULL DEFAULT 0,
  `pen` tinyint(1) NULL DEFAULT 0,
  `breed` tinyint(1) NULL DEFAULT 0,
  `veterinary` tinyint(1) NULL DEFAULT 0,
  `diseases` tinyint(1) NULL DEFAULT 0,
  `buyer` tinyint(1) NULL DEFAULT 0,
  `costing` tinyint(1) NULL DEFAULT 0,
  `animal_cost` tinyint(1) NULL DEFAULT 0,
  `feed_consumption` tinyint(1) NULL DEFAULT 0,
  `medication_treatment` tinyint(1) NULL DEFAULT 0,
  `vaccinations` tinyint(1) NULL DEFAULT 0,
  `vitamins_supplements` tinyint(1) NULL DEFAULT 0,
  `veterinary_checkups` tinyint(1) NULL DEFAULT 0,
  `farm` tinyint(1) NULL DEFAULT 0,
  `animal_class` tinyint(1) NULL DEFAULT 0,
  `edit_bio_info` tinyint(1) NULL DEFAULT 0,
  `event_scheduler` tinyint(1) NULL DEFAULT 0,
  `animal_transfer` tinyint(1) NULL DEFAULT 0,
  `sow_status` tinyint(1) NULL DEFAULT 0,
  `fcr_management` tinyint(1) NULL DEFAULT 0,
  `animal_weights` tinyint(1) NULL DEFAULT 0,
  `animal_operations` tinyint(1) NULL DEFAULT 0,
  `sow_cards` tinyint(1) NULL DEFAULT 0,
  `birth_certificate` tinyint(1) NULL DEFAULT 0,
  `cost_transfer` tinyint(1) NULL DEFAULT 0,
  `analytics_dashboard` tinyint(1) NULL DEFAULT 0,
  `animals_livestock_analytics` tinyint(1) NULL DEFAULT 0,
  `medicine_analytics` tinyint(1) NULL DEFAULT 0,
  `vitamins_supplements_analytics` tinyint(1) NULL DEFAULT 0,
  `vaccines_analytics` tinyint(1) NULL DEFAULT 0,
  `feeds_feeding_analytics` tinyint(1) NULL DEFAULT 0,
  `housing_facilities_analytics` tinyint(1) NULL DEFAULT 0,
  `farm_equipment_tools_analytics` tinyint(1) NULL DEFAULT 0,
  `sanitation_waste_analytics` tinyint(1) NULL DEFAULT 0,
  `breeding_reproduction_analytics` tinyint(1) NULL DEFAULT 0,
  `administration_records_analytics` tinyint(1) NULL DEFAULT 0,
  `maintenance_parts_analytics` tinyint(1) NULL DEFAULT 0,
  `utilities_consumables_analytics` tinyint(1) NULL DEFAULT 0,
  `others_analytics` tinyint(1) NULL DEFAULT 0,
  `reports` tinyint(1) NULL DEFAULT 0,
  `animal_report` tinyint(1) NULL DEFAULT 0,
  `active_users_report` tinyint(1) NULL DEFAULT 0,
  `medicine_report` tinyint(1) NULL DEFAULT 0,
  `feeds_feeding_supplies_report` tinyint(1) NULL DEFAULT 0,
  `housing_facilities_report` tinyint(1) NULL DEFAULT 0,
  `farm_equipment_tools_report` tinyint(1) NULL DEFAULT 0,
  `sanitation_waste_management_report` tinyint(1) NULL DEFAULT 0,
  `breeding_reproduction_report` tinyint(1) NULL DEFAULT 0,
  `administration_records_report` tinyint(1) NULL DEFAULT 0,
  `maintenance_parts_report` tinyint(1) NULL DEFAULT 0,
  `utilities_consumables_report` tinyint(1) NULL DEFAULT 0,
  `vitamins_supplements_report` tinyint(1) NULL DEFAULT 0,
  `vaccine_report` tinyint(1) NULL DEFAULT 0,
  `others_report` tinyint(1) NULL DEFAULT 0,
  `audit_log_report` tinyint(1) NULL DEFAULT 0,
  `medication_report` tinyint(1) NULL DEFAULT 0,
  `vaccination_report` tinyint(1) NULL DEFAULT 0,
  `vitamins_supplements_transaction_report` tinyint(1) NULL DEFAULT 0,
  `feeding_transaction_report` tinyint(1) NULL DEFAULT 0,
  `animal_sales_report` tinyint(1) NULL DEFAULT 0,
  `transactions` tinyint(1) NULL DEFAULT 0,
  `individual_operations` tinyint(1) NULL DEFAULT 0,
  `feeding` tinyint(1) NULL DEFAULT 0,
  `medication` tinyint(1) NULL DEFAULT 0,
  `vitamins_supplements_trans` tinyint(1) NULL DEFAULT 0,
  `check_ups` tinyint(1) NULL DEFAULT 0,
  `vaccination` tinyint(1) NULL DEFAULT 0,
  `purchases` tinyint(1) NULL DEFAULT 0,
  `sell_animals` tinyint(1) NULL DEFAULT 0,
  `batch_group_operations` tinyint(1) NULL DEFAULT 0,
  `group_medication` tinyint(1) NULL DEFAULT 0,
  `group_vitamins` tinyint(1) NULL DEFAULT 0,
  `group_checkup` tinyint(1) NULL DEFAULT 0,
  `group_vaccination` tinyint(1) NULL DEFAULT 0,
  `group_sell_animals` tinyint(1) NULL DEFAULT 0,
  `settings` tinyint(1) NULL DEFAULT 0,
  `manage_accounts` tinyint(1) NULL DEFAULT 0,
  `audit_logs` tinyint(1) NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_user_access`(`user_id`) USING BTREE,
  CONSTRAINT `access_control_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`USER_ID`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `animal_classifications`;
CREATE TABLE `animal_classifications`  (
  `CLASS_ID` int(11) NOT NULL AUTO_INCREMENT,
  `STAGE_NAME` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `MIN_DAYS` int(11) NOT NULL,
  `MAX_DAYS` int(11) NOT NULL,
  `REQUIRED_SEX` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `FCR` decimal(10, 2) NULL DEFAULT NULL,
  `UPDATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`CLASS_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

INSERT INTO `animal_classifications` VALUES (1, 'Piglet', 0, 30, NULL, 1.10, '2026-02-01 12:17:11');
INSERT INTO `animal_classifications` VALUES (2, 'Starter Hog', 31, 60, NULL, 1.50, '2026-01-11 21:54:39');
INSERT INTO `animal_classifications` VALUES (3, 'Grower Hog', 61, 100, NULL, 2.30, '2026-01-05 11:45:32');
INSERT INTO `animal_classifications` VALUES (4, 'Finisher Hog', 101, 150, NULL, 2.80, '2026-01-05 11:45:32');
INSERT INTO `animal_classifications` VALUES (5, 'Jr. Boar', 151, 240, 'M', 3.00, '2026-01-05 11:45:32');
INSERT INTO `animal_classifications` VALUES (6, 'Gilt', 151, 240, 'F', 3.00, '2026-01-05 11:45:32');
INSERT INTO `animal_classifications` VALUES (7, 'Boar', 241, 5000, 'M', 3.20, '2026-01-05 11:45:32');
INSERT INTO `animal_classifications` VALUES (8, 'Sow', 241, 5000, 'F', 0.35, '2026-01-08 21:40:36');

DROP TABLE IF EXISTS `animal_records`;
CREATE TABLE `animal_records`  (
  `ANIMAL_ID` int(11) NOT NULL AUTO_INCREMENT,
  `TAG_NO` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ANIMAL_TYPE_ID` int(11) NULL DEFAULT NULL,
  `BREED_ID` int(11) NULL DEFAULT NULL,
  `BIRTH_DATE` date NULL DEFAULT NULL,
  `SEX` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `WEIGHT_AT_BIRTH` decimal(10, 2) NULL DEFAULT 0.00,
  `WEANING_WEIGHT` decimal(10, 2) NULL DEFAULT NULL,
  `CURRENT_ESTIMATED_WEIGHT` decimal(10, 2) NULL DEFAULT 0.00,
  `CURRENT_ACTUAL_WEIGHT` decimal(10, 2) NULL DEFAULT 0.00,
  `ACQUISITION_COST` decimal(10, 2) NULL DEFAULT 0.00,
  `CURRENT_STATUS` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `LOCATION_ID` int(11) NULL DEFAULT NULL,
  `BUILDING_ID` int(11) NULL DEFAULT NULL,
  `PEN_ID` int(11) NULL DEFAULT NULL,
  `IS_ACTIVE` tinyint(1) NULL DEFAULT NULL,
  `CREATED_AT` datetime NULL DEFAULT NULL,
  `UPDATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `LAST_COST_RESET_DATE` datetime NULL DEFAULT NULL,
  `ANIMAL_ITEM_ID` int(11) NULL DEFAULT NULL,
  `MOTHER_ID` int(11) NULL DEFAULT NULL,
  `FATHER_ID` int(11) NULL DEFAULT NULL,
  `CLASS_ID` int(11) NULL DEFAULT NULL,
  `IS_PURCHASED` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ANIMAL_ID`) USING BTREE,
  CONSTRAINT `fk_animal_class` FOREIGN KEY (`CLASS_ID`) REFERENCES `animal_classifications` (`CLASS_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mother_id` FOREIGN KEY (`MOTHER_ID`) REFERENCES `animal_records` (`ANIMAL_ID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `animal_fcr_logs`;
CREATE TABLE `animal_fcr_logs`  (
  `LOG_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ANIMAL_ID` int(11) NOT NULL,
  `PEN_ID` int(11) NOT NULL,
  `LOG_DATE` date NOT NULL,
  `BIRTH_WEIGHT` decimal(10, 2) NULL DEFAULT 0.00,
  `FEED_SHARE_KG` decimal(10, 2) NOT NULL,
  `FCR_USED` decimal(10, 4) NOT NULL,
  `TOTAL_GAIN_EST` decimal(10, 2) NOT NULL,
  `ESTIMATED_WEIGHT` decimal(10, 2) NOT NULL,
  `ACTUAL_WEIGHT` decimal(10, 2) NOT NULL,
  `VARIANCE` decimal(10, 2) NOT NULL,
  `CREATED_BY` int(11) NULL DEFAULT NULL,
  `CREATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`LOG_ID`) USING BTREE,
  CONSTRAINT `animal_fcr_logs_ibfk_1` FOREIGN KEY (`ANIMAL_ID`) REFERENCES `animal_records` (`ANIMAL_ID`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `animal_sales`;
CREATE TABLE `animal_sales`  (
  `sale_id` int(11) NOT NULL AUTO_INCREMENT,
  `animal_id` int(11) NOT NULL,
  `sale_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `customer_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `weight_at_sale` decimal(10, 2) NULL DEFAULT NULL,
  `price_per_kg` decimal(10, 2) NULL DEFAULT NULL,
  `final_sale_price` decimal(12, 2) NOT NULL,
  `cost_acquisition` decimal(12, 2) NULL DEFAULT 0.00,
  `cost_feed_total` decimal(12, 2) NULL DEFAULT 0.00,
  `cost_medication_total` decimal(12, 2) NULL DEFAULT 0.00,
  `cost_vaccination_total` decimal(12, 2) NULL DEFAULT 0.00,
  `cost_checkup_total` decimal(12, 2) NULL DEFAULT 0.00,
  `cost_vitamins_total` decimal(12, 2) NULL DEFAULT 0.00,
  `cost_overhead` decimal(12, 2) NULL DEFAULT 0.00,
  `total_net_worth` decimal(12, 2) NULL DEFAULT 0.00,
  `gross_profit` decimal(12, 2) NULL DEFAULT 0.00,
  `notes` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
  `created_by` int(11) NULL DEFAULT NULL,
  `transaction_type` tinyint(1) NULL DEFAULT 1,
  `batch_id` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`sale_id`) USING BTREE,
  CONSTRAINT `animal_sales_ibfk_1` FOREIGN KEY (`animal_id`) REFERENCES `animal_records` (`ANIMAL_ID`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `animal_type`;
CREATE TABLE `animal_type`  (
  `ANIMAL_TYPE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ANIMAL_TYPE_NAME` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CREATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ANIMAL_TYPE_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs`  (
  `LOG_ID` int(11) NOT NULL AUTO_INCREMENT,
  `USER_ID` int(11) NULL DEFAULT NULL,
  `USERNAME` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `ACTION_TYPE` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `TABLE_NAME` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `ACTION_DETAILS` varchar(4000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `IP_ADDRESS` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `LOG_DATE` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`LOG_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `breeds`;
CREATE TABLE `breeds`  (
  `BREED_ID` int(11) NOT NULL AUTO_INCREMENT,
  `BREED_NAME` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ANIMAL_TYPE_ID` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`BREED_ID`) USING BTREE,
  CONSTRAINT `breeds_ibfk_1` FOREIGN KEY (`ANIMAL_TYPE_ID`) REFERENCES `animal_type` (`ANIMAL_TYPE_ID`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `locations`;
CREATE TABLE `locations`  (
  `LOCATION_ID` int(11) NOT NULL AUTO_INCREMENT,
  `LOCATION_NAME` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `COMPLETE_ADDRESS` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `CREATED_AT` datetime NULL DEFAULT NULL,
  `UPDATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `LONGITUDE` decimal(9, 6) NULL DEFAULT NULL,
  `LATITUDE` decimal(9, 6) NULL DEFAULT NULL,
  `WEANING_DAYS` int(11) NULL DEFAULT 30,
  PRIMARY KEY (`LOCATION_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `buildings`;
CREATE TABLE `buildings`  (
  `BUILDING_ID` int(11) NOT NULL AUTO_INCREMENT,
  `BUILDING_NAME` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `LOCATION_ID` int(11) NOT NULL,
  `UPDATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`BUILDING_ID`) USING BTREE,
  CONSTRAINT `buildings_ibfk_1` FOREIGN KEY (`LOCATION_ID`) REFERENCES `locations` (`LOCATION_ID`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `buyers`;
CREATE TABLE `buyers`  (
  `BUYER_ID` int(11) NOT NULL AUTO_INCREMENT,
  `FULL_NAME` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `CONTACT_NO` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `ADDRESS` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `IS_ACTIVE` tinyint(1) NULL DEFAULT 1,
  `CREATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`BUYER_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `check_ups`;
CREATE TABLE `check_ups`  (
  `CHECK_UP_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ANIMAL_ID` bigint(20) NOT NULL,
  `VET_NAME` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CHECKUP_DATE` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `REMARKS` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `COST` decimal(10, 2) NULL DEFAULT 0.00,
  `DATE_UPDATED` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`CHECK_UP_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `cost_transfers`;
CREATE TABLE `cost_transfers`  (
  `TRANSFER_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SOW_ID` int(11) NOT NULL,
  `BOAR_ID` int(11) NULL DEFAULT NULL,
  `SOW_COST` decimal(10, 2) NULL DEFAULT 0.00,
  `BOAR_COST` decimal(10, 2) NULL DEFAULT 0.00,
  `TOTAL_AMOUNT` decimal(10, 2) NOT NULL,
  `PIGLET_COUNT` int(11) NOT NULL,
  `COST_PER_HEAD` decimal(10, 2) NOT NULL,
  `TRANSFER_DATE` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `CREATED_BY` int(11) NULL DEFAULT 1,
  PRIMARY KEY (`TRANSFER_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `diseases`;
CREATE TABLE `diseases`  (
  `DISEASE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `DISEASE_NAME` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `SYMPTOMS` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `NOTES` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`DISEASE_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees`  (
  `EMPLOYEE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `EMPLOYEE_CODE` int(11) NOT NULL,
  `FULL_NAME` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `POSITION` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `CONTACT_NO` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `HIRE_DATE` date NULL DEFAULT NULL,
  `STATUS` enum('Active','Inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'Active',
  PRIMARY KEY (`EMPLOYEE_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `event_schedules`;
CREATE TABLE `event_schedules`  (
  `EVENT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ANIMAL_ID` int(11) NOT NULL,
  `EVENT_TYPE` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `ITEM_ID` int(11) NULL DEFAULT NULL,
  `START_DATE` datetime NOT NULL,
  `END_DATE` datetime NULL DEFAULT NULL,
  `INTERVAL_DAYS` int(11) NULL DEFAULT NULL,
  `STATUS` enum('Pending','Done','Cancelled','Archived') CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT 'Pending',
  `COMPLETED_AT` datetime NULL DEFAULT NULL,
  `IS_ACTIVE` tinyint(1) NULL DEFAULT 1,
  `CREATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`EVENT_ID`) USING BTREE,
  CONSTRAINT `event_schedules_ibfk_1` FOREIGN KEY (`ANIMAL_ID`) REFERENCES `animal_records` (`ANIMAL_ID`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `farm_roles`;
CREATE TABLE `farm_roles`  (
  `ROLE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ROLE_NAME` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `DESCRIPTION` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  PRIMARY KEY (`ROLE_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Compact;

INSERT INTO `farm_roles` VALUES (1, 'Farm Manager', 'Oversees overall farm operations.');

DROP TABLE IF EXISTS `fcr_configurations`;
CREATE TABLE `fcr_configurations`  (
  `CONFIG_ID` int(11) NOT NULL AUTO_INCREMENT,
  `CONFIG_TYPE` enum('Individual','Location','Building','Pen','Age') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `LOCATION_ID` int(11) NULL DEFAULT NULL,
  `BUILDING_ID` int(11) NULL DEFAULT NULL,
  `PEN_ID` int(11) NULL DEFAULT NULL,
  `ANIMAL_ID` int(11) NULL DEFAULT NULL,
  `MIN_AGE_DAYS` int(11) NULL DEFAULT NULL,
  `MAX_AGE_DAYS` int(11) NULL DEFAULT NULL,
  `TARGET_FCR` decimal(5, 2) NOT NULL,
  `CREATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`CONFIG_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `feeds`;
CREATE TABLE `feeds`  (
  `FEED_ID` int(11) NOT NULL AUTO_INCREMENT,
  `FEED_NAME` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `TOTAL_WEIGHT_KG` decimal(10, 3) NULL DEFAULT 0.000,
  `DATE_UPDATED` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `LOCATION_ID` int(11) NULL DEFAULT NULL,
  `TOTAL_COST` decimal(12, 2) NULL DEFAULT 0.00,
  `DATE_CREATED` datetime NULL DEFAULT NULL,
  `EXPIRATION_DATE` date NOT NULL,
  PRIMARY KEY (`FEED_ID`) USING BTREE,
  UNIQUE INDEX `unique_feed_batch`(`FEED_NAME`, `LOCATION_ID`, `EXPIRATION_DATE`) USING BTREE,
  CONSTRAINT `feeds_ibfk_1` FOREIGN KEY (`LOCATION_ID`) REFERENCES `locations` (`LOCATION_ID`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `feed_transactions`;
CREATE TABLE `feed_transactions`  (
  `FT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `FEED_ID` int(11) NOT NULL,
  `ANIMAL_ID` int(11) NOT NULL,
  `TRANSACTION_DATE` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `REMARKS` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `QUANTITY_KG` decimal(10, 3) NULL DEFAULT NULL,
  `TRANSACTION_COST` decimal(12, 2) NULL DEFAULT NULL,
  `BATCH_ID` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`FT_ID`) USING BTREE,
  CONSTRAINT `feed_transactions_ibfk_1` FOREIGN KEY (`FEED_ID`) REFERENCES `feeds` (`FEED_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `feed_transactions_ibfk_2` FOREIGN KEY (`ANIMAL_ID`) REFERENCES `animal_records` (`ANIMAL_ID`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `inventory_adjustments`;
CREATE TABLE `inventory_adjustments`  (
  `ADJUSTMENT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `TRANSACTION_DATE` datetime NOT NULL,
  `CATEGORY` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'feed, medicine, vitamin, vaccine',
  `REF_ID` int(11) NOT NULL COMMENT 'The ID from source table',
  `ITEM_NAME` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `BATCH_EXPIRY` date NULL DEFAULT NULL,
  `ADJUSTMENT_TYPE` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Deduct',
  `INPUT_MODE` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'By Amount or By Balance',
  `QUANTITY` decimal(10, 3) NOT NULL COMMENT 'The actual amount deducted/added',
  `PREVIOUS_STOCK` decimal(10, 3) NOT NULL,
  `NEW_STOCK` decimal(10, 3) NOT NULL,
  `REASON` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `REMARKS` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `CREATED_BY` int(11) NULL DEFAULT NULL,
  `DATE_CREATED` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ADJUSTMENT_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `item_types`;
CREATE TABLE `item_types`  (
  `ITEM_TYPE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ITEM_TYPE_NAME` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ITEM_TYPE_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

INSERT INTO `item_types` VALUES (7, 'Administration & Records'), (13, 'Animals'), (6, 'Breeding & Reproduction'), (4, 'Farm Equipment & Tools'), (2, 'Feeds & Feeding Supplies'), (3, 'Housing & Facilities'), (8, 'Maintenance & Parts'), (1, 'Medicine'), (12, 'Others'), (5, 'Sanitation & Waste Management'), (9, 'Utilities & Consumables'), (11, 'Vaccine'), (10, 'Vitamins & Supplements');

DROP TABLE IF EXISTS `units`;
CREATE TABLE `units`  (
  `UNIT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `UNIT_NAME` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `UNIT_ABBR` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `DATE_UPDATED` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`UNIT_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

INSERT INTO `units` VALUES (1, 'milliliter', 'ml', NULL), (2, 'grams', 'g', NULL), (3, 'kilograms', 'kg', NULL), (4, 'pieces', 'pcs', NULL);

DROP TABLE IF EXISTS `items`;
CREATE TABLE `items`  (
  `ITEM_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ITEM_NAME` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ITEM_TYPE_ID` int(11) NOT NULL,
  `UNIT_ID` int(11) NOT NULL,
  `UNIT_COST` decimal(12, 2) NULL DEFAULT NULL,
  `CREATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ITEM_CATEGORY` tinyint(1) NULL DEFAULT 0,
  `ITEM_DESCRIPTION` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `ITEM_NET_WEIGHT` decimal(10, 2) NULL DEFAULT NULL,
  `DATE_OF_PURCHASE` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `QUANTITY` int(11) NULL DEFAULT NULL,
  `TOTAL_COST` decimal(10, 2) NULL DEFAULT NULL,
  `TOTAL_QTY` decimal(10, 2) NULL DEFAULT NULL,
  `LOCATION_ID` int(11) NULL DEFAULT NULL,
  `BUILDING_ID` int(11) NULL DEFAULT NULL,
  `PEN_ID` int(11) NULL DEFAULT NULL,
  `STATUS` int(11) NULL DEFAULT 0,
  `DATE_UPDATED` datetime NULL DEFAULT NULL,
  `EXPIRATION_DATE` date NULL DEFAULT NULL,
  `SUPPLIER` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `REFERENCE_NO` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`ITEM_ID`) USING BTREE,
  CONSTRAINT `items_ibfk_1` FOREIGN KEY (`ITEM_TYPE_ID`) REFERENCES `item_types` (`ITEM_TYPE_ID`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `items_ibfk_2` FOREIGN KEY (`UNIT_ID`) REFERENCES `units` (`UNIT_ID`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `medicines`;
CREATE TABLE `medicines`  (
  `SUPPLY_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SUPPLY_NAME` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `TOTAL_STOCK` decimal(10, 3) NULL DEFAULT 0.000,
  `DATE_UPDATED` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `UNIT_ID` int(11) NULL DEFAULT NULL,
  `DATE_CREATED` datetime NULL DEFAULT NULL,
  `TOTAL_COST` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `EXPIRATION_DATE` date NOT NULL,
  `LOCATION_ID` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`SUPPLY_ID`) USING BTREE,
  CONSTRAINT `fk_medicine_location` FOREIGN KEY (`LOCATION_ID`) REFERENCES `locations` (`LOCATION_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `medicines_ibfk_1` FOREIGN KEY (`UNIT_ID`) REFERENCES `units` (`UNIT_ID`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `operational_cost`;
CREATE TABLE `operational_cost`  (
  `op_cost_id` int(11) NOT NULL AUTO_INCREMENT,
  `animal_id` int(11) NOT NULL,
  `operation_cost` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `description` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT 'Operational Cost',
  `datetime_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`op_cost_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `pens`;
CREATE TABLE `pens`  (
  `PEN_ID` int(11) NOT NULL AUTO_INCREMENT,
  `PEN_NAME` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `BUILDING_ID` int(11) NOT NULL,
  `UPDATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`PEN_ID`) USING BTREE,
  CONSTRAINT `pens_ibfk_1` FOREIGN KEY (`BUILDING_ID`) REFERENCES `buildings` (`BUILDING_ID`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `service_day_limits`;
CREATE TABLE `service_day_limits`  (
  `SERVICE_NUMBER` int(11) NOT NULL,
  `MAX_DAYS_ALLOWED` int(11) NOT NULL,
  PRIMARY KEY (`SERVICE_NUMBER`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Compact;

INSERT INTO `service_day_limits` VALUES (1, 21), (2, 21), (3, 21), (4, 21), (5, 21);

DROP TABLE IF EXISTS `sow_birthing_records`;
CREATE TABLE `sow_birthing_records`  (
  `RECORD_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ANIMAL_ID` int(11) NOT NULL,
  `PARITY` int(11) NOT NULL,
  `DATE_FARROWED` date NOT NULL,
  `TOTAL_BORN` int(11) NULL DEFAULT 0,
  `ACTIVE_COUNT` int(11) NULL DEFAULT 0,
  `DEAD_COUNT` int(11) NULL DEFAULT 0,
  `MUMMIFIED_COUNT` int(11) NULL DEFAULT 0,
  `CREATED_BY` int(11) NULL DEFAULT NULL,
  `REMARKS` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `CREATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RECORD_ID`) USING BTREE,
  CONSTRAINT `sow_birthing_records_ibfk_1` FOREIGN KEY (`ANIMAL_ID`) REFERENCES `animal_records` (`ANIMAL_ID`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `sow_service_history`;
CREATE TABLE `sow_service_history`  (
  `SERVICE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ANIMAL_ID` int(11) NOT NULL,
  `SERVICE_NUMBER` int(11) NOT NULL,
  `SERVICE_TYPE` enum('Natural','Artificial') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'Natural',
  `BOAR_ID` int(11) NULL DEFAULT NULL,
  `SERVICE_START_DATE` date NOT NULL,
  `SERVICE_END_DATE` date NULL DEFAULT NULL,
  `IS_ACTIVE` tinyint(1) NULL DEFAULT 0,
  `IS_CANCELLED` tinyint(1) NULL DEFAULT 0,
  `CYCLE_ID` int(11) NULL DEFAULT NULL,
  `REMARKS` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `CREATED_BY` int(11) NULL DEFAULT NULL,
  `CREATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`SERVICE_ID`) USING BTREE,
  CONSTRAINT `fk_service_boar` FOREIGN KEY (`BOAR_ID`) REFERENCES `animal_records` (`ANIMAL_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `sow_service_history_ibfk_1` FOREIGN KEY (`ANIMAL_ID`) REFERENCES `animal_records` (`ANIMAL_ID`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `sow_status_history`;
CREATE TABLE `sow_status_history`  (
  `STATUS_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ANIMAL_ID` int(11) NOT NULL,
  `STATUS_NAME` enum('DRY','SERVICE 1','SERVICE 2','SERVICE 3','SERVICE 4','SERVICE 5','PREGNANT','BIRTHING','ABORTION') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `PARITY` int(11) NULL DEFAULT NULL,
  `STATUS_START_DATE` datetime NULL DEFAULT NULL,
  `STATUS_END_DATE` datetime NULL DEFAULT NULL,
  `IS_ACTIVE` tinyint(1) NULL DEFAULT 0,
  `CREATED_BY` int(11) NULL DEFAULT NULL,
  `CREATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `SOW_CARD_CREATED` tinyint(1) NULL DEFAULT 0,
  PRIMARY KEY (`STATUS_ID`) USING BTREE,
  CONSTRAINT `sow_status_history_ibfk_1` FOREIGN KEY (`ANIMAL_ID`) REFERENCES `animal_records` (`ANIMAL_ID`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers`  (
  `SUPPLIER_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SUPPLIER_NAME` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `CONTACT_PERSON` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `CONTACT_NUMBER` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `EMAIL` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `ADDRESS` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `STATUS` enum('Active','Inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'Active',
  `CREATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UPDATED_AT` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`SUPPLIER_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `transaction_types`;
CREATE TABLE `transaction_types`  (
  `TRANS_TYPE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `TRANS_TYPE_NAME` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`TRANS_TYPE_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `treatment_transactions`;
CREATE TABLE `treatment_transactions`  (
  `TT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ANIMAL_ID` int(11) NULL DEFAULT NULL,
  `ITEM_ID` int(11) NULL DEFAULT NULL,
  `DOSAGE` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `ADMINISTERED_BY` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `QUANTITY_USED` decimal(10, 2) NULL DEFAULT NULL,
  `TRANSACTION_DATE` datetime NULL DEFAULT NULL,
  `REMARKS` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `CREATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `DATE_UPDATED` datetime NULL DEFAULT NULL,
  `TOTAL_COST` decimal(10, 2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`TT_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `vaccines`;
CREATE TABLE `vaccines`  (
  `SUPPLY_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SUPPLY_NAME` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `TOTAL_STOCK` bigint(20) NULL DEFAULT NULL,
  `DATE_UPDATED` datetime NULL DEFAULT NULL,
  `UNIT_ID` bigint(20) NULL DEFAULT NULL,
  `DATE_CREATED` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `TOTAL_COST` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `EXPIRATION_DATE` date NOT NULL,
  `LOCATION_ID` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`SUPPLY_ID`) USING BTREE,
  CONSTRAINT `fk_vaccine_location` FOREIGN KEY (`LOCATION_ID`) REFERENCES `locations` (`LOCATION_ID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `vaccination_records`;
CREATE TABLE `vaccination_records`  (
  `VACCINATION_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ANIMAL_ID` int(11) NOT NULL,
  `ITEM_ID` int(11) NOT NULL,
  `VET_NAME` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `QUANTITY` decimal(10, 3) NULL DEFAULT NULL,
  `UNIT_ID` int(11) NULL DEFAULT NULL,
  `VACCINATION_COST` decimal(12, 2) NULL DEFAULT 0.00,
  `ADMINISTERED_BY` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `VACCINE_COST` decimal(12, 2) NULL DEFAULT 0.00,
  `REMARKS` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `VACCINATION_DATE` datetime NULL DEFAULT NULL,
  `DATE_UPDATED` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`VACCINATION_ID`) USING BTREE,
  CONSTRAINT `vaccination_records_ibfk_1` FOREIGN KEY (`ANIMAL_ID`) REFERENCES `animal_records` (`ANIMAL_ID`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `vaccination_records_ibfk_2` FOREIGN KEY (`ITEM_ID`) REFERENCES `vaccines` (`SUPPLY_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `veterinarians`;
CREATE TABLE `veterinarians`  (
  `VET_ID` int(11) NOT NULL AUTO_INCREMENT,
  `FULL_NAME` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CONTACT_INFO` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `IS_ACTIVE` tinyint(1) NOT NULL DEFAULT 1,
  `CREATED_AT` datetime NULL DEFAULT NULL,
  `UPDATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`VET_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `vitamins_supplements`;
CREATE TABLE `vitamins_supplements`  (
  `SUPPLY_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SUPPLY_NAME` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `TOTAL_STOCK` bigint(20) NULL DEFAULT NULL,
  `DATE_UPDATED` datetime NULL DEFAULT NULL,
  `UNIT_ID` bigint(20) NULL DEFAULT NULL,
  `DATE_CREATED` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `TOTAL_COST` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `EXPIRATION_DATE` date NOT NULL,
  `LOCATION_ID` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`SUPPLY_ID`) USING BTREE,
  CONSTRAINT `fk_vitamin_location` FOREIGN KEY (`LOCATION_ID`) REFERENCES `locations` (`LOCATION_ID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

DROP TABLE IF EXISTS `vitamins_supplements_transactions`;
CREATE TABLE `vitamins_supplements_transactions`  (
  `VST_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ANIMAL_ID` int(11) NULL DEFAULT NULL,
  `ITEM_ID` int(11) NULL DEFAULT NULL,
  `DOSAGE` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `QUANTITY_USED` decimal(10, 2) NULL DEFAULT NULL,
  `TRANSACTION_DATE` datetime NULL DEFAULT NULL,
  `REMARKS` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `CREATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `DATE_UPDATED` datetime NULL DEFAULT NULL,
  `TOTAL_COST` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `ADMINISTERED_BY` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`VST_ID`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Compact;

SET FOREIGN_KEY_CHECKS = 1;
SQL;

            $tenant_conn->exec($schema_tables);

            // B. MYSQL EVENT
            $schema_event = <<<SQL
CREATE EVENT `daily_animal_classification_update`
ON SCHEDULE EVERY 1 DAY STARTS '2026-01-06 15:35:42'
DO UPDATE animal_records ar
    JOIN animal_classifications ac 
        ON DATEDIFF(NOW(), ar.BIRTH_DATE) BETWEEN ac.MIN_DAYS AND ac.MAX_DAYS
    SET ar.CLASS_ID = ac.CLASS_ID
    WHERE 
        ar.IS_ACTIVE = 1 
        AND ar.BIRTH_DATE IS NOT NULL
        AND (ac.REQUIRED_SEX IS NULL OR ac.REQUIRED_SEX COLLATE utf8mb4_unicode_ci = ar.SEX);
SQL;
            $tenant_conn->exec($schema_event);

            // C. MYSQL TRIGGER
            $schema_trigger = <<<SQL
CREATE TRIGGER `trg_classify_animal_insert` BEFORE INSERT ON `animal_records` FOR EACH ROW 
BEGIN
    DECLARE found_class_id INT DEFAULT NULL;

    IF NEW.BIRTH_DATE IS NOT NULL THEN
        SELECT CLASS_ID INTO found_class_id
        FROM animal_classifications
        WHERE DATEDIFF(CURDATE(), NEW.BIRTH_DATE) BETWEEN MIN_DAYS AND MAX_DAYS
          AND (REQUIRED_SEX IS NULL OR REQUIRED_SEX = NEW.SEX)
        LIMIT 1;

        IF found_class_id IS NOT NULL THEN
            SET NEW.CLASS_ID = found_class_id;
        ELSE
            SELECT CLASS_ID INTO found_class_id FROM animal_classifications WHERE STAGE_NAME = 'Unknown Stage' LIMIT 1;
            SET NEW.CLASS_ID = found_class_id;
        END IF;
    ELSE
        SELECT CLASS_ID INTO found_class_id FROM animal_classifications WHERE STAGE_NAME = 'Unknown Stage' LIMIT 1;
        SET NEW.CLASS_ID = found_class_id;
    END IF;
END;
SQL;
            $tenant_conn->exec($schema_trigger);

            // D. INSERT THE NEW CLIENT AS A SUPER ADMIN INTO THE TENANT DATABASE
            $clean_phone = preg_replace('/[^0-9]/', '', $owner_phone);
            
            $stmtNewAdmin = $tenant_conn->prepare("INSERT INTO users (FULL_NAME, EMAIL, CONTACT_INFO, PASSWORD, USER_TYPE, IS_ACTIVE) VALUES (?, ?, ?, ?, 4, 1)");
            $stmtNewAdmin->execute([$owner_name, $owner_email, $clean_phone ?: null, $app_pass_hashed]);

            $tenant_conn = null;

        } catch (PDOException $schemaError) {
            throw new Exception("Schema Migration Failed: " . $schemaError->getMessage());
        }

        // ========================================================================
        // 5. UPDATE MASTER DB RECORDS & ASSIGNMENTS
        // ========================================================================
        $conn->beginTransaction();

        // Insert Farm
        $stmtFarm = $conn->prepare("INSERT INTO farms (farm_name, admin_id, farm_status) VALUES (?, ?, 1)");
        $stmtFarm->execute([$farm_name, $admin_id]);
        $farm_id = $conn->lastInsertId();

        // Assign to new table
        $stmtAssign = $conn->prepare("INSERT INTO assigned_farms (admin_id, farm_id) VALUES (?, ?)");
        $stmtAssign->execute([$admin_id, $farm_id]);

        $conn->commit();

        echo json_encode([
            'success'      => true,
            'message'      => 'Farm database and client created successfully.',
            'db_name'      => $db_name
        ]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        
        if ($db_created) {
            try {
                $conn->exec("DROP DATABASE IF EXISTS `$db_name`");
            } catch (Exception $cleanupError) {}
        }

        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>