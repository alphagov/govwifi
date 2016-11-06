<?php
namespace Alphagov\GovWifi;

class emailRequest {
    public $emailFrom;
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
            $email = new emailResponse;
            $email->to = $this->emailFrom->text;
            $email->verify($code);
            $email->send();
        }
    }

    private function generateRandomVerifyCode() {
        $config = config::getInstance();
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

    public function enroll() {
        // Self enrollment request
        if ($this->fromAuthDomain()) {
            error_log("EMAIL: Self Enrolling : " . $this->emailFrom->text);
            $user = new user;
            $user->identifier = $this->emailFrom;
            $user->sponsor = $this->emailFrom;
            $user->enroll();
        } else {
            error_log(
                    "EMAIL: Ignoring self enrollment from : "
                    . $this->emailFrom->text);
        }
    }

    public function sponsor() {
        if ($this->fromAuthDomain()) {
            error_log(
                "EMAIL: Sponsored request from: "
                . $this->emailFrom->text);

            $enrollcount = 0;
            foreach ($this->contactList() as $identifier) {
                $enrollcount++;
                $user = new user;
                $user->identifier = $identifier;
                $user->sponsor = $this->emailFrom;
                $user->enroll();
            }
            $email = new emailResponse();
            $email->to = $this->emailFrom->text;
            $email->sponsor($enrollcount);
            $email->send();
        } else {
            error_log(
                "EMAIL: Ignoring sponsored reqeust from : "
                . $this->emailFrom->text);
        }
    }

    public function logRequest() {
        $orgAdmin = new orgAdmin($this->emailFrom->text);

        if ($orgAdmin->authorised) {
            $report = new report;
            $report->orgAdmin = $orgAdmin;
            error_log(
                "EMAIL: processing log request from : " . $this->emailFrom->text
                . " representing " . $orgAdmin->org_name);
            $subjectArray = explode(":", $this->emailSubject, 2);
            $reportType = strtolower(trim($subjectArray[0]));
            $pdf = new pdf;
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
                case "sitelist":
                    $report->siteList();
                    error_log(
                        "Site list generated records: "
                        . count($report->result));
                    break;
                case "site":
                    $report->bySite($criteria);
                    error_log(
                        "Site list generated records: "
                        . count($report->result));
                    break;
                case "user":
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
            $email = new emailResponse;
            $email->to = $orgAdmin->email;
            $email->logrequest();
            $email->filename = $pdf->filename;
            $email->filepath = $pdf->filepath;
            $email->send();
            // Create sms response for the code
            $sms = new smsResponse($orgAdmin->mobile);
            $sms->sendLogrequestPassword($pdf);
        }
    }

    public function newSite() {
        $this->emailSubject = str_ireplace("re: ", "", $this->emailSubject);
        $db = DB::getInstance();
        $dblink = $db->getConnection();

        $orgAdmin = new orgAdmin($this->emailFrom->text);
        if ($orgAdmin->authorised) {
            error_log(
                "EMAIL: processing new site request from : "
                . $this->emailFrom->text);
            // Add the new site & IP addresses
            $outcome = "Existing site updated\n";
            $site = new site();
            $site->loadByAddress($this->emailSubject);
            $action = "updated";
            if (!$site->id) {
                $site->org_id = $orgAdmin->org_id;
                $site->org_name = $orgAdmin->org_name;
                $site->name = $this->emailSubject;
                error_log(
                    "EMAIL: creating new site : " . $site->name);
                $outcome = "New site created\n";
                $site->setRADKey();
                if ($site->updateFromEmail($this->emailBody))
                    $outcome .= "Site attributes updated\n";
                $site->writeRecord();
                $action = "created";
            } else if ($site->updateFromEmail($this->emailBody)) {
                error_log(
                    "EMAIL: updating site atributes : " . $site->name);
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
            $pdf = new pdf;
            $pdf->populateNewSite($site);
            $report = new report;
            $report->orgAdmin = $orgAdmin;
            $report->getIPList($site);
            $pdf->generatePDF($report);
            // Create email response and attach the pdf
            $email = new emailResponse;
            $email->to = $orgAdmin->email;
            if ($outcome) {
                $email->newSite($action,$outcome,$site);
            } else {
                $email->newSiteBlank($site);
            }
            $email->filename = $pdf->filename;
            $email->filepath = $pdf->filepath;
            $email->send();
            // Create sms response for the code
            $sms = new smsResponse($orgAdmin->mobile);
            $sms->sendNewsitePassword($pdf);

        } else {
            error_log(
                "EMAIL: Ignoring new site request from : "
                . $this->emailFrom->text);
        }
    }

    // TODO(afoldesi-gds): Unused.
    private function extractMobileNo() {
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $this->emailBody)
                as $contact) {
            $contact = new identifier(trim($contact));
            if ($contact->validMobile) {
                return $contact;
            }
        }
    }

    private function contactList() {
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $this->emailBody)
                as $contact) {
            $contact = new identifier(trim($contact));
            if ($contact->validEmail or $contact->validMobile) {
                $list[] = $contact;
            }
        }
        return $list;
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
        $config = config::getInstance();
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
        $this->emailFrom = new identifier($from);
    }
}
