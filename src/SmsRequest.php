<?php
namespace Alphagov\GovWifi;

class SmsRequest {
    public $sender;
    public $message;
    public $messageWords;

    public function setSender($sender) {
        $this->sender = new Identifier($sender);
    }

    public function setMessage($message) {
        $config = Config::getInstance();
        // remove whitespace and convert to lower case
        $this->message = strtolower(trim($message));
        // remove any instances of wifi from the message
        $this->message = str_replace(
                $config->values['strip-keyword'],
                "",
                $this->message);
        $this->messageWords = explode(' ', trim($this->message));
    }

    public function verify() {
        error_log(
                "SMS: Received an email verification code from " .
                $this->sender->text);
        $user = new User();
        $user->identifier = $this->sender;
        $user->codeVerify($this->messageWords[0]);
    }

    public function dailycode() {
        $user = new User();
        $user->identifier = $this->sender;
        $sms = new SmsResponse($this->sender->text);
        $sms->setReply();
        $login = $user->codeActivate($this->messageWords[0]);
        error_log(
            "SMS: Received a daily code from " .
                $this->sender->text . " User: " . $login);
        if ($login) {
            $sms->sendDailyCodeConfirmation();
            error_log(
                "SMS: Account exists, sending activation response to " .
                    $this->sender->text);
        } else {
            $sms->sendTerms();
            error_log("SMS: No account, sending terms to " .
                    $this->sender->text);
        }
    }

    public function security() {
        error_log("SMS: Security info request from ".$this->sender->text);
        $sms = new SmsResponse($this->sender->text);
        $sms->setReply();
        $sms->sendSecurityInfo();
    }

    public function help() {
        error_log("SMS: Sending help information to ".$this->sender->text);
        $sms = new SmsResponse($this->sender->text);
        $sms->setReply();
        $sms->sendHelpForOs($this->message);
    }

    public function newPassword() {
        error_log("SMS: Creating new password for ".$this->sender->text);
        $user = new User();
        // TODO(afoldesi-gds): Discuss what happens to the sponsor field.
        $user->identifier = $this->sender->text;
        $user->sponsor = $this->sender->text;
        $user->enroll(true);
    }

    public function other() {
        $config = Config::getInstance();

        if (!$config->values['send-terms']
            || $this->messageWords[0] == "agree") {
            error_log("SMS: Creating new account for ".$this->sender->text);
            $user = new User();
            $user->identifier = $this->sender;
            $user->sponsor = $this->sender;
            $user->enroll();
        } else {
            $sms = new SmsResponse($this->sender->text);
            $sms->setReply();
            $sms->sendTerms();
            error_log(
                "SMS: Initial request, sending terms to ".$this->sender->text);
        }
    }
}
