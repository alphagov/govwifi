<?php
namespace Alphagov\GovWifi;
require_once "tests/TestConstants.php";

use PDO;
use PHPUnit_Framework_TestCase;

class RestApiTest extends PHPUnit_Framework_TestCase {
    /**
     * @coversNothing
     */
    public function testHealthCheckAuthorisation() {
        $response = file_get_contents(
            TestConstants::getBackendBaseUrl()
            . TestConstants::authorisationUrlForUser(Config::HEALTH_CHECK_USER),
            false,
            TestConstants::getInstance()->getHttpContext()
        );
        $this->assertEquals(TestConstants::HTTP_OK, $http_response_header[0]);
        $this->assertEquals(
            TestConstants::authorisationResponseForPassword(
                TestConstants::HEALTH_CHECK_USER_PASSWORD
            ),
            $response
        );
    }

    /**
     * @coversNothing
     */
    public function testHealthCheckPostAuth() {
        $response = file_get_contents(
            TestConstants::getBackendBaseUrl()
            . TestConstants::postAuthUrlForUser(Config::HEALTH_CHECK_USER),
            false,
            TestConstants::getInstance()->getHttpContext()
        );
        $this->assertEquals(TestConstants::HTTP_11_NO_DATA, $http_response_header[0]);
        $this->assertEquals("", $response);
    }

    /**
     * @coversNothing
     */
    public function testUserAuthorization() {
        $response = file_get_contents(
            TestConstants::getBackendBaseUrl()
            . TestConstants::authorisationUrlForUser(
                TestConstants::getInstance()->getAcceptanceTestUserName()
            ),
            false,
            TestConstants::getInstance()->getHttpContext()
        );
        $this->assertEquals(TestConstants::HTTP_OK, $http_response_header[0]);
        $this->assertEquals(
            TestConstants::authorisationResponseForPassword(
                TestConstants::getInstance()->getAcceptanceTestUserPassword()
            ),
            $response
        );
    }

    /**
     * @coversNothing
     */
    public function testUserPostAuth() {
        $response = file_get_contents(
            TestConstants::getBackendBaseUrl()
            . TestConstants::postAuthUrlForUser(
                TestConstants::getInstance()->getAcceptanceTestUserName()
            ),
            false,
            TestConstants::getInstance()->getHttpContext()
        );
        $this->assertEquals(TestConstants::HTTP_11_NO_DATA, $http_response_header[0]);
        $this->assertEquals("", $response);

        $statement = DB::getInstance()->getConnection()->prepare(
            "SELECT * FROM session WHERE username = :username ORDER BY start DESC LIMIT 1");
        $statement->bindValue(
            ":username",
            TestConstants::getInstance()->getAcceptanceTestUserName(),
            PDO::PARAM_STR
        );
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        $this->assertEquals(
            TestConstants::getInstance()->getAcceptanceTestUserName(),
            $result[0]['username']);
        $this->assertEquals("02-11-00-00-00-01",        $result[0]['mac']);
        $this->assertEquals(TestConstants::BUILDING_ID, $result[0]['building_identifier']);
        $this->assertEquals(null,                       $result[0]['ap']);
        $this->assertEquals("172.17.0.6",               $result[0]['siteIP']);
    }

    // The order of the tests below matters (!)

    /**
     * @coversNothing
     */
    public function testAccountingStart() {
        $userName = TestConstants::getInstance()->getAcceptanceTestUserName();
        $response = file_get_contents(
            TestConstants::getBackendBaseUrl()
            . TestConstants::accountingUrlForUser($userName),
            false,
            stream_context_create(array(
                'http' => array(
                    'method'           => 'POST',
                    'protocol_version' => 1.0,
                    'header'           => 'Content-type: application/json',
                    'content'          =>
                        TestConstants::getAccountingJsonForType(AAA::ACCOUNTING_TYPE_START, $userName)
                )
            ))
        );
        $this->assertEquals(TestConstants::HTTP_NO_DATA, $http_response_header[0]);
        $this->assertEquals("", $response);
    }

    /**
     * @coversNothing
     */
    public function testAccountingInterim() {
        $userName = TestConstants::getInstance()->getAcceptanceTestUserName();
        $response = file_get_contents(
            TestConstants::getBackendBaseUrl()
            . TestConstants::accountingUrlForUser($userName),
            false,
            stream_context_create(array(
                'http' => array(
                    'method'           => 'POST',
                    'protocol_version' => 1.0,
                    'header'           => 'Content-type: application/json',
                    'content'          =>
                        TestConstants::getAccountingJsonForType(AAA::ACCOUNTING_TYPE_INTERIM, $userName)
                )
            ))
        );
        $this->assertEquals(TestConstants::HTTP_NO_DATA, $http_response_header[0]);
        $this->assertEquals("", $response);
    }

    /**
     * @coversNothing
     */
    public function testAccountingStop() {
        $userName = TestConstants::getInstance()->getAcceptanceTestUserName();
        $response = file_get_contents(
            TestConstants::getBackendBaseUrl()
            . TestConstants::accountingUrlForUser($userName),
            false,
            stream_context_create(array(
                'http' => array(
                    'method'           => 'POST',
                    'protocol_version' => 1.0,
                    'header'           => 'Content-type: application/json',
                    'content'          =>
                        TestConstants::getAccountingJsonForType(AAA::ACCOUNTING_TYPE_STOP, $userName)
                )
            ))
        );
        $this->assertEquals(TestConstants::HTTP_NO_DATA, $http_response_header[0]);
        $this->assertEquals("", $response);
    }

    /**
     * @coversNothing
     */
    public function testAccountingOn() {
        $userName = TestConstants::getInstance()->getAcceptanceTestUserName();
        $response = file_get_contents(
            TestConstants::getBackendBaseUrl()
            . TestConstants::accountingUrlForUser($userName),
            false,
            stream_context_create(array(
                'http' => array(
                    'method'           => 'POST',
                    'protocol_version' => 1.0,
                    'header'           => 'Content-type: application/json',
                    'content'          =>
                        TestConstants::getAccountingJsonForType(AAA::ACCOUNTING_TYPE_ON, $userName)
                )
            ))
        );
        $this->assertEquals(TestConstants::HTTP_NO_DATA, $http_response_header[0]);
        $this->assertEquals("", $response);
    }

    /**
     * @coversNothing
     */
    public function testAccountingOff() {
        $userName = TestConstants::getInstance()->getAcceptanceTestUserName();
        $response = file_get_contents(
            TestConstants::getBackendBaseUrl()
            . TestConstants::accountingUrlForUser($userName),
            false,
            stream_context_create(array(
                'http' => array(
                    'method'           => 'POST',
                    'protocol_version' => 1.0,
                    'header'           => 'Content-type: application/json',
                    'content'          =>
                        TestConstants::getAccountingJsonForType(AAA::ACCOUNTING_TYPE_OFF, $userName)
                )
            ))
        );
        $this->assertEquals(TestConstants::HTTP_NO_DATA, $http_response_header[0]);
        $this->assertEquals("", $response);
    }
}
