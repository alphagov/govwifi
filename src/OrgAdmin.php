<?php
namespace Alphagov\GovWifi;

use PDO;

class OrgAdmin {
    public $email;
    public $orgName;
    public $orgId;
    public $mobile;
    public $name;
    public $authorised;

    public function __construct($email) {
        $db = DB::getInstance();
        $dbLink = $db->getConnection();
        
        $this->email = $email;
        $handle = $dbLink->prepare(
                'select id, mobile, orgname, name from orgs_admins_view ' .
                'where email=?');
        $handle->bindValue(1, $this->email, PDO::PARAM_STR);
        $handle->execute();
        $row = $handle->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // if a row is returned then that user is an authorised contact
            $this->orgId   = $row['id'];
            $this->orgName = $row['orgname'];
            $this->name    = $row['name'];
            $this->mobile  = $row['mobile'];
            $this->authorised = true;
        } else {
            $this->authorised = false;
        }
    }
}
