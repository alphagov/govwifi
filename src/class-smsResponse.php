<?php

class smsResponse {
    private $from;
    private $destinationNumber;
    public $template;
    public $personalisation;

    public function __construct($destinationNumber) {
        $this->setNoReply();
        $this->destinationNumber = $destinationNumber;
    }

    public function setReply() {
        $config = config::getInstance();
        $this->from = $config->values['reply-sender'];
    }

    public function setNoReply() {
        $config = config::getInstance();
        $this->from = $config->values['noreply-sender'];
    }

    private function send() {
        $config = config::getInstance();
        $notifyClient = new \Alphagov\Notifications\Client([
            'serviceId'     => $config->values['notify']['serviceId'],
            'apiKey'        => $config->values['notify']['apiKey'],
            'httpClient'    => new \Http\Adapter\Guzzle6\Client]);

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
        $config = config::getInstance();
        $this->personalisation['PASSWORD'] = $pdf->password;
        $this->personalisation['FILENAME'] = $pdf->filename;
        $this->template=$config->values['notify']['newsite-password'];
        $this->send();
    }

    public function sendLogrequestPassword($pdf) {
        $config = config::getInstance();
	    $this->personalisation['PASSWORD'] = $pdf->password;
        $this->personalisation['FILENAME'] = $pdf->filename;
        $this->template=$config->values['notify']['logrequest-password'];
        $this->send();
    }

    public function sendCredentials($user) {
        $config = config::getInstance();
	    $this->personalisation['LOGIN'] = $user->login;
        $this->personalisation['PASS'] = $user->password;
	    $this->personalisation['KEYWORD'] = $config->values['reply-keyword'];
        $this->template=$config->values['notify']['wifi-details'];
        $this->send();
    }

    public function sendRestrictedSiteHelpEmailUnset(site $site) {
        $config = config::getInstance();
        $this->personalisation['ADDRESS'] = $site->name;
        $this->personalisation['WHITELIST'] = $site->getWhitelist();
        $this->template = $config->values['notify']['restricted-site-email-unset'];
        $this->send();
    }

    public function sendRestrictedSiteHelpEmailSet(site $site) {
        $config = config::getInstance();
        $this->personalisation['ADDRESS'] = $site->name;
        $this->template = $config->values['notify']['restricted-site-email-set'];
        $this->send();
    }

    public function sendTerms() {
        $config = config::getInstance();
        $this->personalisation['KEYWORD'] = $config->values['reply-keyword'];
        $this->template = $config->values['notify']['terms'];
        $this->send();
    }

    public function sendDailyCodeConfirmation() {
        $config = config::getInstance();
        $this->template = $config->values['notify']['daily-code-confirmation'];
        $this->send();
    }

    public function sendSecurityInfo() {
        $config = config::getInstance();
        $this->personalisation['THUMBPRINT'] = $config->values['radcert-thumbprint'];
        $this->template = $config->values['notify']['security-details'];
        $this->send();
    }

    public function sendHelpForOs($os) {
        $config = config::getInstance();
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

?>
