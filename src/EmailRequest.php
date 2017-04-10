<?php
namespace Alphagov\GovWifi;

use Exception;
use PDO;
use PDOException;

class EmailRequest extends GovWifiBase {
    const CONTENT_PLAIN_TEXT = "content-type: text/plain";
    const CONTENT_HTML       = "content-type: text/html";

    /**
     * @var Identifier
     */
    public $emailFrom;

    /**
     * @var string
     */
    public $senderName;
    public $emailTo;
    public $emailToCMD;
    public $emailBody;
    public $emailSubject;

    /**
     * Instance of a class implementing the EmailProvider interface.
     * @var EmailProvider
     */
    private $emailProvider;

    /**
     * Instance of the environment-specific Config class.
     * @var Config
     */
    private $config;

    /**
     * Instance of the environment-specific Cache class.
     * @var Cache
     */
    private $cache;

    /**
     * Instance of the environment-specific DB class.
     * @var DB
     */
    private $db;

    /**
     * The text journey type to switch sponsored text templates.
     * @var string
     */
    private $journeyType;

    public function __construct($params) {
        $defaults = [
            'emailProvider' => null,
            'config'        => null,
            'db'            => null,
            'cache'         => null,
        ];
        $params = array_merge($defaults, $params);
        parent::checkNotEmpty(array_keys($defaults), $params);
        parent::checkStandardParams($params);
        if (!is_a($params['emailProvider'], EmailProvider::class)) {
            throw new GovWifiException("EmailProvider class not recognised.");
        }
        $this->emailProvider = $params['emailProvider'];
        $this->config        = $params['config'];
        $this->cache         = $params['cache'];
        $this->db            = $params['db'];
        $this->journeyType   = SmsRequest::SMS_JOURNEY_TERMS;
        if (! $this->config->values['send-terms']) {
            $this->journeyType = SmsRequest::SMS_JOURNEY_SPLIT;
        }
    }

    /**
     * Processes the incoming email request based on the EmailProvider the class was initialised with.
     *
     * @return bool True if the normal processing stage was executed, false if only the provider-specific
     * pre-processing was done.
     * @throws GovWifiException
     */
    public function processRequest() {
        $furtherProcessingRequired = $this->emailProvider->preProcess();
        if ($furtherProcessingRequired) {
            $emailFrom  = $this->emailProvider->getEmailFrom();
            $senderName = $this->emailProvider->getSenderName();
            error_log($this->emailProvider->getProviderId() . " EMAIL: From : " . $emailFrom .
                ", Sender name: [" . $senderName . "]");
            $this->setEmailFrom($emailFrom);
            $this->setSenderName($senderName);

            $destination = $this->emailProvider->getEmailTo();
            error_log($this->emailProvider->getProviderId() . " EMAIL: To : " . $destination);
            $this->setEmailTo($destination);

            $this->setEmailSubject($this->emailProvider->getEmailSubject());
            $this->setEmailBody($this->emailProvider->getEmailBody());
            $this->setEmailTo($this->emailProvider->getEmailTo());

            switch ($this->emailToCMD) {
                case "enroll":
                case "enrol":
                case "signup":
                    $this->signUp();
                    break;
                case "verify":
                    $this->verify();
                    break;
                case "sponsor":
                    $this->sponsor();
                    break;
                case "newsite":
                    $this->newSite();
                    break;
                case "logrequest":
                    $this->logRequest();
                    break;
                default:
                    error_log("AWS SNS EMAIL: No command found. Have we been cc'd?");
                    break;
            }
            return true;
        }
        return false;
    }

