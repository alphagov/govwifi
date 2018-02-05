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
    public $emailManagerAddress;

    public function __construct($email) {
        $db = DB::getInstance();

        $this->email = $email;
        $handle = $db->getConnection()->prepare(
                'select id, mobile, orgname, name, email_manager_address from orgs_admins_view ' .
                'where email=?');
        $handle->bindValue(1, $this->email, PDO::PARAM_STR);
        $handle->execute();
        $row = $handle->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // if a row is returned then that user is an authorised contact
            $this->orgId               = $row['id'];
            $this->orgName             = $row['orgname'];
            $this->name                = $row['name'];
            $this->mobile              = $row['mobile'];
            $this->emailManagerAddress = $row['email_manager_address'];
            $this->authorised = true;
        } else {
            $this->authorised = false;
        }
    }
}
