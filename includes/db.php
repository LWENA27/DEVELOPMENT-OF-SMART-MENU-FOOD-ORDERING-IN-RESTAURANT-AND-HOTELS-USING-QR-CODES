<?php
// includes/db.php - Database connection

require_once 'config.php';

class Database {
    private $connection;
    private static $instance;
    
    private function __construct() {
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->connection->connect_error) {
            die('Database connection failed: ' . $this->connection->connect_error);
        }
        
        $this->connection->set_charset('utf8mb4');
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
    
    public function query($sql) {
        return $this->connection->query($sql);
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function escape($value) {
        return $this->connection->real_escape_string($value);
    }
    
    public function getLastId() {
        return $this->connection->insert_id;
    }
    
    public function getError() {
        return $this->connection->error;
    }
    
    public function beginTransaction() {
        $this->connection->begin_transaction();
    }
    
    public function commit() {
        $this->connection->commit();
    }
    
    public function rollback() {
        $this->connection->rollback();
    }
    
    public function close() {
        $this->connection->close();
    }
}

// Function to get database connection
function getDb() {
    return Database::getInstance()->getConnection();
}
?>