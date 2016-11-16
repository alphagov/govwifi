<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class PDFTest extends PHPUnit_Framework_TestCase {
    function testClassInstantiates() {
        $this->assertInstanceOf(PDF::class, new PDF());
    }
}
