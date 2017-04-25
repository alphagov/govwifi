<?php
namespace Alphagov\GovWifi;

require "../common.php";

$jsonData = file_get_contents('php://input');
$aaa = new AAA($_SERVER['SCRIPT_NAME'], $jsonData);
$response = $aaa->processRequest();

if (!empty($response['headers']) && is_array($response['headers'])) {
    foreach ($response['headers'] as $header) {
        header($header);
    }
}
if (! empty($response['body'])) {
    print $response['body'];
}
