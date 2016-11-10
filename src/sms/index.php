<?php
namespace Alphagov\GovWifi;

require "../common.php";

$smsReq = new SmsRequest();

// FireText uses source, message, and keyword
// for short numbers keyword is set to the keyword entered,
// otherwise it's a string constant.
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

if ($smsReq->sender->validMobile)
{
    $firstword = $smsReq->messageWords[0];
    error_log("*".$firstword."*");
    switch ($firstword) {
        case "security":
            $smsReq->security();
            break;
        case "new":
            $smsReq->newPassword();
            break;
        case "help":
            $smsReq->help();
            break;
        case "agree":
            $smsReq->signUp();
            break;
        default:
            if (preg_match('/^[0-9]{4}$/', $firstword)) {
                $smsReq->dailyCode();
            } else if (preg_match('/^[0-9]{6}$/', $firstword)) {
                $smsReq->verify();
            } else {
                $smsReq->other();
            }
            break;
    }
} else {
    error_log("SMS: Invalid number " . $smsReq->sender->text);
}
