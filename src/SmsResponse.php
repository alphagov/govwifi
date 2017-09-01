<?php
namespace Alphagov\GovWifi;

use Alphagov\Notifications\Client as NotifyClient;
use Alphagov\Notifications\Exception\NotifyException;
use Http\Adapter\Guzzle6\Client as Guzzle6Client;

class SmsResponse {
    private $from;
    private $destinationNumber;
    public $template;
    public $personalisation;

    public function __construct($destinationNumber) {
        $this->setNoReply();
        $this->destinationNumber = $destinationNumber;
        $this->personalisation   = array();
    }

    public function setReply() {
        $config = Config::getInstance();
        $this->from = $config->values['reply-sender'];
    }

    public function setNoReply() {
        $config = Config::getInstance();
        $this->from = $config->values['noreply-sender'];
    }

    private function send() {
        $config = Config::getInstance();
        $notifyClient = new NotifyClient([
            'apiKey'        => $config->values['notify']['apiKey'],
            'httpClient'    => new Guzzle6Client]);

        try {
            $response = $notifyClient->sendSms(
                    $this->destinationNumber,
                    $this->template,
                    $this->personalisation);
            error_log("SMS sent. " . json_encode($response));
        } catch (NotifyException $e) {
            error_log("Exception from Notify: (" . $this->destinationNumber. ") [" . $e->getCode(). "] " .
                $e->getMessage());
        }
    }

    public function sendNewsitePassword($pdf) {
        $config = Config::getInstance();
        $this->personalisation['PASSWORD'] = $pdf->password;
        $this->personalisation['FILENAME'] = $pdf->filename;
        $this->template=$config->values['notify']['newsite-password'];
        $this->send();
    }

    public function sendLogRequestPassword($pdf) {
        $config = Config::getInstance();
        $this->personalisation['PASSWORD'] = $pdf->password;
        $this->personalisation['FILENAME'] = $pdf->filename;
        $this->template=$config->values['notify']['logrequest-password'];
        $this->send();
    }

    public function sendCredentials($user, $message = "", $journey = SmsRequest::SMS_JOURNEY_TERMS) {
        $template = $this->getTemplateForOs($message, $journey);
        error_log("Using template [" . $template . "] for message [" . $message . "]");
        $this->template = $template;
        if (SmsRequest::SMS_JOURNEY_TERMS == $journey || empty($message)) {
            $this->personalisation['LOGIN'] = $user->login;
            $this->personalisation['PASS'] = $user->password;
        }
        $this->send();
    }

    public function sendHelp($journey) {
        $config = Config::getInstance();
        $this->personalisation['KEYWORD'] = $config->values['reply-keyword'];
        $this->template = $config->values['notify'][$journey . 'help'];
        $this->send();
    }

    public function sendTerms() {
        $config = Config::getInstance();
        $this->personalisation['KEYWORD'] = $config->values['reply-keyword'];
        $this->template = $config->values['notify']['terms'];
        $this->send();
    }

    public function sendSecurityInfo() {
        $config = Config::getInstance();
        // TODO: thumbprint is not in the template, however keyword is. Seems half-baked. Remove? Discuss.
        $this->personalisation['THUMBPRINT'] = $config->values['radcert-thumbprint'];
        $this->template = $config->values['notify']['security-details'];
        $this->send();
    }

    /**
     * Send a survey text message to the contact, based on the survey configuration provided.
     * @param $surveyConfig array the survey configuration array
     */
    public function sendSurvey($surveyConfig) {
        $this->template = $surveyConfig['text_template'];
        $this->personalisation['SURVEY'] = $surveyConfig['survey_url'];
        $this->send();
    }

    public function getTemplateForOs($os, $journey) {
        $config = Config::getInstance();
        if (empty($os)) {
            $defaultTemplate = $config->values['notify'][$journey . 'creds-unknown'];
            if (SmsRequest::SMS_JOURNEY_SPLIT === $journey) {
                $defaultTemplate = $config->values['notify']['split-wifi-details'];
            }
            return $defaultTemplate;
        }
        // TODO: Split per journey, fail and log error if message is mismatched.
        switch ($os) {
            case (preg_match("/(3|mac|OSX|apple)/i", $os) ? true : false):
                return $config->values['notify'][$journey . 'creds-mac'];
                break;
            case (preg_match("/(win|windows)\s?(XP|7|8)/i", $os) ? true : false):
                return $config->values['notify'][$journey . 'creds-windows7'];
                break;
            case (preg_match("/(win|windows)\s?10/i", $os) ? true : false):
                return $config->values['notify'][$journey . 'creds-windows10'];
                break;
            case (preg_match("/(4|win|windows)\s?/i", $os) ? true : false):
                return $config->values['notify'][$journey . 'creds-windows'];
                break;
            case (preg_match("/(1|android|samsung|galaxy|htc|huawei|sony|motorola|lg|nexus)/i", $os) ? true : false):
                return $config->values['notify'][$journey . 'creds-android'];
                break;
            case (preg_match("/(2|ios|ipad|iphone|ipod)/i", $os) ? true : false):
                return $config->values['notify'][$journey . 'creds-iphone'];
                break;
            case (preg_match("/blackberry/i", $os) ? true : false):
                return $config->values['notify'][$journey . 'creds-blackberry'];
                break;
            default:
                return $config->values['notify'][$journey . 'creds-unknown'];
                break;
        }
    }
}

