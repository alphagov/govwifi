<?php

namespace Alphagov\GovWifi;

use DateInterval;
use DateTime;

/**
 * Sends the volumetric reports to the Performance Platform.
 * Eg. number of sign-ups per channel and total.
 *
 * @package Alphagov\GovWifi
 */
class ReportVolumetrics extends PerformancePlatformReport {

    public function getMetricName() {
        return 'volumetrics';
    }

    public function sendMetrics($date = null) {
        $dateObject = new DateTime();
        if (empty($date)) {
            $date = $dateObject->sub(new DateInterval('P1D'))->format('Y-m-d');
        }
        $defaults = [
            'timestamp'     => $date . 'T00:00:00+00:00'
        ];

        // Number of sign ups per day and total.
        $totalSql = [
            "SELECT count(username) AS count FROM userdetails WHERE date(created_at) = '" . $date . "'",
            "SELECT count(username) AS cumulative_count FROM userdetails WHERE date(created_at) <= '" . $date . "'"
        ];
        $this->sendSimpleMetric(array_merge($defaults, [
            'extras' => [ 'channel' => 'all-sign-ups' ],
            'sql'    => $totalSql,
        ]));

        // SMS sign ups per day and total.
        $smsCondition = "contact LIKE '+%' " .
            "AND userdetails.contact = userdetails.sponsor AND date(created_at) <= '" . $date . "'";
        $smsSql = [
            "SELECT count(username) AS count FROM userdetails WHERE date(created_at) = '" . $date . "' AND " .
            $smsCondition,
            "SELECT count(username) AS cumulative_count FROM userdetails WHERE " . $smsCondition .
            " AND date(created_at) <= '" . $date . "'"
        ];
        $this->sendSimpleMetric(array_merge($defaults, [
            'extras' => [ 'channel' => 'sms-sign-ups' ],
            'sql'    => $smsSql,
        ]));

        // Email self-sign ups per day and total.
        $emailCondition = "contact LIKE '%@%' " .
            "AND userdetails.contact = userdetails.sponsor";
        $emailSql = [
            "SELECT count(username) AS count FROM userdetails WHERE date(created_at) = '" . $date . "' AND " .
            $emailCondition,
            "SELECT count(username) AS cumulative_count FROM userdetails WHERE " . $emailCondition .
            " AND date(created_at) <= '" . $date . "'"
        ];
        $this->sendSimpleMetric(array_merge($defaults, [
            'extras' => [ 'channel' => 'email-sign-ups' ],
            'sql'    => $emailSql,
        ]));

        // Sponsored sign-ups per day and total
        $sponsorCondition = "userdetails.contact != userdetails.sponsor";
        $sponsorSql = [
            "SELECT count(username) AS count FROM userdetails WHERE date(created_at) = '" . $date . "' AND " .
            $emailCondition,
            "SELECT count(username) AS cumulative_count FROM userdetails WHERE " . $sponsorCondition .
            " AND date(created_at) <= '" . $date . "'"
        ];
        $this->sendSimpleMetric(array_merge($defaults, [
            'extras' => [ 'channel' => 'sponsor-sign-ups' ],
            'sql'    => $sponsorSql,
        ]));
    }
}
