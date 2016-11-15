<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class PDFTest extends PHPUnit_Framework_TestCase {
    function testClassInstantiates() {
        self::assertInstanceOf(PDF::class, new PDF());
    }
}
