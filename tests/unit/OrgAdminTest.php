<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class OrgAdminTest extends PHPUnit_Framework_TestCase {
    function testClassInstantiates() {
        $this->assertInstanceOf(OrgAdmin::class, new OrgAdmin("test@test.com"));
    }
}
