<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class SessionTest extends PHPUnit_Framework_TestCase {
    const SESSION_ID = "sessionID";

    function testClassInstantiates() {
        $this->assertInstanceOf(Session::class, new Session(self::SESSION_ID));
    }

    function testSessionCache() {
        $session = new Session("NoIdLikeThis");
        $sessionRecord = $session->sessionRecord();
        $session->writeToCache();
        $session->loadFromCache();
        $this->assertEquals($session->sessionRecord(), $sessionRecord);
        $this->assertEquals(false, Cache::getInstance()->itemWasNotFound());
        $session->deleteFromCache();
        $session->loadFromCache();
        $this->assertEquals(true, Cache::getInstance()->itemWasNotFound());
    }
}
