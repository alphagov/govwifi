<?php
namespace Alphagov\GovWifi;

use Aws\S3\S3Client;

class SnsEmailProviderTest extends \PHPUnit_Framework_TestCase {
    /**
     * SnsEmailProvider @var
     */
    private $snsEmailProvider;

    public function setUp() {
        parent::setUp();
        $config = Config::getInstance();
        $this->snsEmailProvider = new SnsEmailProvider([
            'jsonData' => json_encode(array(
                "Message" => json_encode(
                    array(
                        'mail' => array('commonHeaders' => array(
                            'to'   => array("\"'signup@wifi.service.gov.uk'\" <signup@wifi.service.gov.uk>"),
                            'from' => array("\"'signup@wifi.service.gov.uk'\" <signup@wifi.service.gov.uk>")
                        )),
                        'receipt' => array('action' => array(
                            'bucketName' => 'bucket',
                            'objectKey' => 'dummyObjectKey'
                        ))
                    )
            ))),
            's3Client' => new S3Client([
                'version' => 'latest',
                'region'  => 'eu-west-1',
                'credentials' => [
                    'key'    => $config->values['AWS']['Access-keyID'],
                    'secret' => $config->values['AWS']['Access-key']
                ]
            ])
        ]);
    }

    public function testClassInstantiates() {
        $this->assertInstanceOf(SnsEmailProvider::class, $this->snsEmailProvider);

    }

    public function testPeculiarSenderName() {
        self::assertEquals("signup@wifi.service.gov.uk", $this->snsEmailProvider->getSenderName());
    }

    public function testPeculiarToField() {
        self::assertEquals("signup@wifi.service.gov.uk", $this->snsEmailProvider->getEmailTo());
    }
}
