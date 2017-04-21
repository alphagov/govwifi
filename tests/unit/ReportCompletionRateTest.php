<?php

namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class ReportCompletionRateTest extends PHPUnit_Framework_TestCase {
    public function testClassInstantiates() {
        $this->assertInstanceOf(
            ReportCompletionRate::class,
            new ReportCompletionRate(Config::getInstance(), DB::getInstance())
        );
    }
}
