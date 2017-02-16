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
            'serviceId'     => $config->values['notify']['serviceId'],
            'apiKey'        => $config->values['notify']['apiKey'],
            'httpClient'    => new Guzzle6Client]);

        try {
            $response = $notifyClient->sendSms(
                    $this->destinationNumber,
                    $this->template,
                    $this->personalisation);
            error_log("SMS sent. " . json_encode($response));
        } catch (NotifyException $e) {
            error_log("Exception from Notify: " . var_export($e, true));
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
        if (SmsRequest::SMS_JOURNEY_TERMS == $journey) {
            $this->personalisation['LOGIN'] = $user->login;
            $this->personalisation['PASS'] = $user->password;
        }
        $this->send();
    }

    public function sendRestrictedSiteHelpEmailUnset(Site $site) {
        $config = Config::getInstance();
        $this->personalisation['ADDRESS'] = $site->name;
        $this->personalisation['WHITELIST'] = $site->getWhitelist();
        $this->template = $config->values['notify']['restricted-site-email-unset'];
        $this->send();
    }

    public function sendRestrictedSiteHelpEmailSet(Site $site) {
        $config = Config::getInstance();
        $this->personalisation['ADDRESS'] = $site->name;
        $this->template = $config->values['notify']['restricted-site-email-set'];
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

    public function sendDailyCodeConfirmation() {
        $config = Config::getInstance();
        $this->template = $config->values['notify']['daily-code-confirmation'];
        $this->send();
    }

    public function sendSecurityInfo() {
        $config = Config::getInstance();
        // TODO: thumbprint is not in the template, however keyword is. Seems half-baked. Remove? Discuss.
        $this->personalisation['THUMBPRINT'] = $config->values['radcert-thumbprint'];
        $this->template = $config->values['notify']['security-details'];
        $this->send();
    }

    public function getTemplateForOs($os, $journey) {
        $config = Config::getInstance();
        if (empty($os)) {
            return $config->values['notify'][$journey . 'creds-unknown'];
        }
        // TODO: Split per journey, fail and log error if message is mismatched.
        switch ($os) {
            case (preg_match("/(5|mac|OSX|apple)/i", $os) ? true : false):
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
            case (preg_match("/(2|3|ios|ipad|iphone|ipod)/i", $os) ? true : false):
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

