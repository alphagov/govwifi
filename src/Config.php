<?php
namespace Alphagov\GovWifi;

class Config {
    const HEALTH_CHECK_USER = "HEALTH";
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
        foreach ($this->values as $key => $value) {
            $envValue = getenv($key);
            if ($envValue) {
                $this->values[$key] = $envValue;
            }
        }
    }
}
