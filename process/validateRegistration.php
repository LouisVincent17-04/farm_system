<?php
// ../process/registerProcess.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

include '../config/Connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$fullname = trim($_POST['fullname'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// --- Validation ---
if (empty($fullname)) {
    echo json_encode(['success' => false, 'message' => 'Full name is required.']);
    exit;
}

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required.']);
    exit;
}

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    $sql = "INSERT INTO USERS (FULL_NAME, EMAIL, PASSWORD, CREATED_AT) 
            VALUES (:fullname, :email, :password, NOW())";
    
    $stmt = $conn->prepare($sql);
    
    $params = [
        ":fullname" => $fullname,
        ":email"    => $email,
        ":password" => password_hash($password, PASSWORD_BCRYPT)
    ];

    if ($stmt->execute($params)) {
        echo json_encode(['success' => true, 'message' => 'Registration successful!']);
        header('Location: ../views/admin_dashboard.php');
        exit;
    } else {
        throw new Exception("Registration failed.");
    }

} catch (PDOException $e) {
    $errorMsg = $e->getMessage();
    
    // Check for Duplicate Email (MySQL Error 1062 / SQLSTATE 23000)
    if ($e->getCode() == '23000' || strpos($errorMsg, 'Duplicate entry') !== false) {
        $errorMsg = "This email address is already registered.";
    }

    echo json_encode(['success' => false, 'message' => $errorMsg]);
    header('Location: ../views/login.php');
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    header('Location: ../views/dashboard.php');
    exit;
}
?>