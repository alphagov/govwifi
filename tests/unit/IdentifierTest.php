<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class IdentifierTest extends PHPUnit_Framework_TestCase {
    function testClassInstantiates() {
        $this->assertInstanceOf(Identifier::class, new Identifier("+447766554433"));
    }
}
