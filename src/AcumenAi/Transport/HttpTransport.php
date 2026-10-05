<?php

namespace AcumenAi\Transport;

use GuzzleHttp\Client;

class HttpTransport implements TransportInterface
{
    private Client $client;
    private string $endpoint;
    private string $keyId;
    private string $secret;

    public function __construct(string $endpoint, string $keyId, string $secret, ?Client $client = null)
    {
        $this->endpoint = rtrim($endpoint, '/');
        $this->keyId = $keyId;
        $this->secret = $secret;
        $this->client = $client ?? new Client([
            'timeout' => 5,
            'http_errors' => false,
        ]);
    }

    public function sendEvents(array $events): void
    {
        $body = json_encode(['events' => array_values($events)], JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $this->secret);

        $this->client->post($this->endpoint.'/v1/events', [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-acumen-key-id' => $this->keyId,
                'x-acumen-secret' => $this->secret,
                'x-acumen-timestamp' => $timestamp,
                'x-acumen-signature' => $signature,
            ],
            'body' => $body,
        ]);
    }

    public function guard(array $event): array
    {
        $response = $this->client->post($this->endpoint.'/v1/guard', [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-acumen-key-id' => $this->keyId,
                'x-acumen-secret' => $this->secret,
            ],
            'json' => $event,
            'timeout' => 1,
        ]);

        $json = json_decode((string) $response->getBody(), true) ?? [];

        return [
            'verdict' => $json['verdict'] ?? 'allow',
            'detections' => $json['detections'] ?? [],
            'latency_ms' => $json['latency_ms'] ?? 0,
        ];
    }
}
