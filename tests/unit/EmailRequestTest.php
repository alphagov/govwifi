<?php
namespace Alphagov\GovWifi;
require_once "tests/TestConstants.php";

use PHPUnit_Framework_TestCase;

class EmailRequestTest extends PHPUnit_Framework_TestCase {
    const CONTACT_NUMBER = "+447123456789";
    function testClassInstantiates() {
        $this->assertInstanceOf(EmailRequest::class, new EmailRequest());
    }

    function testContactListFromEmail() {
        $body = file_get_contents(TestConstants::FIXTURE_EMAIL_SPONSOR_MULTIPART) . "\n";
        $req = new EmailRequest();
        $req->setEmailBody($body);
        $this->assertEquals([new Identifier(self::CONTACT_NUMBER)], $req->uniqueContactList());
    }
}
