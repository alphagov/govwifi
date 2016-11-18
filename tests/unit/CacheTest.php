<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class CacheTest extends PHPUnit_Framework_TestCase {
    function testClassInstantiates() {
        $this->assertInstanceOf(Cache::class, Cache::getInstance());
    }
}
