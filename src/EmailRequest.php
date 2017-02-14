<?php
namespace Alphagov\GovWifi;

use Exception;
use PDO;
use PDOException;

class EmailRequest {
    public $emailFrom;

    /**
     * @var string
     */
    public $senderName;
    public $emailTo;
    public $emailToCMD;
    public $emailBody;
    public $emailSubject;

    public function verify() {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $handle = $dblink->prepare('delete from verify where email = :email');
        $handle->bindValue(':email', $this->emailFrom->text, PDO::PARAM_STR);
        $handle->execute();
        $handle = $dblink->prepare(
                'insert into verify (code, email) values (:code,:email)');
        $handle->bindValue(':email', $this->emailFrom->text, PDO::PARAM_STR);
        $attempts=0;
        $success=false;
        while ($success==false and $attempts<10) {
            try {
                $attempts++;
                $code = $this->generateRandomVerifyCode();
                $handle->bindValue(':code', $code, PDO::PARAM_STR);
                $handle->execute();
                $success=true;
            } catch (PDOException $e) {
                $success=false;
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
        $config = Config::getInstance();
        $length = $config->values['verify-code']['length'];
        $pattern = $config->values['verify-code']['regex'];
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
            $user = new User(Cache::getInstance(), Config::getInstance());
            $user->identifier = $this->emailFrom;
            $user->sponsor = $this->emailFrom;
            $user->signUp();
        } else {
            error_log(
                    "EMAIL: Ignoring signup from : "
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
                $user = new User(Cache::getInstance(), Config::getInstance());
                $user->identifier = $identifier;
                $user->sponsor = $this->emailFrom;
                $user->signUp();
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
                    $pdf->encrypt = FALSE;
                    error_log(
                        "Top Sites report generated records: "
                        . count($report->result));
                    break;
                case "topsites-alltime":
                    $report->topSitesAllTime();
                    $pdf->encrypt = FALSE;
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
            $email->filepath = $pdf->filepath;
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
            if (count($newSiteIPs) >0) {
                error_log(
                    "EMAIL: Adding client IP addresses : " . $site->name);
                $outcome .= count($newSiteIPs) . " RADIUS IP Addresses added\n";
                $site->addIPs($newSiteIPs);
            }

            $newSiteSourceIPs = $this->sourceIpList();
            if (count($newSiteSourceIPs) >0) {
                error_log(
                    "EMAIL: Adding source IP addresses : " . $site->name);
                $outcome .=
                    count($newSiteIPs) . " Source IP Address ranges added\n";
                $site->addSourceIPs($newSiteSourceIPs);
            }

            // Create the site information pdf
            $pdf = new PDF();
            $pdf->populateNewSite($site);
            $report = new Report;
            $report->orgAdmin = $orgAdmin;
            $report->getIPList($site);
            $pdf->generatePDF($report);
            // Create email response and attach the pdf
            $email = new EmailResponse;
            $email->to = $orgAdmin->email;
            if ($outcome) {
                $email->newSite($action, $outcome, $site);
            } else {
                $email->newSiteBlank($site);
            }
            $email->fileName = $pdf->filename;
            $email->filepath = $pdf->filepath;
            $email->send($orgAdmin->emailManagerAddress);
            // Create sms response for the code
            $sms = new SmsResponse($orgAdmin->mobile);
            $sms->sendNewsitePassword($pdf);

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

    private function ipList() {
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

    private function sourceIpList() {
        $list = array();

        foreach (preg_split("/((\r?\n)|(\r\n?))/", $this->emailBody)
                as $ipAddr) {
            $ipAddr = preg_replace('/[^-0-9.]/', '', $ipAddr);
            $ipAddr = explode("-",$ipAddr);
            if (count($ipAddr) == 2
                    and filter_var($ipAddr[0], FILTER_VALIDATE_IP,
                            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE |
                            FILTER_FLAG_NO_RES_RANGE)
                    and filter_var($ipAddr[1], FILTER_VALIDATE_IP,
                            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE |
                            FILTER_FLAG_NO_RES_RANGE)) {
                $list[] = array("min" => $ipAddr[0],'max' => $ipAddr[1]);
            }
        }
        return $list;
    }


    public function fromAuthDomain() {
        $config = Config::getInstance();
        return preg_match(
                $config->values['authorised-domains'],
                $this->emailFrom->text);
    }

    public function setEmailSubject($subject) {
        $this->emailSubject = $subject;
    }

    public function setEmailBody($body) {
        $this->emailBody = strip_tags(strtolower($body));
    }

    public function setEmailTo($to) {
        $this->emailTo = $to;
        $this->emailToCMD = strtolower(trim(strtok($this->emailTo, "@")));
    }

    public function setEmailFrom($from) {
        $this->emailFrom = new Identifier($from);
    }
}
