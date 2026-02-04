<?php
// process/eventManager.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

// Helper function for Audit Log
function logAudit($conn, $action, $details) {
    $user_id = $_SESSION['user_id'] ?? 1; // Fallback to System ID 1
    $username = $_SESSION['username'] ?? 'System';
    
    $stmt = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, ?, 'EVENT_SCHEDULES', ?, ?)");
    $stmt->execute([$user_id, $username, $action, $details, $_SERVER['REMOTE_ADDR']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    // 1. Fetch Items based on Event Type
    if ($action === 'get_items') {
        $type = $_GET['type'] ?? '';
        try {
            if ($type === 'Medication') {
                $stmt = $conn->query("SELECT SUPPLY_ID as id, SUPPLY_NAME as name FROM medicines ORDER BY SUPPLY_NAME");
            } elseif ($type === 'Vitamins') {
                $stmt = $conn->query("SELECT SUPPLY_ID as id, SUPPLY_NAME as name FROM vitamins_supplements ORDER BY SUPPLY_NAME");
            } elseif ($type === 'Vaccination') {
                $stmt = $conn->query("SELECT SUPPLY_ID as id, SUPPLY_NAME as name FROM vaccines ORDER BY SUPPLY_NAME");
            } else {
                echo json_encode([]); exit;
            }
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) { echo json_encode([]); }
        exit;
    }

    // 2. Fetch Building Population (Pens + Animals)
    if ($action === 'get_building_population') {
        $bldg_id = $_GET['bldg_id'] ?? 0;
        try {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
            $stmt->execute([$bldg_id]);
            $pens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pens as &$pen) {
                $astmt = $conn->prepare("SELECT ANIMAL_ID, TAG_NO FROM animal_records WHERE PEN_ID = ? AND IS_ACTIVE = 1 ORDER BY TAG_NO");
                $astmt->execute([$pen['PEN_ID']]);
                $pen['animals'] = $astmt->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode($pens);
        } catch (Exception $e) { echo json_encode([]); }
        exit;
    }
    
    // 3. Fetch Buildings for Filter
    if ($action === 'get_buildings_filter') {
        $loc_id = $_GET['loc_id'] ?? 0;
        $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
        $stmt->execute([$loc_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 5. Batch Save Event (UPDATED: Enforce End Date)
    if ($action === 'save_batch_event') {
        try {
            $conn->beginTransaction();

            $animal_ids = isset($_POST['animal_ids']) ? explode(',', $_POST['animal_ids']) : [];
            $event_type = $_POST['event_type'];
            $item_id = !empty($_POST['item_id']) ? $_POST['item_id'] : null;
            $start_date = $_POST['start_date'];
            
            // --- STRICT END DATE CHECK ---
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            
            if (empty($end_date)) {
                 throw new Exception("End Date (Deadline) is required.");
            }

            $interval = !empty($_POST['interval_days']) ? $_POST['interval_days'] : null;

            $sql = "INSERT INTO event_schedules (ANIMAL_ID, EVENT_TYPE, ITEM_ID, START_DATE, END_DATE, INTERVAL_DAYS, STATUS) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
            $stmt = $conn->prepare($sql);

            $count = 0;
            foreach ($animal_ids as $id) {
                if(!empty($id)) {
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

    // 6. Update Status (Single) - Kept for compatibility but you use Bulk Update mostly
    if ($action === 'update_status') { /* ... */ }

    // =================================================================
    // 7. SECURE BULK UPDATE STATUS (The Verification Logic)
    // =================================================================
    if ($action === 'bulk_update_status') {
        try {
            $ids_to_update = $_POST['ids'] ?? '';
            $new_status = $_POST['status'] ?? 'Pending';
            
            if (empty($ids_to_update)) { 
                echo json_encode(['success' => false, 'message' => "No events selected."]); 
                exit; 
            }

            $all_ids = explode(',', $ids_to_update);
            $valid_ids = [];
            $failed_count = 0;

            // --- SECURITY CHECK ---
            // If marking as 'Done', strictly verify that transaction records exist.
            if ($new_status === 'Done') {
                foreach ($all_ids as $id) {
                    // 1. Get Event Details
                    $checkSql = "SELECT EVENT_TYPE, ANIMAL_ID, ITEM_ID, CREATED_AT FROM event_schedules WHERE EVENT_ID = ?";
                    $stmt = $conn->prepare($checkSql);
                    $stmt->execute([$id]);
                    $ev = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$ev) continue;

                    $exists = false;
                    
                    // 2. Check Specific Transaction Table based on Type
                    // Rule: Transaction Date must be >= Event Creation Date
                    if ($ev['EVENT_TYPE'] === 'Medication') {
                        $q = $conn->prepare("SELECT 1 FROM treatment_transactions WHERE ANIMAL_ID = ? AND ITEM_ID = ? AND TRANSACTION_DATE >= ? LIMIT 1");
                        $q->execute([$ev['ANIMAL_ID'], $ev['ITEM_ID'], $ev['CREATED_AT']]);
                        if ($q->fetch()) $exists = true;

                    } elseif ($ev['EVENT_TYPE'] === 'Vaccination') {
                        $q = $conn->prepare("SELECT 1 FROM vaccination_records WHERE ANIMAL_ID = ? AND ITEM_ID = ? AND VACCINATION_DATE >= ? LIMIT 1");
                        $q->execute([$ev['ANIMAL_ID'], $ev['ITEM_ID'], $ev['CREATED_AT']]);
                        if ($q->fetch()) $exists = true;

                    } elseif ($ev['EVENT_TYPE'] === 'Vitamins') {
                        $q = $conn->prepare("SELECT 1 FROM vitamins_supplements_transactions WHERE ANIMAL_ID = ? AND ITEM_ID = ? AND TRANSACTION_DATE >= ? LIMIT 1");
                        $q->execute([$ev['ANIMAL_ID'], $ev['ITEM_ID'], $ev['CREATED_AT']]);
                        if ($q->fetch()) $exists = true;

                    } elseif ($ev['EVENT_TYPE'] === 'Checkup') {
                        $q = $conn->prepare("SELECT 1 FROM check_ups WHERE ANIMAL_ID = ? AND CHECKUP_DATE >= ? LIMIT 1");
                        $q->execute([$ev['ANIMAL_ID'], $ev['CREATED_AT']]);
                        if ($q->fetch()) $exists = true;
                    }

                    // 3. Filter IDs
                    if ($exists) {
                        $valid_ids[] = $id;
                    } else {
                        $failed_count++;
                    }
                }
            } else {
                // If status is 'Cancelled' or 'Pending', no need to check records.
                $valid_ids = $all_ids;
            }

            // --- PROCEED WITH UPDATE FOR VALID IDS ONLY ---
            if (empty($valid_ids)) {
                echo json_encode(['success' => false, 'message' => "Security Alert: None of the selected events have valid transaction records created AFTER the event schedule."]);
                exit;
            }

            $placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
            
            $conn->beginTransaction();
            
            $time_sql = ($new_status === 'Pending') ? "NULL" : "NOW()";
            
            // Allow update only if not already 'Done' (Prevent double done)
            $sql = "UPDATE event_schedules 
                    SET STATUS = ?, COMPLETED_AT = $time_sql 
                    WHERE EVENT_ID IN ($placeholders) 
                    AND STATUS != 'Done'"; 
            
            $params = array_merge([$new_status], $valid_ids);
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            $updated_count = $stmt->rowCount();
            
            logAudit($conn, 'BULK_STATUS_UPDATE', "Updated $updated_count events to status: $new_status. ($failed_count rejected due to missing records)");
            
            $conn->commit();

            if ($failed_count > 0) {
                echo json_encode(['success' => true, 'message' => "Updated $updated_count events. $failed_count events were skipped because no valid transaction record was found."]);
            } else {
                echo json_encode(['success' => true, 'message' => "$updated_count events successfully updated."]);
            }

        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // 8. Bulk Delete (Archive)
    if ($action === 'bulk_delete') {
        try {
            $ids_to_delete = $_POST['ids_to_delete'] ?? ''; 
            $conn->beginTransaction();

            if (!empty($ids_to_delete)) {
                $ids = explode(',', $ids_to_delete);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql = "UPDATE event_schedules SET IS_ACTIVE = 0, STATUS = 'Archived' WHERE EVENT_ID IN ($placeholders) AND STATUS != 'Done'";
                $stmt = $conn->prepare($sql);
                $stmt->execute($ids);
                $count = $stmt->rowCount();
            } else {
                // Filter logic
                $count = 0; 
            }

            if ($count > 0) {
                logAudit($conn, 'BULK_ARCHIVE', "Archived $count events.");
                $conn->commit();
                echo json_encode(['success' => true, 'message' => "Archived $count events."]);
            } else {
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => "No eligible events found."]);
            }
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // =================================================================
    // 10. AUTO-SYNC STATUS (UPDATED: Uses End Date as Target)
    // =================================================================
    if ($action === 'auto_sync_status') {
        try {
            $conn->beginTransaction();
            $updated_count = 0;

            // A. Sync Medications
            // Logic: Transaction Date must be >= Start Date AND >= CREATED_AT
            $sql_med = "UPDATE event_schedules e
                        JOIN treatment_transactions tt 
                            ON e.ANIMAL_ID = tt.ANIMAL_ID 
                            AND e.ITEM_ID = tt.ITEM_ID
                        SET e.STATUS = 'Done', e.COMPLETED_AT = tt.TRANSACTION_DATE
                        WHERE e.EVENT_TYPE = 'Medication' 
                        AND e.STATUS = 'Pending'
                        AND DATE(tt.TRANSACTION_DATE) >= DATE(e.START_DATE)
                        AND tt.TRANSACTION_DATE >= e.CREATED_AT";
            $stmt = $conn->prepare($sql_med);
            $stmt->execute();
            $updated_count += $stmt->rowCount();

            // B. Sync Vitamins
            $sql_vit = "UPDATE event_schedules e
                        JOIN vitamins_supplements_transactions vst 
                            ON e.ANIMAL_ID = vst.ANIMAL_ID 
                            AND e.ITEM_ID = vst.ITEM_ID
                        SET e.STATUS = 'Done', e.COMPLETED_AT = vst.TRANSACTION_DATE
                        WHERE e.EVENT_TYPE = 'Vitamins' 
                        AND e.STATUS = 'Pending'
                        AND DATE(vst.TRANSACTION_DATE) >= DATE(e.START_DATE)
                        AND vst.TRANSACTION_DATE >= e.CREATED_AT";
            $stmt = $conn->prepare($sql_vit);
            $stmt->execute();
            $updated_count += $stmt->rowCount();

            // C. Sync Vaccinations
            $sql_vac = "UPDATE event_schedules e
                        JOIN vaccination_records vr 
                            ON e.ANIMAL_ID = vr.ANIMAL_ID 
                            AND e.ITEM_ID = vr.ITEM_ID
                        SET e.STATUS = 'Done', e.COMPLETED_AT = vr.VACCINATION_DATE
                        WHERE e.EVENT_TYPE = 'Vaccination' 
                        AND e.STATUS = 'Pending'
                        AND DATE(vr.VACCINATION_DATE) >= DATE(e.START_DATE)
                        AND vr.VACCINATION_DATE >= e.CREATED_AT";
            $stmt = $conn->prepare($sql_vac);
            $stmt->execute();
            $updated_count += $stmt->rowCount();

            // D. Sync Checkups
            $sql_chk = "UPDATE event_schedules e
                        JOIN check_ups cu 
                            ON e.ANIMAL_ID = cu.ANIMAL_ID 
                        SET e.STATUS = 'Done', e.COMPLETED_AT = cu.CHECKUP_DATE
                        WHERE e.EVENT_TYPE = 'Checkup' 
                        AND e.STATUS = 'Pending'
                        AND DATE(cu.CHECKUP_DATE) >= DATE(e.START_DATE)
                        AND cu.CHECKUP_DATE >= e.CREATED_AT";
            $stmt = $conn->prepare($sql_chk);
            $stmt->execute();
            $updated_count += $stmt->rowCount();

            $conn->commit();
            echo json_encode(['success' => true, 'updated' => $updated_count]);

        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
?>