    public function verify() {
        $dblink = $this->db->getConnection();
        $handle = $dblink->prepare('delete from verify where email = :email');
        $handle->bindValue(':email', $this->emailFrom->text, PDO::PARAM_STR);
        $handle->execute();
        $handle = $dblink->prepare(
                'insert into verify (code, email) values (:code,:email)');
        $handle->bindValue(':email', $this->emailFrom->text, PDO::PARAM_STR);
        $attempts = 0;
        $success = false;

        while (!$success && $attempts < 10) {
            try {
                $attempts++;
                $code = $this->generateRandomVerifyCode();
                $handle->bindValue(':code', $code, PDO::PARAM_STR);
                $handle->execute();
                $success = true;
            } catch (PDOException $e) {
                $success = false;
            }
        }

        if ($success) {
            $email = new EmailResponse;
            $email->to = $this->emailFrom->text;
            // TODO: There's no verify() in EmailResponse.
            //$email->verify($code);
            $email->send();
        }
    }

    private function generateRandomVerifyCode() {
        $length  = $this->config->values['verify-code']['length'];
        $pattern = $this->config->values['verify-code']['regex'];
        $pass = preg_replace(
                $pattern,
                "",
                base64_encode($this->strongRandomBytes($length * 10)));
        return substr($pass, 0, $length);
    }

    private function strongRandomBytes($length) {
        $strong = false; // Flag for whether a strong algorithm was used
        $bytes = openssl_random_pseudo_bytes($length, $strong);

        if (!$strong) {
            // System did not use a cryptographically strong algorithm
            throw new Exception('Strong algorithm not available for PRNG.');
        }

        return $bytes;
    }

    public function signUp() {
        // Self service signup request
        if ($this->fromAuthDomain()) {
            error_log("EMAIL: signup : " . $this->emailFrom->text);
            $user = new User($this->cache, $this->config);
            $user->identifier = $this->emailFrom;
            $user->sponsor = $this->emailFrom;
            $user->signUp();
        } else {
            error_log(
                    "EMAIL: Ignoring signup from: "
                    . $this->emailFrom->text);
        }
    }

    public function sponsor() {
        if ($this->fromAuthDomain()) {
            error_log(
                "EMAIL: Sponsored request from: "
                . $this->emailFrom->text);

            $signUpCount = 0;

            foreach ($this->uniqueContactList() as $identifier) {
                $signUpCount++;
                $user = new User($this->cache, $this->config);
                $user->identifier = $identifier;
                $user->sponsor = $this->emailFrom;
                $user->signUp("", false, false, $this->senderName, $this->journeyType);
            }

            $email = new EmailResponse();
            $email->to = $this->emailFrom->text;
            $email->sponsor($signUpCount, $this->uniqueContactList());
            $email->send();
        } else {
            error_log(
                "EMAIL: Ignoring sponsored request from : "
                . $this->emailFrom->text);
        }
    }

    public function logRequest() {
        $orgAdmin = new OrgAdmin($this->emailFrom->text);

        if ($orgAdmin->authorised) {
            $report = new Report;
            $report->orgAdmin = $orgAdmin;
            error_log(
                "EMAIL: processing log request from : " . $this->emailFrom->text
                . " representing " . $orgAdmin->orgName);
            $subjectArray = explode(":", $this->emailSubject, 2);
            $reportType = strtolower(trim($subjectArray[0]));
            $pdf = new PDF();
            $criteria = "";
            if (count($subjectArray) > 1) {
                $criteria = trim($subjectArray[1]);
            }

            switch ($reportType) {
                case "topsites":
                    $report->topSites();
                    $pdf->encrypt = false;
                    error_log(
                        "Top Sites report generated records: "
                        . count($report->result));
                    break;
                case "topsites-alltime":
                    $report->topSitesAllTime();
                    $pdf->encrypt = false;
                    error_log(
                        "Top Sites Total report generated records: "
                        . count($report->result));
                    break;
                case "sitelist":
                    $report->siteList();
                    error_log(
                        "Site list generated records: "
                        . count($report->result));
                    break;
                case "site":
                    // TODO: Error handling if criteria is empty.
                    $report->bySite($criteria);
                    error_log(
                        "Site list generated records: "
                        . count($report->result));
                    break;
                case "user":
                    // TODO: Error handling if criteria is empty.
                    $report->byUser($criteria);
                    error_log(
                        "User report generated records: "
                        . count($report->result));
                    break;
                default:
                    $report->byOrgId();
                    error_log(
                        "Report by Org ID generated records: "
                        . count($report->result));
                    break;
            }

            // Create report pdf
            $pdf->populateLogRequest($orgAdmin);
            $pdf->landscape = true;
            $pdf->generatePDF($report);

            // Create email response and attach the pdf
            $email = new EmailResponse;
            $email->to = $orgAdmin->email;
            $email->fileName = $pdf->filename;
            $email->filePath = $pdf->filepath;
            $email->logRequest();
            $email->send($orgAdmin->emailManagerAddress);

            // Create sms response for the code if the pdf is encrypted
            if ($pdf->encrypt) {
                $sms = new SmsResponse($orgAdmin->mobile);
                $sms->sendLogRequestPassword($pdf);
            }
        }
    }

