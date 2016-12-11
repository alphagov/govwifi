<?php
namespace Alphagov\GovWifi;
use Exception;

/**
 * Class TestConstants
 * Singleton. Stores constants and configuration used in the acceptance tests.
 *
 * @package Alphagov\GovWifi
 */
class TestConstants {
    const REQUEST_PROTOCOL                = "http://";
    const HTTP_OK                         = "HTTP/1.1 200 OK";
    const HTTP_NO_DATA                    = "HTTP/1.0 204 OK";
    const HEALTH_CHECK_USER_PASSWORD      = 'GS3EWA64EshRD8I0XdVl$dko';
    const BACKEND_API_PORT                = "8080";
    const CLEARTEXT_PASSWORD_PLACEHOLDER  = "#CLEARTEXT_PASSWORD#";
    const AUTHORIZATION_RESPONSE_TEMPLATE =
        "{\"control:Cleartext-Password\":\"" . self::CLEARTEXT_PASSWORD_PLACEHOLDER . "\"}";
    const USERNAME_PLACEHOLDER            = "#USERNAME#";
    const AUTHORIZATION_URL_TEMPLATE      = "/api/authorize/user/" . self::USERNAME_PLACEHOLDER
        . "/mac/02-11-00-00-00-01/ap//site/172.17.0.6";
    const POST_AUTH_URL_TEMPLATE          = "/api/post-auth/user/" . self::USERNAME_PLACEHOLDER
        . "/mac/02-11-00-00-00-01/ap//site/172.17.0.6/result/Access-Accept";
    const ACCOUNTING_URL_TEMPLATE         = "/api/accounting/user/" . self::USERNAME_PLACEHOLDER
        . "/site/172.17.0.6";
    const ACCOUNTING_DATA_FILE_START      = "tests/acceptance/config/radius-accounting-start.json";
    const ACCOUNTING_DATA_FILE_STOP       = "tests/acceptance/config/radius-accounting-stop.json";
    const ACCOUNTING_DATA_FILE_INTERIM    = "tests/acceptance/config/radius-accounting-interim.json";
    const TIMESTAMP_PLACEHOLDER           = "#TIMESTAMP#";

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
     * Builds and authorisation url for the username provided.
     *
     * @param $username string
     * @return string
     */
    public static function authorisationUrlForUser($username) {
        return str_replace(
            self::USERNAME_PLACEHOLDER,
            $username,
            self::AUTHORIZATION_URL_TEMPLATE
        );
    }

    /**
     * Builds a post-auth url for the username provided.
     *
     * @param $username string
     * @return string
     */
    public static function postAuthUrlForUser($username) {
        return str_replace(
            self::USERNAME_PLACEHOLDER,
            $username,
            self::POST_AUTH_URL_TEMPLATE
        );
    }

    /**
     * Builds an authorisation response JSON containing the password.
     *
     * @param $password string
     * @return string
     */
    public static function authorisationResponseForPassword($password) {
        return str_replace(
            self::CLEARTEXT_PASSWORD_PLACEHOLDER,
            $password,
            self::AUTHORIZATION_RESPONSE_TEMPLATE
        );
    }

    /**
     * Builds an accounting url for the username provided.
     *
     * @param $username string
     * @return string
     */
    public static function accountingUrlForUser($username) {
        return str_replace(
            self::USERNAME_PLACEHOLDER,
            $username,
            self::ACCOUNTING_URL_TEMPLATE
        );
    }

    /**
     * Builds a backend base url
     */
    public static function getBackendBaseUrl() {
        return self::REQUEST_PROTOCOL
        . self::getInstance()->getBackendContainer()
        . ":" . self::BACKEND_API_PORT;
    }

    /**
     * Builds the json data for the accounting request type and username provided.
     *
     * @param $accountingType string
     * @param $username string
     * @return string the json data
     * @throws Exception if the accounting type is not in the list defined in class AAA,
     * or there's no file for the given type
     */
    public static function getAccountingJsonForType($accountingType, $username) {
        if (!in_array($accountingType, AAA::ACCEPTED_ACCOUNTING_TYPES)) {
            throw new Exception("Accounting type not recognised. [" . $accountingType . "]");
        }
        $jsonData = "";
        switch ($accountingType) {
            case AAA::ACCOUNTING_TYPE_START:
                $jsonData = file_get_contents(self::ACCOUNTING_DATA_FILE_START);
                break;
            case AAA::ACCOUNTING_TYPE_STOP:
                $jsonData = file_get_contents(self::ACCOUNTING_DATA_FILE_STOP);
                break;
            case AAA::ACCOUNTING_TYPE_INTERIM:
                $jsonData = file_get_contents(self::ACCOUNTING_DATA_FILE_INTERIM);
                break;
        }
        if (empty($jsonData)) {
            throw new Exception("Data file not found for the accounting type provided. [" . $accountingType . "]");
        }
        return str_replace(
            self::USERNAME_PLACEHOLDER,
            $username,
            str_replace(
                self::TIMESTAMP_PLACEHOLDER,
                date('M d Y H:i:s T', time()),
                $jsonData
            )
        );
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