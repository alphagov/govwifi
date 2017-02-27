<?php
namespace Alphagov\GovWifi;

use Aws\S3\S3Client;

/**
 * Email Provider implementation to handle AWS SES emails.
 * Has a pre-processing step that handles automatic subscription to the AWS SNS notification and handles further
 * SNS notifications received every time an email is received.
 *
 * @package Alphagov\GovWifi
 */
class SnsEmailProvider extends GovWifiBase implements EmailProvider {
    /**
     * @var array The decoded JSON data received in the SES request.
     */
    private $data;

    /**
     * @var array The email message received.
     */
    private $message;

    /**
     * The AWS S3 Client.
     * @var S3Client
     */
    private $s3Client;

    /**
     * The name of the s3 bucket where the full email is stored.
     * @var string
     */
    private $bucketName;

    /**
     * The object key identifying the email in the S3 bucket.
     * @var string
     */
    private $objectKey;

    /**
     * SnsEmailProvider constructor. Required params: the JSON encoded request received from SNS and an instance
     * of the environment-specific configuration.
     *
     * @param array $params
     */
    public function __construct($params) {
        $defaults = [
            'jsonData' => null,
            's3Client' => null,
        ];
        $params = array_merge($defaults, $params);
        parent::checkNotEmpty(array_keys($defaults), $params);

        $this->data = json_decode($params['jsonData']);

        if (null === $this->data) {
            throw new GovWifiException("JSON decoding failed.");
        }
        if (isset($this->data['Message'])) {
            $this->message    = json_decode($this->data['Message'], true);
            if (null === $this->message) {
                throw new GovWifiException("JSON decoding failed.");
            }
            $this->bucketName = $this->message['receipt']['action']['bucketName'];
            $this->objectKey  = $this->message['receipt']['action']['objectKey'];
        }
        $this->s3Client = $params['s3Client'];
    }

    /**
     * Pre-process the request received - send an HTTP GET request to the subscription url if received in the data.
     * No further processing is needed in this case.
     *
     * @return bool Whether or not further processing is required.
     * @throws GovWifiException If there was no subscription url, yet the message is not set in the data.
     */
    public function preProcess() {
        if (isset($this->data['SubscribeURL'])) {
            // We need to replace the param separators in the url, otherwise the
            // request will fail with status 400 - Bad Request.
            $subscribeUrl = str_replace("&amp;", "&", $this->data['SubscribeURL']);
            $urlRegex = "/^https:\/\/sns\.[^.]+\.amazonaws.com\/.+/";
            if (preg_match($urlRegex, $subscribeUrl) !== 1) {
                error_log("AWS SNS SubscribeURL received, but doesn't appear to be "
                    . "pointing to the expected service! URL: "
                    . ">>>" . $subscribeUrl . "<<<");
            } else {
                // HTTP GET request.
                $response = file_get_contents($subscribeUrl);
                if (false === $response) {
                    error_log("AWS SNS SubscribeURL received, subscription FAILED. URL:"
                        . " >>>" . $subscribeUrl . "<<<");
                } else {
                    error_log("AWS SNS SubscribeURL confirmed.");
                }
            }
            return false;
        } else if (!isset($this->data['Message'])) {
            throw new GovWifiException("AWS SNS - empty data received.");
        } else {
            error_log("EMAIL original message metadata: " . $this->data['Message']);
            return true;
        }
    }

    /**
     * Extract the "from" email address from the message received.
     * @return string
     */
    public function getEmailFrom() {
        preg_match(
            EmailProvider::EMAIL_REGEX,
            reset($this->message['mail']['commonHeaders']['from']),
            $fromMatches);
        return $fromMatches[0];
    }
    /**
     * Extract the name of the sender from the message received.
     * @return string
     */
    public function getSenderName() {
        preg_match(
            "/^([^<]+)/",
            reset($this->message['mail']['commonHeaders']['from']),
            $nameMatches);
        return trim($nameMatches[0]);
    }

    /**
     * Extract the "to" email address from the message.
     * @return string
     */
    public function getEmailTo() {
        preg_match(
            EmailProvider::EMAIL_REGEX,
            reset($this->message['mail']['commonHeaders']['to']),
            $toMatches);
        return $toMatches[0];
    }

    /**
     * Extract the subject of the email from the message.
     * @return string
     */
    public function getEmailSubject() {
        return $this->message['mail']['commonHeaders']['subject'];
    }

    /**
     * Retrieves the body of the email from the S3 bucket where it was stored.
     *
     * @return string The full Mime-encoded email. (!) TODO: Consider pulling MIME logic over, return body only.
     */
    public function getEmailBody() {
        $result = $this->s3Client->getObject([
            'Bucket' => $this->bucketName,
            'Key'    => $this->objectKey,
        ]);

        $body = $result['Body'] . "\n";
        error_log("EMAIL body: " . $body);
        return $body;
    }
}
