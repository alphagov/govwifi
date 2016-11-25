<?php
namespace Alphagov\GovWifi;

use Exception;
use PHPUnit_Framework_TestCase;

class AAATest extends PHPUnit_Framework_TestCase {
    const AUTHORIZATION_URL = "/api/authorize/user/HEALTH/mac/02-00-00-00-00-01/"
        . "ap/02-00-00-42-00-01/site/172.17.0.6";
    const POST_AUTH_URL = "/api/post-auth/user/HEALTH/mac/02-00-00-00-00-01/"
        . "ap/02-00-00-42-00-01/site/172.17.0.6/result/Access-Accept";
    const AUTHENTICATION_URL = "/api/authenticate/user/HEALTH/mac/02-00-00-00-00-01/"
        . "site/172.17.0.6";

    function testClassInstantiates() {
        $this->assertInstanceOf(AAA::class, new AAA(self::AUTHORIZATION_URL));
    }

    function testAuthenticationUrlIsParsedProperly() {
        $aaa = new AAA(self::AUTHORIZATION_URL);
        $this->assertEquals(AAA::TYPE_AUTHORIZE,       $aaa->type);
        $this->assertInstanceOf(User::class,           $aaa->user);
        $this->assertEquals(Config::HEALTH_CHECK_USER, $aaa->user->login);
        $this->assertEquals("02-00-00-00-00-01",       $aaa->mac);
        $this->assertEquals("02-00-00-42-00-01",       $aaa->ap);
        $this->assertInstanceOf(Site::class,           $aaa->site);
    }

    function testPostAuthUrlIsParsedProperly() {
        $aaa = new AAA(self::POST_AUTH_URL);
        $this->assertEquals(AAA::TYPE_POST_AUTH,       $aaa->type);
        $this->assertInstanceOf(User::class,           $aaa->user);
        $this->assertEquals(Config::HEALTH_CHECK_USER, $aaa->user->login);
        $this->assertEquals("02-00-00-00-00-01",       $aaa->mac);
        $this->assertEquals("02-00-00-42-00-01",       $aaa->ap);
        $this->assertInstanceOf(Site::class,           $aaa->site);
        $this->assertEquals("Access-Accept",           $aaa->result);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Request type [authenticate] is not recognized.
     */
    function testExceptionIsThrownForUnsupportedType() {
        new AAA(self::AUTHENTICATION_URL);
    }
}
