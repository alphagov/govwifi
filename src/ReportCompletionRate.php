<?php

namespace Alphagov\GovWifi;

use DateInterval;
use DateTime;

/**
 * Sends the registration process completion rate details to
 * the Performance Platform.
 *
 * Data is sent weekly, for registrations between 14 and 7 days ago,
 * to allow for pre-registered users to complete the process.
 *
 * @package Alphagov\GovWifi
 */
class ReportCompletionRate extends PerformancePlatformReport {

    public function getMetricName() {
        return 'completion-rate';
    }

    public function sendMetrics($date = null) {
        $dateObject = new DateTime();
        if (empty($date)) {
            $date = $dateObject->sub(new DateInterval('P1D'))->format('Y-m-d');
        } else {
            $dateObject = new DateTime($date);
        }

        // The date object retains it's state - so these will be 7 and 14 days before the date provided.
        $endDate = $dateObject->sub(new DateInterval('P7D'))->format('Y-m-d');
        $startDate = $dateObject->sub(new DateInterval('P7D'))->format('Y-m-d');

        $defaults = [
            'timestamp'    => $date . 'T00:00:00+00:00',
            'categoryName' => 'stage',
            'period'       => 'week'
        ];

        $smsCondition = "contact LIKE '+%' AND userdetails.contact = userdetails.sponsor";
        // Number of registered users
        $this->sendSimpleMetric(array_merge($defaults, [
            'categoryValue' => 'start',
            'sql' => "SELECT count(username) AS count FROM userdetails "
                . "WHERE date(created_at) BETWEEN '" . $startDate . "' AND '" . $endDate . "'"
        ]));

        // Number of users successfully logged in
        $this->sendSimpleMetric(array_merge($defaults, [
            'categoryValue' => 'complete',
            'sql' => "SELECT count(distinct(userdetails.username)) AS count FROM "
                . "userdetails LEFT JOIN session ON (userdetails.username = session.username) "
                . "WHERE date(userdetails.created_at) BETWEEN '" . $startDate . "' AND '" . $endDate . "' "
                . "AND session.username IS NOT NULL"
        ]));
    }
}
