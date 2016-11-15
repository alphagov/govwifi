<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class DBTest extends PHPUnit_Framework_TestCase {

    function setUp() {
        putenv("DB_HOSTNAME=localhost");
        putenv("DB_USER=test");
        putenv("DB_PASS=test");
        putenv("DB_NAME=test");
    }

    function testClassInstantiates() {
        $this->assertInstanceOf(DB::class, DB::getInstance());
    }
}
