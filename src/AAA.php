<?php
namespace Alphagov\GovWifi;

use Exception;
use PDO;

class AAA {
    const URL_API                   = "api";
    const URL_USER                  = "user";
    const URL_MAC                   = "mac";
    const URL_AP                    = "ap";
    const URL_SITE                  = "site";
    const URL_RESULT                = "result";
    const TYPE_AUTHORIZE            = "authorize";
    const TYPE_POST_AUTH            = "post-auth";
    const TYPE_ACCOUNTING           = "accounting";
    const ACCEPTED_REQUEST_TYPES    = [
        self::TYPE_AUTHORIZE,
        self::TYPE_ACCOUNTING,
        self::TYPE_POST_AUTH,
    ];
    const AUTH_RESULT_ACCEPT        = "Access-Accept";
    const AUTH_RESULT_REJECT        = "Access-Reject";
    const ACCEPTED_AUTH_RESULTS     = [
        self::AUTH_RESULT_ACCEPT,
        self::AUTH_RESULT_REJECT
    ];
    const ACCOUNTING_TYPE_START     = 1;
    const ACCOUNTING_TYPE_STOP      = 2;
    const ACCOUNTING_TYPE_INTERIM   = 3;
    const ACCOUNTING_TYPE_ON        = 7;
    const ACCOUNTING_TYPE_OFF       = 8;
    const ACCEPTED_ACCOUNTING_TYPES = [
        self::ACCOUNTING_TYPE_START,
        self::ACCOUNTING_TYPE_STOP,
        self::ACCOUNTING_TYPE_INTERIM,
        self::ACCOUNTING_TYPE_ON,
        self::ACCOUNTING_TYPE_OFF
    ];
    const HTTP_RESPONSE_OK          = "200 OK";
    const HTTP_RESPONSE_NO_CONTENT  = "204 OK";
    const HTTP_RESPONSE_NOT_FOUND   = "404 Not Found";
    const DEFAULT_HTTP_PROTOCOL     = "HTTP/1.1";
    /**
     * @var User
     */
    public $user;
    public $siteIP;
    /**
     * @var Site
     */
    public $site;
    public $type;
    /**
     * The result of the RADIUS authentication.
     * @var string
     */
    public $result;
    private $responseHeader;
    private $responseBody;
    private $requestJson;
    /**
     * @var Session
     */
    private $session;

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
     * @var string Building identifier
     *
     * Sent in place of the MAC address of the AP by clients
     * with cloud-based services where the originating IP address
     * is the same for multiple buildings.
     */
    private $buildingIdentifier;

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

