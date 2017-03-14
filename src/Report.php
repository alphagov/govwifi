<?php
namespace Alphagov\GovWifi;

use PDO;

class Report {
    public $orgAdmin;
    public $result;
    public $subject;
    public $columns;

    public function getIPList(Site $site) {
        $db = DB::getInstance();
        $dbLink = $db->getConnection();
        $sql = "select ip, site.radkey from site, siteip "
                . "where site.id = siteip.site_id "
                . "and org_id = ? "
                . "and site.address = ?";
        $handle = $dbLink->prepare($sql);
        $handle->bindValue(1, $site->org_id, PDO::PARAM_INT);
        $handle->bindValue(2, $site->name, PDO::PARAM_STR);
        $handle->execute();
        $this->result = $handle->fetchAll(PDO::FETCH_NUM);
        $this->subject = "List of IP Addresses configured for " . $site->name;
        $this->columns = array(
            "IP Address",
            "RADIUS Secret");
    }

    function siteList() {
        $db = DB::getInstance();
        $dbLink = $db->getConnection();
        $sql = "select t2.name, t1.address from
                site t1 left join organisation t2
                on t1.org_id = t2.id
                where t1.org_id = ?
                order by t2.name, t1.address";
        $handle = $dbLink->prepare($sql);
        $handle->bindValue(1, $this->orgAdmin->orgId);
        $handle->execute();
        $this->result = $handle->fetchAll(PDO::FETCH_NUM);
        $this->subject = "List of sites subscribed to GovWifi";
        $this->columns = array("Organisation", "Site Name");
    }

    function topSites() {
        $db = DB::getInstance();
        $dbLink = $db->getConnection();
        $sql = "select name, count(distinct username) as usercount
                from logs
                where start > DATE_SUB(NOW(), INTERVAL 30 DAY)
                group by shortname
                having usercount > 2 order by usercount desc";
        $handle = $dbLink->prepare($sql);
        // TODO: We should restrict this functionality.
        //$handle->bindValue(1, $this->orgAdmin->orgId);
        $handle->execute();
        $this->result = $handle->fetchAll(PDO::FETCH_NUM);
        $this->subject = "Sites by number of unique users in the last 30 days";
        $this->columns = array("Site Name", "Users");
    }

    function topSitesAllTime() {
        $db = DB::getInstance();
        $dbLink = $db->getConnection();
        $sql = "select org.name, sum(total) as usercount 
                from 
                (select siteIP, count(distinct(username)) as total from session group by siteIP) t1 
                left join siteip on (siteip.ip = t1.siteIP) 
                left join site on (siteip.site_id = site.id) 
                left join organisation org on (site.org_id = org.id) 
                where org.name is not null group by org.name order by usercount desc";
        $handle = $dbLink->prepare($sql);
        // TODO: We should restrict this functionality.
        //$handle->bindValue(1, $this->orgAdmin->orgId);
        $handle->execute();
        $this->result = $handle->fetchAll(PDO::FETCH_NUM);
        $this->subject = "Sites by number of unique users";
        $this->columns = array("Site Name", "Users");
    }

    function byOrgId() {
        $db = DB::getInstance();
        $dbLink = $db->getConnection();
        $sql = "select start, username, shortname, InMB, OutMB
                from logs
                where org_id = ?";
        $handle = $dbLink->prepare($sql);
        $handle->bindValue(1, $this->orgAdmin->orgId, PDO::PARAM_INT);
        $handle->execute();
        $this->result = $handle->fetchAll(PDO::FETCH_NUM);
        $this->subject = "All authentications for "
                . $this->orgAdmin->orgName
                . " sites";
        $this->columns = array(
            "Date/Time",
            "Username",
            "Site Name",
            "Up MB",
            "Down MB");
    }

    function bySite($siteShortName) {
        $db = DB::getInstance();
        $dbLink = $db->getConnection();
        $sql = "select start, stop, username, InMB, OutMB, mac, ap
                from logs where org_id = ? and shortname = ?";
        $handle = $dbLink->prepare($sql);
        $handle->bindValue(1, $this->orgAdmin->orgId, PDO::PARAM_INT);
        $handle->bindValue(2, $siteShortName, PDO::PARAM_INT);
        $handle->execute();
        $this->result = $handle->fetchAll(PDO::FETCH_NUM);
        $this->subject = "All authentications for " . $siteShortName;
        $this->columns = array(
            "Start",
            "Stop",
            "Username",
            "Up MB",
            "Down MB",
            "MAC",
            "AP");
    }

    function byUser($userName) {
        $db = DB::getInstance();
        $dbLink = $db->getConnection();
        $sql = "select start, stop, contact, sponsor from logs where username = ?";
        $handle = $dbLink->prepare($sql);
        $handle->bindValue(1, $userName, PDO::PARAM_INT);
        $handle->execute();
        $this->result = $handle->fetchAll(PDO::FETCH_NUM);
        $this->subject = "All authentications by the user " . $userName;
        $this->columns = array(
            "Start",
            "Stop",
            "Identity",
            "Sponsor");
    }

    function statsUsersPerDay($site = null) {
        $db = DB::getInstance();
        $dbLink = $db->getConnection();

        $siteSql = "";
        if (!empty($site)) {
            $siteSql = 'and shortname = ?';
        }
        $sql = 'select count(distinct(username)) as Users,
                date(start) as Date
                from logs where org_id = ? '
                . $siteSql
                . ' and start > DATE_SUB(NOW(), INTERVAL 30 DAY)
                group by Date order by Date desc';
        $handle = $dbLink->prepare($sql);
        $handle->bindValue(1, $this->orgAdmin->orgId, PDO::PARAM_INT);
        if (!empty($site)) {
            $handle->bindValue(2, $site, PDO::PARAM_INT);
        }
        $handle->execute();
        $this->result = $handle->fetchAll(PDO::FETCH_NUM);
        $this->columns = array(
            "Date/Time",
            "Username",
            "Site Name",
            "Identity",
            "Sponsor");
    }
}
