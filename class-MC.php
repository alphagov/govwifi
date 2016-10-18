<?php

class MC {
    private static $instance; //The single instance
    public $m;
    public $hostname;

    public static function getInstance() {
        if (!self::$instance) { // If no instance then make one
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->setHostname();
        try {
            $this->m = new Memcached();
            $this->m->addServer($this->hostname, 11211);
        } catch (PDOException $e) {
            error_log($e->getMessage());
        }
    }

    private function setHostname() {
        if (getenv("CACHE_HOSTNAME") == "") {
            $this->hostname = trim(file_get_contents("/etc/CACHE_HOSTNAME"));
        } else {
            $this->hostname = trim(getenv("CACHE_HOSTNAME"));
        }
    }
}

?>