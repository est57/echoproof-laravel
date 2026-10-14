<?php

declare(strict_types=1);

namespace Echoproof\Laravel;

use Echoproof\Laravel\Http\Middleware\RecordTraffic;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class EchoproofServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/echoproof.php', 'echoproof');

        $this->app->singleton(Recorder::class, function ($app): Recorder {
            $config = $app['config']->get('echoproof');

            return new Recorder(
                $config,
                new Sanitizer($config['redact_headers'] ?? [], $config['redact_fields'] ?? []),
                new SnapshotWriter($config['path']),
            );
        });
        $this->app->singleton(RecordTraffic::class);
    }

    public function boot(Router $router): void
    {
        $this->publishes([__DIR__.'/../config/echoproof.php' => config_path('echoproof.php')], 'echoproof-config');
        $router->aliasMiddleware('echoproof', RecordTraffic::class);

        $config = $this->app['config'];
        if ($config->get('echoproof.enabled') && $config->get('echoproof.global')) {
            $this->app->make(Kernel::class)->prependMiddleware(RecordTraffic::class);
        }
    }
}
