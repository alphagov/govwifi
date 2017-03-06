<?php

namespace Alphagov\GovWifi;

use Aws\S3\S3Client;

/**
 * Factory class for Email Providers.
 *
 * @package Alphagov\GovWifi
 */
class EmailProviderFactory extends GovWifiBase {
    /**
     * Creates an instance of the specific email provider based on configuration settings and request data.
     *
     * @param Config $config
     * @param array $data The data received in the request if any.
     * @return EmailProvider (SnsEmailProvider)
     */
    public static function create($config, $data) {
        parent::checkNotEmpty(['config'], ['config' => $config]);
        parent::checkStandardParams(['config' => $config]);
        if ($config->environment) {
            // Define environment-specific email providers here if required.
        }
        return new SnsEmailProvider([
            'jsonData' => $data,
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
}
