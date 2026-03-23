<?php
// globalxadminzportal/saveRegister.php
// Handles ALL registration paths:
//   A) superadmin — inserts into sadmin_farms.users, status=0 (pending superadmin approval)
//   B) owner      — inserts into sadmin_farms.users, status=0 (pending superadmin approval)
//   C) employee   — inserts into sadmin_farms.users AND the target farm's tenant users table
//
// EMPLOYEE DUAL-WRITE:
//   The employee is written to the farm tenant DB immediately on registration,
//   with IS_ACTIVE=0 (locked until owner approves) and USER_TYPE=1.
//   A zeroed access_control row is also created.
//
// The farm_code the employee inputs is farms.farm_code from sadmin_farms —
// the human-friendly code the farm owner shares with employees (e.g. FP-744F00).
// Each farm has its own unique farm_code. db_key is internal and never user-facing.

header('Content-Type: application/json');

require_once '../config/SadminConnection.php';
require_once '../config/FarmConnection.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

$role      = trim($data['role']      ?? 'superadmin');
$full_name = trim($data['full_name'] ?? '');
$email     = trim($data['email']     ?? '');
$password  =      $data['password']  ?? '';
$phone_no  = trim($data['phone_no']  ?? '');

if (empty($full_name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Full name, email, and password are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

if (!in_array($role, ['superadmin', 'owner', 'employee'], true)) {
    $role = 'superadmin';
}

$dupCheck = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$dupCheck->execute([$email]);
if ($dupCheck->fetch()) {
    echo json_encode(['success' => false, 'message' => 'This email is already registered.']);
    exit;
}

$hashed = password_hash($password, PASSWORD_BCRYPT);

// ══════════════════════════════════════════════════════════════════════════════
// PATH A — SUPERADMIN
// ══════════════════════════════════════════════════════════════════════════════
if ($role === 'superadmin') {
    $insert = $conn->prepare("
        INSERT INTO users (full_name, email, password, role, status, phone_no)
        VALUES (?, ?, ?, 'superadmin', 0, ?)
    ");
    $insert->execute([$full_name, $email, $hashed, $phone_no ?: null]);
    echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Your Super Admin account is pending approval. An existing Super Admin will review your request.']);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// PATH B — OWNER
// ══════════════════════════════════════════════════════════════════════════════
if ($role === 'owner') {
    $insert = $conn->prepare("
        INSERT INTO users (full_name, email, password, role, status, phone_no)
        VALUES (?, ?, ?, 'owner', 0, ?)
    ");
    $insert->execute([$full_name, $email, $hashed, $phone_no ?: null]);
    echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Your Owner account is pending approval by a Super Admin.']);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// PATH C — EMPLOYEE
// Looks up the farm via farms.farm_code — the human-friendly code owners share
// with their employees. Each farm has its own unique code.
// ══════════════════════════════════════════════════════════════════════════════
$farm_code = strtoupper(trim($data['farm_code'] ?? ''));

if (empty($farm_code)) {
    echo json_encode(['success' => false, 'message' => 'Farm Code is required for employee registration.']);
    exit;
}

// Resolve farm via farms.farm_code
$farmLookup = $conn->prepare("
    SELECT  f.farm_id,
            f.farm_name,
            f.farm_status,
            f.db_key,
            dc.db_name,
            dc.db_host,
            dc.db_user,
            dc.db_pass,
            dc.db_port,
            dc.is_active AS dc_active
    FROM    farms f
    JOIN    database_connections dc ON dc.db_key = f.db_key
    WHERE   f.farm_code = ?
    LIMIT   1
");
$farmLookup->execute([$farm_code]);
$farm = $farmLookup->fetch(PDO::FETCH_ASSOC);

if (!$farm) {
    echo json_encode(['success' => false, 'message' => 'Invalid Farm Code. Please check the code provided by your farm owner.']);
    exit;
}

if ((int)$farm['farm_status'] !== 1) {
    echo json_encode(['success' => false, 'message' => "The farm \"{$farm['farm_name']}\" is currently inactive. Please contact your farm owner."]);
    exit;
}

if (!(int)$farm['dc_active']) {
    echo json_encode(['success' => false, 'message' => 'This farm database is currently unavailable. Please contact support.']);
    exit;
}

// Check duplicate in tenant DB
try {
    $farmConn = getFarmConnection((int)$farm['farm_id']);
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => 'Could not connect to the farm database. Please try again.']);
    exit;
}

$tenantDupCheck = $farmConn->prepare("SELECT USER_ID FROM users WHERE EMAIL = ? LIMIT 1");
$tenantDupCheck->execute([$email]);
if ($tenantDupCheck->fetch()) {
    echo json_encode(['success' => false, 'message' => 'This email is already registered in that farm. Contact your farm owner.']);
    exit;
}

// Dual-write: tenant DB first, then central DB
$tenant_user_id = null;



try {
    $clean_phone = preg_replace('/[^0-9]/', '', $phone_no);

    $tenantInsert = $farmConn->prepare("
        INSERT INTO users
            (FULL_NAME, EMAIL, PASSWORD, CREATED_AT, USER_TYPE, CONTACT_INFO, GOOGLE_ID, IS_ACTIVE, LOCATION_ID)
        VALUES (?, ?, ?, NOW(), 1, ?, NULL, 0, NULL)
    ");
    $tenantInsert->execute([$full_name, $email, $hashed, $clean_phone ? (int)$clean_phone : null]);
    $tenant_user_id = (int)$farmConn->lastInsertId();

    $tenantAccess = $farmConn->prepare("
        INSERT INTO access_control (
            user_id,
            dashboard, animal_record, farm_roles, employee_list,
            animal_type, location, building, pen, breed,
            veterinary, diseases, buyer, suppliers,
            costing, animal_cost,
            feed_consumption, medication_treatment, vaccinations,
            vitamins_supplements, veterinary_checkups,
            farm, animal_class, edit_bio_info, event_scheduler,
            animal_transfer, sow_status, fcr_management, animal_weights,
            animal_operations, sow_cards, birth_certificate, cost_transfer,
            analytics_dashboard, animals_livestock_analytics, medicine_analytics,
            vitamins_supplements_analytics, vaccines_analytics,
            feeds_feeding_analytics, housing_facilities_analytics,
            farm_equipment_tools_analytics, sanitation_waste_analytics,
            breeding_reproduction_analytics, administration_records_analytics,
            maintenance_parts_analytics, utilities_consumables_analytics,
            others_analytics,
            reports, animal_report, active_users_report, medicine_report,
            feeds_feeding_supplies_report, housing_facilities_report,
            farm_equipment_tools_report, sanitation_waste_management_report,
            breeding_reproduction_report, administration_records_report,
            maintenance_parts_report, utilities_consumables_report,
            vitamins_supplements_report, vaccine_report, others_report,
            audit_log_report, medication_report, vaccination_report,
            vitamins_supplements_transaction_report, feeding_transaction_report,
            animal_sales_report,
            transactions, individual_operations, feeding, medication,
            vitamins_supplements_trans, check_ups, vaccination, purchases,
            sell_animals,
            batch_group_operations, group_medication, group_vitamins,
            group_checkup, group_vaccination, group_sell_animals,
            settings, manage_accounts, audit_logs,
            created_at
        )
        VALUES (
            ?,
            0,0,0,0, 0,0,0,0,0,
            0,0,0, 0,0,
            0,0,0, 0,0, 0,
            0,0,0,0, 0,0,0,0,
            0,0,0,0,
            0,0,0, 0,0,
            0,0, 0,0,
            0,0, 0,0,
            0,
            0,0,0,0, 0,0,
            0,0, 0,0,
            0,0, 0,0,0,
            0,0,0, 0,0,
            0,
            0,0,0,0, 0,0,0,0,
            0,
            0,0,0, 0,0,0,
            0,0,0,
            NOW()
        )
    ");
    $tenantAccess->execute([$tenant_user_id]);

} catch (Exception $e) {
    if ($tenant_user_id) {
        try { $farmConn->prepare("DELETE FROM users WHERE USER_ID = ?")->execute([$tenant_user_id]); } catch (Exception $ce) {}
    }
    error_log("saveRegister: tenant write failed — " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Registration failed while writing to the farm database. Please try again.']);
    exit;
}

// Central DB write
try {
    $conn->beginTransaction();

    $centralInsert = $conn->prepare("
        INSERT INTO users (full_name, email, password, role, status, phone_no)
        VALUES (?, ?, ?, 'employee', 0, ?)
    ");
    $centralInsert->execute([$full_name, $email, $hashed, $phone_no ?: null]);
    $central_user_id = (int)$conn->lastInsertId();

    $assignInsert = $conn->prepare("INSERT INTO assigned_farms (user_id, farm_id) VALUES (?, ?)");
    $assignInsert->execute([$central_user_id, (int)$farm['farm_id']]);

    $conn->commit();

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    try {
        $farmConn->prepare("DELETE FROM access_control WHERE user_id = ?")->execute([$tenant_user_id]);
        $farmConn->prepare("DELETE FROM users WHERE USER_ID = ?")->execute([$tenant_user_id]);
    } catch (Exception $ce) {}
    error_log("saveRegister: central write failed — " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    exit;
}

echo json_encode([
    'success'   => true,
    'status'    => 'pending',
    'farm_name' => $farm['farm_name'],
    'message'   => "Your registration for \"{$farm['farm_name']}\" has been submitted. The farm owner will review and approve your account.",
]);