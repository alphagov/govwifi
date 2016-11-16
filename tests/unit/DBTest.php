<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class DBTest extends PHPUnit_Framework_TestCase {

    function testClassInstantiates() {
        $this->assertInstanceOf(DB::class, DB::getInstance());
    }
}
