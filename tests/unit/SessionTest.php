<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class SessionTest extends PHPUnit_Framework_TestCase {
    function testClassInstantiates() {
        $this->assertInstanceOf(Session::class, new Session("sessionID"));
    }
}
