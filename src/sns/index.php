<?php
namespace Alphagov\GovWifi;

require "../common.php";

$emailRequest = new EmailRequest([
    'emailProvider' => EmailProviderFactory::create(
        Config::getInstance(),
        file_get_contents('php://input')
    ),
    'config'        => Config::getInstance(),
    'db'            => DB::getInstance(),
    'cache'         => Cache::getInstance(),
]);

$emailRequest->processRequest();
