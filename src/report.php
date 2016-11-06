<?php
namespace Alphagov\GovWifi;

class report {
    public $orgAdmin;
    public $result;
    public $subject;
    public $columns;

    public function getIPList($site) {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $sql = "select ip, site.address, site.radkey from site, siteip " .
               "where site.id = siteip.site_id " .
               "and org_id = ? " .
               "and site.address = ?";
        $handle = $dblink->prepare($sql);
        $handle->bindValue(1, $site->org_id, PDO::PARAM_INT);
        $handle->bindValue(2, $site->name, PDO::PARAM_STR);
        $handle->execute();
        $this->result = $handle->fetchAll(\PDO::FETCH_NUM);
        $this->subject = "List of IP Addresses configured for" . $site->name;
        $this->columns = array(
            "IP Address",
            "Site Name",
            "RADIUS Secret");

    }

    function siteList() {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $sql = "select organisation.name, site.address from "
                . "site, organisation "
                . "where organisation.id=site.org_id "
                . "order by organisation.name, site.address";
        $handle = $dblink->prepare($sql);
        $handle->execute();
        $this->result = $handle->fetchAll(\PDO::FETCH_NUM);
        $this->subject = "List of sites subscribed to GovWifi";
        $this->columns = array("Organisation", "Site Name");
    }

    function topSites() {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $sql = 'select name, count(distinct username) as usercount '
                . 'from logs '
                . 'where start > DATE_SUB(NOW(), INTERVAL 30 DAY) '
                . 'group by shortname '
                . 'having usercount > 2 order by usercount desc';
        $handle = $dblink->prepare($sql);
        $handle->execute();
        $this->result = $handle->fetchAll(\PDO::FETCH_NUM);
        $this->subject = "Sites by number of unique users in the last 30 days";
        $this->columns = array("Site Name", "Users");
    }

    function byOrgId() {

        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $sql = "select start,username,shortname,InMB, OutMB "
                . "from logs "
                . "where org_id = ?";
        $handle = $dblink->prepare($sql);
        $handle->bindValue(1, $this->orgAdmin->org_id, PDO::PARAM_INT);
        $handle->execute();
        $this->result = $handle->fetchAll(\PDO::FETCH_NUM);
        $this->subject = "All authentications for "
                . $this->orgAdmin->org_name
                . " sites";
        $this->columns = array(
            "Date/Time",
            "Username",
            "Site Name",
            "Up MB",
            "Down MB");
    }

    function bySite($site) {
        $db = DB::getInstance();
        $dblink = $db->getConnection();
        $sql = "select start,stop,username, InMB,OutMB,mac,ap "
                . "from logs where org_id = ? and shortname = ?";
        $handle = $dblink->prepare($sql);
        $handle->bindValue(1, $this->orgAdmin->org_id, PDO::PARAM_INT);
        $handle->bindValue(2, $site, PDO::PARAM_INT);
        $handle->execute();
        $this->result = $handle->fetchAll(\PDO::FETCH_NUM);
        $this->subject = "All authentications for " . $site;
        $this->columns = array(
            "Start",
            "Stop",
            "Username",
            "Up MB",
            "Down MB",
            "MAC",
            "AP");
    }

    function byUser($user) {
        $db = DB::getInstance();
        $dblink = $db->getConnection();

        $sql = "select start,stop,contact,sponsor from logs where username = ?";
        $handle = $dblink->prepare($sql);
        $handle->bindValue(1, $this->orgAdmin->org_id, PDO::PARAM_INT);
        $handle->bindValue(2, $site, PDO::PARAM_INT);
        $handle->execute();
        $this->result = $handle->fetchAll(\PDO::FETCH_NUM);
        $this->subject = "All authentications by the user " . $user;
        $this->columns = array(
            "Start",
            "Stop",
            "Identity",
            "Sponsor");

    }

    function statsUsersPerDay($orgAdmin, $site = null) {
        $db = DB::getInstance();
        $dblink = $db->getConnection();

        if ($site) {
            $sitesql = 'and shortname = ?';
        }
        $sql = 'select count(distinct(username)) as Users, '
                . 'date(start) as Date  '
                . 'from logs where org_id = ' . $this->orgAdmin->org_id . ' '
                . $sitesql
                . '  and start > DATE_SUB(NOW(), INTERVAL 30 DAY) '
                . 'group by Date order by Date desc';
        $handle = $dblink->prepare($sql);
        $handle->bindValue(1, $orgAdmin->org_id, PDO::PARAM_INT);
        $handle->bindValue(2, $site, PDO::PARAM_INT);
        $handle->execute();
        $this->result = $handle->fetchAll(\PDO::FETCH_NUM);
        $this->columns = array(
            "Date/Time",
            "Username",
            "Site Name",
            "Identity",
            "Sponsor");
    }

    function userIdentifier($orgAdmin, $user) {
        $dblink = $db->getConnection();
        $sql = "select start,username,reply,contact,sponsor "
                . "from logs where username = ?";
        $handle = $dblink->prepare($sql);
        $handle->bindValue(1, $orgAdmin->org_id, PDO::PARAM_INT);
        $handle->bindValue(2, $site, PDO::PARAM_INT);
        $handle->execute();
        $this->result = $handle->fetchAll(\PDO::FETCH_NUM);
    }
}

