<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class CacheTest extends PHPUnit_Framework_TestCase {
    const KEY = "testSessionID";
    const DATA = array("test1" => "val1", "test2" => 42);

    function testClassInstantiates() {
        $this->assertInstanceOf(Cache::class, Cache::getInstance());
    }

    function testValueRetrieval() {
        $cache = Cache::getInstance();
        $this->assertTrue($cache->set(self::KEY, self::DATA));
        $this->assertEquals(self::DATA, $cache->get(self::KEY));
    }

    function testValueRemoval() {
        $cache = Cache::getInstance();
        $this->assertTrue($cache->set(self::KEY, self::DATA));
        $this->assertTrue($cache->delete(self::KEY));
        $this->assertFalse($cache->get(self::KEY));
        $this->assertTrue($cache->itemWasNotFound());
    }

    function testValueExpiry() {
        $cache = Cache::getInstance();
        $this->assertTrue($cache->set(self::KEY, self::DATA, 1));
        sleep(2);
        $this->assertFalse($cache->get(self::KEY));
        $this->assertTrue($cache->itemWasNotFound());
    }
}
