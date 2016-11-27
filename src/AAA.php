<?php
namespace Alphagov\GovWifi;

use Exception;
use PDO;

class AAA {
    const URL_API      = "api";
    const URL_USER     = "user";
    const URL_MAC      = "mac";
    const URL_AP       = "ap";
    const URL_SITE     = "site";
    const URL_KIOSK_IP = "kioskip";
    const URL_RESULT   = "result";
    const URL_PHONE    = "phone";
    const URL_CODE     = "code";
    const TYPE_AUTHORIZE  = "authorize";
    const TYPE_POST_AUTH  = "post-auth";
    const TYPE_ACCOUNTING = "accounting";
    const TYPE_ACTIVATE   = "activate";
    const ACCEPTED_REQUEST_TYPES = [
        self::TYPE_AUTHORIZE,
        self::TYPE_ACCOUNTING,
        self::TYPE_POST_AUTH,
        self::TYPE_ACTIVATE
    ];

    /**
     * @var User
     */
    public $user;
    public $siteIP;
    public $site;
    public $type;
    public $responseHeader;
    public $responseBody;
    public $requestJson;
    public $session;
    public $result;
    public $kioskKey;

    /**
     * @var string The request url.
     */
    private $requestUrl;

    /**
     * @var string MAC address of the client.
     */
    private $mac;

    /**
     * @var string MAC address of the AP.
     */
    private $ap;

    /**
     * AAA constructor.
     *
     * @param $requestUrl string The request url.
     * @param $jsonData string The json data POSTed to us,
     * only required for accounting requests.
     * @throws Exception When the request type is not recognized.
     */
    public function __construct($requestUrl, $jsonData) {
        $this->requestUrl     = $requestUrl;
        $this->requestJson    = $jsonData;
        $this->parseRequest();
    }

    /**
     * Parses the request url.
     *
     * @throws Exception When the request type is not recognized.
     */
    public function parseRequest() {
        $parts = explode('/', $this->requestUrl);
        for ($x = 0; $x < count($parts); $x++) {
            switch ($parts[$x]) {
                case self::URL_API:
                    $this->type = $parts[$x + 1];
                    if (! in_array($this->type, self::ACCEPTED_REQUEST_TYPES)) {
                        throw new Exception("Request type [" . $this->type . "] is not recognized.");
                    }
                    break;
                case self::URL_USER:
                    $this->user = new User(Cache::getInstance());
                    $this->user->login = strtoupper($parts[$x + 1]);
                    $this->user->loadRecord();
                    break;
                case self::URL_MAC:
                    $this->setMac($parts[$x + 1]);
                    break;
                case self::URL_AP:
                    $this->setAp($parts[$x + 1]);
                    break;
                case self::URL_SITE:
                    $this->siteIP = $parts[$x + 1];
                    $this->site = new Site;
                    $this->site->loadByIp($this->siteIP);
                    break;
                case self::URL_KIOSK_IP:
                    $this->site = new Site;
                    $this->site->loadByKioskIp($parts[$x + 1]);
                    break;
                case self::URL_RESULT:
                    $this->result = $parts[$x + 1];
                    break;
                case self::URL_PHONE:
                    $this->user = new User(Cache::getInstance());
                    $this->user->identifier = new Identifier($parts[$x + 1]);
                    break;
                case self::URL_CODE:
                    $this->kioskKey = $parts[$x + 1];
                    break;
            }
        }
    }

    /**
     * Process the request provided in the constructor based
     * on the type of the request.
     *
     * Supported request types are: authorize, post-auth, accounting
     * and activate.
     */
    public function processRequest() {
        switch ($this->type) {
            case self::TYPE_AUTHORIZE:
                $this->authorize();
                break;
            case self::TYPE_POST_AUTH:
                $this->postAuth();
                break;
            case self::TYPE_ACCOUNTING:
                $this->accounting();
                break;
            case self::TYPE_ACTIVATE:
                $this->activate();
                break;
        }
    }

    public function kioskKeyValid() {
        if (strtoupper($this->site->kioskKey) == strtoupper($this->kioskKey)) {
            return true;
        } else {
            error_log($this->site->kioskKey . " " . $this->kioskKey);
            return false;
        }
    }

    public function activate() {
        error_log("Site ID: ".$this->site->id);

        if (($this->site->id) && $this->kioskKeyValid()) {
            if (isset($this->user->identifier)
                && $this->user->identifier->validMobile) {
                // insert an activation entry
                $this->user->kioskActivate($this->site->id);
                $this->user->loadRecord();
                if (!$this->user->login) {
                     $sms = new SmsResponse($this->user->identifier->text);
                     $sms->setReply();
                     $sms->sendTerms();
                }
            } else {
                // TODO: print.
                print $this->site->getDailyCode();
            }
        } else {
            $this->responseHeader = "404 Not Found";
        }
    }

