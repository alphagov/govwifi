<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class EmailResponseTest extends PHPUnit_Framework_TestCase {
    function testClassInstantiates() {
        $this->assertInstanceOf(EmailResponse::class, new EmailResponse());
    }
}
