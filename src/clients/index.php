<?php
namespace Alphagov\GovWifi;

use PDO;

if ($_REQUEST['key'] == "xp93rDXY65DKQ5IiKlUC0sN0WDwj0v") {

    require "../common.php";

    $db = DB::getInstance();
    $dblink = $db->getConnection();
    $handle = $dblink->prepare('select ip, radkey from siteip left join site on (siteip.site_id = site.id)');
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
