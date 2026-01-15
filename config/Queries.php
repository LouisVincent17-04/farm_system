<?php
/**
 * Retrieves data from the database (SELECT).
 * * @param PDO $conn The PDO connection object
 * @param string $sql The SQL query with named placeholders (e.g., :id)
 * @param array $params Associative array of parameters (e.g., [':id' => 1])
 * @return array Associative array of results
 */
function retrieveData($conn, $sql, $params = []) {
    try {
        // Prepare the statement
        $stmt = $conn->prepare($sql);

        // Execute automatically binds the parameters provided in the array
        $result = $stmt->execute($params);

        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            die("❌ Execute error: " . $errorInfo[2]);
        }

        // Fetch all results as an associative array
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $rows;

    } catch (PDOException $e) {
        die("❌ Error: " . $e->getMessage());
    }
}

/**
 * Modifies data in the database (INSERT, UPDATE, DELETE).
 * * @param PDO $conn The PDO connection object
 * @param string $sql The SQL query with named placeholders
 * @param array $params Associative array of parameters
 * @return bool True on success
 */
function modifyTable($conn, $sql, $params = []) {
    try {
        // Prepare the statement
        $stmt = $conn->prepare($sql);

        // Execute the statement
        $result = $stmt->execute($params);

        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            die("❌ Execute error: " . $errorInfo[2]);
        }

        return true;

    } catch (PDOException $e) {
        die("❌ Error: " . $e->getMessage());
    }
}
?>