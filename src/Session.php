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
    public $siteIP;

    public function __construct($id) {
        $this->id = $id;
        $this->loadFromCache();
    }

    public function sessionRecord() {
        $sessionRecord['login'] = $this->login;
        $sessionRecord['InO'] = $this->inOctets;
        $sessionRecord['OutO'] = $this->outOctets;
        $sessionRecord['Start'] = $this->startTime;
        $sessionRecord['siteIP'] = $this->siteIP;
        $sessionRecord['mac'] = $this->mac;
        $sessionRecord['ap'] = $this->ap;
        return $sessionRecord;
    }

    public function inMB() {
        return round($this->inOctets / 1000000);
    }

    public function outMB() {
        return round($this->outOctets / 1000000);
    }

    public function loadFromCache() {
        $m = Cache::getInstance();
        $sessionRecord = $m->m->get($this->id);
        if ($sessionRecord) {
            $this->login = $sessionRecord['login'];
            $this->inOctets = $sessionRecord['InO'];
            $this->outOctets = $sessionRecord['OutO'];
            $this->startTime = $sessionRecord['Start'];
            $this->siteIP = $sessionRecord['siteIP'];
            $this->mac = $sessionRecord['mac'];
            $this->ap = $sessionRecord['ap'];
        }
    }

    public function deleteFromCache() {
        $m = Cache::getInstance();
        $m->m->delete($this->id);
    }

    public function writeToCache() {
        $m = Cache::getInstance();
        $m->m->set($this->id, $this->SessionRecord());
    }

    public function writeToDB() {
        $window = 30; // Must match a session that starts within x seconds of the authentication
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $handle = $dblink->prepare(
                "update sessions set stop=now(), inMB=:inMB, outMB=:outMB "
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
        $handle->bindValue(':inMB', $this->inMB(), PDO::PARAM_INT);
        $handle->bindValue(':outMB', $this->outMB(), PDO::PARAM_INT);
        $handle->execute();
    }
}
