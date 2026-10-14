<?php

declare(strict_types=1);

namespace Echoproof\Laravel;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class Recorder
{
    public function __construct(
        private readonly array $config,
        private readonly Sanitizer $sanitizer,
        private readonly SnapshotWriter $writer,
    ) {
    }

    public function wants(Request $request): bool
    {
        if (! ($this->config['enabled'] ?? false)) {
            return false;
        }
        $path = $request->path();
        $only = $this->config['only'] ?? [];
        if ($only !== [] && ! Str::is($only, $path)) {
            return false;
        }
        if (Str::is($this->config['except'] ?? [], $path)) {
            return false;
        }
        if (str_starts_with((string) $request->header('content-type'), 'multipart/')) {
            return false;
        }
        $rate = (float) ($this->config['sample_rate'] ?? 1.0);

        return $rate >= 1.0 || mt_rand() / mt_getrandmax() < $rate;
    }

    public function record(Request $request, Response $response, int $latencyMs): void
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return;
        }
        $requestBytes = $request->getContent();
        $responseBytes = $response->getContent();
        $limit = (int) ($this->config['max_body_bytes'] ?? PHP_INT_MAX);
        if (! is_string($responseBytes) || strlen($requestBytes) > $limit || strlen($responseBytes) > $limit) {
            return;
        }

        $this->writer->append([
            'id' => bin2hex(random_bytes(6)),
            'recorded_at_ms' => (int) floor(microtime(true) * 1000),
            'request' => [
                'method' => strtoupper($request->getMethod()),
                'path' => $request->getRequestUri(),
                'headers' => $this->sanitizer->headers(self::headers($request->headers)),
                'body' => $this->sanitizer->body(Body::decode($requestBytes, $request->headers->get('content-type'))),
            ],
            'response' => [
                'status' => $response->getStatusCode(),
                'headers' => $this->sanitizer->headers(self::headers($response->headers)),
                'body' => $this->sanitizer->body(Body::decode($responseBytes, $response->headers->get('content-type'))),
                'latency_ms' => max(0, $latencyMs),
            ],
        ]);
    }

    /** @return array<string, string> */
    private static function headers(HeaderBag $bag): array
    {
        $headers = [];
        foreach ($bag->all() as $name => $values) {
            $headers[strtolower((string) $name)] = implode(', ', array_map('strval', $values));
        }
        ksort($headers);

        return $headers;
    }
}
