<?php
namespace Alphagov\GovWifi;

use Exception;

class Config {
    const HEALTH_CHECK_USER      = "HEALTH";
    const FIRETEXT_EMPTY_KEYWORD = "NON-SPECIFIED";
    const SERVICE_NAME           = "GovWifi";
    private static $instance;
    public $values;
    public $environment;

    public static function getInstance() {
        if (!self::$instance) { // If no instance then make one
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Set up default values
        $this->loadValuesFromConfig("default-values");
        // Extend / overwrite with environment specific ones
        $this->environment = strtolower(trim(getenv("ENVIRONMENT_NAME")));
        if ($this->environment) {
            $this->loadValuesFromConfig($this->environment);
        } else {
            throw new GovWifiException("Environment name is required.");
        }

        // By default the values below are passed from terraform, and the
        // IP(ranges) should match with the firewall settings.
        $radiusIPs = getenv("RADIUS_SERVER_IPS");
        if ($radiusIPs) {
            // Expecting a comma-separated list of IP ranges here.
            $this->values['radiusIPs'] = $radiusIPs;
            $this->values['radiusServerCount'] = count(explode(",", $radiusIPs));
            $radiusHostname = getenv('RADIUS_HOSTNAME');
            if ($radiusHostname) {
                $this->values['radiusHostnameTemplate'] = $radiusHostname;
            }
        }
        // Shared key for the frontend init script to grab the clients list
        $this->values["frontendApiKey"] = getenv("FRONTEND_API_KEY");
    }

    /**
     * Load configuration values from an ini file identified by the configLabel
     * parameter. Can be called multiple times. Subsequent calls will overwrite
     * configuration values with the same name.
     *
     * Expects the ini files to be located at /etc/enrollment.[configLabel].cfg
     *
     * @param $configLabel String The name of the environment or the literal
     * "default-values" for loading the default configuration.
     */
    private function loadValuesFromConfig($configLabel) {
        $configValues = parse_ini_file(
            "/etc/enrollment." . $configLabel . ".cfg", "TRUE");
        foreach ($configValues as $key => $configValue) {
            $this->values[$key] = $configValue;
        }
    }
}
