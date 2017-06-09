<?php

namespace Alphagov\GovWifi;
use DateInterval;
use DateTime;


/**
 * Sends the average number of unique users per working day to the
 * Performance Platform.
 *
 * The script is expected to run on Sundays - with data for the previous
 * month overwritten.
 *
 * @package Alphagov\GovWifi
 */
class ReportUniqueUsers extends PerformancePlatformReport {

    public function getMetricName() {
        return 'unique-users';
    }

    public function sendMetrics($date = null) {
        $dateObject = new DateTime();
        if (! empty($date)) {
            $dateObject = new DateTime($date);
        }
        $today = $dateObject->format('Y-m-d');

        $perWeekSql = "SELECT sum(users)/count(*) DIV 1 as `count` FROM (" .
                "SELECT date(start) AS day, count(distinct(username)) AS users FROM session " .
                "WHERE start BETWEEN " .
                "date_sub('" . $today . "', INTERVAL 7 DAY) AND '" . $today . "' ".
                "AND dayofweek(start) NOT IN (1,7) GROUP BY day" .
            ") foo;";
        $this->sendSimpleMetric([
            'timestamp' => $today . 'T00:00:00+00:00',
            'sql'       => $perWeekSql,
            'period'    => 'week' //TODO: refactor accepted PP periods to constants.
        ]);

        $lastMonthStart = (new DateTime($today))->sub(new DateInterval('P1M'))->format('Y-m-01');
        $thisMonthStart = (new DateTime($today))->format('Y-m-01');
        $perMonthSql = "SELECT sum(users)/count(*) DIV 1 as `month-count` FROM (" .
                "SELECT date(start) AS day, count(distinct(username)) AS users FROM session " .
                "WHERE start BETWEEN '" . $lastMonthStart . "' AND '" . $thisMonthStart . "' ".
                "AND dayofweek(start) NOT IN (1,7) GROUP BY day" .
            ") foo;";
        $this->sendSimpleMetric([
            'timestamp' => $lastMonthStart . 'T00:00:00+00:00',
            'sql'       => $perMonthSql,
            'period'    => 'month'
        ]);
    }
}
