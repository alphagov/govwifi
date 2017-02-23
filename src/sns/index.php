<?php
namespace Alphagov\GovWifi;

use Aws\S3\S3Client;

require "../common.php";
$emailreq = new EmailRequest();

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (isset($data['SubscribeURL'])) {
    // We need to replace the param separators in the url, otherwise the
    // request will fail with status 400 - Bad Request.
    $subscribeUrl = str_replace("&amp;", "&", $data['SubscribeURL']);
    $urlRegex = "/^https:\/\/sns\.[^.]+\.amazonaws.com\/.+/";
    if (preg_match($urlRegex, $subscribeUrl) !== 1) {
        error_log("AWS SNS SubscribeURL received, but doesn't appear to be "
                . "pointing to the expected service! URL: "
                . ">>>" . $subscribeUrl . "<<<");
    } else {
        $response = file_get_contents($subscribeUrl);
        if ($response === false) {
            error_log("AWS SNS SubscribeURL received, subscription FAILED. URL:"
                    . " >>>" . $subscribeUrl . "<<<");
        } else {
            error_log("AWS SNS SubscribeURL confirmed.");
        }
    }
} else if (!isset($data['Message'])) {
    error_log("AWS SNS - empty data received.");
} else {
    error_log("EMAIL original message metadata: " . $data['Message']);
    $message = json_decode($data['Message'], true);
    $emailPattern = "/([a-zA-Z0-9_\.\-]+@[a-zA-Z0-9_\.\-]+)/";

    preg_match(
        $emailPattern,
        reset($message['mail']['commonHeaders']['from']),
        $fromMatches);
    $emailFrom = $fromMatches[0];
    // TODO: extend validation, fail fast, log.
    $emailreq->setEmailFrom($emailFrom);

    preg_match(
        "/^([^<]+)/",
        reset($message['mail']['commonHeaders']['from']),
        $nameMatches);
    $senderName = trim($nameMatches[0]);
    $emailreq->senderName = $senderName;
    error_log("AWS SNS EMAIL: From : " . $emailFrom . ", Sender name: [" . $senderName . "]");

    preg_match(
        $emailPattern,
        reset($message['mail']['commonHeaders']['to']),
        $toMatches);
    $destination = $toMatches[0];
    error_log("AWS SNS EMAIL: To : " . $destination);
    $emailreq->setEmailTo($destination);
    $emailreq->setEmailSubject($message['mail']['commonHeaders']['subject']);
    $bucket = $message['receipt']['action']['bucketName'];
    $key = $message['receipt']['action']['objectKey'];

    $config = Config::getInstance();
    $s3 = new S3Client(array(
        'version' => 'latest',
        'region'  => 'eu-west-1',
        'credentials' => array(
            'key'    => $config->values['AWS']['Access-keyID'],
            'secret' => $config->values['AWS']['Access-key']
        )
    ));
    $result = $s3->getObject(array(
        'Bucket' => $bucket,
        'Key'    => $key
    ));

    $body = $result['Body'] . "\n";
    error_log("EMAIL body: " . $body);
    $emailreq->setEmailBody($body);

    switch ($emailreq->emailToCMD) {
        case "enroll":
        case "enrol":
        case "signup":
            $emailreq->signUp();
            break;
        case "verify":
            $emailreq->verify();
            break;
        case "sponsor":
            $emailreq->sponsor();
            break;
        case "newsite":
            $emailreq->newSite();
            break;
        case "logrequest":
            $emailreq->logRequest();
            break;
        default:
            error_log("AWS SNS EMAIL: No command found. Have we been cc'd?");
            break;
    }
}
