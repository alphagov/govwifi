<?php
namespace Alphagov\GovWifi;

/**
 * Class Identifier
 *
 * Stores and validates a user identifier, the supported formats are
 * either a UK mobile number, or an email address. Also has a special
 * case for health checks.
 *
 * @package Alphagov\GovWifi
 */
class Identifier {
    /**
     * @var string
     */
    public $text;

    /**
     * @var bool
     */
    public $validEmail = false;

    /**
     * @var bool
     */
    public $validMobile = false;

    /**
     * Identifier constructor.
     * @param $identifier string Formats recognized are:
     * - UK mobile number with or without spaces
     * - email address
     * - the health check identifier string literal
     */
    public function __construct($identifier) {
        if (preg_match("/^\+?[0-9\s]+$/", $identifier)) {
            $identifier = preg_replace("/\s+/", "", $identifier);
        }
        if ($this->isValidMobileNumber($identifier)) {
            $this->validMobile = true;
            $this->text = $this->standardizeMobileNumber($identifier);
        } else if ($this->isValidEmailAddress($identifier)) {
            $this->validEmail = true;
            $this->text = $this->fixUpEmailAddress($identifier);
        } else if (Config::HEALTH_CHECK_USER == $identifier) {
            $this->text = Config::HEALTH_CHECK_USER;
        }
    }

    /**
     * Define class behaviour when it's treated as, or forced to, a string.
     *
     * @return string
     */
    public function __toString() {
        return $this->text;
    }

    /**
     * Check if the supplied string is a valid mobile number.
     * @param $identifier string
     * @return int
     */
    private function isValidMobileNumber($identifier) {
        $pattern = "/^\+?\d{1,15}$/Ui";
        return preg_match($pattern, $identifier);
    }

    /**
     * Check if the supplied string is a valid email address.
     * @param $identifier string
     * @return mixed The string email address or bool false.
     */
    private function isValidEmailAddress($identifier) {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Change a UK mobile number to international format, while leaving
     * short codes (length 6 or below) as is.
     * @param $mobileNumber string
     * @return string
     */
    private function standardizeMobileNumber($mobileNumber) {
        // Do not mangle the short code
        if (strlen($mobileNumber) > 6) {
            if (substr($mobileNumber, 0, 2) == "07") {
                $mobileNumber = "44" . substr($mobileNumber, 1);
            }
            if (substr($mobileNumber, 0, 1) != "+") {
                $mobileNumber = "+" . $mobileNumber;
            }
        }
        return $mobileNumber;
    }

    /**
     * Turn email address lowercase, if matches pattern,
     * return null otherwise.
     * @param $email string
     * @return string or null
     */
    private function fixUpEmailAddress($email) {
        preg_match_all(
            '/[A-Za-z0-9\_\+\.\'-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]+/',
            $email,
            $matches);
        return strtolower($matches[0][0]);
    }
}
