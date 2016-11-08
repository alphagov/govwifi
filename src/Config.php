<?php
namespace Alphagov\GovWifi;

class Config {
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
            if (getenv($key))
		$this->values[$key] = $value;
    }
}
