<?php
namespace Alphagov\GovWifi;

use Exception;
use Memcached;
use PDO;

class User {
    public $identifier;
    public $login;
    public $password;
    public $sponsor;
    public $email;

    public function signUp($message = "", $force = false) {
        $this->setUsername();
        $this->loadRecord();
        if ($force) {
            $this->newPassword();
        }
        $this->radiusDbWrite();
        $this->sendCredentials($message);
    }

    public function kioskActivate($site_id) {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $handle = $dblink->prepare(
                'insert into activation (site_id, contact) '
                . 'values (:siteId,:contact)');
        $handle->bindValue(':siteId', $site_id, PDO::PARAM_INT);
        $handle->bindValue(':contact', $this->identifier->text, PDO::PARAM_STR);
        $handle->execute();
    }

    public function codeActivate($code) {
        $this->loadRecord();
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $handle = $dblink->prepare(
                'insert into activation (dailycode, contact) '
                . 'values (:dailycode,:contact)');
        $handle->bindValue(':dailycode', $code, PDO::PARAM_INT);
        $handle->bindValue(':contact', $this->identifier->text, PDO::PARAM_STR);
        $handle->execute();
        return $this->login;
    }

    public function codeVerify($code) {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $handle = $dblink->prepare(
                'select email from verify where code = :code');
        $handle->bindValue(':code', $code, PDO::PARAM_STR);
        $handle->execute();
        $row = $handle->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $handle = $dblink->prepare('delete from verify where code = :code');
            $handle->bindValue(':code', $code, PDO::PARAM_STR);
            $handle->execute();

            $handle = $dblink->prepare(
                    'update userdetails set email = :email '
                    . 'where contact = :contact');
            $handle->bindValue(':email', $row['email'], PDO::PARAM_STR);
            $handle->bindValue(':contact', $this->identifier->text,
                    PDO::PARAM_STR);
            $handle->execute();
        }
    }

    private function sendCredentials($message = "") {
        if ($this->identifier->validMobile) {
            $sms = new SmsResponse($this->identifier->text);
            $sms->setReply();
            $sms->sendCredentials($this, $message);
        } else if ($this->identifier->validEmail) {
            $email = new EmailResponse();
            $email->to = $this->identifier->text;
            $email->signUp($this);
        }
    }

    public function activatedHere($site) {
        if ($this->identifier->validMobile) {
            $db = DB::getInstance();
            $dblink = $db->getConnection();
            $handle = $dblink->prepare(
                    'SELECT IF ((date(now()) - max(date(`activated`)))'
                    . '<site.activation_days,"YES","NO") as valid, '
                    . 'IF (count(1)=0,"YES","NO") as firstvisit '
                    . 'from activation,site '
                    . 'WHERE (activation.site_id = site.id '
                    . 'OR activation.dailycode = site.dailycode) '
                    . 'AND site_id = ? AND contact = ?');
            $handle->bindValue(1, $site->id, PDO::PARAM_INT);
            $handle->bindValue(2, $this->identifier->text, PDO::PARAM_STR);
            $handle->execute();
            $row = $handle->fetch(PDO::FETCH_ASSOC);
            if ($row['valid'] == "YES") {
                return true;
            } else {
                if ($row['firstvisit'] == "YES") {
                    // Send text message the first time a user enters a building
                    error_log(
                        "SMS: Sending restricted building to " .
                        $this->identifier->text);
                    $sms = new SmsResponse($this->identifier->text);
                    $sms->setReply();

                    if ($this->email) {
                        $sms->sendRestrictedSiteHelpEmailSet($site);
                    } else {
                        $sms->sendRestrictedSiteHelpEmailUnset($site);
                    }
                    // Put an entry in the activations database with a date of 0
                    $handle = $dblink->prepare(
                            'insert into activation '
                            . '(activated, site_id, contact) values (0, ?, ?)');
                    $handle->bindValue(1, $site->id, PDO::PARAM_INT);
                    $handle->bindValue(2, $this->identifier->text,
                            PDO::PARAM_STR);
                    $handle->execute();
                }
                // TODO(afoldesi-gds): Discuss moving this to the last line.
                return false;
            }
        }
    }

    private function radiusDbWrite() {
        $db = DB::getInstance();
        $dblink = $db->getConnection();

        // Insert user record
        // TODO(afoldesi-gds): Discuss handling of sponsor - update this after
        // the db schema has changed.
        $handle = $dblink->prepare(
                'insert into userdetails (username, contact, email,password) '
                . 'VALUES (:login,:contact,:email,:password) '
                . 'ON DUPLICATE KEY UPDATE email=:email, password=:password');
        $handle->bindValue(':login', $this->login, PDO::PARAM_STR);
        $handle->bindValue(':contact', $this->identifier->text, PDO::PARAM_STR);
        $handle->bindValue(':email', $this->email, PDO::PARAM_STR);
        $handle->bindValue(':password', $this->password, PDO::PARAM_STR);
        $handle->execute();

        // Populate the record for the cache
        $userRecord['contact'] = $this->identifier->text;
        $userRecord['email'] = $this->email;
        $userRecord['sponsor'] = $this->sponsor->text;
        $userRecord['password'] = $this->password;

        // Write to memcache - we need to do this to flush old entries
        $m = Cache::getInstance();
        $m->m->set($this->login, $userRecord);
    }

    public function newPassword() {
        # This will force the generation of a new password for the user
        $this->password = $this->generateRandomWifiPassword();
    }

    public function loadRecord() {
        # This function looks for an existing password entry for this username
        # if it finds it and force is false then it will return the same password
        # otherwise it will return a randomly generated one
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $row = false;
        if ($this->login) {
            $m = Cache::getInstance();
            $userRecord = $m->m->get($this->login);

            if (!$userRecord) {
                $handle = $dblink->prepare(
                        'select * from userdetails where username=?');
                $handle->bindValue(1, $this->login, PDO::PARAM_STR);
                $handle->execute();
                $userRecord = $handle->fetch(PDO::FETCH_ASSOC);

                if ($m->m->getResultCode() == Memcached::RES_NOTFOUND
                    && $userRecord) {
                    // Not in cache but in the database - let's cache it for next time
                    $m->m->set($this->login, $userRecord);
                }
            }
        } else if ($this->identifier->validMobile) {
            $handle = $dblink->prepare(
                    'select * from userdetails where contact=?');
            $handle->bindValue(1, $this->identifier->text, PDO::PARAM_STR);
            $handle->execute();
            $userRecord = $handle->fetch(PDO::FETCH_ASSOC);
        }

        if ($userRecord) {
            $this->login = strtoupper($userRecord['username']);
            $this->password = $userRecord['password'];
            $this->identifier = new Identifier($userRecord['contact']);
            $this->sponsor = new Identifier($userRecord['sponsor']);
            $this->email = $userRecord['email'];
        } else {
            $this->newPassword();
        }
    }

    private function UsernameIsUnique($uname) {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $handle = $dblink->prepare('select count(username) as unamecount '
                . 'from userdetails where username=?');
        $handle->bindValue(1, $uname, PDO::PARAM_STR);
        $handle->execute();
        $row = $handle->fetch(PDO::FETCH_ASSOC);
        if ($row['unamecount'] == 0) {
            return true;
        } else {
            return false;
        }
    }

    private function setUsername() {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $handle = $dblink->prepare('select distinct username '
                . 'from userdetails where contact=?');
        $handle->bindValue(1, $this->identifier->text, PDO::PARAM_STR);
        $handle->execute();
        $row = $handle->fetch(PDO::FETCH_ASSOC);
        if ($row)        {
            $username = $row['username'];
        } else {
            $username = $this->generateRandomUsername();
            while (!$this->usernameIsUnique($username)) {
                $username = $this->generateRandomUsername();
            }
        }
        $this->login = $username;
    }


    private function generateRandomUsername() {
        $config = Config::getInstance();
        $length = $config->values['wifi-username']['length'];
        $pattern = $config->values['wifi-username']['regex'];
        $pass = preg_replace(
                $pattern, "",
                base64_encode($this->strongRandomBytes($length * 4)));
        return substr($pass, 0, $length);
    }

    function generateRandomWifiPassword() {
        $config = Config::getInstance();
        $password = "";
        if ($config->values['wifi-password']['random-words']) {
            $f_contents = file(
                    $config->values['wifi-password']['wordlist-file']);
            for ($x = 1; $x <= $config->values['wifi-password']['word-count'];
                    $x++) {
                $word = trim($f_contents[array_rand($f_contents)]);
                if ($config->values['wifi-password']['uppercase'])
                    $word = ucfirst($word);
                $password .= $word;
            }
        }

        if ($config->values['wifi-password']['random-chars']) {
            $length = $config->values['wifi-password']['length'];
            $pattern = $config->values['wifi-password']['regex'];
            $pass = preg_replace(
                    $pattern, "",
                    base64_encode($this->strongRandomBytes($length * 4)));
            $password = substr($pass, 0, $length);
        }
        return $password;
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
