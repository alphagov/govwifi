<?php

require "../common.php";

$emailreq = new emailRequest();

if (preg_match("/^Postmark/", $_SERVER['HTTP_USER_AGENT']))
{
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    // Support for Postmark
    error_log("Postmark EMAIL: From : " . $data['FromFull']['Email']);
    $emailreq->setEmailFrom($data['FromFull']['Email']);
    $emailreq->setEmailTo($data['ToFull'][0]['Email']);
    $emailreq->setEmailSubject($data['Subject']);
    $emailreq->setEmailBody($data['TextBody']);
}

if (isset($_REQUEST['sender']))
{
    // Support for Mailgun
    error_log("Mailgun EMAIL: From : " . $_REQUEST['sender']);
    $emailreq->setEmailFrom($_REQUEST['sender']);
    $emailreq->setEmailTo($_REQUEST['recipient']);
    $emailreq->setEmailSubject($_REQUEST['subject']);
    $emailreq->setEmailBody($_REQUEST['body-plain']);
}


switch ($emailreq->emailToCMD)
{
    case "verify":
        $emailreq->verify();
        break;
    case "enrol":
    case "enroll":
        $emailreq->enroll();
        break;
    case "sponsor":
        $emailreq->sponsor();
        break;
    case "newsite":
        $emailreq->newsite();
        break;
    case "logrequest":
        $emailreq->logrequest();
        break;
}

?>
