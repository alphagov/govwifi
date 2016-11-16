<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class ReportTest extends PHPUnit_Framework_TestCase {
    function testClassInstantiates() {
        $this->assertInstanceOf(Report::class, new Report());
    }
}
