<?php
// smart-menu/includes/db.php - Database connection

require_once __DIR__ . '/config.php';

class Database {
    private $connection;
    private static $instance;
    
    private function __construct() {
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->connection->connect_error) {
            error_log('Database connection failed: ' . $this->connection->connect_error);
            die('Database connection failed');
        }
        
        if (!$this->connection->set_charset('utf8mb4')) {
            error_log('Failed to set charset: ' . $this->connection->error);
        }
    }
    
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function getLastId() {
        return $this->connection->insert_id;
    }
    
    public function getError() {
        return $this->connection->error;
    }
    
    public function beginTransaction() {
        return $this->connection->begin_transaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
    
    public function close() {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
            self::$instance = null;
        }
    }
}

// Function to get database connection
function getDb() {
    return Database::getInstance()->getConnection();
}
?>