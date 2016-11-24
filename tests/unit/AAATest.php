<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class AAATest extends PHPUnit_Framework_TestCase {
    const AUTHENTICATION_URL = "/api/authorize/user/HEALTH/mac/02-00-00-00-00-01/ap/02-00-00-42-00-01/site/172.17.0.6 HTTP/1.1";
    const POST_AUTH_URL = "";

    function testClassInstantiates() {
        $this->assertInstanceOf(AAA::class, new AAA(""));
    }

    function testAuthenticationUrlIsParsedProperly() {
        $aaa = new AAA(self::AUTHENTICATION_URL);
        $this->assertEquals("authorize",               $aaa->type);
        $this->assertInstanceOf(User::class,           $aaa->user);
        $this->assertEquals(Config::HEALTH_CHECK_USER, $aaa->user->login);
        $this->assertEquals("02-00-00-00-00-01",       $aaa->mac);
        $this->assertEquals("02-00-00-42-00-01",       $aaa->ap);
        $this->assertInstanceOf(Site::class,           $aaa->site);
    }

    function testPostAuthUrlIsParsedProperly() {

    }
}
