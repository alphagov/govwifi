<?php
namespace Alphagov\GovWifi;

require dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "common.php";

if (! empty($_REQUEST['key']) && Config::getInstance()->values["frontendApiKey"] === $_REQUEST['key']) {
    $survey = new Survey([
        'config' => Config::getInstance(),
        'db'     => DB::getInstance()
    ]);
    $survey->sendSurveys();
} else if (! strtolower(substr(php_sapi_name(), 0, 3)) === 'cli') {
    header("HTTP/1.1 404 Not Found");
}
