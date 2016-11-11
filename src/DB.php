<?php
namespace Alphagov\GovWifi;

use Exception;
use PDO;
use PDOException;

class DB {
    private $connection;
    private static $instance; //The single instance
    private $hostname;
    private $username;
    private $password;
    private $dbName;

    public static function getInstance() {
        if (!self::$instance)
        { // If no instance then make one
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Constructor
    private function __construct() {
        try {
            $this->setCredentials();
            $this->connection = new PDO(
                    'mysql:host=' . $this->hostname
                    . '; dbname=' . $this->dbName
                    . '; charset=utf8mb4',
                    $this->username,
                    $this->password,
                    array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_PERSISTENT => false));
        } catch (PDOException $e) {
            error_log($e->getMessage());
        }
    }

    private function setCredentials() {
        if (getenv("DB_NAME")) {
            $this->hostname = trim(getenv("DB_HOSTNAME"));
            $this->username = trim(getenv("DB_USER"));
            $this->password = trim(getenv("DB_PASS"));
            $this->dbName   = trim(getenv("DB_NAME"));
        } else {
            throw new Exception("DB name is required.");
        }
    }

    // Magic method clone is empty to prevent duplication of connection
    private function __clone() { }

    public function getConnection()
    {
        return $this->connection;
    }
}

