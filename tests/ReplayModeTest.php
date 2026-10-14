<?php

declare(strict_types=1);

namespace Echoproof\Laravel\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

final class ReplayModeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('echoproof.enabled', false);
        $app['config']->set('echoproof.replay', true);
        $app['config']->set('echoproof.replay_path', dirname($this->snapshot).'/replay');
    }

    protected function defineRoutes($router): void
    {
        Route::post('/checkout', function () {
            $first = Http::post('https://pay.example.test/v2/charges', ['amount' => 54000]);
            $second = Http::get('https://wa.example.test/send');

            return ['charge' => $first->json('id'), 'charge_status' => $first->status(), 'wa_status' => $second->status()];
        });
    }

    private function fixtures(string $id): array
    {
        return ['outbound' => [[
            'request' => ['method' => 'POST', 'url' => 'https://pay.example.test/v2/charges?api_key=%5BREDACTED%5D', 'headers' => [], 'body' => ['json' => ['amount' => 54000]]],
            'response' => ['status' => 201, 'headers' => ['content-type' => 'application/json'], 'body' => ['json' => ['id' => 'ch_9']]],
        ]]];
    }

    public function test_answers_recorded_calls_and_flags_unrecorded_ones(): void
    {
        $this->postJson('/__echoproof/fixtures/ex1', $this->fixtures('ex1'))->assertOk()->assertJson(['stored' => 1]);

        $this->withHeaders(['x-echoproof-exchange' => 'ex1'])->postJson('/checkout')
            ->assertOk()
            ->assertJson(['charge' => 'ch_9', 'charge_status' => 201, 'wa_status' => 599])
            ->assertHeader('x-echoproof-outbound', '2');

        $fetched = $this->getJson('/__echoproof/outbound/ex1')->assertOk();
        $this->assertStringNotContainsString('"headers":[]', $fetched->getContent());
        $captured = $fetched->json('outbound');
        $this->assertCount(2, $captured);
        $this->assertSame(54000, $captured[0]['request']['body']['json']['amount']);
        $this->assertArrayNotHasKey('unrecorded', $captured[0]);
        $this->assertTrue($captured[1]['unrecorded']);
        $this->assertSame([], $this->getJson('/__echoproof/outbound/ex1')->json('outbound'));
    }

    public function test_blocks_outbound_calls_without_an_exchange_header(): void
    {
        $this->postJson('/checkout')->assertStatus(500);
    }

    public function test_rejects_invalid_ids_and_bad_payloads(): void
    {
        $this->postJson('/__echoproof/fixtures/..%2Fetc', $this->fixtures('x'))->assertStatus(404);
        $this->postJson('/__echoproof/fixtures/'.str_repeat('a', 65), $this->fixtures('x'))->assertStatus(422);
        $this->postJson('/__echoproof/fixtures/ex2', ['outbound' => 'nope'])->assertStatus(422);
    }

    public function test_requires_the_token_when_configured(): void
    {
        config(['echoproof.replay_token' => 's3cret']);

        $this->postJson('/__echoproof/fixtures/ex3', $this->fixtures('ex3'))->assertStatus(403);
        $this->getJson('/__echoproof/outbound/ex3')->assertStatus(403);
        $this->withHeaders(['x-echoproof-token' => 'wrong'])->getJson('/__echoproof/outbound/ex3')->assertStatus(403);
        $this->withHeaders(['x-echoproof-token' => 's3cret'])->postJson('/__echoproof/fixtures/ex3', $this->fixtures('ex3'))->assertOk();
    }
}
