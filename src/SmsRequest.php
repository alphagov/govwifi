<?php
namespace Alphagov\GovWifi;

class SmsRequest {
    public $sender;
    public $message;
    public $messageWords;

    /**
     * Processes an incoming SMS request based on the first word of the text sent.
     *
     * Note that messages sent to short numbers do not contain the keyword, so the "first word" here actually means the
     * second in the actual text message - first being the keyword itself. The keyword is sent separately.
     *
     * @return bool To indicate success or failure - only dependent on the validity of the number.
     */
    public function processRequest() {
        if (! $this->sender->validMobile) {
            return false;
        }

        $firstWord = $this->messageWords[0];
        error_log("SMS first word:*" . $firstWord . "*");
        switch ($firstWord) {
            case "security":
                $this->security();
                break;
            case "new":
            case "newpassword":
                $this->newPassword();
                break;
            case "help":
                $this->help();
                break;
            case "agree":
                $this->signUp();
                break;
            default:
                if (preg_match('/^[0-9]{4}$/', $firstWord)) {
                    $this->dailyCode();
                } else if (preg_match('/^[0-9]{6}$/', $firstWord)) {
                    $this->verify();
                } else {
                    $this->other();
                }
                break;
        }
        return true;
    }

    public function setSender($sender) {
        error_log("Sender: " . $sender);
        $this->sender = new Identifier($sender);
        error_log("Sender Obj:" . var_export($this->sender, true));
    }

    /**
     * Sets the message property based on the string provided.
     *
     * For normal mobile number end points (not short numbers), keywords are not necessary; however if they are
     * sent, the message still needs to be recognized. In this case the keyword is stripped based on a regex defined
     * in the environment-specific configuration.
     *
     * @param string $message
     */
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
        $user = new User(Cache::getInstance(), Config::getInstance());
        $user->identifier = $this->sender;
        $user->codeVerify($this->messageWords[0]);
    }

    public function dailyCode() {
        $user = new User(Cache::getInstance(), Config::getInstance());
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
        $sms->sendHelp();
    }

    public function newPassword() {
        error_log("SMS: Creating new password for ".$this->sender->text);
        $user = new User(Cache::getInstance(), Config::getInstance());
        $user->identifier = $this->sender->text;
        $user->sponsor = $this->sender->text;
        $user->signUp("", true);
    }

    public function signUp() {
        error_log("SMS: Creating new account for ".$this->sender->text);
        $user = new User(Cache::getInstance(), Config::getInstance());
        $user->identifier = $this->sender;
        $user->sponsor = $this->sender;
        $user->signUp($this->message);
    }

    public function other() {
        $config = Config::getInstance();

        if (!$config->values['send-terms']) {
            $this->signUp();
        } else {
            $sms = new SmsResponse($this->sender->text);
            $sms->setReply();
            error_log(
                "SMS: Initial request, sending terms to ".$this->sender->text);
            $sms->sendTerms();
        }
    }
}
