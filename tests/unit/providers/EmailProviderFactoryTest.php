<?php

namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class EmailProviderFactoryTest extends PHPUnit_Framework_TestCase {
    public function testEmailProviderFactory() {
        $emailProvider = EmailProviderFactory::create(Config::getInstance(), TestConstants::EMPTY_SNS_JSON);
        self::assertInstanceOf(SnsEmailProvider::class, $emailProvider);
    }
}
