<?php
namespace Alphagov\GovWifi;
require_once "tests/TestConstants.php";

use Exception;
use PDO;
use PHPUnit_Framework_TestCase;

class AAATest extends PHPUnit_Framework_TestCase {
    const AUTHORISATION_URL    = "/api/authorize/user/HEALTH/mac/02-00-00-00-00-01/"
        . "ap/02-00-00-42-00-01/site/172.17.0.6";
    const POST_AUTH_URL        = "/api/post-auth/user/HEALTH/mac/02-00-00-00-00-01/"
        . "ap/02-00-00-42-00-01/site/172.17.0.6/result/Access-Accept";
    const POST_AUTH_REJECT_URL = "/api/post-auth/user/HEALTH/mac/02-00-00-00-00-01/"
        . "ap/02-00-00-42-00-01/site/172.17.0.6/result/Access-Reject";
    const POST_AUTH_MALFORMED_URL = "/api/post-auth/user/HEALTH/mac/02-00-00-00-00-01/"
    . "ap/02-00-00-42-00-01/site/172.17.0.6/result/Access-Malformed";
    const AUTHENTICATION_URL   = "/api/authenticate/user/HEALTH/mac/02-00-00-00-00-01/"
        . "site/172.17.0.6";

    function testClassInstantiates() {
        $this->assertInstanceOf(AAA::class, new AAA(self::AUTHORISATION_URL, ""));
    }

    function testAuthorisationUrlIsParsedProperly() {
        $aaa = new AAA(self::AUTHORISATION_URL, "");
        $this->assertEquals(AAA::TYPE_AUTHORIZE,       $aaa->type);
        $this->assertInstanceOf(User::class,           $aaa->user);
        $this->assertEquals(Config::HEALTH_CHECK_USER, $aaa->user->login);
        $this->assertEquals("02-00-00-00-00-01",       $aaa->getMac());
        $this->assertEquals("02-00-00-42-00-01",       $aaa->getAp());
        $this->assertInstanceOf(Site::class,           $aaa->site);
    }

    function testPostAuthAcceptUrlIsParsedProperly() {
        $aaa = new AAA(self::POST_AUTH_URL, "");
        $this->assertEquals(AAA::TYPE_POST_AUTH,       $aaa->type);
        $this->assertInstanceOf(User::class,           $aaa->user);
        $this->assertEquals(Config::HEALTH_CHECK_USER, $aaa->user->login);
        $this->assertEquals("02-00-00-00-00-01",       $aaa->getMac());
        $this->assertEquals("02-00-00-42-00-01",       $aaa->getAp());
        $this->assertInstanceOf(Site::class,           $aaa->site);
        $this->assertEquals("Access-Accept",           $aaa->result);
    }

    function testPostAuthRejectIsParsedProperly() {
        $aaa = new AAA(self::POST_AUTH_REJECT_URL, "");
        $this->assertEquals(AAA::TYPE_POST_AUTH,       $aaa->type);
        $this->assertInstanceOf(User::class,           $aaa->user);
        $this->assertEquals(Config::HEALTH_CHECK_USER, $aaa->user->login);
        $this->assertEquals("02-00-00-00-00-01",       $aaa->getMac());
        $this->assertEquals("02-00-00-42-00-01",       $aaa->getAp());
        $this->assertInstanceOf(Site::class,           $aaa->site);
        $this->assertEquals("Access-Reject",           $aaa->result);
    }

    function testAuthorisationUrlIsParsedProperlyForTestUser() {
        $userName = TestConstants::getInstance()->getUnitTestUserName();
        $aaa = new AAA(TestConstants::authorisationUrlForUser($userName), "");
        $this->assertEquals(AAA::TYPE_AUTHORIZE,       $aaa->type);
        $this->assertInstanceOf(User::class,           $aaa->user);
        $this->assertEquals(
            TestConstants::getInstance()->getUnitTestUserPassword(),
            $aaa->user->password);
        $this->assertEquals($userName,                 $aaa->user->login);
        $this->assertEquals("02-11-00-00-00-01",       $aaa->getMac());
        $this->assertEquals(null,                      $aaa->getAp());
        $this->assertEquals(
            TestConstants::BUILDING_ID,
            $aaa->getBuildingIdentifier());
        $this->assertInstanceOf(Site::class,           $aaa->site);
    }

    function testProcessAuthorisationRequestHealthCheck() {
        $aaa = new AAA(TestConstants::authorisationUrlForUser(Config::HEALTH_CHECK_USER), "");
        $this->assertEquals(
            [
                'headers' => [
                    TestConstants::HTTP_OK,
                    "Content-Type: application/json",
                ],
                'body' => TestConstants::authorisationResponseForPassword(
                    TestConstants::HEALTH_CHECK_USER_PASSWORD)
            ],
            $aaa->processRequest()
        );
    }

