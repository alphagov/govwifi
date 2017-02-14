<?php

namespace Alphagov\GovWifi;

use DateInterval;
use DateTime;
// TODO: Implement completion rate metrics.
class ReportCompletionRate {

    public function getMetricName() {
        return 'completion-rate';
    }

    public function sendMetrics($date = null) {
        $dateObject = new DateTime();
        if (empty($date)) {
            $date = $dateObject->sub(new DateInterval('P1D'))->format('Y-m-d');
        }

        $defaults = [
            'timestamp'    => $date . 'T00:00:00+00:00',
            'categoryName' => 'stage',
            'period'       => 'week'
        ];
    }
}
