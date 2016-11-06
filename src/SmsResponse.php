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
        } catch (NotifyException $e) {
            // TODO(afoldesi-gds): Handle failure, $response.
        }
    }

    public function sendNewsitePassword($pdf) {
        $config = Config::getInstance();
        $this->personalisation['PASSWORD'] = $pdf->password;
        $this->personalisation['FILENAME'] = $pdf->filename;
        $this->template=$config->values['notify']['newsite-password'];
        $this->send();
    }

    public function sendLogrequestPassword($pdf) {
        $config = Config::getInstance();
	    $this->personalisation['PASSWORD'] = $pdf->password;
        $this->personalisation['FILENAME'] = $pdf->filename;
        $this->template=$config->values['notify']['logrequest-password'];
        $this->send();
    }

    public function sendCredentials($user) {
        $config = Config::getInstance();
	    $this->personalisation['LOGIN'] = $user->login;
        $this->personalisation['PASS'] = $user->password;
	    $this->personalisation['KEYWORD'] = $config->values['reply-keyword'];
        $this->template=$config->values['notify']['wifi-details'];
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
        $this->personalisation['THUMBPRINT'] = $config->values['radcert-thumbprint'];
        $this->template = $config->values['notify']['security-details'];
        $this->send();
    }

    public function sendHelpForOs($os) {
        $config = Config::getInstance();
        switch ($os) {
            case (preg_match("/OSX/i", $os) ? true : false):
                $this->template = $config->values['notify']['help-osx'];
                break;
            case (preg_match("/win.*(XP|7|8)/i", $os) ? true : false):
                $this->template = $config->values['notify']['help-windows'];
                break;
            case (preg_match("/win.*10/i", $os) ? true : false):
                $this->template = $config->values['notify']['help-windows10'];
                break;
            case (preg_match("/android/i", $os) ? true : false):
                $this->template = $config->values['notify']['help-android'];
                break;
            case (preg_match("/(ios|ipad|iphone|ipod)/i", $os) ? true : false):
                $this->template = $config->values['notify']['help-iphone'];
                break;
            case (preg_match("/blackberry/i", $os) ? true : false):
                $this->template = $config->values['notify']['help-blackberry'];
                break;
            default:
                $this->template = $config->values['notify']['help'];
                break;
        }
        $this->send();
    }
}

