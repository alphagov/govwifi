<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class SiteTest extends PHPUnit_Framework_TestCase {
    function testClassInstantiates() {
        $this->assertInstanceOf(Site::class, new Site());
    }
}
