<?php
namespace DigitalPenguin\Commerce_Splitit\API;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SplititClient {

    /** @var ?Client */
    private ?Client $client;
    private bool $authMode = false;

    public function __construct(bool $testMode = true, bool $authMode = false, string $accessToken = '')
    {
        $this->authMode = $authMode;

        // Authorization request requires a different "Content-Type" header to all other requests.
        $headers = $this->authMode
            ? ['Content-Type' => 'application/x-www-form-urlencoded']
            : [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ];

        $this->client = new Client([
            'headers' => $headers,
            'base_uri' => $this->getEndpoint($testMode),
        ]);
    }

    /**
     * Creates an API request and actions it
     * @param string $resource
     * @param array $data
     * @param string $method
     * @return Response
     */
    public function request(string $resource, array $data, string $method = 'POST'): Response
    {
        // For authorization requests, data needs to be sent as "form_params".
        $type = $this->authMode ? 'form_params' : 'json';

        $payload = [
            $type => $data,
//            'debug' => true,
        ];
        if (!empty($data['headers'])) {
            $payload['headers'] = [
                $data['headers'],
            ];
        }

        try {
            $response = $this->client->request($method, $resource, $payload);
            return Response::from($response);
        }
        catch (GuzzleException $e) {
            $errorResponse = new Response(false, 0);
            $errorResponse->addError(get_class($e), $e->getMessage());
            return $errorResponse;
        }
    }

    /**
     * Returns either sandbox or production endpoint depending on Commerce's test mode.
     * @param bool $testMode
     * @return string
     */
    private function getEndpoint(bool $testMode): string
    {
        $type = $testMode ? 'sandbox' : 'production';

        return $this->authMode
            ? "https://id.$type.splitit.com/"
            : "https://web-api-v3.$type.splitit.com/";
    }
}