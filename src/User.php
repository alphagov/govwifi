<?php
namespace Alphagov\GovWifi;

use Exception;
use PDO;

class User {
    const RANDOM_BYTES_LENGTH_MULTIPLIER = 4;
    /**
     * @var Identifier
     */
    public $identifier;
    public $login;
    public $password;
    /**
     * @var Identifier
     */
    public $sponsor;
    public $email;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var Config
     */
    private $config;

    public function __construct(Cache $cache, Config $config) {
        $this->cache  = $cache;
        $this->config = $config;
    }

    /**
     * Sign up a new user to the service, send email or sms notification based on the user's main contact.
     *
     * @param string $message The extra message received in the text to select the appropriate sms template
     * to respond with.
     * @param bool $force Force the creation of a new password
     * @param bool $selfSignup To select self-signup or sponsored email template.
     * @param string $senderName The display name of the sender if present in the email's from field.
     * @param string $journey The SMS journey type to decide the template id to use
     */

    public function signUp($message = "",
                           $force = false,
                           $selfSignup = true,
                           $senderName = "",
                           $journey = SmsRequest::SMS_JOURNEY_TERMS) {
        $this->setUsername();
        $this->loadRecord();
        if ($force) {
            $this->newPassword();
        }
        $this->radiusDbWrite();
        $this->sendCredentials($message, $selfSignup, $senderName, $journey);
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

    /**
     * Send the generated credentials to the user, in an email or text message, based on the user's main contact.
     *
     * @param string $message The extra message received in the text to select the appropriate sms template
     * to respond with.
     * @param bool $selfSignup To select self-signup or sponsored email template.
     * @param string $senderName The display name of the sender if present in the email's from field.
     * @param string $journey The SMS journey type to decide the template id to use
     */
    private function sendCredentials($message = "",
                                     $selfSignup = true,
                                     $senderName = "",
                                     $journey = SmsRequest::SMS_JOURNEY_TERMS) {
        if ($this->identifier->validMobile) {
            $sms = new SmsResponse($this->identifier->text);
            $sms->setReply();
            $sms->sendCredentials($this, $message, $journey);
        } else if ($this->identifier->validEmail) {
            $email = new EmailResponse();
            $email->to = $this->identifier->text;
            $email->signUp($this, $selfSignup, $senderName);
        }
    }

    public function activatedHere(Site $site) {
        if ($this->identifier->validMobile) {
            $db = DB::getInstance();
            $dblink = $db->getConnection();
            $handle = $dblink->prepare(
                    'SELECT IF ((date(now()) - max(date(`activated`)))
                    <site.activation_days,"YES","NO") as valid,
                    IF (count(1)=0,"YES","NO") as firstvisit
                    from activation,site
                    WHERE (activation.site_id = site.id
                    OR activation.dailycode = site.dailycode)
                    AND site_id = ? AND contact = ?');
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
        $dbLink = $db->getConnection();

        // Insert user record
        $handle = $dbLink->prepare(
                'insert into userdetails (username, contact, sponsor, password, email) '
                . 'VALUES (:login, :contact, :sponsor, :password, :email) '
                . 'ON DUPLICATE KEY UPDATE email=:email, password=:password');
        $handle->bindValue(':login',    $this->login,            PDO::PARAM_STR);
        $handle->bindValue(':contact',  $this->identifier->text, PDO::PARAM_STR);
        $handle->bindValue(':sponsor',  $this->sponsor->text,    PDO::PARAM_STR);
        $handle->bindValue(':password', $this->password,         PDO::PARAM_STR);
        $handle->bindValue(':email',    $this->email,            PDO::PARAM_STR);

        $handle->execute();

        // Populate the record for the cache
        $userRecord['contact']  = $this->identifier->text;
        $userRecord['email']    = $this->email;
        $userRecord['sponsor']  = $this->sponsor->text;
        $userRecord['password'] = $this->password;

        // Write to cache - we need to do this to flush old entries
        $this->cache->set($this->login, $userRecord);
    }

    /**
     * Forces the generation of a new password for the user.
     */
    public function newPassword() {
        $this->password = $this->generateRandomWifiPassword();
    }

    public function loadRecord() {
        # This function looks for an existing password entry for this username
        # if it finds it and force is false then it will return the same password
        # otherwise it will return a randomly generated one
        $db = DB::getInstance();
        $dblink = $db->getConnection();

        if ($this->login) {
            $userRecord = $this->cache->get($this->login);

            if (!$userRecord) {
                $handle = $dblink->prepare(
                        'select * from userdetails where username=?');
                $handle->bindValue(1, $this->login, PDO::PARAM_STR);
                $handle->execute();
                $userRecord = $handle->fetch(PDO::FETCH_ASSOC);

                if ($this->cache->itemWasNotFound() && $userRecord) {
                    // Not in cache but in the database - let's cache it for next time
                    $this->cache->set($this->login, $userRecord);
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
            $this->password = $userRecord['password'];
            $this->identifier = new Identifier($userRecord['contact']);
            $this->sponsor = new Identifier($userRecord['sponsor']);
            $this->email = $userRecord['email'];
        } else {
            $this->newPassword();
        }
    }

    private function usernameIsUnique($uname) {
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

     /**
     * Generates a random username. Makes sure that the generated string is lowercase.
     *
     * @return string
     */
    public function generateRandomUsername() {
        $length = $this->config->values['wifi-username']['length'];
        $pattern = $this->config->values['wifi-username']['regex'];

        $userName = $this->getRandomCharacters($pattern, $length);
        return strtolower($userName);
    }

    /**
     * Generates a random password for the user.
     *
     * There are 2 versions, depending on configuration:
     * - Random words: words randomly chosen from a configuration-defined word list file,
     * the actual count of words used comes from the config too.
     * - Random chars: Randomly generated characters derived from a base64-encoded string,
     * with length and exclusion regex set in the config.
     *
     * @return string
     */
    public function generateRandomWifiPassword() {
        $password = "";
        if ($this->config->values['wifi-password']['random-words']) {
            $f_contents = file(
                    $this->config->values['wifi-password']['wordlist-file']);
            for ($x = 1; $x <= $this->config->values['wifi-password']['word-count'];
                    $x++) {
                $word = trim($f_contents[array_rand($f_contents)]);
                if ($this->config->values['wifi-password']['uppercase'])
                    $word = ucfirst($word);
                $password .= $word;
            }
        }

        if ($this->config->values['wifi-password']['random-chars']) {
            $length = $this->config->values['wifi-password']['length'];
            $pattern = $this->config->values['wifi-password']['regex'];

            $password = $this->getRandomCharacters($pattern, $length);
        }
        return $password;
    }

    /**
     * Generate a set of random characters based on the base64 encoded random bytes provided
     * by the openssl_random_pseudo_bytes function.
     *
     * @param string $exclusionPattern Regex pattern to exclude certain characters from the result.
     * @param integer $length The character length of the resulting string.
     * @return string
     * @throws Exception If we tried 100 times to generate an appropriate-length string and failed.
     */
    public function getRandomCharacters($exclusionPattern, $length) {
        $randomChars = preg_replace(
            $exclusionPattern,
            "",
            $this->strongRandomBytes(
                $length * self::RANDOM_BYTES_LENGTH_MULTIPLIER
            )
        );
        // Need to retry as we can't guarantee that the exclusion regex will not cut too many characters.
        $tries = 0;
        while ($tries < 100 && strlen($randomChars) < $length) {
            $randomChars = preg_replace(
                $exclusionPattern,
                "",
                $this->strongRandomBytes(
                    $length * self::RANDOM_BYTES_LENGTH_MULTIPLIER
                )
            );
            $tries++;
        }
        if (100 == $tries) {
            throw new Exception("Tried too many times, exiting random char generation.");
        }
        return substr($randomChars, 0, $length);
    }

    /**
     * Generates a base64 encoded string derived from random bytes provided by
     * the openssl_random_pseudo_bytes function.
     *
     * @param integer $length The number of bytes.
     * @return string The base64-encoded representation of the bytes generated.
     * @throws Exception If the system did not use a cryptographically strong algorithm
     * to generate the random bytes.
     */
    private function strongRandomBytes($length) {
        $strong = false; // Flag for whether a strong algorithm was used
        $bytes = openssl_random_pseudo_bytes($length, $strong);
        if (!$strong) {
            // System did not use a cryptographically strong algorithm
            throw new Exception('Strong algorithm not available for PRNG.');
        }
        return base64_encode($bytes);
    }
}
