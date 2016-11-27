<?php
namespace Alphagov\GovWifi;

require "../common.php";

$jsonData = file_get_contents('php://input');
$aaa = new AAA($_SERVER['SCRIPT_NAME'], $jsonData);
$aaa->processRequest();
// TODO: This is just to gather examples for automated testing. Remove when done.
error_log("Request JSON: >>>>" . $jsonData . "<<<<");

header($_SERVER["SERVER_PROTOCOL"].' '.$aaa->responseHeader);
header("Content-Type: application/json");

print $aaa->responseBody;
