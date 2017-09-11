<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class DBTest extends PHPUnit_Framework_TestCase {

    function testClassInstantiates() {
        $this->assertInstanceOf(DB::class, DB::getInstance());
    }

    function testDefaultClassCreatesTheSameConnection() {
        $db1 = DB::getInstance();
        $db2 = DB::getInstance(DB::DB_TYPE_DEFAULT);
        $this->assertTrue($db1->getConnection() === $db2->getConnection());
    }
}
