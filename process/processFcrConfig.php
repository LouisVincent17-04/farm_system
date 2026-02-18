<?php
// process/processFcrConfig.php
session_start();
include '../config/Connection.php';

$action = $_REQUEST['action'] ?? '';
$user_id = $_SESSION['user']['USER_ID'] ?? 0;

// --- 1. SAVE CONFIGURATION ---
if ($action === 'save_config') {
    $type = $_POST['type'];
    $fcr = $_POST['fcr'];
    
    try {
        $sql = "";
        $params = [];

        if ($type === 'Individual') {
            $animId = $_POST['animal_id'];
            if(!$animId) throw new Exception("No Animal Selected");

            $check = $conn->prepare("SELECT CONFIG_ID FROM fcr_configurations WHERE CONFIG_TYPE='Individual' AND ANIMAL_ID=?");
            $check->execute([$animId]);
            
            if($check->rowCount() > 0) {
                $sql = "UPDATE fcr_configurations SET TARGET_FCR=? WHERE CONFIG_TYPE='Individual' AND ANIMAL_ID=?";
                $params = [$fcr, $animId];
            } else {
                $sql = "INSERT INTO fcr_configurations (CONFIG_TYPE, ANIMAL_ID, TARGET_FCR) VALUES ('Individual', ?, ?)";
                $params = [$animId, $fcr];
            }
        }
        elseif ($type === 'Location') {
            $loc = $_POST['location_id'];
            $check = $conn->prepare("SELECT CONFIG_ID FROM fcr_configurations WHERE CONFIG_TYPE='Location' AND LOCATION_ID=?");
            $check->execute([$loc]);
            if($check->rowCount() > 0) {
                $sql = "UPDATE fcr_configurations SET TARGET_FCR=? WHERE CONFIG_TYPE='Location' AND LOCATION_ID=?";
                $params = [$fcr, $loc];
            } else {
                $sql = "INSERT INTO fcr_configurations (CONFIG_TYPE, LOCATION_ID, TARGET_FCR) VALUES ('Location', ?, ?)";
                $params = [$loc, $fcr];
            }
        } 
        elseif ($type === 'Building') {
            $bldg = $_POST['building_id'];
            $check = $conn->prepare("SELECT CONFIG_ID FROM fcr_configurations WHERE CONFIG_TYPE='Building' AND BUILDING_ID=?");
            $check->execute([$bldg]);
            if($check->rowCount() > 0) {
                $sql = "UPDATE fcr_configurations SET TARGET_FCR=? WHERE CONFIG_TYPE='Building' AND BUILDING_ID=?";
                $params = [$fcr, $bldg];
            } else {
                $sql = "INSERT INTO fcr_configurations (CONFIG_TYPE, BUILDING_ID, TARGET_FCR) VALUES ('Building', ?, ?)";
                $params = [$bldg, $fcr];
            }
        } 
        elseif ($type === 'Pen') {
            $pen = $_POST['pen_id'];
            $check = $conn->prepare("SELECT CONFIG_ID FROM fcr_configurations WHERE CONFIG_TYPE='Pen' AND PEN_ID=?");
            $check->execute([$pen]);
            if($check->rowCount() > 0) {
                $sql = "UPDATE fcr_configurations SET TARGET_FCR=? WHERE CONFIG_TYPE='Pen' AND PEN_ID=?";
                $params = [$fcr, $pen];
            } else {
                $sql = "INSERT INTO fcr_configurations (CONFIG_TYPE, PEN_ID, TARGET_FCR) VALUES ('Pen', ?, ?)";
                $params = [$pen, $fcr];
            }
        } 
        elseif ($type === 'Age') {
            $min = $_POST['min_age'];
            $max = $_POST['max_age'];
            $sql = "INSERT INTO fcr_configurations (CONFIG_TYPE, MIN_AGE_DAYS, MAX_AGE_DAYS, TARGET_FCR) VALUES ('Age', ?, ?, ?)";
            $params = [$min, $max, $fcr];
        }

        if($sql) {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success'=>true, 'message'=>"$type Rule Saved"]);
        }
    } catch (Exception $e) {
        echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
    }
    exit;
}

// --- FETCH ANIMALS FOR DROPDOWN ---
if ($action === 'get_pen_animals') {
    $pen_id = $_GET['pen_id'];
    try {
        $stmt = $conn->prepare("SELECT ANIMAL_ID, TAG_NO FROM animal_records WHERE PEN_ID = ? AND IS_ACTIVE = 1 ORDER BY TAG_NO ASC");
        $stmt->execute([$pen_id]);
        $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($animals);
    } catch (Exception $e) { echo json_encode([]); }
    exit;
}

