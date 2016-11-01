<?php

class site {
    public $radKey;
    public $kioskKey;
    public $name;
    public $org_id;
    public $org_name;
    public $id;
    public $activationRegex;
    public $activationDays;
    public $postcode;
    public $dataController;
    public $address;
    public $dailyCode;
    public $dailyCodeDate;

    public function writeRecord() {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $handle = $dblink->prepare('insert into site (id, radkey, kioskkey, datacontroller, address, postcode, activation_regex, activation_days, org_id)
         VALUES (:id, :radkey, :kioskkey, :datacontroller, :address, :postcode, :activation_regex, :activation_days, :org_id)
                on duplicate key update radkey=:radkey, kioskkey=:kioskkey, datacontroller=:datacontroller, address=:address
                ,postcode=:postcode, activation_regex=:activation_regex, activation_days=:activation_days, org_id = :org_id, dailycode=:dailycode, dailycodedate=:dailycodedate');
        $handle->bindValue(':id', $this->id, PDO::PARAM_INT);
        $handle->bindValue(':radkey', $this->radKey, PDO::PARAM_STR);
        $handle->bindValue(':kioskkey', $this->kioskKey, PDO::PARAM_STR);
        $handle->bindValue(':datacontroller', $this->dataController, PDO::PARAM_STR);
        $handle->bindValue(':address', $this->name, PDO::PARAM_STR);
        $handle->bindValue(':postcode', $this->postcode, PDO::PARAM_STR);
        $handle->bindValue(':activation_regex', $this->activationRegex, PDO::PARAM_STR);
        $handle->bindValue(':activation_days', $this->activationDays, PDO::PARAM_STR);
        $handle->bindValue(':org_id', $this->org_id, PDO::PARAM_INT);
        $handle->bindValue(':dailycode', $this->dailyCode, PDO::PARAM_STR);
        $handle->bindValue(':dailycodedate', $this->dailyCodeDate, PDO::PARAM_INT);
        $handle->execute();
        if (!$this->id) {
            $this->id = $dblink->lastInsertId();
        }
    }

    public function getDailyCode() {
        if ($this->dailyCodeDate <> date("z")) {
            $config = config::getInstance();
            $length = $config->values['daily-code']['length'];
            $pattern = $config->values['daily-code']['regex'];
            $pass = preg_replace(
                    $pattern, "",
                    base64_encode($this->strongRandomBytes($length * 4)));
            $this->dailyCode = substr($pass, 0, $length);
            $this->dailyCodeDate = date("z");
            $this->writeRecord();
        }

        return $this->dailyCode;
    }

    private function loadRow($row) {
        $this->name = $row['address'];
        $this->postcode = $row['postcode'];
        $this->org_id = $row['org_id'];
        $this->dataController = $row['datacontroller'];
        $this->activationRegex = $row['activation_regex'];
        $this->activationDays = $row['activation_days'];
        $this->id = $row['site_id'];
        $this->radKey = $row['radkey'];
        $this->kioskKey = $row['kioskkey'];
        $this->org_name = $row['org_name'];
        $this->dailyCode = $row['dailycode'];
        $this->dailyCodeDate = $row['dailycodedate'];
    }

    public function getWhitelist() {
        $whitelist = str_replace("$|",", ",$this->activationRegex);
        $whitelist = str_replace("$","",$whitelist);
        return $whitelist;
    }

    public function attributesText() {
        $attributes = "Postcode: ".$this->postcode."\n";
        $attributes .= "Activation-whitelist: ".$this->getWhitelist()."\n";
        $attributes .= "Activation-days: ".$this->activationDays."\n";
        $attributes .= "DataController: ".$this->dataController."\n";
        return $attributes;
    }

    // TODO (afoldesi-gds): This should be the job of the servicedesk. Probably.
    public function updateFromEmail($emailBody) {
        $updated = FALSE;
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $emailBody) as $line) {
            $line = str_replace(">","",$line);
            $line = str_replace("*","",$line);
            $line = trim($line);
            $parameter = strtolower(trim(substr($line, 0, strpos($line,":"))));

            $value = substr($line, strpos($line,":")+1);

            switch ($parameter) {
                case "postcode":
                error_log("*".$parameter."*");
                $this->postcode = $value;
                $updated = TRUE;
                break;
                case "activation-whitelist":
                    error_log("*".$parameter."*");
                    $value = str_replace(" ","",$value);
                    $value = str_replace(",","$|",$value);
                    $value .="$";
                    error_log("activation_regex:/".$value."/");
                    $this->activationRegex = $value;
                    $updated = TRUE;
                break;
                case "activation-days":
                    error_log("*".$parameter."*");
                    $this->activationDays = $value;
                    $updated = TRUE;
                break;
                case "datacontroller":
                    error_log("*".$parameter."*");
                    $this->dataController = $value;
                    $updated = TRUE;
                break;
            }
        }
        return $updated;
    }

    public function loadByKioskIp($ipAddr) {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $handle = $dblink->prepare('select site.id as site_id,
                                    radkey,
                                    kioskkey,
                                    datacontroller,
                                    address,
                                    postcode,
                                    activation_regex,
                                    activation_days,
                                    org_id,
                                    organisation.name as org_name,
                                    dailycode,
                                    dailycodedate
                                    from site, organisation, sourceip
                                    WHERE organisation.id = site.org_id
                                    and site.id=sourceip.site_id
                                    and ? between sourceip.min
                                    and sourceip.max');
        $handle->bindValue(1, ip2long($ipAddr), PDO::PARAM_INT);
        $handle->execute();
        $row = $handle->fetch(\PDO::FETCH_ASSOC);
        $this->loadRow($row);
    }

    public function loadByIp($ipAddr) {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $handle = $dblink->prepare('select site.id as site_id,
                                    radkey,
                                    kioskkey,
                                    datacontroller,
                                    address,
                                    postcode,
                                    activation_regex,
                                    activation_days,
                                    org_id,
                                    organisation.name as org_name,
                                    dailycode,
                                    dailycodedate
                                    from site, organisation, siteip
                                    WHERE organisation.id = site.org_id
                                    and site.id=siteip.site_id
                                    and siteip.ip = ?');
        $handle->bindValue(1, $ipAddr, PDO::PARAM_STR);
        $handle->execute();
        $row = $handle->fetch(\PDO::FETCH_ASSOC);
        $this->loadRow($row);
    }

    public function loadByAddress($address) {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $handle = $dblink->prepare('select site.id as site_id,
                                    radkey,
                                    kioskkey,
                                    datacontroller,
                                    address,
                                    postcode,
                                    activation_regex,
                                    activation_days,
                                    org_id,
                                    organisation.name as org_name,
                                    dailycode,
                                    dailycodedate
                                    from site, organisation
                                    WHERE organisation.id = site.org_id
                                    and site.address = ?');
        $handle->bindValue(1, $address, PDO::PARAM_STR);
        $handle->execute();
        $row = $handle->fetch(\PDO::FETCH_ASSOC);
        $this->loadRow($row);
    }

    public function addIPs($iplist) {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        foreach ($iplist as $ip_addr) {
            $handle = $dblink->prepare(
                    'insert into siteip (ip, site_id) VALUES (?,?)');
            $handle->bindValue(1, $ip_addr, PDO::PARAM_STR);
            $handle->bindValue(2, $this->id, PDO::PARAM_INT);
            try {
                $handle->execute();
            } catch (PDOException $e) {
                // if it already exists the insert will fail, silently continue.
            }
        }
    }

    public function addSourceIPs($iplist) {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        foreach ($iplist as $ip_addr) {
            $handle = $dblink->prepare('insert into sourceip (min, max, site_id) VALUES (?,?,?)');
            $handle->bindValue(1, ip2long($ip_addr['min']), PDO::PARAM_INT);
            $handle->bindValue(2, ip2long($ip_addr['max']), PDO::PARAM_INT);
            $handle->bindValue(3, $this->id, PDO::PARAM_INT);
            try {
                $handle->execute();
            } catch (PDOException $e) {
                // if it already exists the insert will fail, silently continue.
            }
        }
    }

    public function setRadkey() {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $handle = $dblink->prepare('select secret, kioskkey from nas WHERE shortname=? and org_id=?');
        $handle->bindValue(1, $this->name, PDO::PARAM_STR);
        $handle->bindValue(2, $this->org_id, PDO::PARAM_INT);
        $handle->execute();
        $row = $handle->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $this->radKey = $row['secret'];
            $this->kioskKey = $row['kioskkey'];
        } else {
            $this->generateRandomRadKey();
            $this->generateRandomKioskKey();
        }
    }

    private function generateRandomRadKey() {
        $config = config::getInstance();
        $length = $config->values['radius-password']['length'];
        $pattern = $config->values['radius-password']['regex'];
        $pass = preg_replace(
                $pattern, "",
                base64_encode($this->strongRandomBytes($length * 4)));
        $this->radKey = substr($pass, 0, $length);
    }

    private function generateRandomKioskKey() {
        $config = config::getInstance();
        $length = $config->values['kiosk-password']['length'];
        $pattern = $config->values['kiosk-password']['regex'];
        $pass = preg_replace(
                $pattern, "",
                base64_encode($this->strongRandomBytes($length * 10)));
        $this->kioskKey = substr($pass, 0, $length);
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
}

?>
