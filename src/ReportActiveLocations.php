<?php

namespace Alphagov\GovWifi;
use DateInterval;
use DateTime;


/**
 * Sends the number of active locations to the Performance Platform.
 * Eg. The number of sites with at least 5 active users for the given day.
 *
 * @package Alphagov\GovWifi
 */
class ReportActiveLocations extends PerformancePlatformReport {

    public function getMetricName() {
        return 'active-locations';
    }

    public function sendMetrics($date = null) {
        $dateObject = new DateTime();
        if (empty($date)) {
            $date = $dateObject->sub(new DateInterval('P1D'))->format('Y-m-d');
        }
    }
}
