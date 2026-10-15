<?php

declare(strict_types=1);

namespace Echoproof\Laravel\Http\Middleware;

use Closure;
use Echoproof\Laravel\Outbound\Encoder;
use Echoproof\Laravel\Replay\FixtureStore;
use Echoproof\Laravel\Replay\Responder;
use Echoproof\Laravel\Sanitizer;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReplayOutbound
{
    public const EXCHANGE_HEADER = 'x-echoproof-exchange';

    public const OUTBOUND_HEADER = 'x-echoproof-outbound';

    public function __construct(
        private readonly FixtureStore $store,
        private readonly Encoder $encoder,
        private readonly Factory $http,
        private readonly Sanitizer $sanitizer,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) $request->headers->get(self::EXCHANGE_HEADER, '');
        if ($id === '' || ! FixtureStore::validId($id) || str_starts_with($request->path(), '__echoproof/')) {
            return $next($request);
        }

        $this->sanitizer->reset();
        $responder = new Responder($this->store->takeFixtures($id), $this->encoder);
        $this->http->fake(fn ($request) => $responder($request));
        $response = $next($request);

        $captured = $this->sanitizer->scrub($responder->captured());
        if ($captured !== []) {
            $this->store->putCaptured($id, $captured);
        }
        $response->headers->set(self::OUTBOUND_HEADER, (string) count($captured));

        return $response;
    }
}
