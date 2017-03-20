<?php
namespace Alphagov\GovWifi;

require "../common.php";

$smsReq = new SmsRequest(Config::getInstance());

// FireText uses source, message, and keyword
// for short numbers keyword is set to the keyword entered,
// otherwise it's a string constant.
error_log(var_export($_REQUEST, true));
$sender = "";
if (isset($_REQUEST['sender'])) {
    $sender = $_REQUEST['sender'];
} else if (isset($_REQUEST['source'])) {
    $sender = $_REQUEST['source'];
}
$smsReq->setSender($sender);

if (isset($_REQUEST["message"])) {
    $keyword = "";
    if (!empty($_REQUEST["keyword"]) &&
        Config::FIRETEXT_EMPTY_KEYWORD != strtoupper($_REQUEST["keyword"])) {
        $keyword = $_REQUEST["keyword"];
    }
    $message = "";
    error_log("SMS Empty message: " .
        var_export(! strtolower(Config::FIRETEXT_EMPTY_MESSAGE) == strtolower($_REQUEST["message"]), true));
    if (! strtolower(Config::FIRETEXT_EMPTY_MESSAGE) == strtolower($_REQUEST["message"])) {
        $message = trim($keyword . " " . $_REQUEST["message"]);
    }
    $smsReq->setMessage($message);
} else {
    $smsReq->setMessage($_REQUEST["content"]);
}

if (!$smsReq->processRequest()) {
    error_log("SMS: Invalid number " . $sender);
}
