<?php
namespace Alphagov\GovWifi;

use Exception;
use PDO;
use PDOException;

/**
 * Class DB
 *
 * Singleton. Manages the database connections.
 *
 * @package Alphagov\GovWifi
 */
class DB {
    const DB_TYPE_DEFAULT = 1;
    const DB_TYPE_READ_REPLICA = 2;
    const ALLOWED_DB_TYPES = [
        self::DB_TYPE_DEFAULT,
        self::DB_TYPE_READ_REPLICA
    ];
    private $connection;
    private static $instances = array();
    private $hostname;
    private $username;
    private $password;
    private $dbName;

    /**
     * Creates or returns an existing DB instance if on has been created previously.
     * @param int $dbType They type of the database connection to be used.
     * @return DB the db instance with a connection to the database defined by the type above.
     * @throws GovWifiException if the db type is not in the allowed list.
     */
    public static function getInstance($dbType = self::DB_TYPE_DEFAULT) {
        if (!in_array($dbType, self::ALLOWED_DB_TYPES)) {
            throw new GovWifiException("DB type not recognized. [" . $dbType . "]");
        }
        if (empty(self::$instances[ $dbType ])) {
            self::$instances[ $dbType ] = new self($dbType);
        }
        return self::$instances[ $dbType ];
    }

    // Constructor
    private function __construct($dbType) {
        try {
            $this->setCredentials($dbType);
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

    /**
     * Sets up credentials for connecting to a database based on the dbType provided
     * @param int $dbType The type of the database based on the allowed list.
     * @throws GovWifiException if the DB name was not set.
     */
    private function setCredentials($dbType) {
        switch ($dbType) {
            case self::DB_TYPE_DEFAULT:
                $this->hostname = trim(getenv("DB_HOSTNAME"));
                $this->username = trim(getenv("DB_USER"));
                $this->password = trim(getenv("DB_PASS"));
                $this->dbName   = trim(getenv("DB_NAME"));
                break;
            case self::DB_TYPE_READ_REPLICA:
                $this->hostname = trim(getenv("RR_DB_HOSTNAME"));
                $this->username = trim(getenv("RR_DB_USER"));
                $this->password = trim(getenv("RR_DB_PASS"));
                $this->dbName   = trim(getenv("RR_DB_NAME"));
                break;
        }

        if (empty($this->dbName)) {
            throw new GovWifiException("DB name is required.");
        }
    }

    // Magic method clone is empty to prevent duplication of connection
    private function __clone() { }

    public function getConnection() {
        return $this->connection;
    }
}
