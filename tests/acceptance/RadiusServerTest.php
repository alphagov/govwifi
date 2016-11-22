<?php

class RadiusServerTest extends PHPUnit_Framework_TestCase {
    const PEAP_MSCHAP_V2_CONFIG_TEMPLATE = "tests/acceptance/config/peap-mschapv2.conf";
    const CONFIG_FILE = "tests/acceptance/config/currentconfig.conf";
    const EAPOL_TEST_RUNNER = "tests/acceptance/config/run-eapol-test.sh";
    /**
     * @var string The user name to authenticate with.
     */
    private $userName;

    /**
     * @var string The password to authenticate with.
     */
    private $password;

    public function setUp() {
        $this->userName  = getenv("TEST_USER_NAME");
        $this->password  = getenv("TEST_USER_PASSWORD");
    }

    public function testRadiusPEAPMsChapV2Authentication() {
        $template = file_get_contents(self::PEAP_MSCHAP_V2_CONFIG_TEMPLATE);
        $configuration = str_replace("#PASSWORD#", $this->password,
            str_replace("#USERNAME#", $this->userName, $template)
        );
        file_put_contents(self::CONFIG_FILE, $configuration);

        $this->assertEquals("SUCCESS", exec("/bin/bash " . self::EAPOL_TEST_RUNNER));
    }
}