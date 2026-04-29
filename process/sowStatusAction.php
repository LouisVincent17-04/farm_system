<?php
// process/sowStatusAction.php
session_start();
require_once '../config/Connection.php';

// --- AUDIT LOG CONTEXT ---
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : 1;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $animal_id    = $_POST['animal_id']     ?? null;
    $action_type  = $_POST['action_type']   ?? null;
    $current_status = $_POST['current_status'] ?? '';

    // Validate required fields up-front
    if (empty($animal_id) || empty($action_type)) {
        echo json_encode(['error' => 'Missing required parameters.']);
        exit;
    }

    // NEW INPUTS
    $service_type = $_POST['service_type'] ?? 'Natural';
    $boar_id      = !empty($_POST['boar_id']) ? $_POST['boar_id'] : null;

    // FETCH THE EXPLICIT DATE FROM THE FRONTEND FORMS
    $raw_action_date = !empty($_POST['action_date']) ? trim($_POST['action_date']) : date('Y-m-d H:i:s');

    // Normalize: replace HTML datetime-local "T" separator with space for MySQL
    $action_date = str_replace('T', ' ', $raw_action_date);
    // Pad missing seconds
    if (strlen($action_date) === 16) {
        $action_date .= ':00';
    }

    // Validate the resulting datetime
    $dtCheck = DateTime::createFromFormat('Y-m-d H:i:s', $action_date);
    if (!$dtCheck) {
        echo json_encode(['error' => 'Invalid date/time supplied: ' . htmlspecialchars($action_date)]);
        exit;
    }

    try {
        $conn->beginTransaction();

        // Fetch Tag Number for Audit Log
        $tagStmt = $conn->prepare("SELECT TAG_NO FROM animal_records WHERE ANIMAL_ID = ?");
        $tagStmt->execute([$animal_id]);
        $tag_no = $tagStmt->fetchColumn() ?: 'Unknown';

        // =========================================================
        // LOGIC FOR UNDO
        // =========================================================
        if ($action_type === 'undo') {

            // BUG FIX 1: Original query fetched by STATUS_ID < current, which relies on
            // auto-increment ordering only and breaks when IDs are non-sequential or
            // when a status was re-inserted (e.g. after a previous undo).
            // FIX: Fetch the currently active status first.
            $stmt = $conn->prepare("
                SELECT STATUS_ID, STATUS_NAME 
                FROM sow_status_history 
                WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1
            ");
            $stmt->execute([$animal_id]);
            $currentStatusRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentStatusRow) {
                throw new Exception("No active status found to undo.");
            }

            // BUG FIX 2: The previous query used STATUS_ID < current, which fails if
            // records were inserted out of order or re-activated. We now order by
            // STATUS_START_DATE DESC + STATUS_ID DESC to find the true prior record,
            // excluding the current active one.
            $stmtPrev = $conn->prepare("
                SELECT STATUS_ID, STATUS_NAME 
                FROM sow_status_history 
                WHERE ANIMAL_ID = ? 
                  AND STATUS_ID != ?
                  AND IS_ACTIVE = 0
                ORDER BY STATUS_START_DATE DESC, STATUS_ID DESC 
                LIMIT 1
            ");
            $stmtPrev->execute([$animal_id, $currentStatusRow['STATUS_ID']]);
            $prevStatusRow = $stmtPrev->fetch(PDO::FETCH_ASSOC);

            if (!$prevStatusRow) {
                throw new Exception("No previous status found to revert to. This is the first status entry.");
            }

            // Close the current active status, stamping it with the undo action date
            $stmtClose = $conn->prepare("
                UPDATE sow_status_history 
                SET IS_ACTIVE = 0, STATUS_END_DATE = ? 
                WHERE STATUS_ID = ?
            ");
            $stmtClose->execute([$action_date, $currentStatusRow['STATUS_ID']]);

            // BUG FIX 3: Original cancel query used IS_ACTIVE = 1, but after a previous
            // forward action the service record was already closed (IS_ACTIVE = 0).
            // We now target the most recent service record for this animal regardless
            // of IS_ACTIVE, so an undo correctly cancels it.
            if (strpos($currentStatusRow['STATUS_NAME'], 'SERVICE') !== false) {
                $stmtCancel = $conn->prepare("
                    UPDATE sow_service_history 
                    SET IS_ACTIVE = 0, IS_CANCELLED = 1, SERVICE_END_DATE = ? 
                    WHERE ANIMAL_ID = ? 
                    ORDER BY SERVICE_ID DESC 
                    LIMIT 1
                ");
                $stmtCancel->execute([$action_date, $animal_id]);
            }

            // Reactivate the previous status and clear its end date
            $stmtReactivate = $conn->prepare("
                UPDATE sow_status_history 
                SET IS_ACTIVE = 1, STATUS_END_DATE = NULL 
                WHERE STATUS_ID = ?
            ");
            $stmtReactivate->execute([$prevStatusRow['STATUS_ID']]);

            // BUG FIX 4: Original query to reactivate previous service used a compound
            // WHERE (IS_CANCELLED = 0) after previously setting IS_CANCELLED = 1 in the
            // same request, so it could never match the record it needed.
            // FIX: Find the most recent service record that belongs to this status
            // specifically, matching the service number from the status name, and
            // reactivate it regardless of IS_CANCELLED value.
            if (strpos($prevStatusRow['STATUS_NAME'], 'SERVICE') !== false) {
                // Extract service number from status name (e.g. "SERVICE 2" → 2)
                $prevServiceNum = (int) filter_var($prevStatusRow['STATUS_NAME'], FILTER_SANITIZE_NUMBER_INT);

                $stmtReactivateServ = $conn->prepare("
                    UPDATE sow_service_history 
                    SET IS_ACTIVE = 1, IS_CANCELLED = 0, SERVICE_END_DATE = NULL 
                    WHERE ANIMAL_ID = ? 
                      AND SERVICE_NUMBER = ?
                    ORDER BY SERVICE_ID DESC 
                    LIMIT 1
                ");
                $stmtReactivateServ->execute([$animal_id, $prevServiceNum]);
            }

            $audit_action  = "SOW_STATUS_UNDO";
            $audit_details = "Reverted Sow $tag_no from '{$currentStatusRow['STATUS_NAME']}' back to '{$prevStatusRow['STATUS_NAME']}' at $action_date.";

        }
        // =========================================================
        // LOGIC FOR NEXT STAGE / REPEAT SERVICE / ABORTION
        // =========================================================
        elseif (in_array($action_type, ['next_stage', 'repeat_service', 'abortion'])) {

            // Determine New Status Name
            $new_status = '';

            if ($action_type === 'abortion') {
                $new_status = 'ABORTION';

            } elseif ($current_status === 'DRY') {
                $new_status = 'SERVICE 1';

            } elseif ($action_type === 'repeat_service') {
                // BUG FIX 5: Original used == instead of === for action_type comparison
                // inside elseif chains, causing potential loose-type matches.
                // Also added SERVICE 5 guard — original allowed repeat_service on SERVICE 5
                // which would produce an invalid "SERVICE 6". Now throws an error instead.
                $serviceMap = [
                    'SERVICE 1' => 'SERVICE 2',
                    'SERVICE 2' => 'SERVICE 3',
                    'SERVICE 3' => 'SERVICE 4',
                    'SERVICE 4' => 'SERVICE 5',
                ];
                if (!isset($serviceMap[$current_status])) {
                    throw new Exception("Cannot repeat service from '$current_status'. Maximum of 5 services reached.");
                }
                $new_status = $serviceMap[$current_status];

            } elseif ($action_type === 'next_stage' && strpos($current_status, 'SERVICE') !== false) {
                $new_status = 'PREGNANT';

            } elseif ($current_status === 'PREGNANT' && $action_type === 'next_stage') {
                $new_status = 'BIRTHING';

            } elseif ($current_status === 'BIRTHING') {
                $new_status = 'DRY';

            } elseif ($current_status === 'ABORTION') {
                $new_status = 'DRY';
            }

            if (empty($new_status)) {
                throw new Exception("Invalid transition from '$current_status' with action '$action_type'.");
            }

            // -----------------------------------------------------
            // PARITY DETERMINATION & VALIDATION LOGIC
            // -----------------------------------------------------
            $parity = null;

            if ($new_status === 'BIRTHING') {
                if (!empty($_POST['parity'])) {
                    $parity = (int) $_POST['parity'];
                    if ($parity < 1) {
                        throw new Exception("Parity must be a positive integer.");
                    }
                } else {
                    // Auto-calculate: highest existing birthing parity + 1
                    $stmtMaxP = $conn->prepare("
                        SELECT MAX(PARITY) 
                        FROM sow_status_history 
                        WHERE ANIMAL_ID = ? AND STATUS_NAME = 'BIRTHING'
                    ");
                    $stmtMaxP->execute([$animal_id]);
                    $maxP   = $stmtMaxP->fetchColumn();
                    $parity = $maxP ? ((int) $maxP + 1) : 1;
                }

                // Strict duplicate-parity check
                $stmtCheck = $conn->prepare("
                    SELECT COUNT(*) 
                    FROM sow_status_history 
                    WHERE ANIMAL_ID = ? AND PARITY = ? AND STATUS_NAME = 'BIRTHING'
                ");
                $stmtCheck->execute([$animal_id, $parity]);
                if ($stmtCheck->fetchColumn() > 0) {
                    // Return as JSON error so the front-end toast picks it up
                    echo json_encode(['error' => "Parity $parity has already been recorded for this sow. Duplicate parities are not allowed."]);
                    $conn->rollBack();
                    exit;
                }

            } else {
                // For all other states (Dry, Service, Pregnant, Abortion),
                // inherit the most recent parity without incrementing.
                // BUG FIX 6: The original ORDER BY only used STATUS_START_DATE DESC.
                // If two rows share the same timestamp the wrong parity could be returned.
                // Added STATUS_ID DESC as a tiebreaker.
                $stmtCurrP = $conn->prepare("
                    SELECT PARITY 
                    FROM sow_status_history 
                    WHERE ANIMAL_ID = ? 
                    ORDER BY STATUS_START_DATE DESC, STATUS_ID DESC 
                    LIMIT 1
                ");
                $stmtCurrP->execute([$animal_id]);
                $currP  = $stmtCurrP->fetchColumn();
                $parity = $currP ?: null;
            }

            // 1. Close Current Active Status
            $stmt = $conn->prepare("
                UPDATE sow_status_history 
                SET IS_ACTIVE = 0, STATUS_END_DATE = ? 
                WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1
            ");
            $stmt->execute([$action_date, $animal_id]);

            // 2. Close Current Active Service Record (if any)
            $stmtServ = $conn->prepare("
                UPDATE sow_service_history 
                SET IS_ACTIVE = 0, SERVICE_END_DATE = ? 
                WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1
            ");
            $stmtServ->execute([$action_date, $animal_id]);

            // 3. Insert New Status
            $stmtNew = $conn->prepare("
                INSERT INTO sow_status_history 
                    (ANIMAL_ID, STATUS_NAME, STATUS_START_DATE, PARITY, IS_ACTIVE, CREATED_BY) 
                VALUES (?, ?, ?, ?, 1, ?)
            ");
            $stmtNew->execute([$animal_id, $new_status, $action_date, $parity, $user_id]);

            // 4. If new status is a SERVICE, record details in sow_service_history
            if (strpos($new_status, 'SERVICE') !== false) {
                // BUG FIX 7: FILTER_SANITIZE_NUMBER_INT strips non-digit chars but keeps
                // the sign (+/-). For "SERVICE 2" it returns "2" which is fine, but wrapping
                // with (int) cast is safer and more explicit.
                $service_num = (int) filter_var($new_status, FILTER_SANITIZE_NUMBER_INT);

                $stmtServNew = $conn->prepare("
                    INSERT INTO sow_service_history 
                        (ANIMAL_ID, SERVICE_NUMBER, SERVICE_TYPE, BOAR_ID, SERVICE_START_DATE, IS_ACTIVE, CREATED_BY) 
                    VALUES (?, ?, ?, ?, ?, 1, ?)
                ");
                $stmtServNew->execute([
                    $animal_id,
                    $service_num,
                    $service_type,
                    $boar_id,
                    $action_date,
                    $user_id
                ]);
            }

            $audit_action  = "SOW_STATUS_CHANGE";
            $audit_details = "Updated Sow $tag_no status: '$current_status' -> '$new_status' on $action_date. Parity: " . ($parity ?? 'N/A') . ". Action: $action_type";

        } else {
            // BUG FIX 8: Original had no else branch — an unrecognised action_type would
            // silently commit an empty transaction with no audit log and return success.
            throw new Exception("Unknown action_type: " . htmlspecialchars($action_type));
        }

        // --- INSERT AUDIT LOG ---
        if (isset($audit_action)) {
            $audit_sql = "
                INSERT INTO audit_logs 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES (?, ?, ?, 'SOW_STATUS_HISTORY', ?, ?)
            ";
            $audit_stmt = $conn->prepare($audit_sql);
            $audit_stmt->execute([$user_id, $username, $audit_action, $audit_details, $ip_address]);
        }

        $conn->commit();
        echo json_encode(['success' => true]);
        exit;

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
?>