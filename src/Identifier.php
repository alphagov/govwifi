<?php
namespace Alphagov\GovWifi;

class Identifier {
    public $text;
    public $validEmail;
    public $validMobile;

    public function __construct($identifier) {
        $identifier = preg_replace("/\s+/", "", $identifier);
        if ($this->isValidMobileNumber($identifier)) {
            $this->validMobile = true;
            $this->text = $this->fixUpMobileNumber($identifier);
        } else if ($this->isValidEmailAddress($identifier)) {
            $this->validEmail = true;
            $this->text = $this->fixUpEmailAddress($identifier);
        } else if ($identifier == "HEALTH") {
            $this->text = "HEALTH";
        }
    }

    private function isValidMobileNumber($identifier) {
        $pattern = "/^\+?\d{1,15}$/Ui";
        return preg_match($pattern, $identifier);
    }

    private function isValidEmailAddress($identifier) {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL);
    }

    private function fixUpMobileNumber($sender) {
        // dont mangle the short code
        if (strlen($sender) > 6) {
            if (substr($sender, 0, 2) == "07") {
                $sender = "44" . substr($sender, 1);
            }
            if (substr($sender, 0, 1) != "+") {
                $sender = "+" . $sender;
            }
        }
        return $sender;
    }

    private function fixUpEmailAddress($address) {
        preg_match_all(
            '/[A-Za-z0-9\_\+\.\'-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]+/',
            $address,
            $matches);
        return strtolower($matches[0][0]);
    }
}
