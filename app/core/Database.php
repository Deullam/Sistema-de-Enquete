<?php

/**
 * Database Class
 * Singleton pattern implementation for database connection
 */
class Database
{
    private static $instance = null;
    private $connection = null;
    private $host;
    private $db_name;
    private $username;
    private $password;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->loadEnv();

        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'deullam';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';
    }

    /**
     * Get singleton instance
     * @return Database
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load environment variables from .env file
     */
    private function loadEnv()
    {
        $envFile = dirname(dirname(__DIR__)) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    putenv("$key=$value");
                }
            }
        }
    }

    /**
     * Connect to the database
     * @return PDO|null
     * @throws PDOException If connection fails and APP_DEBUG is true
     */
    public function connect()
    {
        if ($this->connection !== null) {
            return $this->connection;
        }
        try {
            $pdo = new PDO("mysql:host=$this->host;dbname=$this->db_name", $this->username, $this->password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection = $pdo;
            echo "<h2>Connected to database: $this->db_name</h2>";
            return $this->connection;
        } catch (PDOException $e) {
            // Better error handling based on environment
            if (getenv('APP_DEBUG') === 'true') {
                throw $e; 
            } else {
                // Log error but don't expose details in production
                error_log('Database connection failed: ' . $e->getMessage());
                return null;
            }
        }
    }
    /**
     * Execute a query with parameters
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind to the query
     * @return PDOStatement|false
     */
    public function query($query, $params = [])
    {
        try {
            $stmt = $this->connect()->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (getenv('APP_DEBUG') === 'true') {
                throw $e;
            } else {
                error_log('Query execution failed: ' . $e->getMessage());
                return false;
            }
        }
    }

    /**
     * Get a single record
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind to the query
     * @return array|false Single record or false on failure
     */
    public function single($query, $params = [])
    {
        $stmt = $this->query($query, $params);
        return $stmt ? $stmt->fetch() : false;
    }

    /**
     * Get multiple records
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind to the query
     * @return array|false Array of records or false on failure
     */
    public function resultSet($query, $params = [])
    {
        $stmt = $this->query($query, $params);
        return $stmt ? $stmt->fetchAll() : false;
    }

    /**
     * Get row count from last query
     * @return int
     */
    // public function rowCount() {
    //     return $this->stmt->rowCount();
    // }

    /**
     * Get last inserted ID
     * @return string
     */
    public function lastInsertId()
    {
        return $this->connect()->lastInsertId();
    }
}
