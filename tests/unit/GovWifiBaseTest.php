<?php

namespace Alphagov\GovWifi;

use PHPUnit_Framework_TestCase;

class GovWifiBaseTest extends PHPUnit_Framework_TestCase {

    /**
     * Test if nothing is thrown
     */
    public function testNotEmptyValidation() {
        try {
            GovWifiBase::checkNotEmpty(
                ['this', 'that'],
                ['this' => 'not empty', 'that' => 'is not either']
            );
        } catch (GovWifiException $e) {
            $this->fail('Unexpected exception: ' . $e->getMessage());
        }
        $this->addToAssertionCount(1);
    }

    /**
     * @expectedException \Alphagov\GovWifi\GovWifiException
     * @expectedExceptionMessage The field key is required in the params array.
     */
    public function testNotEmptyFailsForNull() {
        GovWifiBase::checkNotEmpty(['key'], array('key' => null));
    }

    /**
     * @expectedException \Alphagov\GovWifi\GovWifiException
     * @expectedExceptionMessage The field key is required in the params array.
     */
    public function testNotEmptyFailsForUnset() {
        GovWifiBase::checkNotEmpty(['key'], array());
    }

    /**
     * @expectedException \Alphagov\GovWifi\GovWifiException
     * @expectedExceptionMessage The field key is required in the params array.
     */
    public function testNotEmptyFailsForEmptyString() {
        GovWifiBase::checkNotEmpty(['key'], array('key' => ''));
    }

    /**
     * Test standard params validation passes.
     */
    public function testStandardParamsPass() {
        try {
            GovWifiBase::checkStandardParams([
                'config' => Config::getInstance(),
                'db'     => DB::getInstance(),
                'cache'  => Cache::getInstance()
            ]);
        } catch (GovWifiException $e) {
            $this->fail('Unexpected exception: ' . $e->getMessage());
        }
        $this->addToAssertionCount(1);
    }

    /**
     * @expectedException \Alphagov\GovWifi\GovWifiException
     * @expectedExceptionMessage Config class not recognised.
     */
    public function testStandardParamsValidationConfigFail() {
        GovWifiBase::checkStandardParams([
            'config' => Cache::getInstance()
        ]);
    }

    /**
     * @expectedException \Alphagov\GovWifi\GovWifiException
     * @expectedExceptionMessage DB class not recognised.
     */
    public function testStandardParamsValidationDBFail() {
        GovWifiBase::checkStandardParams([
            'db' => Config::getInstance()
        ]);
    }

    /**
     * @expectedException \Alphagov\GovWifi\GovWifiException
     * @expectedExceptionMessage Cache class not recognised.
     */
    public function testStandardParamsValidationCacheFail() {
        GovWifiBase::checkStandardParams([
            'cache' => Config::getInstance()
        ]);
    }
}