    public function newSite() {
        $this->emailSubject = str_ireplace("re: ", "", $this->emailSubject);
        $orgAdmin = new OrgAdmin($this->emailFrom->text);
        if ($orgAdmin->authorised) {
            error_log(
                "EMAIL: processing new site request from : "
                . $this->emailFrom->text);

            // Add the new site & IP addresses
            $outcome = "Existing site updated\n";
            $site = new Site();
            $site->loadByAddress($this->emailSubject);
            $action = "updated";

            if (!$site->id) {
                $site->org_id = $orgAdmin->orgId;
                $site->org_name = $orgAdmin->orgName;
                $site->name = $this->emailSubject;
                error_log(
                    "EMAIL: creating new site : " . $site->name);
                $outcome = "New site created\n";
                $site->setRadKey();
                if ($site->updateFromEmail($this->emailBody))
                    $outcome .= "Site attributes updated\n";
                $site->writeRecord();
                $action = "created";
            } else if ($site->updateFromEmail($this->emailBody)) {
                error_log(
                    "EMAIL: updating site attributes : " . $site->name);
                $outcome .= "Site attributes updated\n";
                $site->writeRecord();
            }

            $newSiteIPs = $this->ipList();
            if (count($newSiteIPs) > 0) {
                error_log(
                    "EMAIL: Adding client IP addresses : " . $site->name);
                $outcome .= count($newSiteIPs) . " RADIUS IP Addresses added\n";
                $site->addIPs($newSiteIPs);
            }

            $newSiteSourceIPs = $this->sourceIpList();
            if (count($newSiteSourceIPs) > 0) {
                error_log(
                    "EMAIL: Adding source IP addresses : " . $site->name);
                $outcome .=
                    count($newSiteIPs) . " Source IP Address ranges added\n";
                $site->addSourceIPs($newSiteSourceIPs);
            }


            // Create email response and attach the pdf
            $email = new EmailResponse;
            $email->to = $orgAdmin->email;
            if ($outcome) {
                $email->newSite($action, $outcome, $site);
                // Create the site information pdf
                $pdf = new PDF();
                $pdf->populateNewSite($site);
                $report = new Report;
                $report->orgAdmin = $orgAdmin;
                $report->getIPList($site);
                $pdf->generatePDF($report);
                $email->fileName = $pdf->filename;
                $email->filePath = $pdf->filepath;
                // Create sms response for the code
                $sms = new SmsResponse($orgAdmin->mobile);
                $sms->sendNewsitePassword($pdf);
            } else {
                $email->newSiteBlank($site);
            }
            $email->send($orgAdmin->emailManagerAddress);

        } else {
            error_log(
                "EMAIL: Ignoring new site request from : "
                . $this->emailFrom->text);
        }
    }

    /**
     * Extracts the unique list of contacts (mobile number or email address) from
     * the body of the email.
     *
     * @return array Identifier instances.
     */
    public function uniqueContactList() {
        $list = [];

        foreach (preg_split("/((\r?\n)|(\r\n?))/", $this->emailBody)
                as $line) {
            $contact = new Identifier(trim($line));
            if ($contact->validEmail || $contact->validMobile) {
                $list[] = $contact;
            }
        }

        return array_unique($list);
    }

