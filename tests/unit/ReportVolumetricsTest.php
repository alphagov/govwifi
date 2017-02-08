<?php
namespace Alphagov\GovWifi;

use DateInterval;
use DateTime;
use PHPUnit_Framework_TestCase;

class ReportVolumetricsTest extends PHPUnit_Framework_TestCase {
    public function testVolumetrics() {
        $rv = new ReportVolumetrics(Config::getInstance(), DB::getInstance());
        /*
        for ($i = 35; $i > 4; $i--) {
            $dateObject = new DateTime();
            $rv->sendMetrics($dateObject->sub(new DateInterval('P' . $i . 'D'))->format('Y-m-d'));
        }
        */
    }
}
