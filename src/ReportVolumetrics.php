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
            'timestamp'     => $date . 'T00:00:00+00:00',
            'categoryName'  => 'channel',
        ];

        // Number of sign ups per day and total.
        $sql = [
            "SELECT count(username) AS count FROM userdetails WHERE date(created_at) = '" . $date . "'",
            "SELECT count(username) AS cumulative_count FROM userdetails WHERE date(created_at) <= '" . $date . "'"
        ];
        $this->sendSimpleMetric(array_merge($defaults, [
            'categoryValue' => 'all-sign-ups',
            'sql'           => $sql,
        ]));

        // SMS sign ups per day and total.
        $smsCondition = "contact LIKE '+%' " .
            "AND userdetails.contact = userdetails.sponsor AND date(created_at) <= '" . $date . "'";
        $sql = [
            "SELECT count(username) AS count FROM userdetails WHERE date(created_at) = '" . $date . "' AND " .
            $smsCondition,
            "SELECT count(username) AS cumulative_count FROM userdetails WHERE " . $smsCondition .
            " AND date(created_at) <= '" . $date . "'"
        ];
        $this->sendSimpleMetric(array_merge($defaults, [
            'categoryValue' => 'sms-sign-ups',
            'sql'           => $sql,
        ]));

        // Email self-sign ups per day and total.
        $emailCondition = "contact LIKE '%@%' " .
            "AND userdetails.contact = userdetails.sponsor";
        $sql = [
            "SELECT count(username) AS count FROM userdetails WHERE date(created_at) = '" . $date . "' AND " .
            $emailCondition,
            "SELECT count(username) AS cumulative_count FROM userdetails WHERE " . $emailCondition .
            " AND date(created_at) <= '" . $date . "'"
        ];
        $this->sendSimpleMetric(array_merge($defaults, [
            'categoryValue' => 'email-sign-ups',
            'sql'           => $sql,
        ]));

        // Sponsored sign-ups per day and total
        $emailCondition = "userdetails.contact != userdetails.sponsor";
        $sql = [
            "SELECT count(username) AS count FROM userdetails WHERE date(created_at) = '" . $date . "' AND " .
            $emailCondition,
            "SELECT count(username) AS cumulative_count FROM userdetails WHERE " . $emailCondition .
            " AND date(created_at) <= '" . $date . "'"
        ];
        $this->sendSimpleMetric(array_merge($defaults, [
            'categoryValue' => 'sponsor-sign-ups',
            'sql'           => $sql,
        ]));
    }
}
