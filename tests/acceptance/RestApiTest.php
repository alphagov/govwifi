<?php
namespace Alphagov\GovWifi;
require_once "TestConstants.php";

use PHPUnit_Framework_TestCase;

class RestApiTest extends PHPUnit_Framework_TestCase {
    /**
     * @coversNothing
     */
    public function testHealthCheckAuthorization() {
        $response = file_get_contents(
            TestConstants::REQUEST_PROTOCOL
            . TestConstants::getInstance()->getBackendContainer()
            . ":" . TestConstants::BACKEND_API_PORT
            . TestConstants::HEALTH_CHECK_AUTHORIZATION_URL,
            false,
            TestConstants::getInstance()->getHttpContext()
        );
        $this->assertEquals(TestConstants::HTTP_OK, $http_response_header[0]);
        $this->assertEquals(str_replace(
                TestConstants::CLEARTEXT_PASSWORD_PLACEHOLDER,
                TestConstants::HEALTH_CHECK_USER_PASSWORD,
                TestConstants::AUTHORIZATION_RESPONSE_TEMPLATE
            ),
            $response
        );
    }

    /**
     * @coversNothing
     */
    public function testHealthCheckPostAuth() {
        $response = file_get_contents(
            TestConstants::REQUEST_PROTOCOL
            . TestConstants::getInstance()->getBackendContainer()
            . ":" . TestConstants::BACKEND_API_PORT
            . TestConstants::HEALTH_CHECK_POST_AUTH_URL,
            false,
            TestConstants::getInstance()->getHttpContext()
        );
        $this->assertEquals(TestConstants::HTTP_OK, $http_response_header[0]);
        $this->assertEquals("", $response);
    }

    /**
     * @coversNothing
     */
    public function testUserAuthorization() {
        $response = file_get_contents(
            TestConstants::REQUEST_PROTOCOL
            . TestConstants::getInstance()->getBackendContainer()
            . ":" . TestConstants::BACKEND_API_PORT
            . TestConstants::USER_AUTHORIZATION_URL,
            false,
            TestConstants::getInstance()->getHttpContext()
        );
        $this->assertEquals(TestConstants::HTTP_OK, $http_response_header[0]);
        $this->assertEquals(str_replace(
                TestConstants::CLEARTEXT_PASSWORD_PLACEHOLDER,
                TestConstants::getInstance()->getTestUserPassword(),
                TestConstants::AUTHORIZATION_RESPONSE_TEMPLATE
            ),
            $response
        );
    }

    /**
     * @coversNothing
     */
    public function testUserPostAuth() {
        $response = file_get_contents(
            TestConstants::REQUEST_PROTOCOL
            . TestConstants::getInstance()->getBackendContainer()
            . ":" . TestConstants::BACKEND_API_PORT
            . TestConstants::USER_POST_AUTH_URL,
            false,
            TestConstants::getInstance()->getHttpContext()
        );
        $this->assertEquals(TestConstants::HTTP_OK, $http_response_header[0]);
        $this->assertEquals("", $response);
    }
}