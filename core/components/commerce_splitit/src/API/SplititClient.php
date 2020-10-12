<?php
namespace DigitalPenguin\Commerce_Splitit\API;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SplititClient {

    /** @var Client */
    private $client;

    public function __construct(bool $testMode = true)
    {
        $this->client = new Client([
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'base_uri' => $this->_getEndpoint($testMode),
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

    private function _getEndpoint(bool $testMode): string
    {
        return !$testMode ? 'https://web-api.splitit.com' : 'https://web-api-sandbox.splitit.com';
    }
}