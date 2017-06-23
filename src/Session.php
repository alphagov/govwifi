<?php
namespace Alphagov\GovWifi;

use PDO;

class Session {
    public $id;
    public $inOctets;
    public $outOctets;
    public $login;
    public $startTime;
    public $stopTime;
    public $mac;
    public $ap;
    public $buildingIdentifier;
    public $siteIP;

    /**
     * @var Cache The cache for retrieving initial session data.
     */
    private $cache;

    public function __construct($id, Cache $cache) {
        $this->id = $id;
        $this->cache = $cache;
        $this->loadFromCache();
    }

    /**
     * Build an associative array of the current field names => values,
     * ready to be saved to the Cache.
     *
     * @return array
     */
    public function sessionRecord() {
        $sessionRecord = array();
        $sessionRecord['login']               = $this->login;
        $sessionRecord['InO']                 = $this->inOctets;
        $sessionRecord['OutO']                = $this->outOctets;
        $sessionRecord['Start']               = $this->startTime;
        $sessionRecord['siteIP']              = $this->siteIP;
        $sessionRecord['mac']                 = $this->mac;
        $sessionRecord['ap']                  = $this->ap;
        $sessionRecord['building_identifier'] = $this->buildingIdentifier;
        return $sessionRecord;
    }

    public function inMB() {
        return round($this->inOctets / 1000000);
    }

    public function outMB() {
        return round($this->outOctets / 1000000);
    }

    /**
     * Load a record corresponding to the current ID from
     * the cache.
     */
    public function loadFromCache() {
        $sessionRecord = $this->cache->get($this->id);
        if ($sessionRecord) {
            $this->login              = $sessionRecord['login'];
            $this->inOctets           = $sessionRecord['InO'];
            $this->outOctets          = $sessionRecord['OutO'];
            $this->startTime          = $sessionRecord['Start'];
            $this->siteIP             = $sessionRecord['siteIP'];
            $this->mac                = $sessionRecord['mac'];
            $this->ap                 = $sessionRecord['ap'];
            $this->buildingIdentifier = $sessionRecord['building_identifier'];
        }
    }

    /**
     * Remove the record corresponding to the current ID from
     * the cache, if exists.
     */
    public function deleteFromCache() {
        $this->cache->delete($this->id);
    }

    /**
     * Write the current fields' value to the cache, using the
     * current ID as the key.
     */
    public function writeToCache() {
        $this->cache->set($this->id, $this->sessionRecord());
    }

    public function writeToDB() {
        $window = 30; // Must match a session that starts within x seconds of the authentication
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $handle = $dblink->prepare(
                "update session set stop=now(), inMB=:inMB, outMB=:outMB "
                . "where siteIP=:siteIP and username=:username "
                . "and stop is null and mac=:mac and ap=:ap "
                . "and start between :startmin and :startmax");
        // TODO (afoldesi-gds): Validate this logic.
        $startmin = strftime('%Y-%m-%d %H:%M:%S',$this->startTime-$window);
        $startmax = strftime('%Y-%m-%d %H:%M:%S',$this->startTime+$window);
        error_log(
                "Updating record between "
                . $startmin. " and ".$startmax." for ".$this->login);
        $handle->bindValue(':startmin', $startmin , PDO::PARAM_STR);
        $handle->bindValue(':startmax', $startmax , PDO::PARAM_STR);
        $handle->bindValue(':siteIP', $this->siteIP, PDO::PARAM_STR);
        $handle->bindValue(':username', $this->login, PDO::PARAM_STR);
        $handle->bindValue(':mac', $this->mac, PDO::PARAM_STR);
        $handle->bindValue(':ap', $this->ap, PDO::PARAM_STR);
        $handle->bindValue(':building_identifier', $this->buildingIdentifier, PDO::PARAM_STR);
        $handle->bindValue(':inMB', $this->inMB(), PDO::PARAM_INT);
        $handle->bindValue(':outMB', $this->outMB(), PDO::PARAM_INT);
        $handle->execute();
    }
}
