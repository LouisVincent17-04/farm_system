<?php
// globalxadminportal/saveClientFarm.php
header('Content-Type: application/json');
session_start();

include '../config/SadminConnection.php';

// ── Auth guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['admin']) || $_SESSION['admin']['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$admin_id  = $_SESSION['admin']['admin_id'];
$ip        = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception("Invalid request data.");

    // ── Validate inputs ───────────────────────────────────────────────────────
    $farm_name   = trim($input['farm_name']   ?? '');
    $owner_name  = trim($input['owner_name']  ?? '');
    $owner_email = trim($input['owner_email'] ?? '');
    $owner_phone = trim($input['owner_phone'] ?? '');
    $plan        = $input['plan']  ?? 'Basic';
    $max_users   = (int)($input['max_users']   ?? 5);
    $max_animals = (int)($input['max_animals'] ?? 500);
    $trial_days  = (int)($input['trial_days']  ?? 30);

    if (empty($farm_name))   throw new Exception("Farm name is required.");
    if (empty($owner_name))  throw new Exception("Owner name is required.");
    if (empty($owner_email)) throw new Exception("Owner email is required.");
    if (!in_array($plan, ['Basic','Standard','Premium'])) throw new Exception("Invalid plan.");

    // ── Derive DB name: sanitize farm name → snake_case ──────────────────────
    // e.g. "Green Pastures Farm" → "farm_green_pastures_farm"
    $db_name = 'farm_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $farm_name));
    $db_name = preg_replace('/_+/', '_', $db_name); // collapse multiple underscores
    $db_name = trim($db_name, '_');

    // ── Check uniqueness ──────────────────────────────────────────────────────
    $check = $conn->prepare("SELECT farm_id FROM farms WHERE farm_name = ? OR db_name = ?");
    $check->execute([$farm_name, $db_name]);
    if ($check->fetch()) throw new Exception("A farm with this name already exists.");

    // ── Generate DB credentials ───────────────────────────────────────────────
    $db_password_plain = bin2hex(random_bytes(12)); // 24-char random password
    $trial_ends_at = date('Y-m-d', strtotime("+{$trial_days} days"));

    // ── BEGIN: Provision the new farm database ────────────────────────────────
    $rootConn = getRootConn();

    // 1. Create the database
    $rootConn->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 2. Create a dedicated MySQL user for this farm
    //    Using 'localhost' host — change to '%' if on separate DB server
    $rootConn->exec("CREATE USER IF NOT EXISTS '{$db_name}_user'@'localhost' IDENTIFIED BY '{$db_password_plain}'");
    $rootConn->exec("GRANT ALL PRIVILEGES ON `{$db_name}`.* TO '{$db_name}_user'@'localhost'");
    $rootConn->exec("FLUSH PRIVILEGES");

    // 3. Seed the new database from the template SQL file
    //    The template is a clean export of the farm schema (no data, structure only)
    $templatePath = __DIR__ . '/../config/farm_template.sql';
    if (!file_exists($templatePath)) {
        throw new Exception("Farm template SQL not found at: $templatePath");
    }

    $sql = file_get_contents($templatePath);

    // Switch PDO connection to the new database
    $farmConn = new PDO(
        "mysql:host=" . SADMIN_DB_HOST . ";dbname={$db_name};charset=utf8mb4",
        SADMIN_DB_ROOT_USER,
        SADMIN_DB_ROOT_PASS,
        [
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => true,
        ]
    );

    // Execute the template SQL (multi-statement)
    $farmConn->exec($sql);

    // ── Register in control plane ─────────────────────────────────────────────
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        INSERT INTO farms (
            farm_name, db_name, db_user, db_password,
            status, plan, owner_name, owner_email, owner_phone,
            max_users, max_animals, trial_ends_at
        ) VALUES (
            :farm_name, :db_name, :db_user, :db_password,
            'Trial', :plan, :owner_name, :owner_email, :owner_phone,
            :max_users, :max_animals, :trial_ends_at
        )
    ");

    $stmt->execute([
        ':farm_name'    => $farm_name,
        ':db_name'      => $db_name,
        ':db_user'      => $db_name . '_user',
        ':db_password'  => password_hash($db_password_plain, PASSWORD_BCRYPT),
        ':plan'         => $plan,
        ':owner_name'   => $owner_name,
        ':owner_email'  => $owner_email,
        ':owner_phone'  => $owner_phone,
        ':max_users'    => $max_users,
        ':max_animals'  => $max_animals,
        ':trial_ends_at'=> $trial_ends_at,
    ]);

    $new_farm_id = $conn->lastInsertId();

    // Log the provisioning event
    $conn->prepare("
        INSERT INTO farm_activity_log (farm_id, admin_id, action, details, ip_address)
        VALUES (?, ?, 'FARM_CREATED', ?, ?)
    ")->execute([
        $new_farm_id,
        $admin_id,
        "Farm '{$farm_name}' provisioned. DB: {$db_name}. Plan: {$plan}. Trial ends: {$trial_ends_at}.",
        $ip
    ]);

    $conn->commit();

    echo json_encode([
        'success'    => true,
        'message'    => "Farm '{$farm_name}' provisioned successfully!",
        'farm_id'    => $new_farm_id,
        'db_name'    => $db_name,
        'db_user'    => $db_name . '_user',
        'db_password'=> $db_password_plain, // Show once — save this!
        'trial_ends' => $trial_ends_at,
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>