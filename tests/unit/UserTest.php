<?php
namespace Alphagov\GovWifi;

use Exception;
use PHPUnit_Framework_TestCase;

class UserTest extends PHPUnit_Framework_TestCase {
    function testClassInstantiates() {
        $this->assertInstanceOf(User::class, new User(Cache::getInstance()));
    }

    function testRandomCharacterGenerationReturnsCorrectLength() {
        $user = new User(Cache::getInstance());
        $userName = $user->getRandomCharacters("/[^A-Z]/", 6);
        $this->assertEquals(6, strlen($userName));

        $password = $user->getRandomCharacters("/[^abcdefgijkmnopqrstwxyzABCDEFGHJKLMNPQRSTWXYZ23456789]/", 8);
        $this->assertEquals(8, strlen($password));
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Tried too many times, exiting random char generation.
     */
    function testRandomCharacterGenerationThrowsException() {
        $user = new User(Cache::getInstance());
        $user->getRandomCharacters("/.*/", 6);
    }
}
