<?php

declare(strict_types=1);

namespace Echoproof\Laravel\Outbound;

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;

final class OutboundLog
{
    private bool $active = false;

    /** @var list<array> */
    private array $calls = [];

    public function __construct(private readonly Encoder $encoder)
    {
    }

    public function start(): void
    {
        $this->active = true;
        $this->calls = [];
    }

    /** @return list<array> */
    public function finish(): array
    {
        $calls = $this->calls;
        $this->active = false;
        $this->calls = [];

        return $calls;
    }

    public function received(ResponseReceived $event): void
    {
        if ($this->active) {
            $this->calls[] = $this->encoder->call($event->request, $event->response);
        }
    }

    public function failed(ConnectionFailed $event): void
    {
        if ($this->active) {
            $this->calls[] = $this->encoder->call($event->request, null);
        }
    }
}
