<?php

class config {
    private static $instance;
    public $values;

    public static function getInstance() {
        if (!self::$instance) { // If no instance then make one
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->values = parse_ini_file("/etc/enrollment.cfg", "TRUE");
    }
}

?>