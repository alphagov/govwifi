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
        // Empty AP value.
        $this->assertEquals("-----",                   $aaa->getAp());
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
        self::assertEquals(array(), $this->getSessionDataForUser(Config::HEALTH_CHECK_USER));
    }

    function testProcessPostAuthRequestNormalUser() {
        $userName = TestConstants::getInstance()->getUnitTestUserName();
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
        $sessionData = $this->getSessionDataForUser($userName);
        $sessionDataFixture = [
            0 => [
                'start'    => $sessionData[0]['start'],
                'stop'     => NULL,
                'siteIP'   => "172.17.0.6",
                'username' => $userName,
                'InMB'     => NULL,
                'OutMB'    => NULL,
                'mac'      => "02-11-00-00-00-01",
                'ap'       => "-----"
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

    /**
     * Retrieves the stored session from the database for the username provided.
     *
     * @param $username
     * @return array
     */
    private function getSessionDataForUser($username) {
        $statement = DB::getInstance()->getConnection()->prepare(
            "SELECT * FROM session WHERE username = :username ORDER BY start DESC LIMIT 1");
        $statement->bindValue(
            ":username",
            $username,
            PDO::PARAM_STR
        );
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
