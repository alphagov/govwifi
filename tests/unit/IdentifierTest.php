<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class IdentifierTest extends PHPUnit_Framework_TestCase {
    const SHORT_CODE = "73337";
    const ITL_MOBILE = "+447766554433";
    const UK_MOBILE = "07766554433";
    const ITL_MOBILE_WITH_SPACES = "+44 7766 554 433";
    const UK_MOBILE_WITH_SPACES = "077 6655 4433";
    const EMAIL = "test.EMAILaddress@test.DOMAIN.com";
    const EMAIL_LOWERCASE = "test.emailaddress@test.domain.com";

    function testClassInstantiates() {
        $this->assertInstanceOf(Identifier::class, new Identifier(self::ITL_MOBILE));
    }

    function testInternationalMobileNumberIsRecognized() {
        $identifier = new Identifier(self::ITL_MOBILE);
        $this->assertTrue($identifier->validMobile);
        $this->assertFalse($identifier->validEmail);
    }

    function testUKMobileNumberIsRecognized() {
        $identifier = new Identifier(self::UK_MOBILE);
        $this->assertTrue($identifier->validMobile);
        $this->assertFalse($identifier->validEmail);
    }

    function testInternationalMobileNumberWithSpacesIsRecognized() {
        $identifier = new Identifier(self::ITL_MOBILE_WITH_SPACES);
        $this->assertTrue($identifier->validMobile);
        $this->assertFalse($identifier->validEmail);
    }

    function testUKMobileNumberWithSpacesIsRecognized() {
        $identifier = new Identifier(self::UK_MOBILE_WITH_SPACES);
        $this->assertTrue($identifier->validMobile);
        $this->assertFalse($identifier->validEmail);
    }

    function testUKMobileNumberConverted() {
        $identifier = new Identifier(self::UK_MOBILE);
        $this->assertEquals(self::ITL_MOBILE, $identifier->text);
    }

    function testShortCodeIsRecognized() {
        $identifier = new Identifier(self::SHORT_CODE);
        $this->assertTrue($identifier->validMobile);
        $this->assertFalse($identifier->validEmail);
    }

    function testShortCodeIsNotMangled() {
        $identifier = new Identifier(self::SHORT_CODE);
        $this->assertEquals(self::SHORT_CODE, $identifier->text);
    }

    function testEmailAddressIsRecognized() {
        $identifier = new Identifier(self::EMAIL);
        $this->assertTrue($identifier->validEmail);
        $this->assertFalse($identifier->validMobile);
    }

    function testEmailAddressIsLowerCased() {
        $identifier = new Identifier(self::EMAIL);
        $this->assertEquals(self::EMAIL_LOWERCASE, $identifier->text);
    }

    function testHealthCheckIdentifier() {
        $identifier = new Identifier(Config::HEALTH_CHECK_USER);
        $this->assertFalse($identifier->validMobile);
        $this->assertFalse($identifier->validEmail);
        $this->assertEquals(Config::HEALTH_CHECK_USER, $identifier->text);
    }
}
