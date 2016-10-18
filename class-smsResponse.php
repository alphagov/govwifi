<?php

class smsResponse {
    public $from;
    public $to;
    public $template;
    public $personalisation;

    public function __construct() {
        $this->setNoReply();
    }

    public function setReply() {
        $config = config::getInstance();
        $this->from = $config->values['reply-sender'];

    }

    public function setNoReply() {
        $config = config::getInstance();
        $this->from = $config->values['noreply-sender'];
    }

    public function send() {
        $config = config::getInstance();
        $notifyClient = new \Alphagov\Notifications\Client([
            'serviceId'     => $config->values['notify']['serviceId'],
            'apiKey'        => $config->values['notify']['apiKey'],
            'httpClient'    => new \Http\Adapter\Guzzle6\Client]);

        try {
            $response = $notifyClient->sendSms(
                    $this->to,
                    $this->template,
                    $this->personalisation);
        } catch (NotifyException $e) {
            // TODO(afoldesi-gds): Handle failure, $response.
        }
    }

    public function newsite($pdf) {
        $config = config::getInstance();
	$this->personalisation['PASSWORD'] = $pdf->password;
	$this->personalisation['FILENAME'] = $pdf->filename;
	$this->template=$config->values['notify']['newsite-password'];
        $this->send();
    }

    public function logrequest($pdf) {
        $config = config::getInstance();
	$this->personalisation['PASSWORD'] = $pdf->password;
        $this->personalisation['FILENAME'] = $pdf->filename;
        $this->template=$config->values['notify']['logrequest-password'];
        $this->send();
    }

    public function enroll($user) {
        $config = config::getInstance();
	$this->personalisation['LOGIN'] = $user->login;
        $this->personalisation['PASS'] = $user->password;
	$this->personalisation['KEYWORD'] = $config->values['reply-keyword'];
        $this->template=$config->values['notify']['wifi-details'];
        $this->send();
    }
    //TODO (afoldesi-gds): Incomplete, unused. Update to use notify templates.
    public function restrictedUnset($site) {
        $config = config::getInstance();
        $this->message = file_get_contents(
                $config->values['sms-messages']['restricted-unset-file']);
        $this->message = str_replace("%ADDRESS%", $site->name, $this->message);
        $this->message = str_replace(
                "%WHITELIST%", $site->getWhitelist(), $this->message);
        $this->send();
    }
    //TODO (afoldesi-gds): Incomplete, unused. Update to use notify templates.
    public function restrictedSet($site) {
        $config = config::getInstance($site);
        $this->message = file_get_contents(
                $config->values['sms-messages']['restricted-set-file']);
        $this->message = str_replace("%ADDRESS%", $site->name, $this->message);
        $this->message = str_replace(
                "%WHITELIST%", $site->getWhitelist(), $this->message);
        $this->send();
    }

    public function terms() {
        $config = config::getInstance();
	$this->personalisation['KEYWORD'] = $config->values['reply-keyword'];
	$this->template = $config->values['notify']['terms'];
        $this->send();
    }
    //TODO (afoldesi-gds): Update to use notify templates.
    public function activate() {
        $config = config::getInstance();
        $this->message = file_get_contents(
                $config->values['sms-messages']['activate-file']);
        $this->message = str_replace(
                "%KEYWORD%", $config->values['reply-keyword'], $this->message);
        $this->send();
    }

    public function security() {
        $config = config::getInstance();
	$this->personalisation['THUMBPRINT']=$config->values['radcert-thumbprint'];
	$this->template=$config->values['notify']['security-details'];
        $this->send();
    }

    public function help($os) {
        $config = config::getInstance();

        switch ($os) {
            case (preg_match("/OSX/i", $os) ? true : false):
                $this->template=$config->values['notify']['help-osx'];
                break;
            case (preg_match("/win.*(XP|7|8)/i", $os) ? true : false):
                $this->template=$config->values['notify']['help-windows'];
                break;
            case (preg_match("/win.*10/i", $os) ? true : false):
                $this->template=$config->values['notify']['help-windows10'];
                break;
            case (preg_match("/android/i", $os) ? true : false):
                $this->template=$config->values['notify']['help-android'];
                break;
            case (preg_match("/(ios|ipad|iphone|ipod)/i", $os) ? true : false):
                $this->template=$config->values['notify']['help-iphone'];
                break;
            case (preg_match("/blackberry/i", $os) ? true : false):
                $this->template=$config->values['notify']['help-blackberry'];
                break;
            default:
                $this->template=$config->values['notify']['help'];
                break;
        }
        $this->send();
    }
}

?>
