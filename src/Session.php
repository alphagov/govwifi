<?php
namespace Alphagov\GovWifi;

use PDO;
use PDOException;

class Session {
    public $id;
    /**
     * @deprecated
     */
    public $inOctets;
    /**
     * @deprecated
     */
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
        $sessionRecord['Start']               = $this->startTime;
        $sessionRecord['siteIP']              = $this->siteIP;
        $sessionRecord['mac']                 = $this->mac;
        $sessionRecord['ap']                  = $this->ap;
        $sessionRecord['building_identifier'] = $this->buildingIdentifier;
        return $sessionRecord;
    }

    /**
     * @deprecated
     * @return float
     */
    public function inMB() {
        return round($this->inOctets / 1000000);
    }

    /**
     * @deprecated
     * @return float
     */
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
        $db = DB::getInstance();
        $dbLink = $db->getConnection();
        $handle = $dbLink->prepare(
                "update sessions set " . $stopField .
                " building_identifier=:buildingID " .
                "where siteIP=:siteIP and username=:username " .
                    "and stop is null and mac=:mac " .
                    "and start between :startmin and :startmax");
        // Some (300/11500) sessions are started multiple times in the same second, can
        // NOT use "order by start desc limit 1" here for now.
        // TODO (afoldesi-gds): Validate this logic further.
        $startMin = strftime('%Y-%m-%d %H:%M:%S',$this->startTime-$window);
        $startMax = strftime('%Y-%m-%d %H:%M:%S',$this->startTime+$window);
        error_log(
                "Updating record between " .
                $startMin . " and " . $startMax . " for " . $this->login);
        $handle->bindValue(':startmin',   $startMin,                 PDO::PARAM_STR);
        $handle->bindValue(':startmax',   $startMax,                 PDO::PARAM_STR);
        $handle->bindValue(':siteIP',     $this->siteIP,             PDO::PARAM_STR);
        $handle->bindValue(':username',   $this->login,              PDO::PARAM_STR);
        $handle->bindValue(':mac',        $this->mac,                PDO::PARAM_STR);
        $handle->bindValue(':buildingID', $this->buildingIdentifier, PDO::PARAM_STR);
        $affectedRows = 0;
        try {
            $dbLink->beginTransaction();
            $handle->execute();
            $affectedRows = $handle->rowCount();
            $dbLink->commit();
        } catch (PDOException $e) {
            $dbLink->rollBack();
            error_log("Exception while updating sessions: " .
                $e->getMessage() . "|Trace: " . implode("|" . $e->getTrace()));
        }
        if ($affectedRows > 0) {
            error_log("Session record updated. " .
                $startMin . " and " . $startMax . " for " . $this->login . " Rows: " . $affectedRows);
        } else {
            error_log("Session update failed. " .
                $startMin . " and " . $startMax . " for " . $this->login);
        }
    }
}
