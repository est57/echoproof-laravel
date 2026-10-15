<?php

declare(strict_types=1);

namespace Echoproof\Laravel\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

final class OutboundRecordingTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::post('/checkout', function () {
            $charge = Http::withToken('sk_live_secret')
                ->post('https://pay.example.test/v2/charges?api_key=k-123456789&currency=IDR', ['amount' => 54000, 'card_token' => 'tok_1'])
                ->json();

            return response()->json(['paid' => $charge['status'] === 'succeeded', 'charge' => $charge['id']]);
        });
        Route::get('/plain', fn () => 'ok');
    }

    public function test_records_outbound_calls_with_redaction(): void
    {
        Http::fake(['pay.example.test/*' => Http::response(['id' => 'ch_9', 'status' => 'succeeded'], 201)]);

        $this->postJson('/checkout')->assertOk()->assertJson(['paid' => true]);

        [$exchange] = $this->exchanges();
        $this->assertCount(1, $exchange['outbound']);
        [$call] = $exchange['outbound'];
        $this->assertSame('POST', $call['request']['method']);
        $this->assertSame('https://pay.example.test/v2/charges?api_key=%5BREDACTED%5D&currency=IDR', $call['request']['url']);
        $this->assertSame('[REDACTED]', $call['request']['headers']['authorization']);
        $this->assertSame(54000, $call['request']['body']['json']['amount']);
        $this->assertSame('[REDACTED]', $call['request']['body']['json']['card_token']);
        $this->assertSame(201, $call['response']['status']);
        $this->assertSame('ch_9', $call['response']['body']['json']['id']);
    }

    public function test_secrets_echoed_by_a_third_party_are_erased(): void
    {
        Http::fake(['pay.example.test/*' => Http::response([
            'id' => 'ch_9',
            'status' => 'succeeded',
            'url' => 'https://pay.example.test/v2/charges?api_key=k-123456789&currency=IDR',
            'headers' => ['Authorization' => 'Bearer sk_live_secret'],
        ], 201)]);

        $this->postJson('/checkout?callback_token=cb_live_7f6e5d4c')->assertOk();

        $raw = $this->rawLines()[0];
        $this->assertStringNotContainsString('k-123456789', $raw);
        $this->assertStringNotContainsString('sk_live_secret', $raw);
        $this->assertStringNotContainsString('cb_live_7f6e5d4c', $raw);
        $this->assertStringContainsString('currency=IDR', $raw);
        $this->assertStringContainsString('ch_9', $raw);
    }

    public function test_exchanges_without_outbound_calls_have_no_outbound_field(): void
    {
        $this->get('/plain');

        $this->assertArrayNotHasKey('outbound', $this->exchanges()[0]);
    }
}
