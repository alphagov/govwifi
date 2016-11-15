<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class AAATest extends PHPUnit_Framework_TestCase {

    function testClassInstantiates() {
        $this->assertInstanceOf(AAA::class, new AAA(""));
    }
}
