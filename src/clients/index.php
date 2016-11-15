<?php
namespace Alphagov\GovWifi;

require "../common.php";

use PDO;

if (Config::getInstance()->values["frontendApiKey"] == $_REQUEST['key']) {
    $db = DB::getInstance();
    $dbLink = $db->getConnection();
    $handle = $dbLink->prepare(
        'select ip, radkey from siteip 
         left join site on (siteip.site_id = site.id)');
    $handle->execute();

    while ($result = $handle->fetch(PDO::FETCH_ASSOC)) {
        $clientName = str_replace('.', '-', $result['ip']);
        $clientIp = $result['ip'];
        $clientSecret = $result['radkey'];
        print 'client ' . $clientName . ' {
        ipaddr = ' . $clientIp . '
        secret = ' . $clientSecret . '
        }
';
    }
}