// --- 2. LIST CONFIGS ---
if ($action === 'list') {
    $sql = "SELECT c.*, l.LOCATION_NAME, b.BUILDING_NAME, p.PEN_NAME, a.TAG_NO
            FROM fcr_configurations c
            LEFT JOIN locations l ON c.LOCATION_ID = l.LOCATION_ID
            LEFT JOIN buildings b ON c.BUILDING_ID = b.BUILDING_ID
            LEFT JOIN pens p ON c.PEN_ID = p.PEN_ID
            LEFT JOIN animal_records a ON c.ANIMAL_ID = a.ANIMAL_ID
            ORDER BY 
                CASE c.CONFIG_TYPE 
                    WHEN 'Individual' THEN 1
                    WHEN 'Pen' THEN 2 
                    WHEN 'Building' THEN 3 
                    WHEN 'Location' THEN 4 
                    ELSE 5 
                END ASC";
    
    $stmt = $conn->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo '<table class="data-table"><thead><tr><th>Priority Type</th><th>Target</th><th>FCR %</th><th>Action</th></tr></thead><tbody>';
    foreach($rows as $r) {
        $desc = "";
        if ($r['CONFIG_TYPE'] == 'Individual') $desc = "Tag: " . $r['TAG_NO'];
        elseif ($r['CONFIG_TYPE'] == 'Location') $desc = $r['LOCATION_NAME'];
        elseif ($r['CONFIG_TYPE'] == 'Building') $desc = $r['BUILDING_NAME'];
        elseif ($r['CONFIG_TYPE'] == 'Pen') $desc = $r['PEN_NAME'];
        else $desc = $r['MIN_AGE_DAYS'] . " - " . $r['MAX_AGE_DAYS'] . " Days";

        echo "<tr>
                <td><b>{$r['CONFIG_TYPE']}</b></td>
                <td>$desc</td>
                <td style='color:#f59e0b'>{$r['TARGET_FCR']}</td>
                <td><button onclick='deleteConfig({$r['CONFIG_ID']})' style='color:#ef4444;background:none;border:none;cursor:pointer'>🗑️</button></td>
              </tr>";
    }
    echo '</tbody></table>';
    exit;
}

