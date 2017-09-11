<?php
namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;
use Exception;

class DBTest extends PHPUnit_Framework_TestCase {

    function testClassInstantiates() {
        $this->assertInstanceOf(DB::class, DB::getInstance());
    }

    function testDefaultClassCreatesTheSameConnection() {
        $db1 = DB::getInstance();
        $db2 = DB::getInstance(DB::DB_TYPE_DEFAULT);
        $this->assertTrue($db1->getConnection() === $db2->getConnection());
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage DB type not recognized. [unknown]
     */
    function testUnknownDBTypeThrowsException() {
        DB::getInstance("unknown");
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage DB name is required.
     */
    function testRRConnectionThrowsExceptionForNow() {
        DB::getInstance(DB::DB_TYPE_READ_REPLICA);
    }
}
