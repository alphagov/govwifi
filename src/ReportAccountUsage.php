<?php

namespace Alphagov\GovWifi;

use DateInterval;
use DateTime;

/**
 * Sends the account usage reports to the Performance Platform.
 * Eg. number of active users per day, total, roaming and single-location.
 *
 * @package Alphagov\GovWifi
 */
class ReportAccountUsage extends PerformancePlatformReport {

    public function getMetricName() {
        return 'account-usage';
    }

    public function sendMetrics($date = null) {
        $dateObject = new DateTime();
        if (empty($date)) {
            $date = $dateObject->sub(new DateInterval('P1D'))->format('Y-m-d');
        }
        $defaults = [
            'timestamp'     => $date . 'T00:00:00+00:00'
        ];

        $sql = "SELECT count(distinct(username)) as total, "
            . "count(distinct(concat_ws('-', sessions.username, site.address))) as per_site FROM sessions "
            . "LEFT JOIN siteip ON (siteip.ip = sessions.siteIP) "
            . "LEFT JOIN site ON (siteip.site_id = site.id) WHERE "
            . "site.org_id IS NOT NULL AND date(sessions.start) = '" . $date . "' GROUP BY date(start)";

        $results = $this->runQuery($sql);
        $total   = intval($results[0]['total']);
        $perSite = intval($results[0]['per_site']);
        $roaming = $perSite - $total;

        $this->sendSimpleMetric(array_merge($defaults, [
            'extras' => [ 'type' => 'total' ],
            'data'   => [
                'count' => $total
            ]
        ]));

        $this->sendSimpleMetric(array_merge($defaults, [
            'extras' => [ 'type' => 'roaming' ],
            'data'   => [
                'count' => $roaming
            ]
        ]));

        $this->sendSimpleMetric(array_merge($defaults, [
            'extras' => [ 'type' => 'one-time' ],
            'data'   => [
                'count' => $total - $roaming
            ]
        ]));

        $this->sendSimpleMetric(array_merge($defaults, [
            'extras' => [ 'type' => 'transactions' ],
            'data'   => [
                'count' => $perSite
            ]
        ]));
    }
}