    /**
     * Extracts the list of IP addresses from the emailBody attribute.
     *
     * @return array
     */
    public function ipList() {
        $list = array();

        foreach (preg_split("/((\r?\n)|(\r\n?))/", $this->emailBody)
                as $ipAddr) {
            $ipAddr = preg_replace('/[^0-9.]/', '', $ipAddr);
            if (filter_var($ipAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 |
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $list[] = $ipAddr;
            }
        }

        return $list;
    }

    /**
     * Extract an IP range from the emailBody attribute.
     * Expects the range to be in "minIP - maxIP" format.
     *
     * @return array
     */
    public function sourceIpList() {
        $list = array();

        foreach (preg_split("/((\r?\n)|(\r\n?))/", $this->emailBody)
                as $ipAddr) {
            $ipAddr = preg_replace('/[^-0-9.]/', '', $ipAddr);
            $ipAddr = explode("-", $ipAddr);
            if (count($ipAddr) == 2
                    && filter_var($ipAddr[0], FILTER_VALIDATE_IP,
                            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE |
                            FILTER_FLAG_NO_RES_RANGE)
                    && filter_var($ipAddr[1], FILTER_VALIDATE_IP,
                            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE |
                            FILTER_FLAG_NO_RES_RANGE)) {
                $list[] = array("min" => $ipAddr[0], 'max' => $ipAddr[1]);
            }
        }

        return $list;
    }

    public function fromAuthDomain() {
        return preg_match(
                $this->config->values['authorised-domains'],
                $this->emailFrom->text);
    }

    /**
     * @param string $senderName
     */
    public function setSenderName($senderName) {
        $this->senderName = $senderName;
    }

    public function setEmailSubject($subject) {
        $this->emailSubject = $subject;
    }

    /**
     * Sets the emailBody attribute based on the input string.
     *
     * If the input is a multipart message in MIME format, it takes the first part marked with
     * plain text or html content type, with the plain text taking precedence if both present.
     * Otherwise the full input string is used.
     *
     * In every case the input string is converted to lowercase and stripped of html tags.
     *
     * @param $email
     */
    public function setEmailBody($email) {
        $email = strtolower($email);
        $body  = $this->extractTextBody($email);
        if (empty($body)) {
            $body = $email;
        }
        $this->emailBody = strip_tags($body);
    }

    /**
     * Recursive function to extract plain text or HTML parts of a multipart MIME message.
     *
     * @param string $email The full/partial message.
     * @param string $ignoreBoundary Boundary to ignore - as the first part will contain the current one.
     * @return string The found plain text or html part of the message, or empty string.
     */
    private function extractTextBody($email, $ignoreBoundary = "") {
        $body          = "";
        $boundary      = "";
        $matches       = [];

        if (preg_match("/boundary=\"(.*)\"/", $email, $matches)) {
            $boundary = $matches[1];
        } else if (preg_match("/boundary=([A-Za-z0-9_\-]+)/", $email, $matches)) {
            $boundary = $matches[1];
        }

        if (!empty($boundary) && $boundary != $ignoreBoundary) {
            foreach (explode("--" . $boundary, $email) as $part) {
                $textBody = $this->extractTextBody($part, $boundary);
                if (!empty($textBody)) {
                    return $textBody;
                }
            }
        } else {
            if (!strpos($email, self::CONTENT_PLAIN_TEXT) == false) {
                $body = $this->ignoreSignature($email);
            } else if (!strpos($email, self::CONTENT_HTML) == false) {
                $body = $this->ignoreSignature($email);
            }
        }
        return $body;
    }

    private function ignoreSignature($emailBody) {
        if (!strpos($emailBody, "--") == false) {
            return strstr($emailBody, "--", true);
        }
        return $emailBody;
    }

    public function setEmailTo($to) {
        $this->emailTo = $to;
        $this->emailToCMD = strtolower(trim(strtok($this->emailTo, "@")));
    }

    public function setEmailFrom($from) {
        $this->emailFrom = new Identifier($from);
    }
}