    public function accounting() {
        $acct = json_decode($this->requestJson, true);
        $this->session = new Session(
                $this->user->login . $acct['Acct-Session-Id']['value'][0],
                Cache::getInstance());

        switch ($acct['Acct-Status-Type']['value'][0]) {
            case 1:
                // Acct Start - Store session in Memcache
                $this->session->login = $this->user->login;
                $this->session->startTime = time();
                $this->setMac($acct['Calling-Station-Id']['value'][0]);
                $this->setAp($acct['Called-Station-Id']['value'][0]);
                $this->session->mac = $this->getMac();
                $this->session->ap = $this->getAp();
                $this->session->siteIP = $this->siteIP;
                $this->session->writeToCache();
                error_log(
                        "Accounting start: "
                        . $this->session->login . " "
                        . $this->session->id);
                    $this->responseHeader = "HTTP/1.0 204 OK";
                break;
            case 2:
                // Acct Stop - store record in DB -
                // if there is no start record do nothing.
                if ($this->session->startTime) {
                    $this->session->inOctets +=
                            $acct['Acct-Input-Octets']['value'][0];
                    $this->session->outOctets +=
                            $acct['Acct-Output-Octets']['value'][0];
                    $this->session->stopTime = time();
                    $this->session->deleteFromCache();
                    error_log(
                            "Accounting stop: "
                            . $this->session->login . " "
                            . $this->session->id);

                    error_log(
                            "Accounting stop: "
                            . $this->session->login . " "
                            . $this->session->id
                            . " InMB: " . $this->session->inMB()
                            . " OutMB: " . $this->session->outMB()
                            . " site: " . $this->session->siteIP
                            . " mac: " . $this->session->mac
                            . " ap: " . $this->session->ap);
                    $this->session->writeToDB();
                    $this->responseHeader = "HTTP/1.0 204 OK";
                }
                break;
            case 3:
                // Acct Interim - if there is no start record do nothing.
                if ($this->session->startTime) {
                    $this->session->inOctets +=
                            $acct['Acct-Input-Octets']['value'][0];
                    $this->session->outOctets +=
                            $acct['Acct-Output-Octets']['value'][0];
                    $this->session->writeToCache();
                    error_log(
                            "Accounting update: "
                            . $this->session->login . " "
                            . $this->session->id);
                    $this->responseHeader = "HTTP/1.0 204 OK";
                }
                break;
        }
    }

    /**
     * Handle the post authentication request from the Radius REST API.
     *
     * If the authentication was successful, and it's not the health check user,
     * start a new session. (Insert a new session record into the database.)
     */
    public function postAuth() {
        if ($this->result == "Access-Accept") {
            if ($this->user->login != "HEALTH") {
                // insert a new entry into session (unless it's a health check)
                $db = DB::getInstance();
                $dbLink = $db->getConnection();
                $handle = $dbLink->prepare(
                        'insert into session ' .
                        '(start, siteIP, username, mac, ap) ' .
                        'values (now(), :siteIP, :username, :mac, :ap)');
                $handle->bindValue(
                    ':siteIP', $this->siteIP, PDO::PARAM_STR);
                $handle->bindValue(
                    ':username', $this->user->login, PDO::PARAM_STR);
                $handle->bindValue(
                    ':mac', strtoupper($this->getMac()), PDO::PARAM_STR);
                $handle->bindValue(
                    ':ap', strtoupper($this->getAp()), PDO::PARAM_STR);
                $handle->execute();
            }
        }
    }

    private function fixMac($mac) {
        //convert to upper case
        $mac = strtoupper($mac);
        //get rid of anything that isn't hex
        $mac = preg_replace('/[^0-F]/',"",$mac);
        // recreate the mac in IETF format using the first 12 chars.
        $mac = substr($mac,0,2) . '-' . substr($mac,2,2) .'-'
            . substr($mac,4,2) . '-' . substr($mac,6,2). '-'
            . substr($mac,8,2) . '-' . substr($mac,10,2);
        return $mac;
    }

    /**
     * @return string
     */
    public function getMac() {
        return $this->mac;
    }

    /**
     * @param $mac string
     */
    public function setMac($mac) {
        $this->mac = $this->fixMac($mac);
    }

    /**
     * @return string
     */
    public function getAp() {
        return $this->ap;
    }

    /**
     * @param $apMac string
     */
    public function setAp($apMac) {
        $this->ap = $this->fixMac($apMac);
    }

    /**
     * Tries to authorize the user based on her mobile number, email address,
     * and site-specific settings. Sets the response header and content
     * accordingly.
     *
     * If the site is restricted by an activation regex, the user's email
     * is checked against this.
     * If this check fails the user could still be authorized if she has
     * a valid daily code already activated for the current site.
     */
    public function authorize() {
        // Return immediately for health checks.
        if (Config::HEALTH_CHECK_USER == $this->user->login) {
            $this->authorizeResponse(TRUE);
            return;
        }

        // If this matches a user account continue
        if (isset($this->user->identifier)
            && $this->user->identifier->text) {

            // If the site isn't restricted
            if (($this->site->activationRegex == "")
            // or the user's email address is authorised
            || preg_match('/' . $this->site->activationRegex . '/',
                    $this->user->email)) {
                $this->authorizeResponse(TRUE);
            } else {
                error_log(
                    "Restricted site: "
                    . $this->site->activationRegex
                    . " Users email: " . $this->user->email);
                // or the user has activated at this site
                if ($this->user->activatedHere($this->site)) {
                    $this->authorizeResponse(TRUE);
                } else {
                    $this->authorizeResponse(FALSE);
                }
            }
        }
    }

    private function authorizeResponse($accept) {
        if ($accept) {
            $this->responseHeader = "200 OK";
            $response['control:Cleartext-Password'] = $this->user->password;
            $this->responseBody = json_encode($response);
        } else {
             $this->responseHeader = "404 Not Found";
        }
    }
}
