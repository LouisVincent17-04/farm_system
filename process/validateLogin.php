<?php
// ../process/loginProcess.php

include '../config/Connection.php';
// include '../config/Queries.php'; // Optional, not used in this specific logic

function validateLogin($conn, $email, $password) {
    // Trim inputs
    $email = trim($email);
    $password = trim($password);

    // Check if empty
    if (empty($email) || empty($password)) {
        return [
            'success' => false,
            'error' => 'Email and password are required.'
        ];
    }

    try {
        if (!isset($conn)) {
             throw new Exception("Database connection failed.");
        }

        // Prepare SQL to fetch user by email
        $sql = "SELECT USER_ID, FULL_NAME, EMAIL, USER_TYPE, PASSWORD FROM USERS WHERE EMAIL = :email";

        $stmt = $conn->prepare($sql);
        
        // Execute with binding
        if (!$stmt->execute([':email' => $email])) {
            $errorInfo = $stmt->errorInfo();
            return [
                'success' => false,
                'error' => 'Database execute error: ' . $errorInfo[2]
            ];
        }

        // Fetch the user
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return [
                'success' => false,
                'error' => 'No account found with this email.'
            ];
        }

        // Verify password
        if (!password_verify($password, $user['PASSWORD'])) {
            return [
                'success' => false,
                'error' => 'Incorrect password.'
            ];
        }

        // Login successful
        return [
            'success' => true,
            'user' => $user
        ];

    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'System error: ' . $e->getMessage()
        ];
    }
}

// ======= Handle login POST request =======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = validateLogin($conn, $email, $password);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; // Get User IP

    if ($result['success']) {
        session_start();
        $_SESSION['user'] = $result['user'];

        // --- HARDCODED AUDIT LOG (SUCCESS) ---
        try {
            if (isset($conn)) {
                $logSql = "INSERT INTO AUDIT_LOGS 
                           (USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                           VALUES 
                           (:usr, 'LOGIN', 'USERS', 'User logged in successfully', :ip)";
                
                $logStmt = $conn->prepare($logSql);
                
                // Bind parameters
                $username = $result['user']['FULL_NAME'];
                // $userId   = $result['user']['USER_ID']; // Available if needed later
                
                $logStmt->execute([
                    ':usr' => $username,
                    ':ip'  => $ip_address
                ]);
            }
        } catch (Exception $e) {
            // Silently fail logging to allow login to proceed
        }
        // -------------------------------------

        header("Location: ../views/admin_dashboard.php"); // Redirect to admin dashboard
        exit;
        
    } else {
        // --- HARDCODED AUDIT LOG (FAILURE) ---
        try {
            if (isset($conn)) {
                $logSql = "INSERT INTO AUDIT_LOGS 
                           (USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                           VALUES 
                           (:usr, 'LOGIN_FAILED', 'USERS', :dtl, :ip)";
                
                $logStmt = $conn->prepare($logSql);
                
                // Use the attempted email as username
                $errorMsg = 'Login failed: ' . $result['error'];
                
                $logStmt->execute([
                    ':usr' => $email,
                    ':dtl' => $errorMsg,
                    ':ip'  => $ip_address
                ]);
            }
        } catch (Exception $e) {
            // Silently fail logging
        }
        // -------------------------------------

        header("Location: ../views/login.php?status=error&msg=" . urlencode($result['error']));
        exit;
    }
}
?>