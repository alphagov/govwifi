<?php
namespace Alphagov\GovWifi;

/**
 * Class TestConstants
 * Singleton. Stores constants and configuration used in the acceptance tests.
 *
 * @package Alphagov\GovWifi
 */
class TestConstants {
    const REQUEST_PROTOCOL           = "http://";
    const HTTP_OK                    = "HTTP/1.1 200 OK";
    const HEALTH_CHECK_USER_PASSWORD = 'GS3EWA64EshRD8I0XdVl$dko';
    const BACKEND_API_PORT           = "8080";
    const HEALTH_CHECK_AUTHORIZATION_URL =
        "/api/authorize/user/HEALTH/mac/02-00-00-00-00-01/ap//site/172.17.0.6";
    const CLEARTEXT_PASSWORD_PLACEHOLDER = "#CLEARTEXT_PASSWORD#";
    const AUTHORIZATION_RESPONSE_TEMPLATE =
        "{\"control:Cleartext-Password\":\"" . self::CLEARTEXT_PASSWORD_PLACEHOLDER . "\"}";
    const HEALTH_CHECK_POST_AUTH_URL =
        "/api/post-auth/user/HEALTH/mac/02-00-00-00-00-01/ap//site/172.17.0.6/result/Access-Accept";
    const USER_AUTHORIZATION_URL =
        "/api/authorize/user/GQDMK/mac/02-00-00-00-00-01/ap//site/172.17.0.6";
    const USER_POST_AUTH_URL =
        "/api/post-auth/user/GQDMK/mac/02-00-00-00-00-01/ap//site/172.17.0.6/result/Access-Accept";

    /**
     * @var TestConstants
     */
    private static $instance;

    /**
     * @var string The user name to authenticate with.
     */
    private $testUserName;

    /**
     * @var string The password to authenticate with.
     */
    private $testUserPassword;

    /**
     * @var string the host name of the frontend docker container.
     */
    private $frontendContainer;

    /**
     * @var string the host name of the backend docker container.
     */
    private $backendContainer;

    /**
     * @var resource
     */
    private $httpContext;

    private function __construct() {
        $this->testUserName      = getenv("TEST_USER_NAME");
        $this->testUserPassword  = getenv("TEST_USER_PASSWORD");
        $this->frontendContainer = getenv("FRONTEND_CONTAINER");
        $this->backendContainer  = getenv("BACKEND_CONTAINER");
        $this->httpContext       = stream_context_create(array(
            'http' => array(
                'timeout' => 5,
                'protocol_version' => 1.1,
                'header' => 'Connection: close'
            )
        ));
    }

    /**
     * @return TestConstants
     */
    public static function getInstance() {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @return string
     */
    public function getTestUserName() {
        return $this->testUserName;
    }

    /**
     * @return string
     */
    public function getTestUserPassword() {
        return $this->testUserPassword;
    }

    /**
     * @return string
     */
    public function getFrontendContainer() {
        return $this->frontendContainer;
    }

    /**
     * @return string
     */
    public function getBackendContainer() {
        return $this->backendContainer;
    }

    /**
     * @return resource
     */
    public function getHttpContext() {
        return $this->httpContext;
    }
}