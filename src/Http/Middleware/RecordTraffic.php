<?php

declare(strict_types=1);

namespace Echoproof\Laravel\Http\Middleware;

use Closure;
use Echoproof\Laravel\Recorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RecordTraffic
{
    private const STARTED = 'echoproof.started';

    public function __construct(private readonly Recorder $recorder)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->recorder->wants($request)) {
            $request->attributes->set(self::STARTED, hrtime(true));
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $started = $request->attributes->get(self::STARTED);
        if (! is_int($started)) {
            return;
        }
        try {
            $this->recorder->record($request, $response, intdiv(hrtime(true) - $started, 1_000_000));
        } catch (Throwable $error) {
            Log::warning('echoproof: failed to record exchange', ['error' => $error->getMessage()]);
        }
    }
}