        $this->parseRequest($requestUrl);
    }

    /**
     * Parses the request url.
     *
     * @param string $requestUrl
     * @throws Exception When the request type or the auth result is not recognized.
     */
    public function parseRequest($requestUrl) {
        $parts = explode('/', $requestUrl);
        for ($x = 0; $x < count($parts); $x++) {
            switch ($parts[$x]) {
                case self::URL_API:
                    $this->type = $parts[$x + 1];
                    if (! in_array($this->type, self::ACCEPTED_REQUEST_TYPES)) {
                        throw new Exception("Request type [" . $this->type . "] is not recognized.");
                    }
                    break;
                case self::URL_USER:
                    $this->user = new User(Cache::getInstance(), Config::getInstance());
                    //TODO: Consider removing strtoupper so session ID / records match visually. (log search!)
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
                case self::URL_RESULT:
                    $this->result = $parts[$x + 1];
                    if (! in_array($this->result, self::ACCEPTED_AUTH_RESULTS)) {
                        throw new Exception("Auth result [" . $this->result . "] is not recognized.");
                    }
                    break;
            }
        }
    }

    /**
     * Process the request provided in the constructor based
     * on the type of the request.
     *
     * Supported request types are: authorize, post-auth, accounting.
     *
     * @return array the response headers and body to be returned for the request
     * format:
     * 'headers' => array(string, string, ...),
     * 'body' => string
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
        }

        $httpProtocol = self::DEFAULT_HTTP_PROTOCOL;
        if (! empty($_SERVER["SERVER_PROTOCOL"])){
            $httpProtocol = $_SERVER["SERVER_PROTOCOL"];
        }

        return [
            'headers' => [
                $httpProtocol . " " . $this->responseHeader,
                "Content-Type: application/json",
            ],
            'body'    => $this->responseBody
        ];
    }

    /**
     * Handles the accounting requests.
     *
     * There are 3 types recognised: start, stop and interim.
     *
     * A start request places an entry in the cache, interim requests update it, while the stop
     * deletes the session from the cache, takes the data accumulated, and saves it into permanent
     * storage.
     */
    public function accounting() {
        error_log("Accounting JSON: " . $this->requestJson);

        $acct = json_decode($this->requestJson, true);
        $accountingType = $acct['Acct-Status-Type']['value'][0];

        // Default value in case of failure.
        $this->responseHeader = self::HTTP_RESPONSE_NOT_FOUND;

        if (! in_array($accountingType, self::ACCEPTED_ACCOUNTING_TYPES)) {
            error_log("Accounting request type not recognised: [" . $accountingType . "]");
            return;
        }

        if (! $this->user->validUser) {
            error_log("Skip Accounting [" . $accountingType .
                "] for invalid user [" . $this->user->login . "]");
            return;
        }

        $this->session = new Session(
                $this->user->login . md5($acct['Acct-Session-Id']['value'][0]),
                Cache::getInstance());

        error_log("Acct type: " . $accountingType);
        switch ($accountingType) {
            case self::ACCOUNTING_TYPE_START:
            case self::ACCOUNTING_TYPE_ON:
                // Acct Start - Store session in Memcache
                $this->session->login = $this->user->login;
                $this->session->startTime = time();
                $this->setMac($acct['Calling-Station-Id']['value'][0]);
                $this->setAp($acct['Called-Station-Id']['value'][0]);
                $this->session->mac = $this->getMac();
                $this->session->ap = $this->getAp();
                $this->session->buildingIdentifier = $this->buildingIdentifier;
                $this->session->siteIP = $this->siteIP;
                $this->session->writeToCache();
                error_log(
                        "Accounting start: "
                        . "[" . $accountingType . "] "
                        . $this->session->login . " "
                        . $this->session->id);
                $this->responseHeader = self::HTTP_RESPONSE_NO_CONTENT;
                break;
            case self::ACCOUNTING_TYPE_STOP:
            case self::ACCOUNTING_TYPE_OFF:
                // Acct Stop - store record in DB

                // If there is no start record do nothing and return the default error message.
                // The RADIUS frontend will not respond to the client if this happens.
                if ($this->session->startTime) {
                    $this->session->inOctets +=
                            $acct['Acct-Input-Octets']['value'][0];
                    $this->session->outOctets +=
                            $acct['Acct-Output-Octets']['value'][0];
                    $this->session->stopTime = time();
                    $this->session->deleteFromCache();
                    error_log(
                            "Accounting stop: "
                            . "[" . $accountingType . "] "
                            . $this->session->login . " "
                            . $this->session->id
                            . " InMB: " . $this->session->inMB()
                            . " OutMB: " . $this->session->outMB()
                            . " site: " . $this->session->siteIP
                            . " mac: " . $this->session->mac
                            . " ap: " . $this->session->ap);
                    $this->session->writeToDB();
                    $this->responseHeader = self::HTTP_RESPONSE_NO_CONTENT;
                } else {
                    error_log("No previous record found for accounting stop request. ID ["
                        . $this->session->id. "]");
                }
                break;
            case self::ACCOUNTING_TYPE_INTERIM:
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
                    $this->responseHeader = self::HTTP_RESPONSE_NO_CONTENT;
                } else {
                    error_log("No previous record found for accounting interim request. ID ["
                        . $this->session->id. "]");
                }
                break;
            default:
                error_log("Accounting request type not recognised: (default) [" . $accountingType . "]");
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
        if (self::AUTH_RESULT_ACCEPT == $this->result) {
            $this->responseHeader = self::HTTP_RESPONSE_NO_CONTENT;
            if ($this->user->login != "HEALTH") {
                // insert a new entry into session (unless it's a health check)
                $db = DB::getInstance();
                $dbLink = $db->getConnection();
                $handle = $dbLink->prepare(
                        'insert into session ' .
                        '(start, siteIP, username, mac, ap, building_identifier) ' .
                        'values (now(), :siteIP, :username, :mac, :ap, :building_identifier)');
                $handle->bindValue(
                    ':siteIP', $this->siteIP, PDO::PARAM_STR);
                $handle->bindValue(
                    ':username', $this->user->login, PDO::PARAM_STR);
                $handle->bindValue(
                    ':mac', strtoupper($this->getMac()), PDO::PARAM_STR);
                $handle->bindValue(
                    ':ap', strtoupper($this->getAp()), PDO::PARAM_STR);
                $handle->bindValue(
                    ':building_identifier', $this->buildingIdentifier, PDO::PARAM_STR);

                $handle->execute();
            }
        } else if (self::AUTH_RESULT_REJECT == $this->result) {
            $this->responseHeader = self::HTTP_RESPONSE_NO_CONTENT;
        } else {
            $this->responseHeader = self::HTTP_RESPONSE_NOT_FOUND;
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
     * @param $calledStationId string Sets the AP or the building identifier
     * based on the length of the "fixed" mac address.
     * This is valid as building identifiers can not be converted to
     * a correct (and proper sized) mac address.
     */
    public function setAp($calledStationId) {
        $this->ap = null;
        $this->buildingIdentifier = null;
        $possibleMac = $this->fixMac($calledStationId);
        if (17 === strlen($possibleMac)) {
            $this->ap = $possibleMac;
        } else if (! empty($calledStationId)) {
            $this->buildingIdentifier = $calledStationId;
        }
    }

    /**
     * Visible for testing
     *
     * @return string
     */
    public function getBuildingIdentifier() {
        return $this->buildingIdentifier;
    }

    /**
     * Tries to authorize the user based on her mobile number, email address,
     * and site-specific settings. Sets the response header and content
     * accordingly.
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
            // Logic for restricted journey may come here
            // Eg test emails against specific regex, lock out SMS-registered users, etc.
            $this->authorizeResponse(TRUE);
        }
    }

    private function authorizeResponse($accept) {
        if ($accept) {
            $this->responseHeader = self::HTTP_RESPONSE_OK;
            $response['control:Cleartext-Password'] = $this->user->password;
            $this->responseBody = json_encode($response);
        } else {
             $this->responseHeader = self::HTTP_RESPONSE_NOT_FOUND;
        }
    }
}
