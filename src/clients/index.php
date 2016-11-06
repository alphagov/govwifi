<?php
namespace Alphagov\GovWifi;

if ($_REQUEST['key'] == "xp93rDXY65DKQ5IiKlUC0sN0WDwj0v") {

    require "../common.php";

    $db = DB::getInstance();
    $dblink = $db->getConnection();
    $handle = $dblink->prepare('select nasname, secret from nas');
    $handle->execute();

    while ($result = $handle->fetch(PDO::FETCH_ASSOC)) {
        $clientName = str_replace('.', '-', $result['nasname']);
        $clientIp = $result['nasname'];
        $clientSecret = $result['secret'];
        print 'client ' . $clientName . ' {
        ipaddr = ' . $clientIp . '
        secret = ' . $clientSecret . '
        }
';
    }
}
