<?php
namespace Alphagov\GovWifi;

require dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "common.php";

if (! empty($_REQUEST['key']) && Config::getInstance()->values["frontendApiKey"] === $_REQUEST['key']) {
    if (!empty($_REQUEST['org_id']) && is_numeric($_REQUEST['org_id'])) {
        $bulkRegistration = new BulkRegistration(Config::getInstance(), DB::getInstance(), Cache::getInstance());
        $bulkRegistration->sendBulkEmails($_REQUEST['org_id']);
    } else {
        header("HTTP/1.1 400 Bad Request");
    }
} else if (! (strtolower(substr(php_sapi_name(), 0, 3)) === 'cli')) {
    header("HTTP/1.1 404 Not Found");
}
