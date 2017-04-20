<?php
namespace Alphagov\GovWifi;

require dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "common.php";

use DateInterval;
use DateTime;

if (!empty($_REQUEST['key']) && Config::getInstance()->values["frontendApiKey"] == $_REQUEST['key']) {

    $reportVolumetrics = new ReportVolumetrics(Config::getInstance(), DB::getInstance());

    if (!empty($_REQUEST['days']) && is_numeric($_REQUEST['days'])) {
        for ($i = $_REQUEST['days']; $i >= 1; $i--) {
            $dateObject = new DateTime();
            $reportVolumetrics->sendMetrics(
                $dateObject->sub(new DateInterval('P' . intval($i). 'D'))->format('Y-m-d'));
        }
    } else {
        $reportVolumetrics->sendMetrics();
    }
} else {
    header("HTTP/1.1 404 Not Found");
}
