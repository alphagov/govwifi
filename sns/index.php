<?php
require "../common.php";
$emailreq = new emailRequest();

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
        // TODO: See if it's sensible to check their certificate.
        $response = file_get_contents(
            $subscribeUrl,
            false,
            stream_context_create(array(
                "ssl"=>array(
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                )
            ))
        );
        if ($response === false) {
            error_log("AWS SNS SubscribeURL received, subscription FAILED. URL:"
                    . " >>>" . $subscribeUrl . "<<<");
        } else {
            error_log("AWS SNS SubscribeURL confirmed.");
        }
    }
} else {
    $pattern = "/([a-zA-Z\.\-]+@[a-zA-Z\.\-]+)/";
    preg_match($pattern,reset($data['mail']['commonHeaders']['from']),$matches);
    $emailreq->setEmailFrom($matches[0]);
    error_log("AWS SNS EMAIL: From : " . $matches[0]);
    preg_match($pattern,reset($data['mail']['commonHeaders']['to']),$matches);
    $emailreq->setEmailTo($matches[0]);
    $emailreq->setEmailSubject($data['mail']['commonHeaders']['subject']);
    $config = config::getInstance();
    $bucket = $config->values['AWS']['email-bucket'];
    $key = $data['receipt']['action']['objectKey'];

    $s3 = new Aws\S3\S3Client([
        'version' => 'latest',
        'region'  => 'eu-west-1',
        'credentials' => [
            'key'    => $config->values['AWS']['Access-keyID'],
            'secret' => $config->values['AWS']['Access-key']
            ]
        ]);
    $result = $s3->getObject(array(
        'Bucket' => $bucket,
        'Key'    => $key
    ));

    $body = $result['Body'] . "\n";
    error_log($body);
    $emailreq->setEmailBody($body);

    switch ($emailreq->emailToCMD) {
        case "enroll":
            $emailreq->enroll();
        break;
        case "enrol":
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
}

?>
