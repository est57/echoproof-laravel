<?php

declare(strict_types=1);

namespace Echoproof\Laravel\Replay;

use Echoproof\Laravel\Body;
use Echoproof\Laravel\Outbound\Encoder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;

final class Responder
{
    public const UNRECORDED_STATUS = 599;

    /** @var array<string, list<array>> */
    private array $queues = [];

    /** @var list<array> */
    private array $captured = [];

    /** @param  list<array>  $fixtures */
    public function __construct(array $fixtures, private readonly Encoder $encoder)
    {
        foreach ($fixtures as $call) {
            $request = $call['request'] ?? [];
            $key = self::key((string) ($request['method'] ?? ''), (string) ($request['url'] ?? ''));
            $this->queues[$key][] = $call['response'] ?? [];
        }
    }

    public function __invoke(Request $request): PromiseInterface
    {
        $key = self::key($request->method(), $request->url());
        $recorded = isset($this->queues[$key]) ? array_shift($this->queues[$key]) : null;
        $call = ['request' => $this->encoder->request($request)];
        if ($recorded === null) {
            $call['response'] = ['status' => self::UNRECORDED_STATUS, 'headers' => new \stdClass, 'body' => ['text' => 'echoproof: unrecorded outbound call']];
            $call['unrecorded'] = true;
            $this->captured[] = $call;

            return Factory::response('echoproof: unrecorded outbound call', self::UNRECORDED_STATUS);
        }
        $call['response'] = $recorded;
        $this->captured[] = $call;

        return Factory::response(
            Body::encode($recorded['body'] ?? 'empty'),
            (int) ($recorded['status'] ?? 200),
            array_diff_key((array) ($recorded['headers'] ?? []), ['content-length' => true, 'transfer-encoding' => true]),
        );
    }

    /** @return list<array> */
    public function captured(): array
    {
        return $this->captured;
    }

    private static function key(string $method, string $url): string
    {
        $parts = parse_url($url) ?: [];

        return strtoupper($method).' '.strtolower(($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '')).($parts['path'] ?? '/');
    }
}
