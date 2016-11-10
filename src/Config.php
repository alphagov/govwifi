<?php
namespace Alphagov\GovWifi;

use Exception;

class Config {
    const HEALTH_CHECK_USER = "HEALTH";
    const FIRETEXT_EMPTY_KEYWORD = "NON-SPECIFIED";
    private static $instance;
    public $values;

    public static function getInstance() {
        if (!self::$instance) { // If no instance then make one
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $environment = getenv("ENVIRONMENT_NAME");
        if ($environment) {
            $this->values = parse_ini_file("/etc/enrollment." . $environment . ".cfg", "TRUE");
        } else {
            throw new Exception("Environment name is required.");
        }
        $radiusIPs = getenv("RADIUS_SERVER_IPS");
        if ($radiusIPs) {
            $this->values['radiusIPs'] = $radiusIPs;
            $this->values['radiusServerCount'] = count(explode(",", $radiusIPs));
            $radiusHostname = getenv('RADIUS_HOSTNAME');
            if ($radiusHostname) {
                $this->values['radiusHostnameTemplate'] = $radiusHostname;
            }
        }
    }
}
