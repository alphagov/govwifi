<?php

namespace Alphagov\GovWifi;
use DateInterval;
use DateTime;


/**
 * Sends the number of active locations to the Performance Platform.
 * Eg. The number of sites with at least 4 active users for the given day.
 *
 * @package Alphagov\GovWifi
 */
class ReportActiveLocations extends PerformancePlatformReport {
    const MINIMUM_NUMBER_OF_USERS = 4;

    public function getMetricName() {
        return 'active-locations';
    }

    public function sendMetrics($date = null) {
        $dateObject = new DateTime();
        if (empty($date)) {
            $date = $dateObject->sub(new DateInterval('P1D'))->format('Y-m-d');
        }

        $defaults = [
            'timestamp'     => $date . 'T00:00:00+00:00'
        ];

        // Deliberately not checking if the site is hidden.
        $sql = "SELECT count(1) AS `count` FROM (" .
            "SELECT count(distinct(username)) users, address FROM " .
                "sessions LEFT JOIN siteip ON (siteip.ip = sessions.siteIP) " .
                "LEFT JOIN site ON (siteip.site_id = site.id) " .
                "WHERE date(start) = '" . $date . "' GROUP BY address " .
                "HAVING users >= " . self::MINIMUM_NUMBER_OF_USERS . ") foo;";

        $this->sendSimpleMetric(array_merge($defaults, [
            'sql'    => $sql,
        ]));
    }
}
