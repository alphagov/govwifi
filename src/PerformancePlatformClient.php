<?php

namespace Alphagov\GovWifi;

use GuzzleHttp\Psr7\Request;
use RuntimeException;
use Http\Adapter\Guzzle6\Client as Guzzle6Client;

/**
 * Simple client to send data to the Performance Platform.
 *
 * @package Alphagov\GovWifi
 */
class PerformancePlatformClient {
    const VALID_PERIODS = [
        'day',
        'week',
        'month',
        'quarter'
    ];

    /**
     * @var string
     */
    protected $serviceName;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * PerformancePlatformClient constructor.
     *
     * @param array $config An array containing the serviceName and the baseUrl
     * as required by the Performance Platform.
     * @throws GovWifiException
     */
    public function __construct($config) {
        $config = array_merge([
                'serviceName' => null,
                'baseUrl'     => null
            ],
            $config
        );

        if (empty($config['serviceName'])) {
            throw new GovWifiException("The service name is required.");
        }
        if (empty($config['baseUrl']) || filter_var($config['baseUrl'], FILTER_VALIDATE_URL) === false) {
            throw new GovWifiException("The baseUrl is required and it must be a valid URL.");
        }
        $this->serviceName = $config['serviceName'];
        $this->baseUrl     = $config['baseUrl'];
    }

    /**
     * Sends the data provided to the Performance Platform.
     *
     * @param array $config The following fields are mandatory
     * 'bearerToken'   The auth token sent in the http request header
     * 'timestamp'     The timestamp the data relates to
     * 'dataType'      The name of the metric, used in the url construction as well as being sent
     * 'period'        The period the data describes.
     * 'categoryName'  Name of the category used, eg. channel, stage, etc.
     * 'categoryValue' The actual value of the category.
     *
     * @param array $data Associative array, passed through as-is.
     * @throws GovWifiException
     */
    public function sendData($config, $data) {
        $requiredFields = [
            'bearerToken'   => null,
            'timestamp'     => null,
            'dataType'      => null,
            'period'        => null,
            'categoryName'  => null,
            'categoryValue' => null
        ];
        $config = array_merge($requiredFields, $config);

        foreach ($requiredFields as $key => $value) {
            if (empty($config[$key])) {
                throw new GovWifiException("The field " . $key . " is required in the config array.");
            }
        }
        if (! in_array($config['period'], self::VALID_PERIODS)) {
            throw new GovWifiException("The period provided [" . $config['period'] . "] is not recognised.");
        }

        $payload = array_merge([
                '_id'       => base64_encode(
                    $config['timestamp'] .
                    $this->serviceName .
                    $config['period'] .
                    $config['dataType'] .
                    $config['categoryValue']
                ),
                '_timestamp' => $config['timestamp'],
                'dataType'   => $config['dataType'],
                'period'     => $config['period'],
                $config['categoryName'] => $config['categoryValue']
            ],
            $data);
        try {
            // TODO: Check response?
            $this->httpPostJson($this->buildUrl($config['dataType']), $config['bearerToken'], $payload);
        } catch (RuntimeException $e) {
            throw new GovWifiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Sends an HTTP POST request to the url provided. The payload array is json encoded before sending
     *
     * @param string $url
     * @param string $bearerToken metric-specific authorisation token
     * @param array $payload
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws RuntimeException if the request fails.
     */
    private function httpPostJson($url, $bearerToken, $payload) {
        $payload = json_encode($payload);
        // TODO: Inject http client.
        $httpClient = new Guzzle6Client();

        $request = new Request(
            'POST',
            $url,
            $this->buildHttpHeaders($bearerToken, strlen($payload)),
            $payload
        );
        return $httpClient->sendRequest($request);
    }

    /**
     * Builds an array of the http headers required for POSTing the API.
     *
     * @param string $bearerToken metric-specific authorisation token
     * @param int $contentLength the character length of the payload
     * @return array
     */
    private function buildHttpHeaders($bearerToken, $contentLength){
        return [
            'Content-type'   => 'application/json',
            'Authorization'  => 'Bearer ' . $bearerToken,
            'Content-length' => $contentLength
        ];
    }

    /**
     * Builds a Performance Platform API url using the base URL, service name and metric.
     * @param string $metric
     * @return string The API URL for the metric provided.
     */
    private function buildUrl($metric) {
        return implode("/", [
            rtrim($this->baseUrl, "/ "),
            $this->serviceName,
            $metric
        ]);
    }
}