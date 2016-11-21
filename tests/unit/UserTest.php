<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class UserTest extends PHPUnit_Framework_TestCase {
    function testClassInstantiates() {
        $this->assertInstanceOf(User::class, new User(Cache::getInstance()));
    }
}
