<?php
namespace Alphagov\GovWifi;

use Exception;
use PHPUnit_Framework_TestCase;

class UserTest extends PHPUnit_Framework_TestCase {
    function testClassInstantiates() {
        $this->assertInstanceOf(User::class, new User(Cache::getInstance(), Config::getInstance()));
    }

    function testRandomCharacterGenerationReturnsCorrectLength() {
        $user = new User(Cache::getInstance(), Config::getInstance());
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
        $user = new User(Cache::getInstance(), Config::getInstance());
        $user->getRandomCharacters("/.*/", 6);
    }

    function testUserNameIsLowercase() {
        $user = new User(Cache::getInstance(), Config::getInstance());
        $userName = $user->generateRandomUsername();
        self::assertEquals(strtolower($userName), $userName);
    }
}
