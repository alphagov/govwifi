<?php
namespace Alphagov\GovWifi;

require "../../common.php";


if (Config::getInstance()->values["frontendApiKey"] == $_REQUEST['key']) {
    $survey = new Survey([
        'config' => Config::getInstance(),
        'db'     => DB::getInstance()
    ]);
    $survey->sendSurveys();
}
