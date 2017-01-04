<?php
namespace Alphagov\GovWifi;

require "../common.php";

$smsReq = new SmsRequest();

// FireText uses source, message, and keyword
// for short numbers keyword is set to the keyword entered,
// otherwise it's a string constant.
error_log(var_export($_REQUEST, true));
if (isset($_REQUEST['source'])) {
    $smsReq->setSender($_REQUEST['source']);
} else {
    $smsReq->setSender($_REQUEST['sender']);
}

if (isset($_REQUEST["message"])) {
    $keyword = "";
    if (!empty($_REQUEST["keyword"]) &&
        Config::FIRETEXT_EMPTY_KEYWORD != strtoupper($_REQUEST["keyword"])) {
        $keyword = $_REQUEST["keyword"];
    }
    $smsReq->setMessage(trim($keyword . " " . $_REQUEST["message"]));
} else {
    $smsReq->setMessage($_REQUEST["content"]);
}

if (!$smsReq->processRequest()) {
    error_log("SMS: Invalid number " . $smsReq->sender->text);
}