    function testProcessAuthorisationRequestNormalUser() {
        $aaa = new AAA(TestConstants::authorisationUrlForUser(TestConstants::getInstance()->getUnitTestUserName()), "");
        $this->assertEquals(
            [
                'headers' => [
                    TestConstants::HTTP_OK,
                    "Content-Type: application/json",
                ],
                'body' => TestConstants::authorisationResponseForPassword(
                    TestConstants::getInstance()->getUnitTestUserPassword())
            ],
            $aaa->processRequest()
        );
    }

    function testProcessAuthRejectRequestNormalUser() {
        $aaa = new AAA(TestConstants::authorisationUrlForUser("INVALI"), "");
        $this->assertEquals(
            [
                'headers' => [
                    TestConstants::HTTP_11_NOT_FOUND,
                    "Content-Type: application/json",
                ],
                'body' => ''
            ],
            $aaa->processRequest()
        );
    }

    function testProcessPostAuthRequestHealthCheckDoesNotStartASession() {
        $aaa = new AAA(TestConstants::postAuthUrlForUser(Config::HEALTH_CHECK_USER), "");
        $this->assertEquals(
            [
                'headers' => [
                    TestConstants::HTTP_11_NO_DATA,
                    "Content-Type: application/json",
                ],
                'body' => ''
            ],
            $aaa->processRequest()
        );
        self::assertEquals(array(), $this->getSessionDataForUser(Config::HEALTH_CHECK_USER, "bla"));
    }

    function testProcessPostAuthRequestNormalUser() {
        $userName = TestConstants::getInstance()->getUnitTestUserName();
        $mac = "02-11-00-00-00-01";
        $aaa = new AAA(TestConstants::postAuthUrlForUser($userName), "");
        $this->assertEquals(
            [
                'headers' => [
                    TestConstants::HTTP_11_NO_DATA,
                    "Content-Type: application/json",
                ],
                'body' => ''
            ],
            $aaa->processRequest()
        );
        $sessionData = $this->getSessionDataForUser($userName, $mac);
        $sessionDataFixture = [
            0 => [
                'start'               => $sessionData[0]['start'],
                'stop'                => NULL,
                'siteIP'              => "172.17.0.6",
                'username'            => $userName,
                'InMB'                => NULL,
                'OutMB'               => NULL,
                'mac'                 => $mac,
                'ap'                  => NULL,
                'building_identifier' => TestConstants::BUILDING_ID
            ]
        ];
        self::assertEquals($sessionDataFixture, $sessionData);
    }

