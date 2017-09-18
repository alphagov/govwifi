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

    public function writeToDB($stop = true) {
        $window = 30; // Must match a session that starts within x seconds of the authentication
        $stopField = "";
        if ($stop) {
            $stopField = "stop=now(),";
        }
        $addAP = false;
        $apText = "";
        if (! empty($this->ap)) {
            $addAP = true;
            $apText = " and ap=:ap ";
        }
        $db = DB::getInstance();
        $dbLink = $db->getConnection();
        $handle = $dbLink->prepare(
                "update session set " . $stopField .
                    " inMB=:inMB, outMB=:outMB, building_identifier=:buildingID "
                . "where siteIP=:siteIP and username=:username "
                    . "and stop is null and mac=:mac " . $apText
                    . "and start between :startmin and :startmax");
        // Some (300/11500) sessions are started multiple times in the same second, can
        // NOT use "order by start desc limit 1" here for now.
        // TODO (afoldesi-gds): Validate this logic further.
        $startMin = strftime('%Y-%m-%d %H:%M:%S',$this->startTime-$window);
        $startMax = strftime('%Y-%m-%d %H:%M:%S',$this->startTime+$window);
        error_log(
                "Updating record between "
                . $startMin . " and " . $startMax . " for " . $this->login);
        $handle->bindValue(':startmin',   $startMin,                 PDO::PARAM_STR);
        $handle->bindValue(':startmax',   $startMax,                 PDO::PARAM_STR);
        $handle->bindValue(':siteIP',     $this->siteIP,             PDO::PARAM_STR);
        $handle->bindValue(':username',   $this->login,              PDO::PARAM_STR);
        $handle->bindValue(':mac',        $this->mac,                PDO::PARAM_STR);
        if ($addAP) {
            $handle->bindValue(':ap',     $this->ap,                 PDO::PARAM_STR);
        }
        $handle->bindValue(':inMB',       $this->inMB(),             PDO::PARAM_INT);
        $handle->bindValue(':outMB',      $this->outMB(),            PDO::PARAM_INT);
        $handle->bindValue(':buildingID', $this->buildingIdentifier, PDO::PARAM_STR);
        $handle->execute();
    }
}
