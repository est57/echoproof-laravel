<?php

declare(strict_types=1);

namespace Echoproof\Laravel\Tests;

use Illuminate\Support\Facades\Route;

final class DisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('echoproof.enabled', false);
    }

    protected function defineRoutes($router): void
    {
        Route::get('/health', fn () => 'ok');
    }

    public function test_records_nothing_when_disabled(): void
    {
        $this->get('/health')->assertOk();

        $this->assertFileDoesNotExist($this->snapshot);
    }
}