    function testProcessPostAuthRequestRejectNormalUser() {
        $aaa = new AAA(TestConstants::postAuthUrlForUser(
            TestConstants::getInstance()->getUnitTestUserName(), TestConstants::AUTH_RESULT_REJECT), "");
        $this->assertEquals(
            [
                'headers' => [
                    TestConstants::HTTP_11_NO_DATA,
                    "Content-Type: application/json",
                ],
                'body' => ''
            ],
            $aaa->processRequest()
        );
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Request type [authenticate] is not recognized.
     */
    function testExceptionIsThrownForUnsupportedType() {
        new AAA(self::AUTHENTICATION_URL, "");
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Auth result [Access-Malformed] is not recognized.
     */
    function testExceptionIsThrownForWrongAuthResult() {
        new AAA(self::POST_AUTH_MALFORMED_URL, "");
    }

    function testAccountingStartJsonHandled() {
        $userName = TestConstants::getInstance()->getUnitTestUserName();
        $aaa = new AAA(
            TestConstants::accountingUrlForUser($userName),
            TestConstants::getAccountingJsonForType(AAA::ACCOUNTING_TYPE_START, $userName));
        $this->assertEquals(
            [
                'headers' => [
                    TestConstants::HTTP_11_NO_DATA,
                    "Content-Type: application/json",
                ],
                'body' => ''
            ],
            $aaa->processRequest()
        );
        self::assertTrue($aaa->user->validUser);
    }

    function testAccountingInterimJsonHandled() {
        $userName = TestConstants::getInstance()->getUnitTestUserName();
        $aaa = new AAA(
            TestConstants::accountingUrlForUser($userName),
            TestConstants::getAccountingJsonForType(AAA::ACCOUNTING_TYPE_INTERIM, $userName));
        $this->assertEquals(
            [
                'headers' => [
                    TestConstants::HTTP_11_NO_DATA,
                    "Content-Type: application/json",
                ],
                'body' => ''
            ],
            $aaa->processRequest()
        );
        self::assertTrue($aaa->user->validUser);
    }

    function testAccountingStopJsonHandled() {
        $userName = TestConstants::getInstance()->getUnitTestUserName();
        $aaa = new AAA(
            TestConstants::accountingUrlForUser($userName),
            TestConstants::getAccountingJsonForType(AAA::ACCOUNTING_TYPE_STOP, $userName));
        $this->assertEquals(
            [
                'headers' => [
                    TestConstants::HTTP_11_NO_DATA,
                    "Content-Type: application/json",
                ],
                'body' => ''
            ],
            $aaa->processRequest()
        );
        self::assertTrue($aaa->user->validUser);
    }

    function testAccountingOnJsonHandled() {
        $userName = TestConstants::getInstance()->getUnitTestUserName();
        $aaa = new AAA(
            TestConstants::accountingUrlForUser($userName),
            TestConstants::getAccountingJsonForType(AAA::ACCOUNTING_TYPE_ON, $userName));
        $this->assertEquals(
            [
                'headers' => [
                    TestConstants::HTTP_11_NO_DATA,
                    "Content-Type: application/json",
                ],
                'body' => ''
            ],
            $aaa->processRequest()
        );
        self::assertTrue($aaa->user->validUser);
    }

    function testAccountingOffJsonHandled() {
        $userName = TestConstants::getInstance()->getUnitTestUserName();
        $aaa = new AAA(
            TestConstants::accountingUrlForUser($userName),
            TestConstants::getAccountingJsonForType(AAA::ACCOUNTING_TYPE_OFF, $userName));
        $this->assertEquals(
            [
                'headers' => [
                    TestConstants::HTTP_11_NO_DATA,
                    "Content-Type: application/json",
                ],
                'body' => ''
            ],
            $aaa->processRequest()
        );
        self::assertTrue($aaa->user->validUser);
    }

    function testAccountingStopForNoStartJsonHandled() {
        $userName = TestConstants::getInstance()->getUnitTestUserName();
        $aaa = new AAA(
            TestConstants::accountingUrlForUser($userName),
            TestConstants::getAccountingJsonForType(AAA::ACCOUNTING_TYPE_STOP, $userName));
        $this->assertEquals(
            [
                'headers' => [
                    TestConstants::HTTP_11_NOT_FOUND,
                    "Content-Type: application/json",
                ],
                'body' => ''
            ],
            $aaa->processRequest()
        );
        self::assertTrue($aaa->user->validUser);
    }

    function testAccountingStopForInvalidUserJsonHandled() {
        $userName = "INVALI";
        $aaa = new AAA(
            TestConstants::accountingUrlForUser($userName),
            TestConstants::getAccountingJsonForType(AAA::ACCOUNTING_TYPE_START, $userName));
        $this->assertEquals(
            [
                'headers' => [
                    TestConstants::HTTP_11_NOT_FOUND,
                    "Content-Type: application/json",
                ],
                'body' => ''
            ],
            $aaa->processRequest()
        );
        self::assertFalse($aaa->user->validUser);
    }

    function testAccountingStartUpdatesSession() {
        $userName = TestConstants::getInstance()->getUnitTestUserName();
        $mac = "02-11-00-00-00-01";
        $bbb = new AAA(TestConstants::postAuthUrlForUser($userName), "");
        $bbb->processRequest();

        $aaa = new AAA(
            TestConstants::accountingUrlForUser($userName),
            TestConstants::getAccountingJsonForType(
                AAA::ACCOUNTING_TYPE_START,
                $userName,
                TestConstants::ALTERNATIVE_BUILDING_ID,
                $mac
            )
        );
        $this->assertEquals(
            [
                'headers' => [
                    TestConstants::HTTP_11_NO_DATA,
                    "Content-Type: application/json",
                ],
                'body' => ''
            ],
            $aaa->processRequest()
        );
        self::assertTrue($aaa->user->validUser);
        self::assertEquals(AAA::HTTP_RESPONSE_NO_CONTENT, $aaa->getResponseHeader());


        $sessionData = $this->getSessionDataForUser($userName, $mac);
        $sessionDataFixture = [
            0 => [
                'start'               => $sessionData[0]['start'],
                'stop'                => NULL,
                'siteIP'              => "172.17.0.6",
                'username'            => $userName,
                'InMB'                => 0,
                'OutMB'               => 0,
                'mac'                 => $mac,
                'ap'                  => "",
                'building_identifier' => TestConstants::ALTERNATIVE_BUILDING_ID
            ]
        ];
        self::assertEquals($sessionDataFixture, $sessionData);

    }

    /**
     * Retrieves the stored session from the database for the username provided.
     *
     * @param string $username
     * @param string $mac
     * @return array
     */
    private function getSessionDataForUser($username, $mac) {
        $statement = DB::getInstance()->getConnection()->prepare(
            "SELECT * FROM session WHERE username = :username AND mac = :mac ORDER BY start DESC LIMIT 1");
        $statement->bindValue(":username", $username, PDO::PARAM_STR);
        $statement->bindValue(":mac", $mac, PDO::PARAM_STR);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
