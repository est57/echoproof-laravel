<?php

declare(strict_types=1);

namespace Echoproof\Laravel\Tests;

use Echoproof\Laravel\EchoproofServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected string $snapshot;

    protected function setUp(): void
    {
        $this->snapshot = sys_get_temp_dir().'/echoproof-'.bin2hex(random_bytes(4)).'/traffic.jsonl';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        @unlink($this->snapshot);
        @rmdir(dirname($this->snapshot));
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [EchoproofServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('echoproof.enabled', true);
        $app['config']->set('echoproof.path', $this->snapshot);
    }

    /** @return list<array<string, mixed>> */
    protected function exchanges(): array
    {
        if (! file_exists($this->snapshot)) {
            return [];
        }
        $lines = array_filter(explode("\n", (string) file_get_contents($this->snapshot)));

        return array_values(array_map(fn (string $line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $lines));
    }

    protected function rawLines(): array
    {
        return array_values(array_filter(explode("\n", (string) file_get_contents($this->snapshot))));
    }
}
