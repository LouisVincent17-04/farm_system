<?php
// process/eventManager.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

// ─────────────────────────────────────────────
// HELPER: Audit Log
// ─────────────────────────────────────────────
function logAudit($conn, $action, $details) {
    $user_id   = $_SESSION['user']['USER_ID']   ?? 1;
    $username  = $_SESSION['user']['FULL_NAME'] ?? 'System';
    $ip_address = $_SERVER['REMOTE_ADDR']        ?? 'Unknown';

    $stmt = $conn->prepare("
        INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS)
        VALUES (?, ?, ?, 'EVENT_SCHEDULES', ?, ?)
    ");
    $stmt->execute([$user_id, $username, $action, $details, $ip_address]);
}

// ═════════════════════════════════════════════
//  GET  REQUESTS
// ═════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    // ── 1. Items by Type + Location ───────────────────────────────────────
    if ($action === 'get_items_by_loc') {
        $type   = $_GET['type']   ?? '';
        $loc_id = $_GET['loc_id'] ?? 0;

        try {
            if ($type === 'Medication') {
                $stmt = $conn->prepare("SELECT SUPPLY_ID as id, SUPPLY_NAME as name, TOTAL_STOCK as qty FROM medicines WHERE LOCATION_ID = ? ORDER BY SUPPLY_NAME");
            } elseif ($type === 'Vitamins') {
                $stmt = $conn->prepare("SELECT SUPPLY_ID as id, SUPPLY_NAME as name, TOTAL_STOCK as qty FROM vitamins_supplements WHERE LOCATION_ID = ? ORDER BY SUPPLY_NAME");
            } elseif ($type === 'Vaccination') {
                $stmt = $conn->prepare("SELECT SUPPLY_ID as id, SUPPLY_NAME as name, TOTAL_STOCK as qty FROM vaccines WHERE LOCATION_ID = ? ORDER BY SUPPLY_NAME");
            } else {
                echo json_encode([]); exit;
            }
            $stmt->execute([$loc_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) { echo json_encode([]); }
        exit;
    }

    // ── 2. Buildings for cascading dropdown ───────────────────────────────
    if ($action === 'get_buildings_filter') {
        $loc_id = $_GET['loc_id'] ?? 0;
        $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
        $stmt->execute([$loc_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // ── 3. Full building population (pens + animals) ──────────────────────
    if ($action === 'get_building_population') {
        $bldg_id = $_GET['bldg_id'] ?? 0;
        try {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
            $stmt->execute([$bldg_id]);
            $pens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pens as &$pen) {
                $astmt = $conn->prepare("SELECT ANIMAL_ID, TAG_NO FROM animal_records WHERE PEN_ID = ? AND IS_ACTIVE = 1 AND CURRENT_STATUS != 'Sold' ORDER BY TAG_NO");
                $astmt->execute([$pen['PEN_ID']]);
                $pen['animals'] = $astmt->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode($pens);
        } catch (Exception $e) { echo json_encode([]); }
        exit;
    }

    // ── 4. Event details for auto-select in transaction modules ───────────
    //    Returns full location context so the module can lock dropdowns
    //    and auto-populate the correct supply + animals.
    if ($action === 'get_events_details') {
        $ids = $_GET['ids'] ?? '';
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No IDs provided']);
            exit;
        }

        try {
            $idArray      = array_filter(array_map('intval', explode(',', $ids)));
            if (empty($idArray)) {
                echo json_encode(['success' => false, 'message' => 'Invalid IDs']);
                exit;
            }
            $placeholders = implode(',', array_fill(0, count($idArray), '?'));

            $sql = "
                SELECT
                    e.EVENT_ID,
                    e.EVENT_TYPE,
                    e.ANIMAL_ID,
                    e.ITEM_ID,
                    e.START_DATE,
                    e.END_DATE,
                    a.TAG_NO,
                    p.PEN_ID,
                    p.PEN_NAME,
                    b.BUILDING_ID,
                    b.BUILDING_NAME,
                    b.LOCATION_ID,
                    l.LOCATION_NAME,
                    CASE 
                        WHEN e.EVENT_TYPE = 'Medication'  THEN m.SUPPLY_NAME
                        WHEN e.EVENT_TYPE = 'Vitamins'    THEN vs.SUPPLY_NAME
                        WHEN e.EVENT_TYPE = 'Vaccination' THEN v.SUPPLY_NAME
                        ELSE NULL
                    END AS ITEM_NAME
                FROM event_schedules e
                JOIN animal_records       a  ON e.ANIMAL_ID  = a.ANIMAL_ID
                JOIN pens                 p  ON a.PEN_ID      = p.PEN_ID
                JOIN buildings            b  ON p.BUILDING_ID = b.BUILDING_ID
                JOIN locations            l  ON b.LOCATION_ID = l.LOCATION_ID
                LEFT JOIN medicines          m  ON e.ITEM_ID = m.SUPPLY_ID  AND e.EVENT_TYPE = 'Medication'
                LEFT JOIN vitamins_supplements vs ON e.ITEM_ID = vs.SUPPLY_ID AND e.EVENT_TYPE = 'Vitamins'
                LEFT JOIN vaccines           v  ON e.ITEM_ID = v.SUPPLY_ID  AND e.EVENT_TYPE = 'Vaccination'
                WHERE e.EVENT_ID IN ($placeholders)
                ORDER BY a.TAG_NO ASC
            ";

            $stmt = $conn->prepare($sql);
            $stmt->execute($idArray);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'events' => $events]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// ═════════════════════════════════════════════
//  POST  REQUESTS
// ═════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 5. Batch-save new event schedule ──────────────────────────────────
    if ($action === 'save_batch_event') {
        try {
            $conn->beginTransaction();

            $animal_ids = isset($_POST['animal_ids']) ? explode(',', $_POST['animal_ids']) : [];
            $event_type = $_POST['event_type'];
            $item_id    = !empty($_POST['item_id'])       ? $_POST['item_id']       : null;
            $start_date = $_POST['start_date'];
            $end_date   = !empty($_POST['end_date'])       ? $_POST['end_date']       : null;

            if (empty($end_date)) {
                throw new Exception("End Date (Deadline) is required.");
            }

            $interval = !empty($_POST['interval_days']) ? $_POST['interval_days'] : null;

            $sql  = "INSERT INTO event_schedules (ANIMAL_ID, EVENT_TYPE, ITEM_ID, START_DATE, END_DATE, INTERVAL_DAYS, STATUS) VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
            $stmt = $conn->prepare($sql);

            $count = 0;
            foreach ($animal_ids as $id) {
                if (!empty(trim($id))) {
                    $stmt->execute([$id, $event_type, $item_id, $start_date, $end_date, $interval]);
                    $count++;
                }
            }

            logAudit($conn, 'BATCH_ADD', "Scheduled $count events of type $event_type. Due: $end_date");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => "$count events scheduled."]);
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── 6. Secure bulk status update ──────────────────────────────────────
    if ($action === 'bulk_update_status') {
        try {
            $ids_to_update = $_POST['ids']    ?? '';
            $new_status    = $_POST['status'] ?? 'Pending';

            if (empty($ids_to_update)) {
                echo json_encode(['success' => false, 'message' => "No events selected."]);
                exit;
            }

            $all_ids     = explode(',', $ids_to_update);
            $valid_ids   = [];
            $failed_count = 0;

            if ($new_status === 'Done') {
                foreach ($all_ids as $id) {
                    $stmt = $conn->prepare("SELECT EVENT_TYPE, ANIMAL_ID, ITEM_ID, CREATED_AT, START_DATE FROM event_schedules WHERE EVENT_ID = ?");
                    $stmt->execute([$id]);
                    $ev = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$ev) continue;

                    $exists = false;

                    if ($ev['EVENT_TYPE'] === 'Medication') {
                        $q = $conn->prepare("SELECT 1 FROM treatment_transactions WHERE ANIMAL_ID = ? AND ITEM_ID = ? AND TRANSACTION_DATE >= ? AND TRANSACTION_DATE >= DATE(?) LIMIT 1");
                        $q->execute([$ev['ANIMAL_ID'], $ev['ITEM_ID'], $ev['START_DATE'], $ev['CREATED_AT']]);
                        if ($q->fetch()) $exists = true;

                    } elseif ($ev['EVENT_TYPE'] === 'Vaccination') {
                        $q = $conn->prepare("SELECT 1 FROM vaccination_records WHERE ANIMAL_ID = ? AND ITEM_ID = ? AND VACCINATION_DATE >= ? AND VACCINATION_DATE >= DATE(?) LIMIT 1");
                        $q->execute([$ev['ANIMAL_ID'], $ev['ITEM_ID'], $ev['START_DATE'], $ev['CREATED_AT']]);
                        if ($q->fetch()) $exists = true;

                    } elseif ($ev['EVENT_TYPE'] === 'Vitamins') {
                        $q = $conn->prepare("SELECT 1 FROM vitamins_supplements_transactions WHERE ANIMAL_ID = ? AND ITEM_ID = ? AND TRANSACTION_DATE >= ? AND TRANSACTION_DATE >= DATE(?) LIMIT 1");
                        $q->execute([$ev['ANIMAL_ID'], $ev['ITEM_ID'], $ev['START_DATE'], $ev['CREATED_AT']]);
                        if ($q->fetch()) $exists = true;

                    } elseif ($ev['EVENT_TYPE'] === 'Checkup') {
                        $q = $conn->prepare("SELECT 1 FROM check_ups WHERE ANIMAL_ID = ? AND CHECKUP_DATE >= ? AND CHECKUP_DATE >= DATE(?) LIMIT 1");
                        $q->execute([$ev['ANIMAL_ID'], $ev['START_DATE'], $ev['CREATED_AT']]);
                        if ($q->fetch()) $exists = true;
                    }

                    if ($exists) { $valid_ids[] = $id; } else { $failed_count++; }
                }
            } else {
                $valid_ids = $all_ids;
            }

            if (empty($valid_ids)) {
                echo json_encode(['success' => false, 'message' => "Security Alert: No valid transaction records found created AFTER the schedule date."]);
                exit;
            }

            $placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
            $conn->beginTransaction();
            $time_sql = ($new_status === 'Pending') ? "NULL" : "NOW()";

            $sql    = "UPDATE event_schedules SET STATUS = ?, COMPLETED_AT = $time_sql WHERE EVENT_ID IN ($placeholders) AND STATUS != 'Done'";
            $params = array_merge([$new_status], $valid_ids);
            $stmt   = $conn->prepare($sql);
            $stmt->execute($params);
            $updated_count = $stmt->rowCount();

            logAudit($conn, 'BULK_STATUS_UPDATE', "Updated $updated_count events to $new_status. ($failed_count rejected)");
            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => "Updated $updated_count events." . ($failed_count > 0 ? " $failed_count events skipped (no records found)." : "")
            ]);

        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── 7. Bulk archive (soft-delete) ─────────────────────────────────────
    if ($action === 'bulk_delete') {
        try {
            $ids_to_delete = $_POST['ids_to_delete'] ?? '';
            $conn->beginTransaction();
            $count = 0;

            if (!empty($ids_to_delete)) {
                $ids          = explode(',', $ids_to_delete);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql  = "UPDATE event_schedules SET IS_ACTIVE = 0, STATUS = 'Archived' WHERE EVENT_ID IN ($placeholders) AND STATUS != 'Done'";
                $stmt = $conn->prepare($sql);
                $stmt->execute($ids);
                $count = $stmt->rowCount();
            }

            if ($count > 0) {
                logAudit($conn, 'BULK_ARCHIVE', "Archived $count events.");
                $conn->commit();
                echo json_encode(['success' => true, 'message' => "Archived $count events."]);
            } else {
                if ($conn->inTransaction()) $conn->rollBack();
                echo json_encode(['success' => false, 'message' => "No eligible events found."]);
            }
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── 8. Auto-sync pending → done via transaction join ──────────────────
    if ($action === 'auto_sync_status') {
        try {
            $conn->beginTransaction();
            $updated_count = 0;

            $stmt = $conn->prepare("UPDATE event_schedules e JOIN treatment_transactions tt ON e.ANIMAL_ID = tt.ANIMAL_ID AND e.ITEM_ID = tt.ITEM_ID SET e.STATUS = 'Done', e.COMPLETED_AT = tt.TRANSACTION_DATE WHERE e.EVENT_TYPE = 'Medication' AND e.STATUS = 'Pending' AND tt.TRANSACTION_DATE >= e.START_DATE AND tt.TRANSACTION_DATE >= e.CREATED_AT");
            $stmt->execute(); $updated_count += $stmt->rowCount();

            $stmt = $conn->prepare("UPDATE event_schedules e JOIN vitamins_supplements_transactions vst ON e.ANIMAL_ID = vst.ANIMAL_ID AND e.ITEM_ID = vst.ITEM_ID SET e.STATUS = 'Done', e.COMPLETED_AT = vst.TRANSACTION_DATE WHERE e.EVENT_TYPE = 'Vitamins' AND e.STATUS = 'Pending' AND vst.TRANSACTION_DATE >= e.START_DATE AND vst.TRANSACTION_DATE >= e.CREATED_AT");
            $stmt->execute(); $updated_count += $stmt->rowCount();

            $stmt = $conn->prepare("UPDATE event_schedules e JOIN vaccination_records vr ON e.ANIMAL_ID = vr.ANIMAL_ID AND e.ITEM_ID = vr.ITEM_ID SET e.STATUS = 'Done', e.COMPLETED_AT = vr.VACCINATION_DATE WHERE e.EVENT_TYPE = 'Vaccination' AND e.STATUS = 'Pending' AND vr.VACCINATION_DATE >= e.START_DATE AND vr.VACCINATION_DATE >= e.CREATED_AT");
            $stmt->execute(); $updated_count += $stmt->rowCount();

            $stmt = $conn->prepare("UPDATE event_schedules e JOIN check_ups cu ON e.ANIMAL_ID = cu.ANIMAL_ID SET e.STATUS = 'Done', e.COMPLETED_AT = cu.CHECKUP_DATE WHERE e.EVENT_TYPE = 'Checkup' AND e.STATUS = 'Pending' AND cu.CHECKUP_DATE >= e.START_DATE AND cu.CHECKUP_DATE >= e.CREATED_AT");
            $stmt->execute(); $updated_count += $stmt->rowCount();

            $conn->commit();
            echo json_encode(['success' => true, 'updated' => $updated_count]);
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── 9. ★ NEW: Mark specific event IDs as Done (called by batch processors) ──
    if ($action === 'mark_events_done') {
        try {
            $event_ids = $_POST['event_ids'] ?? '';
            if (empty($event_ids)) {
                echo json_encode(['success' => false, 'message' => 'No event IDs provided.']);
                exit;
            }

            $ids          = array_filter(array_map('intval', explode(',', $event_ids)));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $conn->beginTransaction();
            $params = array_merge(['Done'], $ids);
            $stmt   = $conn->prepare("UPDATE event_schedules SET STATUS = 'Done', COMPLETED_AT = NOW() WHERE EVENT_ID IN ($placeholders) AND STATUS = 'Pending'");
            $stmt->execute($params);
            $updated = $stmt->rowCount();

            logAudit($conn, 'BATCH_CLOSE', "Marked $updated events as Done via batch transaction. IDs: $event_ids");
            $conn->commit();

            echo json_encode(['success' => true, 'message' => "$updated event(s) marked as Done.", 'updated' => $updated]);
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
?>