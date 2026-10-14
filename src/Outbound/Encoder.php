<?php

declare(strict_types=1);

namespace Echoproof\Laravel\Outbound;

use Echoproof\Laravel\Body;
use Echoproof\Laravel\Sanitizer;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;

final class Encoder
{
    public function __construct(private readonly Sanitizer $sanitizer)
    {
    }

    public function call(Request $request, ?Response $response): array
    {
        return [
            'request' => $this->request($request),
            'response' => $response === null
                ? ['status' => 0, 'headers' => new \stdClass, 'body' => ['text' => 'connection failed']]
                : $this->response($response),
        ];
    }

    public function request(Request $request): array
    {
        $headers = self::headers($request->headers());

        return [
            'method' => strtoupper($request->method()),
            'url' => $this->sanitizer->url($request->url()),
            'headers' => $this->sanitizer->headers($headers),
            'body' => $this->sanitizer->body(Body::decode($request->body(), $headers['content-type'] ?? null)),
        ];
    }

    private function response(Response $response): array
    {
        $headers = self::headers($response->headers());

        return [
            'status' => $response->status(),
            'headers' => $this->sanitizer->headers($headers),
            'body' => $this->sanitizer->body(Body::decode($response->body(), $headers['content-type'] ?? null)),
        ];
    }

    /** @return array<string, string> */
    private static function headers(array $headers): array
    {
        $flat = [];
        foreach ($headers as $name => $values) {
            $flat[strtolower((string) $name)] = implode(', ', array_map('strval', (array) $values));
        }
        ksort($flat);

        return $flat;
    }
}
