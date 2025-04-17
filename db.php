<?php
/**
 * db.php - Database connection and helper functions
 */

/**
 * Get database connection
 * @return mysqli Database connection
 */
function getDb() {
    static $db = null;
    
    if ($db === null) {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($db->connect_error) {
            die('Database connection failed: ' . $db->connect_error);
        }
        
        $db->set_charset('utf8mb4');
    }
    
    return $db;
}

/**
 * Execute a query and return result
 * @param string $sql SQL query
 * @param array $params Parameters for prepared statement
 * @param string $types Types of parameters (i=integer, s=string, d=double, b=blob)
 * @return mysqli_result|bool Query result
 */
function dbQuery($sql, $params = [], $types = '') {
    $db = getDb();
    
    if (empty($params)) {
        return $db->query($sql);
    }
    
    $stmt = $db->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $db->error);
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            $types = str_repeat('s', count($params));
        }
        
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result;
}

/**
 * Get a single row from the database
 * @param string $sql SQL query
 * @param array $params Parameters for prepared statement
 * @param string $types Types of parameters
 * @return array|null Result row as associative array or null
 */
function dbFetchRow($sql, $params = [], $types = '') {
    $result = dbQuery($sql, $params, $types);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Get multiple rows from the database
 * @param string $sql SQL query
 * @param array $params Parameters for prepared statement
 * @param string $types Types of parameters
 * @return array Result rows as associative arrays
 */
function dbFetchAll($sql, $params = [], $types = '') {
    $result = dbQuery($sql, $params, $types);
    $rows = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    
    return $rows;
}

/**
 * Insert data into a table
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @return int|bool Last insert ID or false on failure
 */
function dbInsert($table, $data) {
    $db = getDb();
    $columns = array_keys($data);
    $values = array_values($data);
    $types = '';
    
    // Determine parameter types
    foreach ($values as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value) || is_double($value)) {
            $types .= 'd';
        } elseif (is_string($value)) {
            $types .= 's';
        } else {
            $types .= 'b';
        }
    }
    
    $placeholders = array_fill(0, count($columns), '?');
    
    $sql = "INSERT INTO {$table} (`" . implode('`, `', $columns) . "`) 
            VALUES (" . implode(', ', $placeholders) . ")";
    
    $stmt = $db->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Insert preparation failed: " . $db->error);
    }
    
    $stmt->bind_param($types, ...$values);
    $result = $stmt->execute();
    
    if ($result) {
        return $db->insert_id;
    }
    
    return false;
}

/**
 * Update data in a table
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @param string $where WHERE clause (without 'WHERE' keyword)
 * @param array $whereParams Parameters for WHERE clause
 * @param string $whereTypes Types for WHERE parameters
 * @return bool Success or failure
 */
function dbUpdate($table, $data, $where, $whereParams = [], $whereTypes = '') {
    $db = getDb();
    $columns = array_keys($data);
    $values = array_values($data);
    $types = '';
    
    // Determine parameter types for data
    foreach ($values as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value) || is_double($value)) {
            $types .= 'd';
        } elseif (is_string($value)) {
            $types .= 's';
        } else {
            $types .= 'b';
        }
    }
    
    // Add WHERE parameter types
    $types .= $whereTypes;
    
    // Build SET clause
    $set = [];
    foreach ($columns as $column) {
        $set[] = "`{$column}` = ?";
    }
    
    $sql = "UPDATE {$table} SET " . implode(', ', $set) . " WHERE {$where}";
    
    $stmt = $db->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Update preparation failed: " . $db->error);
    }
    
    // Combine data values and where parameters
    $allParams = array_merge($values, $whereParams);
    
    $stmt->bind_param($types, ...$allParams);
    return $stmt->execute();
}

/**
 * Begin a transaction
 * @return bool Success or failure
 */
function dbBeginTransaction() {
    return getDb()->begin_transaction();
}

/**
 * Commit a transaction
 * @return bool Success or failure
 */
function dbCommit() {
    return getDb()->commit();
}

/**
 * Rollback a transaction
 * @return bool Success or failure
 */
function dbRollback() {
    return getDb()->rollback();
}

/**
 * Get last insert ID
 * @return int Last insert ID
 */
function dbLastId() {
    return getDb()->insert_id;
}

/**
 * Add method to mysqli class for missing function in some environments
 */
if (!method_exists('mysqli', 'getLastId')) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
    class mysqli_extended extends mysqli {
        public function getLastId() {
            return $this->insert_id;
        }
        
        public function beginTransaction() {
            return $this->begin_transaction();
        }
    }
    
    function getDb() {
        static $db = null;
        
        if ($db === null) {
            $db = new mysqli_extended(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($db->connect_error) {
                die('Database connection failed: ' . $db->connect_error);
            }
            
            $db->set_charset('utf8mb4');
        }
        
        return $db;
    }
}