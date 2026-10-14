<?php

declare(strict_types=1);

namespace Echoproof\Laravel\Tests;

final class ReplayProductionTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['env'] = 'production';
        $app['config']->set('app.env', 'production');
        $app['config']->set('echoproof.enabled', false);
        $app['config']->set('echoproof.replay', true);
    }

    public function test_replay_mode_is_refused_in_production(): void
    {
        $this->postJson('/__echoproof/fixtures/ex1', ['outbound' => []])->assertNotFound();
    }
}
