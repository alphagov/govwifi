<?php
namespace Alphagov\GovWifi;

require dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "common.php";

use DateInterval;
use DateTime;

if (! empty($_REQUEST['key']) && Config::getInstance()->values["frontendApiKey"] === $_REQUEST['key']) {
    $period = "daily";
    if (! empty($_REQUEST['period'])) {
        $period = $_REQUEST['period'];
    }

    switch ($period) {
        case "daily":
            $reportVolumetrics = new ReportVolumetrics(Config::getInstance(), DB::getInstance());

            if (! empty($_REQUEST['days']) && is_numeric($_REQUEST['days'])) {
                for ($i = intval($_REQUEST['days']); $i >= 1; $i--) {
                    $dateObject = new DateTime();
                    $reportVolumetrics->sendMetrics(
                        $dateObject->sub(new DateInterval('P' . $i. 'D'))->format('Y-m-d'));
                }
            } else {
                $reportVolumetrics->sendMetrics();
            }
            break;
        case "weekly":
            $reportCompletionRate = new ReportCompletionRate(Config::getInstance(), DB::getInstance());
            if (! empty($_REQUEST['date'])) {
                $reportCompletionRate->sendMetrics($_REQUEST['date']);
            } else {
                $reportCompletionRate->sendMetrics();
            }
            break;
        case "monthly":
            break;
    }
} else {
    header("HTTP/1.1 404 Not Found");
}
