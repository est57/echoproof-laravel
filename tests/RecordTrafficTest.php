<?php

declare(strict_types=1);

namespace Echoproof\Laravel\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

final class RecordTrafficTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::post('/orders', fn (Request $request) => response()->json([
            'id' => 7,
            'total' => 1.0,
            'meta' => new \stdClass,
            'items' => [],
            'user' => ['name' => 'kasir', 'pin_hash' => 'pbkdf2$x', 'max_tokens' => 10],
        ], 201));
        Route::get('/health', fn () => response('ok', 200, ['content-type' => 'text/plain']));
        Route::get('/logo', fn () => response("\xff\xd8\xff", 200, ['content-type' => 'image/jpeg']));
        Route::get('/up', fn () => 'up');
        Route::get('/price', fn () => response('{"total":1.0,"meta":{},"items":[]}', 200, ['content-type' => 'application/json']));
    }

    public function test_records_json_exchange_in_snapshot_format(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer secret-value', 'X-Terminal' => 'T1'])
            ->postJson('/orders?branch=3', ['items' => [['sku' => 'A1', 'qty' => 2]], 'password' => 'hunter2'])
            ->assertCreated();

        [$exchange] = $this->exchanges();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $exchange['id']);
        $this->assertSame('POST', $exchange['request']['method']);
        $this->assertSame('/orders?branch=3', $exchange['request']['path']);
        $this->assertSame('[REDACTED]', $exchange['request']['headers']['authorization']);
        $this->assertSame('T1', $exchange['request']['headers']['x-terminal']);
        $this->assertSame('[REDACTED]', $exchange['request']['body']['json']['password']);
        $this->assertSame(2, $exchange['request']['body']['json']['items'][0]['qty']);
        $this->assertSame(201, $exchange['response']['status']);
        $this->assertSame('[REDACTED]', $exchange['response']['body']['json']['user']['pin_hash']);
        $this->assertSame(10, $exchange['response']['body']['json']['user']['max_tokens']);
        $this->assertIsInt($exchange['response']['latency_ms']);
    }

    public function test_keeps_empty_objects_and_float_zero_fraction(): void
    {
        $this->get('/price');

        $line = $this->rawLines()[0];
        $this->assertStringContainsString('"meta":{}', $line);
        $this->assertStringContainsString('"items":[]', $line);
        $this->assertStringContainsString('"total":1.0', $line);
    }

    public function test_encodes_text_binary_and_empty_bodies(): void
    {
        $this->get('/health');
        $this->get('/logo');

        [$health, $logo] = $this->exchanges();
        $this->assertSame('empty', $health['request']['body']);
        $this->assertSame(['text' => 'ok'], $health['response']['body']);
        $this->assertSame(['base64' => base64_encode("\xff\xd8\xff")], $logo['response']['body']);
    }

    public function test_skips_excluded_paths(): void
    {
        $this->get('/up');

        $this->assertSame([], $this->exchanges());
    }

    public function test_only_limits_recorded_paths(): void
    {
        config(['echoproof.only' => ['orders*']]);
        $this->get('/health');
        $this->postJson('/orders', []);

        $this->assertSame(['/orders'], array_map(fn ($e) => $e['request']['path'], $this->exchanges()));
    }

    public function test_snapshot_file_is_private(): void
    {
        $this->get('/health');

        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->snapshot)), -4));
    }
}
