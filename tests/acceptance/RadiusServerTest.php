<?php
namespace Alphagov\GovWifi;
require_once "tests/TestConstants.php";

use PHPUnit_Framework_TestCase;

class RadiusServerTest extends PHPUnit_Framework_TestCase {
    const PEAP_MSCHAP_V2_CONFIG_TEMPLATE = "tests/acceptance/config/peap-mschapv2.conf";
    const CONFIG_FILE                    = "tests/acceptance/config/currentconfig.conf";
    const EAPOL_TEST_RUNNER              = "tests/acceptance/config/run-eapol-test.sh";
    const PASSWORD_PLACEHOLDER           = "#PASSWORD#";
    const USERNAME_PLACEHOLDER           = "#USERNAME#";
    const SUCCESS_LINE                   = "SUCCESS";

    /**
     * @coversNothing Do not count acceptance tests in code coverage analysis.
     */
    public function testRadiusPEAPMsChapV2Authentication() {
        $template = file_get_contents(self::PEAP_MSCHAP_V2_CONFIG_TEMPLATE);
        $configuration = str_replace(
            self::PASSWORD_PLACEHOLDER, TestConstants::getInstance()->getAcceptanceTestUserPassword(),
            str_replace(
                self::USERNAME_PLACEHOLDER, TestConstants::getInstance()->getAcceptanceTestUserName(), $template)
        );
        file_put_contents(self::CONFIG_FILE, $configuration);

        $this->assertEquals(self::SUCCESS_LINE, exec("/bin/bash " . self::EAPOL_TEST_RUNNER));
    }

    /**
     * @coversNothing
     */
    public function testRadiusHealthCheckAuthentication() {
        $template = file_get_contents(self::PEAP_MSCHAP_V2_CONFIG_TEMPLATE);
        $configuration = str_replace(
            self::PASSWORD_PLACEHOLDER, TestConstants::HEALTH_CHECK_USER_PASSWORD,
            str_replace(
                self::USERNAME_PLACEHOLDER, Config::HEALTH_CHECK_USER, $template)
        );
        file_put_contents(self::CONFIG_FILE, $configuration);

        $this->assertEquals(self::SUCCESS_LINE, exec("/bin/bash " . self::EAPOL_TEST_RUNNER));
    }

    /**
     * @coversNothing
     */
    public function testRadiusHealthCheckUrl() {
        @file_get_contents(TestConstants::REQUEST_PROTOCOL
            . TestConstants::getInstance()->getFrontendContainer() . "/");
        $this->assertEquals(TestConstants::HTTP_OK, $http_response_header[0]);
    }
}