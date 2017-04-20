<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class PerformancePlatformClientTest extends PHPUnit_Framework_TestCase {
    public function testClassInstantiates() {
        $this->assertInstanceOf(
            PerformancePlatformClient::class,
            new PerformancePlatformClient([
                'serviceName' => "test",
                'baseUrl'     => "https://test.com"
            ])
        );
    }
}

