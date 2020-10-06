<?php
namespace DigitalPenguin\Commerce_SplitIt\API;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SplitItClient {

    /** @var Client */
    private $client;

    public function __construct(string $region, string $uid, string $password, bool $testMode = true)
    {
        $this->client = new Client([
            'base_uri' => $this->_getEndpoint($region, $testMode),
            'auth' => [$uid, $password, 'basic'],
            'http_errors' => false,
        ]);
    }

    public function request(string $resource, array $data, string $method = 'POST'): Response
    {
        try {
            $response = $this->client->request($method, $resource, [
                'json' => $data,
            ]);
            return Response::from($response);
        } catch (GuzzleException $e) {
            $errorResponse = new Response(false, 0);
            $errorResponse->addError(get_class($e), $e->getMessage());
            return $errorResponse;
        }
    }

    private function _getEndpoint(string $region, bool $testMode): string
    {
        switch ($region) {
            case 'EU':
                return !$testMode ? 'https://api.klarna.com/' : 'https://api.playground.klarna.com/';
            case 'NA':
                return !$testMode ? 'https://api-na.klarna.com/' : 'https://api-na.playground.klarna.com/';
            case 'OC':
                return !$testMode ? 'https://api-oc.klarna.com/' : 'https://api-oc.playground.klarna.com/';
        }

        return '';
    }
}