// --- 3. VIEW ANIMALS (PRIORITY LOGIC ENGINE) ---
if ($action === 'view_animals') {
    header('Content-Type: application/json');
    $filterLoc = $_GET['loc'] ?? '';
    $filterBldg = $_GET['bldg'] ?? '';
    $filterPen = $_GET['pen'] ?? '';

    // Enforce Pen selection for this specific view to prevent massive loads
    if(empty($filterPen)) {
        echo json_encode([]); 
        exit; 
    }

    $sql = "SELECT a.ANIMAL_ID, a.TAG_NO, a.WEIGHT_AT_BIRTH as BIRTH_WEIGHT,
                   DATEDIFF(NOW(), a.BIRTH_DATE) as AGE_DAYS,
                   COALESCE((SELECT SUM(QUANTITY_KG) FROM feed_transactions WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as TOTAL_FEED,
                   p.PEN_ID, p.PEN_NAME,
                   b.BUILDING_ID, b.BUILDING_NAME,
                   l.LOCATION_ID, l.LOCATION_NAME,
                   (SELECT ACTUAL_WEIGHT FROM animal_fcr_logs WHERE ANIMAL_ID = a.ANIMAL_ID ORDER BY LOG_DATE DESC LIMIT 1) as LAST_WEIGHT
            FROM animal_records a
            LEFT JOIN pens p ON a.PEN_ID = p.PEN_ID
            LEFT JOIN buildings b ON p.BUILDING_ID = b.BUILDING_ID
            LEFT JOIN locations l ON b.LOCATION_ID = l.LOCATION_ID
            WHERE a.IS_ACTIVE = 1";
            
    if($filterLoc) $sql .= " AND l.LOCATION_ID = $filterLoc";
    if($filterBldg) $sql .= " AND b.BUILDING_ID = $filterBldg";
    if($filterPen) $sql .= " AND p.PEN_ID = $filterPen";
    
    $stmt = $conn->query($sql);
    $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Rules
    $indRules = $conn->query("SELECT ANIMAL_ID, TARGET_FCR FROM fcr_configurations WHERE CONFIG_TYPE='Individual'")->fetchAll(PDO::FETCH_KEY_PAIR);
    $penRules = $conn->query("SELECT PEN_ID, TARGET_FCR FROM fcr_configurations WHERE CONFIG_TYPE='Pen'")->fetchAll(PDO::FETCH_KEY_PAIR);
    $bldgRules = $conn->query("SELECT BUILDING_ID, TARGET_FCR FROM fcr_configurations WHERE CONFIG_TYPE='Building'")->fetchAll(PDO::FETCH_KEY_PAIR);
    $locRules = $conn->query("SELECT LOCATION_ID, TARGET_FCR FROM fcr_configurations WHERE CONFIG_TYPE='Location'")->fetchAll(PDO::FETCH_KEY_PAIR);
    $ageRules = $conn->query("SELECT MIN_AGE_DAYS, MAX_AGE_DAYS, TARGET_FCR FROM fcr_configurations WHERE CONFIG_TYPE='Age'")->fetchAll(PDO::FETCH_ASSOC);

    $results = [];

    foreach($animals as $anim) {
        $fcr = 0; $source = 'None';

        if (isset($indRules[$anim['ANIMAL_ID']])) { $fcr = $indRules[$anim['ANIMAL_ID']]; $source = 'Individual'; }
        elseif (isset($penRules[$anim['PEN_ID']])) { $fcr = $penRules[$anim['PEN_ID']]; $source = 'Pen'; }
        elseif (isset($bldgRules[$anim['BUILDING_ID']])) { $fcr = $bldgRules[$anim['BUILDING_ID']]; $source = 'Building'; }
        elseif (isset($locRules[$anim['LOCATION_ID']])) { $fcr = $locRules[$anim['LOCATION_ID']]; $source = 'Location'; }
        else {
            foreach($ageRules as $ar) {
                if ($anim['AGE_DAYS'] >= $ar['MIN_AGE_DAYS'] && $anim['AGE_DAYS'] <= $ar['MAX_AGE_DAYS']) {
                    $fcr = $ar['TARGET_FCR']; $source = 'Age'; break;
                }
            }
        }

        $feed = floatval($anim['TOTAL_FEED']);
        $birth = floatval($anim['BIRTH_WEIGHT']);
        $gain = ($fcr > 0) ? ($feed * $fcr) : 0; 
        $est = $birth + $gain;

        $results[] = [
            'id' => $anim['ANIMAL_ID'],
            'pen_id' => $anim['PEN_ID'],
            'tag' => $anim['TAG_NO'],
            'path' => "{$anim['LOCATION_NAME']} > {$anim['BUILDING_NAME']} > {$anim['PEN_NAME']}",
            'age' => $anim['AGE_DAYS'],
            'source' => $source,
            'fcr' => $fcr ?: 0,
            'feed' => number_format($feed, 2),
            'birth_weight' => number_format($birth, 2),
            'gain' => number_format($gain, 2),
            'est_weight' => number_format($est, 2),
            'actual_weight' => $anim['LAST_WEIGHT']
        ];
    }
    echo json_encode($results);
    exit;
}

// --- 4. SAVE SINGLE LOG ---
if ($action === 'save_single_log') {
    try {
        $aStmt = $conn->prepare("SELECT WEIGHT_AT_BIRTH, COALESCE((SELECT SUM(QUANTITY_KG) FROM feed_transactions WHERE ANIMAL_ID = animal_records.ANIMAL_ID), 0) as TOTAL_FEED FROM animal_records WHERE ANIMAL_ID = ?");
        $aStmt->execute([$_POST['animal_id']]);
        $aData = $aStmt->fetch(PDO::FETCH_ASSOC);
        
        $birth = $aData['WEIGHT_AT_BIRTH'];
        $feed = $aData['TOTAL_FEED'];
        $fcr = $_POST['fcr_used'];
        $act = $_POST['actual_weight'];
        $date = $_POST['weigh_date'];
        $gain = $feed * $fcr;
        $est = $birth + $gain;
        $var = $act - $est;

        $stmt = $conn->prepare("INSERT INTO animal_fcr_logs (ANIMAL_ID, PEN_ID, LOG_DATE, BIRTH_WEIGHT, FEED_SHARE_KG, FCR_USED, TOTAL_GAIN_EST, ESTIMATED_WEIGHT, ACTUAL_WEIGHT, VARIANCE, CREATED_BY) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['animal_id'], $_POST['pen_id'], $date, $birth, $feed, $fcr, $gain, $est, $act, $var, $user_id]);
        
        $conn->prepare("UPDATE animal_records SET CURRENT_ACTUAL_WEIGHT = ?, CURRENT_ESTIMATED_WEIGHT = ? WHERE ANIMAL_ID = ?")->execute([$act, $est, $_POST['animal_id']]);

        echo json_encode(['success'=>true, 'message'=>'Record Saved']);
    } catch (Exception $e) {
        echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
    }
    exit;
}

// --- 5. DELETE CONFIG ---
if ($action === 'delete_config') {
    $config_id = $_POST['config_id'];
    try {
        $conn->prepare("DELETE FROM fcr_configurations WHERE CONFIG_ID = ?")->execute([$config_id]);
        echo json_encode(['success'=>true, 'message'=>'Configuration Deleted']);
    } catch (Exception $e) { echo json_encode(['success'=>false, 'message'=>$e->getMessage()]); }
    exit;
}
?>