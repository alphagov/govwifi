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
            $reportAccountUsage = new ReportAccountUsage(Config::getInstance(), DB::getInstance());
            $reportActiveLocations = new ReportActiveLocations(Config::getInstance(), DB::getInstance());

            if (! empty($_REQUEST['days']) && is_numeric($_REQUEST['days'])) {
                for ($i = intval($_REQUEST['days']); $i >= 1; $i--) {
                    $dateObject = new DateTime();
                    $reportDate = $dateObject->sub(new DateInterval('P' . $i. 'D'))->format('Y-m-d');
                    // $reportVolumetrics->sendMetrics($reportDate); - report initialised.
                    // $reportAccountUsage->sendMetrics($reportDate);
                    $reportActiveLocations->sendMetrics($reportDate);
                }
            } else {
                $reportVolumetrics->sendMetrics();
                $reportAccountUsage->sendMetrics();
                $reportActiveLocations->sendMetrics();
            }
            break;
        case "weekly":
            $reportCompletionRate = new ReportCompletionRate(Config::getInstance(), DB::getInstance());
            $reportUniqueUsers = new ReportUniqueUsers(Config::getInstance(), DB::getInstance());
            if (! empty($_REQUEST['date'])) {
                // $reportCompletionRate->sendMetrics($_REQUEST['date']);
                $reportUniqueUsers->sendMetrics($_REQUEST['date']);
            } else {
                $reportCompletionRate->sendMetrics();
                $reportUniqueUsers->sendMetrics();
            }
            break;
        case "monthly":
            break;
    }
} else if (! strtolower(substr(php_sapi_name(), 0, 3)) === 'cli') {
    header("HTTP/1.1 404 Not Found");
}
