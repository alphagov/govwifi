<?php

namespace Alphagov\GovWifi;
use DateInterval;
use DateTime;


/**
 * Sends the number of unique users to the Performance Platform.
 * This is different from the the number of transactions, as the period
 * used here is week and month - which helps to compare with the number
 * of registrations.
 *
 * The script is expected to run on Mondays - with data for the previous
 * month overwritten.
 *
 * @package Alphagov\GovWifi
 */
class ReportUniqueUsers extends PerformancePlatformReport {

    public function getMetricName() {
        return 'unique-users';
    }

    public function sendMetrics($date = null) {
        $dateObject = (new DateTime())->sub(new DateInterval('P1D'));
        if (! empty($date)) {
            $dateObject = new DateTime($date);
        }
        $yesterday = $dateObject->format('Y-m-d');
        $today = $dateObject->add(new DateInterval('P1D'))->format('Y-m-d');

        $perWeekSql = "SELECT count(distinct(username)) FROM session " .
            "WHERE start BETWEEN " .
            "date_sub('" . $yesterday . "', INTERVAL 7 DAY) AND '" . $today . "';";
        $this->sendSimpleMetric([
            'timestamp' => $today . 'T00:00:00+00:00',
            'sql'       => $perWeekSql,
            'period'    => 'week' //TODO: refactor accepted PP periods to constants.
        ]);

        $lastMonthStart = (new DateTime($yesterday))->sub(new DateInterval('P1M'))->format('Y-m-01');
        $thisMonthStart = (new DateTime($yesterday))->format('Y-m-01');
        $perMonthSql = "SELECT count(distinct(username)) FROM session " .
            "WHERE start BETWEEN '" . $lastMonthStart . "' AND '" . $thisMonthStart . "';";
        $this->sendSimpleMetric([
            'timestamp' => $thisMonthStart . 'T00:00:00+00:00',
            'sql'       => $perMonthSql,
            'period'    => 'month'
        ]);
    }
}
