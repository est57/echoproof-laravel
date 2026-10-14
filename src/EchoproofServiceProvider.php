<?php

declare(strict_types=1);

namespace Echoproof\Laravel;

use Echoproof\Laravel\Http\Controllers\ReplayController;
use Echoproof\Laravel\Http\Middleware\RecordTraffic;
use Echoproof\Laravel\Http\Middleware\ReplayOutbound;
use Echoproof\Laravel\Outbound\Encoder;
use Echoproof\Laravel\Outbound\OutboundLog;
use Echoproof\Laravel\Replay\FixtureStore;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

final class EchoproofServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/echoproof.php', 'echoproof');

        $this->app->singleton(Sanitizer::class, function ($app): Sanitizer {
            $config = $app['config']->get('echoproof');

            return new Sanitizer($config['redact_headers'] ?? [], $config['redact_fields'] ?? []);
        });
        $this->app->singleton(Recorder::class, fn ($app) => new Recorder(
            $app['config']->get('echoproof'),
            $app->make(Sanitizer::class),
            new SnapshotWriter($app['config']->get('echoproof.path')),
        ));
        $this->app->singleton(Encoder::class);
        $this->app->singleton(OutboundLog::class);
        $this->app->singleton(FixtureStore::class, fn ($app) => new FixtureStore($app['config']->get('echoproof.replay_path')));
        $this->app->singleton(RecordTraffic::class);
    }

    public function boot(Router $router, Dispatcher $events): void
    {
        $this->publishes([__DIR__.'/../config/echoproof.php' => config_path('echoproof.php')], 'echoproof-config');
        $router->aliasMiddleware('echoproof', RecordTraffic::class);

        $config = $this->app['config'];
        if ($config->get('echoproof.enabled')) {
            $events->listen(ResponseReceived::class, [OutboundLog::class, 'received']);
            $events->listen(ConnectionFailed::class, [OutboundLog::class, 'failed']);
            if ($config->get('echoproof.global')) {
                $this->app->make(Kernel::class)->prependMiddleware(RecordTraffic::class);
            }
        }

        if ($config->get('echoproof.replay')) {
            $this->bootReplay($router);
        }
    }

    private function bootReplay(Router $router): void
    {
        if ($this->app->environment('production')) {
            Log::critical('echoproof: ECHOPROOF_REPLAY is ignored when APP_ENV=production');

            return;
        }
        $this->app->make(Factory::class)->preventStrayRequests();
        $this->app->make(Kernel::class)->prependMiddleware(ReplayOutbound::class);
        $router->post('__echoproof/fixtures/{id}', [ReplayController::class, 'fixtures']);
        $router->get('__echoproof/outbound/{id}', [ReplayController::class, 'outbound']);
    }
}
