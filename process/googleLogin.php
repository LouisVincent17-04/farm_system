<?php
// 1. SUPPRESS HTML ERRORS & START SESSION
error_reporting(E_ALL);
ini_set('display_errors', 0); 
header('Content-Type: application/json');

session_start();

try {
    // 2. CHECK CONFIG
    // Make sure this points to your PDO connection file
    if (!file_exists('../config/Connection.php')) {
        throw new Exception("Configuration file not found.");
    }
    
    // 3. INCLUDE CONNECTION
    // Using output buffering to catch any accidental whitespace from the include
    ob_start();
    require '../config/Connection.php'; 
    ob_end_clean();

    // Ensure $conn exists and is a PDO object
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new Exception("Database connection failed.");
    }

    // 4. GET INPUT
    $input = json_decode(file_get_contents('php://input'), true);
    $accessToken = $input['access_token'] ?? null;

    if (!$accessToken) {
        throw new Exception("No access token provided.");
    }

    // 5. VERIFY WITH GOOGLE
    $googleApiUrl = "https://www.googleapis.com/oauth2/v3/userinfo?access_token=" . $accessToken;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $googleApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Disable SSL verification ONLY for local testing (remove for production)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception("Curl Error: " . curl_error($ch));
    }
    curl_close($ch);

    $googleUser = json_decode($response, true);

    if (isset($googleUser['error']) || !isset($googleUser['email'])) {
        throw new Exception("Invalid or expired Google Token.");
    }

    // 6. PROCESS USER
    $email = $googleUser['email'];
    $name  = $googleUser['name'];
    $google_sub_id = (string)$googleUser['sub']; 
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    // Check if user exists
    $check_sql = "SELECT USER_ID, FULL_NAME, EMAIL, USER_TYPE, CONTACT_INFO, PASSWORD 
                  FROM USERS WHERE EMAIL = :email";
    
    $stmt = $conn->prepare($check_sql);
    $stmt->execute([':email' => $email]);
    
    // Fetch user as associative array
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // --- A. LOGIN EXISTING USER ---
        
        // Link Google ID if it is currently NULL
        $update_sql = "UPDATE USERS SET GOOGLE_ID = :gid 
                       WHERE USER_ID = :uid AND GOOGLE_ID IS NULL";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([
            ':gid' => $google_sub_id,
            ':uid' => $user['USER_ID']
        ]);

        // Set Session (Mapping keys to match your DB column names)
        $_SESSION['user'] = [
            'USER_ID'      => $user['USER_ID'],
            'FULL_NAME'    => $user['FULL_NAME'],
            'EMAIL'        => $user['EMAIL'],
            'USER_TYPE'    => $user['USER_TYPE'],
            'CONTACT_INFO' => $user['CONTACT_INFO']
        ];

        // --- AUDIT LOG: LOGIN ---
        try {
            $logSql = "INSERT INTO AUDIT_LOGS 
                       (USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                       VALUES 
                       (:usr, 'LOGIN_GOOGLE', 'USERS', 'User logged in via Google', :ip)";
            
            $logStmt = $conn->prepare($logSql);
            $logStmt->execute([
                ':usr' => $user['FULL_NAME'],
                ':ip'  => $ip_address
            ]);
        } catch (Exception $e) { 
            // Silently fail logging so user can still login
        }
        // ------------------------

        echo json_encode(['success' => true, 'message' => 'Login successful.']);

    } else {
        // --- B. REGISTER NEW USER ---
        
        $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $default_role_id = 1; // 1 = New User (Ensure this ID exists in USER_TYPES table)

        $insert_sql = "INSERT INTO USERS 
                       (FULL_NAME, EMAIL, PASSWORD, GOOGLE_ID, USER_TYPE, CONTACT_INFO) 
                       VALUES (:fullname, :email, :password, :google_id, :role_id, NULL)";
        
        $stmt = $conn->prepare($insert_sql);
        
        $params = [
            ':fullname'  => $name,
            ':email'     => $email,
            ':password'  => $random_password,
            ':google_id' => $google_sub_id,
            ':role_id'   => $default_role_id
        ];
        
        try {
            if ($stmt->execute($params)) {
                
                // Get the new ID (In MySQL, we use lastInsertId)
                $new_user_id = $conn->lastInsertId();
                
                $_SESSION['user'] = [
                    'USER_ID'      => $new_user_id,
                    'FULL_NAME'    => $name,
                    'EMAIL'        => $email,
                    'USER_TYPE'    => $default_role_id,
                    'CONTACT_INFO' => NULL
                ];

                // --- AUDIT LOG: REGISTER ---
                try {
                    $logSql = "INSERT INTO AUDIT_LOGS 
                               (USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                               VALUES 
                               (:usr, 'REGISTER_GOOGLE', 'USERS', 'New account created via Google', :ip)";
                    
                    $logStmt = $conn->prepare($logSql);
                    $logStmt->execute([
                        ':usr' => $name,
                        ':ip'  => $ip_address
                    ]);
                } catch (Exception $e) { /* Ignore log error */ }
                // ---------------------------

                echo json_encode(['success' => true, 'message' => 'Account created and logged in.']);
            }
        } catch (PDOException $e) {
            // Check for foreign key constraint violation (Error 23000 is generic integrity violation)
            if ($e->getCode() == 23000) { 
                 // Often means USER_TYPE ID 1 doesn't exist
                throw new Exception("Role ID $default_role_id does not exist in USER_TYPES table.");
            }
            throw new Exception("Database Insert Failed: " . $e->getMessage());
        }
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Server Error: ' . $e->getMessage()
    ]);
}
?>