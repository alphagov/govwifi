<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class EmailRequestTest extends PHPUnit_Framework_TestCase {
    function testClassInstantiates() {
        $this->assertInstanceOf(EmailRequest::class, new EmailRequest());
    }
}
