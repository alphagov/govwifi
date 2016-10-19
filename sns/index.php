<?php
require "../common.php";
$emailreq = new emailRequest();

$json = file_get_contents('php://input');
$data = json_decode($json, true);
// TODO(afoldesi-gds): Check if there is a way to check authentication to make
// sure that we are talking to the right service.
if (isset($data['SubscribeURL'])) {
    file_get_contents($data['SubscribeURL']);
    error_log("AWS SNS SubscribeURL confirmed");
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
